<?php
/**
 * Tests for SmsChannel validation and channel support checks
 *
 * Covers:
 * - Valid E.164 phone number detection
 * - Invalid phone number rejection
 * - SMS-specific validation rules
 * - Provider integration
 */

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification\Channel;

use Qburst\CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\SmsProviderInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SmsChannelTest extends TestCase
{
    private SmsChannel $channel;
    private SmsProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(SmsProviderInterface::class);
        $this->channel = new SmsChannel($this->mockProvider);
    }

    #[DataProvider('validPhoneNumberProvider')]
    public function test_it_supports_valid_e164_phone_numbers(string $phoneNumber): void
    {
        $message = new NotificationMessage($phoneNumber, 'Test', 'SMS Body', 'sms');

        self::assertTrue($this->channel->supports($message));
    }

    public static function validPhoneNumberProvider(): array
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

    #[DataProvider('invalidPhoneNumberProvider')]
    public function test_it_rejects_invalid_phone_numbers(string $phoneNumber): void
    {
        $message = new NotificationMessage($phoneNumber, 'Test', 'SMS Body', 'sms');

        self::assertFalse($this->channel->supports($message));
    }

    public static function invalidPhoneNumberProvider(): array
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
        self::assertEquals('sms', $this->channel->getName());
    }

    /**
     */
    public function test_it_sends_sms_through_provider(): void
    {
        $phoneNumber = '+66812345678';
        $message = 'Test SMS message';

        $this->mockProvider->expects($this->once())
            ->method('sendSms')
            ->with($phoneNumber, $message)
            ->willReturn(true);

        $notificationMessage = new NotificationMessage($phoneNumber, 'Subject', $message, 'sms');

        self::assertTrue($this->channel->send($notificationMessage));
    }

    /**
     */
    public function test_it_handles_provider_failure(): void
    {
        $phoneNumber = '+66812345678';
        $message = 'Test SMS message';

        $this->mockProvider->expects($this->once())
            ->method('sendSms')
            ->with($phoneNumber, $message)
            ->willReturn(false);

        $notificationMessage = new NotificationMessage($phoneNumber, 'Subject', $message, 'sms');

        self::assertFalse($this->channel->send($notificationMessage));
    }

    /**
     */
    public function test_it_validates_message_length_for_sms(): void
    {
        // SMS should support up to 160 GSM characters or 70 Unicode
        $longMessage = str_repeat('A', 160);
        $message = new NotificationMessage('+66812345678', 'Subject', $longMessage, 'sms');

        self::assertTrue($this->channel->supports($message));
    }

    /**
     */
    public function test_it_handles_unicode_characters_in_sms(): void
    {
        $unicodeMessage = 'สวัสดีครับ 🌟'; // Thai text with emoji
        $message = new NotificationMessage('+66812345678', 'Subject', $unicodeMessage, 'sms');

        self::assertTrue($this->channel->supports($message));
    }
}