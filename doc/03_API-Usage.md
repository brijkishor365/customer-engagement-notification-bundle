# CEP Bundle - API Usage Guide

## Overview

This guide demonstrates how to use the CEP Bundle for sending notifications across different channels. All examples are based on the `NotificationController` implementation.

## Basic Usage

### Injecting the NotificationManager

```php
use CustomerEngagementNotificationBundle\Notification\NotificationManager;

class YourController extends AbstractController
{
    public function __construct(
        private readonly NotificationManager $notificationManager
    ) {}

    // Your methods here
}
```

## SMS Notifications

### Basic SMS

```php
#[Route('/notify/sms', methods: ['POST'])]
public function sendSms(): JsonResponse
{
    $message = new NotificationMessage(
        recipient: '+66812345678',        // E.164 format required
        subject: 'Order Update',          // Optional for SMS
        body: 'Your order #1234 has been shipped!', // SMS content
        channel: 'sms'
    );

    $success = $this->notificationManager->send($message);

    return $this->json(['success' => $success]);
}
```

**Requirements:**
- Recipient must be in E.164 format (+country code)
- Body limited to SMS character limits (160 GSM, 70 Unicode)
- Subject is optional but recommended for logging

## Email Notifications

### Pimcore Email Document Mode

```php
#[Route('/notify/email', methods: ['POST'])]
public function sendEmail(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: 'customer@example.com',
        subject: 'Your order has shipped',     // fallback if doc has no subject
        body: '',                              // ignored in document mode
        channel: 'email',
        context: [
            'document_path' => '/emails/order-shipped',  // Pimcore document path
            'customerName' => 'Jane Doe',
            'orderNumber' => '1234',
            'trackingUrl' => 'https://track.example.com/1234',
            'items' => [
                ['name' => 'Widget A', 'qty' => 2, 'price' => '฿199'],
                ['name' => 'Widget B', 'qty' => 1, 'price' => '฿299'],
            ],
        ]
    ));

    return $this->json(['success' => $success]);
}
```

**Requirements:**
- Pimcore Email Document must exist and be published
- Document path must start with `/emails/`
- Context variables are injected into the document template
- Recipient must be valid email format (RFC 5322)

### SMTP / Plain HTML Email Mode

```php
#[Route('/notify/email/plain', methods: ['POST'])]
public function sendEmailPlain(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: 'customer@example.com',
        subject: 'Order #1234 shipped',
        body: '<h1>Your order is on the way</h1><p>Order {orderNumber} has been shipped.</p>',
        channel: 'email',
        context: ['orderNumber' => '1234']
    ));

    return $this->json(['success' => $success]);
}
```

**Notes:**
- This mode works with the SMTP email provider via Symfony Mailer.
- `document_path` is ignored in SMTP mode.
- Scalar context values may be substituted into `{placeholder}` tokens in subject/body.

## Push Notifications

### Single Device Push

```php
#[Route('/notify/push/single', methods: ['POST'])]
public function sendFirebasePushSingleDevice(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: 'fcm_device_registration_token_152_chars_here', // FCM token
        subject: 'Order Shipped!',
        body: 'Your order #1234 is on its way.',
        channel: 'push',
        context: [
            'order_id' => '1234',
            'screen' => 'order_detail',   // deep-link data
        ]
    ));

    return $this->json(['success' => $success]);
}
```

### Topic-Based Push Broadcast

```php
#[Route('/notify/push/topic', methods: ['POST'])]
public function sendFirebasePushTopicBroadcast(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: '/topics/flash_sale',     // FCM topic
        subject: 'Flash Sale starts NOW',
        body: '50% off everything — today only.',
        channel: 'push',
        context: ['promo_id' => 'FLASH2025']
    ));

    return $this->json(['success' => $success]);
}
```

**Requirements:**
- Single device: Valid FCM registration token (152 characters)
- Topic: Must start with `/topics/` and be valid topic name
- Context data is passed as `data` payload to the app

## LINE Messaging

### Plain Text Message

