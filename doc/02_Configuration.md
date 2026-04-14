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
MAILER_FROM_NAME="CEP Platform"

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
```

## Performance Tuning

- Configure appropriate cache TTL for Firebase tokens (default: 55 minutes)
- Set reasonable HTTP timeouts (default: 10 seconds)
- Implement circuit breakers for external service failures
- Use connection pooling for high-volume notifications
        - city
        - true
    appbundle.cmf.mailchimp.address-state-transformer:
      class: CustomerEngagementPlatformBundle\Newsletter\ProviderHandler\Mailchimp\DataTransformer\Address
      arguments:
        - state
        - true
    appbundle.cmf.mailchimp.address-country-transformer:
      class: CustomerEngagementPlatformBundle\Newsletter\ProviderHandler\Mailchimp\DataTransformer\Address
      arguments:
        - country
        - true

    appbundle.cmf.mailchimp.handler.list1:
        class: CustomerEngagementPlatformBundle\Newsletter\ProviderHandler\Mailchimp
        autowire: true
        arguments:
            # Shortcut of the handler/list for internal use
            - list1
            
            # List ID within Mailchimp
            - ls938393f
            
            # Mapping of Pimcore status field => Mailchimp status
            - manuallySubscribed: subscribed
              singleOptIn: subscribed
              doubleOptIn: subscribed
              unsubscribed: unsubscribed
              pending: pending
              
            # Reverse mapping of Mailchimp status => Pimcore status field
            - subscribed: doubleOptIn
              unsubscribed: unsubscribed
              pending: pending

            # Mapping of Pimcore data object attributes => Mailchimp merge fields
            - firstname: FNAME
              lastname: LNAME
              # See the data transformer below why we can map muliple fields to 
              # the same merge field.
              street: ADDRESS
              zip: ADDRESS
              city: ADDRESS
              countryCode: ADDRESS
              birthDate: BIRTHDATE

            # Special data transformer for the birthDate field. 
            # This ensures that the correct data format will be used.
            - birthDate: '@appbundle.cmf.mailchimp.birthdate-transformer'
              # Special data transformer for the multi-value field ADDRESS. 
              street: '@appbundle.cmf.mailchimp.address-addr1-transformer'
              zip: '@appbundle.cmf.mailchimp.address-zip-transformer'
              city: '@appbundle.cmf.mailchimp.address-city-transformer'
              countryCode: '@appbundle.cmf.mailchimp.address-country-transformer'

        tags: [cen.newsletter_provider_handler]
        
    
```        


## Configuration Tree   

e.g. in `config.yml`: 

