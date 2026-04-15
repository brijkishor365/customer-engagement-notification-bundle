<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Security;

use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

/**
 * Security validator for notification messages.
 *
 * Provides validation to prevent common security issues like SSRF attacks,
 * template injection, and other input-based vulnerabilities.
 */
class MessageValidator
{
    /**
     * Private IP ranges that should be blocked for SSRF protection.
     */
    private const PRIVATE_IP_RANGES = [
        '127.0.0.0/8',    // localhost
        '10.0.0.0/8',     // private class A
        '172.16.0.0/12',  // private class B
        '192.168.0.0/16', // private class C
        '169.254.0.0/16', // link-local
        'fc00::/7',       // IPv6 private
        'fe80::/10',      // IPv6 link-local
    ];

    /**
     * Rate limiting storage (in production, use Redis/cache).
     */
    private array $rateLimitStorage = [];

    /**
     * Validates a notification message for security issues.
     *
     * @param NotificationMessage $message The message to validate
     * @return bool True if the message passes all security checks
     */
    public function validateMessage(NotificationMessage $message): bool
    {
        // Check for SSRF in URLs within context
        if (!$this->validateUrls($message->getContext())) {
            return false;
        }

        // Check for template injection patterns
        if ($this->containsTemplateInjection($message->getBody())) {
            return false;
        }

        if ($this->containsTemplateInjection($message->getSubject())) {
            return false;
        }

        return true;
    }

    /**
     * Validates a message template for injection attacks.
     *
     * @param NotificationMessage $message The message to validate
     * @return bool True if the template is safe
     */
    public function validateTemplate(NotificationMessage $message): bool
    {
        return !$this->containsTemplateInjection($message->getBody()) &&
               !$this->containsTemplateInjection($message->getSubject());
    }

    /**
     * Sanitizes context data for logging by masking sensitive information.
     *
     * @param array $context The context data to sanitize
     * @return array The sanitized context data
     */
    public function sanitizeForLogging(array $context): array
    {
        $sanitized = [];
        $sensitiveKeys = ['api_key', 'apikey', 'password', 'passwd', 'token', 'secret', 'key'];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForLogging($value);
            } elseif (in_array(strtolower($key), $sensitiveKeys, true)) {
                $sanitized[$key] = '***';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Checks if a recipient is within rate limits for a channel.
     *
     * @param string $recipient The recipient identifier
     * @param string $channel The notification channel
     * @return bool True if within rate limits
     */
    public function checkRateLimit(string $recipient, string $channel): bool
    {
        $key = $recipient . ':' . $channel;
        $now = time();
        $window = 3600; // 1 hour window
        $limit = 5; // 5 messages per hour per recipient per channel

        if (!isset($this->rateLimitStorage[$key])) {
            $this->rateLimitStorage[$key] = [];
        }

        // Remove old entries outside the window
        $this->rateLimitStorage[$key] = array_filter(
            $this->rateLimitStorage[$key],
            fn($timestamp) => $timestamp > ($now - $window)
        );

        // Check if under limit
        if (count($this->rateLimitStorage[$key]) < $limit) {
            $this->rateLimitStorage[$key][] = $now;
            return true;
        }

        return false;
    }

    /**
     * Validates URLs in the context to prevent SSRF attacks.
     *
     * @param array $context The context array to check
     * @return bool True if all URLs are safe
     */
    private function validateUrls(array $context): bool
    {
        $urls = $this->extractUrls($context);

        foreach ($urls as $url) {
            if (!$this->isUrlSafe($url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extracts URLs from context array recursively.
     *
     * @param array $data The data to search
     * @return array List of URLs found
     */
    private function extractUrls(array $data): array
    {
        $urls = [];

        foreach ($data as $value) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $urls[] = $value;
            } elseif (is_array($value)) {
                $urls = array_merge($urls, $this->extractUrls($value));
            }
        }

        return $urls;
    }

    /**
     * Checks if a URL is safe (not pointing to private/internal resources).
     *
     * @param string $url The URL to check
     * @return bool True if the URL is safe
     */
    private function isUrlSafe(string $url): bool
    {
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['host'])) {
            return true; // Not a full URL, assume safe
        }

        $host = $parsed['host'];

        // Check if host is a private IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !$this->isPrivateIp($host);
        }

        // Check for localhost variants
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'])) {
            return false;
        }

