<?php

// ================================= //
// --- PHẦN CẤU HÌNH DỰ ÁN ---
// ================================= //
// Đổi tên file này thành config.php và điền thông tin thật của bạn.
// File config.php sẽ không được đưa lên Git để đảm bảo an toàn.

// --- Thông tin đăng nhập cPanel ---
$cpanel_host = 'your_cpanel_host.com:2083'; // VD: yourdomain.com:2083
$cpanel_user = 'your_cpanel_username';
$cpanel_pass = 'your_cpanel_password';

// --- Cấu hình chi tiết cho kịch bản ---
$config = [
    // --- Cấu hình Source Code ---
    // Thư mục trên server, sẽ tự động lấy theo username cPanel
    'targetDirectory'   => '/home/' . $cpanel_user . '/public_html', 
    
    // Đường dẫn tuyệt đối đến file source code .zip trên máy của bạn
    'localFileToUpload' => 'C:/path/to/your/source-code.zip',

    // File môi trường cần chỉnh sửa sau khi giải nén
    'fileToEdit'        => '/.env',

    // --- Cấu hình Database ---
    // Tên database, user, và mật khẩu sẽ tự động tạo dựa trên username cPanel
    'database_name'     => $cpanel_user . '_dbname',
    'db_user'           => $cpanel_user . '_dbuser',
    'db_pass'           => 'VeryStrongPassword123!', // 🔑 Đặt một mật khẩu database thật mạnh ở đây
    'db_host'           => 'localhost',
    
    // Đường dẫn tuyệt đối đến file backup .sql trên máy của bạn
    'localSqlFile'      => 'C:/path/to/your/database-backup.sql',
];


// =================================================================================
// === CÔNG CỤ TỰ ĐỘNG HÓA TRIỂN KHAI CPANEL TOÀN DIỆN =============================
// =================================================================================
// Tác giả: Trần Đăng Khoa & Gemini
// Phiên bản logic DB: 16/08/2025 (sử dụng DOMDocument)
// Chức năng:
// 1. Tự động upload và cấu hình source code (.env).
// 2. Tự động tạo Database và User.
// 3. Tự động xóa sạch bảng và import file .sql mới (logic mới, mạnh mẽ hơn).
// 4. Chế độ triển khai đầy đủ (làm cả 3 việc trên).
// =================================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(900); // Tăng thời gian chạy lên 15 phút cho các tác vụ lớn

/**
 * Hàm gửi yêu cầu cURL cho các API của cPanel (UAPI, JSON API).
 * Trả về kết quả đã được giải mã từ JSON.
 */
function sendApiRequest(string $url, string $cookieFile, string $method = 'GET', array $headers = [], $data = null, bool $isUAPI = false)
{
    $ch = curl_init();
    if ($ch === false) {
        error_log('Lỗi: Không thể khởi tạo cURL cho API.');
        return false;
    }

    $isUpload = false;
    if (is_array($data)) {
        foreach ($data as $value) {
            if ($value instanceof CURLFile) {
                $isUpload = true;
                break;
            }
        }
    }

    $postFields = null;
    if (strtoupper($method) !== 'GET') {
        $postFields = $isUpload ? $data : (is_array($data) ? http_build_query($data) : $data);
    } elseif (is_array($data) && $isUAPI) {
        $url .= '?' . http_build_query($data);
    }
    
    if ($isUpload) {
        foreach ($headers as $i => $header) {
            if (stripos($header, 'Content-Type:') === 0) unset($headers[$i]);
        }
    }

    $options = [
        CURLOPT_URL            => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method), CURLOPT_HTTPHEADER     => array_values($headers),
        CURLOPT_POSTFIELDS     => $postFields, CURLOPT_TIMEOUT        => 600,
        CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5, CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile, CURLOPT_SSL_VERIFYPEER => false,
    ];

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    if (curl_errno($ch)) {
        error_log('Lỗi cURL API: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $body = substr($response, $headerSize);
    return json_decode($body, true);
}

/**
 * Hàm gửi yêu cầu cURL giả lập trình duyệt (cho phpMyAdmin).
 * Trả về kết quả thô (HTML/text).
 */
function sendBrowserRequest(string $url, string $cookieFile, string $method = 'GET', array $headers = [], $data = null)
{
    $ch = curl_init();
    $defaultHeaders = ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'];
    $finalHeaders = array_merge($defaultHeaders, $headers);
    $postFields = null;

    if ($data !== null && strtoupper($method) === 'POST') {
        $isMultipart = false;
        if (is_array($data)) {
            foreach ($data as $value) {
                if ($value instanceof CURLFile) {
                    $isMultipart = true;
                    break;
                }
            }
        }
        $postFields = $isMultipart ? $data : (is_array($data) ? http_build_query($data) : $data);
    }
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER         => false,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method), CURLOPT_HTTPHEADER     => $finalHeaders,
        CURLOPT_POSTFIELDS     => $postFields, CURLOPT_TIMEOUT        => 900,
        CURLOPT_COOKIEJAR      => $cookieFile, CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}


