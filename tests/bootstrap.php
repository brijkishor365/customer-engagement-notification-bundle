<?php
/**
 * PHPUnit Bootstrap for CustomerEngagementNotificationBundle Tests
 *
 * This bootstrap file is loaded by PHPUnit before running tests.
 */

// Try to find composer autoloader in multiple locations
$autoloadPaths = [
    // When running from bundle in src/ directory (development)
    dirname(__DIR__, 2) . '/vendor/autoload.php',
    // When bundle is in project's vendor directory (normal installation)
    dirname(__DIR__, 4) . '/vendor/autoload.php',
    // When running from vendor directory
    dirname(__DIR__) . '/vendor/autoload.php',
    // Fallback for other setups
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    throw new \RuntimeException('Could not find composer autoloader. Please run "composer install" in the project root.');
}

// Load environment variables if available
$envPaths = [
    dirname(__DIR__, 4) . '/.env',
    dirname(__DIR__, 2) . '/.env',
];

foreach ($envPaths as $envPath) {
    if (file_exists($envPath)) {
        $dotenv = new \Symfony\Component\Dotenv\Dotenv();
        $dotenv->loadEnv($envPath);
        break;
    }
}
