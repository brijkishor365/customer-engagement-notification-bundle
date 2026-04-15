<?php
/**
 * Tests for PushChannel validation and channel support checks
 *
 * Covers:
 * - Valid FCM token and topic validation
 * - Invalid token/topic rejection
 * - Firebase-specific validation rules
 * - Provider integration
 */

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification\Channel;

use Qburst\CustomerEngagementNotificationBundle\Notification\Channel\PushChannel;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\PushProviderInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PushChannelTest extends TestCase
{
    private PushChannel $channel;
    private PushProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(PushProviderInterface::class);
        $this->channel = new PushChannel($this->mockProvider);
    }

    #[DataProvider('validPushRecipientProvider')]
    public function test_it_supports_valid_push_recipients(string $recipient): void
    {
        $message = new NotificationMessage($recipient, 'Title', 'Body', 'push');

        self::assertTrue($this->channel->supports($message));
    }

    public static function validPushRecipientProvider(): array
    {
        return [
            'FCM token' => [str_repeat('A', 152)],
            'FCM topic' => ['/topics/news'],
            'FCM topic with underscores' => ['/topics/user_123_updates'],
            'FCM topic with hyphens' => ['/topics/special-offers'],
            'FCM topic with numbers' => ['/topics/channel123'],
        ];
    }

    #[DataProvider('invalidPushRecipientProvider')]
    public function test_it_rejects_invalid_push_recipients(string $recipient): void
    {
        $message = new NotificationMessage($recipient, 'Title', 'Body', 'push');

        self::assertFalse($this->channel->supports($message));
    }

    public static function invalidPushRecipientProvider(): array
    {
        return [
            'too short token' => ['short'],
            'token with invalid chars' => ['invalid@token!'],
            'topic without slash' => ['topics/news'],
            'topic with wrong prefix' => ['/notifications/news'],
            'topic with spaces' => ['/topics/news alert'],
            'topic with invalid chars' => ['/topics/news!alert'],
            'null bytes' => ["token\x00null"],
        ];
    }

    /**
     */
    public function test_it_returns_correct_channel_name(): void
    {
        self::assertEquals('push', $this->channel->getName());
    }

    /**
     */
    public function test_it_sends_push_notification_through_provider(): void
    {
        $token = str_repeat('A', 152);
        $title = 'Test Notification';
        $body = 'Test message body';
        $context = ['order_id' => '12345'];

        $this->mockProvider->expects($this->once())
            ->method('sendPush')
            ->with($token, $title, $body, $context)
            ->willReturn(true);

        $message = new NotificationMessage($token, $title, $body, 'push', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     */
    public function test_it_sends_topic_broadcast_through_provider(): void
    {
        $topic = '/topics/flash_sale';
        $title = 'Flash Sale!';
        $body = '50% off everything today only';
        $context = ['promo_id' => 'FLASH2024'];

        $this->mockProvider->expects($this->once())
            ->method('sendPush')
            ->with($topic, $title, $body, $context)
            ->willReturn(true);

        $message = new NotificationMessage($topic, $title, $body, 'push', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     */
    public function test_it_handles_provider_failure(): void
    {
        $token = str_repeat('A', 152);
        $title = 'Test Notification';
        $body = 'Test message body';

        $this->mockProvider->expects($this->once())
            ->method('sendPush')
            ->with($token, $title, $body, [])
            ->willReturn(false);

        $message = new NotificationMessage($token, $title, $body, 'push');

        self::assertFalse($this->channel->send($message));
    }

    /**
     */
    public function test_it_validates_fcm_token_length(): void
    {
        // FCM tokens are typically 152 characters
        $validToken = str_repeat('A', 152);
        $message = new NotificationMessage($validToken, 'Title', 'Body', 'push');

        self::assertTrue($this->channel->supports($message));
    }

    /**
     */
    public function test_it_supports_unicode_in_push_notifications(): void
    {
        $title = 'แจ้งเตือน 📢';
        $body = 'ข้อความภาษาไทย 🌟';
        $token = str_repeat('A', 152);

        $message = new NotificationMessage($token, $title, $body, 'push');

        self::assertTrue($this->channel->supports($message));
    }
}