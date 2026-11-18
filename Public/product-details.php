<?php
// Public/product-details.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId < 1) {
    header('Location: shopping.php');
    exit;
}

// ── Product
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.is_active = 1
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: shopping.php');
    exit;
}

$pageTitle = $product['name'];
$activeNav = 'shop';

// Company
$cStmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$logoUrl = !empty($company['logo_url'])
    ? '../' . ltrim($company['logo_url'], '/')
    : '../images/drum_logo.jpg';

// ── Variants + Spirit
$vStmt = $pdo->prepare("
    SELECT 
        pv.*,
        s.name AS spirit_name,
        s.short_description AS spirit_short,
        s.full_description AS spirit_full
    FROM product_variants pv
    LEFT JOIN spirits s ON s.id = pv.spirit_id
    WHERE pv.product_id = ? AND pv.is_active = 1
    ORDER BY pv.id
");
$vStmt->execute([$productId]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($variants)) {
    header('Location: shopping.php');
    exit;
}

// ── Images per variant (from product_images table – currently empty, but kept for future use)
$imagesByVariant = [];
$imgStmt = $pdo->prepare("
    SELECT variant_id, url, alt_text, is_primary, sort_order
    FROM product_images
    WHERE product_id = ?
    ORDER BY is_primary DESC, sort_order ASC, id ASC
");
$imgStmt->execute([$productId]);

foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $img) {
    $vid = $img['variant_id'] ?: 0;
    $url = trim($img['url'] ?? '');

    if ($url !== '' && !str_starts_with($url, 'http')) {
        $url = ltrim($url, '/');
        $url = preg_replace('#^\.\./#', '', $url);
        $url = '../' . $url;
    }

    $imagesByVariant[$vid][] = [
        'url'        => $url ?: '../images/drum_logo.jpg',
        'alt'        => $img['alt_text'] ?: $product['name'],
        'is_primary' => (int)$img['is_primary']
    ];
}

// Helper: normalise a relative image path
function normaliseImageUrl(?string $url): string {
    $url = trim($url ?? '');
    if ($url === '') return '../images/drum_logo.jpg';
    if (str_starts_with($url, 'http')) return $url;
    $url = ltrim($url, '/');
    $url = preg_replace('#^\.\./#', '', $url);
    return '../' . $url;
}

// ── Attribute types
$atStmt = $pdo->prepare("
    SELECT at.id, at.name, at.display_name, pat.sort_order
    FROM product_attribute_types pat
    JOIN attribute_types at ON at.id = pat.attribute_type_id
    WHERE pat.product_id = ?
    ORDER BY pat.sort_order, at.id
");
$atStmt->execute([$productId]);
$attributeTypes = $atStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Attribute values
$attrValues = [];
$typeIds = array_column($attributeTypes, 'id');
if ($typeIds) {
    $ph = implode(',', array_fill(0, count($typeIds), '?'));
    $avStmt = $pdo->prepare("
        SELECT id, attribute_type_id, value, display_value, sort_order
        FROM attribute_values
        WHERE attribute_type_id IN ($ph)
        ORDER BY sort_order, id
    ");
    $avStmt->execute($typeIds);
    foreach ($avStmt->fetchAll(PDO::FETCH_ASSOC) as $av) {
        $attrValues[$av['attribute_type_id']][] = $av;
    }
}

// ── Variant → attribute values
$variantAttrs = [];
$vIds = array_column($variants, 'id');
if ($vIds) {
    $ph = implode(',', array_fill(0, count($vIds), '?'));
    $pvaStmt = $pdo->prepare("
        SELECT variant_id, attribute_value_id
        FROM product_variant_attributes
        WHERE variant_id IN ($ph)
    ");
    $pvaStmt->execute($vIds);
    foreach ($pvaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $variantAttrs[$row['variant_id']][] = (int)$row['attribute_value_id'];
    }
}

// ── Heritage content
$contentStmt = $pdo->prepare("
    SELECT content_type, title, body
    FROM product_content
    WHERE product_id = ?
    ORDER BY sort_order, id
");
$contentStmt->execute([$productId]);
$contents = $contentStmt->fetchAll(PDO::FETCH_ASSOC);
$heritageContent = null;
foreach ($contents as $c) {
    if ($c['content_type'] === 'heritage') {
        $heritageContent = $c;
        break;
    }
}

// ── JS payload + default image
$jsVariants = [];
$defaultImage = '../images/drum_logo.jpg';

foreach ($variants as $v) {
    // Prefer images from product_images table …
    $imgs = $imagesByVariant[$v['id']] ?? [];

    // … otherwise fall back to the variant’s own image_url column
    if (empty($imgs)) {
        $fallback = normaliseImageUrl($v['image_url'] ?? null);
        $imgs = [[
            'url'        => $fallback,
            'alt'        => $v['title'] ?: $product['name'],
            'is_primary' => 1
        ]];
    }

    $primary = $imgs[0]['url'];
    foreach ($imgs as $im) {
        if (!empty($im['is_primary'])) {
            $primary = $im['url'];
            break;
        }
    }

    if ($defaultImage === '../images/drum_logo.jpg' && $primary) {
        $defaultImage = $primary;
    }

    $jsVariants[] = [
        'id'           => (int)$v['id'],
        'sku'          => $v['sku'],
        'title'        => $v['title'],
        'price'        => (float)$v['price'],
        'stock'        => (int)$v['stock_quantity'],
        'spirit_id'    => $v['spirit_id'] ? (int)$v['spirit_id'] : null,
        'spirit_name'  => $v['spirit_name'] ?? null,
        'spirit_short' => $v['spirit_short'] ?? null,
        'spirit_full'  => $v['spirit_full'] ?? null,
        'image'        => $primary,
        'images'       => array_column($imgs, 'url'),
        'attr_ids'     => $variantAttrs[$v['id']] ?? []
    ];
}

$hasSpirits = false;
foreach ($jsVariants as $v) {
    if ($v['spirit_id']) {
        $hasSpirits = true;
        break;
    }
}

$currency = $product['currency'] ?? 'UGX';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .gallery-wrapper {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .thumbs-vertical {
        display: none;
        flex-direction: column;
        gap: 0.6rem;
        max-height: 520px;
        overflow-y: auto;
        padding-right: 4px;
        scrollbar-width: thin;
    }
    .thumbs-vertical::-webkit-scrollbar { width: 4px; }
    .thumbs-vertical::-webkit-scrollbar-thumb { background: #2A2A2A; border-radius: 4px; }

    .thumb {
        width: 72px;
        height: 72px;
        border-radius: 0.6rem;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        background: #1C1C1C;
        flex-shrink: 0;
        transition: all 0.15s ease;
    }
    .thumb:hover { transform: translateY(-2px); border-color: #D4A017; }
    .thumb.selected {
        border-color: #E8B923;
        box-shadow: 0 0 0 2px rgba(232, 185, 35, 0.25);
    }
    .thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .main-image-box {
        background: #1C1C1C;
        border: 1px solid #2A2A2A;
        border-radius: 1rem;
        overflow: hidden;
        aspect-ratio: 1/1;
        max-height: 520px;
        flex: 1;
    }
    .main-image-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .thumbs-horizontal {
        display: flex;
        gap: 0.6rem;
        overflow-x: auto;
        padding-bottom: 4px;
        margin-top: 0.75rem;
        scrollbar-width: thin;
    }
    .thumbs-horizontal::-webkit-scrollbar { height: 4px; }
    .thumbs-horizontal::-webkit-scrollbar-thumb { background: #2A2A2A; border-radius: 4px; }

    @media (min-width: 768px) {
        .thumbs-vertical { display: flex; }
        .thumbs-horizontal { display: none; }
    }

    .form-select {
        width: 100%;
        background: #141414;
        border: 1px solid #2A2A2A;
        border-radius: 0.75rem;
        padding: 0.75rem 2.25rem 0.75rem 1rem;
        font-size: 0.95rem;
        color: #ffffff;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23E8B923'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1.1em;
    }
    .form-select:focus {
        outline: none;
        border-color: #E8B923;
        box-shadow: 0 0 0 3px rgba(232, 185, 35, 0.12);
    }
    .form-select option {
        background: #141414;
        color: #ffffff;
    }

    .qty-btn {
        width: 40px; height: 40px;
        display: flex; align-items: center; justify-content: center;
        border: 1px solid #2A2A2A; background: #141414; color: #ffffff;
    }
    .qty-btn:hover { background: #1C1C1C; border-color: #D4A017; }
    .add-btn { background: #E8B923; color: #0B0B0B; transition: background 0.2s; }
    .add-btn:hover { background: #F0C94A; }
    .add-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .heritage-panel {
        background: #0B0B0B;
        position: relative;
        overflow: hidden;
        border-top: 1px solid #2A2A2A;
    }
    .heritage-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('../images/africanacity1.jpg') center/cover;
        opacity: 0.12;
    }
</style>

<section class="bg-[#0B0B0B] pt-6 pb-14 md:pb-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">

        <a href="javascript:history.back()" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-[#D4A017] mb-6 transition-colors">
            <i class="fa-solid fa-arrow-left text-[10px]"></i> Back
        </a>

        <div class="grid md:grid-cols-2 gap-8 lg:gap-12">

            <!-- LEFT: Thumbnails + Main Image -->
            <div>
                <div class="gallery-wrapper">
                    <div class="thumbs-vertical" id="thumbs-vertical"></div>
                    <div class="main-image-box">
                        <img id="main-image"
                             src="<?= htmlspecialchars($defaultImage) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             onerror="this.src='../images/drum_logo.jpg'">
                    </div>
                </div>
                <div class="thumbs-horizontal" id="thumbs-horizontal"></div>
            </div>

            <!-- RIGHT: Product info -->
            <div class="flex flex-col">
                <?php if (!empty($product['category_name'])): ?>
                    <p class="text-[11px] tracking-[0.15em] uppercase text-[#D4A017] mb-2">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </p>
                <?php endif; ?>

                <h1 id="product-title" class="heading-font text-2xl sm:text-3xl font-semibold text-white leading-snug">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <p id="product-price" class="text-2xl font-semibold text-[#D4A017] mt-3">
                    <?= htmlspecialchars($currency) ?> <?= number_format((float)$product['base_price']) ?>
                </p>

                <p class="text-gray-400 text-[15px] leading-relaxed mt-4">
                    <?= htmlspecialchars($product['short_description'] ?: $product['long_description'] ?: '') ?>
                </p>

                <!-- SPIRIT -->
                <?php if ($hasSpirits): ?>
                <div class="mt-6">
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">
                        Choose Spirit
                    </label>
                    <select id="spirit-select" class="form-select" onchange="onSpiritChange()">
                        <option value="">Select a Spirit</option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- COLOR / MATERIAL -->
                <div class="mt-5" id="color-dropdown-wrapper" style="display:none;">
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">
                        Choose Color / Material
                    </label>
                    <select id="color-select" class="form-select" onchange="onColorDropdownChange()">
                        <option value="">Select Color / Material</option>
                    </select>
                </div>

                <!-- SIZE -->
                <div class="mt-5" id="size-wrapper" style="display:none;">
                    <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">
                        Choose Size
                    </label>
                    <select id="size-select" class="form-select" onchange="onSizeChange()">
                        <option value="">Select Size</option>
                    </select>
                </div>

                <!-- Quantity -->
                <div class="flex items-center gap-4 mt-6">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Qty</span>
                    <div class="flex items-center">
                        <button type="button" class="qty-btn rounded-l-lg" onclick="changeQty(-1)">−</button>
                        <span id="quantity" class="w-12 text-center text-sm font-medium border-y border-[#2A2A2A] py-2 text-white bg-[#141414]">1</span>
                        <button type="button" class="qty-btn rounded-r-lg" onclick="changeQty(1)">+</button>
                    </div>
                    <span id="stock-note" class="text-xs text-gray-500"></span>
                </div>

                <button id="add-btn" type="button" onclick="addToCart()"
                        class="add-btn mt-8 w-full py-3.5 rounded-xl font-medium text-sm flex items-center justify-center gap-2" disabled>
                    <i class="fa-solid fa-cart-plus text-sm"></i>
                    Add to Cart
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Living Legacy -->
<section class="heritage-panel text-white">
    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 py-14 md:py-16">
        <p class="text-[#D4A017] text-[11px] tracking-[0.18em] uppercase mb-3">A Living Legacy</p>
        <h2 class="heading-font text-2xl sm:text-3xl font-semibold max-w-xl mb-10">
            <?= htmlspecialchars($product['long_description'] ?: 'More than an object — a vessel of spirit and memory.') ?>
        </h2>

        <div class="grid md:grid-cols-2 gap-5 md:gap-6">
            <div class="bg-[#141414] border border-[#2A2A2A] rounded-xl p-6 md:p-7">
                <h3 class="font-semibold text-lg mb-3 text-white">
                    <?= htmlspecialchars($heritageContent['title'] ?? 'Heritage & History') ?>
                </h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    <?php if ($heritageContent): ?>
                        <?= nl2br(htmlspecialchars($heritageContent['body'])) ?>
                    <?php else: ?>
                        Every piece is carefully crafted using techniques passed down through generations of Ugandan artisans, carrying the stories and spirit of the land.
                    <?php endif; ?>
                </p>
            </div>

            <div class="bg-[#141414] border border-[#2A2A2A] rounded-xl p-6 md:p-7" id="spirit-panel">
                <h3 class="font-semibold text-lg mb-3 text-white">Spirits & Meaning</h3>
                <p id="spirit-text" class="text-gray-400 text-sm leading-relaxed">
                    Select a spirit above to learn about its meaning and significance.
                </p>
            </div>
        </div>
    </div>
</section>

<script>
const variants   = <?= json_encode($jsVariants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const attrTypes  = <?= json_encode($attributeTypes, JSON_UNESCAPED_UNICODE) ?>;
const attrValues = <?= json_encode($attrValues, JSON_UNESCAPED_UNICODE) ?>;
const currency   = <?= json_encode($currency) ?>;
const hasSpirits = <?= $hasSpirits ? 'true' : 'false' ?>;

let currentVariant = null;
let quantity = 1;
let selectedColorId = null;
let selectedSizeId  = null;
let currentColorList = [];

const colorType   = attrTypes.find(t => t.name.toLowerCase() === 'color');
const colorTypeId = colorType ? colorType.id : null;
const sizeType    = attrTypes.find(t => t.name.toLowerCase() === 'size');
const sizeTypeId  = sizeType ? sizeType.id : null;

function getSelectedSpirit() {
    const el = document.getElementById('spirit-select');
    return el && el.value ? Number(el.value) : null;
}

function getVariantsForSpirit(spiritId) {
    if (!hasSpirits) return variants;
    if (!spiritId) return [];
    return variants.filter(v => v.spirit_id === spiritId);
}

function findVariant(spiritId, colorId, sizeId) {
    return variants.find(v => {
        if (hasSpirits && spiritId && v.spirit_id !== spiritId) return false;
        if (colorId && !(v.attr_ids || []).includes(colorId)) return false;
        if (sizeId  && !(v.attr_ids || []).includes(sizeId))  return false;
        return true;
    });
}

function populateSpirits() {
    if (!hasSpirits) return;
    const select = document.getElementById('spirit-select');
    const spirits = {};
    variants.forEach(v => {
        if (v.spirit_id) spirits[v.spirit_id] = v.spirit_name;
    });
    select.innerHTML = '<option value="">Select a Spirit</option>';
    Object.entries(spirits).forEach(([id, name]) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = name;
        select.appendChild(opt);
    });
}

function onSpiritChange() {
    const spiritId = getSelectedSpirit();
    selectedColorId = null;
    selectedSizeId  = null;
    currentVariant  = null;

    document.getElementById('add-btn').disabled = true;
    document.getElementById('stock-note').textContent = '';

    if (!spiritId) {
        document.getElementById('color-dropdown-wrapper').style.display = 'none';
        document.getElementById('size-wrapper').style.display = 'none';
        document.getElementById('thumbs-vertical').innerHTML = '';
        document.getElementById('thumbs-horizontal').innerHTML = '';
        return;
    }
    populateColorAndSize(spiritId);
}

function populateColorAndSize(spiritId) {
    const scopedVariants = getVariantsForSpirit(spiritId);
    const colorDropdownWrapper = document.getElementById('color-dropdown-wrapper');
    const colorSelect = document.getElementById('color-select');
    const sizeWrapper = document.getElementById('size-wrapper');
    const sizeSelect  = document.getElementById('size-select');

    // ----- Colors -----
    const colorMap = {};
    if (colorTypeId) {
        scopedVariants.forEach(v => {
            (v.attr_ids || []).forEach(aid => {
                const colorVal = (attrValues[colorTypeId] || []).find(av => Number(av.id) === Number(aid));
                if (colorVal && !colorMap[aid]) {
                    colorMap[aid] = {
                        id:    Number(aid),
                        name:  colorVal.display_value || colorVal.value,
                        image: v.image || '../images/drum_logo.jpg'
                    };
                }
            });
        });
    }
    currentColorList = Object.values(colorMap);

    colorSelect.innerHTML = '<option value="">Select Color / Material</option>';
    currentColorList.forEach(color => {
        const opt = document.createElement('option');
        opt.value = color.id;
        opt.textContent = color.name;
        colorSelect.appendChild(opt);
    });
    colorDropdownWrapper.style.display = currentColorList.length ? 'block' : 'none';

    renderThumbnails(currentColorList, spiritId);

    // ----- Sizes -----
    const sizes = {};
    if (sizeTypeId) {
        scopedVariants.forEach(v => {
            (v.attr_ids || []).forEach(aid => {
                const sizeVal = (attrValues[sizeTypeId] || []).find(av => Number(av.id) === Number(aid));
                if (sizeVal) sizes[aid] = sizeVal.display_value || sizeVal.value;
            });
        });
    }

    sizeSelect.innerHTML = '<option value="">Select Size</option>';
    Object.entries(sizes).forEach(([id, name]) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = name;
        sizeSelect.appendChild(opt);
    });
    sizeWrapper.style.display = Object.keys(sizes).length ? 'block' : 'none';

    // ===== AUTO-SELECT FIRST COLOR + FIRST SIZE =====
    if (currentColorList.length >= 1) {
        selectColor(currentColorList[0].id, spiritId);
    }

    if (Object.keys(sizes).length >= 1) {
        const firstSizeId = Number(Object.keys(sizes)[0]);
        sizeSelect.value = firstSizeId;
        selectedSizeId = firstSizeId;
        trySelectVariant(spiritId);
    }

    if (currentColorList.length === 0 && Object.keys(sizes).length === 0 && scopedVariants.length === 1) {
        selectVariant(scopedVariants[0]);
    } else {
        updateSpiritPanel(scopedVariants[0] || null);
    }
}

function renderThumbnails(colorList, spiritId) {
    const vertical   = document.getElementById('thumbs-vertical');
    const horizontal = document.getElementById('thumbs-horizontal');
    vertical.innerHTML = '';
    horizontal.innerHTML = '';

    colorList.forEach(color => {
        const makeThumb = () => {
            const div = document.createElement('div');
            div.className = 'thumb';
            div.dataset.colorId = color.id;
            div.innerHTML = `<img src="${color.image}" alt="${color.name}" onerror="this.src='../images/drum_logo.jpg'">`;
            div.onclick = () => selectColor(color.id, spiritId);
            return div;
        };
        vertical.appendChild(makeThumb());
        horizontal.appendChild(makeThumb());
    });
}

function onColorDropdownChange() {
    const colorSelect = document.getElementById('color-select');
    const colorId = colorSelect.value ? Number(colorSelect.value) : null;
    if (colorId) selectColor(colorId, getSelectedSpirit());
}

function selectColor(colorId, spiritId) {
    selectedColorId = colorId;
    const colorSelect = document.getElementById('color-select');
    if (colorSelect) colorSelect.value = colorId;

    document.querySelectorAll('.thumb').forEach(t => {
        t.classList.toggle('selected', Number(t.dataset.colorId) === colorId);
    });

    trySelectVariant(spiritId);
}

function onSizeChange() {
    const sizeSelect = document.getElementById('size-select');
    selectedSizeId = sizeSelect && sizeSelect.value ? Number(sizeSelect.value) : null;
    trySelectVariant(getSelectedSpirit());
}

function trySelectVariant(spiritId) {
    const match = findVariant(spiritId, selectedColorId, selectedSizeId);
    if (match) {
        selectVariant(match);
    } else {
        currentVariant = null;
        document.getElementById('add-btn').disabled = true;
        document.getElementById('stock-note').textContent = 'Combination not available';
    }
}

function selectVariant(v) {
    currentVariant = v;
    document.getElementById('product-title').textContent = v.title || '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>';
    document.getElementById('product-price').textContent = currency + ' ' + Number(v.price).toLocaleString();

    const mainImg = document.getElementById('main-image');
    mainImg.src = v.image;
    mainImg.alt = v.title;

    const stockNote = document.getElementById('stock-note');
    const addBtn = document.getElementById('add-btn');
    if (v.stock <= 0) {
        stockNote.textContent = 'Out of stock';
        addBtn.disabled = true;
    } else {
        stockNote.textContent = v.stock <= 5 ? `Only ${v.stock} left` : '';
        addBtn.disabled = false;
    }
    updateSpiritPanel(v);
}

function updateSpiritPanel(v) {
    const text = document.getElementById('spirit-text');
    if (text) {
        text.innerHTML = (v && v.spirit_full)
            ? v.spirit_full.replace(/\n/g, '<br>')
            : 'Select a spirit above to learn about its meaning and significance.';
    }
}

function changeQty(delta) {
    quantity = Math.max(1, quantity + delta);
    if (currentVariant && currentVariant.stock > 0) {
        quantity = Math.min(quantity, currentVariant.stock);
    }
    document.getElementById('quantity').textContent = quantity;
}

async function addToCart() {
    if (!currentVariant || currentVariant.stock <= 0) return;
    try {
        const res = await fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ variant_id: currentVariant.id, quantity }),
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
            const countEl = document.getElementById('cart-count');
            if (countEl) countEl.textContent = data.cart_count;
            if (typeof showToast === 'function') showToast('Added to cart');
            else alert('Added to cart');
        } else {
            alert(data.message || 'Could not add to cart');
        }
    } catch (err) {
        console.error(err);
        alert('Network error');
    }
}

// ---------- Init ----------
populateSpirits();

if (hasSpirits) {
    const spiritSelect = document.getElementById('spirit-select');
    // Always select the first spirit
    if (spiritSelect && spiritSelect.options.length > 1) {
        spiritSelect.selectedIndex = 1;
        onSpiritChange();   // this will also select the first color
    }
} else {
    populateColorAndSize(null);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>