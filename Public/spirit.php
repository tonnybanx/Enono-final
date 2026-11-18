<?php
/**
 * Public/spirit.php
 * Single spirit detail page
 * Usage: spirit.php?id=1
 */

require_once __DIR__ . '/../config/database-connection.php';

$spiritId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($spiritId < 1) {
    header('Location: heritage.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, name, short_description, full_description, image_url
    FROM spirits
    WHERE id = ? AND is_active = 1
    LIMIT 1
");
$stmt->execute([$spiritId]);
$spirit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$spirit) {
    header('Location: heritage.php');
    exit;
}

$pageTitle = $spirit['name'] . ' – Spiritual Heritage';
$activeNav = 'heritage';

$cStmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoUrl = !empty($company['logo_url'])
    ? '../' . ltrim($company['logo_url'], '/')
    : '../images/drum_logo.jpg';

// Sacred items (with category)
$itemsStmt = $pdo->prepare("
    SELECT 
        pv.id AS variant_id,
        pv.title AS variant_title,
        pv.price,
        p.id AS product_id,
        p.name AS product_name,
        c.id AS category_id,
        c.name AS category_name,
        COALESCE(
            pv.image_url,
            (SELECT pi.url FROM product_images pi 
             WHERE pi.variant_id = pv.id AND pi.is_primary = 1 LIMIT 1),
            (SELECT pi.url FROM product_images pi 
             WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1)
        ) AS image_url
    FROM product_variants pv
    JOIN products p ON p.id = pv.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE pv.spirit_id = ? AND pv.is_active = 1 AND p.is_active = 1
    ORDER BY c.sort_order ASC, p.name ASC, pv.id ASC
");
$itemsStmt->execute([$spiritId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Unique categories for filters
$categories = [];
foreach ($items as $item) {
    if (!empty($item['category_id']) && !isset($categories[$item['category_id']])) {
        $categories[$item['category_id']] = $item['category_name'];
    }
}

// Prev / Next
$allStmt = $pdo->query("
    SELECT id, name 
    FROM spirits 
    WHERE is_active = 1 
    ORDER BY sort_order ASC, id ASC
");
$allSpirits = $allStmt->fetchAll(PDO::FETCH_ASSOC);

$prevSpirit = null;
$nextSpirit = null;
foreach ($allSpirits as $i => $s) {
    if ((int)$s['id'] === $spiritId) {
        $prevSpirit = $allSpirits[$i - 1] ?? null;
        $nextSpirit = $allSpirits[$i + 1] ?? null;
        break;
    }
}

$spiritImage = !empty($spirit['image_url'])
    ? '../' . ltrim($spirit['image_url'], '/')
    : $logoUrl;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* ── Spirit panel: fixed equal columns ── */
    .spirit-panel {
        display: grid;
        grid-template-columns: 1fr;
        border: 1px solid rgba(232, 169, 43, 0.30);
        border-radius: 1rem;
        overflow: hidden;
        background: #0D0D0D;
    }
    @media (min-width: 768px) {
        .spirit-panel {
            grid-template-columns: 1fr 1fr;
        }
    }

    /* Fixed aspect ratio so every spirit image is the same height */
    .spirit-img-wrap {
        background: #141008;
        aspect-ratio: 1 / 1;          /* square – consistent on all screens */
        width: 100%;
        overflow: hidden;
    }
    @media (min-width: 768px) {
        .spirit-img-wrap {
            aspect-ratio: 4 / 5;      /* slightly taller on desktop, still fixed */
            max-height: 460px;       /* hard cap so it never grows with the text */
        }
    }

    .spirit-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;           /* crop to fill, never stretch */
        object-position: center;
        display: block;
    }

    .spirit-body {
        display: flex;
        flex-direction: column;
        padding: 1.75rem 1.5rem;
    }
    @media (min-width: 768px) {
        .spirit-body {
            padding: 2rem 2.25rem;
            max-height: 460px;       /* match image max-height */
        }
    }

    .spirit-text {
        flex: 1;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding-right: 0.35rem;
        scrollbar-width: thin;
        scrollbar-color: rgba(232, 169, 43, 0.5) transparent;
    }
    .spirit-text::-webkit-scrollbar { width: 4px; }
    .spirit-text::-webkit-scrollbar-track { background: transparent; }
    .spirit-text::-webkit-scrollbar-thumb { background: rgba(232, 169, 43, 0.5); border-radius: 4px; }

    /* ── Item cards: identical thumbnail size ── */
    .item-card {
        border: 1px solid rgba(232, 169, 43, 0.25);
        border-radius: 0.875rem;
        background: #0D0D0D;
        overflow: hidden;
        transition: border-color 0.15s;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .item-card:hover { border-color: #E8A92B; }
    .item-card.hidden { display: none; }

    .item-thumb {
        aspect-ratio: 1 / 1;         /* every product image is square */
        background: #141008;
        overflow: hidden;
    }
    .item-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
    }

    .filter-btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.9rem;
        border-radius: 999px;
        border: 1px solid rgba(232, 169, 43, 0.5);
        background: #000;
        color: #f3f4f6;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
        white-space: nowrap;
    }
    .filter-btn:hover {
        border-color: #E8A92B;
        color: #E8A92B;
    }
    .filter-btn.active {
        background: #E8A92B;
        border-color: #E8A92B;
        color: #000;
        font-weight: 600;
    }

    .nav-link {
        font-size: 0.875rem;
        color: #E8A92B;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .nav-link:hover { text-decoration: underline; color: #F2B84B; }
</style>

<!-- ==================== BACK + SPIRIT PANEL ==================== -->
<section class="bg-[#050505] pt-6 pb-12 md:pb-14">
    <div class="max-w-5xl mx-auto px-6">

        <a href="heritage.php" class="nav-link inline-flex mb-6">
            <i class="fa-solid fa-arrow-left text-[10px]"></i>
            Heritage
        </a>

        <div class="spirit-panel">
            <div class="spirit-img-wrap">
                <img src="<?= e($spiritImage) ?>"
                     alt="<?= e($spirit['name']) ?>"
                     onerror="imgFallback(this, '<?= e($logoUrl) ?>')">
            </div>

            <div class="spirit-body">
                <?php if (!empty($spirit['short_description'])): ?>
                    <p class="text-[11px] tracking-[0.15em] uppercase text-[#E8A92B] mb-3 shrink-0">
                        <?= e($spirit['short_description']) ?>
                    </p>
                <?php endif; ?>

                <h1 class="heading-font text-2xl sm:text-3xl font-semibold text-[#F2B84B] mb-4 shrink-0">
                    <?= e($spirit['name']) ?>
                </h1>

                <div class="spirit-text text-gray-300 leading-[1.8] text-[15px] space-y-4">
                    <?= nl2br(e($spirit['full_description'] ?? $spirit['short_description'] ?? '')) ?>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- ==================== SACRED ITEMS + FILTERS ==================== -->
<section class="bg-black border-t border-[#E8A92B]/20">
    <div class="max-w-5xl mx-auto px-6 py-12 md:py-14">

        <?php if (!empty($items)): ?>
            <div class="mb-7">
                <h2 class="heading-font text-xl font-semibold text-[#F2B84B] mb-1 pl-3 border-l-4 border-[#E8A92B]">
                    Sacred items
                </h2>
                <p class="text-sm text-gray-400">
                    Objects associated with <?= e($spirit['name']) ?>
                </p>
            </div>

            <!-- Category filters -->
            <div class="flex flex-wrap gap-2 mb-8" id="item-filters" role="tablist">
                <button type="button"
                        class="filter-btn active"
                        data-filter="all"
                        onclick="filterItems('all', this)">
                    All
                </button>
                <?php foreach ($categories as $catId => $catName): ?>
                    <button type="button"
                            class="filter-btn"
                            data-filter="<?= (int)$catId ?>"
                            onclick="filterItems('<?= (int)$catId ?>', this)">
                        <?= e($catName) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Items grid -->
            <div id="items-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">
                <?php foreach ($items as $item): 
                    $img = $item['image_url']
                        ? (str_starts_with($item['image_url'], 'http')
                            ? $item['image_url']
                            : '../' . ltrim($item['image_url'], '/'))
                        : $logoUrl;
                    $catId = $item['category_id'] ?? 0;
                ?>
                    <a href="product-details.php?id=<?= (int)$item['product_id'] ?>"
                       class="item-card"
                       data-category="<?= (int)$catId ?>">
                        <div class="item-thumb">
                            <img src="<?= e($img) ?>"
                                 alt="<?= e($item['product_name']) ?>"
                                 onerror="imgFallback(this, '<?= e($logoUrl) ?>')">
                        </div>
                        <div class="p-3.5">
                            <p class="text-sm font-medium text-gray-100 leading-snug line-clamp-2">
                                <?= e($item['product_name'] ?: $item['variant_title']) ?>
                            </p>
                            <?php if (!empty($item['price'])): ?>
                                <p class="text-xs text-[#E8A92B] mt-1.5 font-semibold">
                                    USh <?= number_format((float)$item['price']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <p id="no-items-msg" class="hidden text-sm text-gray-400 mt-6">
                No items in this category.
            </p>

        <?php else: ?>
            <p class="text-sm text-gray-400">
                No sacred items linked to this spirit yet.
            </p>
        <?php endif; ?>

    </div>
</section>

<!-- ==================== PREV / NEXT ==================== -->
<section class="bg-[#050505] border-t border-[#E8A92B]/20">
    <div class="max-w-5xl mx-auto px-6 py-8 md:py-10 flex flex-wrap items-center justify-between gap-4">
        <div>
            <?php if ($prevSpirit): ?>
                <a href="spirit.php?id=<?= (int)$prevSpirit['id'] ?>" class="nav-link">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <?= e($prevSpirit['name']) ?>
                </a>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($nextSpirit): ?>
                <a href="spirit.php?id=<?= (int)$nextSpirit['id'] ?>" class="nav-link">
                    <?= e($nextSpirit['name']) ?>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    // If an image fails to load: try the company logo first,
    // then fall back to a hosted "no image" graphic so nothing
    // ever shows a broken-image icon.
    function imgFallback(imgEl, logoUrl) {
        const stage = imgEl.dataset.fallbackStage || '0';
        if (stage === '0') {
            imgEl.dataset.fallbackStage = '1';
            imgEl.src = logoUrl;
        } else if (stage === '1') {
            imgEl.dataset.fallbackStage = '2';
            imgEl.onerror = null; // stop here, no more retries
            imgEl.src = 'https://placehold.co/400x400/141008/E8A92B?text=No+Image';
        }
    }

    function filterItems(category, btn) {
        document.querySelectorAll('#item-filters .filter-btn').forEach(b => {
            b.classList.remove('active');
        });
        btn.classList.add('active');

        const cards = document.querySelectorAll('#items-grid .item-card');
        let visible = 0;

        cards.forEach(card => {
            const cat = card.getAttribute('data-category');
            if (category === 'all' || cat === String(category)) {
                card.classList.remove('hidden');
                visible++;
            } else {
                card.classList.add('hidden');
            }
        });

        const msg = document.getElementById('no-items-msg');
        if (msg) {
            msg.classList.toggle('hidden', visible > 0);
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>