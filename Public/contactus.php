<?php
// Public/contactus.php

require_once __DIR__ . '/../config/database-connection.php';
require_once __DIR__ . '/../config/mail.php';

$pageTitle = 'Contact Us';
$activeNav = 'contact';

// Fetch company profile
$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoUrl = !empty($company['logo_url'])
    ? '../' . ltrim($company['logo_url'], '/')
    : '../images/drum_logo.jpg';

$address_line1 = $company['address_line1'] ?? 'First Floor, Plot 25, Kampala Road';
$address_line2 = $company['address_line2'] ?? '';
$city          = $company['city']          ?? 'Kampala';
$country       = $company['country']       ?? 'Uganda';
$email         = $company['email']         ?? 'support@enonomarket.com';
$phone         = $company['phone']         ?? '';
$whatsapp      = $company['whatsapp']      ?? '';
$working_hours = $company['working_hours'] ?? 'Mon – Sat: 9:00 AM – 6:00 PM';

// Location image
$locationImg = '../images/location.PNG';
try {
    $imgStmt = $pdo->query("SELECT url FROM general_images WHERE category = 'location' AND is_active = 1 ORDER BY sort_order LIMIT 1");
    $imgRow  = $imgStmt->fetch(PDO::FETCH_ASSOC);
    if ($imgRow && !empty($imgRow['url'])) {
        $locationImg = '../' . ltrim($imgRow['url'], '/');
    }
} catch (Exception $e) {}

// ---------- AJAX Form Handler ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_contact'])) {
    header('Content-Type: application/json');

    $name    = trim($_POST['name']    ?? '');
    $email_f = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? 'Message from Contact Form');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email_f === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    if (!filter_var($email_f, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    try {
        // Save to database
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) 
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) 
            : null;

        $insert = $pdo->prepare("
            INSERT INTO contact_messages 
                (user_id, name, email, subject, message, status, ip_address, user_agent)
            VALUES 
                (:user_id, :name, :email, :subject, :message, 'new', :ip, :ua)
        ");

        $insert->execute([
            ':user_id'  => $userId,
            ':name'     => $name,
            ':email'    => $email_f,
            ':subject'  => $subject,
            ':message'  => $message,
            ':ip'       => $ipAddress,
            ':ua'       => $userAgent,
        ]);

        // Email to support
        $supportEmail = !empty($company['email']) ? $company['email'] : 'support@enonomarket.com';
        $adminSubject = 'New Contact Message: ' . ($subject ?: 'No subject');
        
        $adminBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <h2 style="color:#8B4513;">New message from enono MARKET website</h2>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr>
                        <td style="padding:8px 0;color:#666;width:120px;">Name</td>
                        <td style="padding:8px 0;font-weight:bold;">' . htmlspecialchars($name) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#666;">Email</td>
                        <td style="padding:8px 0;">
                            <a href="mailto:' . htmlspecialchars($email_f) . '">' . htmlspecialchars($email_f) . '</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#666;">Subject</td>
                        <td style="padding:8px 0;">' . htmlspecialchars($subject) . '</td>
                    </tr>
                </table>
                <div style="background:#f9f5eb;padding:16px;border-radius:8px;margin-top:10px;">
                    <p style="margin:0 0 6px;color:#666;font-size:13px;">Message:</p>
                    <p style="margin:0;white-space:pre-wrap;">' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                <p style="color:#999;font-size:12px;margin-top:25px;">
                    Sent from the Contact Us page on ' . date('d M Y, H:i') . '
                </p>
            </div>';

        $result = sendMail($supportEmail, 'Enono Support', $adminSubject, $adminBody, true);

        if (!$result['success']) {
            error_log('Contact form email failed: ' . $result['message']);
        }

        // Confirmation email to customer
        $confirmSubject = 'We received your message – enono MARKET';
        $confirmBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                <h2 style="color:#8B4513;">Thank you, ' . htmlspecialchars($name) . '!</h2>
                <p>We have received your message and will get back to you within 24 hours.</p>
                <div style="background:#f9f5eb;padding:16px;border-radius:8px;margin:20px 0;">
                    <p style="margin:0 0 6px;color:#666;font-size:13px;">Your message:</p>
                    <p style="margin:0;white-space:pre-wrap;">' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                <p style="color:#666;">Warm regards,<br>The enono MARKET Team</p>
            </div>';

        sendMail($email_f, $name, $confirmSubject, $confirmBody, true);

        // Local log
        $logFile = __DIR__ . '/../contact_log.txt';
        $log = date('Y-m-d H:i:s') . " | $name <$email_f> | $subject\n$message\n" . str_repeat('-', 40) . "\n";
        @file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);

        echo json_encode([
            'success' => true,
            'message' => 'Thank you! Your message has been sent. We will reply within 24 hours.'
        ]);
        exit;

    } catch (Exception $e) {
        error_log('Contact form error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Sorry, we could not send your message at the moment. Please try again later.'
        ]);
        exit;
    }
}