```php
#[Route('/notify/line/text', methods: ['POST'])]
public function sendLinePlainText(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: 'U1a2b3c4d5e6f7a2b3c4d5e6f7a2b3c4',   // 33-char LINE userId
        subject: '',                                    // ignored for LINE
        body: "สวัสดีครับ! คำสั่งซื้อ #1234 ถูกจัดส่งแล้ว 🚚", // Thai text supported
        channel: 'line'
    ));

    return $this->json(['success' => $success]);
}
```

### Rich Flex Message

```php
#[Route('/notify/line/flex', methods: ['POST'])]
public function sendLineFlexMessage(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: 'U1a2b3c4d5e6f7a2b3c4d5e6f7a2b3c4',
        subject: 'Order Confirmed',
        body: 'Order #1234 Confirmed',      // altText shown in chat list
        channel: 'line',
        context: [
            'messages' => [[
                'type' => 'flex',
                'altText' => 'Order #1234 Confirmed',
                'contents' => [
                    'type' => 'bubble',
                    'header' => [
                        'type' => 'box', 'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'Order Confirmed ✅',
                                'weight' => 'bold', 'color' => '#1DB446'],
                        ],
                    ],
                    'body' => [
                        'type' => 'box', 'layout' => 'vertical',
                        'contents' => [
                            ['type' => 'text', 'text' => 'Order #1234'],
                            ['type' => 'text', 'text' => 'Estimated delivery: 2–3 days',
                                'color' => '#888888', 'size' => 'sm'],
                        ],
                    ],
                    'footer' => [
                        'type' => 'box', 'layout' => 'vertical',
                        'contents' => [[
                            'type' => 'button',
                            'style' => 'primary',
                            'action' => ['type' => 'uri', 'label' => 'Track Order',
                                'uri' => 'https://yourapp.com/orders/1234'],
                        ]],
                    ],
                ],
            ]],
        ]
    ));

    return $this->json(['success' => $success]);
}
```

**Requirements:**
- Recipient must be valid LINE user ID (33 characters, starts with 'U')
- Flex messages require proper LINE Flex Message JSON structure
- Unicode text (Thai, emoji) fully supported

## WhatsApp Business API

### Template Message (Most Common)

```php
#[Route('/notify/whatsapp/template', methods: ['POST'])]
public function sendWhatsAppWithTemplateMessage(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: '+66812345678',
        subject: 'Order Shipped',
        body: '',  // ignored for templates
        channel: 'whatsapp',
        context: [
            'whatsapp_type' => 'template',
            'whatsapp_template' => 'order_shipped',    // registered template name
            'whatsapp_language' => 'en_US',
            'whatsapp_components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'Jane Doe'],           // {{1}}
                        ['type' => 'text', 'text' => '#1234'],              // {{2}}
                        ['type' => 'text', 'text' => 'https://track.example.com/1234'], // {{3}}
                    ],
                ],
            ],
        ]
    ));

    return $this->json(['success' => $success]);
}
```

### Template with Header Image

```php
#[Route('/notify/whatsapp/template-header', methods: ['POST'])]
public function sendWhatsAppWithTemplateMessageHeader(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: '+66812345678',
        subject: 'Promo Alert',
        body: '',
        channel: 'whatsapp',
        context: [
            'whatsapp_type' => 'template',
            'whatsapp_template' => 'flash_sale_promo',
            'whatsapp_language' => 'th',
            'whatsapp_components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'image',
                            'image' => ['link' => 'https://cdn.example.com/promo-banner.jpg'],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'Jane'],      // {{1}}
                        ['type' => 'text', 'text' => '50%'],       // {{2}}
                        ['type' => 'text', 'text' => '31 Dec'],    // {{3}}
                    ],
                ],
            ],
        ]
    ));

    return $this->json(['success' => $success]);
}
```

### Template with Quick Reply Buttons

