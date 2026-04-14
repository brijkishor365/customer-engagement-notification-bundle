<?php
/**
 * Tests for LineChannel validation and channel support checks
 *
 * Covers:
 * - Valid LINE user ID validation
 * - Invalid user ID rejection
 * - LINE-specific validation rules
 * - Provider integration
 */

namespace CustomerEngagementNotificationBundle\Tests\Unit\Notification\Channel;

use CustomerEngagementNotificationBundle\Notification\Channel\LineChannel;
use CustomerEngagementNotificationBundle\Notification\Contract\LineProviderInterface;
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use PHPUnit\Framework\TestCase;

class LineChannelTest extends TestCase
{
    private LineChannel $channel;
    private LineProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(LineProviderInterface::class);
        $this->channel = new LineChannel($this->mockProvider);
    }

    /**
     * @test
     * @dataProvider validLineUserIdProvider
     */
    public function it_supports_valid_line_user_ids(string $userId): void
    {
        $message = new NotificationMessage($userId, 'Msg', 'Body', 'line');

        self::assertTrue($this->channel->supports($message));
    }

    public static function validLineUserIdProvider(): array
    {
        return [
            'standard user ID' => ['U' . str_repeat('A', 32)],
            'uppercase user ID' => ['U' . str_repeat('1', 32)],
            'minimum length' => ['U' . str_repeat('9', 32)], // 33 chars
        ];
    }

    /**
     * @test
     * @dataProvider invalidLineUserIdProvider
     */
    public function it_rejects_invalid_line_user_ids(string $userId): void
    {
        $message = new NotificationMessage($userId, 'Msg', 'Body', 'line');

        self::assertFalse($this->channel->supports($message));
    }

    public static function invalidLineUserIdProvider(): array
    {
        return [
            'too short' => ['U1234567890abcdef1234567890abcd'], // 32 chars
            'too long' => ['U1234567890abcdef1234567890abcdef1'], // 34 chars
            'wrong prefix' => ['u1234567890abcdef1234567890abcdef'],
            'contains invalid chars' => ['U1234567890abcdef1234567890abcde!'],
            'lowercase u' => ['u1a2b3c4d5e6f7a2b3c4d5e6f7a2b3c4'],
            'spaces' => ['U1234567890abcdef1234567890abcde '],
            'null bytes' => ["U1234567890abcdef1234567890abcde\x00"],
        ];
    }

    /**
     * @test
     */
    public function it_returns_correct_channel_name(): void
    {
        self::assertEquals('line', $this->channel->getName());
    }

    /**
     * @test
     */
    public function it_sends_line_message_through_provider(): void
    {
        $userId = 'U' . str_repeat('A', 32);
        $subject = 'Order Update';
        $body = 'Your order has been shipped! 🚚';
        $context = ['order_id' => '12345'];

        $this->mockProvider->expects($this->once())
            ->method('sendMessage')
            ->with($userId, $body, $context)
            ->willReturn(true);

        $message = new NotificationMessage($userId, $subject, $body, 'line', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     * @test
     */
    public function it_handles_line_flex_message(): void
    {
        $userId = 'U1a2b3c4d5e6f7a2b3c4d5e6f7a2b3c4';
        $subject = 'Order Confirmed';
        $body = 'Order #1234 Confirmed';
        $context = [
            'messages' => [[
                'type' => 'flex',
                'altText' => 'Order Confirmed',
                'contents' => [
                    'type' => 'bubble',
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'Order #1234']
                        ]
                    ]
                ]
            ]]
        ];

        $this->mockProvider->expects($this->once())
            ->method('sendMessage')
            ->with($userId, $body, $context)
            ->willReturn(true);

        $message = new NotificationMessage($userId, $subject, $body, 'line', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     * @test
     */
    public function it_handles_provider_failure(): void
    {
        $userId = 'U' . str_repeat('A', 32);
        $subject = 'Test';
        $body = 'Test message';

        $this->mockProvider->expects($this->once())
            ->method('sendMessage')
            ->with($userId, $body, [])
            ->willReturn(false);

        $message = new NotificationMessage($userId, $subject, $body, 'line');

        self::assertFalse($this->channel->send($message));
    }

    /**
     * @test
     */
    public function it_supports_unicode_thai_text(): void
    {
        $userId = 'U' . str_repeat('A', 32);
        $body = 'สวัสดีครับ ยินดีต้อนรับ 🌟'; // Thai text with emoji

        $message = new NotificationMessage($userId, 'Msg', $body, 'line');

        self::assertTrue($this->channel->supports($message));
    }

    /**
     * @test
     */
    public function it_validates_user_id_exact_length(): void
    {
        // LINE user IDs are exactly 33 characters
        $validUserId = 'U' . str_repeat('1', 32); // U + 32 chars = 33 total
        $message = new NotificationMessage($validUserId, 'Msg', 'Body', 'line');

        self::assertTrue($this->channel->supports($message));
    }
}