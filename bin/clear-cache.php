<?php
/**
 * Argora Foundry
 *
 * A modular PHP boilerplate for building SaaS applications, admin panels, and control systems.
 *
 * @package    App
 * @author     Taras Kondratyuk <help@argora.org>
 * @copyright  Copyright (c) 2025 Argora
 * @license    MIT License
 * @link       https://github.com/getargora/foundry
 */

$cacheDir = realpath(__DIR__ . '/../cache');

if (!$cacheDir || !is_dir($cacheDir)) {
    echo "Cache directory not found.\n";
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($files as $fileinfo) {
    // Check if the parent directory name is exactly two letters/numbers long
    if (preg_match('/^[a-zA-Z0-9]{2}$/', $fileinfo->getFilename()) || preg_match('/^[a-zA-Z0-9]{2}$/', basename(dirname($fileinfo->getPathname())))) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
}

// After deleting files and subdirectories, delete the 2 letter/number directories themselves
$dirs = new DirectoryIterator($cacheDir);
foreach ($dirs as $dir) {
    if ($dir->isDir() && !$dir->isDot() && preg_match('/^[a-zA-Z0-9]{2}$/', $dir->getFilename())) {
        rmdir($dir->getRealPath());
    }
}

// Clear Slim route cache if it exists
$routeCacheFile = $cacheDir . '/routes.php';
if (file_exists($routeCacheFile)) {
    unlink($routeCacheFile);
}

// Try to restart PHP-FPM 8.3
echo "Restarting PHP-FPM (php8.3-fpm)...\n";
exec("sudo systemctl restart php8.3-fpm 2>&1", $restartOutput, $status);

if ($status === 0) {
    echo "PHP-FPM restarted successfully.\n";
} else {
    echo "Could not restart PHP-FPM automatically.\n";
    echo "Please run manually: sudo systemctl restart php8.3-fpm\n";
}