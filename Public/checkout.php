<?php
session_start();
require_once '../config/database-connection.php';

// ---------- Load cart items ----------
$cartItems = [];
$subtotal  = 0;
$cartId    = null;

try {
    if (!empty($_SESSION['user_id'])) {
        // Logged-in user cart
        $stmt = $pdo->prepare("
            SELECT 
                c.id AS cart_id,
                ci.id AS cart_item_id,
                ci.variant_id, 
                ci.quantity,
                pv.title AS variant_title, 
                pv.price, 
                pv.sku,
                pv.stock_quantity,
                p.id AS product_id,
                p.name AS product_name,
                (SELECT url FROM product_images 
                 WHERE variant_id = pv.id AND is_primary = 1 
                 LIMIT 1) AS image_url
            FROM carts c
            JOIN cart_items ci ON ci.cart_id = c.id
            JOIN product_variants pv ON pv.id = ci.variant_id
            JOIN products p ON p.id = pv.product_id
            WHERE c.user_id = ?
              AND pv.is_active = 1
            ORDER BY ci.id
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Guest cart (session_id)
        $sessionId = session_id();
        $stmt = $pdo->prepare("
            SELECT 
                c.id AS cart_id,
                ci.id AS cart_item_id,
                ci.variant_id, 
                ci.quantity,
                pv.title AS variant_title, 
                pv.price, 
                pv.sku,
                pv.stock_quantity,
                p.id AS product_id,
                p.name AS product_name,
                (SELECT url FROM product_images 
                 WHERE variant_id = pv.id AND is_primary = 1 
                 LIMIT 1) AS image_url
            FROM carts c
            JOIN cart_items ci ON ci.cart_id = c.id
            JOIN product_variants pv ON pv.id = ci.variant_id
            JOIN products p ON p.id = pv.product_id
            WHERE c.session_id = ?
              AND pv.is_active = 1
            ORDER BY ci.id
        ");
        $stmt->execute([$sessionId]);
    }

    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cartItems as &$item) {
        $item['line_total'] = $item['price'] * $item['quantity'];
        $subtotal += $item['line_total'];
        $cartId = $item['cart_id']; // keep for later clearing
    }
    unset($item);

} catch (Exception $e) {
    error_log('Checkout cart load error: ' . $e->getMessage());
    $cartItems = [];
}

$shipping = 0; // Free shipping for now
$total    = $subtotal + $shipping;

// Pre-fill user data if logged in
$userEmail = $_SESSION['email'] ?? '';
$userName  = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$userPhone = '';

if (!empty($_SESSION['user_id'])) {
    try {
        $u = $pdo->prepare("SELECT phone, email, first_name, last_name FROM users WHERE id = ?");
        $u->execute([$_SESSION['user_id']]);
        $row = $u->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userPhone = $row['phone'] ?? '';
            $userEmail = $row['email'] ?? $userEmail;
            $userName  = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $userName;
        }
    } catch (Exception $e) {}
}

