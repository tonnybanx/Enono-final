<?php
// Dashboard/users.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'users';

// ===================== ACCESS CONTROL =====================
// Only admins can view/manage this page. Staff and customers are redirected away.
$viewerRole = $_SESSION['role'] ?? '';
$isJsonRequest = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

if ($viewerRole !== 'admin') {
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to access this page.']);
    } else {
        header('Location: index.php?error=forbidden');
    }
    exit;
}

// ===================== HANDLE AJAX (DELETE / UPDATE / FETCH ONE) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {

    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $ids = $input['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs provided']);
            exit;
        }

        $ids = array_map('intval', $ids);

        // Prevent deleting the currently logged-in user
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $ids = array_filter($ids, fn($id) => $id !== $currentUserId);

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            $stmt->execute(array_values($ids));
            $pdo->commit();
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---------- FETCH ONE (for view/edit modal) ----------
    if ($action === 'get') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT id, email, first_name, last_name, phone, role,
                       email_verified, is_active, created_at, last_login_at
                FROM users WHERE id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            echo json_encode(['success' => true, 'user' => $user]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---------- UPDATE ----------
    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID provided']);
            exit;
        }

        $firstName = trim($input['first_name'] ?? '');
        $lastName  = trim($input['last_name'] ?? '');
        $email     = trim($input['email'] ?? '');
        $phone     = trim($input['phone'] ?? '');
        $allowedRoles = ['admin', 'staff', 'customer'];
        $role = in_array($input['role'] ?? '', $allowedRoles, true) ? $input['role'] : 'customer';
        $isActive  = !empty($input['is_active']) ? 1 : 0;
        $emailVerified = !empty($input['email_verified']) ? 1 : 0;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'A valid email is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, email = ?, phone = ?,
                    role = ?, is_active = ?, email_verified = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $email, $phone, $role, $isActive, $emailVerified, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ===================== FETCH USERS =====================
$sql = "
    SELECT 
        id, email, first_name, last_name, phone, role,
        email_verified, is_active, created_at, last_login_at
    FROM users
    ORDER BY created_at DESC
";