        return true;
    }

    /**
     * Checks if an IP address is in a private range.
     *
     * @param string $ip The IP address to check
     * @return bool True if the IP is private
     */
    private function isPrivateIp(string $ip): bool
    {
        foreach (self::PRIVATE_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if an IP is within a CIDR range.
     *
     * @param string $ip The IP address
     * @param string $range The CIDR range
     * @return bool True if IP is in range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $mask] = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4InRange($ip, $subnet, (int)$mask);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int)$mask);
        }

        return false;
    }

    /**
     * Checks if IPv4 is in range.
     */
    private function ipv4InRange(string $ip, string $subnet, int $mask): bool
    {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Checks if IPv6 is in range (simplified).
     */
    private function ipv6InRange(string $ip, string $subnet, int $mask): bool
    {
        // Simplified IPv6 check - in production, use proper IPv6 range checking
        return strpos($ip, $subnet) === 0;
    }

    /**
     * Sanitizes input data to prevent XSS and other injection attacks.
     *
     * @param array $data The input data to sanitize
     * @return array The sanitized data
     */
    public function sanitizeInput(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_string($value)) {
                // Remove script tags and their content
                $sanitized[$key] = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $value);
                // For message fields, allow basic HTML, for others strip all tags
                if (strtolower($key) === 'message') {
                    // Allow basic formatting tags
                    $allowedTags = '<b><i><u><strong><em>';
                    $sanitized[$key] = strip_tags($sanitized[$key], $allowedTags);
                } else {
                    $sanitized[$key] = strip_tags($sanitized[$key]);
                }
                // Remove null bytes
                $sanitized[$key] = str_replace("\0", '', $sanitized[$key]);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Validates message size limits for different channels.
     *
     * @param NotificationMessage $message The message to validate
     * @param string $channel The channel (optional, uses message channel if not provided)
     * @return bool True if within size limits
     */
    public function validateMessageSize(NotificationMessage $message, string $channel = ''): bool
    {
        $channel = $channel ?: $message->getChannel();
        $body = $message->getBody();

        return match ($channel) {
            'sms' => strlen($body) <= 160,
            'email' => strlen($body) <= 100000, // 100KB reasonable limit
            'push' => strlen($body) <= 4000,     // FCM limit
            'line' => strlen($body) <= 2000,     // LINE limit
            'whatsapp' => strlen($body) <= 4096, // WhatsApp limit
            default => strlen($body) <= 10000,   // Default limit
        };
    }

    /**
     * Detects suspicious content patterns that may indicate spam or phishing.
     *
     * @param NotificationMessage $message The message to check
     * @return bool True if suspicious content is detected
     */
    public function detectSuspiciousContent(NotificationMessage $message): bool
    {
        $content = $message->getBody() . ' ' . $message->getSubject();
        $suspiciousPatterns = [
            '/urgent|immediate|action required/i',
            '/account.*suspend|account.*block/i',
            '/verify.*account|confirm.*identity/i',
            '/click.*here|visit.*link/i',
            '/win.*prize|won.*lottery|you.*won|you.*win/i',
            '/free.*money|cash.*prize/i',
            '/bank.*account|credit.*card/i',
            '/password.*reset|change.*password/i',
            '/send.*details|provide.*information/i',
            '/pay.*\$|payment.*required|release.*pay/i',
            '/http:\/\/|https:\/\/[^\s]*\.(exe|zip|rar|bat|cmd|scr)/i', // Suspicious file extensions
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates recipient format for different channels.
     *
     * @param NotificationMessage $message The message to validate
     * @return bool True if recipient format is valid
     */
    public function validateRecipient(NotificationMessage $message): bool
    {
        $recipient = $message->getRecipient();
        $channel = $message->getChannel();

        return match ($channel) {
            'sms' => $this->isValidPhoneNumber($recipient),
            'email' => $this->isValidEmail($recipient),
            'push' => $this->isValidPushToken($recipient),
            'line' => $this->isValidLineUserId($recipient),
            'whatsapp' => $this->isValidPhoneNumber($recipient),
            default => false,
        };
    }

    /**
     * Validates phone number format.
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // Basic phone validation: starts with +, followed by digits, optional spaces/dashes
        return preg_match('/^\+[1-9]\d{1,14}$/', preg_replace('/[\s\-\(\)]/', '', $phone));
    }

    /**
     * Validates email format.
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates push notification token format.
     */
    private function isValidPushToken(string $token): bool
    {
        // Firebase tokens are typically 152-256 characters, alphanumeric with some special chars
        return preg_match('/^[a-zA-Z0-9_\-\.]{50,300}$/', $token);
    }

    /**
     * Validates LINE user ID format.
     */
    private function isValidLineUserId(string $userId): bool
    {
        // LINE user IDs start with U and are followed by 32 hex characters
        return preg_match('/^U[a-f0-9]{32}$/i', $userId);
    }

    /**
     * Checks for template injection patterns.
     *
     * @param string $content The content to check
     * @return bool True if injection patterns are found
     */
    private function containsTemplateInjection(string $content): bool
    {
        // Check for dangerous patterns that should always be blocked
        $dangerousPatterns = [
            '/\{\%.*\%\}/',      // Twig blocks
            '/<\?php/i',         // PHP code
            '/<%.*?%>/',         // ASP/JSP style
            '/\$\{.*?\}/',       // EL expressions
            '/<script/i',        // Script tags
            '/\.\./',            // Path traversal (..)
            '/etc\/passwd/i',    // Common path traversal target
            '/on\w+\s*=/i',      // Event handlers like onerror=
            '/javascript:/i',    // JavaScript URLs
            '/\';\s*DROP/i',     // SQL injection patterns
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        // Check Twig variables - allow safe ones, block dangerous ones
        if (preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches)) {
            foreach ($matches[1] as $expression) {
                // Block dangerous Twig expressions
                $dangerousTwig = [
                    '/_self/i',
                    '/env/i',
                    '/registerUndefinedFilterCallback/i',
                    '/exec/i',
                    '/\*+/',    // Multiplication
                    '/\++/',    // Addition (multiple +)
                    '/\-+/',    // Subtraction (multiple -)
                    '/\/+/',    // Division
                    '/\%+/',    // Modulo
                ];
                foreach ($dangerousTwig as $pattern) {
                    if (preg_match($pattern, $expression)) {
                        return true;
                    }
                }
                // Allow simple variables and basic filters
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\|[a-zA-Z_][a-zA-Z0-9_]*)*$/', trim($expression))) {
                    return true; // Block complex expressions
                }
            }
        }

        return false;
    }
}