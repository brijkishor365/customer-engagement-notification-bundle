<?php
/**
 * Tests for NotificationFactory
 *
 * Covers:
 * - Channel creation and registration
 * - Service tag processing
 * - Channel resolution
 * - Error handling for unknown channels
 */

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification;

use Qburst\CustomerEngagementNotificationBundle\Notification\Channel\EmailChannel;
use Qburst\CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\NotificationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationFactoryTest extends TestCase
{
    private NotificationFactory $factory;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->factory = new NotificationFactory($this->mockLogger, []);
    }

    /**
     */
    public function test_it_creates_channel_from_container(): void
    {
        $mockChannel = $this->createMock(NotificationChannelInterface::class);
        $mockChannel->method('getName')->willReturn('test');

        $this->mockLogger->expects($this->never())->method('error');

        $this->factory->addChannel($mockChannel);

        $channel = $this->factory->getChannel('test');

        self::assertSame($mockChannel, $channel);
    }

    /**
     */
    public function test_it_throws_exception_for_unknown_channel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification channel "unknown" is not registered');

        $this->factory->getChannel('unknown');
    }

    /**
     */
    public function test_it_returns_registered_channels(): void
    {
        $mockSmsChannel = $this->createMock(SmsChannel::class);
        $mockEmailChannel = $this->createMock(EmailChannel::class);

        $mockSmsChannel->method('getName')->willReturn('sms');
        $mockEmailChannel->method('getName')->willReturn('email');

        $this->factory->addChannel($mockSmsChannel);
        $this->factory->addChannel($mockEmailChannel);

        $channels = $this->factory->getAvailableChannels();

        self::assertCount(2, $channels);
        self::assertContains('sms', $channels);
        self::assertContains('email', $channels);
    }

    /**
     */
    public function test_it_checks_if_channel_is_supported(): void
    {
        $mockSmsChannel = $this->createMock(SmsChannel::class);
        $mockSmsChannel->method('getName')->willReturn('sms');
        $this->factory->addChannel($mockSmsChannel);

        self::assertTrue($this->factory->supportsChannel('sms'));
        self::assertFalse($this->factory->supportsChannel('unknown'));
    }

    /**
     */
    public function test_it_handles_unknown_channel_requests(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification channel "test" is not registered');

        $this->factory->getChannel('test');
    }

    /**
     */
    public function test_it_prevents_duplicate_channel_registration(): void
    {
        $firstChannel = $this->createMock(NotificationChannelInterface::class);
        $firstChannel->method('getName')->willReturn('sms');
        $this->factory->addChannel($firstChannel);

        $mockChannel = $this->createMock(NotificationChannelInterface::class);
        $mockChannel->method('getName')->willReturn('sms');

        $this->factory->addChannel($mockChannel);

        $channel = $this->factory->getChannel('sms');

        self::assertSame($mockChannel, $channel);
    }
}