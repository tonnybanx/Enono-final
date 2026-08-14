<?php
// Public/api/add-to-cart.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database-connection.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $variantId = (int)($input['variant_id'] ?? 0);
    $quantity  = max(1, (int)($input['quantity'] ?? 1));

    if ($variantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product']);
        exit;
    }

    // Get variant + current price + stock
    $stmt = $pdo->prepare("
        SELECT id, product_id, price, stock_quantity, is_active 
        FROM product_variants 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$variantId]);
    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        echo json_encode(['success' => false, 'message' => 'Product not available']);
        exit;
    }

    if ($variant['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock']);
        exit;
    }

    $pdo->beginTransaction();

    // ---------- Find or create cart ----------
    $cartId = null;

    if (!empty($_SESSION['user_id'])) {
        // Logged-in user
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cart) {
            $cartId = $cart['id'];
        } else {
            $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$_SESSION['user_id']]);
            $cartId = $pdo->lastInsertId();
        }
    } else {
        // Guest – use session_id
        $sessionId = session_id();
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE session_id = ? LIMIT 1");
        $stmt->execute([$sessionId]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cart) {
            $cartId = $cart['id'];
        } else {
            $pdo->prepare("INSERT INTO carts (session_id) VALUES (?)")->execute([$sessionId]);
            $cartId = $pdo->lastInsertId();
        }
    }

    // ---------- Add or update cart item ----------
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?");
    $stmt->execute([$cartId, $variantId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = $existing['quantity'] + $quantity;

        if ($newQty > $variant['stock_quantity']) {
            throw new Exception('Not enough stock for the requested quantity');
        }

        $pdo->prepare("
            UPDATE cart_items 
            SET quantity = ?, price = ?, updated_at = NOW() 
            WHERE id = ?
        ")->execute([$newQty, $variant['price'], $existing['id']]);
    } else {
        $pdo->prepare("
            INSERT INTO cart_items (cart_id, variant_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ")->execute([$cartId, $variantId, $quantity, $variant['price']]);
    }

    // Touch cart updated_at
    $pdo->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

    $pdo->commit();

    // Return new cart count
    $countStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
    $countStmt->execute([$cartId]);
    $cartCount = (int)$countStmt->fetchColumn();

    echo json_encode([
        'success'    => true,
        'message'    => 'Added to cart',
        'cart_count' => $cartCount
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('add-to-cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Could not add to cart']);
}