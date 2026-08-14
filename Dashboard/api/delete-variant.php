<?php
session_start();
require_once __DIR__ . '/../../config/database-connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Clean up related data
    $pdo->prepare("DELETE FROM product_images WHERE variant_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_variant_attributes WHERE variant_id = ?")->execute([$id]);
    
    $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}