<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database-connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$type         = trim($_POST['type'] ?? '');
$displayValue = trim($_POST['display_value'] ?? '');
$value        = trim($_POST['value'] ?? '');

if (!in_array($type, ['color', 'size'], true) || $displayValue === '') {
    echo json_encode(['success' => false, 'message' => 'Type and display name are required']);
    exit;
}

if ($value === '') {
    $value = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $displayValue));
    $value = trim($value, '-');
}

try {
    $typeStmt = $pdo->prepare("SELECT id FROM attribute_types WHERE name = ? LIMIT 1");
    $typeStmt->execute([$type]);
    $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$typeRow) {
        echo json_encode(['success' => false, 'message' => 'Attribute type not found']);
        exit;
    }
    $typeId = (int)$typeRow['id'];

    $dup = $pdo->prepare("
        SELECT id FROM attribute_values
        WHERE attribute_type_id = ? AND (value = ? OR display_value = ?)
        LIMIT 1
    ");
    $dup->execute([$typeId, $value, $displayValue]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This value already exists']);
        exit;
    }

    $orderStmt = $pdo->prepare("
        SELECT COALESCE(MAX(sort_order), 0) + 1 FROM attribute_values WHERE attribute_type_id = ?
    ");
    $orderStmt->execute([$typeId]);
    $nextOrder = (int)$orderStmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO attribute_values (attribute_type_id, value, display_value, sort_order)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$typeId, $value, $displayValue, $nextOrder]);
    $newId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success'       => true,
        'id'            => $newId,
        'type'          => $type,
        'display_value' => $displayValue,
        'value'         => $value
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}