<?php
/**
 * Branch Detail API Endpoint
 * Versi Kompatibel: PHP 5.6
 * Returns branch details as JSON
 */

require_once __DIR__ . '/../bootstrap.php';

// Set JSON header
header('Content-Type: application/json');

// PHP 5.6: Ambil ID branch dari parameter tanpa ??
// Cek ID dengan berbagai variasi huruf (id atau ID)
$branchId = null;
if (isset($_GET['id'])) {
    $branchId = $_GET['id'];
} elseif (isset($_GET['ID'])) {
    $branchId = $_GET['ID'];
}

if (!$branchId) {
    $errorResponse = array(
        'success' => false,
        'message' => 'Branch ID is required. Pastikan parameter URL menggunakan ?id='
    );
    echo json_encode($errorResponse);
    exit;
}

try {
    // Inisialisasi API class
    $api = new AccurateAPI();

    // Ambil detail branch
    $branchResponse = $api->getBranchDetail($branchId);

    if ($branchResponse['success'] && isset($branchResponse['data'])) {
        if (isset($branchResponse['data']['s']) && $branchResponse['data']['s'] === true) {
            if (isset($branchResponse['data']['d'])) {
                // KITA UBAH DISINI: Langsung kirim isi 'd' sebagai 'data'
                $successResponse = array(
                    'success' => true,
                    'data' => $branchResponse['data']['d'] 
                );
                echo json_encode($successResponse);
                exit;
            }
        }
    }

    // Jika gagal
    $failResponse = array(
        'success' => false,
        'message' => 'Failed to get branch details',
        'data' => $branchResponse
    );
    echo json_encode($failResponse);

} catch (Exception $e) {
    $exceptionResponse = array(
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    );
    echo json_encode($exceptionResponse);
}
?>