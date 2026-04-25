<?php
/**
 * API untuk mendapatkan informasi vendor/supplier
 * Versi Kompatibel: PHP 5.6
 */

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');

$api = new AccurateAPI();

// Menangkap parameter dari URL (GET)
$id = isset($_GET['id']) ? $_GET['id'] : null;
$vendorNo = isset($_GET['vendorNo']) ? $_GET['vendorNo'] : null;

if ($id || $vendorNo) {
    // Panggil fungsi dengan kedua kemungkinan parameter
    $result = $api->getVendorDetail($id, $vendorNo);
    
    if ($result['success']) {
        echo json_encode($result['data']);
    } else {
        header('HTTP/1.1 404 Not Found');
        $errorMsg = isset($result['error']) ? $result['error'] : 'Vendor not found';
        echo json_encode(array(
            'success' => false,
            'error' => 'Vendor not found',
            'message' => $errorMsg
        ));
    }
} else {
    // Jika tidak ada parameter, tampilkan list seperti sebelumnya
    $result = $api->getVendorList();
    if ($result['success']) {
        echo json_encode($result['data']);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(array(
            'success' => false,
            'message' => isset($result['error']) ? $result['error'] : 'Failed to get vendor list'
        ));
    }
}
?>