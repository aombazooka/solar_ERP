<?php
/** view_image.php — ให้บริการไฟล์ภาพที่มีการป้องกันการเข้าถึง (เช่น รูปเช็คอินหน้างาน) */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::require();

$file = $_GET['file'] ?? '';

// ป้องกัน Directory Traversal
if (!$file || strpos($file, '..') !== false || strpos($file, 'storage/uploads/') !== 0) {
    http_response_code(403);
    exit('Access denied or invalid file path.');
}

$absolutePath = __DIR__ . '/' . $file;

if (!file_exists($absolutePath) || !is_file($absolutePath)) {
    http_response_code(404);
    exit('File not found.');
}

// ตรวจสอบนามสกุลไฟล์ที่อนุญาต
$ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
$allowedTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp'
];

if (!isset($allowedTypes[$ext])) {
    http_response_code(403);
    exit('Invalid file type.');
}

// ล้าง output buffer ก่อนส่งไฟล์
if (ob_get_length()) ob_clean();

header('Content-Type: ' . $allowedTypes[$ext]);
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=86400'); // Cache 1 day for authenticated users
readfile($absolutePath);
exit;
