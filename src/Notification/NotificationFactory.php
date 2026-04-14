<?php

namespace CustomerEngagementNotificationBundle\Notification;

use CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Factory for notification channels.
 *
 * Channels are registered via Symfony service tagging and resolved by name.
 * This class also exposes discovery helpers for supported channels.
 */
class NotificationFactory
{
    /** @var NotificationChannelInterface[] */
    private array $channels = [];

    /**
     * NotificationFactory constructor.
     *
     * @param LoggerInterface $logger PSR-3 logger used for diagnostics and failures
     * @param iterable<NotificationChannelInterface> $channels Tagged notification channels
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        #[TaggedIterator('cen.notification.channel')]
        iterable $channels
    ) {
        foreach ($channels as $channel) {
            $this->channels[$channel->getName()] = $channel;
        }
    }

    /**
     * Register a notification channel with the factory.
     *
     * @param NotificationChannelInterface $channel The channel implementation to register
     */
    public function addChannel(NotificationChannelInterface $channel): void
    {
        $this->channels[$channel->getName()] = $channel;
    }

    /**
     * Retrieve a registered channel by its unique name.
     *
     * @param string $name Channel identifier
     * @return NotificationChannelInterface
     * @throws \InvalidArgumentException If the channel has not been registered
     */
    public function getChannel(string $name): NotificationChannelInterface
    {
        if (!isset($this->channels[$name])) {
            throw new \InvalidArgumentException(
                sprintf('Notification channel "%s" is not registered. Available: [%s]',
                    $name, implode(', ', array_keys($this->channels)))
            );
        }

        return $this->channels[$name];
    }

    /**
     * Check whether the given channel name is registered.
     *
     * @param string $name Channel identifier
     * @return bool True if the channel is available
     */
    public function supportsChannel(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    /**
     * Get the list of available channel identifiers.
     *
     * @return string[] Registered channel names
     */
    public function getAvailableChannels(): array
    {
        return array_keys($this->channels);
    }
}
