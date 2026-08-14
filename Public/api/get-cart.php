<?php
// Public/api/get-cart.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database-connection.php';

try {
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
        echo json_encode([
            'success' => true,
            'items'   => [],
            'count'   => 0,
            'total'   => 0
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT 
            ci.variant_id,
            ci.quantity,
            ci.price,
            (ci.quantity * ci.price) AS subtotal,
            pv.title AS variant_title,
            pv.sku,
            p.name AS product_name,
            (
                SELECT url FROM product_images 
                WHERE variant_id = pv.id AND is_primary = 1 
                LIMIT 1
            ) AS image_url
        FROM cart_items ci
        JOIN product_variants pv ON pv.id = ci.variant_id
        JOIN products p ON p.id = pv.product_id
        WHERE ci.cart_id = ?
        ORDER BY ci.id
    ");
    $stmt->execute([$cartId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $count = 0;
    foreach ($items as $item) {
        $total += $item['subtotal'];
        $count += $item['quantity'];
    }

    echo json_encode([
        'success' => true,
        'items'   => $items,
        'count'   => $count,
        'total'   => $total
    ]);

} catch (Exception $e) {
    error_log('get-cart error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not load cart']);
}