<?php

namespace CustomerEngagementNotificationBundle\EventListener;

use CustomerEngagementNotificationBundle\Notification\Message\NotificationMessage;
use CustomerEngagementNotificationBundle\Notification\NotificationManager;
use Pimcore\Event\Model\DataObjectEvent;

class OrderEventListener
{
    /**
     * OrderEventListener constructor.
     *
     * @param NotificationManager $notificationManager Service for sending notifications
     */
    public function __construct(
        private readonly NotificationManager $notificationManager
    ) {}

    /**
     * Handles order shipped events by sending SMS and LINE notifications.
     *
     * @param DataObjectEvent $event The Pimcore data object event
     */
    public function onOrderShipped(DataObjectEvent $event): void
    {
        $order = $event->getObject();

        // Send SMS
        $this->notificationManager->send(new NotificationMessage(
            recipient: $order->getCustomerPhone(),
            subject:   'Order Shipped',
            body:      sprintf('Order #%s is on its way!', $order->getOrderNumber()),
            channel:   'sms'
        ));

        // Send LINE message
        $this->notificationManager->send(new NotificationMessage(
            recipient: $order->getLineUserId(),
            subject:   'Order Shipped',
            body:      sprintf('สั่งซื้อ #%s ถูกจัดส่งแล้ว!', $order->getOrderNumber()),
            channel:   'line'
        ));
    }
}
