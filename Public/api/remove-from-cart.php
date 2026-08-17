<?php
// Public/api/remove-from-cart.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database-connection.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $variantId = (int)($input['variant_id'] ?? 0);

    if ($variantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item']);
        exit;
    }

    // Find cart
    $cartId = null;
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartId = $cart['id'] ?? null;
    } else {
        $sessionId = session_id();
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_id = ? LIMIT 1");
        $stmt->execute([$sessionId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartId = $cart['id'] ?? null;
    }

    if (!$cartId) {
        echo json_encode(['success' => false, 'message' => 'Cart not found']);
        exit;
    }

    // Remove the item
    $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?")
        ->execute([$cartId, $variantId]);

    // Touch cart
    $pdo->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

    // Return updated cart count
    $countStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
    $countStmt->execute([$cartId]);
    $cartCount = (int)$countStmt->fetchColumn();

    echo json_encode([
        'success'    => true,
        'cart_count' => $cartCount
    ]);

} catch (Exception $e) {
    error_log('remove-from-cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not remove item']);
}