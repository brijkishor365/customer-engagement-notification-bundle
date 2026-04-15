<?php

namespace Qburst\CustomerEngagementNotificationBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Symfony Dependency Injection Extension for CustomerEngagementNotificationBundle.
 *
 * Loads bundle configuration and registers services with auto-tagging for notification channels.
 */
class CustomerEngagementNotificationExtension extends Extension
{
    /**
     * Loads the bundle configuration and services.
     *
     * @param array $configs Array of configuration arrays
     * @param ContainerBuilder $container The service container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Auto-tag all classes implementing NotificationChannelInterface
        $container->registerForAutoconfiguration(
            \Qburst\CustomerEngagementNotificationBundle\Notification\Contract\NotificationChannelInterface::class
        )->addTag('cen.notification.channel');

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
