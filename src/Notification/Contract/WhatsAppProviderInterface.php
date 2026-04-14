<?php

namespace CustomerEngagementNotificationBundle\Notification\Contract;

use CustomerEngagementNotificationBundle\Notification\Provider\WhatsApp\WhatsAppMessage;

interface WhatsAppProviderInterface
{
    /**
     * Send a WhatsApp message (text or template).
     *
     * @param  string           $to      Recipient phone in E.164 format (e.g. +66812345678)
     * @param  WhatsAppMessage  $message Typed message object (text or template)
     * @return bool
     */
    public function send(string $to, WhatsAppMessage $message): bool;

    public function getProviderName(): string;
}
