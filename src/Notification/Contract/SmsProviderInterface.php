<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Contract;

interface SmsProviderInterface
{
    /**
     * Send an SMS message.
     *
     * @param  string $to   Recipient phone number (E.164 format recommended)
     * @param  string $body The message text
     * @return bool         True on success, false on failure
     */
    public function sendSms(string $to, string $body): bool;

    /**
     * A human-readable identifier for logging/debugging.
     * e.g. 'twilio', 'http_default', 'thsms'
     */
    public function getProviderName(): string;
}
