<?php

declare(strict_types=1);

/**
 * Customer Engagement Platform Bundle
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace CustomerEngagementNotificationBundle;

use Exception;
use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Logger;

class Installer extends SettingsStoreAwareInstaller
{
    public function needsReloadAfterInstall(): bool
    {
        return true;
    }

}
