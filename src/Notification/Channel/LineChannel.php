<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Channel;

use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\LineProviderInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

/**
 * LINE notification channel implementation.
 *
 * This channel delegates LINE message delivery to LineProviderInterface and
 * validates LINE identifiers before sending.
 */
class LineChannel implements NotificationChannelInterface
{
    /**
     * @param LineProviderInterface $provider Provider implementation for LINE messaging
     */
    public function __construct(private readonly LineProviderInterface $provider) {}

    /**
     * Send a LINE message via the configured provider.
     *
     * @param NotificationMessage $message The notification message to send
     * @return bool True on success, false on failure
     */
    public function send(NotificationMessage $message): bool
    {
        return $this->provider->sendMessage(
            to:      $message->getRecipient(),
            message: $message->getBody(),
            context: $message->getContext()
        );
    }

    /**
     * Returns the channel identifier.
     *
     * @return string The channel name ('line')
     */
    public function getName(): string { return 'line'; }

    /**
     * Check if the given message is supported by the LINE channel.
     *
     * LINE recipients must be valid LINE IDs (user, group, or room identifiers).
     *
     * @param NotificationMessage $message The message to validate
     * @return bool True if the message can be delivered via LINE
     */
    public function supports(NotificationMessage $message): bool
    {
        // LINE userIds start with U, groupIds with C, roomIds with R
        // followed by 32 UPPERCASE hex characters (alphanumeric)
        // LINE IDs are case-sensitive and use only uppercase
        return (bool) preg_match('/^[UCR][0-9A-F]{32}$/', $message->getRecipient());
    }
}
