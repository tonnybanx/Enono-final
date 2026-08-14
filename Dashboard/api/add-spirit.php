<?php
// Dashboard/api/add-spirit.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database-connection.php';

function jsonResponse($success, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', 405);
}

$name              = trim($_POST['name'] ?? '');
$short_description = trim($_POST['short_description'] ?? '');
$full_description  = trim($_POST['full_description'] ?? '');
$sort_order        = (int)($_POST['sort_order'] ?? 0);
$is_active         = (int)($_POST['is_active'] ?? 1);

if ($name === '' || $full_description === '') {
    jsonResponse(false, 'Name and full description are required', 422);
}

try {
    $imageUrl = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            jsonResponse(false, 'Invalid image type', 422);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            jsonResponse(false, 'Image too large (max 5MB)', 422);
        }

        $uploadDir = __DIR__ . '/../../uploads/spirits/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'spirit_' . time() . '.' . $ext;
        $target = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            jsonResponse(false, 'Failed to save image', 500);
        }
        $imageUrl = 'uploads/spirits/' . $filename;
    }

    $stmt = $pdo->prepare("
        INSERT INTO spirits (name, short_description, full_description, image_url, is_active, sort_order, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $short_description ?: null, $full_description, $imageUrl, $is_active, $sort_order]);

    jsonResponse(true, 'Spirit added successfully');

} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error', 500);
}