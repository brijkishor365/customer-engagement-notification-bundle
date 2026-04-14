<?php
/**
 * Tests for FirebaseCredentialProvider
 *
 * Covers:
 * - OAuth2 token generation and caching
 * - Service account key validation
 * - Token refresh and expiration
 * - Error handling
 */

namespace CustomerEngagementNotificationBundle\Tests\Unit\Notification\Provider\Push;

use CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebaseCredentialProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutException;

class FirebaseCredentialProviderTest extends TestCase
{
    private FirebaseCredentialProvider $provider;
    private HttpClientInterface $mockHttpClient;
    private CacheInterface $mockCache;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMock(HttpClientInterface::class);
        $this->mockCache = $this->createMock(CacheInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        $serviceAccountJson = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'test-key-id',
            'private_key' => "-----BEGIN PRIVATE KEY-----\ntest-private-key\n-----END PRIVATE KEY-----\n",
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'client_id' => '123456789',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        ]);

        $this->provider = new FirebaseCredentialProvider(
            $this->mockHttpClient,
            $this->mockCache,
            $this->mockLogger,
            $serviceAccountJson,
            true // skip validation for tests
        );
    }

    /**
     * @test
     */
    public function it_returns_cached_token_when_available(): void
    {
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('cen.firebase.access_token')
            ->willReturn('cached_token_123');

        $token = $this->provider->getAccessToken();

        self::assertEquals('cached_token_123', $token);
    }

    /**
     * @test
     */
    public function it_fetches_new_token_when_cache_miss(): void
    {
        // Mock cache miss
        $mockCacheItem = $this->createMock(CacheItemInterface::class);
        $mockCacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        // Mock successful token response
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $mockResponse->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'access_token' => 'new_access_token_123',
                'expires_in' => 3600,
            ]);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/token',
                $this->callback(function ($options) {
                    return isset($options['body']['grant_type']) &&
                           isset($options['body']['assertion']);
                })
            )
            ->willReturn($mockResponse);

        // Mock cache save
        $saveCacheItem = $this->createMock(CacheItemInterface::class);
        $saveCacheItem->expects($this->once())
            ->method('set')
            ->with('new_access_token_123')
            ->willReturnSelf();
        $saveCacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3300) // 55 minutes (5 min buffer)
            ->willReturnSelf();

        $this->mockCache->expects($this->exactly(2))
            ->method('getItem')
            ->with('firebase_access_token')
            ->willReturn($mockCacheItem, $saveCacheItem);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->with($saveCacheItem);

        $token = $this->provider->getAccessToken();

        self::assertEquals('new_access_token_123', $token);
    }

    /**
     * @test
     */
    public function it_handles_oauth_error_response(): void
    {
        $mockCacheItem = $this->createMock(CacheItemInterface::class);
        $mockCacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(400);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willReturn('{"error": "invalid_grant", "error_description": "Invalid JWT"}');

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->mockCache->expects($this->once())
            ->method('getItem')
            ->willReturn($mockCacheItem);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to obtain Firebase access token');

        $this->provider->getAccessToken();
    }

    /**
     * @test
     */
    public function it_handles_network_timeout(): void
    {
        $mockCacheItem = $this->createMock(CacheItemInterface::class);
        $mockCacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Symfony\Contracts\HttpClient\Exception\TimeoutException('Request timed out'));

        $this->mockCache->expects($this->once())
            ->method('getItem')
            ->willReturn($mockCacheItem);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to obtain Firebase access token');

        $this->provider->getAccessToken();
    }

    /**
     * @test
     */
    public function it_handles_invalid_service_account_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FirebaseCredentialProvider: invalid JSON in service account configuration.');

        new FirebaseCredentialProvider(
            $this->mockHttpClient,
            $this->mockCache,
            $this->mockLogger,
            'invalid json'
        );
    }

    /**
     * @test
     */
    public function it_handles_missing_required_fields_in_service_account(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FirebaseCredentialProvider: missing required fields in service account: private_key, client_email, project_id');

        new FirebaseCredentialProvider(
            $this->mockHttpClient,
            $this->mockCache,
            $this->mockLogger,
            json_encode(['type' => 'service_account']) // Missing required fields
        );
    }

    /**
     * @test
     */
    public function it_logs_token_fetch_operations(): void
    {
        $mockCacheItem = $this->createMock(CacheItemInterface::class);
        $mockCacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $mockResponse->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'access_token' => 'new_token',
                'expires_in' => 3600,
            ]);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $saveCacheItem = $this->createMock(CacheItemInterface::class);
        $saveCacheItem->expects($this->once())->method('set')->willReturnSelf();
        $saveCacheItem->expects($this->once())->method('expiresAfter')->willReturnSelf();

        $this->mockCache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturn($mockCacheItem, $saveCacheItem);
        $this->mockCache->expects($this->once())
            ->method('save');

        $this->mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Fetching new Firebase access token'],
                ['Firebase access token cached successfully']
            );

        $this->provider->getAccessToken();
    }

    /**
     * @test
     */
    public function it_logs_errors_during_token_fetch(): void
    {
        $mockCacheItem = $this->createMock(CacheItemInterface::class);
        $mockCacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $this->mockHttpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Network error'));

        $this->mockCache->expects($this->once())
            ->method('getItem')
            ->willReturn($mockCacheItem);

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Failed to fetch Firebase access token', $this->anything());

        try {
            $this->provider->getAccessToken();
        } catch (\RuntimeException $e) {
            // Expected
        }
    }
}