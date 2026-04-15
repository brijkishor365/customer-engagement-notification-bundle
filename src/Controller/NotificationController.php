<?php

namespace Qburst\CustomerEngagementNotificationBundle\Controller;

use Qburst\CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use Qburst\CustomerEngagementNotificationBundle\Notification\NotificationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller exposing notification demo endpoints.
 *
 * Each action demonstrates a specific channel or delivery pattern.
 */
class NotificationController extends AbstractController
{
    /**
     * NotificationController constructor.
     *
     * @param NotificationManager $notificationManager Notification service orchestrator
     */
    public function __construct(
        private readonly NotificationManager $notificationManager
    )
    {
    }

    #[Route('/api/notify/sms', name: 'notify_sms', methods: ['GET'])]
    public function sendSms(): JsonResponse
    {
        $message = new NotificationMessage(
            recipient: '+66812345678',
            subject: 'Order Update',
            body: 'Your order #1234 has been shipped!',
            channel: 'sms'
        );

        $success = $this->notificationManager->send($message);

        return $this->json(['success' => $success]);
    }

    #[Route('/api/notify/email', name: 'notify_email', methods: ['GET'])]
    public function sendEmail(): JsonResponse // Pimcore Document (Mode A)
    {
        $success = $this->notificationManager->send(new NotificationMessage(
            recipient: 'customer@example.com',
            subject: 'Your order has shipped',     // fallback if doc has no subject
            body: 'Hello! your order has been shipped. Please check your email...',
            channel: 'email',
            context: [
                'document_path' => '/emails/order-shipped',  // triggers Mode A
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

    #[Route('/api/notify/email/template', name: 'notify_email_template', methods: ['POST'])]
    public function sendEmailWithTemplate(): JsonResponse //
    {
        $success = $this->notificationManager->send(new NotificationMessage(
            recipient: 'customer@example.com',
            subject: 'Your order has shipped',     // fallback if doc has no subject
            body: '',
            channel: 'email',
            context: [
                'document_path' => '/emails/order-shipped',  // triggers Mode A
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

    #[Route('/api/notify/broadcast', name: 'notify_broadcast', methods: ['POST'])]
    public function broadcast(): JsonResponse
    {
        // One message object, broadcast to SMS + Email simultaneously
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

    #[Route('/api/notify/push/device', name: 'notify_push_device', methods: ['POST'])]
    public function sendFirebasePushSingleDevice(): JsonResponse
    {
        $success = $this->notificationManager->send(new NotificationMessage(
            recipient: 'fcm_device_registration_token_152_chars_here',
            subject: 'Order Shipped!',
            body: 'Your order #1234 is on its way.',
            channel: 'push',
            context: [
                'order_id' => '1234',
                'screen' => 'order_detail',   // deep-link data delivered to the app
            ]
        ));

        return $this->json(['success' => $success]);
    }

    #[Route('/api/notify/push/topic', name: 'notify_push_topic', methods: ['POST'])]
    public function sendFirebasePushTopicBroadcast(): JsonResponse
    {
        $success = $this->notificationManager->send(new NotificationMessage(
            recipient: '/topics/flash_sale',     // all devices subscribed to this topic
            subject: 'Flash Sale starts NOW',
            body: '50% off everything — today only.',
            channel: 'push',
            context: ['promo_id' => 'FLASH2025']
        ));

        return $this->json(['success' => $success]);
    }

    #[Route('/api/notify/line/text', name: 'notify_line_text', methods: ['POST'])]
    public function sendLinePlainText(): JsonResponse
    {
        $success = $this->notificationManager->send(new NotificationMessage(
            recipient: 'U1a2b3c4d5e6f7a2b3c4d5e6f7a2b3c4',   // 33-char LINE userId
            subject: '',
            body: "สวัสดีครับ! คำสั่งซื้อ #1234 ถูกจัดส่งแล้ว 🚚",
            channel: 'line'
        ));

        return $this->json(['success' => $success]);
    }

    #[Route('/api/notify/line/flex', name: 'notify_line_flex', methods: ['POST'])]
    public function sendLineFlexMessage(): JsonResponse // Flex Message (rich card)
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

    #[Route('/api/notify/whatsapp/template', name: 'notify_whatsapp_template', methods: ['POST'])]
    public function sendWhatsAppWithTemplateMessage(): JsonResponse
    {
        // Template message (outbound — most common)
        // Send an approved "order_shipped" template
        // Template preview (registered in Meta):
        // Body: "Hello {{1}}, your order {{2}} has been shipped! Track it here: {{3}}"

        $success = $this->notificationManager->send(new NotificationMessage(
            recipient: '+66812345678',
            subject: 'Order Shipped',
            body: '',
            channel: 'whatsapp',
            context: [
                'whatsapp_type' => 'template',
                'whatsapp_template' => 'order_shipped',
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

    #[Route('/api/notify/whatsapp/template/header', name: 'notify_whatsapp_template_header', methods: ['POST'])]
    public function sendWhatsAppWithTemplateMessageHeader(): JsonResponse
    {
        // Template: header (image) + body text with variables
        // Send an approved "order_shipped" template
        // Template preview (registered in Meta):
        // Body: "Hello {{1}}, your order {{2}} has been shipped! Track it here: {{3}}"

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
                            ['type' => 'text', 'text' => 'Jane'],      // {{1}} customer name
                            ['type' => 'text', 'text' => '50%'],       // {{2}} discount
                            ['type' => 'text', 'text' => '31 Dec'],    // {{3}} expiry date
                        ],
                    ],
                ],
            ]
        ));

        return $this->json(['success' => $success]);
    }

    #[Route('/api/notify/whatsapp/template/reply-button', name: 'notify_whatsapp_template_reply_button', methods: ['POST'])]
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

    // Free-form text (inside 24-hour session window)
//    $notificationManager->send(new NotificationMessage(
//    recipient: '+66812345678',
//    subject:   '',
//    body:      'Hi Jane! Your support ticket #5678 has been resolved. Let us know if you need anything else.',
//    channel:   'whatsapp',
//    context:   [
//    'whatsapp_type' => 'text',
//    ]
//    ));

    //Media — image with caption
    //$notificationManager->send(new NotificationMessage(
    //recipient: '+66812345678',
    //subject:   '',
    //body:      '',
    //channel:   'whatsapp',
    //context:   [
    //'whatsapp_type'      => 'image',
    //'whatsapp_media_url' => 'https://cdn.example.com/receipts/receipt-1234.jpg',
    //'whatsapp_caption'   => 'Receipt for order #1234. Thank you for your purchase!',
    //]
    //));

    //Media — PDF document
    //
    //$notificationManager->send(new NotificationMessage(
    //recipient: '+66812345678',
    //subject:   '',
    //body:      '',
    //channel:   'whatsapp',
    //context:   [
    //'whatsapp_type'      => 'document',
    //'whatsapp_media_url' => 'https://cdn.example.com/invoices/invoice-1234.pdf',
    //'whatsapp_caption'   => 'Invoice for order #1234',
    //'whatsapp_filename'  => 'Invoice-1234.pdf',
    //]
    //));

    // Broadcast all five channels at once
    //$results = $notificationManager->broadcast(
    //message: new NotificationMessage(
    //recipient: $customer->getPhone(),   // used by SMS + WhatsApp
    //subject:   'Order #1234 Shipped',
    //body:      "Your order #1234 has shipped!",
    //channel:   'sms',
    //context:   [
    //    // Email (Pimcore document)
    //'document_path' => '/emails/order-shipped',
    //'customerName'  => $customer->getName(),
    //'orderNumber'   => '1234',
    //'trackingUrl'   => 'https://track.example.com/1234',
    //
    //    // WhatsApp template
    //'whatsapp_type'       => 'template',
    //'whatsapp_template'   => 'order_shipped',
    //'whatsapp_language'   => 'en_US',
    //'whatsapp_components' => [
    //[
    //'type'       => 'body',
    //'parameters' => [
    //['type' => 'text', 'text' => $customer->getName()],
    //['type' => 'text', 'text' => '#1234'],
    //],
    //],
    //],
    //]
    //),
    //channels: ['sms', 'push', 'line', 'email', 'whatsapp']
    //);

}
