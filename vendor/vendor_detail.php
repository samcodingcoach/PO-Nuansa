<?php
/**
 * API untuk mendapatkan informasi vendor/supplier
 * Versi Kompatibel: PHP 5.6
 * Berdasarkan dokumentasi Accurate API untuk vendor
 */

require_once __DIR__ . '/../bootstrap.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Inisialisasi API class (Versi 5.6)
$api = new AccurateAPI();

/**
 * PHP 5.6: Mengganti operator Null Coalescing (??) dengan isset ternary
 * Mengambil parameter ID dari GET atau POST
 */
$vendorId = null;
if (isset($_GET['id'])) {
    $vendorId = $_GET['id'];
} elseif (isset($_POST['id'])) {
    $vendorId = $_POST['id'];
}

if ($vendorId) {
    // Get vendor detail by ID
    // Pastikan fungsi getVendorDetail sudah Anda tambahkan di AccurateAPI.php 5.6
    $result = $api->getVendorDetail($vendorId);
    
    if ($result['success']) {
        echo json_encode($result['data']);
    } else {
        // Mengatur response code secara manual untuk kompatibilitas
        header('HTTP/1.1 404 Not Found');
        
        $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
        $errorResponse = array(
            'error' => 'Vendor not found',
            'message' => $errorMsg
        );
        echo json_encode($errorResponse);
    }
} else {
    // Get vendor list (tanpa parameter)
    // Pastikan fungsi getVendorList sudah Anda tambahkan di AccurateAPI.php 5.6
    $result = $api->getVendorList();
    
    if ($result['success']) {
        echo json_encode($result['data']);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        
        $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
        $errorResponse = array(
            'error' => 'Failed to get vendor list',
            'message' => $errorMsg
        );
        echo json_encode($errorResponse);
    }
}
?>