try {
    $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

function formatDate($dt) {
    if (!$dt) return '—';
    return date('M j, Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05); }
        tr { transition: background-color 0.2s; }
        tr:hover { background-color: #f8fafc; }
        .action-btn { opacity: 0; transform: translateX(10px); transition: all 0.25s ease; }
        tr:hover .action-btn { opacity: 1; transform: translateX(0); }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
        select {
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 1rem center; background-size: 1.1em;
            padding-right: 2.75rem !important;
        }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.open { transform: translateX(0); }
        @media (min-width: 1024px) { .sidebar { transform: none !important; } }

        /* ===== Modal overlay ===== */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
            display: flex; align-items: center; justify-content: center;
            z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
            padding: 1rem;
        }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-panel {
            background: white; border-radius: 20px; width: 100%; max-width: 32rem;
            max-height: 90vh; overflow-y: auto; transform: translateY(12px) scale(0.98);
            transition: transform 0.2s ease; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        .modal-overlay.open .modal-panel { transform: translateY(0) scale(1); }
        .field-label { font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-hidden">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <div class="flex-1 overflow-hidden p-6 flex flex-col">
        <div class="max-w-7xl mx-auto w-full flex flex-col flex-1 min-h-0">

            <!-- Header -->
            <div class="flex justify-between items-center mb-6 flex-shrink-0">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Users</h1>
                    <p class="text-gray-500"><span id="totalCount"><?= count($users) ?></span> users</p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="flex flex-col md:flex-row gap-4 mb-6 flex-shrink-0">
                <div class="flex-1 relative">
                    <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchInput" class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                           placeholder="Search by name or email..." onkeyup="applyFilters()">
                </div>
                <div class="w-full md:w-48">
                    <select id="roleFilter" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500" onchange="applyFilters()">
                        <option value="">All roles</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="customer">Customer</option>
                    </select>
                </div>
                <div class="w-full md:w-48">
                    <select id="statusFilter" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500" onchange="applyFilters()">
                        <option value="">All status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Selection Bar -->
            <div id="selectionBar" class="hidden bg-white border border-gray-200 rounded-2xl p-4 flex items-center justify-between mb-4 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <span class="font-medium text-gray-700">0 selected</span>
                    <button onclick="clearSelection()" class="text-blue-600 hover:text-blue-700 font-medium">Clear</button>
                </div>
                <button onclick="deleteSelected()" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 font-medium">
                    <i class="fa fa-trash"></i> Delete selected
                </button>
            </div>

            <!-- Table Card (flex column so pagination stays at bottom) -->
            <div class="card overflow-hidden flex flex-col flex-1 min-h-0">
                <!-- Scrollable table area -->
                <div class="flex-1 overflow-auto min-h-0">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                            <tr class="text-left text-sm text-gray-600">
                                <th class="w-10 p-4"><input type="checkbox" id="selectAll" class="w-4 h-4"></th>
                                <th class="p-4 font-medium">User</th>
                                <th class="p-4 font-medium">Email</th>
                                <th class="p-4 font-medium">Role</th>
                                <th class="p-4 font-medium">Phone</th>
                                <th class="p-4 font-medium">Status</th>
                                <th class="p-4 font-medium">Joined</th>
                                <th class="p-4 font-medium">Last Login</th>
                                <th class="w-40 p-4 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100" id="usersTable">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="9" class="p-16">
                                        <div class="flex flex-col items-center justify-center text-center">
                                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No users" class="w-36 h-36 object-contain mb-5 opacity-90">
                                            <h3 class="text-lg font-medium text-gray-700 mb-1">No users yet</h3>
                                            <p class="text-gray-400 text-sm max-w-xs">Users will appear here once they register.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="group user-row"
                                        data-id="<?= (int)$user['id'] ?>"
                                        data-name="<?= htmlspecialchars(strtolower(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))) ?>"
                                        data-email="<?= htmlspecialchars(strtolower($user['email'])) ?>"
                                        data-role="<?= htmlspecialchars(strtolower($user['role'] ?? 'customer')) ?>"
                                        data-active="<?= (int)$user['is_active'] ?>">
                                        
                                        <td class="p-4">
                                            <input type="checkbox" class="w-4 h-4 user-checkbox" data-id="<?= (int)$user['id'] ?>">
                                        </td>
                                        
                                        <td class="p-4 cursor-pointer" onclick="openViewModal(<?= (int)$user['id'] ?>)">
                                            <div class="font-medium text-gray-900">
                                                <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: '—') ?>
                                            </div>
                                            <?php if ($user['email_verified']): ?>
                                                <span class="inline-flex items-center gap-1 text-xs text-green-600 mt-0.5">
                                                    <i class="fa fa-check-circle text-[10px]"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-xs text-amber-600 mt-0.5">
                                                    <i class="fa fa-exclamation-circle text-[10px]"></i> Unverified
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="p-4 text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                                        
                                        <td class="p-4">
                                            <?php $roleVal = $user['role'] ?? 'customer'; ?>
                                            <?php if ($roleVal === 'admin'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Admin</span>
                                            <?php elseif ($roleVal === 'staff'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Staff</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Customer</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="p-4 text-gray-600"><?= htmlspecialchars($user['phone'] ?? '—') ?></td>
                                        
                                        <td class="p-4">
                                            <?php if ($user['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    <span class="status-dot mr-1.5 bg-green-500"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    <span class="status-dot mr-1.5 bg-gray-400"></span> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="p-4 text-gray-600"><?= formatDate($user['created_at']) ?></td>
                                        <td class="p-4 text-gray-600"><?= formatDate($user['last_login_at']) ?></td>
                                        
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <button onclick="openViewModal(<?= (int)$user['id'] ?>)"
                                                        class="action-btn bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-1.5 whitespace-nowrap">
                                                    <i class="fa fa-eye text-gray-500"></i> View
                                                </button>
                                                <button onclick="openEditModal(<?= (int)$user['id'] ?>)"
                                                        class="action-btn bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-1.5 whitespace-nowrap">
                                                    <i class="fa fa-pen text-gray-500"></i> Edit
                                                </button>
                                                <button onclick="deleteSingle(<?= (int)$user['id'] ?>)"
                                                        class="action-btn bg-white border border-red-200 hover:border-red-300 text-red-600 px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-1.5">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination – always at the bottom of the card -->
                <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-t bg-gray-50 flex-shrink-0">
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <span>Show</span>
                        <select id="pageSize" class="bg-white border border-gray-300 rounded-xl py-2 px-4 focus:outline-none" onchange="changePageSize()">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                        <span>per page</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span id="pageInfo">Page 1 of 1</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="prevBtn" onclick="changePage(-1)" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa fa-chevron-left mr-1"></i> Previous
                        </button>
                        <button id="nextBtn" onclick="changePage(1)" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
                            Next <i class="fa fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<!-- ===================== VIEW MODAL ===================== -->
<div id="viewModalOverlay" class="modal-overlay" onclick="if(event.target===this) closeViewModal()">
    <div class="modal-panel">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">User details</h2>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div id="viewModalBody" class="p-6 space-y-4">
            <!-- populated by JS -->
        </div>
        <div class="flex justify-end gap-3 px-6 pb-6">
            <button onclick="closeViewModal()" class="px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">Close</button>
            <button id="viewToEditBtn" class="px-4 py-2.5 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700">
                <i class="fa fa-pen mr-1.5"></i> Edit
            </button>
        </div>
    </div>
</div>

<!-- ===================== EDIT MODAL ===================== -->
<div id="editModalOverlay" class="modal-overlay" onclick="if(event.target===this) closeEditModal()">
    <div class="modal-panel">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Edit user</h2>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <form id="editForm" class="p-6 space-y-4" onsubmit="submitEdit(event)">
            <input type="hidden" id="editId">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label">First name</label>
                    <input type="text" id="editFirstName" class="mt-1 w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="field-label">Last name</label>
                    <input type="text" id="editLastName" class="mt-1 w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="field-label">Email</label>
                <input type="email" id="editEmail" required class="mt-1 w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="field-label">Phone</label>
                <input type="text" id="editPhone" class="mt-1 w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Role</label>
                    <select id="editRole" class="mt-1 w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                        <option value="customer">Customer</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select id="editStatus" class="mt-1 w-full px-3.5 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" id="editVerified" class="w-4 h-4"> Email verified
            </label>
            <p id="editError" class="hidden text-sm text-red-600"></p>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" id="editSaveBtn" class="px-4 py-2.5 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentPage = 1, pageSize = 20, filteredRows = [];

    function getAllRows() { return Array.from(document.querySelectorAll('.user-row')); }

    function applyFilters() {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        const role   = document.getElementById('roleFilter').value;
        const status = document.getElementById('statusFilter').value;

        const allRows = getAllRows();
        filteredRows = allRows.filter(row => {
            const name  = row.dataset.name  || '';
            const email = row.dataset.email || '';
            const r     = row.dataset.role  || '';
            const active = row.dataset.active;
            return (!search || name.includes(search) || email.includes(search)) &&
                   (role === '' || r === role) &&
                   (status === '' || active === status);
        });

        allRows.forEach(r => r.style.display = 'none');
        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        filteredRows.forEach((row, idx) => {
            row.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        document.getElementById('pageInfo').textContent = total === 0 ? 'No results' : `Page ${currentPage} of ${totalPages}`;
        document.getElementById('totalCount').textContent = total;
        document.getElementById('prevBtn').disabled = currentPage <= 1;
        document.getElementById('nextBtn').disabled = currentPage >= totalPages;
        document.getElementById('selectAll').checked = false;
        updateSelectionBar();
    }

    function changePage(delta) { currentPage += delta; renderPage(); }
    function changePageSize() { pageSize = parseInt(document.getElementById('pageSize').value, 10); currentPage = 1; renderPage(); }

    function updateSelectionBar() {
        const selected = document.querySelectorAll('.user-checkbox:checked').length;
        const bar = document.getElementById('selectionBar');
        bar.classList.toggle('hidden', selected === 0);
        if (selected > 0) bar.querySelector('span').textContent = `${selected} selected`;
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('user-checkbox')) updateSelectionBar();
    });

    function clearSelection() {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        document.getElementById('selectionBar').classList.add('hidden');
    }

    function deleteSelected() {
        const ids = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.dataset.id);
        if (ids.length === 0) return;
        if (!confirm(`Delete ${ids.length} selected user(s)? This action cannot be undone.`)) return;

        fetch('users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', ids })
        })
        .then(r => r.json())
        .then(data => data.success ? location.reload() : alert(data.message || 'Failed'))
        .catch(() => alert('Network error'));
    }

    function deleteSingle(id) {
        if (!confirm('Delete this user? This action cannot be undone.')) return;
        fetch('users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', ids: [id] })
        })
        .then(r => r.json())
        .then(data => data.success ? location.reload() : alert(data.message || 'Failed'))
        .catch(() => alert('Network error'));
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.toggle('open');
        if (window.innerWidth <= 1024) overlay.classList.toggle('hidden');
    }

    document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.user-checkbox').forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') cb.checked = this.checked;
        });
        updateSelectionBar();
    });

    document.addEventListener('DOMContentLoaded', applyFilters);

    // ===================== VIEW / EDIT MODALS =====================
    async function fetchUser(id) {
        const res = await fetch('users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get', id })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Could not load user');
            return null;
        }
        return data.user;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatDisplayDate(dt) {
        if (!dt) return '—';
        const d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d)) return dt;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // ---------- View modal ----------
    async function openViewModal(id) {
        const user = await fetchUser(id);
        if (!user) return;

        const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || '—';
        const roleLabel = user.role === 'admin' ? 'Admin' : (user.role === 'staff' ? 'Staff' : 'Customer');
        const statusLabel = Number(user.is_active) ? 'Active' : 'Inactive';
        const verifiedLabel = Number(user.email_verified) ? 'Verified' : 'Unverified';

        document.getElementById('viewModalBody').innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div><p class="field-label">Name</p><p class="mt-1 text-gray-900">${escapeHtml(fullName)}</p></div>
                <div><p class="field-label">Email</p><p class="mt-1 text-gray-900">${escapeHtml(user.email)}</p></div>
                <div><p class="field-label">Phone</p><p class="mt-1 text-gray-900">${escapeHtml(user.phone || '—')}</p></div>
                <div><p class="field-label">Role</p><p class="mt-1 text-gray-900">${roleLabel}</p></div>
                <div><p class="field-label">Status</p><p class="mt-1 text-gray-900">${statusLabel}</p></div>
                <div><p class="field-label">Email verification</p><p class="mt-1 text-gray-900">${verifiedLabel}</p></div>
                <div><p class="field-label">Joined</p><p class="mt-1 text-gray-900">${formatDisplayDate(user.created_at)}</p></div>
                <div><p class="field-label">Last login</p><p class="mt-1 text-gray-900">${formatDisplayDate(user.last_login_at)}</p></div>
            </div>
        `;

        document.getElementById('viewToEditBtn').onclick = () => {
            closeViewModal();
            openEditModal(id);
        };

        document.getElementById('viewModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeViewModal() {
        document.getElementById('viewModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    // ---------- Edit modal ----------
    async function openEditModal(id) {
        const user = await fetchUser(id);
        if (!user) return;

        document.getElementById('editId').value = user.id;
        document.getElementById('editFirstName').value = user.first_name || '';
        document.getElementById('editLastName').value = user.last_name || '';
        document.getElementById('editEmail').value = user.email || '';
        document.getElementById('editPhone').value = user.phone || '';
        document.getElementById('editRole').value = ['admin', 'staff'].includes(user.role) ? user.role : 'customer';
        document.getElementById('editStatus').value = Number(user.is_active) ? '1' : '0';
        document.getElementById('editVerified').checked = !!Number(user.email_verified);
        document.getElementById('editError').classList.add('hidden');

        document.getElementById('editModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    async function submitEdit(e) {
        e.preventDefault();
        const errorEl = document.getElementById('editError');
        const saveBtn = document.getElementById('editSaveBtn');
        errorEl.classList.add('hidden');

        const payload = {
            action: 'update',
            id: document.getElementById('editId').value,
            first_name: document.getElementById('editFirstName').value.trim(),
            last_name: document.getElementById('editLastName').value.trim(),
            email: document.getElementById('editEmail').value.trim(),
            phone: document.getElementById('editPhone').value.trim(),
            role: document.getElementById('editRole').value,
            is_active: document.getElementById('editStatus').value === '1',
            email_verified: document.getElementById('editVerified').checked
        };

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const res = await fetch('users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                errorEl.textContent = data.message || 'Could not save changes.';
                errorEl.classList.remove('hidden');
            }
        } catch (err) {
            errorEl.textContent = 'Network error. Please try again.';
            errorEl.classList.remove('hidden');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save changes';
        }
    }

    // Close modals on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeViewModal();
            closeEditModal();
        }
    });
</script>
</body>
</html>