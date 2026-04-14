# CEP Bundle - Troubleshooting Guide

## Overview

This guide helps you diagnose and resolve common issues with the CEP Bundle notification system.

## Quick Diagnosis

### Check Bundle Installation

```bash
# Verify bundle is registered
php bin/console pimcore:bundle:list

# Should show:
# CustomerEngagementNotificationBundle (CustomerEngagementNotificationBundle\CustomerEngagementNotificationBundle) - installed, active
```

### Test Basic Functionality

```php
// In a controller or command
$message = new NotificationMessage(
    recipient: '+66812345678',
    subject: 'Test',
    body: 'Test message',
    channel: 'sms'
);

$success = $this->notificationManager->send($message);
var_dump($success); // Should be true or false
```

### Check Logs

```bash
# View recent logs
tail -f var/log/dev.log | grep cep

# Check for errors
grep -i error var/log/dev.log
```

## Common Issues and Solutions

### Issue: "HttpClientInterface not found"

**Symptoms:**
```
Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException:
You have requested a non-existent service "Symfony\Contracts\HttpClient\HttpClientInterface"
```

**Solution:**
Add the service binding to `config/services.yaml`:

```yaml
services:
    Symfony\Contracts\HttpClient\HttpClientInterface: '@pimcore.http_client'
```

### Issue: "Channel not supported"

**Symptoms:**
```
CustomerEngagementNotificationBundle\Notification\Exception\NotificationException:
Channel 'custom' is not supported
```

**Solutions:**

1. **Check channel registration:**
```yaml
# Ensure channel is properly tagged
services:
    CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel:
        tags:
            - { name: cen.notification.channel, channel: sms }
```

2. **Verify supported channels:**
   - `sms` - SMS messaging
   - `email` - Email notifications
   - `push` - Firebase push notifications
   - `line` - LINE messaging
   - `whatsapp` - WhatsApp Business API

### Issue: "Provider authentication failed"

**Symptoms:**
```
Authentication failed for provider TwilioSmsProvider
```

**Solutions:**

1. **Check environment variables:**
```bash
# Verify credentials are set
echo $TWILIO_ACCOUNT_SID
echo $TWILIO_AUTH_TOKEN
echo $TWILIO_PHONE_NUMBER
```

2. **Test credentials manually:**
```bash
# Twilio API test
curl -X GET "https://api.twilio.com/2010-04-01/Accounts/$TWILIO_ACCOUNT_SID" \
     -u "$TWILIO_ACCOUNT_SID:$TWILIO_AUTH_TOKEN"
```

3. **Check credential format:**
   - Account SID: Starts with 'AC'
   - Auth Token: 32-character string
   - Phone Number: E.164 format (+country code)

### Issue: "Template not found" (Email)

**Symptoms:**
```
Pimcore document not found: /emails/order-shipped
```

**Solutions:**

1. **Verify document exists:**
   - Go to Pimcore Admin → Documents
   - Check path `/emails/order-shipped` exists
   - Ensure document is published

2. **Check document type:**
   - Must be Email Document type
   - Should have subject and HTML content

3. **Verify path format:**
```php
// Correct
'document_path' => '/emails/order-shipped',

// Incorrect
'document_path' => 'order-shipped',
'document_path' => '/order-shipped',
```

### Issue: Firebase Push Notifications Failing

**Symptoms:**
```
Firebase authentication failed: invalid_grant
```

**Solutions:**

1. **Check service account JSON:**
```bash
# Verify environment variable
echo $FIREBASE_SERVICE_ACCOUNT_JSON | jq .project_id
```

2. **Validate JSON format:**
```json
{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "client_email": "...@your-project.iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs"
}
```

3. **Check FCM token validity:**
   - Tokens are 152 characters long
   - Must be obtained from client app
   - Tokens can expire and need refresh

### Issue: WhatsApp Template Not Sending

**Symptoms:**
```
WhatsApp template message failed: template not approved
```

**Solutions:**

1. **Verify template approval:**
   - Templates must be approved by WhatsApp/Meta
   - Check template status in WhatsApp Business Manager

2. **Check template name and language:**
```php
'whatsapp_template' => 'order_shipped',  // Exact name from WhatsApp
'whatsapp_language' => 'en_US',          // Exact language code
```

3. **Validate template parameters:**
   - Parameter count must match template variables
   - Parameter types must be correct (text, image, etc.)

### Issue: LINE Messages Not Delivered

**Symptoms:**
```
LINE API error: invalid user id
```

**Solutions:**

1. **Verify user ID format:**
   - Must be exactly 33 characters
   - Must start with 'U'
   - Example: `U1a2b3c4d5e6f7a2b3c4d5e6f7a2b3c4`

2. **Check channel access token:**
```bash
# Test token validity
curl -X GET "https://api.line.me/v2/bot/info" \
     -H "Authorization: Bearer $LINE_CHANNEL_ACCESS_TOKEN"
```

3. **Verify user consent:**
   - User must have added your LINE Official Account
   - User must not have blocked your account

### Issue: Phone Number Validation Errors

**Symptoms:**
```
Invalid phone number format: +668123456789
```

**Solutions:**

1. **Check E.164 format:**
   - Must start with '+'
   - Must include country code
   - No spaces, dashes, or parentheses

