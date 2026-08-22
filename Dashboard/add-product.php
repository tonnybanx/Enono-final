<?php
// Dashboard/add-product.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'add-product';

// ===================== HANDLE CREATE =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name              = trim($_POST['name'] ?? '');
    $category_id       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $spirit_id         = !empty($_POST['spirit_id']) ? (int)$_POST['spirit_id'] : null;
    $short_description = trim($_POST['short_description'] ?? '');
    $long_description  = trim($_POST['long_description'] ?? '');
    $heritage_body     = trim($_POST['heritage_body'] ?? '');
    $is_active         = isset($_POST['is_active']) ? 1 : 0;
    $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

    $variant_titles = $_POST['variant_title'] ?? [];
    $variant_skus   = $_POST['variant_sku'] ?? [];
    $variant_prices = $_POST['variant_price'] ?? [];
    $variant_stocks = $_POST['variant_stock'] ?? [];
    $variant_attrs  = $_POST['variant_attributes'] ?? [];
    $variant_sizes  = $_POST['variant_sizes'] ?? [];

    if ($name === '') {
        die('Product name is required.');
    }

    $validBlocks = 0;
    foreach ($variant_titles as $i => $title) {
        $title = trim($title);
        $sku   = trim($variant_skus[$i] ?? '');
        $price = (float)($variant_prices[$i] ?? 0);
        if ($title !== '' && $sku !== '' && $price > 0) {
            $validBlocks++;
        }
    }
    if ($validBlocks === 0) {
        die('At least one variant with Title, SKU and Price is required.');
    }

    try {
        $pdo->beginTransaction();

        // 1. Create product (no price / no image)
        $stmt = $pdo->prepare("
            INSERT INTO products 
                (category_id, spirit_id, name, short_description, long_description,
                 heritage_title, heritage_body, base_price, currency, is_active, is_featured)
            VALUES (?, ?, ?, ?, ?, 'Heritage & History', ?, 0, 'UGX', ?, ?)
        ");
        $stmt->execute([
            $category_id,
            $spirit_id,
            $name,
            $short_description,
            $long_description,
            $heritage_body,
            $is_active,
            $is_featured
        ]);
        $productId = (int) $pdo->lastInsertId();

        // 2. Link attribute types (Color + Size)
        $pdo->prepare("
            INSERT INTO product_attribute_types (product_id, attribute_type_id, is_required, sort_order)
            VALUES (?, 1, 1, 1)
        ")->execute([$productId]);

        $pdo->prepare("
            INSERT INTO product_attribute_types (product_id, attribute_type_id, is_required, sort_order)
            VALUES (?, 2, 0, 2)
        ")->execute([$productId]);

        // Helper: resolve or create attribute value
        $resolveAttrValue = function(int $typeId, string $raw) use ($pdo): ?array {
            $raw = trim($raw);
            if ($raw === '') return null;

            // new:Custom Name
            if (str_starts_with($raw, 'new:')) {
                $display = trim(substr($raw, 4));
                if ($display === '') return null;

                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $display));
                $slug = trim($slug, '-');
                if ($slug === '') return null;

                $check = $pdo->prepare("
                    SELECT id, display_value, value 
                    FROM attribute_values 
                    WHERE attribute_type_id = ? AND value = ?
                ");
                $check->execute([$typeId, $slug]);
                $row = $check->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return [
                        'id'    => (int)$row['id'],
                        'label' => $row['display_value'],
                        'slug'  => strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $row['value']), 0, 4))
                    ];
                }

                $pdo->prepare("
                    INSERT INTO attribute_values (attribute_type_id, value, display_value, sort_order)
                    VALUES (?, ?, ?, 99)
                ")->execute([$typeId, $slug, $display]);

                return [
                    'id'    => (int)$pdo->lastInsertId(),
                    'label' => $display,
                    'slug'  => strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $slug), 0, 4))
                ];
            }

            // Existing ID
            $id = (int)$raw;
            if ($id <= 0) return null;

            $s = $pdo->prepare("SELECT id, display_value, value FROM attribute_values WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            return [
                'id'    => (int)$row['id'],
                'label' => $row['display_value'],
                'slug'  => strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $row['value']), 0, 4))
            ];
        };

        // 3. Create variants
        $lowestPrice = null;

        foreach ($variant_titles as $i => $title) {
            $title = trim($title);
            $sku   = trim($variant_skus[$i] ?? '');
            $price = (float)($variant_prices[$i] ?? 0);
            $stock = (int)($variant_stocks[$i] ?? 0);

            if ($title === '' || $sku === '' || $price <= 0) {
                continue;
            }

            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
            }

            // ---- Sizes (multi + new) ----
            $rawSizes = $variant_sizes[$i] ?? [];
            if (!is_array($rawSizes)) $rawSizes = [];

            $selectedSizes = [];
            foreach ($rawSizes as $raw) {
                $resolved = $resolveAttrValue(2, (string)$raw); // type 2 = size
                if ($resolved) {
                    $selectedSizes[] = $resolved;
                }
            }
            if (empty($selectedSizes)) {
                $selectedSizes = [['id' => null, 'label' => '', 'slug' => '']];
            }

            // ---- Other attributes e.g. color (single + new) ----
            $otherAttrs = $variant_attrs[$i] ?? [];
            if (!is_array($otherAttrs)) $otherAttrs = [];

            $resolvedOther = [];
            foreach ($otherAttrs as $typeId => $value) {
                $resolved = $resolveAttrValue((int)$typeId, (string)$value);
                if ($resolved) {
                    $resolvedOther[] = $resolved['id'];
                }
            }

            // ---- Upload images once per group ----
            $uploadedImages = [];
            $fileKey = "variant_images_$i";

            if (!empty($_FILES[$fileKey]['name'][0])) {
                $uploadDir = __DIR__ . '/../images/variants/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                foreach ($_FILES[$fileKey]['tmp_name'] as $j => $tmp) {
                    if ($_FILES[$fileKey]['error'][$j] !== UPLOAD_ERR_OK) continue;

                    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'][$j], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (!in_array($ext, $allowed)) continue;

                    $filename = 'var_' . $productId . '_' . $i . '_' . time() . '_' . $j . '.' . $ext;
                    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                        $uploadedImages[] = 'images/variants/' . $filename;
                    }
                }
            }

            // ---- One variant per size ----
            foreach ($selectedSizes as $sizeInfo) {
                $sizeValueId = $sizeInfo['id'];
                $sizeLabel   = $sizeInfo['label'];
                $sizeSlug    = $sizeInfo['slug'];

                $finalTitle = $sizeLabel ? ($title . ' / ' . $sizeLabel) : $title;
                $finalSku   = $sizeSlug ? ($sku . '-' . $sizeSlug) : $sku;

                // Unique SKU
                $checkSku = $pdo->prepare("SELECT id FROM product_variants WHERE sku = ?");
                $checkSku->execute([$finalSku]);
                if ($checkSku->fetch()) {
                    $finalSku .= '-' . substr((string)time(), -4);
                }

                $firstImage = $uploadedImages[0] ?? null;

                $stmt = $pdo->prepare("
                    INSERT INTO product_variants 
                        (product_id, spirit_id, sku, name, title, price,
                         stock_quantity, low_stock_threshold, is_active, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 5, 1, ?)
                ");
                $stmt->execute([
                    $productId,
                    $spirit_id,
                    $finalSku,
                    $finalTitle,
                    $finalTitle,
                    $price,
                    $stock,
                    $firstImage
                ]);
                $variantId = (int)$pdo->lastInsertId();

                // Link color / other attributes
                foreach ($resolvedOther as $attrValueId) {
                    $pdo->prepare("
                        INSERT INTO product_variant_attributes (variant_id, attribute_value_id)
                        VALUES (?, ?)
                    ")->execute([$variantId, $attrValueId]);
                }

                // Link size
                if ($sizeValueId) {
                    $pdo->prepare("
                        INSERT INTO product_variant_attributes (variant_id, attribute_value_id)
                        VALUES (?, ?)
                    ")->execute([$variantId, $sizeValueId]);
                }

                // Gallery images
                $sort = 1;
                foreach ($uploadedImages as $imgPath) {
                    $pdo->prepare("
                        INSERT INTO product_images 
                            (product_id, variant_id, url, is_primary, sort_order)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $productId,
                        $variantId,
                        $imgPath,
                        $sort === 1 ? 1 : 0,
                        $sort
                    ]);
                    $sort++;
                }
            }
        }

        // base_price = lowest variant price
        if ($lowestPrice !== null) {
            $pdo->prepare("UPDATE products SET base_price = ? WHERE id = ?")
                ->execute([$lowestPrice, $productId]);
        }

        $pdo->commit();
        header("Location: products.php?created=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error creating product: ' . $e->getMessage());
    }
}

