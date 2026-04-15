<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Message;

/**
 * Immutable notification message value object.
 *
 * Represents a notification to be sent through one of the supported channels
 * (SMS, Email, Push, LINE). Contains all necessary information for delivery
 * including recipient, subject, body, channel type, and optional context data.
 *
 * This class performs comprehensive input validation to prevent security issues
 * and ensure data integrity. All properties are readonly to maintain immutability.
 */
class NotificationMessage
{
    /**
     * NotificationMessage constructor.
     *
     * Creates a new notification message with comprehensive validation.
     *
     * @param string $recipient The message recipient (phone number, email, device token, etc.)
     * @param string $subject The message subject/title
     * @param string $body The message content/body
     * @param string $channel The notification channel ('sms', 'email', 'push', 'line')
     * @param array $context Optional additional context data for channel-specific features
     *
     * @throws \InvalidArgumentException If any validation fails
     */
    public function __construct(
        private readonly string $recipient,   // phone, email, device token, LINE userId
        private readonly string $subject,
        private readonly string $body,
        private readonly string $channel,     // 'sms' | 'email' | 'push' | 'line'
        private readonly array  $context = [] // extra channel-specific data
    ) {
        // Validate recipient
        if (empty($recipient) || strlen($recipient) > 255) {
            throw new \InvalidArgumentException(
                'Recipient must be non-empty and less than 255 characters.'
            );
        }

        // Validate subject (RFC 5322 limit: 998 characters)
        if (strlen($subject) > 998) {
            throw new \InvalidArgumentException(
                'Subject must not exceed 998 characters (RFC 5322).'
            );
        }

        // Validate body (must be non-empty, 64KB is reasonable limit)
        if (empty($body) || strlen($body) > 65536) {
            throw new \InvalidArgumentException(
                'Body must be non-empty and not exceed 64KB.'
            );
        }

        // Validate channel name (alphanumeric + underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $channel)) {
            throw new \InvalidArgumentException(
                'Channel name must be alphanumeric (and underscores only).'
            );
        }

        // Validate context array (prevent DoS via infinite nesting)
        $this->validateContextDepth($context, 0, 10);
    }

    /**
     * Recursively validate context array depth and value sizes to prevent DoS.
     *
     * @param array $data The context data to validate
     * @param int $depth Current nesting depth
     * @param int $maxDepth Maximum allowed nesting depth
     *
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateContextDepth(array $data, int $depth, int $maxDepth): void
    {
        if ($depth > $maxDepth) {
            throw new \InvalidArgumentException(
                sprintf('Context nesting too deep (max %d levels).', $maxDepth)
            );
        }

        foreach ($data as $key => $value) {
            // Validate key is a string or integer when nested arrays represent JSON-like structures.
            if (!is_string($key) && !is_int($key)) {
                throw new \InvalidArgumentException(
                    'Context keys must be strings or integers.'
                );
            }

            if (is_array($value)) {
                $this->validateContextDepth($value, $depth + 1, $maxDepth);
            } elseif (is_string($value) && strlen($value) > 10000) {
                throw new \InvalidArgumentException(
                    'Context string value too long (max 10KB).'
                );
            } elseif (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException(
                    'Context values must be scalar, array, or null.'
                );
            }
        }
    }

    /**
     * Get the message recipient.
     *
     * @return string The recipient identifier (phone, email, token, etc.)
     */
    public function getRecipient(): string  { return $this->recipient; }

    /**
     * Get the message subject.
     *
     * @return string The message subject/title
     */
    public function getSubject(): string    { return $this->subject; }

    /**
     * Get the message body content.
     *
     * @return string The message body content
     */
    public function getBody(): string       { return $this->body; }

    /**
     * Get the notification channel.
     *
     * @return string The channel identifier ('sms', 'email', 'push', 'line')
     */
    public function getChannel(): string    { return $this->channel; }

    /**
     * Get the full context array.
     *
     * @return array The complete context data array
     */
    public function getContext(): array     { return $this->context; }

    /**
     * Get a specific value from the context array.
     *
     * @param string $key The context key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The context value or default
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }
}
