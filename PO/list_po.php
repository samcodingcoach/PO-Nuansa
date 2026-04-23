<?php
/**
 * API untuk mendapatkan list purchase order dalam format JSON
 * File: /purchaseorder/list_po.php
 * HTTP Method: GET
 * Scope: purchase_order_view
 * Endpoint: /list.do
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
    // Inisialisasi AccurateAPI
    $api = new AccurateAPI();
    
    // Siapkan filter dinamis dari parameter GET (misal: list_po.php?vendorNo=00-BJM-00063)
    $extraParams = [];
    if (isset($_GET['vendorNo']) && !empty($_GET['vendorNo'])) {
        $extraParams['filter.vendorNo'] = $_GET['vendorNo'];
    }

    // Jika ingin menambah filter lain secara dinamis via URL, bisa tambahkan di sini
    if (isset($_GET['page'])) {
        $extraParams['sp.page'] = $_GET['page'];
    }
    
    // Panggil fungsi dengan parameter yang sudah disiapkan
    $result = $api->getPurchaseOrderList($extraParams);
    
    if ($result['success']) {
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        // Menggunakan kunci 'error' sesuai struktur di makeRequest
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to fetch purchase order data'
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
