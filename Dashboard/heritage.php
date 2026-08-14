<?php
// Dashboard/heritage.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'heritage';

try {
    $spirits = $pdo->query("
        SELECT * FROM spirits 
        ORDER BY sort_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $spirits = [];
}

function spiritImage($url) {
    if (empty($url)) {
        return '../images/drum_logo.jpg';
    }
    if (str_starts_with($url, 'http')) {
        return $url;
    }
    return '../' . ltrim($url, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Heritage & Spirits</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        body { background-color: #f8fafc; }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        }
        tr { transition: background-color 0.2s; }
        tr:hover { background-color: #f8fafc; }
        .action-icons {
            opacity: 0;
            transform: translateX(10px);
            transition: all 0.2s ease;
        }
        tr:hover .action-icons {
            opacity: 1;
            transform: translateX(0);
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .modal { animation: modalPop 0.3s ease; }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.1em;
            padding-right: 2.75rem !important;
        }
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-wrapper table {
            min-width: 900px;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto">

                <!-- Header -->
                <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Heritage & Spirits</h1>
                        <p class="text-gray-500">
                            <span id="totalCount"><?= count($spirits) ?></span> spirits
                        </p>
                    </div>
                    <button onclick="openAddModal()"
                            class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl font-medium">
                        <i class="fa fa-plus"></i>
                        Add Spirit
                    </button>
                </div>

                <!-- Search -->
                <div class="mb-6">
                    <div class="relative max-w-md">
                        <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInput"
                               class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                               placeholder="Search by name..."
                               onkeyup="applyFilters()">
                    </div>
                </div>

                <!-- Table -->
                <div class="card overflow-hidden">
                    <div class="table-wrapper">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr class="text-left text-sm text-gray-600">
                                    <th class="p-4 font-medium w-16">#</th>
                                    <th class="p-4 font-medium">Spirit</th>
                                    <th class="p-4 font-medium">Short Description</th>
                                    <th class="p-4 font-medium">Sort</th>
                                    <th class="p-4 font-medium">Status</th>
                                    <th class="p-4 font-medium w-32">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100" id="spiritsTable">
                                <?php if (empty($spirits)): ?>
                                    <tr>
                                        <td colspan="6" class="p-16 text-center text-gray-400">
                                            <i class="fa fa-dove text-4xl mb-3"></i>
                                            <p>No spirits found. Add the first one.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($spirits as $index => $s): ?>
                                        <tr class="group spirit-row"
                                            data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>">
                                            
                                            <td class="p-4 text-gray-500"><?= $index + 1 ?></td>
                                            
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?= htmlspecialchars(spiritImage($s['image_url'])) ?>"
                                                         class="w-10 h-10 rounded-xl object-cover bg-gray-100"
                                                         alt=""
                                                         onerror="this.src='../images/drum_logo.jpg'">
                                                    <span class="font-medium text-gray-900"><?= htmlspecialchars($s['name']) ?></span>
                                                </div>
                                            </td>
                                            
                                            <td class="p-4 text-gray-600 max-w-xs truncate">
                                                <?= htmlspecialchars($s['short_description'] ?? '—') ?>
                                            </td>
                                            
                                            <td class="p-4 text-gray-600">
                                                <?= (int)$s['sort_order'] ?>
                                            </td>
                                            
                                            <td class="p-4">
                                                <?php if ($s['is_active']): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                        <span class="status-dot mr-1.5 bg-green-500"></span>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                        <span class="status-dot mr-1.5 bg-red-500"></span>Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="p-4">
                                                <div class="action-icons flex items-center gap-2 text-gray-500">
                                                    <button onclick="viewSpirit(<?= (int)$s['id'] ?>)"
                                                            class="hover:text-blue-600 p-2 hover:bg-gray-100 rounded-xl" title="View">
                                                        <i class="fa fa-eye"></i>
                                                    </button>
                                                    <button onclick="editSpirit(<?= (int)$s['id'] ?>)"
                                                            class="hover:text-amber-600 p-2 hover:bg-gray-100 rounded-xl" title="Edit">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== VIEW MODAL ==================== -->
    <div id="viewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="modal bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="relative h-56 bg-gray-100">
                <img id="viewImage" class="w-full h-full object-cover" alt="">
                <button onclick="closeModals()" class="absolute top-4 right-4 w-10 h-10 bg-black/40 hover:bg-black/60 text-white rounded-full flex items-center justify-center">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-2" id="viewName"></h2>
                <p class="text-gray-600 mb-4" id="viewShort"></p>
                <div class="prose prose-sm text-gray-700 whitespace-pre-line" id="viewFull"></div>
            </div>
        </div>
    </div>

    <!-- ==================== ADD / EDIT MODAL ==================== -->
    <div id="formModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="modal bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b sticky top-0 bg-white z-10">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800" id="formTitle">Add Spirit</h2>
                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-600 text-3xl leading-none">&times;</button>
                </div>
            </div>

            <form id="spiritForm" onsubmit="saveSpirit(event)" class="p-6 space-y-5" enctype="multipart/form-data">
                <input type="hidden" id="spiritId" name="id" value="">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Spirit Image</label>
                    <div class="flex items-start gap-5">
                        <img id="imagePreview" src="../images/drum_logo.jpg"
                             class="w-28 h-28 object-cover rounded-2xl border border-gray-200 bg-gray-50">
                        <div class="flex-1">
                            <input type="file" id="spiritImage" name="image" accept="image/*"
                                   class="block w-full text-sm text-gray-500
                                          file:mr-4 file:py-2.5 file:px-4
                                          file:rounded-xl file:border-0
                                          file:text-sm file:font-medium
                                          file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100"
                                   onchange="previewImage(this)">
                            <p class="text-xs text-gray-400 mt-2">Leave empty to keep current image</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" id="spiritName" name="name" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                    <input type="text" id="spiritShort" name="short_description"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500"
                           placeholder="One-line summary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Description *</label>
                    <textarea id="spiritFull" name="full_description" rows="6" required
                              class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500"
                              placeholder="Detailed spiritual and cultural description..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" id="spiritSort" name="sort_order" value="0" min="0"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="spiritActive" name="is_active"
                                class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:border-blue-500">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModals()"
                            class="px-6 py-3 rounded-2xl border border-gray-300 hover:bg-gray-50 font-medium">
                        Cancel
                    </button>
                    <button type="submit" id="saveBtn"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-medium flex items-center gap-2">
                        <i class="fa fa-save"></i>
                        Save Spirit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <script>
        const spiritsData = <?= json_encode(array_map(function($s) {
            return [
                'id'                => (int)$s['id'],
                'name'              => $s['name'],
                'short_description' => $s['short_description'] ?? '',
                'full_description'  => $s['full_description'],
                'image_url'         => spiritImage($s['image_url']),
                'is_active'         => (int)$s['is_active'],
                'sort_order'        => (int)$s['sort_order']
            ];
        }, $spirits), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function applyFilters() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            document.querySelectorAll('.spirit-row').forEach(row => {
                const name = row.dataset.name || '';
                row.style.display = (!search || name.includes(search)) ? '' : 'none';
            });
        }

        function viewSpirit(id) {
            const s = spiritsData.find(x => x.id === id);
            if (!s) return;

            document.getElementById('viewImage').src = s.image_url;
            document.getElementById('viewName').textContent = s.name;
            document.getElementById('viewShort').textContent = s.short_description;
            document.getElementById('viewFull').textContent = s.full_description;

            showModal('viewModal');
        }

        function openAddModal() {
            document.getElementById('formTitle').textContent = 'Add Spirit';
            document.getElementById('spiritId').value = '';
            document.getElementById('spiritName').value = '';
            document.getElementById('spiritShort').value = '';
            document.getElementById('spiritFull').value = '';
            document.getElementById('spiritSort').value = '0';
            document.getElementById('spiritActive').value = '1';
            document.getElementById('imagePreview').src = '../images/drum_logo.jpg';
            document.getElementById('spiritImage').value = '';
            showModal('formModal');
        }

        function editSpirit(id) {
            const s = spiritsData.find(x => x.id === id);
            if (!s) return;

            document.getElementById('formTitle').textContent = 'Edit Spirit';
            document.getElementById('spiritId').value = s.id;
            document.getElementById('spiritName').value = s.name;
            document.getElementById('spiritShort').value = s.short_description;
            document.getElementById('spiritFull').value = s.full_description;
            document.getElementById('spiritSort').value = s.sort_order;
            document.getElementById('spiritActive').value = s.is_active;
            document.getElementById('imagePreview').src = s.image_url;
            document.getElementById('spiritImage').value = '';

            showModal('formModal');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('imagePreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }

        function saveSpirit(e) {
            e.preventDefault();

            const form = document.getElementById('spiritForm');
            const formData = new FormData(form);
            const id = formData.get('id');

            const saveBtn = document.getElementById('saveBtn');
            const original = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

            const url = id ? 'api/update-spirit.php' : 'api/add-spirit.php';

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(id ? 'Spirit updated successfully!' : 'Spirit added successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Operation failed');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = original;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = original;
            });
        }

        function showModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        function closeModals() {
            document.querySelectorAll('[id$="Modal"]').forEach(m => {
                m.classList.add('hidden');
                m.style.display = 'none';
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('open');
            if (window.innerWidth <= 1024) {
                overlay.classList.toggle('hidden');
            }
        }

        document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);
    </script>
</body>
</html>