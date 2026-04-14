<?php

namespace CustomerEngagementNotificationBundle\Notification\Channel;

use CustomerEngagementNotificationBundle\Notification\Contract\EmailProviderInterface;
use CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

/**
 * Email notification channel implementation.
 *
 * This channel handles email notifications by delegating to an EmailProviderInterface
 * implementation. It validates email addresses according to RFC 5321/5322 standards
 * and supports both plain text emails and Pimcore Email Document-based templates.
 */
class EmailChannel implements NotificationChannelInterface
{
    /**
     * EmailChannel constructor.
     *
     * @param EmailProviderInterface $provider The email provider to use for sending emails
     */
    public function __construct(private readonly EmailProviderInterface $provider) {}

    /**
     * Sends an email notification using the configured provider.
     *
     * @param NotificationMessage $message The notification message to send
     * @return bool True if the email was sent successfully, false otherwise
     */
    public function send(NotificationMessage $message): bool
    {
        return $this->provider->sendEmail(
            to:           $message->getRecipient(),
            subject:      $message->getSubject(),
            body:         $message->getBody(),
            params:       $message->getContext(),
            // Optional: caller sets 'document_path' in context to use Mode A
            documentPath: $message->getContextValue('document_path')
        );
    }

    /**
     * Returns the channel name identifier.
     *
     * @return string The channel name ('email')
     */
    public function getName(): string { return 'email'; }

    /**
     * Determines if this channel supports the given notification message.
     *
     * Validates the recipient email address according to RFC 5321/5322 standards:
     * - Maximum length of 254 characters
     * - Valid email format using PHP filter
     * - No consecutive dots
     * - Basic pattern validation
     *
     * @param NotificationMessage $message The notification message to validate
     * @return bool True if the message is supported by this channel, false otherwise
     */
    public function supports(NotificationMessage $message): bool
    {
        $email = $message->getRecipient();
        
        // RFC 5321: email addresses max 254 characters
        if (strlen($email) > 254) {
            return false;
        }
        
        // Use PHP filter but also verify basic format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check for consecutive dots (invalid)
        if (strpos($email, '..') !== false) {
            return false;
        }
        
        // Additional strict pattern check to catch edge cases
        // RFC 5322 simplified - allows most valid emails
        if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
            return false;
        }
        
        return true;
    }
}