// ---------- Handle form submission ----------
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {

    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $full_name  = trim($_POST['full_name'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $postal     = trim($_POST['postal_code'] ?? '');
    $payment    = trim($_POST['payment_method'] ?? '');

    // Basic validation
    if (empty($cartItems)) {
        $error = 'Your cart is empty.';
    } elseif (!$email || !$full_name || !$address || !$city || !$payment) {
        $error = 'Please fill in all required fields and select a payment method.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {

        // Build full shipping address
        $shippingAddress = $full_name . "\n" . $address . "\n" . $city;
        if ($postal) $shippingAddress .= "\n" . $postal;
        $shippingAddress .= "\nUganda";

        try {
            $pdo->beginTransaction();

            // Generate unique order number
            $orderNumber = 'ENO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            // ---------- Insert into orders ----------
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, customer_name, customer_email, customer_phone,
                    status, payment_method, payment_status,
                    subtotal, shipping_fee, total, currency,
                    shipping_address, notes
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    'Pending', ?, 'Pending',
                    ?, ?, ?, 'UGX',
                    ?, NULL
                )
            ");

            $stmt->execute([
                $orderNumber,
                $_SESSION['user_id'] ?? null,
                $full_name,
                $email,
                $phone ?: null,
                $payment,
                $subtotal,
                $shipping,
                $total,
                $shippingAddress
            ]);

            $orderId = $pdo->lastInsertId();

            // ---------- Insert order items + reduce stock ----------
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, variant_id,
                    product_name, sku, quantity,
                    unit_price, total_price, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");

            $stockStmt = $pdo->prepare("
                UPDATE product_variants 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ? AND stock_quantity >= ?
            ");

            foreach ($cartItems as $item) {
                // Safety check: enough stock?
                if ($item['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Sorry, \"{$item['product_name']}\" does not have enough stock.");
                }

                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'],
                    $item['product_name'],
                    $item['sku'],
                    $item['quantity'],
                    $item['price'],
                    $item['line_total']
                ]);

                // Reduce stock
                $stockStmt->execute([
                    $item['quantity'],
                    $item['variant_id'],
                    $item['quantity']
                ]);

                if ($stockStmt->rowCount() === 0) {
                    throw new Exception("Stock update failed for {$item['product_name']}");
                }
            }

            // ---------- Clear the cart ----------
            if ($cartId) {
                $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
                // Optional: also delete the empty cart row
                // $pdo->prepare("DELETE FROM carts WHERE id = ?")->execute([$cartId]);
            }

            $pdo->commit();

            // Success → redirect
            header('Location: order-success.php?order=' . urlencode($orderNumber));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Order creation failed: ' . $e->getMessage());
            $error = $e->getMessage() ?: 'Something went wrong while placing your order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Enono</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        .heading-font { font-family: 'Playfair Display', serif; }
        .body-font { font-family: 'Inter', system-ui, sans-serif; }

        .form-input {
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .left-panel {
            max-width: 520px;
        }

        .payment-btn.selected {
            border-color: #8B4513 !important;
            background-color: #f8f5f0 !important;
        }
    </style>
</head>
<body class="body-font text-gray-800 min-h-screen">

    <!-- Top Banner -->
    <div class="bg-[#8B4513] text-white text-center py-3 text-sm">
        Understand African Heritage and craft • 10% discount on your first order
    </div>

    <div class="flex min-h-[calc(100vh-48px)]">
        
        <!-- Left - Form -->
        <div class="flex-1 overflow-auto p-6 md:p-12 lg:p-16 xl:p-24">
            <div class="left-panel mx-auto">
                <a href="shopping.php" 
                   class="inline-flex items-center gap-2 text-gray-600 hover:text-[#8B4513] mb-10 text-base font-medium">
                    ← Return to store
                </a>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($cartItems)): ?>
                    <div class="text-center py-20">
                        <i class="fa-solid fa-cart-shopping text-5xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 mb-6">Your cart is empty</p>
                        <a href="shopping.php" class="inline-block bg-[#8B4513] text-white px-6 py-3 rounded-xl">
                            Continue Shopping
                        </a>
                    </div>
                <?php else: ?>

                <form method="POST" id="checkout-form">
                    <input type="hidden" name="complete_order" value="1">
                    <input type="hidden" name="payment_method" id="payment_method" value="">

                    <div class="space-y-12">
                        <!-- Contact -->
                        <div>
                            <h2 class="heading-font text-xl font-semibold mb-4">Contact</h2>
                            <div class="space-y-4">
                                <input type="email" name="email" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? $userEmail) ?>"
                                       placeholder="Email address" 
                                       class="form-input w-full border border-gray-300 rounded-lg px-4 py-2.5 outline-none text-base">
                                <input type="tel" name="phone"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $userPhone) ?>"
                                       placeholder="Contact number" 
                                       class="form-input w-full border border-gray-300 rounded-lg px-4 py-2.5 outline-none text-base">
                            </div>
                        </div>

                        <!-- Delivery -->
                        <div>
                            <h2 class="heading-font text-xl font-semibold mb-4">Delivery</h2>
                            <div class="space-y-4">
                                <input type="text" name="full_name" required
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? $userName) ?>"
                                       placeholder="Full name" 
                                       class="form-input w-full border border-gray-300 rounded-lg px-4 py-2.5 outline-none">
                                
                                <select name="country" class="form-input w-full border border-gray-300 rounded-lg px-4 py-2.5 outline-none bg-white">
                                    <option value="UG">Uganda</option>
                                </select>
                                
                                <input type="text" name="address" required
                                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                       placeholder="Address" 
                                       class="form-input w-full border border-gray-300 rounded-lg px-4 py-2.5 outline-none">
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <input type="text" name="city" required
                                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                           placeholder="City" 
                                           class="form-input border border-gray-300 rounded-lg px-4 py-2.5 outline-none">
                                    <input type="text" name="postal_code"
                                           value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>"
                                           placeholder="Postal code (optional)" 
                                           class="form-input border border-gray-300 rounded-lg px-4 py-2.5 outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Payment -->
                        <div>
                            <h2 class="heading-font text-xl font-semibold mb-2">Payment</h2>
                            <p class="text-gray-600 mb-4 text-sm">Choose payment method</p>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <button type="button" onclick="selectPayment(this, 'mtn')" 
                                        class="payment-btn border-2 border-transparent hover:border-[#8B4513] bg-white p-5 rounded-2xl flex flex-col items-center gap-2 transition-all">
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRzmowaypHadzjiejbyAMF-bK8F1ABsHG5vN-QPjFiD5Q&s" 
                                         class="h-10 object-contain" alt="MTN">
                                    <span class="text-xs font-medium">MTN MoMo</span>
                                </button>

                                <button type="button" onclick="selectPayment(this, 'airtel')" 
                                        class="payment-btn border-2 border-transparent hover:border-[#8B4513] bg-white p-5 rounded-2xl flex flex-col items-center gap-2 transition-all">
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQuYhGSJ4PKawoeO0WWHzoKp3fi5VBxiyZDIjvO43t_Dw&s=10" 
                                         class="h-10 object-contain" alt="Airtel">
                                    <span class="text-xs font-medium">Airtel Money</span>
                                </button>

                                <button type="button" onclick="selectPayment(this, 'bank')" 
                                        class="payment-btn border-2 border-transparent hover:border-[#8B4513] bg-white p-5 rounded-2xl flex flex-col items-center gap-2 transition-all">
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSFi-6ddHxd0--NAK0O1mnkboFueeozBHzDpexhP2VS1A&s=10" 
                                         class="h-10 object-contain" alt="Bank">
                                    <span class="text-xs font-medium">Bank Transfer</span>
                                </button>
                            </div>
                        </div>

                        <!-- Mobile order summary -->
                        <div class="md:hidden">
                            <div class="bg-[#faf9f6] rounded-2xl p-6 mb-6">
                                <h3 class="font-semibold mb-4">Order Summary</h3>
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="flex justify-between text-sm mb-2">
                                        <span><?= htmlspecialchars($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                                        <span>UGX <?= number_format($item['line_total']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="border-t pt-3 mt-3 flex justify-between font-semibold">
                                    <span>Total</span>
                                    <span class="text-[#8B4513]">UGX <?= number_format($total) ?></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full bg-[#8B4513] hover:bg-[#6B3410] text-white py-3.5 rounded-xl font-semibold text-base transition">
                            Complete Order
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right - Order Summary (Desktop) -->
        <div class="hidden md:flex w-full md:w-2/5 lg:w-2/5 bg-[#faf9f6] border-l border-gray-200 items-start">
            <div class="sticky top-0 w-full h-screen p-8 lg:p-12 overflow-auto">
                <div class="max-w-md mx-auto">
                    <h2 class="heading-font text-xl font-semibold mb-8">Order Summary</h2>
                    
                    <?php if (empty($cartItems)): ?>
                        <p class="text-gray-500">Your cart is empty</p>
                    <?php else: ?>
                        <div class="space-y-6 mb-10">
                            <?php foreach ($cartItems as $item): 
                                $img = $item['image_url'] ?? '';
                                if ($img && !str_starts_with($img, 'http')) {
                                    $img = '../' . ltrim($img, '/');
                                }
                                if (!$img) $img = '../images/drum_logo.jpg';
                            ?>
                                <div class="flex gap-4">
                                    <img src="<?= htmlspecialchars($img) ?>" 
                                         class="w-[50px] h-[50px] object-cover rounded-lg flex-shrink-0"
                                         onerror="this.src='../images/drum_logo.jpg'" alt="">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between gap-2">
                                            <p class="font-medium leading-tight text-sm">
                                                <?= htmlspecialchars($item['product_name']) ?>
                                                <?php if ($item['quantity'] > 1): ?>
                                                    <span class="text-gray-400">× <?= $item['quantity'] ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="font-medium whitespace-nowrap text-[#9b4925]">
                                                UGX <?= number_format($item['line_total']) ?>
                                            </p>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            <?= htmlspecialchars($item['variant_title'] ?? '') ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Totals -->
                        <div class="border-t pt-6 space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium">UGX <?= number_format($subtotal) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Shipping</span>
                                <span class="text-green-600">Free</span>
                            </div>
                            <div class="flex justify-between text-xl font-semibold pt-4 border-t">
                                <span>Total</span>
                                <span class="text-[#9b4925]">UGX <?= number_format($total) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="text-center text-xs text-gray-500 mt-10">
                        Secure checkout powered by Enono
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectPayment(btn, method) {
            document.querySelectorAll('.payment-btn').forEach(b => {
                b.classList.remove('selected');
            });
            btn.classList.add('selected');
            document.getElementById('payment_method').value = method;
        }

        // Prevent submit without payment method
        document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
            if (!document.getElementById('payment_method').value) {
                e.preventDefault();
                alert('Please select a payment method');
            }
        });
    </script>
</body>
</html>