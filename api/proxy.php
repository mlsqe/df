<?php
// 允许跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

session_start();
$uploadToken = $_POST['upload_token'] ?? '';
$cacheKey = 'upload_' . $uploadToken;

// 防重复提交
if ($uploadToken && isset($_SESSION[$cacheKey])) {
    http_response_code(400);
    echo json_encode(['status_code' => 400, 'status_txt' => '请勿重复提交']);
    exit;
}

if (!isset($_FILES['source']) || $_FILES['source']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status_code' => 400, 'status_txt' => '文件上传失败']);
    exit;
}

// 生成绝对唯一的文件名，避免 PicGo 去重
$originalName = basename($_FILES['source']['name']);
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$uniqueName = uniqid('avatar_', true) . '.' . $extension;

$picgoUrl = 'https://www.picgo.net/api/1/upload';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $picgoUrl);
curl_setopt($ch, CURLOPT_POST, true);

$postFields = [
    'source' => new CURLFile($_FILES['source']['tmp_name'], $_FILES['source']['type'], $uniqueName),
    'key' => 'chv_kLihc_744b6c65cad577c004f6fda0407c14c208bd2a6dcb0fe2fe7967ea8d7430e756_307540b68d7468dcb556e9ab59777832259d71bb72ad56f8be6b6be49070cbf0'   // 请在此处填写你的 PicGo API Key
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['status_code' => 500, 'status_txt' => '代理请求失败: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

// 标记上传令牌已使用
if ($uploadToken) {
    $_SESSION[$cacheKey] = true;
}

http_response_code($httpCode);
echo $response;