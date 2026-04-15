# CEP Bundle Changelog

All notable changes to the CEP Bundle will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2024-04-15

### Added
- MessageValidator class for security validation of notification messages
- SSRF protection for URLs in message context
- Template injection prevention in message content

### Changed
- Improved broadcast() method to support channel-specific recipients via context
- Enhanced SMS phone number validation regex for better E.164 compliance
- Updated test assertions to match actual exception messages

### Fixed
- Added missing skipValidation parameter to FirebaseCredentialProvider for testing
- Fixed NotificationFactoryTest constructor arguments
- Corrected FirebasePushProviderTest constructor parameter order
- Added missing TimeoutException imports in test files
- Fixed cache interface mocking issues in Firebase tests

### Improved
- Enhanced PHP documentation across all classes
- Updated API usage documentation with broadcast examples
- Improved error messages for better debugging

### Added
- Initial release of CEP Bundle (Communication and Engagement Notification)
- Multi-channel notification support: SMS, Email, Push (Firebase), LINE, WhatsApp
- Comprehensive input validation and security hardening
- PHP 8.1+ compatibility with Symfony 6.0+ and Pimcore 11.0+
- Autowiring-based service configuration using PHP attributes
- RESTful API endpoints for all notification channels
- Comprehensive test suite with PHPUnit
- Full documentation and configuration guides

### Changed
- Migrated from YAML service tags to PHP `#[TaggedIterator]` attributes for better maintainability
- Updated package name to `qburst/customer-engagement-notification-bundle` for consistency
- Streamlined composer.json dependencies for standalone bundle distribution
- Added Pimcore routing configuration for automatic controller discovery

### Fixed
- Resolved VS Code YAML validation errors by removing unsupported `!tagged_iterator` tags
- Fixed controller route registration by adding proper routing configuration
- Corrected service dependency injection issues

### Security
- Implemented credential masking in logs and error messages
- Added SSRF protection for HTTP-based providers
- Input validation for all notification parameters
- Secure handling of service account credentials

## Types of changes
- `Added` for new features
- `Changed` for changes in existing functionality
- `Deprecated` for soon-to-be removed features
- `Removed` for now removed features
- `Fixed` for any bug fixes
- `Security` in case of vulnerabilities
