# CEP Bundle for Pimcore

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/your-org/cep-bundle)
[![PHP Version](https://img.shields.io/badge/php-8.1+-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-6.0+-black.svg)](https://symfony.com/)
[![Pimcore Version](https://img.shields.io/badge/pimcore-11.0+-red.svg)](https://pimcore.com/)

The **CEP Bundle** (Communication and Engagement Platform) is a comprehensive multi-channel notification system for Pimcore applications. Send notifications via SMS, Email, Push Notifications, LINE messaging, and WhatsApp through a unified API.

## Features

- 🚀 **Multi-Channel Support**: SMS, Email, Push (Firebase), LINE, WhatsApp
- 🔒 **Security Hardened**: Input validation, SSRF protection, credential masking, template injection prevention
- 📧 **Pimcore Integration**: Native support for Pimcore Email Documents
- 🎨 **Rich Messaging**: Templates, media attachments, interactive buttons
- 📊 **Comprehensive Logging**: Full audit trail and error tracking
- ⚡ **High Performance**: Async processing and connection pooling
- 🧪 **Well Tested**: Comprehensive test suite with security validation

## Quick Start

### 1. Installation

```bash
composer require qburst/customer-engagement-notification-bundle
```

### 2. Register Bundle

Add to `config/bundles.php`:

```php
return [
    // ... other bundles
    CustomerEngagementNotificationBundle\CustomerEngagementNotificationBundle::class => ['all' => true],
];
```

### 3. Configure Environment

The bundle requires several environment variables for external service providers. All required variables are already configured in your project's `.env` file:

```bash
# SMS (Generic HTTP API)
SMS_API_URL=https://api.your-sms-gateway.com/v1/send
SMS_API_TOKEN=your_bearer_token_here

# Firebase Push Notifications
FIREBASE_SERVICE_ACCOUNT_PATH=/path/to/service-account.json

# LINE Messaging API
LINE_CHANNEL_ACCESS_TOKEN=your_channel_access_token_here

# Email
MAILER_FROM_EMAIL=noreply@yourdomain.com
MAILER_FROM_NAME="CEP Platform"

# WhatsApp Business API
WHATSAPP_PHONE_NUMBER_ID=1234567890123456
WHATSAPP_ACCESS_TOKEN=EAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### 4. Service Configuration

The bundle uses autowiring and automatic service discovery. No manual service configuration is required - all services are automatically registered and tagged.

### 5. Testing (Optional)

To run the test suite, install PHPUnit in your project:

```bash
composer require --dev phpunit/phpunit
```

Then run tests using the provided script:

```bash
cd vendor/qburst/customer-engagement-notification-bundle
./run-tests.sh
```

Or run tests directly:

```bash
vendor/bin/phpunit vendor/qburst/customer-engagement-notification-bundle/tests/
```
export WHATSAPP_ACCESS_TOKEN=your_access_token
export WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id

# LINE Messaging API
export LINE_CHANNEL_ACCESS_TOKEN=your_channel_access_token
```

### 4. Configure Services

Add to `config/services.yaml`:

```yaml
services:
    # Required HTTP client binding
    Symfony\Contracts\HttpClient\HttpClientInterface: '@pimcore.http_client'

    # Core notification services
    CustomerEngagementNotificationBundle\Notification\NotificationManager: ~
    CustomerEngagementNotificationBundle\Notification\NotificationFactory: ~

    # Channel implementations
    CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel:
        arguments: ['@CustomerEngagementNotificationBundle\Notification\Provider\Sms\TwilioSmsProvider']
        tags: [{ name: cen.notification.channel, channel: sms }]

    CustomerEngagementNotificationBundle\Notification\Channel\EmailChannel:
        arguments: ['@CustomerEngagementNotificationBundle\Notification\Provider\Email\PimcoreEmailProvider']
        tags: [{ name: cen.notification.channel, channel: email }]

    CustomerEngagementNotificationBundle\Notification\Channel\PushChannel:
        arguments: ['@CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebasePushProvider']
        tags: [{ name: cen.notification.channel, channel: push }]

    CustomerEngagementNotificationBundle\Notification\Channel\LineChannel:
        arguments: ['@CustomerEngagementNotificationBundle\Notification\Provider\Line\LineMessengerProvider']
        tags: [{ name: cen.notification.channel, channel: line }]

    CustomerEngagementNotificationBundle\Notification\Channel\WhatsAppChannel:
        arguments: ['@CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppCloudProvider']
        tags: [{ name: cen.notification.channel, channel: whatsapp }]
```

### 5. Send Your First Notification

```php
use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use CustomerEngagementNotificationBundle\Notification\NotificationManager;

class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationManager $notificationManager
    ) {}

    #[Route('/notify/test', methods: ['POST'])]
    public function testNotification(): JsonResponse
    {
        $message = new NotificationMessage(
            recipient: '+66812345678',
            subject: 'Test Notification',
            body: 'Hello from CEP Bundle! 🚀',
            channel: 'sms'
        );

        $success = $this->notificationManager->send($message);

        return $this->json(['success' => $success]);
    }
}
```

## Supported Channels

| Channel | Provider | Use Case |
|---------|----------|----------|
| **SMS** | Twilio, HTTP APIs | Transactional alerts, OTP codes |
| **Email** | Pimcore Documents, SMTP | Rich HTML emails, newsletters |
| **Push** | Firebase Cloud Messaging | Mobile app notifications |
| **LINE** | LINE Messaging API | Customer service, rich cards |
| **WhatsApp** | WhatsApp Business API | Business messaging, templates |

## Example Usage

### SMS Notification

```php
$message = new NotificationMessage(
    recipient: '+66812345678',
    subject: 'Order Update',
    body: 'Your order #1234 has been shipped!',
    channel: 'sms'
);

$this->notificationManager->send($message);
```

### Email with Pimcore Document

```php
$message = new NotificationMessage(
    recipient: 'customer@example.com',
    subject: 'Order Shipped',
    body: '',
    channel: 'email',
    context: [
        'document_path' => '/emails/order-shipped',
        'customerName' => 'John Doe',
        'orderNumber' => '1234',
        'trackingUrl' => 'https://track.example.com/1234'
    ]
);

$this->notificationManager->send($message);
```

### Firebase Push Notification

```php
$message = new NotificationMessage(
    recipient: 'fcm_registration_token_here',
    subject: 'New Message',
    body: 'You have a new message from support',
    channel: 'push',
    context: [
        'screen' => 'chat',
        'message_id' => 'msg_123'
    ]
);

$this->notificationManager->send($message);
```

### WhatsApp Template Message

```php
$message = new NotificationMessage(
    recipient: '+66812345678',
    subject: 'Order Confirmation',
    body: '',
    channel: 'whatsapp',
    context: [
        'whatsapp_type' => 'template',
        'whatsapp_template' => 'order_confirmation',
        'whatsapp_language' => 'en_US',
        'whatsapp_components' => [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => 'John Doe'],
                    ['type' => 'text', 'text' => '#1234']
                ]
            ]
        ]
    ]
);

$this->notificationManager->send($message);
```

### Multi-Channel Broadcast

```php
$message = new NotificationMessage(
    recipient: '+66812345678',  // SMS recipient
    subject: 'Flash Sale!',
    body: '50% off everything today only!',
    channel: 'sms',
    context: [
        'email_recipient' => 'customer@example.com',
        'line_user_id' => 'U1234567890abcdef...'
    ]
);

// Send to SMS, Email, and LINE simultaneously
$results = $this->notificationManager->broadcast($message, ['sms', 'email', 'line']);
```

## Security Features

- ✅ **Input Validation**: Strict validation of all message parameters
- ✅ **SSRF Protection**: URL validation with private IP blocking
- ✅ **Credential Security**: No sensitive data in logs
- ✅ **Template Injection Prevention**: HTML escaping and sanitization
- ✅ **HTTP Hardening**: Timeouts and redirect limits

## Documentation

- 📖 **[Architecture Overview](./01_Architecture-Overview.md)** - System design and components
- ⚙️ **[Configuration Guide](./02_Configuration.md)** - Detailed setup instructions
- 🚀 **[API Usage Guide](./03_API-Usage.md)** - Complete API reference with examples
- 🔒 **[Security Guide](./04_Security.md)** - Security features and best practices
- 🔧 **[Troubleshooting](./05_Troubleshooting.md)** - Common issues and solutions

## Requirements

- **PHP**: 8.1 or higher
- **Symfony**: 6.0 or higher
- **Pimcore**: 11.0 or higher
- **Extensions**: JSON, cURL, OpenSSL

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## Testing

```bash
# Run the test suite
php bin/phpunit

# Run security tests
php bin/phpunit --testsuite=security

# Run integration tests
php bin/phpunit --testsuite=integration
```

## License

This project is licensed under the MIT License - see the [LICENSE](../LICENSE.md) file for details.

## Support

- 📧 **Email**: support@yourcompany.com
- 💬 **Issues**: [GitHub Issues](https://github.com/your-org/cep-bundle/issues)
- 📚 **Documentation**: [Full Documentation](./01_Architecture-Overview.md)

---

**Made with ❤️ for Pimcore developers**

*Empower your applications with multi-channel communication capabilities*
