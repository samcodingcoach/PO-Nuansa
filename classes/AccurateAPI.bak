<?php
/**
 * Class AccurateAPI untuk handle semua API calls ke Accurate
 * Versi Kompatibel: PHP 5.6
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/utils.php';

class AccurateAPI {
    // PHP 5.6 tidak mendukung type hinting pada properti
    private $accessToken;
    private $sessionId;
    private $host;
    private $authHost;
    private $databaseId;
    
    public function __construct() {
        $this->accessToken = ACCURATE_ACCESS_TOKEN;
        $this->sessionId = ACCURATE_SESSION_ID;
        $this->host = ACCURATE_API_HOST;
        $this->authHost = ACCURATE_AUTH_HOST;
        $this->databaseId = ACCURATE_DATABASE_ID;
    }
    
    public function setAccessToken($newToken) {
        $this->accessToken = $newToken;
    }
    
    public function setSessionId($newSessionId) {
        $this->sessionId = $newSessionId;
    }
    
    public function setHost($newHost) {
        $this->host = $newHost;
    }
    
    public function getSessionId() {
        return $this->sessionId;
    }
    
    public function getCurrentAccessToken() {
        return $this->accessToken;
    }
    
    public function getBaseUrl() {
        return $this->host;
    }
    
    private function makeRequest($url, $method = 'GET', $data = null, $headers = array()) {
        $ch = curl_init();
        
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Nuansa Accurate API Client/1.0'
        );
        
        curl_setopt_array($ch, $options);
        
        $defaultHeaders = array(
            "Accept: application/json"
        );

        if (strpos($url, '/oauth/token') === false) {
            $defaultHeaders[] = "Authorization: Bearer " . $this->accessToken;
            if ($this->sessionId) {
                $defaultHeaders[] = "X-Session-ID: " . $this->sessionId;
            }
        }
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
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
                    }
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            logError("cURL Error: " . $error, __FILE__, __LINE__);
            return array('success' => false, 'http_code' => 0, 'data' => null, 'error' => $error);
        }
        
        $decodedResponse = json_decode($response, true);
        $success = ($httpCode >= 200 && $httpCode < 300);
        
        if ($success && is_array($decodedResponse)) {
            if (isset($decodedResponse['s']) && $decodedResponse['s'] === false) {
                $success = false;
            }
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
            logError("API Error: " . $errorMessage . " (HTTP " . $httpCode . ") - URL: " . $url, __FILE__, __LINE__);
        }
        
        return array(
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $errorMessage
        );
    }
    
    public function getSessionInfo() {
        $databaseInfo = null;
        $databaseList = $this->getDatabaseList();
        if ($databaseList['success'] && isset($databaseList['data']['d'])) {
            foreach ($databaseList['data']['d'] as $db) {
                if ($db['id'] == $this->databaseId) {
                    $databaseInfo = $db;
                    break;
                }
                if (!$databaseInfo && !$db['expired']) {
                    $databaseInfo = $db;
                }
            }
            if (!$databaseInfo && !empty($databaseList['data']['d'])) {
                $databaseInfo = end($databaseList['data']['d']);
            }
        }
        
        // PHP 5.6: Menggunakan isset() sebagai pengganti Null Coalescing (??)
        return array(
            'access_token' => $this->accessToken,
            'session_id' => $this->sessionId,
            'host' => $this->host,
            'database_id' => $this->databaseId,
            'database_info' => $databaseInfo,
            'database_alias' => isset($databaseInfo['alias']) ? $databaseInfo['alias'] : 'Unknown Database',
            'database_expired' => isset($databaseInfo['expired']) ? $databaseInfo['expired'] : true,
            'database_trial_end' => isset($databaseInfo['trialEnd']) ? $databaseInfo['trialEnd'] : 'Unknown'
        );
    }

    public function getAccessToken($authCode) {
        $url = $this->authHost . '/oauth/token';
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => OAUTH_REDIRECT_URI
        );
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(OAUTH_CLIENT_ID . ':' . OAUTH_CLIENT_SECRET)
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('success' => false, 'http_code' => 0, 'data' => null, 'error' => $error);
        }

        $decodedResponse = json_decode($response, true);
        $success = ($httpCode >= 200 && $httpCode < 300);

        $err_msg = null;
        if (!$success && is_array($decodedResponse)) {
            $err_msg = isset($decodedResponse['error_description']) ? $decodedResponse['error_description'] : (isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Unknown error');
        }

        return array(
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $err_msg,
            'raw_response' => $response
        );
    }

    public function refreshToken($refreshToken) {
        $url = $this->authHost . '/oauth/token';
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        );
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(OAUTH_CLIENT_ID . ':' . OAUTH_CLIENT_SECRET)
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('success' => false, 'http_code' => 0, 'data' => null, 'error' => $error);
        }

        $decodedResponse = json_decode($response, true);
        $success = ($httpCode >= 200 && $httpCode < 300);

        $err_msg = null;
        if (!$success && is_array($decodedResponse)) {
            $err_msg = isset($decodedResponse['error_description']) ? $decodedResponse['error_description'] : (isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Unknown error');
        }

        return array(
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $err_msg,
            'raw_response' => $response
        );
    }

    public function updateConfigWithNewToken($tokenData) {
        if (!isset($tokenData['access_token'])) {
            return false;
        }
        
        $configPath = __DIR__ . '/../config/config.php';
        $configContent = file_get_contents($configPath);
        
        $configContent = preg_replace(
            "/define\('ACCURATE_ACCESS_TOKEN',\s*'[^']*'\);/",
            "define('ACCURATE_ACCESS_TOKEN', '" . $tokenData['access_token'] . "');",
            $configContent
        );
        
        if (isset($tokenData['refresh_token'])) {
            $configContent = preg_replace(
                "/define\('ACCURATE_REFRESH_TOKEN',\s*'[^']*'\);/",
                "define('ACCURATE_REFRESH_TOKEN', '" . $tokenData['refresh_token'] . "');",
                $configContent
            );
        }
        
        return file_put_contents($configPath, $configContent) !== false;
    }

    public function getDatabaseList() {
        $url = 'https://account.accurate.id/api/db-list.do';
        return $this->makeRequest($url);
    }

    public function closeSession() {
        $url = $this->host . '/accurate/api/close-session.do';
        return $this->makeRequest($url, 'POST');
    }

    public function openSession() {
        $url = $this->host . '/accurate/api/open-session.do';
        return $this->makeRequest($url, 'POST');
    }

    public function openDatabase($databaseId = null) {
        if (empty($databaseId)) {
            $databaseId = $this->databaseId;
        }
        
        if (empty($databaseId)) {
            return array(
                'success' => false,
                'error' => 'Database ID is required',
                'http_code' => 400,
                'data' => null
            );
        }
        
        $url = 'https://account.accurate.id/api/open-db.do?id=' . $databaseId;
        return $this->makeRequest($url, 'GET');
    }

    private function makeGetRequest($endpoint, $params = array()) {
        $url = $this->host . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get list cabang/branch dari Accurate API
     * Versi Kompatibel: PHP 5.6
     * @param array $params Parameter tambahan untuk query
     * @return array Response dari API
     */
    public function getBranchList($params = array()) {
        $url = $this->host . '/accurate/api/branch/list.do';
        
        // Parameter default menggunakan array()
        $defaultParams = array(
            'sp.pageSize' => 25,
            'sp.page' => 1
        );
        
        // Merge dengan parameter yang diberikan
        $queryParams = array_merge($defaultParams, $params);
        
        // Build URL dengan query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    

    /**
 * Get detail branch berdasarkan ID
 * Versi Kompatibel: PHP 5.6
 */
public function getBranchDetail($id) {
    // Gunakan endpoint detail.do sesuai dokumentasi Accurate
    $url = $this->host . '/accurate/api/branch/detail.do';
    
    $params = array(
        'id' => $id
    );
    
    $url .= '?' . http_build_query($params);
    
    // Pastikan makeRequest mengembalikan array yang berisi ['data']['d']
    return $this->makeRequest($url, 'GET');
}

    public function getPurchaseOrderList($params = array()) {
        $url = $this->host . '/accurate/api/purchase-order/list.do';
        
        $defaultParams = array(
            'sp.page' => 1,
            'sp.pageSize' => 200,
            'fields' => 'id,number,transDate,dueDate,totalAmount,status,statusName,vendor,vendor.name',
        );
        
        $finalParams = array_merge($defaultParams, $params);
        $url .= '?' . http_build_query($finalParams);
        
        return $this->makeRequest($url, 'GET');
    }

    public function getPurchaseOrderDetail($purchaseOrderNumber) {
        if (empty($purchaseOrderNumber)) {
            return array(
                'success' => false,
                'message' => 'Purchase order ID / Number PO is required',
                'data' => null
            );
        }
        
        $url = $this->host . '/accurate/api/purchase-order/detail.do';
        $params = array('number' => $purchaseOrderNumber);
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

   /**
 * Get detail vendor berdasarkan ID atau vendorNo
 * Versi Kompatibel: PHP 5.6
 */
public function getVendorDetail($vendorId = null, $vendorNo = null) {
    $url = $this->host . '/accurate/api/vendor/detail.do';
    $params = array();

    if (!empty($vendorId)) {
        $params['id'] = $vendorId;
    } elseif (!empty($vendorNo)) {
        // Menggunakan vendorNo sesuai dokumentasi gambar yang Anda kirim
        $params['vendorNo'] = $vendorNo;
    } else {
        return array(
            'success' => false,
            'message' => 'ID atau Nomor Vendor tidak boleh kosong'
        );
    }
    
    $url .= '?' . http_build_query($params);
    return $this->makeRequest($url, 'GET');
}

}
?>