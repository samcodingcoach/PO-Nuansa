<?php
/**
 * Bootstrap file untuk autoload dan inisialisasi aplikasi
 * Versi Kompatibel: PHP 5.6
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Matikan display_errors untuk production-ready feel

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
    // Pada PHP 5.6, penanganan mkdir rekursif terkadang butuh pengecekan manual
    // Memastikan folder logs dibuat dengan permission yang tepat
    mkdir($logDir, 0755, true);
}
?>