// Normal page load
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .contact-hero {
        background: linear-gradient(160deg, #2C1A0F 0%, #3d2618 50%, #5c3a22 100%);
        position: relative;
        overflow: hidden;
    }
    .contact-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('https://picsum.photos/id/1015/2000/900') center/cover;
        opacity: 0.15;
    }

    .card {
        background: #ffffff;
        border: 1px solid #ebe4d9;
        border-radius: 1.25rem;
    }

    .info-row {
        display: flex;
        gap: 1rem;
        padding: 1.1rem 0;
        border-bottom: 1px solid #f3eee6;
    }
    .info-row:last-of-type {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #f7f0e6;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8B4513;
        flex-shrink: 0;
        font-size: 0.95rem;
    }

    .form-input {
        width: 100%;
        background: #faf8f4;
        border: 1px solid #e5ddd2;
        border-radius: 10px;
        padding: 0.85rem 1.1rem;
        font-size: 0.95rem;
        color: #1f1f1f;
        transition: border-color 0.15s, background 0.15s;
    }
    .form-input:focus {
        outline: none;
        background: #fff;
        border-color: #8B4513;
    }
    .form-input::placeholder {
        color: #b0a79a;
    }

    .submit-btn {
        background: #8B4513;
        transition: background 0.2s;
    }
    .submit-btn:hover {
        background: #6B3410;
    }
    .submit-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
</style>

<!-- ==================== HERO ==================== -->
<section class="contact-hero text-white relative">
    <div class="relative z-10 max-w-7xl mx-auto px-6 py-16 md:py-24">
        <div class="max-w-xl">
            <p class="text-amber-200/80 text-xs font-medium tracking-[0.18em] uppercase mb-3">
                Enono Market
            </p>
            <h1 class="text-4xl sm:text-5xl font-semibold heading-font leading-tight mb-4">
                Get in touch
            </h1>
            <p class="text-white/65 text-base leading-relaxed">
                Questions about our crafts, an order, or the artisans behind the work — we’d love to hear from you.
            </p>
        </div>
    </div>
</section>

