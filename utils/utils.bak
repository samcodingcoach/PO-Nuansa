<?php
/**
 * Utility functions untuk aplikasi Accurate API
 * Versi Kompatibel: PHP 5.6
 */

/**
 * Fungsi untuk mengambil nilai nested dari array
 * PHP 5.6: Menggunakan helper function internal untuk rekursi
 */
function getNested($array, $path) {
    $parts = explode(">", $path);
    
    // Helper function karena PHP 5.6 memiliki batasan pada closure recursion
    return _getNestedRecursive($array, $parts);
}

/**
 * Internal recursive helper untuk getNested (Kompatibilitas PHP 5.6)
 */
function _getNestedRecursive($data, $keys) {
    if (empty($keys)) {
        if (is_bool($data)) {
            return $data ? 'True' : 'False';
        } elseif (is_array($data)) {
            // Cek jika array numerik (PHP 5.6 style)
            if (array_keys($data) === range(0, count($data) - 1)) {
                return isset($data[0]) ? json_encode($data[0]) : "-";
            } else {
                return json_encode($data);
            }
        } else {
            return $data;
        }
    }
    
    $key = array_shift($keys);
    if (is_array($data)) {
        // Cek jika array numerik
        if (array_keys($data) === range(0, count($data) - 1)) {
            if (count($data) > 0) {
                return _getNestedRecursive($data[0], array_merge(array($key), $keys));
            } else {
                return "-";
            }
        } else {
            return isset($data[$key]) ? _getNestedRecursive($data[$key], $keys) : "-";
        }
    }
    return "-";
}

/**
 * Fungsi untuk memformat response JSON
 */
function jsonResponse($data = null, $success = true, $message = '') {
    $response = array(
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    );
    
    header('Content-Type: application/json; charset=UTF-8');
    // PHP 5.6 sudah mendukung konstanta ini
    return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Fungsi untuk memformat currency
 */
function formatCurrency($amount, $currency = 'IDR') {
    if ($currency === 'IDR') {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    return $currency . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * Fungsi untuk sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Fungsi untuk log error
 */
function logError($message, $file = '', $line = 0) {
    $logMessage = date('Y-m-d H:i:s') . " - ERROR: " . $message;
    if ($file) {
        $logMessage .= " in " . $file;
    }
    if ($line) {
        $logMessage .= " on line " . $line;
    }
    
    // Pastikan folder logs sudah ada melalui bootstrap.php
    error_log($logMessage . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
}

/**
 * Fungsi untuk membuat basic auth header
 */
function createBasicAuth($username, $password) {
    return base64_encode($username . ":" . $password);
}

/**
 * Fungsi untuk validasi required parameters
 */
function validateRequired($params, $required) {
    $errors = array();
    
    foreach ($required as $field) {
        if (!isset($params[$field]) || empty($params[$field])) {
            $errors[] = "Parameter '" . $field . "' is required";
        }
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * Fungsi untuk generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

/**
 * Fungsi untuk convert berbagai format ke boolean
 */
function convertToBoolean($value) {
    if (is_bool($value)) {
        return $value;
    }
    
    if (is_string($value)) {
        $value = strtolower(trim($value));
        switch ($value) {
            case 'true':
            case '1':
            case 'yes':
            case 'on':
                return true;
            case 'false':
            case '0':
            case 'no':
            case 'off':
            case '':
                return false;
            default:
                return null;
        }
    }
    
    if (is_numeric($value)) {
        return (bool) $value;
    }
    
    return null;
}
?>