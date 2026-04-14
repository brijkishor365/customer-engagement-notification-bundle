# CEP Bundle - Security Guide

## Overview

The CEP Bundle implements comprehensive security measures to protect against common vulnerabilities and ensure safe communication with external services.

## Security Features

### Input Validation

All message parameters are strictly validated:

```php
// Automatic validation on NotificationMessage creation
$message = new NotificationMessage(
    recipient: '+66812345678',        // E.164 phone validation
    subject: 'Order Update',          // Length and content validation
    body: 'Your order shipped!',      // Size limits and sanitization
    channel: 'sms'                    // Supported channel validation
);
```

**Validation Rules:**
- **Phone Numbers**: E.164 format (+country code), length validation
- **Email Addresses**: RFC 5322 compliance, domain validation
- **FCM Tokens**: Exact 152-character format validation
- **LINE User IDs**: 33-character format starting with 'U'
- **Message Bodies**: Size limits (SMS: 160 GSM/70 Unicode, others configurable)
- **URLs**: HTTPS-only, no private IP ranges (SSRF protection)

### SSRF Protection

Server-Side Request Forgery protection for media URLs:

```php
// Blocked automatically
'whatsapp_media_url' => 'http://192.168.1.1/image.jpg'  // Private IP blocked
'whatsapp_media_url' => 'http://localhost/image.jpg'    // Localhost blocked

// Allowed
'whatsapp_media_url' => 'https://cdn.example.com/image.jpg'  // HTTPS public URL
```

