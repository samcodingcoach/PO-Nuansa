<?php
/**
 * Class AccurateAPI untuk handle semua API calls ke Accurate
 * Versi Kompatibel: PHP 5.6
 * Integrasi Auth: API Token Version 1.0.3 (Non-OAuth)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/utils.php';

class AccurateAPI {
    private $apiToken;
    private $signatureSecret;
    public $host;
    
    public function __construct() {
        // Mengambil dari config.php
        $this->apiToken = ACCURATE_API_TOKEN;
        $this->signatureSecret = ACCURATE_SIGNATURE_SECRET;
        
        // Resolve host otomatis saat inisialisasi class
        $this->host = $this->resolveHost();
    }
    
    private function resolveHost() {
        $url = 'https://account.accurate.id/api/api-token.do';
        $response = $this->executeCurl($url, 'POST');
        
        if ($response['success']) {
            // Membaca host dari struktur JSON terbaru Accurate
            if (isset($response['data']['d']['database']['host'])) {
                return rtrim($response['data']['d']['database']['host'], '/');
            } 
            // Fallback untuk struktur lama
            elseif (isset($response['data']['d']['host'])) {
                return rtrim($response['data']['d']['host'], '/');
            }
        }
        
        // Catat ke log jika benar-benar gagal
        if (function_exists('logError')) {
            logError("Gagal mendapatkan Host URL. Response: " . json_encode($response), __FILE__, __LINE__);
        }
        return null;
    }
    private function getAccurateTimestamp() {
        $dt = new DateTime("now", new DateTimeZone("Asia/Jakarta"));
        return $dt->format('d/m/Y H:i:s');
    }

    /**
     * Helper: Menghasilkan Signature menggunakan HMAC-SHA256
     */
    private function generateAccurateSignature($timestamp) {
        $hash = hash_hmac('sha256', $timestamp, $this->signatureSecret, true);
        return base64_encode($hash);
    }
    
    public function getBaseUrl() {
        return $this->host;
    }
    
    /**
     * Fungsi utama untuk memproses request API ke endpoint spesifik
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $headers = array()) {
        if (!$this->host) {
            return array(
                'success' => false, 
                'http_code' => 0, 
                'data' => null, 
                'error' => 'Host URL tidak ditemukan. Periksa API Token Anda.'
            );
        }

        // Susun full URL
        $endpoint = ltrim($endpoint, '/');
        $url = $this->host . '/' . $endpoint;
        
        return $this->executeCurl($url, $method, $data, $headers);
    }

    /**
     * Eksekutor cURL dengan injeksi Header Otorisasi API Token
     */
    private function executeCurl($url, $method = 'GET', $data = null, $customHeaders = array()) {
        $ch = curl_init();
        
        // Generate Security Headers
        $timestamp = $this->getAccurateTimestamp();
        $signature = $this->generateAccurateSignature($timestamp);

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // Mengatasi response code 308 (Redirect)
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_UNRESTRICTED_AUTH => true, // Mencegah header auth hilang saat redirect
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Nuansa Accurate API Client/2.0'
        );
        
        curl_setopt_array($ch, $options);
        
        $defaultHeaders = array(
            "Accept: application/json",
            "Authorization: Bearer " . $this->apiToken,
            "X-Api-Timestamp: " . $timestamp,
            "X-Api-Signature: " . $signature
        );
        
        $allHeaders = array_merge($defaultHeaders, $customHeaders);
        
        $method = strtoupper($method);
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    $isFormData = false;
                    foreach ($allHeaders as $header) {
                        if (stripos($header, 'Content-Type: application/x-www-form-urlencoded') !== false) {
                            $isFormData = true;
                            break;
                        }
                    }
                    if ($isFormData) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    } else {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        $allHeaders[] = 'Content-Type: application/json';
                    }
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $allHeaders[] = 'Content-Type: application/json';
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            if (function_exists('logError')) logError("cURL Error: " . $error, __FILE__, __LINE__);
            return array('success' => false, 'http_code' => 0, 'data' => null, 'error' => $error);
        }
        
        $decodedResponse = json_decode($response, true);
        $success = ($httpCode >= 200 && $httpCode < 300);
        
        // Pengecekan standar Accurate jika HTTP 200 tapi 's' (success) = false
        if ($success && is_array($decodedResponse) && isset($decodedResponse['s']) && $decodedResponse['s'] === false) {
            $success = false;
        }
        
        $errorMessage = null;
        if (!$success) {
            if (is_array($decodedResponse) && isset($decodedResponse['d']) && is_array($decodedResponse['d']) && !empty($decodedResponse['d'])) {
                $errorMessage = implode(', ', $decodedResponse['d']);
            } elseif (is_array($decodedResponse) && isset($decodedResponse['error'])) {
                $errorMessage = $decodedResponse['error'];
            } elseif (is_array($decodedResponse) && isset($decodedResponse['message'])) {
                $errorMessage = $decodedResponse['message'];
            } else {
                $errorMessage = "HTTP " . $httpCode . " error";
            }
            if (function_exists('logError')) logError("API Error: " . $errorMessage . " (HTTP " . $httpCode . ") - URL: " . $url, __FILE__, __LINE__);
        }
        
        return array(
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $errorMessage
        );
    }

    /* ====================================================================
       DAFTAR ENDPOINT SPECIFIC (Diadaptasi sesuai kode Anda sebelumnya)
       ==================================================================== */

    private function makeGetRequest($endpoint, $params = array()) {
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->makeRequest($endpoint, 'GET');
    }

    public function getBranchList($params = array()) {
        $endpoint = 'accurate/api/branch/list.do';
        $defaultParams = array(
            'sp.pageSize' => 25,
            'sp.page' => 1
        );
        $queryParams = array_merge($defaultParams, $params);
        
        return $this->makeGetRequest($endpoint, $queryParams);
    }

    public function getBranchDetail($id) {
        $endpoint = 'accurate/api/branch/detail.do';
        $params = array('id' => $id);
        
        return $this->makeGetRequest($endpoint, $params);
    }

    public function getPurchaseOrderList($params = array()) {
        $endpoint = 'accurate/api/purchase-order/list.do';
        $defaultParams = array(
            'sp.page' => 1,
            'sp.pageSize' => 200,
            'fields' => 'id,number,transDate,dueDate,totalAmount,status,statusName,vendor,vendor.name',
        );
        $finalParams = array_merge($defaultParams, $params);
        
        return $this->makeGetRequest($endpoint, $finalParams);
    }

    public function getPurchaseOrderDetail($purchaseOrderNumber) {
        if (empty($purchaseOrderNumber)) {
            return array(
                'success' => false,
                'message' => 'Purchase order ID / Number PO is required',
                'data' => null
            );
        }
        $endpoint = 'accurate/api/purchase-order/detail.do';
        $params = array('number' => $purchaseOrderNumber);
        
        return $this->makeGetRequest($endpoint, $params);
    }

    public function getVendorDetail($vendorId = null, $vendorNo = null) {
        $endpoint = 'accurate/api/vendor/detail.do';
        $params = array();

        if (!empty($vendorId)) {
            $params['id'] = $vendorId;
        } elseif (!empty($vendorNo)) {
            $params['vendorNo'] = $vendorNo;
        } else {
            return array(
                'success' => false,
                'error' => 'ID atau Nomor Vendor tidak boleh kosong'
            );
        }
        
        return $this->makeGetRequest($endpoint, $params);
    }
}
?>