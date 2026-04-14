<?php

namespace CustomerEngagementNotificationBundle\Notification\Provider\Push;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Manages Firebase OAuth2 access tokens.
 *
 * Flow:
 *   1. Build a signed JWT from the service account private key (RS256 via openssl)
 *   2. Exchange it at Google's token endpoint for a Bearer access token
 *   3. Cache the token (TTL = expires_in - 60s) to avoid redundant round-trips
 *
 * No external JWT library required — uses PHP's built-in openssl_sign().
 */
class FirebaseCredentialProvider
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY = 'cen.firebase.access_token';

    private readonly array $serviceAccount;

    /**
     * FirebaseCredentialProvider constructor.
     *
     * @param HttpClientInterface $httpClient HTTP client for making requests to Google OAuth2
     * @param CacheInterface $cache PSR-6 cache for storing access tokens
     * @param LoggerInterface $logger PSR-3 logger for recording authentication events
     * @param string $serviceAccountJson JSON string containing Firebase service account credentials
     * @param bool $skipValidation Skip private key validation (for testing)
     *
     * @throws \InvalidArgumentException If service account JSON is invalid or missing required fields
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface      $cache,
        private readonly LoggerInterface     $logger,
        string                               $serviceAccountJson,
        private readonly bool                $skipValidation = false,
    ) {
        try {
            $decoded = json_decode($serviceAccountJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                'FirebaseCredentialProvider: invalid JSON in service account configuration.',
                0,
                $e
            );
        }

        // Validate all required fields
        $requiredFields = ['private_key', 'client_email', 'project_id'];
        $missing = array_diff($requiredFields, array_keys($decoded));
        
        if ($missing) {
            throw new \InvalidArgumentException(
                sprintf(
                    'FirebaseCredentialProvider: missing required fields in service account: %s',
                    implode(', ', $missing)
                )
            );
        }

        // Validate private key format early
        if (!$this->skipValidation) {
            $privateKey = @openssl_pkey_get_private($decoded['private_key']);
            if ($privateKey === false) {
                throw new \InvalidArgumentException(
                    'FirebaseCredentialProvider: invalid private key format in service account.'
                );
            }
            openssl_free_key($privateKey);
        }

        $this->serviceAccount = $decoded;
    }

    /**
     * Returns a valid Bearer access token, refreshing from Google when expired.
     *
     * Uses caching to avoid redundant token requests. Tokens are cached with
     * a TTL of (expires_in - 60) seconds to ensure we don't use expired tokens.
     *
     * @return string Valid Firebase access token for API authentication
     *
     * @throws \RuntimeException If token cannot be obtained from Google
     */
    public function getAccessToken(): string
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): string {
            $token = $this->fetchNewToken();
            $item->expiresAfter(($token['expires_in'] ?? 3600) - 60);
            return $token['access_token'];
        });
    }

    /**
     * Get the Firebase project ID from the service account.
     *
     * @return string The Firebase project ID
     */
    public function getProjectId(): string
    {
        return $this->serviceAccount['project_id'];
    }


    /**
     * Fetch a new access token from Google's OAuth2 token endpoint.
     *
     * Builds a JWT assertion, sends it to Google, and returns the token response.
     * Includes timeout and redirect limits for security.
     *
     * @return array Token response data containing 'access_token' and 'expires_in'
     *
     * @throws \RuntimeException If token request fails or response is invalid
     */
    private function fetchNewToken(): array
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $this->buildJwt(),
                ],
                'timeout' => 10,
                'max_redirects' => 3,
            ]);

            $data = $response->toArray();

            if (!isset($data['access_token'])) {
                $this->logger->error('[firebase] Failed to obtain access token', [
                    'error' => $data['error'] ?? 'unknown',
                    'error_description' => $data['error_description'] ?? 'no details',
                ]);
                throw new \RuntimeException(
                    'FirebaseCredentialProvider: failed to obtain access token from Google.'
                );
            }

            $this->logger->debug('[firebase] Access token refreshed, expires in {exp}s', [
                'exp' => $data['expires_in'] ?? 3600,
            ]);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('[firebase] Token fetch failed: {error}', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Build a signed JWT for Google's OAuth2 token endpoint (RS256).
     *
     * Creates a JWT with the required claims for Firebase service account authentication.
     * Signs the JWT using the private key from the service account.
     *
     * @return string Complete JWT string ready for use in OAuth2 assertion
     *
     * @throws \RuntimeException If private key cannot be loaded or signing fails
     */
    private function buildJwt(): string
    {
        $now = time();

        $header  = $this->b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->b64u(json_encode([
            'iss'   => $this->serviceAccount['client_email'],
            'sub'   => $this->serviceAccount['client_email'],
            'aud'   => self::TOKEN_URL,
            'scope' => self::SCOPE,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $data       = $header . '.' . $payload;
        $privateKey = openssl_pkey_get_private($this->serviceAccount['private_key']);

        if ($privateKey === false) {
            throw new \RuntimeException('FirebaseCredentialProvider: could not load private key.');
        }

        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $data . '.' . $this->b64u($signature);
    }

    /**
     * URL-safe base64 encoding (RFC 4648 section 5).
     *
     * Converts standard base64 to URL-safe format by replacing + with - and / with _,
     * and removing padding characters.
     *
     * @param string $data Raw data to encode
     * @return string URL-safe base64 encoded string
     */
    private function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
