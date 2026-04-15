<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Config;

/**
 * Immutable value object that carries all configuration
 * for the generic HttpSmsProvider.
 *
 * Supports three body formats:
 *   - 'json' : sends application/json  (most modern REST APIs)
 *   - 'form' : sends application/x-www-form-urlencoded  (legacy gateways)
 *   - 'raw'  : sends a plain text body  (rare, but supported)
 *
 * Body template values and header values may contain placeholders:
 *   {to}        → replaced by the recipient phone number
 *   {message}   → replaced by the SMS body text
 *   Any key from NotificationMessage::getContext() is also available as {key}
 *
 * Example body template (JSON gateway):
 *   ['msisdn' => '{to}', 'text' => '{message}', 'from' => 'CEP']
 *
 * Example success_response_path (optional deep-check):
 *   'result.status'  checks  $response['result']['status'] === $success_response_value
 */
class HttpSmsProviderConfig
{
    /**
     * @param string      $url                  Full API endpoint URL
     * @param string      $method               HTTP method: 'POST' | 'GET'
     * @param array       $headers              Static + templated HTTP headers
     * @param array       $bodyTemplate         Key/value pairs with {placeholders}
     * @param string      $bodyFormat           'json' | 'form' | 'raw'
     * @param array       $successHttpCodes     HTTP status codes treated as success
     * @param string|null $successResponsePath  Dot-notation JSON path to verify (optional)
     * @param string|null $successResponseValue Expected value at the path above (optional)
     */
    public function __construct(
        private readonly string  $url,
        private readonly string  $method               = 'POST',
        private readonly array   $headers              = [],
        private readonly array   $bodyTemplate         = [],
        private readonly string  $bodyFormat           = 'json',
        private readonly array   $successHttpCodes     = [200, 201],
        private readonly ?string $successResponsePath  = null,
        private readonly ?string $successResponseValue = null,
    ) {
        if (!in_array(strtoupper($method), ['GET', 'POST'], true)) {
            throw new \InvalidArgumentException('HttpSmsProviderConfig: method must be GET or POST.');
        }
        if (!in_array($bodyFormat, ['json', 'form', 'raw'], true)) {
            throw new \InvalidArgumentException('HttpSmsProviderConfig: bodyFormat must be json, form, or raw.');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(sprintf('HttpSmsProviderConfig: invalid URL "%s".', $url));
        }
    }

    public function getUrl(): string                  { return $this->url; }
    public function getMethod(): string               { return strtoupper($this->method); }
    public function getHeaders(): array               { return $this->headers; }
    public function getBodyTemplate(): array          { return $this->bodyTemplate; }
    public function getBodyFormat(): string           { return $this->bodyFormat; }
    public function getSuccessHttpCodes(): array      { return $this->successHttpCodes; }
    public function getSuccessResponsePath(): ?string { return $this->successResponsePath; }
    public function getSuccessResponseValue(): ?string{ return $this->successResponseValue; }
}
