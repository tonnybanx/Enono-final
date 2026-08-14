<?php
/**
 * Public/heritage.php
 * Spiritual Heritage – overview + scalable spirit links
 */

require_once __DIR__ . '/../config/database-connection.php';

$pageTitle = 'Spiritual Heritage';
$activeNav = 'heritage';

$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoUrl = !empty($company['logo_url'])
    ? '../' . ltrim($company['logo_url'], '/')
    : '../images/drum_logo.jpg';

// Fetch spirits + count of linked active variants
$spiritsStmt = $pdo->query("
    SELECT 
        s.id,
        s.name,
        s.short_description,
        (
            SELECT COUNT(*)
            FROM product_variants pv
            JOIN products p ON p.id = pv.product_id
            WHERE pv.spirit_id = s.id
              AND pv.is_active = 1
              AND p.is_active = 1
        ) AS item_count
    FROM spirits s
    WHERE s.is_active = 1
    ORDER BY s.sort_order ASC, s.id ASC
");
$spirits = $spiritsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .heritage-hero {
        background: #2C1A0F;
        position: relative;
    }
    .heritage-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('https://media.istockphoto.com/id/695152194/photo/african-drums.jpg') center/cover;
        opacity: 0.12;
    }

    .spirit-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.1rem 0;
        border-bottom: 1px solid #e6ddd2;
        text-decoration: none;
        color: inherit;
    }
    .spirit-row:hover .spirit-name {
        color: #8B4513;
    }
    .spirit-name {
        transition: color 0.15s;
    }
    .item-count {
        font-variant-numeric: tabular-nums;
        font-size: 0.8rem;
        color: #a89880;
        min-width: 1.75rem;
        text-align: right;
        flex-shrink: 0;
    }
</style>

<!-- ==================== HERO ==================== -->
<section class="heritage-hero text-white relative">
    <div class="relative z-10 max-w-5xl mx-auto px-6 py-16 md:py-20">
        <p class="text-amber-200/70 text-[11px] tracking-[0.2em] uppercase mb-4">
            Enono · Heritage
        </p>
        <h1 class="heading-font text-3xl sm:text-4xl md:text-5xl font-semibold leading-tight max-w-2xl">
            Spiritual Heritage of Buganda
        </h1>
    </div>
</section>

<!-- ==================== INTRO ==================== -->
<section class="bg-[#F9F5EB]">
    <div class="max-w-5xl mx-auto px-6 py-12 md:py-16">
        <div class="max-w-3xl">
            <p class="text-[11px] tracking-[0.18em] uppercase text-[#8B4513]/80 mb-4">
                The Balubaale
            </p>

            <div class="space-y-4 text-gray-600 leading-[1.75] text-[15px]">
                <p>
                    In the spiritual landscape of Buganda, the Balubaale are not distant myths.  
                    They are living forces — ancestral and natural spirits called upon for protection, 
                    prosperity, fertility, courage, and guidance through life’s transitions.
                </p>
                <p>
                    Each spirit is linked to particular places, elements, and sacred objects.  
                    Pipes, vessels, barkcloth, drums and other items were crafted and used as 
                    quiet acts of respect and remembrance.
                </p>
                <p>
                    Enono presents these objects with care, honouring the continuity between 
                    the artisans, the land, and the ancestral realm.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== SPIRITS LIST (2 columns) ==================== -->
<section class="bg-[#F9F5EB] pb-16 md:pb-20">
    <div class="max-w-5xl mx-auto px-6">

        <h2 class="heading-font text-xl font-semibold text-gray-900 mb-2">
            The Spirits
        </h2>
        <p class="text-sm text-gray-500 mb-8">
            Choose a spirit to read its story and see the objects associated with it.
        </p>

        <?php if (empty($spirits)): ?>
            <p class="text-sm text-gray-500 py-8 border-t border-[#e6ddd2]">
                No spirits have been added yet.
            </p>
        <?php else: ?>
            <nav aria-label="Spirits" class="grid sm:grid-cols-2 gap-x-12 lg:gap-x-16">
                <?php foreach ($spirits as $spirit): ?>
                    <a href="spirit.php?id=<?= (int)$spirit['id'] ?>" class="spirit-row">
                        <div class="min-w-0">
                            <span class="spirit-name heading-font text-[1.1rem] text-gray-900 block">
                                <?= e($spirit['name']) ?>
                            </span>
                            <?php if (!empty($spirit['short_description'])): ?>
                                <span class="text-sm text-gray-500 mt-0.5 block">
                                    <?= e($spirit['short_description']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="item-count">
                            <?= (int)$spirit['item_count'] ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

    </div>
</section>

<!-- ==================== FOOTNOTE ==================== -->
<section class="border-t border-[#e6ddd2] bg-white">
    <div class="max-w-5xl mx-auto px-6 py-10 md:py-12 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
        <p class="text-sm text-gray-500 leading-relaxed max-w-md">
            These spirits and their associated objects remain part of a living tradition.
        </p>
        <a href="shopping.php"
           class="inline-flex items-center justify-center bg-[#8B4513] hover:bg-[#6B3410] text-white px-6 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">
            Explore the Collection
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>