<?php

namespace Qburst\CustomerEngagementNotificationBundle\Notification\Contract;

interface PushProviderInterface
{
    /**
     * @param string $deviceToken  FCM registration token or topic ('/topics/news')
     * @param string $title        Notification title
     * @param string $body         Notification body text
     * @param array  $data         Optional key/value data payload delivered to the app
     */
    public function sendPush(
        string $deviceToken,
        string $title,
        string $body,
        array  $data = []
    ): bool;

    public function getProviderName(): string;
}
