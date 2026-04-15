<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp;

/**
 * Typed value object representing a WhatsApp message payload.
 *
 * Two message types are supported:
 *
 * TYPE: text
 *   Use inside an open 24-hour user-initiated session.
 *   WhatsAppMessage::text('Hello, how can we help?')
 *
 * TYPE: template
 *   Required for all business-initiated (outbound) messages.
 *   The template must be pre-approved in Meta Business Manager.
 *
 *   WhatsAppMessage::template(
 *       name:       'order_shipped',
 *       language:   'th',
 *       components: [
 *           [
 *               'type'       => 'body',
 *               'parameters' => [
 *                   ['type' => 'text', 'text' => 'Jane Doe'],   // {{1}}
 *                   ['type' => 'text', 'text' => '#1234'],      // {{2}}
 *               ],
 *           ],
 *       ]
 *   )
 *
 * TYPE: media  (image / document / video sent as URL)
 *   WhatsAppMessage::media(
 *       mediaType: 'image',
 *       url:       'https://cdn.example.com/receipt-1234.jpg',
 *       caption:   'Your receipt for order #1234'
 *   )
 */
class WhatsAppMessage
{
    public const TYPE_TEXT     = 'text';
    public const TYPE_TEMPLATE = 'template';
    public const TYPE_IMAGE    = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_VIDEO    = 'video';

    private function __construct(
        private readonly string  $type,
        private readonly array   $payload,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────

    /**
     * Plain text message — only valid inside a 24-hour user-initiated session.
     */
    public static function text(string $body, bool $previewUrl = false): self
    {
        return new self(self::TYPE_TEXT, [
            'body'        => $body,
            'preview_url' => $previewUrl,
        ]);
    }

    /**
     * Approved template message — required for all outbound (business-initiated) messages.
     *
     * @param string $name        Template name as registered in Meta Business Manager
     * @param string $language    Language code e.g. 'en_US', 'th', 'ja'
     * @param array  $components  Array of component objects (header, body, buttons)
     *                            Each component follows the Meta API schema:
     *                            [['type'=>'body','parameters'=>[['type'=>'text','text'=>'value']]]]
     */
    public static function template(string $name, string $language, array $components = []): self
    {
        return new self(self::TYPE_TEMPLATE, [
            'name'       => $name,
            'language'   => $language,
            'components' => $components,
        ]);
    }

    /**
     * Media message — image, document, or video sent as a publicly accessible URL.
     *
     * @param string      $mediaType  'image' | 'document' | 'video'
     * @param string      $url        Publicly accessible media URL
     * @param string|null $caption    Optional caption (image/video only)
     * @param string|null $filename   Optional filename shown to recipient (document only)
     */
    public static function media(
        string  $mediaType,
        string  $url,
        ?string $caption  = null,
        ?string $filename = null
    ): self {
        $allowed = [self::TYPE_IMAGE, self::TYPE_DOCUMENT, self::TYPE_VIDEO];

        if (!in_array($mediaType, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf('WhatsAppMessage: mediaType must be one of [%s].', implode(', ', $allowed))
            );
        }

        return new self($mediaType, array_filter([
            'url'      => $url,
            'caption'  => $caption,
            'filename' => $filename,
        ]));
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getType(): string    { return $this->type; }
    public function getPayload(): array  { return $this->payload; }
    public function isTemplate(): bool   { return $this->type === self::TYPE_TEMPLATE; }
    public function isText(): bool       { return $this->type === self::TYPE_TEXT; }
    public function isMedia(): bool      { return in_array($this->type, [self::TYPE_IMAGE, self::TYPE_DOCUMENT, self::TYPE_VIDEO], true); }
}
