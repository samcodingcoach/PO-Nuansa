<?php
/**
 * Class AccurateAPI untuk handle semua API calls ke Accurate
 * Menggabungkan semua fungsi API dalam satu class yang terorganisir
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/utils.php';

class AccurateAPI {
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
    
    /**
     * Get current session ID
     * @return string Current session ID
     */
    public function getSessionId() {
        return $this->sessionId;
    }
    
    /**
     * Get current access token
     * @return string Current access token
     */
    public function getCurrentAccessToken() {
        return $this->accessToken;
    }
    
    /**
     * Get base URL for API calls
     * @return string Base URL
     */
    public function getBaseUrl() {
        return $this->host;
    }
    
    /**
     * Make HTTP request to Accurate API
     * @param string $url URL endpoint
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param mixed $data Request data
     * @param array $headers Additional headers
     * @return array Response array with success, http_code, data, error
     */
    private function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Nuansa Accurate API Client/1.0'
        ]);
        
        $defaultHeaders = [
            "Accept: application/json"
        ];

        // Only add Auth and Session headers for non-OAuth requests
        if (strpos($url, '/oauth/token') === false) {
            $defaultHeaders[] = "Authorization: Bearer {$this->accessToken}";
            if ($this->sessionId) {
                $defaultHeaders[] = "X-Session-ID: {$this->sessionId}";
            }
        }
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        switch (strtoupper($method)) {
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
            logError("cURL Error: $error", __FILE__, __LINE__);
            return ['success' => false, 'http_code' => 0, 'data' => null, 'error' => $error];
        }
        
        $decodedResponse = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300;
        
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
                $errorMessage = "HTTP $httpCode error";
            }
            logError("API Error: $errorMessage (HTTP $httpCode) - URL: $url", __FILE__, __LINE__);
        }
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $errorMessage,
            'raw_response' => $response
        ];
    }
    
    /**
     * Get session information including database details
     * @return array Session information
     */
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
        return [
            'access_token' => $this->accessToken,
            'session_id' => $this->sessionId,
            'host' => $this->host,
            'database_id' => $this->databaseId,
            'database_info' => $databaseInfo,
            'database_alias' => $databaseInfo['alias'] ?? 'Unknown Database',
            'database_expired' => $databaseInfo['expired'] ?? true,
            'database_trial_end' => $databaseInfo['trialEnd'] ?? 'Unknown'
        ];
    }

    /**
     * Get access token from Accurate OAuth
     * @param string $authCode Authorization code
     * @return array Response from token endpoint
     */
    public function getAccessToken($authCode) {
        $url = $this->authHost . '/oauth/token';
        
        $data = [
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => OAUTH_REDIRECT_URI
        ];
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(OAUTH_CLIENT_ID . ':' . OAUTH_CLIENT_SECRET)
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logError("cURL Error in getAccessToken: $error", __FILE__, __LINE__);
            return ['success' => false, 'http_code' => 0, 'data' => null, 'error' => $error];
        }

        $decodedResponse = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $success ? null : ($decodedResponse['error_description'] ?? $decodedResponse['error'] ?? 'Unknown error'),
            'raw_response' => $response
        ];
    }

    /**
     * Refresh access token
     * @param string $refreshToken Refresh token
     * @return array Response from token endpoint
     */
    public function refreshToken($refreshToken) {
        $url = $this->authHost . '/oauth/token';
        
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(OAUTH_CLIENT_ID . ':' . OAUTH_CLIENT_SECRET)
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logError("cURL Error in refreshToken: $error", __FILE__, __LINE__);
            return ['success' => false, 'http_code' => 0, 'data' => null, 'error' => $error];
        }

        $decodedResponse = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $decodedResponse,
            'error' => $success ? null : ($decodedResponse['error_description'] ?? $decodedResponse['error'] ?? 'Unknown error'),
            'raw_response' => $response
        ];
    }

    /**
     * Update config.php with new token data
     * @param array $tokenData Token data from OAuth response
     * @return bool Success status
     */
    public function updateConfigWithNewToken($tokenData) {
        if (!isset($tokenData['access_token'])) {
            return false;
        }
        
        $configPath = __DIR__ . '/../config/config.php';
        $configContent = file_get_contents($configPath);
        
        // Update access token
        $configContent = preg_replace(
            "/define\('ACCURATE_ACCESS_TOKEN',\s*'[^']*'\);/",
            "define('ACCURATE_ACCESS_TOKEN', '{$tokenData['access_token']}');",
            $configContent
        );
        
        // Update refresh token if available
        if (isset($tokenData['refresh_token'])) {
            $configContent = preg_replace(
                "/define\('ACCURATE_REFRESH_TOKEN',\s*'[^']*'\);/",
                "define('ACCURATE_REFRESH_TOKEN', '{$tokenData['refresh_token']}');",
                $configContent
            );
        }
        
        return file_put_contents($configPath, $configContent) !== false;
    }

    /**
     * Get list of databases
     * @return array Response from API
     */
    public function getDatabaseList() {
        $url = 'https://account.accurate.id/api/db-list.do';
        return $this->makeRequest($url);
    }

    /**
     * Close current session
     * @return array Response from API
     */
    public function closeSession() {
        $url = $this->host . '/accurate/api/close-session.do';
        return $this->makeRequest($url, 'POST');
    }

    /**
     * Open new session
     * @return array Response from API
     */
    public function openSession() {
        $url = $this->host . '/accurate/api/open-session.do';
        return $this->makeRequest($url, 'POST');
    }
    /**
     * Open database
     * @param int $databaseId Database ID to open
     * @return array Response from API
     */
    public function openDatabase($databaseId = null) {
        // Gunakan database ID dari parameter atau default
        if (empty($databaseId)) {
            $databaseId = $this->databaseId;
        }
        
        if (empty($databaseId)) {
            return [
                'success' => false,
                'error' => 'Database ID is required',
                'http_code' => 400,
                'data' => null
            ];
        }
        
        // Gunakan endpoint yang benar dari dokumentasi (GET dengan URL parameter)
        $url = 'https://account.accurate.id/api/open-db.do?id=' . $databaseId;
        
        return $this->makeRequest($url, 'GET');
    }


    /**
     * Helper method for GET requests
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array Response from API
     */
    private function makeGetRequest($endpoint, $params = []) {
        $url = $this->host . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get purchase order list dari Accurate API
     * @param string $params Parameter tambahan untuk query
     * @return array Response dari API
     */
    public function getPurchaseOrderList($vendorNo) {

        if (empty($vendorNo)) {
            return [
                'success' => false,
                'message' => 'Vendor Number is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/purchase-order/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 10,
            'fields' => 'id,number,transDate,dueDate,totalAmount,status,statusName,vendor,vendor.name,vendor.id'
        ];

        $params = [
            'vendorNo' => $vendorNo
        ];
        
        // Merge dengan params yang diberikan
        // $params = array_merge($defaultParams, $params);

      
        
        $url .= '?' . http_build_query($defaultParams) . '&' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get purchase order detail berdasarkan Nomor PO
     * @param string $purchaseOrderNumber Nomor purchase order
     * @return array Response dari API
     */
    public function getPurchaseOrderDetail($purchaseOrderNumber) {
        // Validasi ID purchase order
        if (empty($purchaseOrderNumber)) {
            return [
                'success' => false,
                'message' => 'Purchase order ID / Number PO is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/purchase-order/detail.do';
        
        $params = [
            'number' => $purchaseOrderNumber
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

}

?>
