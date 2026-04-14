<?php

namespace CustomerEngagementNotificationBundle\Notification\Security;

use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;

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
     * Checks for template injection patterns.
     *
     * @param string $content The content to check
     * @return bool True if injection patterns are found
     */
    private function containsTemplateInjection(string $content): bool
    {
        // Check for common template injection patterns
        $patterns = [
            '/\{\{.*?\}\}/',     // Twig-style variables
            '/\{\%.*\%\}/',      // Twig blocks
            '/<\?php/i',         // PHP code
            '/<%.*?%>/',         // ASP/JSP style
            '/\$\{.*?\}/',       // EL expressions
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}