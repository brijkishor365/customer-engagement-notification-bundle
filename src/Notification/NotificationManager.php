<?php

namespace CustomerEngagementNotificationBundle\Notification;

use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use Psr\Log\LoggerInterface;

/**
 * Central notification manager for sending messages through various channels.
 *
 * This class orchestrates the sending of notifications by coordinating with
 * the NotificationFactory to get appropriate channels and handling delivery
 * with proper logging and error handling.
 */
class NotificationManager
{
    /**
     * NotificationManager constructor.
     *
     * @param NotificationFactory $factory The factory for creating notification channels
     * @param LoggerInterface $logger PSR-3 logger for recording notification events
     */
    public function __construct(
        private readonly NotificationFactory $factory,
        private readonly LoggerInterface     $logger
    ) {}

    /**
     * Send a notification via a single channel.
     *
     * Validates that the channel supports the message before attempting delivery.
     * Logs the result and any errors that occur during sending.
     *
     * @param NotificationMessage $message The notification message to send
     * @return bool True if the notification was sent successfully, false otherwise
     */
    public function send(NotificationMessage $message): bool
    {
        try {
            $channel = $this->factory->getChannel($message->getChannel());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send notification via {channel}: {error}', [
                'channel' => $message->getChannel(),
                'error'   => $e->getMessage(),
            ]);
            return false;
        }

        if (!$channel->supports($message)) {
            $this->logger->warning('Channel {channel} does not support this message.', [
                'channel' => $message->getChannel(),
            ]);
            return false;
        }

        try {
            $result = $channel->send($message);
            $this->logger->info('Notification sent via {channel} to {recipient}', [
                'channel'   => $message->getChannel(),
                'recipient' => $message->getRecipient(),
            ]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send notification via {channel}: {error}', [
                'channel' => $message->getChannel(),
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Broadcast the same message across multiple channels.
     *
     * Sends the same notification content to multiple channels simultaneously.
     * Each channel gets its own NotificationMessage instance with the appropriate
     * channel name set. Channel-specific recipients can be specified in the context
     * using keys like 'email_recipient', 'sms_recipient', etc.
     *
     * @param NotificationMessage $message The base notification message to broadcast
     * @param array $channels List of channel names to broadcast to
     * @return array Map of channel name => boolean success result
     */
    public function broadcast(NotificationMessage $message, array $channels): array
    {
        $results = [];
        $context = $message->getContext();

        foreach ($channels as $channelName) {
            // Use channel-specific recipient if available, otherwise use base recipient
            $recipientKey = $channelName . '_recipient';
            $recipient = $context[$recipientKey] ?? $message->getRecipient();

            $broadcastMessage = new NotificationMessage(
                $recipient,
                $message->getSubject(),
                $message->getBody(),
                $channelName,
                $context
            );
            $results[$channelName] = $this->send($broadcastMessage);
        }

        return $results;
    }
}
