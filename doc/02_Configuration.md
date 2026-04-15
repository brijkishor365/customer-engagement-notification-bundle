# CEP Bundle - Configuration Guide

## Overview

The CEP Bundle uses modern Symfony autowiring and PHP attributes for configuration. Most services are automatically discovered and configured, requiring minimal manual setup.

## Environment Variables

Configure these environment variables in your `.env` file:

```bash
# SMS (Generic HTTP API)
SMS_API_URL=https://api.your-sms-gateway.com/v1/send
SMS_API_TOKEN=your_bearer_token_here

# Firebase Push Notifications (path to service account JSON file)
FIREBASE_SERVICE_ACCOUNT_PATH=/var/secrets/firebase/service-account.json

# LINE Messaging API
LINE_CHANNEL_ACCESS_TOKEN=your_channel_access_token_here

# Email Configuration
MAILER_FROM_EMAIL=noreply@yourdomain.com
MAILER_FROM_NAME="CEP Notification"

# WhatsApp Business API
WHATSAPP_PHONE_NUMBER_ID=1234567890123456
WHATSAPP_ACCESS_TOKEN=EAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## Automatic Service Configuration

The bundle uses autowiring and automatic service discovery. No manual service configuration is required in `services.yaml`. The following services are automatically registered:

### Core Services
- `CustomerEngagementNotificationBundle\Notification\NotificationManager` - Main notification orchestrator
- `CustomerEngagementNotificationBundle\Notification\NotificationFactory` - Channel factory with tagged iterator injection

### Channel Services (automatically tagged)
- `CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel` - SMS notifications
- `CustomerEngagementNotificationBundle\Notification\Channel\EmailChannel` - Email notifications
- `CustomerEngagementNotificationBundle\Notification\Channel\PushChannel` - Firebase push notifications
- `CustomerEngagementNotificationBundle\Notification\Channel\LineChannel` - LINE messaging
- `CustomerEngagementNotificationBundle\Notification\Channel\WhatsAppChannel` - WhatsApp messaging

### Provider Services
- `CustomerEngagementNotificationBundle\Notification\Provider\Sms\HttpSmsProvider` - Generic HTTP SMS
- `CustomerEngagementNotificationBundle\Notification\Provider\Email\PimcoreEmailProvider` - Pimcore Email Document provider
- `CustomerEngagementNotificationBundle\Notification\Provider\Email\SmtpEmailProvider` - SMTP email via Symfony Mailer
- `CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebasePushProvider` - Firebase push
- `CustomerEngagementNotificationBundle\Notification\Provider\Line\LineMessengerProvider` - LINE API
- `CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppCloudProvider` - WhatsApp API

## Manual Configuration (Optional)

If you need to customize the default configuration, you can override services in your project's `services.yaml`:

```yaml
services:
    # Example: Custom SMS provider configuration
    CustomerEngagementNotificationBundle\Notification\Provider\Sms\HttpSmsProvider:
        arguments:
            $config: '@your.custom.sms.config'

    # Example: Custom email provider
    CustomerEngagementNotificationBundle\Notification\Provider\Email\PimcoreEmailProvider:
        arguments:
            $fromEmail: '%env(CUSTOM_MAILER_FROM_EMAIL)%'
            $fromName: '%env(CUSTOM_MAILER_FROM_NAME)%'