/**
 * Chức năng 1: Dọn dẹp, upload, giải nén và cấu hình source code.
 */
function uploadAndConfigure(string $cpanel_host, string $securityToken, string $cookieFile, array $config)
{
    echo "\n==================================================\n";
    echo " BẮT ĐẦU TÁC VỤ 1: UPLOAD VÀ CẤU HÌNH SOURCE CODE \n";
    echo "==================================================\n";
    
    $targetDirectory = $config['targetDirectory'];
    $localFileToUpload = $config['localFileToUpload'];
    $fileToEdit = $config['fileToEdit'];

    // --- DỌN DẸP THƯ MỤC CŨ ---
    echo "🚀 Đang dọn dẹp thư mục {$targetDirectory}...\n";
    $apiUrlJson = "https://{$cpanel_host}{$securityToken}/json-api/cpanel";
    $listParams = ['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'listfiles', 'cpanel_jsonapi_apiversion' => '2', 'dir' => $targetDirectory, 'showdotfiles' => '1'];
    $listResult = sendApiRequest($apiUrlJson . '?' . http_build_query($listParams), $cookieFile, 'GET');

    $itemsToDelete = [];
    if (!empty($listResult['cpanelresult']['data'])) {
        foreach ($listResult['cpanelresult']['data'] as $item) {
            if ($item['file'] !== '.' && $item['file'] !== '..') $itemsToDelete[] = $item['fullpath'];
        }
    }

    if (empty($itemsToDelete)) {
        echo "✅ Thư mục đã trống.\n\n";
    } else {
        echo "🔎 Tìm thấy " . count($itemsToDelete) . " mục, đang xóa...\n";
        $deletePostData = ['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'fileop', 'cpanel_jsonapi_apiversion' => '2', 'op' => 'unlink', 'doubledecode' => '0'];
        $deleteDataString = http_build_query($deletePostData);
        foreach ($itemsToDelete as $itemPath) $deleteDataString .= '&sourcefiles=' . urlencode($itemPath);
        
        $deleteResult = sendApiRequest($apiUrlJson, $cookieFile, 'POST', [], $deleteDataString);
        if ($deleteResult && empty($deleteResult['cpanelresult']['error'])) echo "✅ Dọn dẹp thành công.\n\n";
        else die("❌ Lỗi khi dọn dẹp: " . ($deleteResult['cpanelresult']['error'] ?? 'Unknown error'));
    }

    // --- UPLOAD, GIẢI NÉN, CẤU HÌNH ---
    if (!file_exists($localFileToUpload)) die("❌ Lỗi: Không tìm thấy file '{$localFileToUpload}'.\n");
    echo "🚀 Đang upload file '{$localFileToUpload}'...\n";
    $uploadUrl = "https://{$cpanel_host}{$securityToken}/execute/Fileman/upload_files";
    $uploadPostData = ['dir' => $targetDirectory, 'file-0' => new CURLFile(realpath($localFileToUpload))];
    $uploadData = sendApiRequest($uploadUrl, $cookieFile, 'POST', [], $uploadPostData);
    if (!$uploadData || !empty($uploadData['errors'])) die("❌ Upload thất bại: " . ($uploadData['errors'][0] ?? 'Unknown error'));
    echo "✅ Upload thành công!\n";

    echo "🚀 Đang giải nén file trên server...\n";
    $serverFilePath = $targetDirectory . '/' . basename($localFileToUpload);
    $extractPostData = ['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'fileop', 'cpanel_jsonapi_apiversion' => '2', 'op' => 'extract', 'sourcefiles' => $serverFilePath, 'destfiles' => $targetDirectory];
    $extractResult = sendApiRequest($apiUrlJson, $cookieFile, 'POST', [], $extractPostData);
    if (!$extractResult || !empty($extractResult['cpanelresult']['error'])) die("❌ Lỗi khi giải nén: " . ($extractResult['cpanelresult']['error'] ?? 'Unknown error'));
    echo "✅ Giải nén thành công!\n";

    echo "🚀 Đang cấu hình file {$fileToEdit}...\n";
    $uapi_url = "https://{$cpanel_host}{$securityToken}/execute/Fileman/get_file_content";
    $getContentResult = sendApiRequest($uapi_url, $cookieFile, 'GET', [], ['dir' => dirname($targetDirectory . $fileToEdit), 'file' => basename($fileToEdit)], true);
    if (!$getContentResult || !$getContentResult['status']) die("❌ Lỗi đọc file .env: " . ($getContentResult['errors'][0] ?? 'Unknown error'));
    
    $currentContent = $getContentResult['data']['content'];
    $replacements = [
        'DB_HOST'     => $config['db_host'], 'DB_DATABASE' => $config['database_name'],
        'DB_USERNAME' => $config['db_user'], 'DB_PASSWORD' => $config['db_pass'],
    ];
    $lines = explode("\n", $currentContent);
    $newLines = [];
    $updatedKeys = [];
    foreach ($lines as $line) {
        $found = false;
        foreach ($replacements as $key => $value) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/i', $line)) {
                $newLines[] = "{$key}={$value}"; $updatedKeys[$key] = true; $found = true; break;
            }
        }
        if (!$found) $newLines[] = $line;
    }
    foreach ($replacements as $key => $value) {
        if (!isset($updatedKeys[$key])) $newLines[] = "{$key}={$value}";
    }
    $newContent = implode("\n", $newLines);
    $savePostData = ['cpanel_jsonapi_apiversion' => '2', 'cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'savefile', 'dir' => dirname($targetDirectory . $fileToEdit), 'filename' => basename($fileToEdit), 'content' => $newContent];
    $saveResult = sendApiRequest($apiUrlJson, $cookieFile, 'POST', [], $savePostData);
    if (!$saveResult || !empty($saveResult['cpanelresult']['error'])) die("❌ Lỗi lưu file .env: " . ($saveResult['cpanelresult']['error'] ?? 'Unknown error'));
    echo "✅ Cấu hình .env thành công!\n";

    echo "🚀 Đang dọn dẹp file zip...\n";
    $cleanupPostData = ['cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'fileop', 'cpanel_jsonapi_apiversion' => '2', 'op' => 'unlink', 'sourcefiles' => $serverFilePath];
    sendApiRequest($apiUrlJson, $cookieFile, 'POST', [], $cleanupPostData);
    echo "✅ Dọn dẹp file zip hoàn tất.\n";
}


/**
 * Chức năng 2: Tạo database, user và gán quyền.
 */
function createDatabaseAndUser(string $cpanel_host, string $securityToken, string $cookieFile, array $config)
{
    echo "\n=================================================\n";
    echo " BẮT ĐẦU TÁC VỤ 2: TẠO DATABASE VÀ USER \n";
    echo "=================================================\n";
    $apiUrl = "https://{$cpanel_host}{$securityToken}/execute/";

    echo "🚀 Đang tạo database '{$config['database_name']}'...\n";
    $createDbResult = sendApiRequest($apiUrl . 'Mysql/create_database', $cookieFile, 'GET', [], ['name' => $config['database_name']], true);
    if ($createDbResult && $createDbResult['status']) echo "✅ Tạo database thành công.\n";
    elseif (strpos($createDbResult['errors'][0] ?? '', 'already exists') !== false) echo "⚠️  Database đã tồn tại, bỏ qua.\n";
    else die("❌ Lỗi tạo database: " . ($createDbResult['errors'][0] ?? 'Unknown error'));

    echo "🚀 Đang tạo user '{$config['db_user']}'...\n";
    $createUserResult = sendApiRequest($apiUrl . 'Mysql/create_user', $cookieFile, 'GET', [], ['name' => $config['db_user'], 'password' => $config['db_pass']], true);
    if ($createUserResult && $createUserResult['status']) echo "✅ Tạo user thành công.\n";
    elseif (strpos($createUserResult['errors'][0] ?? '', 'already exists') !== false) echo "⚠️  User đã tồn tại, bỏ qua.\n";
    else die("❌ Lỗi tạo user: " . ($createUserResult['errors'][0] ?? 'Unknown error'));

    echo "🚀 Đang gán quyền...\n";
    $setPrivilegesResult = sendApiRequest($apiUrl . 'Mysql/set_privileges_on_database', $cookieFile, 'GET', [], ['user' => $config['db_user'], 'database' => $config['database_name'], 'privileges' => 'ALL PRIVILEGES'], true);
    if ($setPrivilegesResult && $setPrivilegesResult['status']) echo "✅ Gán quyền thành công.\n";
    else die("❌ Lỗi gán quyền: " . ($setPrivilegesResult['errors'][0] ?? 'Unknown error'));
}

/**
 * Chức năng 3: Xóa sạch bảng và import file .sql mới (LOGIC MỚI).
 */
function resetAndImportDatabase(string $cpanel_host, string $securityToken, string $cookieFile, array $config) {
    echo "\n=================================================\n";
    echo " BẮT ĐẦU TÁC VỤ 3: RESET & IMPORT DATABASE \n";
    echo "=================================================\n";
    $database_name = $config['database_name'];
    $localSqlFile = $config['localSqlFile'];

    echo "🚀 Đang lấy danh sách các bảng và token từ phpMyAdmin...\n";
    $pmaBaseUrl = "https://{$cpanel_host}{$securityToken}/3rdparty/phpMyAdmin/index.php?route=/database/structure&db={$database_name}";
    $initialPageContent = sendBrowserRequest($pmaBaseUrl, $cookieFile);
    $pmaToken = null;
    $htmlContent = '';
    
    $initialData = json_decode($initialPageContent, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($initialData['params']['token'])) {
        $pmaToken = $initialData['params']['token'];
        $htmlContent = $initialData['message'] ?? '';
    } else {
        preg_match('/<input type="hidden" name="token" value="([a-f0-9]{32})">/', $initialPageContent, $matches);
        if (!empty($matches[1])) $pmaToken = $matches[1];
        $htmlContent = $initialPageContent;
    }
    if (empty($pmaToken)) die("❌ Lỗi: Không tìm thấy phpMyAdmin CSRF token.");
    echo "   -> Lấy token thành công: {$pmaToken}\n";

    $tablesToDelete = [];
    if (!empty($htmlContent)) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//tr[starts-with(@id, 'row_tbl_')]");
        foreach ($rows as $row) {
            $tableNameNode = $xpath->query("th/a", $row);
            if ($tableNameNode->length > 0) $tablesToDelete[] = '`' . trim($tableNameNode[0]->nodeValue) . '`';
        }
    }

    if (empty($tablesToDelete)) {
        echo "✅ Database không có bảng nào để xóa.\n\n";
    } else {
        echo "✅ Đã lấy được danh sách " . count($tablesToDelete) . " bảng!\n\n";
        echo "🔎 Tìm thấy " . count($tablesToDelete) . " bảng cần xóa.\n";
    
        echo "🚀 Đang xóa bảng...\n";
        
        $tempSqlFile = __DIR__ . '/temp_drop_tables.sql';
        $dropQuery = "SET FOREIGN_KEY_CHECKS=0;\nDROP TABLE IF EXISTS " . implode(', ', $tablesToDelete) . ";\nSET FOREIGN_KEY_CHECKS=1;";
        file_put_contents($tempSqlFile, $dropQuery);
    
        $pmaImportUrl = "https://{$cpanel_host}{$securityToken}/3rdparty/phpMyAdmin/index.php?route=/import";
        
        // ✅ SỬA LỖI: Bổ sung đầy đủ các trường dữ liệu POST để giả lập form hợp lệ
        $importDropPostData = [
            'import_type'     => 'database', 'db' => $database_name, 'token' => $pmaToken,
            'MAX_FILE_SIZE'   => '536870912', 'charset_of_file' => 'utf-8', 
            'allow_interrupt' => 'yes', 'skip_queries' => '0', 'fk_checks' => '0', 'format' => 'sql',
            'import_file'     => new CURLFile(realpath($tempSqlFile), 'application/sql', basename($tempSqlFile)),
        ];
        
        $dropHeaders = [ 'X-Requested-With: XMLHttpRequest', 'Referer: ' . $pmaBaseUrl ];
        $response = sendBrowserRequest($pmaImportUrl, $cookieFile, 'POST', $dropHeaders, $importDropPostData);
        unlink($tempSqlFile);

        if (strpos($response, 'Import has been successfully finished') !== false) {
            echo "✅ Đã xóa tất cả các bảng thành công.\n\n";
        } else {
            $data = json_decode($response, true);
            $errorMessage = $data['error'] ?? 'Unknown error';
            die("❌ Lỗi khi xóa bảng. Phản hồi: " . $errorMessage . "\n---\nRaw Response (first 1000 chars):\n" . substr($response, 0, 1000));
        }
    }

    if (!file_exists($localSqlFile)) die("❌ Lỗi: Không tìm thấy file '{$localSqlFile}'.\n");
    echo "🚀 Đang import dữ liệu từ '{$localSqlFile}'...\n";
    $pmaImportUrl = "https://{$cpanel_host}{$securityToken}/3rdparty/phpMyAdmin/index.php?route=/import";
    
    // ✅ ĐỒNG BỘ: Sử dụng đầy đủ các trường POST cho cả thao tác import chính
    $importMainPostData = [
        'import_type'     => 'database', 'db' => $database_name, 'token' => $pmaToken,
        'MAX_FILE_SIZE'   => '536870912', 'charset_of_file' => 'utf-8', 'allow_interrupt' => 'yes',
        'skip_queries'    => '0', 'fk_checks' => '0', 'format' => 'sql',
        'import_file'     => new CURLFile(realpath($localSqlFile), 'application/octet-stream', basename($localSqlFile)),
    ];
    $importHeaders = [
        'X-Requested-With: XMLHttpRequest',
        'Referer: https://' . $cpanel_host . $securityToken . '/3rdparty/phpMyAdmin/index.php?route=/database/import&db=' . $database_name,
    ];
    $importResponse = sendBrowserRequest($pmaImportUrl, $cookieFile, 'POST', $importHeaders, $importMainPostData);

    if (strpos($importResponse, 'Import has been successfully finished') !== false) {
        echo "✅ Import database hoàn tất.\n";
    } else {
        $importData = json_decode($importResponse, true);
        $errorMessage = $importData['error'] ?? 'Không nhận được thông báo thành công.';
        if(isset($importData['message'])) $errorMessage .= ' Message: ' . strip_tags($importData['message']);
        die("❌ Lỗi khi import database. Phản hồi: " . $errorMessage . "\n---\nRaw Response (first 1000 chars):\n" . substr($importResponse, 0, 1000));
    }
}


// ================================= //
// --- PHẦN THỰC THI KỊCH BẢN ---
// ================================= //

$loginUrl = "https://{$cpanel_host}/login/?login_only=1";
$cookieFile = __DIR__ . '/cookie_main.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

echo "🚀 Bước 1: Đang đăng nhập vào cPanel...\n";
$loginResult = sendApiRequest($loginUrl, $cookieFile, 'POST', [], ['user' => $cpanel_user, 'pass' => $cpanel_pass]);
if (!$loginResult || !isset($loginResult['status']) || $loginResult['status'] != 1) {
    die("❌ Đăng nhập thất bại. Phản hồi: " . json_encode($loginResult));
}
$securityToken = $loginResult['security_token'];
echo "✅ Đăng nhập thành công!\n\n";

// --- HIỂN THỊ MENU LỰA CHỌN ---
while (true) {
    echo "================ MENU ================\n";
    echo "  1. Chỉ Upload & Cấu hình source code\n";
    echo "  2. Chỉ Tạo Database & User\n";
    echo "  3. Chỉ Reset & Import Database\n";
    echo "  4. 🔥 TRIỂN KHAI ĐẦY ĐỦ (1 + 2 + 3)\n";
    echo "  0. Thoát\n";
    echo "======================================\n";
    $choice = readline("Vui lòng chọn chức năng: ");

    switch ($choice) {
        case '1':
            uploadAndConfigure($cpanel_host, $securityToken, $cookieFile, $config);
            break 2;
        case '2':
            createDatabaseAndUser($cpanel_host, $securityToken, $cookieFile, $config);
            break 2;
        case '3':
            resetAndImportDatabase($cpanel_host, $securityToken, $cookieFile, $config);
            break 2;
        case '4':
            uploadAndConfigure($cpanel_host, $securityToken, $cookieFile, $config);
            createDatabaseAndUser($cpanel_host, $securityToken, $cookieFile, $config);
            resetAndImportDatabase($cpanel_host, $securityToken, $cookieFile, $config);
            break 2;
        case '0':
            echo "Đã thoát chương trình.\n";
            exit;
        default:
            echo "\n❌ Lựa chọn không hợp lệ. Vui lòng nhập lại.\n\n";
    }
}

echo "\n🎉 Kịch bản đã hoàn thành!\n";
if (file_exists($cookieFile)) unlink($cookieFile);

?>