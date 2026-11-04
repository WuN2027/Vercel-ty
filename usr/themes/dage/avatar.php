<?php
// QQ 头像代理 + 本地缓存，避免每次请求都访问腾讯服务器
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// 加密
$aesKey  = 'z9x7b5q2w8e3r1t6y0u4i8o2p5a7s9d';
$aesIv   = '5g7h2j4k6l1z8x0c';

$defaultAvatar = dirname(__FILE__) . '/bai/tx/default.png';
$fallbackAvatar = file_exists($defaultAvatar) ? $defaultAvatar : dirname(__FILE__) . '/bai/tx/1.jpg';

// 默认头像
if (empty($_GET['hash'])) {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    readfile($fallbackAvatar);
    exit;
}

// 正常逻辑
$encryptedQq = $_GET['hash'];
$size = $_GET['s'] ?? 100;
$validSizes = [40, 64, 100, 132];
$size = in_array($size, $validSizes) ? $size : 100;

// Base64
$encryptedQq = str_replace(' ', '+', $encryptedQq);
$encryptedQq = strtr($encryptedQq, '-_', '+/');
$padding = strlen($encryptedQq) % 4;
if ($padding > 0) {
    $encryptedQq .= str_repeat('=', 4 - $padding);
}

// 解码
$encryptedData = base64_decode($encryptedQq);
if ($encryptedData === false) {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    readfile($fallbackAvatar);
    exit;
}

// 解密
$qq = openssl_decrypt(
    $encryptedData,
    'aes-256-cbc',
    $aesKey,
    OPENSSL_RAW_DATA,
    $aesIv
);

// 验证 QQ
$qq = trim($qq);
if (!is_numeric($qq) || strlen($qq) < 5 || strlen($qq) > 10) {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    readfile($fallbackAvatar);
    exit;
}

// 本地缓存目录
$cacheDir = dirname(__FILE__) . '/cache/avatars/';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . md5($qq . '_' . $size) . '.jpg';

// 命中缓存直接输出
if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    readfile($cacheFile);
    exit;
}

// 获取头像
$qqAvatarUrl = "https://q1.qlogo.cn/g?b=qq&nk={$qq}&s={$size}";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $qqAvatarUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
]);
$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 输出
if ($httpCode == 200 && !empty($imageData)) {
    @file_put_contents($cacheFile, $imageData);
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    echo $imageData;
} else {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    readfile($fallbackAvatar);
}
