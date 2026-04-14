<?php
/**
 * Tests for NotificationManager
 *
 * Covers:
 * - Single channel message sending
 * - Multi-channel broadcasting
 * - Error handling and logging
 * - Channel validation
 */

namespace CustomerEngagementNotificationBundle\Tests\Unit\Notification;

use CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use CustomerEngagementNotificationBundle\Notification\NotificationFactory;
use CustomerEngagementNotificationBundle\Notification\NotificationManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationManagerTest extends TestCase
{
    private NotificationManager $manager;
    private NotificationFactory $mockFactory;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockFactory = $this->createMock(NotificationFactory::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->manager = new NotificationManager($this->mockFactory, $this->mockLogger);
    }

    /**
     * @test
     */
    public function it_sends_message_through_correct_channel(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'sms');
        $mockChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->once())
            ->method('getChannel')
            ->with('sms')
            ->willReturn($mockChannel);

        $mockChannel->expects($this->once())
            ->method('supports')
            ->with($message)
            ->willReturn(true);

        $mockChannel->expects($this->once())
            ->method('send')
            ->with($message)
            ->willReturn(true);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Notification sent via {channel} to {recipient}', $this->anything());

        $result = $this->manager->send($message);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_handles_channel_not_supporting_message(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'sms');
        $mockChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->once())
            ->method('getChannel')
            ->with('sms')
            ->willReturn($mockChannel);

        $mockChannel->expects($this->once())
            ->method('supports')
            ->with($message)
            ->willReturn(false);

        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Channel {channel} does not support this message.', $this->anything());

        $result = $this->manager->send($message);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_handles_channel_send_failure(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'sms');
        $mockChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->once())
            ->method('getChannel')
            ->with('sms')
            ->willReturn($mockChannel);

        $mockChannel->expects($this->once())
            ->method('supports')
            ->with($message)
            ->willReturn(true);

        $mockChannel->expects($this->once())
            ->method('send')
            ->with($message)
            ->willReturn(false);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Notification sent via {channel} to {recipient}', $this->anything());

        $result = $this->manager->send($message);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_handles_unknown_channel(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'unknown');

        $this->mockFactory->expects($this->once())
            ->method('getChannel')
            ->with('unknown')
            ->willThrowException(new \InvalidArgumentException('Notification channel "unknown" is not registered. Available: []'));

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Failed to send notification via {channel}: {error}', $this->anything());

        $result = $this->manager->send($message);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_broadcasts_to_multiple_channels(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'sms');
        $channels = ['sms', 'email'];

        $mockSmsChannel = $this->createMock(NotificationChannelInterface::class);
        $mockEmailChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->exactly(2))
            ->method('getChannel')
            ->willReturnMap([
                ['sms', $mockSmsChannel],
                ['email', $mockEmailChannel],
            ]);

        $mockSmsChannel->expects($this->once())
            ->method('supports')
            ->with($this->callback(function (NotificationMessage $message) {
                return $message->getChannel() === 'sms'
                    && $message->getRecipient() === 'recipient'
                    && $message->getSubject() === 'Subject'
                    && $message->getBody() === 'Body';
            }))
            ->willReturn(true);
        $mockSmsChannel->expects($this->once())
            ->method('send')
            ->with($this->callback(function (NotificationMessage $message) {
                return $message->getChannel() === 'sms';
            }))
            ->willReturn(true);

        $mockEmailChannel->expects($this->once())
            ->method('supports')
            ->with($this->callback(function (NotificationMessage $message) {
                return $message->getChannel() === 'email'
                    && $message->getRecipient() === 'recipient';
            }))
            ->willReturn(true);
        $mockEmailChannel->expects($this->once())
            ->method('send')
            ->with($this->callback(function (NotificationMessage $message) {
                return $message->getChannel() === 'email';
            }))
            ->willReturn(false);

        $this->mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Notification sent via {channel} to {recipient}', $this->anything()],
                ['Notification sent via {channel} to {recipient}', $this->anything()]
            );

        $results = $this->manager->broadcast($message, $channels);

        self::assertCount(2, $results);
        self::assertTrue($results['sms']);
        self::assertFalse($results['email']);
    }

    /**
     * @test
     */
    public function it_handles_broadcast_with_unsupported_channels(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'sms');
        $channels = ['sms', 'unknown'];

        $mockSmsChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->exactly(2))
            ->method('getChannel')
            ->willReturnCallback(function (string $channelName) use ($mockSmsChannel) {
                return $channelName === 'sms'
                    ? $mockSmsChannel
                    : throw new \InvalidArgumentException('Notification channel "unknown" is not registered. Available: [sms]');
            });

        $mockSmsChannel->expects($this->once())
            ->method('supports')
            ->with($this->callback(function (NotificationMessage $message) {
                return $message->getChannel() === 'sms'
                    && $message->getRecipient() === 'recipient'
                    && $message->getSubject() === 'Subject'
                    && $message->getBody() === 'Body';
            }))
            ->willReturn(true);
        $mockSmsChannel->expects($this->once())
            ->method('send')
            ->with($this->callback(function (NotificationMessage $message) {
                return $message->getChannel() === 'sms';
            }))
            ->willReturn(true);

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Failed to send notification via {channel}: {error}', $this->anything());

        $results = $this->manager->broadcast($message, $channels);

        self::assertCount(2, $results);
        self::assertTrue($results['sms']);
        self::assertFalse($results['unknown']);
    }

    /**
     * @test
     */
    public function it_logs_successful_notifications(): void
    {
        $message = new NotificationMessage('+66812345678', 'Test', 'Body', 'sms');
        $mockChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->once())
            ->method('getChannel')
            ->with('sms')
            ->willReturn($mockChannel);

        $mockChannel->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $mockChannel->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Notification sent via {channel} to {recipient}', [
                'channel' => 'sms',
                'recipient' => '+66812345678',
            ]);

        $this->manager->send($message);
    }

    /**
     * @test
     */
    public function it_logs_failed_notifications(): void
    {
        $message = new NotificationMessage('user@example.com', 'Test', 'Body', 'email');
        $mockChannel = $this->createMock(NotificationChannelInterface::class);

        $this->mockFactory->expects($this->once())
            ->method('getChannel')
            ->with('email')
            ->willReturn($mockChannel);

        $mockChannel->expects($this->once())
            ->method('supports')
            ->willReturn(true);
        $mockChannel->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Notification sent via {channel} to {recipient}', [
                'channel' => 'email',
                'recipient' => 'user@example.com',
            ]);

        $this->manager->send($message);
    }
}