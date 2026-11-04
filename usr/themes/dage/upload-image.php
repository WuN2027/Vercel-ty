<?php
// 禁止直接访问
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /404.html');
    exit;
}

// 验证请求来源
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (empty($referer) || strpos($referer, $host) === false) {
    header('Location: /404.html');
    exit;
}

// 基础配置
$siteDomain = 'https://' . $host;
$baseUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/usr/uploads/comment-images/';
$baseUploadUrl = $siteDomain . '/usr/uploads/comment-images/';

// 上传限制
$maxSize = 2048 * 1024;
$allowTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// 日期格式
$yearMonth = date('Ym');
$uploadDir = $baseUploadDir . $yearMonth . '/';
$uploadUrl = $baseUploadUrl . $yearMonth . '/';

// 递归创建目录
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        exit(json_encode([
            'success' => false,
            'msg' => '无法创建上传目录'
        ]));
    }
}

// 检测目录写入权限
if (!is_writable($uploadDir)) {
    exit(json_encode([
        'success' => false,
        'msg' => '上传目录无写入权限'
    ]));
}

// 检测文件上传状态
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorList = [
        1 => '文件超出服务器大小限制',
        2 => '文件超出表单大小限制',
        3 => '文件仅部分上传',
        4 => '未选择上传文件',
        6 => '临时目录不存在',
        7 => '文件写入失败'
    ];
    $errCode = $_FILES['file']['error'] ?? -1;
    $msg = isset($errorList[$errCode]) ? $errorList[$errCode] : '未知上传错误';
    exit(json_encode([
        'success' => false,
        'msg' => $msg
    ]));
}

// 获取文件信息
$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileType = $file['type'];
$tmpFileName = $file['tmp_name'];

// 校验文件大小
if ($fileSize > $maxSize) {
    exit(json_encode([
        'success' => false,
        'msg' => '文件过大，最大支持 2MB'
    ]));
}

// 校验文件类型
if (!in_array($fileType, $allowTypes)) {
    exit(json_encode([
        'success' => false,
        'msg' => '仅支持 JPG、PNG、GIF、WebP 图片'
    ]));
}

// 生成唯一文件名
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$uniqueFileName = uniqid('comment_', true) . '.' . $fileExt;
$targetFilePath = $uploadDir . $uniqueFileName;
$targetFileUrl = $uploadUrl . $uniqueFileName;

// 移动临时文件到正式目录
if (!move_uploaded_file($tmpFileName, $targetFilePath)) {
    exit(json_encode([
        'success' => false,
        'msg' => '文件保存失败，请检查目录权限'
    ]));
}

// 二次验证文件是否存在
if (!file_exists($targetFilePath)) {
    exit(json_encode([
        'success' => false,
        'msg' => '上传完成，但文件丢失'
    ]));
}

// 返回前端所需数据
exit(json_encode([
    'success' => true,
    'msg' => '上传成功',
    'url' => $targetFileUrl,
    'name' => $uniqueFileName,
    'size' => $fileSize
]));
?>
