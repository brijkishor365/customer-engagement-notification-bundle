<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Provider\Sms;

use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\SmsProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Twilio-specific provider. Uses the Twilio Messages REST API directly.
 * Kept separate because Twilio has unique auth (Basic) and response shape.
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    private const API_BASE = 'https://api.twilio.com/2010-04-01';

    /**
     * TwilioSmsProvider constructor.
     *
     * @param HttpClientInterface $httpClient HTTP client for making Twilio API requests
     * @param string $accountSid Twilio Account SID from the Twilio Console
     * @param string $authToken Twilio Auth Token from the Twilio Console
     * @param string $fromNumber Twilio phone number to send SMS from (E.164 format)
     * @param LoggerInterface $logger PSR-3 logger for recording SMS sending events
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $accountSid,
        private readonly string              $authToken,
        private readonly string              $fromNumber,
        private readonly LoggerInterface     $logger,
    ) {}

    public function sendSms(string $to, string $body): bool
    {
        $url = sprintf('%s/Accounts/%s/Messages.json', self::API_BASE, $this->accountSid);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body'       => ['To' => $to, 'From' => $this->fromNumber, 'Body' => $body],
                'timeout' => 10,
                'max_redirects' => 3,
            ]);

            $data = $response->toArray(false);

            if (isset($data['error_code'])) {
                $this->logger->error('[twilio] Error {code}: {message}', [
                    'code'    => $data['error_code'],
                    'message' => $data['message'] ?? '',
                ]);
                return false;
            }

            $this->logger->info('[twilio] SMS sent successfully to {to}', [
                'to' => $this->maskPhoneNumber($to),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('[twilio] Failed to send SMS: {error}', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mask phone number for safe logging (show only last 4 digits).
     */
    private function maskPhoneNumber(string $phone): string
    {
        $cleaned = ltrim($phone, '+');
        return strlen($cleaned) > 4
            ? str_repeat('*', strlen($cleaned) - 4) . substr($cleaned, -4)
            : '****';
    }

    public function getProviderName(): string { return 'twilio'; }
}
