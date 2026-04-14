<?php
/**
 * Tests for FirebasePushProvider
 *
 * Covers:
 * - Push notification sending through Firebase API
 * - Authentication token management
 * - Message formatting and validation
 * - Error handling
 */

namespace CustomerEngagementNotificationBundle\Tests\Unit\Notification\Provider\Push;

use CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebaseCredentialProvider;
use CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebasePushProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FirebasePushProviderTest extends TestCase
{
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->mockCredentialProvider = $this->createMock(FirebaseCredentialProvider::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->provider = new FirebasePushProvider($this->mockHttpClient, $this->mockCredentialProvider, $this->mockLogger);
    }

    /**
     * @test
     */
    public function it_sends_push_to_single_device(): void
    {
        $token = 'eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9';
        $title = 'Test Notification';
        $body = 'Test message body';
        $context = ['order_id' => '12345'];

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('ya29.test_token');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $expectedPayload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $context,
            ],
        ];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://fcm.googleapis.com/v1/projects/test-project/messages:send',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ya29.test_token',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $expectedPayload,
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendPush($token, $title, $body, $context);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_sends_push_to_topic(): void
    {
        $topic = '/topics/flash_sale';
        $title = 'Flash Sale!';
        $body = '50% off everything';
        $context = ['promo_id' => 'FLASH2024'];

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('ya29.test_token');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $expectedPayload = [
            'message' => [
                'topic' => 'flash_sale',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $context,
            ],
        ];

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://fcm.googleapis.com/v1/projects/test-project/messages:send',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ya29.test_token',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $expectedPayload,
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendPush($topic, $title, $body, $context);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_handles_firebase_api_error(): void
    {
        $token = 'eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9';
        $title = 'Test';
        $body = 'Test message';

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('ya29.test_token');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(400);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn('{"error": {"message": "Invalid token"}}');

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $this->provider->sendPush($token, $title, $body, []);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_handles_authentication_failure(): void
    {
        $token = 'eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9';
        $title = 'Test';
        $body = 'Test message';

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willThrowException(new \RuntimeException('Authentication failed'));

        $result = $this->provider->sendPush($token, $title, $body, []);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_handles_network_timeout(): void
    {
        $token = 'eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9';
        $title = 'Test';
        $body = 'Test message';

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('ya29.test_token');

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Symfony\Contracts\HttpClient\Exception\TimeoutException('Request timed out'));

        $result = $this->provider->sendPush($token, $title, $body, []);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function it_sends_unicode_notifications(): void
    {
        $token = 'eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9';
        $title = 'แจ้งเตือน 📢';
        $body = 'ข้อความภาษาไทย 🌟';

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('ya29.test_token');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) use ($title, $body) {
                    return $options['json']['message']['notification']['title'] === $title
                        && $options['json']['message']['notification']['body'] === $body;
                })
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendPush($token, $title, $body, []);

        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function it_handles_empty_context(): void
    {
        $token = 'eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9';
        $title = 'Test';
        $body = 'Test message';

        $this->mockCredentialProvider->expects($this->once())
            ->method('getAccessToken')
            ->willReturn('ya29.test_token');

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($options) {
                    return !isset($options['json']['message']['data'])
                        || empty($options['json']['message']['data']);
                })
            )
            ->willReturn($mockResponse);

        $result = $this->provider->sendPush($token, $title, $body, []);

        self::assertTrue($result);
    }
}