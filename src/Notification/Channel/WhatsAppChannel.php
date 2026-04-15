<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Channel;

use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Contract\WhatsAppProviderInterface;
use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use Qburst\CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppMessage;

/**
 * WhatsApp notification channel.
 *
 * Message type is resolved from NotificationMessage context:
 *
 *   context['whatsapp_type'] = 'template'  (default for outbound)
 *     → requires: context['whatsapp_template']  (template name)
 *                 context['whatsapp_language']  (language code, default 'en_US')
 *                 context['whatsapp_components'] (template component parameters)
 *
 *   context['whatsapp_type'] = 'text'
 *     → uses NotificationMessage::getBody() directly
 *       (only valid inside a 24-hour user-initiated session)
 *
 *   context['whatsapp_type'] = 'image' | 'document' | 'video'
 *     → requires: context['whatsapp_media_url']
 *       optional: context['whatsapp_caption'], context['whatsapp_filename']
 *
 * If no 'whatsapp_type' is set in context, defaults to 'template'.
 */
class WhatsAppChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly WhatsAppProviderInterface $provider
    ) {}

    /**
     * Send a WhatsApp message using the configured provider.
     *
     * @param NotificationMessage $message The notification message to send
     * @return bool True when the message dispatch succeeds
     */
    public function send(NotificationMessage $message): bool
    {
        $waMessage = $this->resolveMessage($message);

        return $this->provider->send($message->getRecipient(), $waMessage);
    }

    /**
     * Returns the channel identifier.
     *
     * @return string The channel name ('whatsapp')
     */
    public function getName(): string { return 'whatsapp'; }

    /**
     * Check whether the WhatsApp message is supported.
     *
     * @param NotificationMessage $message The message to validate
     * @return bool True if the recipient and payload are valid for WhatsApp
     */
    public function supports(NotificationMessage $message): bool
    {
        // E.164 format: optional leading +, then 7–15 digits
        return (bool) preg_match('/^\+?[1-9]\d{6,14}$/', $message->getRecipient());
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Resolve a NotificationMessage into a typed WhatsAppMessage object.
     *
     * @param NotificationMessage $message The incoming notification message
     * @return WhatsAppMessage The typed WhatsApp message payload
     */
    private function resolveMessage(NotificationMessage $message): WhatsAppMessage
    {
        $type = $message->getContextValue('whatsapp_type', 'template');

        return match ($type) {
            'text'     => WhatsAppMessage::text(
                body:       $message->getBody(),
                previewUrl: (bool) $message->getContextValue('whatsapp_preview_url', false)
            ),

            'template' => WhatsAppMessage::template(
                name:       $message->getContextValue('whatsapp_template')
                ?? throw new \InvalidArgumentException(
                    'WhatsAppChannel: context[whatsapp_template] is required for template messages.'
                ),
                language:   $message->getContextValue('whatsapp_language', 'en_US'),
                components: $message->getContextValue('whatsapp_components', [])
            ),

            'image', 'document', 'video' => WhatsAppMessage::media(
                mediaType: $type,
                url:       $message->getContextValue('whatsapp_media_url')
                ?? throw new \InvalidArgumentException(
                    'WhatsAppChannel: context[whatsapp_media_url] is required for media messages.'
                ),
                caption:   $message->getContextValue('whatsapp_caption'),
                filename:  $message->getContextValue('whatsapp_filename')
            ),

            default => throw new \InvalidArgumentException(
                sprintf('WhatsAppChannel: unsupported whatsapp_type "%s".', $type)
            ),
        };
    }
}
