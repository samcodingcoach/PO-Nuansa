<?php
/**
 * Utility functions untuk aplikasi Accurate API
 * File ini berisi fungsi-fungsi helper yang sering digunakan
 */

/**
 * Fungsi untuk mengambil nilai nested dari array
 * @param array $array Array yang akan diextract
 * @param string $path Path dengan separator ">"
 * @return mixed Nilai yang ditemukan atau "-" jika tidak ada
 */
function getNested($array, $path) {
    $parts = explode(">", $path);
    $extract = function($data, $keys) use (&$extract) {
        if (empty($keys)) {
            if (is_bool($data)) {
                return $data ? 'True' : 'False';
            } elseif (is_array($data)) {
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
            if (array_keys($data) === range(0, count($data) - 1)) {
                if (count($data) > 0) {
                    return $extract($data[0], array_merge([$key], $keys));
                } else {
                    return "-";
                }
            } else {
                return isset($data[$key]) ? $extract($data[$key], $keys) : "-";
            }
        }
        return "-";
    };
    return $extract($array, $parts);
}

/**
 * Fungsi untuk memformat response JSON
 * @param mixed $data Data yang akan diformat
 * @param bool $success Status response
 * @param string $message Pesan response
 * @return string JSON response
 */
function jsonResponse($data = null, $success = true, $message = '') {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    header('Content-Type: application/json; charset=UTF-8');
    return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Fungsi untuk memformat currency
 * @param float $amount Jumlah yang akan diformat
 * @param string $currency Mata uang (default: IDR)
 * @return string Formatted currency
 */
function formatCurrency($amount, $currency = 'IDR') {
    if ($currency === 'IDR') {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    return $currency . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * Fungsi untuk sanitize input
 * @param string $input Input yang akan di-sanitize
 * @return string Clean input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Fungsi untuk log error
 * @param string $message Pesan error
 * @param string $file File yang error
 * @param int $line Line yang error
 */
function logError($message, $file = '', $line = 0) {
    $logMessage = date('Y-m-d H:i:s') . " - ERROR: $message";
    if ($file) {
        $logMessage .= " in $file";
    }
    if ($line) {
        $logMessage .= " on line $line";
    }
    
    error_log($logMessage . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
}

/**
 * Fungsi untuk membuat basic auth header
 * @param string $username Username
 * @param string $password Password
 * @return string Basic auth header
 */
function createBasicAuth($username, $password) {
    return base64_encode("$username:$password");
}

/**
 * Fungsi untuk validasi required parameters
 * @param array $params Parameters yang akan divalidasi
 * @param array $required Required parameters
 * @return array|bool Array error atau true jika valid
 */
function validateRequired($params, $required) {
    $errors = [];
    
    foreach ($required as $field) {
        if (!isset($params[$field]) || empty($params[$field])) {
            $errors[] = "Parameter '$field' is required";
        }
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * Fungsi untuk generate random string
 * @param int $length Panjang string
 * @return string Random string
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
 * @param mixed $value Value yang akan diconvert
 * @return bool|null Boolean value atau null jika tidak valid
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
