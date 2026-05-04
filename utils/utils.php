<?php
/**
 * File: utils/utils.php
 */

function getAccurateTimestamp() {
    return date('d/m/Y H:i:s');
}

function generateAccurateSignature($timestamp, $secret) {
    $hash = hash_hmac('sha256', $timestamp, $secret, true);
    return base64_encode($hash);
}

function logError($message, $file, $line) {
    $logDir = __DIR__ . '/../log';
    
    // Cek apakah folder log sudah ada, jika belum, buat otomatis
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/error.log';
    $date = date('Y-m-d H:i:s');
    $msg = "[$date] Error in $file on line $line: $message" . PHP_EOL;
    file_put_contents($logFile, $msg, FILE_APPEND);
}
?>