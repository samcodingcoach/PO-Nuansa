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
    
    // Dapatkan data purchase order list dari API
    $result = $api->getPurchaseOrderList();
    
    if ($result['success']) {
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        echo jsonResponse(null, false, $result['message'] ?? 'Failed to fetch purchase order data');
    }
} catch (Exception $e) {
    echo jsonResponse(null, false, 'Error: ' . $e->getMessage());
}
?>
