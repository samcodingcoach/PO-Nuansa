<?php
/**
 * API untuk mendapatkan detail purchase order dalam format JSON
 * Versi Kompatibel: PHP 5.6
 * File: /purchaseorder/detail_po.php
 */

require_once __DIR__ . '/../bootstrap.php';

// Set header untuk JSON response
header('Content-Type: application/json; charset=UTF-8');

// Handle hanya untuk method GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo jsonResponse(null, false, 'Method tidak diizinkan. Gunakan GET.');
    exit;
}

// Validasi parameter Nomor PO
if (!isset($_GET['nomor_po']) || empty($_GET['nomor_po'])) {
    echo jsonResponse(null, false, 'Parameter Nomor PO diperlukan');
    exit;
}

$id = $_GET['nomor_po'];

try {
    // Inisialisasi AccurateAPI (Versi 5.6)
    $api = new AccurateAPI();
    
    // Dapatkan data purchase order detail dari API
    $result = $api->getPurchaseOrderDetail($id);
    
    if ($result['success']) {
        // Hapus raw_response dari output agar JSON bersih
        if (isset($result['raw_response'])) {
            unset($result['raw_response']);
        }
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        // PHP 5.6: Mengganti Null Coalescing (??) dengan isset ternary
        $errorMessage = isset($result['message']) ? $result['message'] : 'Failed to fetch purchase order detail';
        echo jsonResponse(null, false, $errorMessage);
    }
} catch (Exception $e) {
    echo jsonResponse(null, false, 'Error: ' . $e->getMessage());
}
?>