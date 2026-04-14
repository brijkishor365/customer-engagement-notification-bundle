<?php
/**
 * Tests for EmailChannel validation and channel support checks
 *
 * Covers:
 * - Valid email address detection
 * - Invalid email rejection
 * - Edge cases (consecutive dots, too long, etc.)
 * - RFC 5321 compliance
 */

namespace CustomerEngagementNotificationBundle\Tests\Unit\Notification\Channel;

use CustomerEngagementNotificationBundle\Notification\Channel\EmailChannel;
use CustomerEngagementNotificationBundle\Notification\Contract\EmailProviderInterface;
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use PHPUnit\Framework\TestCase;

class EmailChannelTest extends TestCase
{
    private EmailChannel $channel;
    private EmailProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(EmailProviderInterface::class);
        $this->channel = new EmailChannel($this->mockProvider);
    }

    /**
     * @test
     * @dataProvider validEmailProvider
     */
    public function it_supports_valid_email_addresses(string $email): void
    {
        $message = new NotificationMessage($email, 'Subject', 'Body', 'email');

        self::assertTrue($this->channel->supports($message));
    }

    public static function validEmailProvider(): array
    {
        return [
            'simple email' => ['user@example.com'],
            'subdomain' => ['user@mail.example.co.uk'],
            'plus addressing' => ['user+tag@example.com'],
            'number in local' => ['user123@example.com'],
            'dash in domain' => ['user@my-domain.com'],
            'long local part' => ['very.long.user.name.with.dots@example.com'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidEmailProvider
     */
    public function it_rejects_invalid_email_addresses(string $email): void
    {
        $message = new NotificationMessage($email, 'Subject', 'Body', 'email');

        self::assertFalse($this->channel->supports($message));
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'no at sign' => ['userexample.com'],
            'double at' => ['user@@example.com'],
            'consecutive dots' => ['user..name@example.com'],
            'dot before @' => ['user.@example.com'],
            'at end' => ['user@example.com.'],
            'no domain' => ['user@'],
            'no local' => ['@example.com'],
            'spaces' => ['user @example.com'],
        ];
    }

    /**
     * @test
     */
    public function it_returns_correct_channel_name(): void
    {
        self::assertEquals('email', $this->channel->getName());
    }

    /**
     * @test
     */
    public function it_sends_email_through_provider(): void
    {
        $email = 'user@example.com';
        $subject = 'Test Subject';
        $body = 'Test Body';
        $context = ['customer_id' => 123];

        $this->mockProvider->expects($this->once())
            ->method('sendEmail')
            ->with($email, $subject, $body, $context, null)
            ->willReturn(true);

        $message = new NotificationMessage($email, $subject, $body, 'email', $context);

        self::assertTrue($this->channel->send($message));
    }

    /**
     * @test
     */
    public function it_handles_document_path_from_context(): void
    {
        $email = 'user@example.com';
        $documentPath = '/emails/welcome';

        $this->mockProvider->expects($this->once())
            ->method('sendEmail')
            ->with(
                $email,
                'Subject',
                'Body',
                ['document_path' => $documentPath],
                $documentPath
            )
            ->willReturn(true);

        $message = new NotificationMessage(
            $email,
            'Subject',
            'Body',
            'email',
            ['document_path' => $documentPath]
        );

        self::assertTrue($this->channel->send($message));
    }
}
