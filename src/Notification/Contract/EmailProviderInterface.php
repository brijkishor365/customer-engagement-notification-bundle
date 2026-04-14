<?php

namespace CustomerEngagementNotificationBundle\Notification\Contract;

interface EmailProviderInterface
{
    /**
     * @param string      $to             Recipient email address
     * @param string      $subject        Subject (fallback if document has none)
     * @param string      $body           Raw HTML body (used without documentPath)
     * @param array       $params         Twig params injected into the Pimcore document
     * @param string|null $documentPath   Pimcore doc path e.g. '/emails/order-shipped'
     */
    public function sendEmail(
        string  $to,
        string  $subject,
        string  $body,
        array   $params       = [],
        ?string $documentPath = null
    ): bool;

    public function getProviderName(): string;
}
