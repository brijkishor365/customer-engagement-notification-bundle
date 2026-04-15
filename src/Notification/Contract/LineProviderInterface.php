<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Contract;

interface LineProviderInterface
{
    /**
     * @param string $to      LINE userId / groupId / roomId
     * @param string $message Text message content
     * @param array  $context Pass 'messages' key with LINE message objects for rich content
     */
    public function sendMessage(string $to, string $message, array $context = []): bool;

    public function getProviderName(): string;
}
