<?php

namespace CustomerEngagementNotificationBundle\Notification\Channel;

use CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use CustomerEngagementNotificationBundle\Notification\Contract\SmsProviderInterface;
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

/**
 * SMS notification channel implementation.
 *
 * This channel handles SMS notifications by delegating to an SmsProviderInterface
 * implementation. It validates phone numbers according to the E.164 international
 * standard to ensure compatibility with SMS providers worldwide.
 */
class SmsChannel implements NotificationChannelInterface
{
    /**
     * SmsChannel constructor.
     *
     * @param SmsProviderInterface $provider The SMS provider to use for sending messages
     */
    public function __construct(
        private readonly SmsProviderInterface $provider  // inject whichever provider you want
    ) {}

    /**
     * Sends an SMS notification using the configured provider.
     *
     * @param NotificationMessage $message The notification message to send
     * @return bool True if the SMS was sent successfully, false otherwise
     */
    public function send(NotificationMessage $message): bool
    {
        return $this->provider->sendSms(
            $message->getRecipient(),
            $message->getBody()
        );
    }

    /**
     * Returns the channel name identifier.
     *
     * @return string The channel name ('sms')
     */
    public function getName(): string { return 'sms'; }

    /**
     * Determines if this channel supports the given notification message.
     *
     * Validates the recipient phone number according to E.164 international standard:
     * - Must start with + followed by country code (1-3 digits)
     * - Country code must not start with 0
     * - Total length must be 7-15 digits after the + (16 characters max)
     *
     * @param NotificationMessage $message The notification message to validate
     * @return bool True if the message is supported by this channel, false otherwise
     */
    public function supports(NotificationMessage $message): bool
    {
        $phone = $message->getRecipient();
        
        // E.164 format: +[1-9]{1,3}[0-9]{4,12}
        // Country code 1-3 digits, subscriber 4-12 digits (total 7-15 digits after +)
        return preg_match('/^\+[1-9]\d{0,2}\d{4,12}$/', $phone) === 1;
    }
}
