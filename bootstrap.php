<?php
/**
 * Bootstrap file untuk autoload dan inisialisasi aplikasi
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Autoload classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load config dan utils
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/utils.php';

// Create logs directory if not exists
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
?>
