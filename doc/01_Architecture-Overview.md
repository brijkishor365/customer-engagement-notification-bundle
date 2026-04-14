# CEP Bundle - Architecture Overview

## Overview

The **CEP Bundle** (Communication and Engagement Platform) is a comprehensive multi-channel notification system for Pimcore applications. It provides a unified API for sending notifications across various communication channels including SMS, Email, Push Notifications, LINE messaging, and WhatsApp.

## Key Features

- **Multi-Channel Support**: SMS, Email, Push (Firebase), LINE, WhatsApp
- **Security Hardened**: Input validation, SSRF protection, credential masking
- **Pimcore Integration**: Native support for Pimcore Email Documents
- **Template System**: Flexible message templating with variable substitution
- **Broadcasting**: Send to multiple channels simultaneously
- **Comprehensive Logging**: Full audit trail of notification activities
- **Error Handling**: Robust error handling with detailed logging

## Architecture Components

### Core Classes

```
NotificationManager (Main Entry Point)
├── NotificationFactory (Channel Creation)
├── NotificationMessage (Immutable Message Object)
├── Channel Interfaces (Validation & Routing)
└── Provider Implementations (External API Integration)
```

### Notification Flow

1. **Message Creation**: Create `NotificationMessage` with recipient, content, and channel
2. **Channel Selection**: `NotificationFactory` selects appropriate channel implementation
3. **Validation**: Channel validates recipient format and message compatibility
4. **Provider Execution**: Channel delegates to provider for external API calls
5. **Response**: Success/failure returned with comprehensive logging

### Supported Channels

| Channel | Provider | Validation | Features |
|---------|----------|------------|----------|
| `sms` | Twilio, HTTP APIs | E.164 phone format | Text messaging |
| `email` | Pimcore Documents, SMTP | RFC 5322 email format | HTML templates, attachments |
| `push` | Firebase Cloud Messaging | FCM tokens/topics | Device notifications, topics |
| `line` | LINE Messaging API | LINE user IDs | Text, rich cards, buttons |
| `whatsapp` | WhatsApp Business API | E.164 phone format | Templates, media, interactive |

## Security Features

- **Input Validation**: Strict validation of all message parameters
- **SSRF Protection**: URL validation for media attachments
- **Credential Security**: No sensitive data in logs, secure token handling
- **Rate Limiting**: Built-in delays and timeout controls
- **Content Sanitization**: HTML escaping and template injection prevention

## Integration Points

- **Symfony Services**: Full dependency injection support
- **Pimcore Documents**: Email document integration
- **Logging**: PSR-3 compatible logging interface
- **Caching**: PSR-6 cache for token management
- **HTTP Client**: Symfony HTTP client with timeout controls

## Configuration

The bundle is configured through standard Symfony service definitions and environment variables for API credentials. See [Configuration Guide](./02_Configuration.md) for details.

## Usage Examples

See [API Usage Guide](./03_API-Usage.md) for comprehensive examples of all notification types.
