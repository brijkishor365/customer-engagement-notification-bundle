<?php

namespace CustomerEngagementNotificationBundle\Notification\Provider\Sms;

use CustomerEngagementNotificationBundle\Notification\Config\HttpSmsProviderConfig;
use CustomerEngagementNotificationBundle\Notification\Contract\SmsProviderInterface;
use CustomerEngagementNotificationBundle\Notification\Resolver\BodyTemplateResolver;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generic HTTP-based SMS provider.
 *
 * Works with any SMS gateway that exposes a JSON REST, form-encoded,
 * or raw HTTP endpoint. All configuration is injected via HttpSmsProviderConfig,
 * so this class itself never changes when switching providers.
 *
 * Flow:
 *   1. Resolve {to} and {message} placeholders in headers + body template
 *   2. Build the Symfony HttpClient request options
 *   3. Execute the HTTP call
 *   4. Validate: HTTP status code ∈ successHttpCodes
 *   5. Validate (optional): response JSON path matches expected value
 */
class HttpSmsProvider implements SmsProviderInterface
{
    /**
     * HttpSmsProvider constructor.
     *
     * @param HttpClientInterface $httpClient HTTP client for making SMS API requests
     * @param HttpSmsProviderConfig $config Configuration for the SMS provider endpoint
     * @param BodyTemplateResolver $resolver Resolves placeholders in request body templates
     * @param LoggerInterface $logger PSR-3 logger for recording SMS sending events
     * @param string $providerName Name identifier for this provider instance
     */
    public function __construct(
        private readonly HttpClientInterface  $httpClient,
        private readonly HttpSmsProviderConfig $config,
        private readonly BodyTemplateResolver  $resolver,
        private readonly LoggerInterface       $logger,
        private readonly string                $providerName = 'http_default',
    ) {}

    // ── SmsProviderInterface ───────────────────────────────────────────────

    public function sendSms(string $to, string $body): bool
    {
        $options = $this->buildRequestOptions($to, $body);

        $this->logger->debug('[{provider}] Sending SMS to {to} via {url}', [
            'provider' => $this->providerName,
            'to'       => $to,
            'url'      => $this->config->getUrl(),
        ]);

        try {
            $response   = $this->httpClient->request(
                $this->config->getMethod(),
                $this->config->getUrl(),
                $options + ['timeout' => 10, 'max_redirects' => 3]
            );

            $statusCode = $response->getStatusCode();

            if (!in_array($statusCode, $this->config->getSuccessHttpCodes(), true)) {
                // Limit response body size to prevent log inflation
                $content = $response->getContent(false);
                $body = strlen($content) > 500 ? substr($content, 0, 500) . '...(truncated)' : $content;

                $this->logger->error('[{provider}] SMS failed — unexpected HTTP {code}', [
                    'provider' => $this->providerName,
                    'code'     => $statusCode,
                    'body'     => $body,
                ]);
                return false;
            }

            // Optional deep-check on the response body
            if ($this->config->getSuccessResponsePath() !== null) {
                return $this->validateResponseBody($response->toArray(false));
            }

            $this->logger->info('[{provider}] SMS sent successfully to {to}', [
                'provider' => $this->providerName,
                'to'       => $to,
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[{provider}] HTTP transport error: {error}', [
                'provider' => $this->providerName,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function buildRequestOptions(string $to, string $message): array
    {
        $resolvedHeaders = $this->resolver->resolve(
            $this->config->getHeaders(), $to, $message
        );

        $options = ['headers' => $resolvedHeaders];

        $resolvedBody = $this->resolver->resolve(
            $this->config->getBodyTemplate(), $to, $message
        );

        $options += match ($this->config->getBodyFormat()) {
            'json' => ['json' => $resolvedBody],
            'form' => ['body' => $resolvedBody],
            'raw'  => [
                'body'    => $this->resolver->resolveString(
                    implode('', $this->config->getBodyTemplate()), $to, $message
                ),
            ],
            default => ['json' => $resolvedBody],
        };

        return $options;
    }

    /**
     * Navigate a dot-notation path inside a decoded JSON response array.
     * e.g. path 'result.status' checks $data['result']['status']
     */
    private function validateResponseBody(array $data): bool
    {
        $path  = $this->config->getSuccessResponsePath();
        $keys  = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                $this->logger->warning('[{provider}] Response path "{path}" not found in response.', [
                    'provider' => $this->providerName,
                    'path'     => $path,
                ]);
                return false;
            }
            $value = $value[$key];
        }

        $expected = $this->config->getSuccessResponseValue();
        $matched  = ((string) $value === $expected);

        if (!$matched) {
            $this->logger->warning('[{provider}] Response check failed — expected "{expected}", got "{actual}"', [
                'provider' => $this->providerName,
                'expected' => $expected,
                'actual'   => $value,
            ]);
        }

        return $matched;
    }
}
