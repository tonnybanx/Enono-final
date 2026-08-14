<?php
require_once __DIR__ . '/../config/database-connection.php';

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

function getImagesByCategory(PDO $pdo, string $category): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM general_images
         WHERE category = :category AND is_active = 1
         ORDER BY sort_order ASC"
    );
    $stmt->execute([':category' => $category]);
    return $stmt->fetchAll();
}

function splitIntoParagraphs(?string $text): array
{
    if (!$text) {
        return [];
    }
    $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z])/u', trim($text));
    return array_values(array_filter(array_map('trim', $sentences)));
}

function parseValueCards(?string $text): array
{
    if (!$text) {
        return [];
    }
    $icons = [
        'Authenticity'   => 'fa-gem',
        'Fair Trade'     => 'fa-handshake',
        'Sustainability' => 'fa-leaf',
    ];

    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $cards = [];
    foreach ($lines as $line) {
        $parts = preg_split('/\s*[–-]\s*/u', $line, 2);
        $title = trim($parts[0] ?? $line);
        $desc  = trim($parts[1] ?? '');
        $cards[] = [
            'title'       => $title,
            'description' => $desc,
            'icon'        => $icons[$title] ?? 'fa-star',
        ];
    }
    return $cards;
}

function buildAddress(array $company): string
{
    $parts = array_filter([
        $company['address_line1'] ?? null,
        $company['address_line2'] ?? null,
        $company['city'] ?? null,
        $company['country'] ?? null,
    ]);
    return implode(', ', $parts);
}

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

$company = getCompanyProfile($pdo);

$storyImage    = getImageByCategory($pdo, 'africanacity', 1);
$missionImage  = getImageByCategory($pdo, 'africanacity', 2);
$locationImage = getImageByCategory($pdo, 'location', 1);
$logoImage     = getImageByCategory($pdo, 'logo', 1);

$storyParagraphs = splitIntoParagraphs($company['full_history'] ?? '');
$valueCards       = parseValueCards($company['vision'] ?? '');
$address          = buildAddress($company);

$logoUrl          = resolveImagePath($logoImage['url'] ?? ($company['logo_url'] ?? 'images/drum_logo.jpg'));
$storyImageUrl    = resolveImagePath($storyImage['url'] ?? 'images/africanacity1.jpg');
$missionImageUrl  = resolveImagePath($missionImage['url'] ?? 'images/africanacity2.jpg');
$locationImageUrl = resolveImagePath($locationImage['url'] ?? 'images/location.PNG');

$pageTitle = 'About Us';
$activeNav = 'aboutus';
require __DIR__ . '/../includes/header.php';
?>

    <!-- Our Story -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-2 gap-0 about-grid">
                <!-- Text -->
                <div class="px-6 md:px-16 py-12 md:py-20">
                    <h2 class="text-5xl font-semibold heading-font mb-8">Our Story</h2>
                    <?php foreach ($storyParagraphs as $paragraph): ?>
                        <p class="text-lg text-gray-600 leading-relaxed mt-6 first:mt-0"><?= e($paragraph) ?></p>
                    <?php endforeach; ?>
                </div>

                <!-- Image -->
                <div class="md:pr-0 overflow-hidden">
                    <img src="<?= e($storyImageUrl) ?>"
                         alt="<?= e($storyImage['alt_text'] ?? 'Our Story') ?>"
                         class="story-image w-full h-auto object-cover">
                </div>
            </div>
        </div>
    </section>

    <!-- Our Mission -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-2 gap-0 about-grid">
                <!-- Image -->
                <div class="md:pr-0 order-2 md:order-1 overflow-hidden">
                    <img src="<?= e($missionImageUrl) ?>"
                         alt="<?= e($missionImage['alt_text'] ?? 'Our Mission') ?>"
                         class="story-image w-full h-auto object-cover">
                </div>

                <!-- Text -->
                <div class="px-6 md:px-16 py-12 md:py-20 order-1 md:order-2">
                    <h2 class="text-5xl font-semibold heading-font mb-8">Our Mission</h2>
                    <p class="text-lg text-gray-600 leading-relaxed">
                        <?= e($company['mission'] ?? '') ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Values -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-4xl font-semibold heading-font text-center mb-12">What We Stand For</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($valueCards as $card): ?>
                    <div class="text-center p-8">
                        <div class="w-16 h-16 mx-auto bg-amber-100 rounded-2xl flex items-center justify-center mb-6">
                            <i class="fa-solid <?= e($card['icon']) ?> text-4xl text-amber-600"></i>
                        </div>
                        <h3 class="font-semibold text-xl mb-3"><?= e($card['title']) ?></h3>
                        <p class="text-gray-600"><?= e($card['description']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Location -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-semibold heading-font mb-4">Our Home</h2>
                <p class="text-gray-600"><?= e(($company['city'] ?? '') . ', ' . ($company['country'] ?? '')) ?></p>
            </div>
            <div class="bg-white rounded-3xl overflow-hidden shadow-xl">
                <div class="overflow-hidden">
                    <img src="<?= e($locationImageUrl) ?>"
                         alt="<?= e($locationImage['alt_text'] ?? 'Kampala') ?>"
                         class="story-image w-full h-auto object-cover">
                </div>
                <div class="p-8 text-center">
                    <p class="text-gray-600"><?= e($address) ?></p>
                </div>
            </div>
        </div>
    </section>

<?php require __DIR__ . '/../includes/footer.php'; ?>