<?php
// Dashboard/api/update-spirit.php
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

$id                = (int)($_POST['id'] ?? 0);
$name              = trim($_POST['name'] ?? '');
$short_description = trim($_POST['short_description'] ?? '');
$full_description  = trim($_POST['full_description'] ?? '');
$sort_order        = (int)($_POST['sort_order'] ?? 0);
$is_active         = (int)($_POST['is_active'] ?? 1);

if ($id <= 0 || $name === '' || $full_description === '') {
    jsonResponse(false, 'Invalid data', 422);
}

try {
    // Handle optional new image
    $imageSql = '';
    $params = [$name, $short_description ?: null, $full_description, $is_active, $sort_order];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            jsonResponse(false, 'Invalid image type', 422);
        }

        $uploadDir = __DIR__ . '/../../uploads/spirits/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'spirit_' . $id . '_' . time() . '.' . $ext;
        $target = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            jsonResponse(false, 'Failed to save image', 500);
        }

        $imageSql = ', image_url = ?';
        $params[] = 'uploads/spirits/' . $filename;
    }

    $params[] = $id;

    $sql = "
        UPDATE spirits 
        SET name = ?, short_description = ?, full_description = ?, 
            is_active = ?, sort_order = ?, updated_at = NOW()
            $imageSql
        WHERE id = ?
    ";
    $pdo->prepare($sql)->execute($params);

    jsonResponse(true, 'Spirit updated successfully');

} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Database error', 500);
}