<!-- ==================== MAIN ==================== -->
<section class="py-14 md:py-20 bg-[#F9F5EB]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid lg:grid-cols-5 gap-8 lg:gap-10">

            <!-- LEFT: Location -->
            <div class="lg:col-span-2 space-y-5">

                <div class="card overflow-hidden">
                    <div class="relative h-48 sm:h-52">
                        <img src="<?= e($locationImg) ?>"
                             alt="Enono Market – Kampala"
                             class="w-full h-full object-cover"
                             onerror="this.src='https://picsum.photos/id/1015/800/500'">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <div class="absolute bottom-4 left-5 right-5 flex items-end justify-between">
                            <div>
                                <p class="text-white/75 text-[11px] tracking-wider uppercase">Studio</p>
                                <p class="text-white text-lg font-semibold heading-font"><?= e($city) ?>, Uganda</p>
                            </div>
                            <a href="https://maps.google.com/?q=<?= urlencode($address_line1 . ', ' . $city . ', ' . $country) ?>"
                               target="_blank" rel="noopener"
                               class="bg-white/95 text-[#8B4513] text-xs font-medium px-3 py-1.5 rounded-lg flex items-center gap-1.5 hover:bg-white transition">
                                <i class="fa-solid fa-map-location-dot text-[11px]"></i>
                                Map
                            </a>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-400 uppercase tracking-wider mb-0.5">Address</p>
                                <p class="text-gray-800 text-sm leading-relaxed">
                                    <?= e($address_line1) ?><?= $address_line2 ? '<br>' . e($address_line2) : '' ?><br>
                                    <?= e($city) ?>, <?= e($country) ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($working_hours): ?>
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fa-regular fa-clock"></i>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-400 uppercase tracking-wider mb-0.5">Hours</p>
                                <p class="text-gray-800 text-sm"><?= e($working_hours) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fa-solid fa-envelope"></i>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-400 uppercase tracking-wider mb-0.5">Email</p>
                                <a href="mailto:<?= e($email) ?>" class="text-[#8B4513] text-sm font-medium hover:underline">
                                    <?= e($email) ?>
                                </a>
                            </div>
                        </div>

                        <?php if ($phone || $whatsapp): ?>
                        <div class="info-row">
                            <div class="info-icon">
                                <i class="fa-solid fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-400 uppercase tracking-wider mb-0.5">Phone</p>
                                <p class="text-gray-800 text-sm"><?= e($phone ?: $whatsapp) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="text-xs text-gray-500 leading-relaxed px-1 flex gap-2">
                    <i class="fa-solid fa-circle-info text-amber-600/60 mt-0.5 flex-shrink-0"></i>
                    <span>This is our studio address. Returns are handled online — please do not send items here.</span>
                </p>
            </div>

            <!-- RIGHT: Form -->
            <div class="lg:col-span-3">
                <div class="card p-7 sm:p-8 h-full">

                    <div class="mb-7">
                        <h2 class="text-2xl font-semibold heading-font text-gray-900">
                            Send a message
                        </h2>
                        <p class="text-gray-500 text-sm mt-1.5">
                            We usually reply within one business day.
                        </p>
                    </div>

                    <!-- AJAX Alerts (hidden by default) -->
                    <div id="successAlert" class="mb-6 p-4 bg-emerald-50 border border-emerald-100 rounded-xl flex gap-3 hidden">
                        <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5"></i>
                        <div>
                            <p class="text-emerald-800 font-medium text-sm">Message sent</p>
                            <p class="text-emerald-700/80 text-sm mt-0.5" id="successText"></p>
                        </div>
                    </div>

                    <div id="errorAlert" class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl flex gap-3 hidden">
                        <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5"></i>
                        <div>
                            <p class="text-red-800 font-medium text-sm">Please check the form</p>
                            <p class="text-red-700/80 text-sm mt-0.5" id="errorText"></p>
                        </div>
                    </div>

                    <form id="contactForm" class="space-y-5">
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                    Full name <span class="text-[#8B4513]">*</span>
                                </label>
                                <input type="text" name="name" id="name" required
                                       class="form-input"
                                       placeholder="Your name">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                    Email <span class="text-[#8B4513]">*</span>
                                </label>
                                <input type="email" name="email" id="email" required
                                       class="form-input"
                                       placeholder="you@example.com">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                Subject
                            </label>
                            <input type="text" name="subject" id="subject"
                                   class="form-input"
                                   placeholder="What is this about?">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                Message <span class="text-[#8B4513]">*</span>
                            </label>
                            <textarea name="message" id="message" rows="5" required
                                      class="form-input resize-none"
                                      placeholder="Tell us how we can help..."></textarea>
                        </div>

                        <button type="submit" id="submitBtn"
                                class="submit-btn w-full text-white py-3.5 rounded-xl font-medium text-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane text-xs opacity-90" id="sendIcon"></i>
                            <i class="fa-solid fa-spinner fa-spin hidden" id="loadingIcon"></i>
                            <span id="btnText">Send Message</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
document.getElementById('contactForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const sendIcon = document.getElementById('sendIcon');
    const loadingIcon = document.getElementById('loadingIcon');
    const btnText = document.getElementById('btnText');
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    const successText = document.getElementById('successText');
    const errorText = document.getElementById('errorText');

    // Hide previous alerts
    successAlert.classList.add('hidden');
    errorAlert.classList.add('hidden');

    // Show loading state
    submitBtn.disabled = true;
    sendIcon.classList.add('hidden');
    loadingIcon.classList.remove('hidden');
    btnText.textContent = 'Sending...';

    try {
        const formData = new FormData(form);
        formData.append('ajax_contact', '1');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            successText.textContent = data.message;
            successAlert.classList.remove('hidden');
            form.reset();

            // Scroll to success message
            successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            errorText.textContent = data.message;
            errorAlert.classList.remove('hidden');
        }

    } catch (err) {
        errorText.textContent = 'Network error. Please try again.';
        errorAlert.classList.remove('hidden');
    }

    // Reset button
    submitBtn.disabled = false;
    sendIcon.classList.remove('hidden');
    loadingIcon.classList.add('hidden');
    btnText.textContent = 'Send Message';
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>