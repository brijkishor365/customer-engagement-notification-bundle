# CustomerEngagementNotificationBundle Tests

This directory contains the test suite for the CustomerEngagementNotificationBundle notification system.

## Directory Structure

```
tests/
├── Unit/
│   └── Notification/
│       ├── Channel/                 # Channel support validation tests
│       │   ├── EmailChannelTest.php
│       │   ├── SmsChannelTest.php
│       │   ├── PushChannelTest.php
│       │   ├── LineChannelTest.php
│       │   └── WhatsAppChannelTest.php
│       ├── Provider/                # Provider implementation tests
│       │   ├── Push/
│       │   │   ├── FirebaseCredentialProviderTest.php
│       │   │   └── FirebasePushProviderTest.php
│       │   ├── Sms/
│       │   │   ├── HttpSmsProviderTest.php
│       │   │   └── TwilioSmsProviderTest.php
│       │   ├── Email/
│       │   │   └── PimcoreEmailProviderTest.php
│       │   ├── Line/
│       │   │   └── LineMessengerProviderTest.php
│       │   └── WhatsApp/
│       │       └── WhatsAppCloudProviderTest.php
│       ├── Message/
│       │   └── NotificationMessageTest.php      # Input validation tests
│       └── Resolver/
│           └── BodyTemplateResolverTest.php     # Template resolution tests
└── bootstrap.php                    # PHPUnit bootstrap file
```

## Running Tests

### All Tests
```bash
cd src/CustomerEngagementNotificationBundle
./../../vendor/bin/phpunit tests/
```

### Specific Test File
```bash
./../../vendor/bin/phpunit tests/Unit/Notification/Message/NotificationMessageTest.php
```

### Specific Test Method
```bash
./../../vendor/bin/phpunit tests/Unit/Notification/Message/NotificationMessageTest.php::NotificationMessageTest::it_accepts_valid_messages
```

### With Coverage Report
```bash
./../../vendor/bin/phpunit --coverage-html=coverage tests/
```

## Test Categories

### 1. **Message Validation Tests** (MessageTest.php)
Tests for:
- Required field validation
- Size limit enforcement
- Channel name validation
- Context depth and value size validation
- DoS prevention

### 2. **Channel Support Tests** (Channel/*Test.php)
Tests for:
- Phone number validation (SMS Channel)
- Email address validation (Email Channel)
- Firebase device token validation (Push Channel)
- LINE user ID validation (LINE Channel)
- WhatsApp phone number validation
- Edge cases and invalid formats

### 3. **Provider Tests** (Provider/*/*Test.php)
Tests for:
- HTTP request handling
- Error logging
- Timeout configuration
- Response validation
- Credential management
- Security (no credential leaks in logs)

### 4. **Template Resolver Tests** (Resolver/BodyTemplateResolverTest.php)
Tests for:
- Placeholder replacement
- Nested array resolution
- Escaping and injection prevention
- Edge cases (empty values, null, non-scalar keys)

## Test Coverage Goals

| Component | Priority | Status |
|-----------|----------|--------|
| NotificationMessage validation | HIGH | ✅ Example provided |
| Channel validators | HIGH | 🟡 Examples provided |
| Credential providers | HIGH | 🟡 To be implemented |
| Template resolver | MEDIUM | 🟡 To be implemented |
| Provider implementations | HIGH | 🟡 To be implemented |
| Integration tests | MEDIUM | 🟡 To be implemented |

## Example: Writing a New Test

```php
<?php

namespace Qburst\CustomerEngagementNotificationBundle\Tests\Unit\Notification\Channel;

use Qburst\CustomerEngagementNotificationBundle\Notification\Channel\SmsChannel;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\SmsProviderInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use PHPUnit\Framework\TestCase;

class SmsChannelTest extends TestCase
{
    private SmsChannel $channel;

    protected function setUp(): void
    {
        $mockProvider = $this->createMock(SmsProviderInterface::class);
        $this->channel = new SmsChannel($mockProvider);
    }

    /**
     * @test
     */
    public function it_supports_valid_phone_numbers(): void
    {
        $message = new NotificationMessage('+14155552671', 'Subject', 'Body', 'sms');

        self::assertTrue($this->channel->supports($message));
    }

    /**
     * @test
     */
    public function it_rejects_invalid_phone_numbers(): void
    {
        $message = new NotificationMessage('1234567', 'Subject', 'Body', 'sms');

        self::assertFalse($this->channel->supports($message));
    }
}
```

## Best Practices

1. **Use data providers** for testing multiple similar cases
2. **Mock external dependencies** (HTTP clients, providers, etc.)
3. **Test both success and failure paths**
4. **Test security constraints** (input validation, credential handling)
5. **Use descriptive test names** following the pattern: `it_<does_what>_<when_condition>`
6. **Keep tests focused** - one assertion per test when possible
7. **Test edge cases** - empty values, max lengths, special characters

## Security Testing Priorities

Tests should specifically cover these security concerns:

1. ✅ **Input Validation** - Size limits, allowed characters, format compliance
2. 🟡 **Credential Handling** - No secrets in logs, proper error messages
3. 🟡 **Template Injection** - HTML escaping, Twig injection prevention
4. 🟡 **SSRF Prevention** - URL validation, private IP blocking
5. 🟡 **Error Handling** - Proper exception types, no information leaks

## Contributing Tests

When adding a new test file:

1. Use the appropriate directory structure
2. Name the test class `{ClassName}Test`
3. Place it in the same namespace hierarchy as the class being tested
4. Use `@test` annotations instead of `test` prefix
5. Run `phpunit` to ensure your tests pass
6. Aim for >80% code coverage for security-critical code

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Best Practices](https://phpunit.de/best-practices.html)
