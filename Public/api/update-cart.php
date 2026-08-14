<?php
// Public/api/update-cart.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database-connection.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $variantId = (int)($input['variant_id'] ?? 0);
    $quantity  = (int)($input['quantity'] ?? 0);

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

    if ($quantity < 1) {
        // Remove item
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?")
            ->execute([$cartId, $variantId]);
    } else {
        // Check stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM product_variants WHERE id = ?");
        $stmt->execute([$variantId]);
        $stock = (int)$stmt->fetchColumn();

        if ($quantity > $stock) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock']);
            exit;
        }

        $pdo->prepare("
            UPDATE cart_items 
            SET quantity = ?, updated_at = NOW() 
            WHERE cart_id = ? AND variant_id = ?
        ")->execute([$quantity, $cartId, $variantId]);
    }

    // Touch cart
    $pdo->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('update-cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not update cart']);
}