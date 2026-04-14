<?php

namespace CustomerEngagementNotificationBundle\Notification\Channel;

use CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use CustomerEngagementNotificationBundle\Notification\Contract\PushProviderInterface;
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

/**
 * Push notification channel implementation for Firebase Cloud Messaging.
 *
 * This channel handles push notifications by delegating to a PushProviderInterface
 * implementation. It validates Firebase Cloud Messaging (FCM) targets including:
 * - Topic subscriptions (/topics/{name})
 * - Conditional expressions with topic filters
 * - Device registration tokens
 */
class PushChannel implements NotificationChannelInterface
{
    /**
     * PushChannel constructor.
     *
     * @param PushProviderInterface $provider The push provider to use for sending notifications
     */
    public function __construct(private readonly PushProviderInterface $provider) {}

    /**
     * Sends a push notification using the configured provider.
     *
     * @param NotificationMessage $message The notification message to send
     * @return bool True if the push notification was sent successfully, false otherwise
     */
    public function send(NotificationMessage $message): bool
    {
        return $this->provider->sendPush(
            deviceToken: $message->getRecipient(),
            title:       $message->getSubject(),
            body:        $message->getBody(),
            data:        $message->getContext()
        );
    }

    /**
     * Returns the channel name identifier.
     *
     * @return string The channel name ('push')
     */
    public function getName(): string { return 'push'; }

    /**
     * Determines if this channel supports the given notification message.
     *
     * Validates Firebase Cloud Messaging (FCM) targets according to FCM specifications:
     * - Topic: /topics/{topic_name} where topic_name contains only alphanumeric, hyphen, underscore
     * - Condition: Expressions with 'in topics', '&&', '||' operators (10-500 characters)
     * - Device token: 100-255 characters of base64url-safe characters
     *
     * @param NotificationMessage $message The notification message to validate
     * @return bool True if the message is supported by this channel, false otherwise
     */
    public function supports(NotificationMessage $message): bool
    {
        $r = $message->getRecipient();
        
        // Valid FCM targets:
        // 1. Topic: /topics/{topic_name}
        // 2. Condition: e.g. '"weather" in topics'
        // 3. Device token: typically 152+ printable ASCII chars
        
        if (str_starts_with($r, '/topics/')) {
            // Validate topic name (alphanumeric, hyphen, underscore only)
            $topicName = substr($r, 8);
            return !empty($topicName) && preg_match('/^[a-zA-Z0-9_-]+$/', $topicName);
        }
        
        // Condition message or expression like "'weather' in topics" or "platform = 'ios' && ..."
        if (strpos($r, 'in topics') !== false || strpos($r, '&&') !== false || strpos($r, '||') !== false) {
            // Basic validation: reasonable length, not too short
            return strlen($r) >= 10 && strlen($r) <= 500;
        }
        
        // Device token - FCM tokens are typically 152-160 chars of base64-like chars
        // Must be at least 100 chars and max 255 chars
        return strlen($r) >= 100 && strlen($r) <= 255 && !preg_match('/[^a-zA-Z0-9_:-]/', $r);
    }
}