```php
// Valid formats
'+66812345678'   // Thailand
'+1234567890'    // US
'+447700900000'  // UK

// Invalid formats
'0812345678'     // Missing country code
'+66 812345678'  // Contains space
'081-234-5678'   // Contains dashes
```

2. **Verify country code:**
   - Thailand: +66
   - US: +1
   - UK: +44

### Issue: Email Validation Errors

**Symptoms:**
```
Invalid email format: user@domain
```

**Solutions:**

1. **Check RFC 5322 compliance:**
```php
// Valid
'user@example.com'
'user.name+tag@example.com'
'user@localhost'  // For testing

// Invalid
'user@'           // Missing domain
'@example.com'    // Missing local part
'user'            // Missing @ symbol
```

2. **Test with Pimcore mail service:**
```php
// Verify Pimcore can send emails
$mailer = $this->get('pimcore.mail');
$mailer->send($email);
```

## Performance Issues

### Issue: Slow Notification Delivery

**Symptoms:**
Notifications take more than 10 seconds to send

**Solutions:**

1. **Check HTTP timeouts:**
```yaml
framework:
    http_client:
        default_options:
            timeout: 10  # Increase if needed
```

2. **Monitor external API performance:**
   - Check provider status pages
   - Verify API rate limits
   - Consider provider failover

3. **Optimize for high volume:**
```yaml
# Use async processing for bulk notifications
services:
    cen.notification.async_manager:
        class: CustomerEngagementNotificationBundle\Notification\AsyncNotificationManager
        arguments: ['@messenger.default_bus']
```

### Issue: Memory Usage High

**Symptoms:**
PHP memory exhausted during bulk notifications

**Solutions:**

1. **Process in batches:**
```php
$batchSize = 50;
foreach (array_chunk($messages, $batchSize) as $batch) {
    foreach ($batch as $message) {
        $this->notificationManager->send($message);
    }
    sleep(1); // Rate limiting
}
```

2. **Use streaming for large datasets:**
```php
// Process from database cursor instead of loading all at once
$users = $this->userRepository->findUsersNeedingNotification();
foreach ($users as $user) {
    // Send notification
}
```

## Debug Tools

### Enable Debug Logging

```yaml
monolog:
    handlers:
        cep_debug:
            type: stream
            path: '%kernel.logs_dir%/cep_debug.log'
            level: debug
            channels: ['cep']
```

### Test Individual Components

```php
// Test channel factory
$channel = $this->notificationFactory->createChannel('sms');
var_dump($channel); // Should return SmsChannel instance

// Test provider directly
$provider = $this->container->get(CustomerEngagementNotificationBundle\Notification\Provider\Sms\TwilioSmsProvider::class);
$success = $provider->sendSms('+66812345678', 'Test');
var_dump($success);
```

### Monitor External APIs

```bash
# Test Twilio API
curl -u "$TWILIO_ACCOUNT_SID:$TWILIO_AUTH_TOKEN" \
     "https://api.twilio.com/2010-04-01/Accounts/$TWILIO_ACCOUNT_SID/Messages.json" \
     -d "To=%2B66812345678" \
     -d "From=%2B1234567890" \
     -d "Body=Test"

# Test Firebase
curl -X POST "https://fcm.googleapis.com/v1/projects/$PROJECT_ID/messages:send" \
     -H "Authorization: Bearer $ACCESS_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"message":{"token":"test_token","notification":{"title":"Test","body":"Test"}}}'
```

## Emergency Procedures

### Complete Service Outage

1. **Check all provider statuses:**
   - Twilio Status: https://status.twilio.com/
   - Firebase Status: https://status.firebase.google.com/
   - WhatsApp Status: https://developers.facebook.com/status/

2. **Switch to backup providers:**
```yaml
# Configure backup SMS provider
services:
    CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\Sms\HttpSmsProvider'  # Backup
```

3. **Implement circuit breaker:**
```php
if ($this->circuitBreaker->isOpen('primary_sms')) {
    $this->logger->warning('Switching to backup SMS provider');
    // Use backup provider
}
```

### Data Recovery

1. **Check notification logs:**
```sql
-- Find failed notifications
SELECT * FROM notification_log
WHERE status = 'failed'
AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

2. **Retry failed notifications:**
```php
$failedNotifications = $this->notificationRepository->findFailed();
foreach ($failedNotifications as $notification) {
    $this->notificationManager->send($notification);
}
```

## Support Resources

### Documentation
- [API Usage Guide](./03_API-Usage.md)
- [Configuration Guide](./02_Configuration.md)
- [Security Guide](./04_Security.md)

### External Resources
- [Twilio Documentation](https://www.twilio.com/docs)
- [Firebase Cloud Messaging](https://firebase.google.com/docs/cloud-messaging)
- [WhatsApp Business API](https://developers.facebook.com/docs/whatsapp/)
- [LINE Messaging API](https://developers.line.biz/en/docs/messaging-api/)

### Getting Help

1. **Check logs first:** Enable debug logging and review error messages
2. **Test components individually:** Isolate issues to specific providers
3. **Verify configuration:** Double-check all environment variables and service definitions
4. **Contact support:** Include full error logs and configuration details