<?php
session_start();
require_once '../config/database-connection.php';

$token = $_GET['token'] ?? '';

// No token → go back to signup
if (empty($token)) {
    header('Location: signup.php');
    exit;
}

// Check if this token matches the one stored in the session
if (
    !isset($_SESSION['pending_token']) ||
    !isset($_SESSION['pending_email']) ||
    $_SESSION['pending_token'] !== $token
) {
    $message = 'Invalid or expired verification link.';
    $success = false;
} elseif (strtotime($_SESSION['pending_expires']) < time()) {
    $message = 'This verification link has expired. Please try again.';
    $success = false;
    unset($_SESSION['pending_email'], $_SESSION['pending_token'], $_SESSION['pending_expires']);
} else {
    // Email is now verified for this session
    $_SESSION['verified_email'] = $_SESSION['pending_email'];
    unset($_SESSION['pending_token'], $_SESSION['pending_expires']);

    // Go to the next step (name + password)
    header('Location: complete-signup.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - enono MARKET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');
        .heading-font { font-family: 'Playfair Display', sans-serif; }
    </style>
</head>
<body class="bg-white min-h-screen flex items-center justify-center py-12">
    <div class="max-w-md w-full px-6 text-center">

        <div class="flex justify-center mb-8">
            <div class="flex items-center gap-3">
                <img src="../images/drum_logo.jpg" alt="" class="h-14 w-12">
                <div class="text-left">
                    <div class="heading-font text-5xl font-bold tracking-tighter">Enono</div>
                    <div class="pl-2 text-sm tracking-widest text-amber-600 -mt-1">MARKET</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border border-gray-100 p-8">
            <div class="text-red-500 text-5xl mb-4">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <h1 class="text-2xl font-semibold mb-3">Verification Failed</h1>
            <p class="text-gray-600 mb-6"><?= htmlspecialchars($message) ?></p>
            <a href="signup.php" class="inline-block bg-[#8B4513] hover:bg-amber-600 text-white font-semibold px-8 py-3 rounded-xl">
                Try Again
            </a>
        </div>
    </div>
</body>
</html>