```

## Bundle Registration

Ensure the bundle is registered in `config/bundles.php`:

```php
return [
    // ... other bundles
    CustomerEngagementNotificationBundle\CustomerEngagementNotificationBundle::class => ['all' => true],
];
```

## Routing Configuration

Routes are automatically configured via `src/Resources/config/pimcore/routing.yaml`. All API endpoints are available under `/api/notify/*`.

Available endpoints:
- `POST /api/notify/sms` - Send SMS
- `POST /api/notify/email` - Send email
- `POST /api/notify/push/device` - Send push to device
- `POST /api/notify/push/topic` - Send push to topic
- `POST /api/notify/line/text` - Send LINE text message
- `POST /api/notify/line/flex` - Send LINE flex message
- `POST /api/notify/whatsapp/template` - Send WhatsApp template
- And more...
    CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\Sms\TwilioSmsProvider'  # or your preferred SMS provider
        tags:
            - { name: cen.notification.channel, channel: sms }

    CustomerEngagementNotificationBundle\Notification\Channel\EmailChannel:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\Email\PimcoreEmailProvider'
        tags:
            - { name: cen.notification.channel, channel: email }

    CustomerEngagementNotificationBundle\Notification\Channel\PushChannel:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebasePushProvider'
        tags:
            - { name: cen.notification.channel, channel: push }

    CustomerEngagementNotificationBundle\Notification\Channel\LineChannel:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\Line\LineMessengerProvider'
        tags:
            - { name: cen.notification.channel, channel: line }

    CustomerEngagementNotificationBundle\Notification\Channel\WhatsAppChannel:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppCloudProvider'
        tags:
            - { name: cen.notification.channel, channel: whatsapp }
```

### Provider Configurations

#### Firebase Push Provider

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebaseCredentialProvider:
        arguments:
            - '@Symfony\Contracts\HttpClient\HttpClientInterface'
            - '@cache.app'  # or your preferred cache service
            - '@logger'
            - '%env(FIREBASE_SERVICE_ACCOUNT_JSON)%'

    CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebasePushProvider:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Provider\Push\FirebaseCredentialProvider'
            - '@Symfony\Contracts\HttpClient\HttpClientInterface'
```

#### Twilio SMS Provider

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Provider\Sms\TwilioSmsProvider:
        arguments:
            - '%env(TWILIO_ACCOUNT_SID)%'
            - '%env(TWILIO_AUTH_TOKEN)%'
            - '%env(TWILIO_PHONE_NUMBER)%'
            - '@Symfony\Contracts\HttpClient\HttpClientInterface'
```

#### LINE Messenger Provider

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Provider\Line\LineMessengerProvider:
        arguments:
            - '%env(LINE_CHANNEL_ACCESS_TOKEN)%'
            - '@Symfony\Contracts\HttpClient\HttpClientInterface'
```

#### WhatsApp Cloud Provider

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppCloudProvider:
        arguments:
            - '%env(WHATSAPP_ACCESS_TOKEN)%'
            - '%env(WHATSAPP_PHONE_NUMBER_ID)%'
            - '@Symfony\Contracts\HttpClient\HttpClientInterface'
```

#### HTTP SMS Provider (Generic)

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Config\HttpSmsProviderConfig:
        arguments:
            - '%env(HTTP_SMS_API_URL)%'
            - '%env(HTTP_SMS_API_KEY)%'
            # Add other config as needed

    CustomerEngagementNotificationBundle\Notification\Provider\Sms\HttpSmsProvider:
        arguments:
            - '@CustomerEngagementNotificationBundle\Notification\Config\HttpSmsProviderConfig'
            - '@CustomerEngagementNotificationBundle\Notification\Resolver\BodyTemplateResolver'
            - '@Symfony\Contracts\HttpClient\HttpClientInterface'
```

### Pimcore Email Provider

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Provider\Email\PimcoreEmailProvider:
        arguments:
            - '@pimcore.mail'  # Pimcore's mail service
```

### SMTP Email Provider

```yaml
services:
    CustomerEngagementNotificationBundle\Notification\Provider\Email\SmtpEmailProvider:
        arguments:
            - '@Symfony\Component\Mailer\MailerInterface'
            - '%env(MAILER_FROM_EMAIL)%'
            - '%env(MAILER_FROM_NAME)%'
            - '@logger'
```

## Bundle Registration

Ensure the bundle is registered in `config/bundles.php`:

```php
return [
    // ... other bundles
    CustomerEngagementNotificationBundle\CustomerEngagementNotificationBundle::class => ['all' => true],
];
```

## Optional Configuration

### Custom Channel Implementation

To add a custom notification channel:

```yaml
services:
    App\Notification\Channel\CustomChannel:
        arguments:
            - '@your_custom_provider'
        tags:
            - { name: cen.notification.channel, channel: custom }
```

### Custom Provider Implementation

Implement the appropriate provider interface:

```php
use CustomerEngagementNotificationBundle\Notification\Contract\SmsProviderInterface;

class CustomSmsProvider implements SmsProviderInterface
{
    public function sendSms(string $to, string $message): bool
    {
        // Your implementation
    }
}
```

## Security Considerations

- Store API credentials as environment variables, never in code
- Use HTTPS URLs for all external API endpoints
- Regularly rotate API tokens and keys
- Monitor notification logs for unusual activity
- Implement rate limiting at the application level

## Troubleshooting

### Common Issues

1. **"HttpClientInterface not found"**: Ensure the service binding is configured
2. **"Channel not supported"**: Check that the channel is properly tagged
3. **"Provider authentication failed"**: Verify API credentials and environment variables
4. **"Template not found"**: Ensure Pimcore Email Documents exist and are published

### Debug Logging

Enable debug logging to troubleshoot issues:

```yaml
monolog:
    handlers:
        cep_debug:
            type: stream
            path: '%kernel.logs_dir%/cep_%kernel.environment%.log'
            level: debug
            channels: ['cep']