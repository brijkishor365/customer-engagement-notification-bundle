<?php

namespace CustomerEngagementNotificationBundle\Notification\Provider\Push;

use CustomerEngagementNotificationBundle\Notification\Contract\PushProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Firebase Cloud Messaging — HTTP v1 API.
 *
 * Uses the modern FCM v1 endpoint. The legacy /fcm/send endpoint was
 * deprecated June 2023 and shut down June 2024.
 *
 * Target resolution (automatic):
 *   '/topics/news'                     → topic message
 *   '"sports" in topics && ...'        → condition broadcast
 *   any other string                   → single device token
 *
 * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages/send
 */
class FirebasePushProvider implements PushProviderInterface
{
    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    /**
     * FirebasePushProvider constructor.
     *
     * @param HttpClientInterface $httpClient HTTP client for making Firebase API requests
     * @param FirebaseCredentialProvider $credentials Provider for Firebase OAuth2 access tokens
     * @param LoggerInterface $logger PSR-3 logger for recording push notification events
     */
    public function __construct(
        private readonly HttpClientInterface        $httpClient,
        private readonly FirebaseCredentialProvider $credentials,
        private readonly LoggerInterface             $logger,
    ) {}

    public function sendPush(
        string $deviceToken,
        string $title,
        string $body,
        array  $data = []
    ): bool {
        $url = sprintf(self::FCM_ENDPOINT, $this->credentials->getProjectId());

        $this->logger->debug('[firebase] Sending push to {token}', [
            'token' => $this->maskToken($deviceToken),
        ]);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->credentials->getAccessToken(),
                    'Content-Type'  => 'application/json',
                ],
                'json' => ['message' => $this->buildMessage($deviceToken, $title, $body, $data)],
                'timeout' => 10,
                'max_redirects' => 3,
            ]);

            $statusCode   = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if ($statusCode !== 200) {
                $this->logger->error('[firebase] Push failed HTTP {code}: {error}', [
                    'code'  => $statusCode,
                    'error' => $responseData['error']['message'] ?? 'unknown',
                ]);
                return false;
            }

            $this->logger->info('[firebase] Push sent — message id: {id}', [
                'id' => $responseData['name'] ?? 'n/a',
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[firebase] Transport error: {error}', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getProviderName(): string { return 'firebase_fcm_v1'; }

    // ── Private ────────────────────────────────────────────────────────────

    private function buildMessage(string $token, string $title, string $body, array $data): array
    {
        $message = [
            'notification' => ['title' => $title, 'body' => $body],
            // Android: high priority + default channel
            'android' => [
                'priority'     => 'high',
                'notification' => ['channel_id' => 'default'],
            ],
            // APNS: high priority + sound
            'apns' => [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ];

        // Resolve target automatically
        if (str_starts_with($token, '/topics/')) {
            $message['topic'] = substr($token, strlen('/topics/'));
        } elseif (str_contains($token, 'in topics')) {
            $message['condition'] = $token;
        } else {
            $message['token'] = $token;
        }

        // FCM data payload — all values must be strings
        if (!empty($data)) {
            $message['data'] = array_map('strval', $data);
        }

        return $message;
    }

    private function maskToken(string $token): string
    {
        return strlen($token) > 8
            ? str_repeat('*', max(0, strlen($token) - 8)) . substr($token, -8)
            : '********';
    }
}
