<?php
/**
 * Tests for WhatsAppChannel validation and channel support checks
 *
 * Covers:
 * - Valid E.164 phone number validation for WhatsApp
 * - Invalid phone number rejection
 * - WhatsApp-specific validation rules
 * - Provider integration
 */

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification\Channel;

use Qburst\CustomerEngagementNotificationBundle\Notification\Channel\WhatsAppChannel;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\WhatsAppProviderInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use Qburst\CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhatsAppChannelTest extends TestCase
{
    private WhatsAppChannel $channel;
    private WhatsAppProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(WhatsAppProviderInterface::class);
        $this->channel = new WhatsAppChannel($this->mockProvider);
    }

    #[DataProvider('validWhatsAppPhoneNumberProvider')]
    public function test_it_supports_valid_whatsapp_phone_numbers(string $phoneNumber): void
    {
        $message = new NotificationMessage($phoneNumber, 'Test', 'WhatsApp Body', 'whatsapp');

        self::assertTrue($this->channel->supports($message));
    }

    public static function validWhatsAppPhoneNumberProvider(): array
    {
        return [
            'Thailand mobile' => ['+66812345678'],
            'Thailand landline' => ['+6621234567'],
            'US number' => ['+14155552671'],
            'UK number' => ['+447700900000'],
            'Germany number' => ['+491234567890'],
            'Australia number' => ['+61412345678'],
            'Japan number' => ['+819012345678'],
            'Minimum length' => ['+1123456'],
            'Maximum length' => ['+123456789012345'],
        ];
    }

    #[DataProvider('invalidWhatsAppPhoneNumberProvider')]
    public function test_it_rejects_invalid_whatsapp_phone_numbers(string $phoneNumber): void
    {
        $message = new NotificationMessage($phoneNumber, 'Test', 'WhatsApp Body', 'whatsapp');

        self::assertFalse($this->channel->supports($message));
    }

    public static function invalidWhatsAppPhoneNumberProvider(): array
    {
        return [
            'too short' => ['+1'],
            'too long' => ['+1234567890123456'],
            'contains letters' => ['+6681234567a'],
            'contains spaces' => ['+66 8123 45678'],
            'contains dashes' => ['+66-8123-45678'],
            'contains parentheses' => ['+66(812)345678'],
            'just plus' => ['+'],
            'leading zero without plus' => ['066812345678'],
        ];
    }

    /**
     */
    public function test_it_returns_correct_channel_name(): void
    {
        self::assertEquals('whatsapp', $this->channel->getName());
    }

    /**
     */
    public function test_it_sends_whatsapp_text_message_through_provider(): void
    {
        $phoneNumber = '+66812345678';
        $subject = 'Test Message';
        $body = 'Hello from WhatsApp!';
        $context = ['whatsapp_type' => 'text'];

        $this->mockProvider->expects($this->once())
            ->method('send')
            ->with($phoneNumber, $this->callback(function (WhatsAppMessage $message) use ($body) {
                return $message->isText() && $message->getPayload()['body'] === $body;
            }))
            ->willReturn(true);

        $message = new NotificationMessage($phoneNumber, $subject, $body, 'whatsapp', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     */
    public function test_it_sends_whatsapp_template_message(): void
    {
        $phoneNumber = '+66812345678';
        $subject = 'Order Shipped';
        $body = 'N/A';
        $context = [
            'whatsapp_type' => 'template',
            'whatsapp_template' => 'order_shipped',
            'whatsapp_language' => 'en_US',
            'whatsapp_components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'John Doe'],
                        ['type' => 'text', 'text' => '#1234']
                    ]
                ]
            ]
        ];

        $this->mockProvider->expects($this->once())
            ->method('send')
            ->with($phoneNumber, $this->callback(function (WhatsAppMessage $message) {
                return $message->isTemplate() && $message->getPayload()['name'] === 'order_shipped';
            }))
            ->willReturn(true);

        $message = new NotificationMessage($phoneNumber, $subject, $body, 'whatsapp', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     */
    public function test_it_sends_whatsapp_media_message(): void
    {
        $phoneNumber = '+66812345678';
        $subject = 'Receipt';
        $body = 'N/A';
        $context = [
            'whatsapp_type' => 'image',
            'whatsapp_media_url' => 'https://cdn.example.com/receipt.jpg',
            'whatsapp_caption' => 'Your receipt for order #1234'
        ];

        $this->mockProvider->expects($this->once())
            ->method('send')
            ->with($phoneNumber, $this->callback(function (WhatsAppMessage $message) {
                return $message->isMedia() && $message->getPayload()['url'] === 'https://cdn.example.com/receipt.jpg';
            }))
            ->willReturn(true);

        $message = new NotificationMessage($phoneNumber, $subject, $body, 'whatsapp', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     */
    public function test_it_handles_provider_failure(): void
    {
        $phoneNumber = '+66812345678';
        $subject = 'Test';
        $body = 'Test message';
        $context = ['whatsapp_type' => 'text'];

        $this->mockProvider->expects($this->once())
            ->method('send')
            ->with($phoneNumber, $this->callback(function (WhatsAppMessage $message) use ($body) {
                return $message->isText() && $message->getPayload()['body'] === $body;
            }))
            ->willReturn(false);

        $message = new NotificationMessage($phoneNumber, $subject, $body, 'whatsapp', $context);

        self::assertFalse($this->channel->send($message));
    }

    /**
     */
    public function test_it_supports_unicode_in_whatsapp_messages(): void
    {
        $phoneNumber = '+66812345678';
        $body = 'สวัสดีครับ ยินดีต้อนรับ 🌟'; // Thai text with emoji

        $message = new NotificationMessage($phoneNumber, 'Msg', $body, 'whatsapp');

        self::assertTrue($this->channel->supports($message));
    }

    /**
     */
    public function test_it_validates_https_media_urls(): void
    {
        $phoneNumber = '+66812345678';
        $context = [
            'whatsapp_type' => 'image',
            'whatsapp_media_url' => 'https://cdn.example.com/image.jpg'
        ];

        $message = new NotificationMessage($phoneNumber, 'Test', 'Body', 'whatsapp', $context);

        self::assertTrue($this->channel->supports($message));
    }
}