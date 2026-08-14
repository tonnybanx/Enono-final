<?php
session_start();
require_once __DIR__ . '/../../config/database-connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../products.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: ../products.php');
    exit;
}

$name              = trim($_POST['name'] ?? '');
$category_id       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$spirit_id         = !empty($_POST['spirit_id']) ? (int)$_POST['spirit_id'] : null;
$base_price        = (float)($_POST['base_price'] ?? 0);
$short_description = trim($_POST['short_description'] ?? '');
$long_description  = trim($_POST['long_description'] ?? '');
$is_active         = isset($_POST['is_active']) ? 1 : 0;
$is_featured       = isset($_POST['is_featured']) ? 1 : 0;

try {
    $stmt = $pdo->prepare("
        UPDATE products SET
            name = ?,
            category_id = ?,
            spirit_id = ?,
            base_price = ?,
            short_description = ?,
            long_description = ?,
            is_active = ?,
            is_featured = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $category_id,
        $spirit_id,
        $base_price,
        $short_description,
        $long_description,
        $is_active,
        $is_featured,
        $id
    ]);

    header("Location: ../view-product.php?id=$id&updated=1");
    exit;
} catch (Exception $e) {
    die("Error updating product: " . $e->getMessage());
}