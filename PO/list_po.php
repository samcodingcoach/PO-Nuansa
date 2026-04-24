<?php
/**
 * API untuk mendapatkan list purchase order dalam format JSON
 * Versi Kompatibel: PHP 5.6
 * File: /purchaseorder/list_po.php
 */

require_once __DIR__ . '/../bootstrap.php';

// Set header untuk JSON response
header('Content-Type: application/json; charset=UTF-8');

// Handle hanya untuk method GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo jsonResponse(null, false, 'Method tidak diizinkan. Gunakan GET.');
    exit;
}

try {
    // Inisialisasi AccurateAPI (Versi 5.6)
    $api = new AccurateAPI();
    
    // Siapkan filter dinamis (Menggunakan array() untuk kompatibilitas 5.6)
    $extraParams = array();
    if (isset($_GET['vendorNo']) && !empty($_GET['vendorNo'])) {
        $extraParams['filter.vendorNo'] = $_GET['vendorNo'];
    }

    if (isset($_GET['page'])) {
        $extraParams['sp.page'] = $_GET['page'];
    }
    
    // Panggil fungsi dengan parameter yang sudah disiapkan
    $result = $api->getPurchaseOrderList($extraParams);
    
    if ($result['success']) {
        // Meniadakan raw_response agar output JSON bersih
        if (isset($result['raw_response'])) {
            unset($result['raw_response']);
        }
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        // PHP 5.6: Mengganti Null Coalescing (??) dengan isset ternary
        $errorMessage = isset($result['error']) ? $result['error'] : 'Failed to fetch purchase order data';
        
        $errorResponse = array(
            'success' => false,
            'message' => $errorMessage
        );
        echo json_encode($errorResponse, JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    // PHP 5.6: Menggunakan array() secara eksplisit
    $exceptionResponse = array(
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    );
    echo json_encode($exceptionResponse, JSON_PRETTY_PRINT);
}
?>