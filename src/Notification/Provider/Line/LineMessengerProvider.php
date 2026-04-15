<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Provider\Line;

use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\LineProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * LINE Messaging API — Push Message.
 *
 * Sends messages to a LINE user, group, or room.
 *
 * Simple text usage:
 *   sendMessage($userId, 'Hello!')
 *
 * Rich / Flex message usage — pass pre-built LINE objects in context:
 *   sendMessage($userId, 'alt text', ['messages' => [ [...flex object...] ]])
 *
 * @see https://developers.line.biz/en/reference/messaging-api/#send-push-message
 */
class LineMessengerProvider implements LineProviderInterface
{
    private const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    /**
     * LineMessengerProvider constructor.
     *
     * @param HttpClientInterface $httpClient HTTP client for making LINE Messaging API requests
     * @param string $channelAccessToken LINE Channel Access Token from LINE Developers Console
     * @param LoggerInterface $logger PSR-3 logger for recording LINE message events
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $channelAccessToken,
        private readonly LoggerInterface     $logger,
    ) {}

    public function sendMessage(string $to, string $message, array $context = []): bool
    {
        // If caller passes pre-built LINE message objects, use them directly
        $messages = $context['messages'] ?? [['type' => 'text', 'text' => $message]];

        $this->logger->debug('[line] Sending message to {to}', ['to' => $to]);

        try {
            $response = $this->httpClient->request('POST', self::PUSH_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->channelAccessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => ['to' => $to, 'messages' => $messages],
                'timeout' => 10,
                'max_redirects' => 3,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $body = $response->toArray(false);
                $this->logger->error('[line] Failed HTTP {code}: {msg}', [
                    'code' => $statusCode,
                    'msg'  => $body['message'] ?? 'unknown',
                ]);
                return false;
            }

            $this->logger->info('[line] Message sent to {to}', ['to' => $to]);
            return true;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[line] Transport error: {error}', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Convenience method for sending a LINE Flex Message (rich card layout).
     *
     * @param string $to           LINE userId / groupId
     * @param string $altText      Fallback text shown in chat list / notification
     * @param array  $flexContents Flex Message 'contents' object (bubble or carousel)
     */
    public function sendFlexMessage(string $to, string $altText, array $flexContents): bool
    {
        return $this->sendMessage($to, $altText, [
            'messages' => [[
                'type'     => 'flex',
                'altText'  => $altText,
                'contents' => $flexContents,
            ]],
        ]);
    }

    public function getProviderName(): string { return 'line_messenger'; }
}
