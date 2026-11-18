<?php
require_once __DIR__ . '/../config/database-connection.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function resolveImagePath(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '../') || str_starts_with($path, '/')) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

function getCompanyProfile(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
    return $stmt->fetch() ?: [];
}

function getImageByCategory(PDO $pdo, string $category, int $position = 1): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM general_images
         WHERE category = :category AND is_active = 1
         ORDER BY sort_order ASC
         LIMIT 1 OFFSET :offset"
    );
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $position - 1, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ?: null;
}

function getBlogPosts(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT * FROM blog_posts
         WHERE is_active = 1
         ORDER BY sort_order ASC, published_at DESC"
    );
    return $stmt->fetchAll();
}

function formatPostDate(?string $date): string
{
    if (!$date) {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('F j, Y', $timestamp) : '';
}

$company = getCompanyProfile($pdo);
$logoImage = getImageByCategory($pdo, 'logo', 1);
$logoUrl = resolveImagePath($logoImage['url'] ?? ($company['logo_url'] ?? 'images/drum_logo.jpg'));

$posts = getBlogPosts($pdo);

$pageTitle = 'Blog';
$activeNav = 'blog';
require __DIR__ . '/../includes/header.php';
?>

<div class="mx-auto bg-[#0B0B0B] min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 md:py-16">

        <!-- Header -->
        <div class="text-center mb-16">
            <p class="uppercase tracking-[0.2em] text-sm text-[#D4A017] mb-3">THE STORIES</p>
            <h1 class="text-4xl md:text-5xl font-semibold heading-font text-white">
                Stories of <span class="text-[#D4A017]">Craftsmanship</span>
            </h1>
            <p class="text-lg text-gray-400 mt-5 max-w-2xl mx-auto leading-relaxed">
                Discover the stories behind our products, the hands that create them, and the rich African heritage they carry.
            </p>
        </div>

        <!-- Posts -->
        <div class="space-y-20">
            <?php foreach ($posts as $i => $post): ?>
                <?php $reversed = ($i % 2 === 1); ?>
                <div class="grid md:grid-cols-12 gap-10 items-center">
                    <div class="md:col-span-<?= $reversed ? '7 md:order-2' : '5' ?>">
                        <div class="overflow-hidden rounded-2xl border border-[#2A2A2A] bg-[#141414]">
                            <img src="<?= e(resolveImagePath($post['image_url'])) ?>"
                                 class="w-full max-h-[600px] object-cover transition-transform duration-500 hover:scale-105"
                                 alt="<?= e($post['title']) ?>">
                        </div>
                    </div>

                    <div class="md:col-span-<?= $reversed ? '5 md:order-1' : '7' ?>">
                        <div class="flex items-center gap-3 text-sm text-[#D4A017] mb-4">
                            <span class="px-4 py-1.5 bg-[#E8B923]/10 border border-[#E8B923]/30 text-[#E8B923] rounded-full text-xs font-medium">
                                <?= e($post['category']) ?>
                            </span>
                            <span class="text-gray-500">•</span>
                            <span class="text-gray-400"><?= (int) $post['read_time_minutes'] ?> min read</span>
                        </div>

                        <h3 class="text-3xl md:text-4xl font-semibold leading-tight mb-4 text-white">
                            <?= e($post['title']) ?>
                        </h3>

                        <p class="text-gray-400 text-lg leading-relaxed">
                            <?= e($post['excerpt']) ?>
                        </p>

                        <div class="mt-8 flex items-center gap-4">
                            <img src="<?= e(resolveImagePath($post['author_image_url'])) ?>"
                                 class="w-12 h-12 rounded-full object-cover border-2 border-[#2A2A2A]"
                                 alt="<?= e($post['author_name']) ?>">
                            <div>
                                <p class="font-medium text-white">
                                    <?= e($post['author_name']) ?>
                                    <span class="text-gray-500">•</span>
                                    <span class="text-gray-400"><?= e($post['author_country']) ?></span>
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?= e(formatPostDate($post['published_at'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- CTA -->
        <div class="text-center mt-20">
            <button onclick="alert('More stories about our products and artisans coming soon ✨')"
                    class="bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] px-12 py-4 rounded-2xl text-lg font-medium transition-colors">
                Read More Stories
            </button>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>