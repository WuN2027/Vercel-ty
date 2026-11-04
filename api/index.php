<?php
$file = __DIR__ . '/..' . $_SERVER["PHP_SELF"];
if (file_exists($file)) {
    return false; // 对于存在的静态文件（如图片、CSS），直接放行
} else {
    require_once __DIR__ . '/../index.php'; // 其余请求全部交给Typecho主入口处理
}
