<?php
session_start();

// Catch any unexpected fatal errors and show a friendly message
set_exception_handler(function ($e) {
    error_log($e->getMessage());
    // You can also redirect to an error page if you prefer
    die('<div style="font-family:sans-serif;max-width:500px;margin:80px auto;padding:30px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:12px;">
            <h2>Something went wrong</h2>
            <p>We encountered an unexpected error. Please try again later or contact support.</p>
         </div>');
});

require_once '../config/database-connection.php';
require_once '../config/mail.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error   = '';
$success = '';

if (isset($_SESSION['verified_email'])) {
    header('Location: complete-signup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && (int)$existing['email_verified'] === 1) {
                $error = 'This email is already registered. Please <a href="signin.php" class="underline font-medium">sign in</a>.';
            } else {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                $_SESSION['pending_email']   = $email;
                $_SESSION['pending_token']   = $token;
                $_SESSION['pending_expires'] = $expires;

                // Build verification link
                $protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $verify_link = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/verify-email.php?token=' . $token;

                $subject = 'Verify your email - enono MARKET';
                $body = '
                    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                        <h2 style="color:#8B4513;">Welcome to enono MARKET</h2>
                        <p>Hello,</p>
                        <p>You requested to create an account with <strong>enono MARKET</strong>.</p>
                        <p>Please click the button below to verify your email address. This link will expire in 30 minutes.</p>
                        <p style="margin:30px 0;">
                            <a href="' . $verify_link . '"
                               style="background-color:#8B4513;color:#ffffff;padding:12px 28px;
                                      text-decoration:none;border-radius:8px;font-weight:bold;display:inline-block;">
                                Verify Email Address
                            </a>
                        </p>
                        <p style="color:#666;font-size:14px;">
                            Or copy and paste this link into your browser:<br>
                            <a href="' . $verify_link . '">' . $verify_link . '</a>
                        </p>
                        <hr style="border:none;border-top:1px solid #eee;margin:30px 0;">
                        <p style="color:#999;font-size:12px;">
                            If you did not request this, you can safely ignore this email.
                        </p>
                    </div>';

                $result = sendMail($email, '', $subject, $body, true);

                if ($result['success']) {
                    $success = 'We have sent a verification link to <strong>' . htmlspecialchars($email) . '</strong>. 
                                Please check your inbox (and spam folder) and click the link to continue.';
                } else {
                    $error = $result['message']; // already user-friendly
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Database error in signup.php: ' . $e->getMessage());
        $error = 'A database error occurred. Please try again later.';
    } catch (Exception $e) {
        error_log('General error in signup.php: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - enono MARKET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap');
        .heading-font { font-family: 'Playfair Display', sans-serif; }
    </style>
</head>
<body class="bg-white min-h-screen flex items-center justify-center py-12">
    <div class="max-w-md w-full px-6">

        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center gap-3">
                <img src="../images/drum_logo.jpg" alt="" class="h-14 w-12">
                <div>
                    <div class="heading-font text-5xl font-bold tracking-tighter text-black">Enono</div>
                    <div class="pl-2 text-sm tracking-widest text-amber-600 -mt-1">MARKET</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border border-gray-100 p-8">
            <h1 class="text-2xl font-semibold mb-6">Create account</h1>

            <?php if ($error): ?>
                <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-5 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    <?= $success ?>
                </div>
            <?php else: ?>

            <form method="POST" action="">
                <div class="mb-8">
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Enter your email address"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-amber-400">
                </div>

                <button type="submit"
                        class="w-full bg-[#8B4513] hover:bg-amber-600 text-white font-semibold py-3.5 rounded-xl text-lg transition-colors">
                    Continue
                </button>
            </form>

            <?php endif; ?>

            <div class="mt-8 text-center">
                <p class="text-sm">
                    Already have an account?
                    <a href="signin.php" class="text-amber-600 hover:underline">Sign in instead</a>
                </p>
            </div>

            <div class="mt-8 text-[13px] text-gray-500 text-center">
                By creating an account, you agree to enono MARKET's<br>
                <a href="#" class="text-amber-600 hover:underline">Conditions of Use</a> and
                <a href="#" class="text-amber-600 hover:underline">Privacy Notice</a>
            </div>
        </div>

        <div class="text-center mt-10 text-xs text-gray-500">
            <div class="flex justify-center gap-6 mb-4">
                <a href="#" class="hover:text-gray-700">Conditions of Use</a>
                <a href="#" class="hover:text-gray-700">Privacy Notice</a>
                <a href="help.php" class="hover:text-gray-700">Help</a>
            </div>
            <p>© 2026 enono MARKET. All Rights Reserved.</p>
        </div>
    </div>
</body>
</html>