```php
#[Route('/notify/whatsapp/template-buttons', methods: ['POST'])]
public function sendWhatsAppWithTemplateReplyButton(): JsonResponse
{
    $success = $this->notificationManager->send(new NotificationMessage(
        recipient: '+66812345678',
        subject: 'Confirm Appointment',
        body: '',
        channel: 'whatsapp',
        context: [
            'whatsapp_type' => 'template',
            'whatsapp_template' => 'appointment_confirmation',
            'whatsapp_language' => 'en_US',
            'whatsapp_components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => 'Dr. Smith'],    // {{1}}
                        ['type' => 'text', 'text' => '25 Dec 10:00'], // {{2}}
                    ],
                ],
                // Payload for the "Confirm" quick-reply button (index 0)
                [
                    'type' => 'button',
                    'sub_type' => 'quick_reply',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'payload', 'payload' => 'CONFIRM_APT_1234'],
                    ],
                ],
                // Payload for the "Cancel" quick-reply button (index 1)
                [
                    'type' => 'button',
                    'sub_type' => 'quick_reply',
                    'index' => '1',
                    'parameters' => [
                        ['type' => 'payload', 'payload' => 'CANCEL_APT_1234'],
                    ],
                ],
            ],
        ]
    ));

    return $this->json(['success' => $success]);
}
```

**Requirements:**
- Templates must be pre-approved by WhatsApp/Meta
- Recipient must be opted-in (24-hour window or template message)
- Media URLs must be HTTPS and publicly accessible
- Template parameters must match the registered template structure

## Multi-Channel Broadcasting

### Broadcast to Multiple Channels

```php
#[Route('/notify/broadcast', methods: ['POST'])]
public function broadcast(): JsonResponse
{
    // One message object, broadcast to multiple channels simultaneously
    $message = new NotificationMessage(
        recipient: '+66812345678',  // used as fallback; channels use own recipient
        subject: 'Flash Sale!',
        body: '50% off everything today only.',
        channel: 'sms',           // default channel (overridden in broadcast)
        context: [
            'email_recipient' => 'customer@example.com',
            'line_user_id' => 'U1234567890abcdef1234567890abcdef',
        ]
    );

    $results = $this->notificationManager->broadcast($message, ['sms', 'push', 'line', 'email']);

    return $this->json(['results' => $results]);
}
```

**Features:**
- Single message object for multiple channels
- Channel-specific recipients in context
- Individual success/failure per channel
- Comprehensive result reporting

## Advanced Examples

### Free-form WhatsApp Text (24-hour window)

```php
$notificationManager->send(new NotificationMessage(
    recipient: '+66812345678',
    subject: '',
    body: 'Hi Jane! Your support ticket #5678 has been resolved. Let us know if you need anything else.',
    channel: 'whatsapp',
    context: [
        'whatsapp_type' => 'text',
    ]
));
```

### WhatsApp Media Messages

```php
// Image with caption
$notificationManager->send(new NotificationMessage(
    recipient: '+66812345678',
    subject: '',
    body: '',
    channel: 'whatsapp',
    context: [
        'whatsapp_type' => 'image',
        'whatsapp_media_url' => 'https://cdn.example.com/receipts/receipt-1234.jpg',
        'whatsapp_caption' => 'Receipt for order #1234. Thank you for your purchase!',
    ]
));

// PDF document
$notificationManager->send(new NotificationMessage(
    recipient: '+66812345678',
    subject: '',
    body: '',
    channel: 'whatsapp',
    context: [
        'whatsapp_type' => 'document',
        'whatsapp_media_url' => 'https://cdn.example.com/invoices/invoice-1234.pdf',
        'whatsapp_caption' => 'Invoice for order #1234',
        'whatsapp_filename' => 'Invoice-1234.pdf',
    ]
));
```

## Error Handling

All methods return boolean success indicators. Check logs for detailed error information:

```php
$success = $this->notificationManager->send($message);

if (!$success) {
    // Check application logs for detailed error information
    // Common issues: invalid recipients, network timeouts, authentication failures
    return $this->json(['error' => 'Notification failed'], 500);
}
```

## Best Practices

1. **Validate Recipients**: Always validate phone numbers and emails before sending
2. **Handle Failures**: Implement retry logic for transient failures
3. **Rate Limiting**: Respect API rate limits and implement backoff strategies
4. **Logging**: Log all notification attempts for audit and debugging
5. **Testing**: Test with all channels before production deployment
6. **Security**: Never log sensitive data, use environment variables for credentials