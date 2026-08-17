<?php
// Dashboard/messages.php

require_once __DIR__ . '/../config/database-connection.php';
require_once __DIR__ . '/../config/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access control is handled by includes/topbar.php
// (only admin and staff can reach this page)

$adminId = (int) ($_SESSION['user_id'] ?? 0);

// ---------- AJAX Handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===== Send Reply =====
    if (isset($_POST['ajax_reply'])) {
        header('Content-Type: application/json');

        $email = trim($_POST['email'] ?? '');
        $reply = trim($_POST['message'] ?? '');
        $name  = trim($_POST['name'] ?? 'Customer');

        if ($email === '' || $reply === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM contact_messages WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $latest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$latest) {
                throw new Exception('Conversation not found');
            }

            $threadId = $latest['id'];

            $pdo->prepare("
                INSERT INTO message_replies (contact_message_id, sender_type, sender_id, message)
                VALUES (?, 'admin', ?, ?)
            ")->execute([$threadId, $adminId, $reply]);

            $pdo->prepare("
                UPDATE contact_messages 
                SET status = 'replied', replied_by = ?, replied_at = NOW(), admin_reply = ?
                WHERE id = ?
            ")->execute([$adminId, $reply, $threadId]);

            $subject = 'Re: Your message to Enono Market';
            $emailBody = '
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
                    <h2 style="color:#8B4513;">Hello ' . htmlspecialchars($name) . ',</h2>
                    <p>Thank you for contacting <strong>Enono Market</strong>. Here is our reply:</p>
                    <div style="background:#f9f5eb;padding:16px;border-radius:8px;margin:20px 0;border-left:4px solid #8B4513;">
                        <p style="margin:0;white-space:pre-wrap;">' . nl2br(htmlspecialchars($reply)) . '</p>
                    </div>
                    <p style="color:#666;margin-top:30px;">
                        Warm regards,<br>
                        <strong>The Enono Market Team</strong>
                    </p>
                </div>';

            $mailResult = sendMail($email, $name, $subject, $emailBody, true);

            echo json_encode([
                'success'    => true,
                'message'    => $reply,
                'created_at' => date('d M, H:i'),
                'mail_sent'  => $mailResult['success'] ?? false
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    // ===== Delete entire conversation =====
    if (isset($_POST['ajax_delete_conversation'])) {
        header('Content-Type: application/json');
        $email = trim($_POST['email'] ?? '');

        try {
            $stmt = $pdo->prepare("SELECT id FROM contact_messages WHERE email = ?");
            $stmt->execute([$email]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM message_replies WHERE contact_message_id IN ($placeholders)")->execute($ids);
                $pdo->prepare("DELETE FROM contact_messages WHERE email = ?")->execute([$email]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ===== Delete by status =====
    if (isset($_POST['ajax_delete_by_status'])) {
        header('Content-Type: application/json');
        $status = $_POST['status'] ?? '';
        $allowed = ['new', 'read', 'replied', 'closed'];

        if (!in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM contact_messages WHERE status = ?");
            $stmt->execute([$status]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM message_replies WHERE contact_message_id IN ($placeholders)")->execute($ids);
                $pdo->prepare("DELETE FROM contact_messages WHERE status = ?")->execute([$status]);
            }
            echo json_encode(['success' => true, 'deleted' => count($ids)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ---------- Selected conversation ----------
$selectedEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
$selectedName  = '';
$chatMessages  = [];

if ($selectedEmail !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM contact_messages 
        WHERE email = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$selectedEmail]);
    $allMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($allMessages) {
        $selectedName = $allMessages[0]['name'];

        foreach ($allMessages as $msg) {
            $chatMessages[] = [
                'id'          => $msg['id'],
                'sender_type' => 'customer',
                'message'     => $msg['message'],
                'created_at'  => $msg['created_at'],
                'status'      => $msg['status']
            ];

            if ($msg['status'] === 'new') {
                $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?")->execute([$msg['id']]);
            }

            $repliesStmt = $pdo->prepare("
                SELECT * FROM message_replies 
                WHERE contact_message_id = ? 
                ORDER BY created_at ASC
            ");
            $repliesStmt->execute([$msg['id']]);
            $replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($replies as $r) {
                $chatMessages[] = [
                    'id'          => 'r' . $r['id'],
                    'sender_type' => $r['sender_type'],
                    'message'     => $r['message'],
                    'created_at'  => $r['created_at'],
                    'status'      => null
                ];
            }

            if (empty($replies) && !empty($msg['admin_reply'])) {
                $chatMessages[] = [
                    'id'          => 'old' . $msg['id'],
                    'sender_type' => 'admin',
                    'message'     => $msg['admin_reply'],
                    'created_at'  => $msg['replied_at'] ?? $msg['updated_at'],
                    'status'      => null
                ];
            }
        }
    }
}

// ---------- Conversation list (GROUPED BY EMAIL) ----------
$listStmt = $pdo->query("
    SELECT 
        email,
        MAX(name) AS name,
        MAX(created_at) AS last_message_at,
        COUNT(*) AS total_messages,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS unread_count
    FROM contact_messages
    GROUP BY email
    ORDER BY last_message_at DESC
");
$conversations = $listStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as &$conv) {
    $stmt = $pdo->prepare("
        SELECT message 
        FROM contact_messages 
        WHERE email = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$conv['email']]);
    $conv['last_message'] = $stmt->fetchColumn() ?: '';
}
unset($conv);

$pageTitle = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages – Enono Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }

        /* Cleaner bubbles */
        .bubble-customer {
            background: #f1f5f9;
            color: #1e293b;
            border-radius: 18px 18px 18px 4px;
            max-width: 75%;
            border: 1px solid #e2e8f0;
        }
        .bubble-admin {
            background: #8B4513;
            color: #ffffff;
            border-radius: 18px 18px 4px 18px;
            max-width: 75%;
            margin-left: auto;
        }

        .chat-scroll {
            height: calc(100vh - 280px);
            overflow-y: auto;
            background: #ffffff;
        }
        .chat-scroll::-webkit-scrollbar { width: 6px; }
        .chat-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="flex h-screen overflow-hidden">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">

        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 flex overflow-hidden">

            <!-- LEFT: Conversation List -->
            <div class="w-full md:w-96 bg-white border-r border-gray-200 flex flex-col <?= $selectedEmail ? 'hidden md:flex' : 'flex' ?>">
                
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold">Messages</h1>
                        <p class="text-xs text-gray-500 mt-0.5"><?= count($conversations) ?> conversations</p>
                    </div>

                    <div class="relative">
                        <button onclick="document.getElementById('deleteMenu').classList.toggle('hidden')"
                                class="text-gray-500 hover:text-gray-700 text-sm p-2">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div id="deleteMenu" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-100 z-50 py-2">
                            <button onclick="deleteByStatus('read')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 text-gray-700">
                                Delete all Read
                            </button>
                            <button onclick="deleteByStatus('replied')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 text-gray-700">
                                Delete all Replied
                            </button>
                            <button onclick="deleteByStatus('closed')" class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 text-gray-700">
                                Delete all Closed
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <?php if (empty($conversations)): ?>
                        <div class="p-10 text-center text-gray-400 text-sm">
                            <i class="fa-regular fa-comments text-4xl mb-3 opacity-30"></i>
                            <p>No messages yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <?php
                            $isActive  = $selectedEmail === $conv['email'];
                            $hasUnread = $conv['unread_count'] > 0;
                            ?>
                            <a href="?email=<?= urlencode($conv['email']) ?>"
                               class="flex items-center gap-3 px-5 py-4 border-b border-gray-50 hover:bg-gray-50 transition
                                      <?= $isActive ? 'bg-amber-50' : '' ?>">
                                
                                <div class="w-12 h-12 rounded-full bg-[#8B4513]/10 flex items-center justify-center text-[#8B4513] font-semibold text-lg flex-shrink-0">
                                    <?= strtoupper(substr($conv['name'], 0, 1)) ?>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-medium text-sm truncate <?= $hasUnread ? 'text-gray-900 font-semibold' : 'text-gray-800' ?>">
                                            <?= htmlspecialchars($conv['name']) ?>
                                        </p>
                                        <span class="text-[11px] text-gray-400 flex-shrink-0">
                                            <?= date('d M', strtotime($conv['last_message_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between mt-0.5">
                                        <p class="text-xs text-gray-500 truncate">
                                            <?= htmlspecialchars(mb_strimwidth($conv['last_message'], 0, 40, '…')) ?>
                                        </p>
                                        <?php if ($hasUnread): ?>
                                            <span class="bg-amber-500 text-white text-[10px] font-bold min-w-[18px] h-[18px] rounded-full flex items-center justify-center px-1">
                                                <?= $conv['unread_count'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Chat Area -->
            <div class="flex-1 flex flex-col bg-white <?= !$selectedEmail ? 'hidden md:flex' : 'flex' ?>">

                <?php if (!$selectedEmail || empty($chatMessages)): ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400">
                        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                            <i class="fa-regular fa-comments text-3xl opacity-40"></i>
                        </div>
                        <p class="text-sm">Select a conversation to start chatting</p>
                    </div>

                <?php else: ?>

                    <!-- Chat header -->
                    <div class="bg-white px-5 py-3 border-b border-gray-200 flex items-center gap-4">
                        <a href="messages.php" class="md:hidden text-gray-600">
                            <i class="fa-solid fa-arrow-left text-lg"></i>
                        </a>

                        <div class="w-10 h-10 rounded-full bg-[#8B4513]/10 flex items-center justify-center text-[#8B4513] font-semibold">
                            <?= strtoupper(substr($selectedName, 0, 1)) ?>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($selectedName) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($selectedEmail) ?></p>
                        </div>

                        <button onclick="deleteConversation('<?= htmlspecialchars($selectedEmail, ENT_QUOTES) ?>')"
                                class="text-gray-400 hover:text-red-500 transition p-2" title="Delete conversation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>

                    <!-- Messages -->
                    <div class="flex-1 chat-scroll p-5 space-y-4" id="chatBox">
                        <?php foreach ($chatMessages as $msg): ?>
                            <?php $isAdmin = $msg['sender_type'] === 'admin'; ?>
                            
                            <div class="flex <?= $isAdmin ? 'justify-end' : 'justify-start' ?>">
                                <div class="<?= $isAdmin ? 'bubble-admin' : 'bubble-customer' ?> px-4 py-2.5 shadow-sm">
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($msg['message']) ?></p>
                                    <p class="text-[10px] mt-1.5 <?= $isAdmin ? 'text-white/70 text-right' : 'text-gray-500' ?>">
                                        <?= date('d M, H:i', strtotime($msg['created_at'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply box -->
                    <div class="bg-white border-t border-gray-200 p-4">
                        <form id="replyForm" class="flex items-end gap-3">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($selectedEmail) ?>">
                            <input type="hidden" name="name" value="<?= htmlspecialchars($selectedName) ?>">
                            
                            <div class="flex-1">
                                <textarea id="replyMessage" name="message" rows="1" required
                                          placeholder="Type a message..."
                                          class="w-full bg-gray-100 border-0 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#8B4513]/30 resize-none"
                                          oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                            </div>

                            <button type="submit" id="sendBtn"
                                    class="bg-[#8B4513] hover:bg-[#6B3410] text-white w-12 h-12 rounded-full flex items-center justify-center transition flex-shrink-0">
                                <i class="fa-solid fa-paper-plane" id="sendIcon"></i>
                                <i class="fa-solid fa-spinner fa-spin hidden" id="loadingIcon"></i>
                            </button>
                        </form>
                        <p id="replyStatus" class="text-xs mt-2 hidden"></p>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const chatBox = document.getElementById('chatBox');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

// Reply
const replyForm = document.getElementById('replyForm');
if (replyForm) {
    replyForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const messageInput = document.getElementById('replyMessage');
        const message = messageInput.value.trim();
        if (!message) return;

        const sendBtn = document.getElementById('sendBtn');
        const sendIcon = document.getElementById('sendIcon');
        const loadingIcon = document.getElementById('loadingIcon');
        const statusEl = document.getElementById('replyStatus');

        sendBtn.disabled = true;
        sendIcon.classList.add('hidden');
        loadingIcon.classList.remove('hidden');
        statusEl.classList.add('hidden');

        try {
            const formData = new FormData(replyForm);
            formData.append('ajax_reply', '1');

            const res = await fetch('messages.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                const bubble = document.createElement('div');
                bubble.className = 'flex justify-end';
                bubble.innerHTML = `
                    <div class="bubble-admin px-4 py-2.5 shadow-sm">
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">${escapeHtml(data.message)}</p>
                        <p class="text-[10px] mt-1.5 text-white/70 text-right">${data.created_at}</p>
                    </div>
                `;
                chatBox.appendChild(bubble);
                chatBox.scrollTop = chatBox.scrollHeight;
                messageInput.value = '';
                messageInput.style.height = '';

                statusEl.textContent = data.mail_sent ? 'Reply sent & emailed' : 'Reply saved';
                statusEl.className = 'text-xs mt-2 text-emerald-600';
                statusEl.classList.remove('hidden');
            } else {
                statusEl.textContent = data.message || 'Failed';
                statusEl.className = 'text-xs mt-2 text-red-600';
                statusEl.classList.remove('hidden');
            }
        } catch (err) {
            statusEl.textContent = 'Network error';
            statusEl.className = 'text-xs mt-2 text-red-600';
            statusEl.classList.remove('hidden');
        }

        sendBtn.disabled = false;
        sendIcon.classList.remove('hidden');
        loadingIcon.classList.add('hidden');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Delete whole conversation
async function deleteConversation(email) {
    if (!confirm('Delete the entire conversation with this person?')) return;

    const formData = new FormData();
    formData.append('ajax_delete_conversation', '1');
    formData.append('email', email);

    const res = await fetch('messages.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
        window.location.href = 'messages.php';
    } else {
        alert(data.message || 'Could not delete conversation');
    }
}

// Delete by status
async function deleteByStatus(status) {
    if (!confirm(`Delete ALL messages with status "${status}"?`)) return;

    document.getElementById('deleteMenu').classList.add('hidden');

    const formData = new FormData();
    formData.append('ajax_delete_by_status', '1');
    formData.append('status', status);

    const res = await fetch('messages.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
        alert(`Deleted ${data.deleted} messages`);
        window.location.reload();
    } else {
        alert(data.message || 'Failed');
    }
}
</script>

</body>
</html>