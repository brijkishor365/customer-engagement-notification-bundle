<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace CustomerEngagementNotificationBundle;

use Pimcore\Bundle\AdminBundle\PimcoreAdminBundle;
use Pimcore\Bundle\ApplicationLoggerBundle\PimcoreApplicationLoggerBundle;
use Pimcore\Bundle\NewsletterBundle\PimcoreNewsletterBundle;
use Pimcore\Bundle\PersonalizationBundle\PimcorePersonalizationBundle;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;
use Pimcore\HttpKernel\Bundle\DependentBundleInterface;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * CustomerEngagementNotificationBundle - Communication and Engagement Platform Bundle.
 *
 * A Pimcore bundle that provides multi-channel notification capabilities including:
 * - Email notifications (via Pimcore Email Documents or plain HTML)
 * - SMS notifications (via Twilio, HTTP APIs, or generic providers)
 * - Push notifications (via Firebase Cloud Messaging)
 * - LINE messaging (via LINE Messaging API)
 * - WhatsApp messaging (via WhatsApp Business Cloud API)
 *
 * Features comprehensive input validation, security hardening, and error handling
 * to ensure reliable and secure communication across all channels.
 */
class CustomerEngagementNotificationBundle extends AbstractPimcoreBundle implements DependentBundleInterface, PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;
    use PackageVersionTrait;

    /**
     * Get the composer package name for this bundle.
     *
     * @return string The composer package name
     */
    protected function getComposerPackageName(): string
    {
        return 'qburst/customer-engagement-notification-bundle';
    }

    /**
     * Get JavaScript paths for the admin interface.
     *
     * @return array List of JavaScript file paths (empty for this bundle)
     */
    public function getJsPaths(): array
    {
        return [];
    }

    /**
     * Get CSS paths for the admin interface.
     *
     * @return array List of CSS file paths (empty for this bundle)
     */
    public function getCssPaths(): array
    {
        return [];
    }

    /**
     * Register dependent bundles required by this bundle.
     *
     * CustomerEngagementNotificationBundle has no dependent bundles — all dependencies are managed by Symfony container
     * and injected through the service configuration.
     *
     * @param BundleCollection $collection The bundle collection to register dependencies in
     */
    public static function registerDependentBundles(BundleCollection $collection): void
    {
        // CustomerEngagementNotificationBundle has no dependent bundles — all dependencies are managed by Symfony container
    }
}




