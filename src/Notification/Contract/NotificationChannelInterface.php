<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Contract;

use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

interface NotificationChannelInterface
{
    /**
     * Send a notification via this channel.
     */
    public function send(NotificationMessage $message): bool;

    /**
     * Returns the channel name (e.g. 'sms', 'email', 'push', 'line').
     */
    public function getName(): string;

    /**
     * Check if this channel supports the given message.
     */
    public function supports(NotificationMessage $message): bool;
}
