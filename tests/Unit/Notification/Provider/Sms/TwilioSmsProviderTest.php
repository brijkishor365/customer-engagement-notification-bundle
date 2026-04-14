<?php
/**
 * Tests for TwilioSmsProvider
 *
 * Covers:
 * - SMS sending through Twilio API
 * - Authentication and error handling
 * - Message formatting and validation
 * - HTTP client integration
 */

namespace CustomerEngagementNotificationBundle\Tests\Unit\Notification\Provider\Sms;

use CustomerEngagementNotificationBundle\Notification\Provider\Sms\TwilioSmsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TwilioSmsProviderTest extends TestCase
{
    private TwilioSmsProvider $provider;
    private HttpClientInterface $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->provider = new TwilioSmsProvider(
            'AC1234567890abcdef1234567890abcdef',
            'test_auth_token',
            '+1234567890',
            $this->mockHttpClient
        );
    }

    /**
     * @test
     */
    public function it_sends_sms_successfully(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(201);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.twilio.com/2010-04-01/Accounts/AC1234567890abcdef1234567890abcdef/Messages.json',
                [
                    'auth_basic' => ['AC1234567890abcdef1234567890abcdef', 'test_auth_token'],
                    'body' => [
                        'To' => '+66812345678',
                        'From' => '+1234567890',
                        'Body' => 'Test SMS message',
                    ],
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendSms('+66812345678', 'Test SMS message');

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_handles_twilio_api_error(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(400);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn('{"error": "Invalid phone number"}');

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $this->provider->sendSms('+66812345678', 'Test SMS message');

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_handles_network_timeout(): void
    {
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Symfony\Contracts\HttpClient\Exception\TimeoutException('Request timed out'));

        $result = $this->provider->sendSms('+66812345678', 'Test SMS message');

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_handles_http_client_exception(): void
    {
        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Symfony\Contracts\HttpClient\Exception\TransportException('Network error'));

        $result = $this->provider->sendSms('+66812345678', 'Test SMS message');

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_sends_unicode_messages(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(201);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) {
                    return $options['body']['Body'] === 'สวัสดีครับ 🌟';
                })
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendSms('+66812345678', 'สวัสดีครับ 🌟');

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_handles_long_messages(): void
    {
        $longMessage = str_repeat('A', 160); // Exactly SMS limit
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(201);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) use ($longMessage) {
                    return $options['body']['Body'] === $longMessage;
                })
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendSms('+66812345678', $longMessage);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_constructs_with_valid_credentials(): void
    {
        $provider = new TwilioSmsProvider(
            'AC1234567890abcdef1234567890abcdef',
            'valid_auth_token',
            '+1234567890',
            $this->mockHttpClient
        );

        self::assertInstanceOf(TwilioSmsProvider::class, $provider);
    }

    /**
     * @test
     */
    public function it_handles_empty_message(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(201);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) {
                    return $options['body']['Body'] === '';
                })
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendSms('+66812345678', '');

        self::assertTrue($result);
    }
}