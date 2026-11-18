<?php
session_start();
require_once '../config/database-connection.php';
require_once '../config/payment.php';   // Payment methods + processPayment()

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
                pv.image_url AS variant_image,
                p.id AS product_id,
                p.name AS product_name,
                COALESCE(
                    (SELECT url FROM product_images 
                     WHERE variant_id = pv.id AND is_primary = 1 
                     LIMIT 1),
                    (SELECT url FROM product_images 
                     WHERE product_id = p.id AND is_primary = 1 
                     LIMIT 1),
                    pv.image_url
                ) AS image_url
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
                pv.image_url AS variant_image,
                p.id AS product_id,
                p.name AS product_name,
                COALESCE(
                    (SELECT url FROM product_images 
                     WHERE variant_id = pv.id AND is_primary = 1 
                     LIMIT 1),
                    (SELECT url FROM product_images 
                     WHERE product_id = p.id AND is_primary = 1 
                     LIMIT 1),
                    pv.image_url
                ) AS image_url
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

// ---------- Helper: normalise image path for the Public/ folder ----------
function checkoutImageUrl(?string $url): string
{
    if (empty($url)) {
        return '../images/drum_logo.jpg';
    }
    // Absolute / external URL
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
        return $url;
    }
    // Relative path stored in DB (e.g. images/pipe5.jpg)
    return '../' . ltrim($url, '/');
}

