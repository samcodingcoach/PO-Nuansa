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
     * Test API endpoint
     * @param string $endpoint Endpoint to test
     * @return array Response from API
     */
    public function testEndpoint($endpoint) {
        $url = $this->host . $endpoint;
        return $this->makeRequest($url);
    }

    /**
     * Get available API endpoints
     * @return array List of available endpoints
     */
    public function getAvailableEndpoints() {
        return [
            'item' => '/accurate/api/item/list.do',
            'employee' => '/accurate/api/employee/list.do',
            'branch' => '/accurate/api/branch/list.do',
            'vendor' => '/accurate/api/vendor/list.do',
            'warehouse' => '/accurate/api/warehouse/list.do',
            'customer' => '/accurate/api/customer/list.do',
            'coa' => '/accurate/api/coa/list.do',
            'department' => '/accurate/api/department/list.do',
            'unit' => '/accurate/api/unit/list.do',
            'currency' => '/accurate/api/currency/list.do',
            'tax' => '/accurate/api/tax/list.do',
            'sales_invoice' => '/accurate/api/sales-invoice/list.do',
            'purchase_invoice' => '/accurate/api/purchase-invoice/list.do',
            'journal' => '/accurate/api/journal/list.do',
            'report' => '/accurate/api/report/list.do',
            'item_category' => '/accurate/api/item-category/list.do',
            'payment_term' => '/accurate/api/payment-term/list.do'
        ];
    }

    /**
     * Check token status and scopes
     * @return array Token status information
     */
    public function checkTokenStatus() {
        $url = $this->authHost . '/oauth/check_token';
        
        $params = [
            'token' => $this->accessToken
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url);
    }

    /**
     * Get token scopes
     * @return array Available scopes
     */
    public function getTokenScopes() {
        $tokenStatus = $this->checkTokenStatus();
        
        if ($tokenStatus['success'] && isset($tokenStatus['data']['scope'])) {
            return explode(' ', $tokenStatus['data']['scope']);
        }
        
        return [];
    }

    /**
     * Get approved scopes for current token
     * Uses official endpoint: https://account.accurate.id/api/approved-scope.do
     * @return array Response with approved scopes
     */
    public function getApprovedScopes() {
        $url = 'https://account.accurate.id/api/approved-scope.do';
        
        $headers = [
            "Authorization: Bearer {$this->accessToken}",
            "Accept: application/json"
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode,
                'data' => [],
                'raw_response' => ''
            ];
        }
        
        $success = $httpCode >= 200 && $httpCode < 300;
        $decodedResponse = json_decode($response, true);
        
        if (!$success) {
            return [
                'success' => false,
                'error' => 'HTTP ' . $httpCode . ' error',
                'http_code' => $httpCode,
                'data' => [],
                'raw_response' => $response
            ];
        }
        
        // Parse scope data from API response
        $scopeData = [];
        
        if ($decodedResponse) {
            // Handle different response formats
            if (is_array($decodedResponse)) {
                // Check if there's 'd' field (typical Accurate API format)
                if (isset($decodedResponse['d']) && is_array($decodedResponse['d'])) {
                    $scopeData = $decodedResponse['d'];
                } elseif (is_array($decodedResponse) && !empty($decodedResponse)) {
                    // Or direct array of scopes
                    $scopeData = $decodedResponse;
                }
            } elseif (is_string($decodedResponse)) {
                // If response is space-separated string
                $scopeData = explode(' ', trim($decodedResponse));
            }
        }
        
        // Ensure scopeData is valid array of strings
        if (!is_array($scopeData)) {
            $scopeData = [];
        }
        
        // Filter only string elements
        $scopeData = array_filter($scopeData, function($scope) {
            return is_string($scope) && !empty(trim($scope));
        });
        
        // Remove duplicates and reindex
        $scopeData = array_values(array_unique($scopeData));
        
        return [
            'success' => true,
            'data' => $scopeData,
            'http_code' => $httpCode,
            'error' => null,
            'raw_response' => $response
        ];
    }

    /**
     * Test scope access
     * @param string $scope Scope to test
     * @return bool Access status
     */
    private function testScopeAccess($scope) {
        $testMethods = [
            'item_view' => 'testItemView',
            'branch_view' => 'testBranchView',
            'vendor_view' => 'testVendorView',
            'warehouse_view' => 'testWarehouseView',
            'customer_view' => 'testCustomerView',
            'coa_view' => 'testCoaView',
            'department_view' => 'testDepartmentView',
            'employee_view' => 'testEmployeeView',
            'unit_view' => 'testUnitView',
            'currency_view' => 'testCurrencyView',
            'tax_view' => 'testTaxView',
            'sales_invoice_view' => 'testSalesInvoiceView',
            'purchase_invoice_view' => 'testPurchaseInvoiceView',
            'journal_view' => 'testJournalView',
            'report_view' => 'testReportView',
            'item_category_view' => 'testItemCategoryView',
            'payment_term_view' => 'testPaymentTermView'
        ];
        
        if (!isset($testMethods[$scope])) {
            return false;
        }
        
        $method = $testMethods[$scope];
        if (!method_exists($this, $method)) {
            return false;
        }
        
        $result = $this->$method();
        return $result['success'];
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

    // Test methods for different scopes
    public function testItemView() {
        return $this->makeGetRequest('/accurate/api/item/list.do', ['sp.pageSize' => 1]);
    }

    public function testBranchView() {
        return $this->makeGetRequest('/accurate/api/branch/list.do', ['sp.pageSize' => 1]);
    }

    public function testVendorView() {
        return $this->makeGetRequest('/accurate/api/vendor/list.do', ['sp.pageSize' => 1]);
    }

    public function testWarehouseView() {
        return $this->makeGetRequest('/accurate/api/warehouse/list.do', ['sp.pageSize' => 1]);
    }

    public function testCustomerView() {
        return $this->makeGetRequest('/accurate/api/customer/list.do', ['sp.pageSize' => 1]);
    }

    public function testCoaView() {
        return $this->makeGetRequest('/accurate/api/coa/list.do', ['sp.pageSize' => 1]);
    }

    public function testDepartmentView() {
        return $this->makeGetRequest('/accurate/api/department/list.do', ['sp.pageSize' => 1]);
    }

    public function testEmployeeView() {
        return $this->makeGetRequest('/accurate/api/employee/list.do', ['sp.pageSize' => 1]);
    }

    public function testUnitView() {
        return $this->makeGetRequest('/accurate/api/unit/list.do', ['sp.pageSize' => 1]);
    }

    public function testCurrencyView() {
        return $this->makeGetRequest('/accurate/api/currency/list.do', ['sp.pageSize' => 1]);
    }

    public function testTaxView() {
        return $this->makeGetRequest('/accurate/api/tax/list.do', ['sp.pageSize' => 1]);
    }

    public function testSalesInvoiceView() {
        return $this->makeGetRequest('/accurate/api/sales-invoice/list.do', ['sp.pageSize' => 1]);
    }

    public function testPurchaseInvoiceView() {
        return $this->makeGetRequest('/accurate/api/purchase-invoice/list.do', ['sp.pageSize' => 1]);
    }

    public function testJournalView() {
        return $this->makeGetRequest('/accurate/api/journal/list.do', ['sp.pageSize' => 1]);
    }

    public function testReportView() {
        return $this->makeGetRequest('/accurate/api/report/list.do', ['sp.pageSize' => 1]);
    }

    public function testItemCategoryView() {
        return $this->makeGetRequest('/accurate/api/item-category/list.do', ['sp.pageSize' => 1]);
    }

    public function testPaymentTermView() {
        return $this->makeGetRequest('/accurate/api/payment-term/list.do', ['sp.pageSize' => 1]);
    }

    /**
     * Get list of vendors
     * @param array $params Parameters untuk filter (page, per_page, dll)
     * @return array Response dari API
     */
    /**
     * Get vendor list dengan parameter
     * @param mixed $params Parameter untuk filtering (array) atau page number (int)
     * @param int $pageSize Page size jika parameter pertama adalah page number
     * @return array Response dari API
     */
    public function getVendorList($params = [], $pageSize = null) {
        $url = $this->host . '/accurate/api/vendor/list.do';
        
        // Parameter default
        $defaultParams = [
            'sp.pageSize' => 25,
            'sp.page' => 1,
            'fields' => 'id,name,no,email,mobilePhone,phone,balanceList,category'
        ];
        
        // Handle backward compatibility - jika params adalah integer (page number)
        if (is_int($params) && $pageSize !== null) {
            $params = [
                'sp.page' => $params,
                'sp.pageSize' => $pageSize
            ];
        } elseif (!is_array($params)) {
            $params = [];
        }
        
        // Merge dengan parameter yang diberikan
        $queryParams = array_merge($defaultParams, $params);
        
        // Build URL dengan query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get detail vendor berdasarkan ID
     * @param int $id ID dari vendor
     * @return array Response dari API
     */
    public function getVendorDetail($id) {
        $url = $this->host . '/accurate/api/vendor/detail.do';
        
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get list of warehouses
     * @param mixed $params Parameters untuk filter (array) atau limit (int)
     * @param int $page Page number jika parameter pertama adalah limit
     * @return array Response dari API
     */
    public function getWarehouseList($params = [], $page = null) {
        $url = $this->host . '/accurate/api/warehouse/list.do';
        
        // Parameter default
        $defaultParams = [
            'sp.pageSize' => 25,
            'sp.page' => 1
        ];
        
        // Handle backward compatibility - jika params adalah integer (limit)
        if (is_int($params) && $page !== null) {
            $params = [
                'sp.pageSize' => $params,
                'sp.page' => $page
            ];
        } elseif (!is_array($params)) {
            $params = [];
        }
        
        // Merge dengan parameter yang diberikan
        $queryParams = array_merge($defaultParams, $params);
        
        // Build URL dengan query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get detail warehouse berdasarkan ID
     * @param int $id ID dari warehouse
     * @return array Response dari API
     */
    public function getWarehouseDetail($id) {
        $url = $this->host . '/accurate/api/warehouse/detail.do';
        
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get list of branches
     * @param array $params Parameters untuk filter (page, per_page, dll)
     * @return array Response dari API
     */
    public function getBranchList($params = []) {
        $url = $this->host . '/accurate/api/branch/list.do';
        
        // Parameter default
        $defaultParams = [
            'sp.pageSize' => 25,
            'sp.page' => 1
        ];
        
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
     * @param int $id ID dari branch
     * @return array Response dari API
     */
    public function getBranchDetail($id) {
        $url = $this->host . '/accurate/api/branch/detail.do';
        
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get list of employees dengan pagination
     * @param int $limit Jumlah data per halaman
     * @param int $page Nomor halaman
     * @return array Response dari API
     */
    public function getEmployeeList($limit = 25, $page = 1) {
        $url = $this->host . '/accurate/api/employee/list.do';
        $params = ['sp.pageSize' => $limit, 'sp.page' => $page];
        $url .= '?' . http_build_query($params);
        return $this->makeRequest($url);
    }

    /**
 * Get list of units dengan pagination
 * @param int $limit Jumlah data per halaman
 * @param int $page Nomor halaman
 * @return array Response dari API
 */
    public function getUnitList($limit = 25, $page = 1) {
    $url = $this->host . '/accurate/api/unit/list.do';
    $params = ['sp.pageSize' => $limit, 'sp.page' => $page];
    $url .= '?' . http_build_query($params);
    return $this->makeRequest($url);
    }

    /**
     * Get unit detail berdasarkan ID
     * @param int $unitId ID unit
     * @return array Response dari API
     */
    public function getUnitDetail($unitId) {
        // Validasi ID unit
        if (empty($unitId)) {
            return [
                'success' => false,
                'message' => 'Unit ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/unit/detail.do';
        
        $params = [
            'id' => $unitId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Save unit to Accurate
     * @param array $unitData Unit data to save, requires 'name'
     * @return array Response from API
     */
    public function saveUnit($unitData) {
        // Validasi data required
        if (!isset($unitData['name']) || empty($unitData['name'])) {
            return [
                'success' => false,
                'error' => "Field 'name' is required",
                'data' => null,
                'http_code' => 400
            ];
        }

        $url = $this->host . '/accurate/api/unit/save.do';
        
        // Prepare data for API
        $postData = [
            'name' => $unitData['name']
        ];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        return $this->makeRequest($url, 'POST', http_build_query($postData), $headers);
    }

    /**
     * Get list of customers dengan pagination
     * @param mixed $params Parameter tambahan untuk query (array) atau limit (int)
     * @param int $page Page number jika parameter pertama adalah limit
     * @return array Response dari API
     */
    public function getCustomerList($params = [], $page = null) {
        $url = $this->host . '/accurate/api/customer/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,name,no,customerNo,email,mobilePhone,phone,address,createDate,createdDate,lastUpdate,balanceList'
        ];
        
        // Handle backward compatibility - jika params adalah integer (limit)
        if (is_int($params) && $page !== null) {
            $params = [
                'sp.pageSize' => $params,
                'sp.page' => $page
            ];
        } elseif (!is_array($params)) {
            $params = [];
        }
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get customer detail berdasarkan ID
     * @param int $customerId ID customer
     * @return array Response dari API
     */
    public function getCustomerDetail($customerId) {
        // Validasi ID customer
        if (empty($customerId)) {
            return [
                'success' => false,
                'message' => 'Customer ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/customer/detail.do';
        
        $params = [
            'id' => $customerId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get employee detail berdasarkan ID
     * @param int $employeeId ID employee
     * @return array Response dari API
     */
    public function getEmployeeDetail($employeeId) {
        // Validasi ID employee
        if (empty($employeeId)) {
            return [
                'success' => false,
                'message' => 'Employee ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/employee/detail.do';
        
        $params = [
            'id' => $employeeId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Auto refresh token if needed
     * @return bool Success status
     */
    public function autoRefreshIfNeeded() {
        $tokenStatus = $this->checkTokenStatus();
        
        if (!$tokenStatus['success']) {
            // Token might be expired, try to refresh
            if (defined('ACCURATE_REFRESH_TOKEN') && !empty(ACCURATE_REFRESH_TOKEN)) {
                $refreshResult = $this->refreshToken(ACCURATE_REFRESH_TOKEN);
                
                if ($refreshResult['success'] && isset($refreshResult['data']['access_token'])) {
                    // Update config with new token
                    $this->updateConfigWithNewToken($refreshResult['data']);
                    
                    // Update current instance
                    $this->setAccessToken($refreshResult['data']['access_token']);
                    
                    return true;
                }
            }
            return false;
        }
        
        return true;
    }

    /**
     * Get list of items dengan pagination dan filter
     * @param int $limit Jumlah data per halaman
     * @param int $page Nomor halaman
     * @param array $filters Filter tambahan
     * @return array Response dari API
     */
    public function getItemList($limit = 100, $page = 1, $filters = []) {
        $url = $this->host . '/accurate/api/item/list.do';
        $params = [
            'sp.pageSize' => $limit,
            'sp.page' => $page,
            'fields' => 'id,name,no,itemType,itemTypeName,unitPrice,vendorPrice,availableToSell,lastUpdate'
        ];
        if (!empty($filters)) {
            $params = array_merge($params, $filters);
        }
        $url .= '?' . http_build_query($params);
        return $this->makeRequest($url);
    }

    /**
     * Get item detail berdasarkan ID
     * @param int $itemId ID item
     * @return array Response dari API
     */
    public function getItemDetail($itemId) {
        // Validasi ID item
        if (empty($itemId)) {
            return [
                'success' => false,
                'message' => 'Item ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/item/detail.do';
        
        $params = [
            'id' => $itemId,
            'fields' => 'id,no,name,unitPrice,vendorPrice,unit1,unit2,unit3,unit4,unit5,itemCategory,itemCategory.name,detailSellingPrice,detailSellingPrice.branch,detailSellingPrice.branch.name,detailSellingPrice.priceCategory,detailSellingPrice.priceCategory.name,detailSellingPrice.price,detailSellingPrice.effectiveDate'
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get list of item transfers dengan pagination dan filter
     * @param int $limit Jumlah data per halaman
     * @param int $page Nomor halaman
     * @param array $filters Filter tambahan
     * @return array Response dari API
     */
    public function getItemTransferList($limit = 25, $page = 1, $filters = []) {
        $url = $this->host . '/accurate/api/item-transfer/list.do';
        $params = [
            'sp.pageSize' => $limit,
            'sp.page' => $page,
            'fields' => 'id,number,transDate,itemTransferOutStatus,referenceWarehouseName,warehouseName'
        ];
        if (!empty($filters)) {
            $params = array_merge($params, $filters);
        }
        $url .= '?' . http_build_query($params);
        return $this->makeRequest($url);
    }

    /**
     * Get item transfer detail berdasarkan ID
     * @param int $transferId ID transfer
     * @return array Response dari API
     */
    public function getItemTransferDetail($transferId) {
        if (empty($transferId)) {
            return ['success' => false, 'message' => 'Item Transfer ID is required', 'data' => null];
        }
        $url = $this->host . '/accurate/api/item-transfer/detail.do';
        $params = [
            'id' => $transferId,
            'fields' => 'number,transDateView,branchId,warehouseName,statusName,itemTransferType,itemTransferOutStatus,referenceWarehouseName,detailItem,detailItem.item,detailItem.item.unit1,detailItem.detailSerialNumber,detailItem.detailSerialNumber.serialNumber'
        ];
        $url .= '?' . http_build_query($params);
        return $this->makeRequest($url);
    }

    /**
     * Get shipment list dengan parameter
     * @param mixed $params Parameter untuk filtering (array) atau page number (int)
     * @param int $pageSize Page size jika parameter pertama adalah page number
     * @return array Response dari API
     */
    public function getShipmentList($params = [], $pageSize = null) {
        $url = $this->host . '/accurate/api/shipment/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100
        ];
        
        // Handle backward compatibility - jika params adalah integer (page number)
        if (is_int($params) && $pageSize !== null) {
            $params = [
                'sp.page' => $params,
                'sp.pageSize' => $pageSize
            ];
        } elseif (!is_array($params)) {
            $params = [];
        }
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get shipment detail berdasarkan ID
     * @param int $shipmentId ID shipment
     * @return array Response dari API
     */
    public function getShipmentDetail($shipmentId) {
        // Validasi ID shipment
        if (empty($shipmentId)) {
            return [
                'success' => false,
                'message' => 'Shipment ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/shipment/detail.do';
        
        $params = [
            'id' => $shipmentId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get item category list dengan pagination
     * @param mixed $params Parameters untuk filter (array) atau page number (int)
     * @param int $pageSize Page size jika parameter pertama adalah page number
     * @return array Response dari API
     */
    public function getItemCategoryList($params = [], $pageSize = null) {
        $url = $this->host . '/accurate/api/item-category/list.do';
        
        // Default parameters untuk pagination
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,name,nameWithIndent,nameWithIndentStrip,lvl,parentNode,defaultCategory'
        ];
        
        // Handle backward compatibility - jika params adalah integer (page number)
        if (is_int($params) && $pageSize !== null) {
            $params = [
                'sp.page' => $params,
                'sp.pageSize' => $pageSize
            ];
        } elseif (!is_array($params)) {
            $params = [];
        }
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get item category detail berdasarkan ID
     * @param int $itemCategoryId ID item category
     * @return array Response dari API
     */
    public function getItemCategoryDetail($itemCategoryId) {
        // Validasi ID item category
        if (empty($itemCategoryId)) {
            return [
                'success' => false,
                'message' => 'Item category ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/item-category/detail.do';
        
        $params = [
            'id' => $itemCategoryId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get list of fixed assets
     * @param array $params Parameters untuk filter (page, per_page, dll)
     * @return array Response dari API
     */
    public function getFixedAssetList($params = []) {
        $url = $this->host . '/accurate/api/fixed-asset/list.do';
        
        // Parameter default
        $defaultParams = [
            'sp.pageSize' => 25,
            'sp.page' => 1
        ];
        
        // Merge dengan parameter yang diberikan
        $queryParams = array_merge($defaultParams, $params);
        
        // Build URL dengan query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get detail fixed asset berdasarkan ID
     * @param int $id ID dari fixed asset
     * @return array Response dari API
     */
    public function getFixedAssetDetail($id) {
        $url = $this->host . '/accurate/api/fixed-asset/detail.do';
        
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get price category list dengan pagination
     * @param array $params Parameters untuk filter
     * @return array Response dari API
     */
    public function getPriceCategoryList($params = []) {
        $url = $this->host . '/accurate/api/price-category/list.do';
        
        // Default parameters untuk pagination
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,name'
        ];
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get price category detail berdasarkan ID
     * @param int $priceCategoryId ID price category
     * @return array Response dari API
     */
    public function getPriceCategoryDetail($priceCategoryId) {
        // Validasi ID price category
        if (empty($priceCategoryId)) {
            return [
                'success' => false,
                'message' => 'Price category ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/price-category/detail.do';
        
        $params = [
            'id' => $priceCategoryId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get sales order list
     * @param array $params Parameter tambahan untuk query
     * @return array Response dari API
     */
    public function getSalesOrderList($params = []) {
        $url = $this->host . '/accurate/api/sales-order/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,number,transDate,dueDate,totalAmount,status,statusName,customer,customer.name,paymentTerm,paymentTerm.name'
        ];
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get sales order detail berdasarkan ID
     * @param int $salesOrderId ID sales order
     * @return array Response dari API
     */
    public function getSalesOrderDetail($salesOrderId) {
        // Validasi ID sales order
        if (empty($salesOrderId)) {
            return [
                'success' => false,
                'message' => 'Sales order ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/sales-order/detail.do';
        
        $params = [
            'id' => $salesOrderId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get sales invoice list
     * @param array $params Parameter tambahan untuk query
     * @return array Response dari API
     */
    public function getSalesInvoiceList($params = []) {
        $url = $this->host . '/accurate/api/sales-invoice/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,number,transDate,invoiceTime,totalAmount,status,statusName,customer,customer.name'
        ];
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get sales invoice detail berdasarkan ID
     * @param int $salesInvoiceId ID sales invoice
     * @return array Response dari API
     */
    public function getSalesInvoiceDetail($salesInvoiceId) {
        // Validasi ID sales invoice
        if (empty($salesInvoiceId)) {
            return [
                'success' => false,
                'message' => 'Sales invoice ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/sales-invoice/detail.do';
        
        $params = [
            'id' => $salesInvoiceId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get purchase invoice list dengan parameter
     * @param array $params Parameter untuk filtering
     * @return array Response dari API
     */
    public function getPurchaseInvoiceList($params = []) {
        $url = $this->host . '/accurate/api/purchase-invoice/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,number,transDate,dueDate,totalAmount,status,statusName,vendor,vendor.name,paidAmount,remainingAmount,age'
        ];
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get purchase invoice detail berdasarkan ID
     * @param int $purchaseInvoiceId ID purchase invoice
     * @return array Response dari API
     */
    public function getPurchaseInvoiceDetail($purchaseInvoiceId) {
        // Validasi ID purchase invoice
        if (empty($purchaseInvoiceId)) {
            return [
                'success' => false,
                'message' => 'Purchase invoice ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/purchase-invoice/detail.do';
        
        $params = [
            'id' => $purchaseInvoiceId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get sales receipt list dengan parameter
     * @param array $params Parameter untuk filtering
     * @return array Response dari API
     */
    public function getSalesReceiptList($params = []) {
        $url = $this->host . '/accurate/api/sales-receipt/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100
        ];
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get sales receipt detail berdasarkan ID
     * @param int $salesReceiptId ID sales receipt
     * @return array Response dari API
     */
    public function getSalesReceiptDetail($salesReceiptId) {
        // Validasi ID sales receipt
        if (empty($salesReceiptId)) {
            return [
                'success' => false,
                'message' => 'Sales receipt ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/sales-receipt/detail.do';
        
        $params = [
            'id' => $salesReceiptId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get purchase order list dari Accurate API
     * @param array $params Parameter tambahan untuk query
     * @return array Response dari API
     */
    public function getPurchaseOrderList($params = []) {
        $url = $this->host . '/accurate/api/purchase-order/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,number,transDate,dueDate,totalAmount,status,statusName,vendor,vendor.name'
        ];
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
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

    /**
     * Save item to Accurate
     * @param array $itemData Item data to save
     * @return array Response from API
     */
    public function saveItem($itemData) {
        // Validasi data required
        $requiredFields = ['no', 'itemCategoryName', 'name', 'unit1Name', 'manageSN'];
        foreach ($requiredFields as $field) {
            if (!isset($itemData[$field])) {
                return [
                    'success' => false,
                    'error' => "Field {$field} is required",
                    'data' => null
                ];
            }
        }

        $url = $this->host . '/accurate/api/item/save.do';
        
        // Prepare data for API
        $postData = [
            'no' => $itemData['no'],
            'itemCategoryName' => $itemData['itemCategoryName'],
            'itemType' => $itemData['itemType'] ?? 'INVENTORY',
            'name' => $itemData['name'],
            'unit1Name' => $itemData['unit1Name'],
            'manageSN' => $itemData['manageSN'] ? 'true' : 'false',
            'serialNumberType' => 'UNIQUE'
        ];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        return $this->makeRequest($url, 'POST', http_build_query($postData), $headers);
    }

    /**
     * Save selling price adjustment to Accurate
     * @param array $sellingPriceData Selling price data to save
     * @return array Response from API
     */
    public function saveSellingPrice($sellingPriceData) {
        // Validasi data required
        $requiredFields = ['salesAdjustmentType', 'detailItem'];
        foreach ($requiredFields as $field) {
            if (!isset($sellingPriceData[$field])) {
                return [
                    'success' => false,
                    'error' => "Field {$field} is required",
                    'data' => null
                ];
            }
        }

        $url = $this->host . '/accurate/api/sellingprice-adjustment/save.do';
        
        // Prepare data for API - mengikuti format yang berhasil di Postman
        $postData = [
            'salesAdjustmentType' => $sellingPriceData['salesAdjustmentType'],
            'transDate' => $sellingPriceData['transDate'] ?? date('d/m/Y')
        ];
        
        // Add priceCategoryName berdasarkan ID yang diberikan
        if (isset($sellingPriceData['id']) && !empty($sellingPriceData['id'])) {
            // Mapping ID ke nama kategori harga dan ID yang sesuai
            $priceCategoryMapping = [
                '50' => ['level' => 'Price #1', 'id' => 50],
                '1700' => ['level' => 'Price #2', 'id' => 100], 
                '1701' => ['level' => 'Price #3', 'id' => 150],
                '1750' => ['level' => 'Price #4', 'id' => 200],
                '1800' => ['level' => 'Price #5', 'id' => 201],
                '1850' => ['level' => 'Price #6', 'id' => 202],
                '1751' => ['level' => 'Price #7', 'id' => 250]
            ];
            
            if (isset($priceCategoryMapping[$sellingPriceData['id']])) {
                $postData['priceCategoryName'] = $priceCategoryMapping[$sellingPriceData['id']]['level'];
                $postData['id'] = $priceCategoryMapping[$sellingPriceData['id']]['id'];
            } else {
                return [
                    'success' => false,
                    'error' => "Invalid price category ID: {$sellingPriceData['id']}",
                    'data' => null
                ];
            }
        } else {
            return [
                'success' => false,
                'error' => "Price category ID is required",
                'data' => null
            ];
        }

        // Add detail items
        foreach ($sellingPriceData['detailItem'] as $index => $detail) {
            $postData["detailItem[{$index}].itemNo"] = $detail['itemNo'];
            $postData["detailItem[{$index}].price"] = $detail['price'];
        }

        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        return $this->makeRequest($url, 'POST', http_build_query($postData), $headers);
    }

    /**
     * Get payment term list dengan pagination
     * @param mixed $params Parameters untuk filter (array) atau limit (int)
     * @param int $page Page number jika parameter pertama adalah limit
     * @return array Response dari API
     */
    public function getPaymentTermList($params = [], $page = null) {
        $url = $this->host . '/accurate/api/payment-term/list.do';
        
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 100,
            'fields' => 'id,name,description,dueDays,discountDays,discountRate,suspended,createDate'
        ];
        
        // Handle backward compatibility - jika params adalah integer (limit)
        if (is_int($params) && $page !== null) {
            $params = [
                'sp.pageSize' => $params,
                'sp.page' => $page
            ];
        } elseif (!is_array($params)) {
            $params = [];
        }
        
        // Merge dengan params yang diberikan
        $params = array_merge($defaultParams, $params);
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get payment term detail berdasarkan ID
     * @param int $paymentTermId ID payment term
     * @return array Response dari API
     */
    public function getPaymentTermDetail($paymentTermId) {
        // Validasi ID payment term
        if (empty($paymentTermId)) {
            return [
                'success' => false,
                'message' => 'Payment term ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/payment-term/detail.do';
        
        $params = [
            'id' => $paymentTermId
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Create sales order to Accurate API
     * @param array $salesOrderData Sales order data to create
     * @return array Response from API
     */
    public function createSalesOrder($salesOrderData) {
        // Validasi data required
        $requiredFields = ['customerNo', 'branchId'];
        foreach ($requiredFields as $field) {
            if (!isset($salesOrderData[$field]) || empty($salesOrderData[$field])) {
                return [
                    'success' => false,
                    'error' => "Field {$field} is required",
                    'data' => null
                ];
            }
        }

        // Check for detail items
        $hasItems = false;
        foreach ($salesOrderData as $key => $value) {
            if (strpos($key, 'detailItem') === 0 && strpos($key, 'itemNo') !== false) {
                $hasItems = true;
                break;
            }
        }

        if (!$hasItems) {
            return [
                'success' => false,
                'error' => 'At least one item is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/sales-order/save.do';
        
        // Prepare data for API - use the exact format that works
        $postData = [];
        
        // Copy all data directly - the format is already correct from the frontend
        foreach ($salesOrderData as $key => $value) {
            $postData[$key] = $value;
        }
        
        // Ensure required fields are set
        if (!isset($postData['transDate']) || empty($postData['transDate'])) {
            $postData['transDate'] = date('d/m/Y');
        }
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        return $this->makeRequest($url, 'POST', http_build_query($postData), $headers);
    }

    /**
     * Get item selling price based on price category
     * @param string $itemNo Item number/code
     * @param string $branchName Branch name (optional)
     * @param string $priceCategoryName Price category name (optional)
     * @return array Response from API
     */
    public function getItemSellingPrice($itemNo, $branchName = '', $priceCategoryName = '') {
        // Validasi item number required
        if (empty($itemNo)) {
            return [
                'success' => false,
                'error' => 'Item number is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/item/get-selling-price.do';
        
        // Prepare parameters
        $params = [
            'no' => $itemNo
        ];
        
        // Add optional parameters if provided
        if (!empty($branchName)) {
            $params['branchName'] = $branchName;
        }
        
        if (!empty($priceCategoryName)) {
            $params['priceCategoryName'] = $priceCategoryName;
        }
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get serial number report per warehouse
     * @param string $itemNo Item number/code
     * @return array Response from API
     */
    public function getSerialNumberReport($itemNo) {
        // Validasi item number required
        if (empty($itemNo)) {
            return [
                'success' => false,
                'error' => 'Item number is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/report/serial-number-per-warehouse.do';
        
        // Prepare parameters
        $params = [
            'itemNo' => $itemNo
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get selling price adjustment list
     * @param array $params Parameters for filtering and pagination
     * @return array Response from API
     */
    public function getSellingPriceAdjustmentList($params = []) {
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 25,
            'fields' => 'id,number,spaBranchName,salesAdjustmentType,transDate'
        ];
        
        // Merge with provided parameters
        $params = array_merge($defaultParams, $params);
        
        $url = $this->host . '/accurate/api/sellingprice-adjustment/list.do';
        
        // Add parameters to URL if provided
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get selling price adjustment detail by ID
     * @param int $id Selling price adjustment ID
     * @return array Response from API
     */
    public function getSellingPriceAdjustmentDetail($id) {
        // Validasi ID required
        if (empty($id)) {
            return [
                'success' => false,
                'error' => 'Selling price adjustment ID is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/sellingprice-adjustment/detail.do';
        
        // Prepare parameters
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Get selling price adjustment detail by number
     * @param string $number Selling price adjustment number
     * @return array Response from API
     */
    public function getSellingPriceAdjustmentDetailByNumber($number) {
        // Validasi number required
        if (empty($number)) {
            return [
                'success' => false,
                'error' => 'Selling price adjustment number is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/sellingprice-adjustment/detail.do';
        
        // Prepare parameters
        $params = [
            'number' => $number
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }
    
    /**
     * Create sales invoice
     * @param array $invoiceData Invoice data to save
     * @return array Response from API
     */
    public function createSalesInvoice($invoiceData) {
        // Validasi data required fields
        $requiredFields = ['customerNo', 'branchId'];
        foreach ($requiredFields as $field) {
            if (!isset($invoiceData[$field]) || empty($invoiceData[$field])) {
                return [
                    'success' => false,
                    'error' => "Field {$field} is required",
                    'data' => null
                ];
            }
        }
        
        // Check for detail items
        $hasItems = false;
        foreach ($invoiceData as $key => $value) {
            if (strpos($key, 'detailItem') === 0 && strpos($key, 'itemNo') !== false) {
                $hasItems = true;
                break;
            }
        }
        
        if (!$hasItems) {
            return [
                'success' => false,
                'error' => 'At least one item is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/sales-invoice/save.do';
        
        // Prepare data for API - use the exact format that works
        $postData = [];
        
        // Copy all data directly - the format is already correct from the frontend
        foreach ($invoiceData as $key => $value) {
            $postData[$key] = $value;
        }
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        return $this->makeRequest($url, 'POST', http_build_query($postData), $headers);
    }

    /**
     * Get list of GL Accounts
     * @param array $params Parameters for filtering and pagination
     * @return array Response from API
     */
    public function getGlAccountList($params = []) {
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 50,
            'fields' => 'id,accountTypeName,balance,name,no'
        ];
        
        // Merge with provided parameters
        $queryParams = array_merge($defaultParams, $params);

        if(isset($params['type']) && !empty($params['type'])) {
            $queryParams['filter.accountType.op'] = 'EQUAL';
            $queryParams['filter.accountType.val'] = $params['type'];
            unset($queryParams['type']);
        }
        
        $url = $this->host . '/accurate/api/glaccount/list.do';
        
        // Add parameters to URL if provided
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get GL Account detail by ID
     * @param int $id GL Account ID
     * @return array Response from API
     */
    public function getGlAccountDetail($id) {
        // Validasi ID
        if (empty($id)) {
            return [
                'success' => false,
                'message' => 'GL Account ID is required',
                'data' => null
            ];
        }
        
        $url = $this->host . '/accurate/api/glaccount/detail.do';
        
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get journal voucher list
     * @param array $params Parameters for filtering and pagination
     * @return array Response from API
     */
    public function getJournalVoucherList($params = []) {
        // Default parameters
        $defaultParams = [
            'sp.page' => 1,
            'sp.pageSize' => 25,
            'fields' => 'id,number,transDate,transNumber,description'
        ];
        
        // Merge with provided parameters
        $params = array_merge($defaultParams, $params);
        
        $url = $this->host . '/accurate/api/journal-voucher/list.do';
        
        // Add parameters to URL if provided
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get journal voucher detail by ID
     * @param int $id Journal voucher ID
     * @return array Response from API
     */
    public function getJournalVoucherDetail($id) {
        // Validasi ID required
        if (empty($id)) {
            return [
                'success' => false,
                'error' => 'Journal voucher ID is required',
                'data' => null
            ];
        }

        $url = $this->host . '/accurate/api/journal-voucher/detail.do';
        
        // Prepare parameters
        $params = [
            'id' => $id
        ];
        
        $url .= '?' . http_build_query($params);
        
        return $this->makeRequest($url, 'GET');
    }

}

?>
