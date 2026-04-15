<?php
/**
 * Tests for NotificationMessage validation
 *
 * Covers:
 * - Required field validation
 * - Size limits for recipient, subject, body
 * - Channel name validation
 * - Context depth and value size validation
 * - DoS prevention
 */

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification\Message;

use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotificationMessageTest extends TestCase
{
    #[DataProvider('validMessageProvider')]
    public function test_it_accepts_valid_messages(string $recipient, string $subject, string $body, string $channel): void
    {
        $message = new NotificationMessage($recipient, $subject, $body, $channel);

        self::assertEquals($recipient, $message->getRecipient());
        self::assertEquals($subject, $message->getSubject());
        self::assertEquals($body, $message->getBody());
        self::assertEquals($channel, $message->getChannel());
    }

    public static function validMessageProvider(): array
    {
        return [
            'SMS message' => ['+14155552671', 'Test', 'Hello SMS', 'sms'],
            'Email message' => ['user@example.com', 'Subject Line', 'Email body', 'email'],
            'Push message' => ['device_token_123', 'Title', 'Notification body', 'push'],
            'LINE message' => ['U1234567890abcdef1234567890abcdef', 'Msg', 'Body', 'line'],
        ];
    }

    #[DataProvider('invalidRecipientProvider')]
    public function test_it_rejects_invalid_recipients(string $recipient): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient must be non-empty');

        new NotificationMessage($recipient, 'Subject', 'Body', 'sms');
    }

    public static function invalidRecipientProvider(): array
    {
        return [
            'empty recipient' => [''],
            'very long recipient' => [str_repeat('x', 300)],
        ];
    }

    /**
     */
    public function test_it_rejects_invalid_channel_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel name must be alphanumeric');

        new NotificationMessage('user@example.com', 'Subject', 'Body', 'invalid-channel!');
    }

    /**
     */
    public function test_it_rejects_oversized_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Body must be non-empty and not exceed 64KB');

        new NotificationMessage('user@example.com', 'Subject', str_repeat('x', 70000), 'email');
    }

    /**
     */
    public function test_it_rejects_deeply_nested_context(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Context nesting too deep (max 10 levels).');

        // Create deeply nested array > 10 levels
        $context = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => [
                                    'g' => [
                                        'h' => [
                                            'i' => [
                                                'j' => [
                                                    'k' => [
                                                        'l' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        new NotificationMessage('user@example.com', 'Subject', 'Body', 'email', $context);
    }

    /**
     */
    public function test_it_rejects_oversized_context_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Context string value too long');

        $context = ['large_value' => str_repeat('x', 11000)];

        new NotificationMessage('user@example.com', 'Subject', 'Body', 'email', $context);
    }

    /**
     */
    public function test_it_accepts_valid_context_with_scalars(): void
    {
        $context = [
            'customer_id' => 12345,
            'is_urgent' => true,
            'priority' => 9.5,
            'reference' => 'REF-001',
            'nested' => ['item' => 'value'],
        ];

        $message = new NotificationMessage('user@example.com', 'Subject', 'Body', 'email', $context);

        self::assertEquals($context, $message->getContext());
        self::assertEquals(12345, $message->getContextValue('customer_id'));
        self::assertEquals('default', $message->getContextValue('missing_key', 'default'));
    }
}
