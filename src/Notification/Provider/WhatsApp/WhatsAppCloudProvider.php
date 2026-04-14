<?php

namespace CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp;

use CustomerEngagementNotificationBundle\Notification\Contract\WhatsAppProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * WhatsApp Business Cloud API provider (Meta Graph API v18+).
 *
 * Credentials required:
 *   - Phone Number ID    (found in Meta Developer Console → WhatsApp → Phone numbers)
 *   - System User Token  (permanent token from Meta Business Manager → System Users)
 *
 * Outbound message rules (enforced by Meta):
 *   ✅ Template messages   — always allowed (requires pre-approved template)
 *   ✅ Text messages       — only within 24-hour user-initiated session window
 *   ✅ Media messages      — image / document / video via public URL
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/messages
 */
class WhatsAppCloudProvider implements WhatsAppProviderInterface
{
    private const API_BASE = 'https://graph.facebook.com/v18.0';

    /**
     * WhatsAppCloudProvider constructor.
     *
     * @param HttpClientInterface $httpClient HTTP client for making WhatsApp Cloud API requests
     * @param string $phoneNumberId WhatsApp Phone Number ID from Meta Developer Console
     * @param string $accessToken System User Access Token from Meta Business Manager
     * @param LoggerInterface $logger PSR-3 logger for recording WhatsApp message events
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $phoneNumberId,
        private readonly string              $accessToken,
        private readonly LoggerInterface     $logger,
    ) {}

    // ── WhatsAppProviderInterface ──────────────────────────────────────────

    public function send(string $to, WhatsAppMessage $message): bool
    {
        $url     = sprintf('%s/%s/messages', self::API_BASE, $this->phoneNumberId);
        $payload = $this->buildPayload($to, $message);

        $this->logger->debug('[whatsapp] Sending {type} message to {to}', [
            'type' => $message->getType(),
            'to'   => $this->maskPhone($to),
        ]);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 10,
                'max_redirects' => 3,
            ]);

            $statusCode   = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if ($statusCode !== 200) {
                $this->logger->error('[whatsapp] Failed HTTP {code}: {error}', [
                    'code'  => $statusCode,
                    'error' => $this->extractError($responseData),
                ]);
                return false;
            }

            $messageId = $responseData['messages'][0]['id'] ?? 'n/a';

            $this->logger->info('[whatsapp] Message sent to {to} — id: {id}', [
                'to' => $this->maskPhone($to),
                'id' => $messageId,
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[whatsapp] Transport error: {error}', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getProviderName(): string { return 'whatsapp_cloud'; }

    // ── Private ────────────────────────────────────────────────────────────

    private function buildPayload(string $to, WhatsAppMessage $message): array
    {
        // Normalise phone: strip leading + for Meta API (it expects digits only)
        $normalizedTo = ltrim($to, '+');

        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $normalizedTo,
        ];

        return match (true) {
            $message->isTemplate() => $base + $this->buildTemplatePayload($message),
            $message->isText()     => $base + $this->buildTextPayload($message),
            $message->isMedia()    => $base + $this->buildMediaPayload($message),
            default                => throw new \InvalidArgumentException(
                sprintf('WhatsAppCloudProvider: unsupported message type "%s".', $message->getType())
            ),
        };
    }

    private function buildTemplatePayload(WhatsAppMessage $message): array
    {
        $p = $message->getPayload();

        return [
            'type' => 'template',
            'template' => [
                'name'     => $p['name'],
                'language' => ['code' => $p['language']],
                'components' => $p['components'],
            ],
        ];
    }

    private function buildTextPayload(WhatsAppMessage $message): array
    {
        $p = $message->getPayload();

        return [
            'type' => 'text',
            'text' => [
                'body'        => $p['body'],
                'preview_url' => $p['preview_url'] ?? false,
            ],
        ];
    }

    private function buildMediaPayload(WhatsAppMessage $message): array
    {
        $p    = $message->getPayload();
        $type = $message->getType(); // 'image' | 'document' | 'video'

        // Validate URL to prevent SSRF attacks
        $this->validateMediaUrl($p['url'] ?? '');

        $media = ['link' => $p['url']];

        if (!empty($p['caption'])) {
            $media['caption'] = $p['caption'];
        }

        // filename is only relevant for documents
        if ($type === WhatsAppMessage::TYPE_DOCUMENT && !empty($p['filename'])) {
            $media['filename'] = $p['filename'];
        }

        return [
            'type' => $type,
            $type  => $media,
        ];
    }

    /**
     * Validate media URL to prevent SSRF attacks.
     * Ensures URL is:
     *   - Valid format
     *   - Uses HTTPS protocol
     *   - Does not point to private IP ranges
     */
    private function validateMediaUrl(string $url): void
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Media URL cannot be empty.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Media URL has invalid format.');
        }

        $parsed = parse_url($url);

        if (!$parsed) {
            throw new \InvalidArgumentException('Media URL could not be parsed.');
        }

        // Enforce HTTPS
        if (strtolower($parsed['scheme'] ?? '') !== 'https') {
            throw new \InvalidArgumentException('Media URL must use HTTPS protocol, got: ' . ($parsed['scheme'] ?? 'none'));
        }

        $host = $parsed['host'] ?? '';

        // Block private IP ranges (SSRF prevention)
        $privateIpPatterns = [
            '/^127\./',                     // 127.0.0.0/8 (localhost)
            '/^10\./',                      // 10.0.0.0/8 (private)
            '/^192\.168\./',                // 192.168.0.0/16 (private)
            '/^172\.(1[6-9]|2\d|3[01])\./', // 172.16.0.0/12 (private)
            '/^169\.254\./',                // 169.254.0.0/16 (link-local)
            '/^localhost$/i',               // localhost
            '/^::1$/',                      // IPv6 loopback
            '/^fc[0-9a-f]{2}:/i',           // IPv6 unique local (fc00::/7)
        ];

        foreach ($privateIpPatterns as $pattern) {
            if (preg_match($pattern, $host)) {
                throw new \InvalidArgumentException(
                    sprintf('Media URL must not point to private IP address: %s', $host)
                );
            }
        }

        // Also check if it resolves to localhost/private via DNS (optional, but hardening)
        // Blocked for now due to performance concerns, but can be enabled if needed:
        // $ip = gethostbyname($host);
        // if ($ip === $host) return; // Not resolved - assume OK
        // if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        //     throw new \InvalidArgumentException("Media URL resolves to private IP: $ip");
        // }
    }

    private function extractError(array $data): string
    {
        return $data['error']['message']
            ?? ($data['error']['error_data']['details'] ?? 'unknown error');
    }

    /** Mask all but last 4 digits for safe logging */
    private function maskPhone(string $phone): string
    {
        $cleaned = ltrim($phone, '+');
        return strlen($cleaned) > 4
            ? str_repeat('*', strlen($cleaned) - 4) . substr($cleaned, -4)
            : '****';
    }
}
