<?php
/**
 * Tests for Security Validation
 *
 * Covers:
 * - SSRF protection in URL validation
 * - Template injection prevention
 * - Credential masking in logs
 * - Rate limiting validation
 * - Input sanitization
 */

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification\Security;

use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use Qburst\CustomerEngagementNotificationBundle\Notification\Security\MessageValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityValidationTest extends TestCase
{
    private MessageValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new MessageValidator();
    }

    #[DataProvider('ssrfProtectionDataProvider')]
    public function test_it_prevents_ssrf_attacks_in_urls(string $url, bool $expectedValid): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'email');

        // Simulate URL in message body or context
        $message->setContext(['callback_url' => $url]);

        $isValid = $this->validator->validateMessage($message);

        if ($expectedValid) {
            self::assertTrue($isValid);
        } else {
            self::assertFalse($isValid);
        }
    }

    public static function ssrfProtectionDataProvider(): array
    {
        return [
            'valid_https_url' => ['https://api.example.com/webhook', true],
            'valid_http_url' => ['http://api.example.com/webhook', true],
            'localhost_http' => ['http://localhost/webhook', false],
            'localhost_https' => ['https://localhost/webhook', false],
            '127_0_0_1' => ['http://127.0.0.1/webhook', false],
            'internal_ip' => ['http://192.168.1.1/webhook', false],
            'zero_zero_zero_zero' => ['http://0.0.0.0/webhook', false],
            'internal_hostname' => ['http://internal.company.com/webhook', false],
            'file_scheme' => ['file:///etc/passwd', false],
            'ftp_scheme' => ['ftp://example.com/file', false],
            'data_scheme' => ['data:text/plain;base64,SGVsbG8=', false],
            'javascript_url' => ['javascript:alert("xss")', false],
            'relative_url' => ['../admin', false],
            'empty_url' => ['', true], // Empty should be allowed
        ];
    }

    #[DataProvider('templateInjectionDataProvider')]
    public function test_it_prevents_template_injection(string $template, bool $expectedValid): void
    {
        $message = new NotificationMessage('recipient', 'Subject', $template, 'email');

        $isValid = $this->validator->validateTemplate($message);

        if ($expectedValid) {
            self::assertTrue($isValid);
        } else {
            self::assertFalse($isValid);
        }
    }

    public static function templateInjectionDataProvider(): array
    {
        return [
            'safe_template' => ['Hello {{name}}, welcome!', true],
            'template_with_function' => ['Hello {{name|upper}}', true],
            'dangerous_php_code' => ['<?php echo "hacked"; ?>', false],
            'script_tag' => ['<script>alert("xss")</script>', false],
            'template_injection' => ['{{_self.env.registerUndefinedFilterCallback("exec")}}', false],
            'twig_injection' => ['{{7*7}}', false], // Should be blocked in strict mode
            'html_injection' => ['<img src=x onerror=alert(1)>', false],
            'sql_injection' => ["'; DROP TABLE users; --", false],
            'path_traversal' => ['../../../etc/passwd', false],
            'unicode_obfuscation' => ['<script>alert(1)</script>', false],
        ];
    }

    /**
     */
    public function test_it_masks_credentials_in_log_messages(): void
    {
        $message = new NotificationMessage('recipient', 'Subject', 'Body', 'email');
        $message->setContext([
            'api_key' => 'sk-1234567890abcdef',
            'password' => 'secret123',
            'token' => 'bearer_abcdef123456',
            'safe_field' => 'normal_value',
        ]);

        $logContext = $this->validator->sanitizeForLogging($message->getContext());

        self::assertEquals('***', $logContext['api_key']);
        self::assertEquals('***', $logContext['password']);
        self::assertEquals('***', $logContext['token']);
        self::assertEquals('normal_value', $logContext['safe_field']);
    }

    /**
     */
    public function test_it_validates_rate_limiting_rules(): void
    {
        $recipient = '+66812345678';

        // First few requests should pass
        for ($i = 0; $i < 5; $i++) {
            $isAllowed = $this->validator->checkRateLimit($recipient, 'sms');
            self::assertTrue($isAllowed, "Request $i should be allowed");
        }

        // Additional requests should be blocked
        $isAllowed = $this->validator->checkRateLimit($recipient, 'sms');
        self::assertFalse($isAllowed, 'Rate limit should be exceeded');
    }

    /**
     */
    public function test_it_sanitizes_input_data(): void
    {
        $dirtyData = [
            'name' => 'John<script>alert("xss")</script>Doe',
            'email' => 'user@example.com',
            'message' => 'Hello <b>world</b>',
            'phone' => '+66812345678',
            'url' => 'https://example.com?param=<script>',
        ];

        $sanitized = $this->validator->sanitizeInput($dirtyData);

        self::assertEquals('JohnDoe', $sanitized['name']); // Script tags removed
        self::assertEquals('user@example.com', $sanitized['email']); // Email unchanged
        self::assertEquals('Hello <b>world</b>', $sanitized['message']); // HTML allowed for message
        self::assertEquals('+66812345678', $sanitized['phone']); // Phone unchanged
        self::assertEquals('https://example.com?param=', $sanitized['url']); // Script removed from URL
    }

    /**
     */
    public function test_it_validates_message_size_limits(): void
    {
        // Test SMS length limit (160 chars)
        $longSms = str_repeat('A', 161);
        $smsMessage = new NotificationMessage('+66812345678', 'Subject', $longSms, 'sms');

        $isValid = $this->validator->validateMessageSize($smsMessage, 'sms');
        self::assertFalse($isValid, 'SMS over 160 characters should be invalid');

        // Test valid SMS
        $validSms = str_repeat('A', 160);
        $validSmsMessage = new NotificationMessage('+66812345678', 'Subject', $validSms, 'sms');

        $isValid = $this->validator->validateMessageSize($validSmsMessage, 'sms');
        self::assertTrue($isValid, 'SMS within 160 characters should be valid');

        // Test email size (no strict limit, but reasonable)
        $longEmail = str_repeat('A', 10000);
        $emailMessage = new NotificationMessage('user@example.com', 'Subject', $longEmail, 'email');

        $isValid = $this->validator->validateMessageSize($emailMessage, 'email');
        self::assertTrue($isValid, 'Email should allow longer content');
    }

    /**
     */
    public function test_it_detects_suspicious_patterns(): void
    {
        $suspiciousMessages = [
            'URGENT: Your account has been compromised! Click here: http://fake-bank.com/login',
            'You have won $1,000,000! Send your details to scammer@email.com',
            'Download free money here: bitcoin-wallet.exe',
            'Your package is delayed. Pay $50 to release: paypal.me/fake',
        ];

        foreach ($suspiciousMessages as $message) {
            $notification = new NotificationMessage('recipient', 'Subject', $message, 'email');
            $isSuspicious = $this->validator->detectSuspiciousContent($notification);
            self::assertTrue($isSuspicious, "Message should be flagged as suspicious: $message");
        }

        $safeMessages = [
            'Your order #12345 has been shipped',
            'Welcome to our newsletter!',
            'Meeting scheduled for tomorrow at 2 PM',
        ];

        foreach ($safeMessages as $message) {
            $notification = new NotificationMessage('recipient', 'Subject', $message, 'email');
            $isSuspicious = $this->validator->detectSuspiciousContent($notification);
            self::assertFalse($isSuspicious, "Message should not be flagged as suspicious: $message");
        }
    }

    /**
     */
    public function test_it_validates_recipient_format(): void
    {
        $validRecipients = [
            ['+66812345678', 'sms'],
            ['user@example.com', 'email'],
            ['eA1B2cD3E4fG5H6iJ7K8lM9nO0pQ1R2sT3uV4wX5yZ6aB7cD8eF9gH0iJ1kL2mN3oP4qR5sT6uV7wX8yZ9', 'push'],
            ['U1234567890abcdef1234567890abcdef', 'line'],
            ['+66812345678', 'whatsapp'],
        ];

        foreach ($validRecipients as [$recipient, $channel]) {
            $message = new NotificationMessage($recipient, 'Subject', 'Body', $channel);
            $isValid = $this->validator->validateRecipient($message);
            self::assertTrue($isValid, "Valid recipient $recipient for $channel should pass");
        }

        $invalidRecipients = [
            ['invalid-phone', 'sms'],
            ['not-an-email', 'email'],
            ['invalid-token', 'push'],
            ['invalid-user-id', 'line'],
            ['invalid-whatsapp', 'whatsapp'],
        ];

        foreach ($invalidRecipients as [$recipient, $channel]) {
            $message = new NotificationMessage($recipient, 'Subject', 'Body', $channel);
            $isValid = $this->validator->validateRecipient($message);
            self::assertFalse($isValid, "Invalid recipient $recipient for $channel should fail");
        }
    }
}