// ===================== LOAD FORM DATA =====================
$categories = $pdo->query("
    SELECT id, name 
    FROM categories 
    WHERE is_active = 1 AND deleted_at IS NULL 
    ORDER BY sort_order, name
")->fetchAll(PDO::FETCH_ASSOC);

$spirits = $pdo->query("
    SELECT id, name 
    FROM spirits 
    WHERE is_active = 1 
    ORDER BY sort_order, name
")->fetchAll(PDO::FETCH_ASSOC);

$attrTypes = $pdo->query("
    SELECT id, name, display_name 
    FROM attribute_types 
    ORDER BY sort_order
")->fetchAll(PDO::FETCH_ASSOC);

$attrValues = [];
foreach ($attrTypes as $type) {
    $stmt = $pdo->prepare("
        SELECT id, value, display_value 
        FROM attribute_values 
        WHERE attribute_type_id = ? 
        ORDER BY sort_order, display_value
    ");
    $stmt->execute([$type['id']]);
    $attrValues[$type['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Enono Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { overflow: hidden; background-color: #f8fafc; }
        .main-scroll {
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        .main-scroll::-webkit-scrollbar { width: 6px; }
        .main-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
        }
        .variant-block {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            background: #fafafa;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-hidden">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <div class="main-scroll p-6">
        <div class="max-w-7xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <a href="products.php" class="text-gray-500 hover:text-gray-800">
                    <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Add New Product</h1>
                    <p class="text-gray-500 text-sm">
                        Product info + variants (price, stock, multi-size & images)
                    </p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="productForm">

                <!-- PRODUCT INFO -->
                <div class="card p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1">Product Information</h2>
                    <p class="text-sm text-gray-500 mb-5">No price or images here — those belong to each variant</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Product Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                                   placeholder="e.g. Wooden Ancestral Sculpture">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category_id"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]">
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Spirit <span class="text-gray-400 text-xs">(optional)</span>
                            </label>
                            <select name="spirit_id"
                                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]">
                                <option value="">— None —</option>
                                <?php foreach ($spirits as $sp): ?>
                                    <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-center gap-6 md:col-span-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 accent-[#8B4513]">
                                <span class="text-sm font-medium">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 accent-[#8B4513]">
                                <span class="text-sm font-medium">Featured</span>
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                            <textarea name="short_description" rows="2"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                                      placeholder="Brief summary shown on product cards"></textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Long Description</label>
                            <textarea name="long_description" rows="4"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                                      placeholder="Full product story"></textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Heritage / History</label>
                            <textarea name="heritage_body" rows="3"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                                      placeholder="Cultural meaning, origin, traditional use..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- VARIANTS -->
                <div class="card p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1">Variants</h2>
                    <p class="text-sm text-gray-500 mb-5">
                        Select multiple sizes (or add a new one) to auto-create one variant per size
                    </p>

                    <div id="variantsContainer">
                        <div id="emptyVariants" class="text-center py-12 text-gray-500">
                            <i class="fa fa-box-open text-4xl mb-3 opacity-40"></i>
                            <p class="mb-1 font-medium">No variants added yet</p>
                            <p class="text-sm">You need at least one variant to sell this product</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <button type="button" onclick="addVariant()"
                                class="w-full py-3.5 border-2 border-dashed border-gray-300 rounded-xl text-gray-600 hover:border-[#8B4513] hover:text-[#8B4513] transition flex items-center justify-center gap-2 font-medium">
                            <i class="fa fa-plus"></i>
                            <span id="addVariantBtnText">Add a variant</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pb-8">
                    <a href="products.php"
                       class="px-5 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50 font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                            class="bg-[#8B4513] hover:bg-[#6B3410] text-white px-6 py-2.5 rounded-xl font-medium flex items-center gap-2">
                        <i class="fa fa-plus"></i> Create Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let variantIndex = 0;

const attrTypes = <?= json_encode(array_map(function($t) use ($attrValues) {
    return [
        'id' => (int)$t['id'],
        'name' => $t['name'],
        'display_name' => $t['display_name'],
        'values' => array_values($attrValues[$t['id']] ?? [])
    ];
}, $attrTypes)) ?>;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function addVariant() {
    const emptyState = document.getElementById('emptyVariants');
    if (emptyState) emptyState.style.display = 'none';
    document.getElementById('addVariantBtnText').textContent = 'Add another variant';

    let attrsHtml = '';

    attrTypes.forEach(type => {
        if (type.name === 'size') {
            let checks = '';
            type.values.forEach(val => {
                if (val.value === 'normal') return;
                checks += `
                    <label class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-xl cursor-pointer hover:border-[#8B4513] text-sm bg-white">
                        <input type="checkbox"
                               name="variant_sizes[${variantIndex}][]"
                               value="${val.id}"
                               class="accent-[#8B4513]">
                        ${escapeHtml(val.display_value)}
                    </label>`;
            });

            attrsHtml += `
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        ${escapeHtml(type.display_name)}
                        <span class="text-gray-400 font-normal">(select one or more)</span>
                    </label>
                    <div class="flex flex-wrap gap-3 items-center" id="sizeList_${variantIndex}">
                        ${checks}
                        <button type="button"
                                onclick="showNewSizeInput(${variantIndex})"
                                class="px-3 py-2 border border-dashed border-gray-300 rounded-xl text-sm text-gray-500 hover:border-[#8B4513] hover:text-[#8B4513] transition">
                            + Add new size
                        </button>
                    </div>
                    <div id="newSizeBox_${variantIndex}" class="hidden mt-3 flex flex-wrap gap-2 items-center">
                        <input type="text"
                               id="newSizeValue_${variantIndex}"
                               placeholder="e.g. Extra Large"
                               class="px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#8B4513] min-w-[180px]">
                        <button type="button"
                                onclick="addNewSize(${variantIndex})"
                                class="bg-[#8B4513] hover:bg-[#6B3410] text-white px-4 py-2 rounded-xl text-sm">
                            Add
                        </button>
                        <button type="button"
                                onclick="hideNewSizeInput(${variantIndex})"
                                class="text-gray-500 hover:text-gray-700 text-sm px-2">
                            Cancel
                        </button>
                    </div>
                </div>`;
        } else {
            let options = '<option value="">— Select —</option>';
            type.values.forEach(val => {
                options += `<option value="${val.id}">${escapeHtml(val.display_value)}</option>`;
            });
            options += '<option value="__new__">+ Add new...</option>';

            attrsHtml += `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">${escapeHtml(type.display_name)}</label>
                    <div class="flex gap-2">
                        <select name="variant_attributes[${variantIndex}][${type.id}]"
                                class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513] attr-select"
                                onchange="toggleNewAttr(this)">
                            ${options}
                        </select>
                        <input type="text"
                               class="hidden flex-1 px-4 py-2.5 border border-[#8B4513]/40 rounded-xl new-attr-input"
                               placeholder="Type new value">
                    </div>
                </div>`;
        }
    });

    const html = `
    <div class="variant-block" data-index="${variantIndex}">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-medium text-gray-800">Variant group #${variantIndex + 1}</h3>
            <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-sm">
                <i class="fa fa-trash"></i> Remove
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Title <span class="text-red-500">*</span>
                </label>
                <input type="text" name="variant_title[${variantIndex}]" required
                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                       placeholder="e.g. Wooden Sculpture – Natural">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    SKU base <span class="text-red-500">*</span>
                </label>
                <input type="text" name="variant_sku[${variantIndex}]" required
                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                       placeholder="e.g. SCUL-NAT">
                <p class="text-xs text-gray-400 mt-1">Size code is appended automatically</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Price (UGX) <span class="text-red-500">*</span>
                </label>
                <input type="number" name="variant_price[${variantIndex}]" required min="1" step="100"
                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]"
                       placeholder="e.g. 110000">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Stock Quantity <span class="text-red-500">*</span>
                </label>
                <input type="number" name="variant_stock[${variantIndex}]" required min="0" value="5"
                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]">
            </div>

            ${attrsHtml}

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Variant Images</label>
                <input type="file" name="variant_images_${variantIndex}[]" multiple accept="image/*"
                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-[#8B4513]">
                <p class="text-xs text-gray-400 mt-1">Shared across all sizes in this group. First image is primary.</p>
            </div>
        </div>
    </div>`;

    document.getElementById('variantsContainer').insertAdjacentHTML('beforeend', html);
    variantIndex++;
}

function removeVariant(btn) {
    btn.closest('.variant-block').remove();
    if (document.querySelectorAll('.variant-block').length === 0) {
        document.getElementById('emptyVariants').style.display = 'block';
        document.getElementById('addVariantBtnText').textContent = 'Add a variant';
        variantIndex = 0;
    }
}

function toggleNewAttr(select) {
    const input = select.parentElement.querySelector('.new-attr-input');
    if (select.value === '__new__') {
        input.classList.remove('hidden');
        input.focus();
    } else {
        input.classList.add('hidden');
        input.value = '';
    }
}

function showNewSizeInput(index) {
    document.getElementById('newSizeBox_' + index).classList.remove('hidden');
    document.getElementById('newSizeValue_' + index).focus();
}

function hideNewSizeInput(index) {
    document.getElementById('newSizeBox_' + index).classList.add('hidden');
    document.getElementById('newSizeValue_' + index).value = '';
}

function addNewSize(index) {
    const input = document.getElementById('newSizeValue_' + index);
    const name = (input.value || '').trim();
    if (!name) {
        alert('Please enter a size name');
        return;
    }

    const label = document.createElement('label');
    label.className = 'flex items-center gap-2 px-3 py-2 border border-[#8B4513]/40 bg-amber-50 rounded-xl cursor-pointer text-sm';
    label.innerHTML = `
        <input type="checkbox"
               name="variant_sizes[${index}][]"
               value="new:${escapeHtml(name)}"
               class="accent-[#8B4513]"
               checked>
        ${escapeHtml(name)}
        <span class="text-[10px] text-[#8B4513] ml-1">new</span>
    `;

    const list = document.getElementById('sizeList_' + index);
    const addBtn = list.querySelector('button');
    list.insertBefore(label, addBtn);

    hideNewSizeInput(index);
}

document.getElementById('productForm').addEventListener('submit', function(e) {
    document.querySelectorAll('.attr-select').forEach(select => {
        if (select.value === '__new__') {
            const input = select.parentElement.querySelector('.new-attr-input');
            select.value = (input && input.value.trim()) ? 'new:' + input.value.trim() : '';
        }
    });

    if (document.querySelectorAll('.variant-block').length === 0) {
        e.preventDefault();
        alert('Please add at least one variant before saving.');
    }
});
</script>
</body>
</html>