// ---------- Handle form submission ----------
$error   = '';
$success = false;
$orderNumberSuccess = '';
$orderTotalSuccess  = 0;
$paymentMethodLabel = '';

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
    } elseif (!$email || !$full_name || !$address || !$city) {
        $error = 'Please fill in all required fields.';
    } elseif (!$payment || !isset($PAYMENT_METHODS[$payment]) || empty($PAYMENT_METHODS[$payment]['enabled'])) {
        $error = 'Please select a valid payment method before completing your order.';
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

            // ---------- Process payment (simulated or real) ----------
            $paymentResult = processPayment([
                'order_number'   => $orderNumber,
                'order_id'       => $orderId,
                'payment_method' => $payment,
                'total'          => $total,
                'customer_phone' => $phone,
                'customer_email' => $email,
                'customer_name'  => $full_name,
            ]);

            if (!$paymentResult['success']) {
                throw new Exception($paymentResult['message'] ?: 'Payment failed. Please try again.');
            }

            // Update payment status to Paid when simulation / real gateway succeeds
            $pdo->prepare("
                UPDATE orders 
                SET payment_status = 'Paid',
                    notes = CONCAT(COALESCE(notes, ''), ' | TX: ', ?)
                WHERE id = ?
            ")->execute([
                $paymentResult['transaction_id'] ?? 'N/A',
                $orderId
            ]);

            // ---------- Clear the cart ----------
            if ($cartId) {
                $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
            }

            $pdo->commit();

            // Success → show overlay (no redirect)
            $success = true;
            $orderNumberSuccess = $orderNumber;
            $orderTotalSuccess  = $total;
            $paymentMethodLabel = $PAYMENT_METHODS[$payment]['label'] ?? $payment;

            // Empty cart display after success
            $cartItems = [];
            $subtotal  = 0;
            $total     = 0;

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
            background-color: #141414;
            border-color: #2A2A2A;
            color: #ffffff;
        }
        .form-input::placeholder {
            color: #6b7280;
        }
        .form-input:focus {
            border-color: #E8B923;
            box-shadow: 0 0 0 3px rgba(232, 185, 35, 0.15);
            outline: none;
        }

        .left-panel {
            max-width: 520px;
        }

        .payment-btn {
            background-color: #141414;
            border: 2px solid #2A2A2A;
            color: #e5e7eb;
        }
        .payment-btn:hover {
            border-color: #D4A017;
        }
        .payment-btn.selected {
            border-color: #E8B923 !important;
            background-color: #1C1C1C !important;
            box-shadow: 0 0 0 2px rgba(232, 185, 35, 0.25);
        }

        #complete-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        /* Success overlay */
        #success-overlay {
            animation: fadeIn 0.25s ease-out;
        }
        #success-overlay .modal-card {
            animation: scaleIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.92) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body class="body-font text-gray-200 min-h-screen bg-[#0B0B0B]">

    <!-- Top Banner -->
    <div class="bg-[#141414] border-b border-[#2A2A2A] text-[#D4A017] text-center py-3 text-sm">
        Understand African Heritage and craft • 10% discount on your first order
    </div>

    <div class="flex min-h-[calc(100vh-48px)]">
        
        <!-- Left - Form -->
        <div class="flex-1 overflow-auto p-6 md:p-12 lg:p-16 xl:p-24">
            <div class="left-panel mx-auto">
                <a href="shopping.php" 
                   class="inline-flex items-center gap-2 text-gray-400 hover:text-[#D4A017] mb-10 text-base font-medium transition-colors">
                    ← Return to store
                </a>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-900/30 border border-red-700/50 text-red-300 rounded-xl text-sm">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($cartItems) && !$success): ?>
                    <div class="text-center py-20">
                        <i class="fa-solid fa-cart-shopping text-5xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400 mb-6">Your cart is empty</p>
                        <a href="shopping.php" class="inline-block bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] px-6 py-3 rounded-xl font-medium transition-colors">
                            Continue Shopping
                        </a>
                    </div>
                <?php elseif (!$success): ?>

                <form method="POST" id="checkout-form">
                    <input type="hidden" name="complete_order" value="1">
                    <input type="hidden" name="payment_method" id="payment_method" value="<?= htmlspecialchars($_POST['payment_method'] ?? '') ?>">

                    <div class="space-y-12">
                        <!-- Contact -->
                        <div>
                            <h2 class="heading-font text-xl font-semibold mb-4 text-white">Contact</h2>
                            <div class="space-y-4">
                                <input type="email" name="email" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? $userEmail) ?>"
                                       placeholder="Email address" 
                                       class="form-input w-full border rounded-lg px-4 py-2.5 text-base">
                                <input type="tel" name="phone"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $userPhone) ?>"
                                       placeholder="Contact number" 
                                       class="form-input w-full border rounded-lg px-4 py-2.5 text-base">
                            </div>
                        </div>

                        <!-- Delivery -->
                        <div>
                            <h2 class="heading-font text-xl font-semibold mb-4 text-white">Delivery</h2>
                            <div class="space-y-4">
                                <input type="text" name="full_name" required
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? $userName) ?>"
                                       placeholder="Full name" 
                                       class="form-input w-full border rounded-lg px-4 py-2.5">
                                
                                <select name="country" class="form-input w-full border rounded-lg px-4 py-2.5">
                                    <option value="UG">Uganda</option>
                                </select>
                                
                                <input type="text" name="address" required
                                       value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                       placeholder="Address" 
                                       class="form-input w-full border rounded-lg px-4 py-2.5">
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <input type="text" name="city" required
                                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                           placeholder="City" 
                                           class="form-input border rounded-lg px-4 py-2.5">
                                    <input type="text" name="postal_code"
                                           value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>"
                                           placeholder="Postal code (optional)" 
                                           class="form-input border rounded-lg px-4 py-2.5">
                                </div>
                            </div>
                        </div>

                        <!-- Payment -->
                        <div>
                            <h2 class="heading-font text-xl font-semibold mb-2 text-white">Payment</h2>
                            <p class="text-gray-400 mb-4 text-sm">
                                Choose a payment method <span class="text-red-400">*</span>
                                <span id="payment-hint" class="text-red-400 hidden"> — please select one</span>
                            </p>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <?php foreach ($PAYMENT_METHODS as $key => $method): ?>
                                    <?php if (empty($method['enabled'])) continue; ?>
                                    <button type="button"
                                            onclick="selectPayment(this, '<?= htmlspecialchars($key) ?>')"
                                            data-method="<?= htmlspecialchars($key) ?>"
                                            class="payment-btn p-5 rounded-2xl flex flex-col items-center gap-2 transition-all
                                                   <?= (($_POST['payment_method'] ?? '') === $key) ? 'selected' : '' ?>">
                                        <img src="<?= htmlspecialchars($method['icon']) ?>"
                                             class="h-10 object-contain" alt="<?= htmlspecialchars($method['label']) ?>">
                                        <span class="text-xs font-medium"><?= htmlspecialchars($method['label']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Mobile order summary -->
                        <div class="md:hidden">
                            <div class="bg-[#141414] border border-[#2A2A2A] rounded-2xl p-6 mb-6">
                                <h3 class="font-semibold mb-4 text-white">Order Summary</h3>
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="flex justify-between text-sm mb-2 text-gray-300">
                                        <span><?= htmlspecialchars($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                                        <span>UGX <?= number_format($item['line_total']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="border-t border-[#2A2A2A] pt-3 mt-3 flex justify-between font-semibold text-white">
                                    <span>Total</span>
                                    <span class="text-[#D4A017]">UGX <?= number_format($total) ?></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="complete-btn"
                                class="w-full bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] py-3.5 rounded-xl font-semibold text-base transition-colors"
                                <?= empty($_POST['payment_method'] ?? '') ? 'disabled' : '' ?>>
                            Complete Order
                        </button>
                        <p class="text-center text-xs text-gray-500 mt-2">
                            You must select a payment method to continue
                        </p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right - Order Summary (Desktop) -->
        <div class="hidden md:flex w-full md:w-2/5 lg:w-2/5 bg-[#141414] border-l border-[#2A2A2A] items-start">
            <div class="sticky top-0 w-full h-screen p-8 lg:p-12 overflow-auto">
                <div class="max-w-md mx-auto">
                    <h2 class="heading-font text-xl font-semibold mb-8 text-white">Order Summary</h2>
                    
                    <?php if (empty($cartItems)): ?>
                        <p class="text-gray-400"><?= $success ? 'Order placed successfully' : 'Your cart is empty' ?></p>
                    <?php else: ?>
                        <div class="space-y-6 mb-10">
                            <?php foreach ($cartItems as $item): 
                                $img = checkoutImageUrl($item['image_url'] ?? null);
                            ?>
                                <div class="flex gap-4">
                                    <img src="<?= htmlspecialchars($img) ?>" 
                                         class="w-[50px] h-[50px] object-cover rounded-lg flex-shrink-0 border border-[#2A2A2A]"
                                         onerror="this.onerror=null; this.src='../images/drum_logo.jpg';"
                                         alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between gap-2">
                                            <p class="font-medium leading-tight text-sm text-white">
                                                <?= htmlspecialchars($item['product_name']) ?>
                                                <?php if ($item['quantity'] > 1): ?>
                                                    <span class="text-gray-500">× <?= $item['quantity'] ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="font-medium whitespace-nowrap text-[#D4A017]">
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
                        <div class="border-t border-[#2A2A2A] pt-6 space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Subtotal</span>
                                <span class="font-medium text-white">UGX <?= number_format($subtotal) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Shipping</span>
                                <span class="text-green-500">Free</span>
                            </div>
                            <div class="flex justify-between text-xl font-semibold pt-4 border-t border-[#2A2A2A]">
                                <span class="text-white">Total</span>
                                <span class="text-[#D4A017]">UGX <?= number_format($total) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="text-center text-xs text-gray-500 mt-10">
                        Secure checkout powered by Enono
                        <?php if (defined('PAYMENT_MODE') && PAYMENT_MODE === 'simulated'): ?>
                            <br><span class="text-[#D4A017]">(Payment simulation mode)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== SUCCESS OVERLAY ========== -->
    <?php if ($success): ?>
    <div id="success-overlay" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="modal-card bg-[#141414] border border-[#2A2A2A] rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
            <!-- Header strip -->
            <div class="bg-[#E8B923] px-6 py-5 text-center">
                <div class="mx-auto w-16 h-16 rounded-full bg-[#0B0B0B]/20 flex items-center justify-center mb-3">
                    <i class="fa-solid fa-check text-3xl text-[#0B0B0B]"></i>
                </div>
                <h2 class="heading-font text-2xl font-bold text-[#0B0B0B]">Order Confirmed!</h2>
                <p class="text-[#0B0B0B]/80 text-sm mt-1">Thank you for shopping with Enono</p>
            </div>

            <div class="px-6 py-6 space-y-4">
                <div class="bg-[#1C1C1C] border border-[#2A2A2A] rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Order Number</p>
                    <p class="font-semibold text-lg text-[#E8B923] tracking-wide">
                        <?= htmlspecialchars($orderNumberSuccess) ?>
                    </p>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Total paid</span>
                    <span class="font-semibold text-white">UGX <?= number_format($orderTotalSuccess) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Payment method</span>
                    <span class="font-medium text-white"><?= htmlspecialchars($paymentMethodLabel) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Status</span>
                    <span class="text-green-500 font-medium">Paid · Pending dispatch</span>
                </div>

                <p class="text-xs text-gray-500 text-center pt-2">
                    A confirmation has been recorded. You can track this order from your account or contact support with the order number above.
                </p>
            </div>

            <div class="px-6 pb-6 flex flex-col gap-3">
                <a href="shopping.php"
                   class="w-full text-center bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] py-3 rounded-xl font-semibold transition-colors">
                    Continue Shopping
                </a>
                <a href="orders.php"
                   class="w-full text-center border border-[#2A2A2A] hover:border-[#D4A017] text-gray-300 hover:text-[#D4A017] py-3 rounded-xl font-medium transition-colors">
                    View My Orders
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function selectPayment(btn, method) {
            document.querySelectorAll('.payment-btn').forEach(b => {
                b.classList.remove('selected');
            });
            btn.classList.add('selected');
            document.getElementById('payment_method').value = method;

            // Enable the submit button and hide hint
            const completeBtn = document.getElementById('complete-btn');
            if (completeBtn) completeBtn.disabled = false;
            const hint = document.getElementById('payment-hint');
            if (hint) hint.classList.add('hidden');
        }

        // On load: if a method was previously selected (e.g. after validation error), keep button enabled
        document.addEventListener('DOMContentLoaded', function () {
            const selected = document.querySelector('.payment-btn.selected');
            const completeBtn = document.getElementById('complete-btn');
            if (selected && completeBtn) {
                completeBtn.disabled = false;
            }
        });

        // Prevent submit without payment method
        document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
            const method = document.getElementById('payment_method').value;
            if (!method) {
                e.preventDefault();
                const hint = document.getElementById('payment-hint');
                if (hint) hint.classList.remove('hidden');
                alert('Please select a payment method before completing your order.');
                return false;
            }
        });
    </script>
</body>
</html>