**SSRF Protections:**
- Private IP range blocking (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
- Localhost/loopback address blocking
- HTTPS-only requirement for media URLs
- URL format validation

### Template Injection Prevention

HTML escaping and parameter sanitization:

```php
// Safe template rendering
$context = [
    'customerName' => '<script>alert("xss")</script>',  // Automatically escaped
    'orderNumber' => '1234',
];

// Result: &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;
```

### Credential Security

Sensitive data protection in logs and error messages:

```php
// Secure logging - no credentials exposed
$this->logger->error('Firebase auth failed', [
    'error' => 'invalid_grant',
    'channel' => 'push',
    // NO: 'token' => 'secret_token_here'
]);
```

**Security Measures:**
- No API keys or tokens in log files
- Masked error messages for authentication failures
- Secure credential storage (environment variables only)
- Automatic token cleanup and rotation

### HTTP Security

Hardened HTTP client configuration:

```yaml
# Automatic configuration
framework:
    http_client:
        default_options:
            timeout: 10      # 10 second timeout
            max_redirects: 3 # Limited redirects
            verify_peer: true # SSL verification
```

**HTTP Protections:**
- Configurable timeouts (default: 10 seconds)
- Maximum redirect limits (default: 3)
- SSL/TLS certificate verification
- Connection reuse and pooling

## Security Best Practices

### Environment Configuration

```bash
# Secure credential storage
export FIREBASE_SERVICE_ACCOUNT_JSON='{"type":"service_account",...}'
export TWILIO_AUTH_TOKEN='your_secure_token'
export WHATSAPP_ACCESS_TOKEN='your_access_token'

# Never in code or version control
# ❌ BAD: const API_KEY = 'secret';
# ✅ GOOD: Use %env(API_KEY)%
```

### Input Sanitization

Always validate and sanitize user inputs:

```php
// Controller level validation
public function sendNotification(Request $request): JsonResponse
{
    $recipient = $request->get('recipient');
    $body = $request->get('body');

    // Additional validation beyond automatic checks
    if (strlen($body) > 160) {
        return $this->json(['error' => 'Message too long'], 400);
    }

    // Use validated data
    $message = new NotificationMessage(/* validated params */);
}
```

### Rate Limiting

Implement rate limiting to prevent abuse:

```php
// Application-level rate limiting
#[Route('/notify/sms', methods: ['POST'])]
public function sendSms(Request $request): JsonResponse
{
    $clientIp = $request->getClientIp();

    // Check rate limit (e.g., 10 SMS per minute per IP)
    if (!$this->rateLimiter->checkLimit($clientIp, 'sms', 10, 60)) {
        return $this->json(['error' => 'Rate limit exceeded'], 429);
    }

    // Proceed with notification
}
```

### Monitoring and Logging

Comprehensive security monitoring:

```yaml
monolog:
    handlers:
        security:
            type: stream
            path: '%kernel.logs_dir%/security.log'
            level: warning
            channels: ['security']

        cep_audit:
            type: stream
            path: '%kernel.logs_dir%/cep_audit.log'
            level: info
            channels: ['cep']
```

**Monitor For:**
- Authentication failures
- Invalid recipient formats
- SSRF attempt patterns
- Rate limit violations
- Unusual notification volumes

### Error Handling

Secure error responses:

```php
try {
    $success = $this->notificationManager->send($message);
} catch (NotificationException $e) {
    // Log detailed error internally
    $this->logger->error('Notification failed', [
        'error' => $e->getMessage(),
        'channel' => $message->getChannel(),
        'recipient_hash' => hash('sha256', $message->getRecipient()), // Don't log PII
    ]);

    // Return generic error to client
    return $this->json(['error' => 'Notification service temporarily unavailable'], 500);
}
```

## Channel-Specific Security

### SMS Security

- Phone number validation prevents invalid formats
- Message length limits prevent buffer overflows
- No sensitive data in SMS content (use secure links instead)

### Email Security

- HTML sanitization prevents XSS in templates
- Link validation prevents malicious redirects
- Attachment scanning (when using Pimcore documents)

### Push Notification Security

- FCM token validation prevents token injection
- Server-side token validation with Firebase
- No sensitive data in push payloads

### WhatsApp Security

- Template approval prevents malicious content
- Media URL validation prevents SSRF
- Business verification requirements

### LINE Security

- User ID validation prevents impersonation
- Message content validation
- Channel access token rotation

## Compliance Considerations

### GDPR Compliance

```php
// Consent verification
if (!$user->hasMarketingConsent()) {
    throw new NotificationException('User has not consented to marketing communications');
}

// Data minimization
$message = new NotificationMessage(
    recipient: $user->getPhone(),  // Only necessary data
    // Avoid collecting unnecessary PII
);
```

### Audit Logging

```php
// Comprehensive audit trail
$this->auditLogger->log('notification_sent', [
    'channel' => $message->getChannel(),
    'recipient_type' => $this->getRecipientType($message->getRecipient()),
    'timestamp' => time(),
    'correlation_id' => $correlationId,
    // No PII in audit logs
]);
```

## Incident Response

### Security Incident Procedure

1. **Detection**: Monitor logs for suspicious patterns
2. **Containment**: Disable affected channels if compromised
3. **Investigation**: Review audit logs and access patterns
4. **Recovery**: Rotate all API credentials
5. **Lessons Learned**: Update security measures based on findings

### Emergency Contacts

- Rotate API credentials immediately upon suspected compromise
- Notify affected customers if breach involves PII
- Preserve all logs for forensic analysis

## Performance and Security

### Load Balancing

```yaml
# Multiple provider instances for high availability
services:
    cen.sms.provider.primary:
        class: CustomerEngagementNotificationBundle\Notification\Provider\Sms\TwilioSmsProvider
        arguments: ['%twilio_sid%', '%twilio_token%', '%twilio_number%']

    cen.sms.provider.backup:
        class: CustomerEngagementNotificationBundle\Notification\Provider\Sms\HttpSmsProvider
        arguments: ['@cen.http_sms_config']
```

### Circuit Breaker Pattern

```php
// Automatic failover on service degradation
if ($this->circuitBreaker->isOpen('twilio')) {
    // Use backup provider
    $provider = $this->backupSmsProvider;
}
```

## Testing Security

### Security Test Cases

```php
// Unit tests for security validation
class NotificationSecurityTest extends TestCase
{
    public function testSsrpProtection(): void
    {
        $this->expectException(SecurityException::class);
        new NotificationMessage(
            recipient: '+1234567890',
            body: 'Test',
            channel: 'whatsapp',
            context: ['whatsapp_media_url' => 'http://192.168.1.1/image.jpg']
        );
    }

    public function testInputValidation(): void
    {
        $this->expectException(ValidationException::class);
        new NotificationMessage(
            recipient: 'invalid-email',
            body: 'Test',
            channel: 'email'
        );
    }
}
```

This comprehensive security implementation ensures that the CEP Bundle can be safely used in production environments while protecting against common web application vulnerabilities.