```yaml
pimcore_customer_management_framework:
   
    # Configuration of general settings
    general:
        customerPimcoreClass: Customer
        mailBlackListFile:    /home/customerdataframework/www/var/config/cep/mail-blacklist.txt

    
    # Newsletter/MailChimp sync related settings
    newsletter:
        newsletterSyncEnabled: true
        
        # Immediate execution of customer data export on customer save. 
        newsletterQueueImmediateAsyncExecutionEnabled: true

        mailchimp:
          apiKey: d1a40ajzf41d5154455a9455cc7b71b9-us14
          cliUpdatesPimcoreUserName: mailchimp-cli

    # Configuration of EncryptionService
    encryption:

        # echo \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
        # keep it secret
        secret:               'def00000a2fe8752646f7d244c950f0399180a7ab1fb38e43edaf05e0ff40cfa2bbedebf726268d0fc73d5f74d6992a886f83eb294535eb0683bb15db9c4929bbd138aee'

    # Configuration of customer save manager
    customer_save_manager:

        # If enabled the automatic object naming scheme will be applied on each customer save. See: customer_provider -> namingScheme option
        enableAutomaticObjectNamingScheme: false

    # Configuration of customer provider
    customer_provider:

        # parent folder for active customers
        parentPath:           /customers

        # parent folder for customers which are unpublished and inactive
        archiveDir:           /customers/_archive

        # If a naming scheme is configured customer objects will be automatically renamend and moved to the configured folder structure as soon as the naming scheme gets applied.
        namingScheme:         '{countryCode}/{zip}/{firstname}-{lastname}' 
        
        # Parent folder for customers which are created via the "new customer" button in the customer list view
        newCustomersTempDir:         /customers/_temp

    # Configuration of customer save manager
    customer_save_validator:

        # If enabled an exception will be thrown when saving a customer object if duplicate customers exist.
        checkForDuplicates:   false
        requiredFields:

            # Provide valid field combinations. The customer object then is valid as soon as at least one of these field combinations is filled up.
            - [email]
            - [firstname, lastname]

    # Configuration of segment manager
    segment_manager:
        segmentFolder:

            # parent folder of manual segments + segment groups
            manual:               /segments/manual

            # parent folder of calculated segments + segment groups
            calculated:           /segments/calculated

    activity_url_tracker:
          enabled: true
          # used for automatic link generation of LinkActivityDefinition data objects
          linkCepcPlaceholder: '*|ID_ENCODED|*'
     
    # Configuration for segment assignment
    segment_assignment_classes:
          types:
              document:
                  page: true
                  email: true
              asset:
                  image: true
              object:
                  object:
                    Product: true
                    ShopCategory: true
                  folder: true

    # Configuration of customer list view
    customer_list:
    
        # configure exporters available in customer list
        exporters:
            csv:
                name:                 CSV # Required
                icon:                 'fa fa-file-text-o' # Required
                exporter:             '\CustomerEngagementPlatformBundle\CustomerList\Exporter\Csv' # Required
                exportSegmentsAsColumns: true
                properties:           
                   - id
                   - active
                   - gender
                   - email
                   - phone
                   - firstname
                   - lastname
                   - street
                   - zip
                   - city
                   - countryCode
                   - idEncoded
            
            xlsx:
                name:                 XLSX # Required
                icon:                 'fa fa-file-excel-o' # Required
                exporter:             '\CustomerEngagementPlatformBundle\CustomerList\Exporter\Xlsx' # Required
                exportSegmentsAsColumns: true
                properties:           
                   - id
                   - active
                   - gender
                   - email
                   - phone
                   - firstname
                   - lastname
                   - street
                   - zip
                   - city
                   - countryCode
                   - idEncoded
              
        # Configuration of filters in the customer list view. The properties configured here will 
        # be handled if passed as ?filter[] query parameter.
        filter_properties:
            # Filter fields which must match exactly.
            equals:
                # ?filter[id]=8 will result in a SQL condition of "WHERE id=8"
                id:                  id
                active:              active
                
            # Searched fields in customer view search filters
            # (enhanced search syntax (AND/OR/!/*...) could be used in these fields).
            # Search will be applied to all fields in the list, e.g. 
            # ?filter[name]=val will result in a SQL condition of "WHERE (firstname LIKE "%val%" OR lastname LIKE "%val")
            # See https://github.com/pimcore/search-query-parser for detailed search syntax. 
            search:
                # email search filter
                email:
                  - email
                  
                # name search filter
                firstname:
                  - firstname
                      
                lastname: 
                  - lastname
                  
                # main search filter
                search:
                  - id
                  - idEncoded
                  - firstname
                  - lastname
                  - email
                  - zip
                  - city

    # Configuration of customer duplicates services
    customer_duplicates_services:
    
        # Field or field combinations for hard duplicate check
        duplicateCheckFields:
            - [email]
            - [firstname, lastname]
        
        # Performance improvement: add duplicate check fields which are trimmed (trim() called on the field value) by a 
        # customer save handler. No trim operation will be needed in the resulting query.
        duplicateCheckTrimmedFields:
            - email
            - firstname
            - lastname
        
        duplicates_view:
            enabled: true # the feature will be visible in the backend only if it is enabled
            # Visible fields in the customer duplicates view. 
            # Each single group/array is one separate column in the view table.
            listFields:
              - [id]
              - [email]
              - [firstname, lastname]
              - [street]
              - [zip, city]
              
        # Index used for a global search of customer duplicates. 
        # Matching field combinations can be configured here.
        # See "Customer Duplicates Service" docs chapter for more details.
        duplicates_index:
            enableDuplicatesIndex: false
            duplicateCheckFields:
                - firstname:
                      soundex: true
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText

                  zip:
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\Zip

                  street:
                      soundex: true
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText

                  birthDate:
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\BirthDate::class
                
                - lastname:
                      soundex: true
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText

                  firstname:
                      soundex: true
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText

                  zip:
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\Zip

                  city:
                      soundex: true
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText

                  street:
                      soundex: true
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText
                
                
                - email:
                      metaphone: true
                      similarity: \CustomerEngagementPlatformBundle\DataSimilarityMatcher\SimilarText
                      similarityThreshold: 90
    
            dataTransformers:
              street: \CustomerEngagementPlatformBundle\DataTransformer\DuplicateIndex\Street
              firstname: \CustomerEngagementPlatformBundle\DataTransformer\DuplicateIndex\Simplify
              city: \CustomerEngagementPlatformBundle\DataTransformer\DuplicateIndex\Simplify
              lastname: \CustomerEngagementPlatformBundle\DataTransformer\DuplicateIndex\Simplify
              birthDate: \CustomerEngagementPlatformBundle\DataTransformer\DuplicateIndex\Date
```
