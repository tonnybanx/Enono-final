<?php
// help.php
require_once '../config/database-connection.php';

// Fetch active categories
$categories = [];
$stmt = $pdo->query("SELECT id, name, display_name FROM faq_categories WHERE is_active = 1 ORDER BY sort_order, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active FAQs (with category slug)
$faqs = [];
$stmt = $pdo->query("
    SELECT f.id, f.question, f.answer, c.name AS category
    FROM faqs f
    JOIN faq_categories c ON c.id = f.category_id
    WHERE f.is_active = 1 AND c.is_active = 1
    ORDER BY c.sort_order, f.sort_order, f.id
");
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - enono MARKET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap');
        
        .heading-font { font-family: 'Playfair Display', sans-serif; }
        
        .faq-item {
            border-bottom: 1px solid #e5e7eb;
        }
        .faq-item:last-child {
            border-bottom: none;
        }
        
        .faq-answer {
            display: none;
            padding: 20px 0 30px 0;
            color: #374151;
            line-height: 1.7;
        }
        .faq-item.open .faq-answer {
            display: block;
        }

        .faq-item.open .fa-chevron-down {
            transform: rotate(180deg);
        }

        .category-item {
            transition: all 0.2s ease;
        }
        .category-item.active {
            background-color: #fef3c7;
            color: #d97706;
            font-weight: 600;
            border-left: 4px solid #fbbf24;
        }
    </style>
</head>
<body class="bg-white">

<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-semibold heading-font">Help Center</h1>
        <p class="text-gray-600 mt-3">Find answers or contact our support team</p>
    </div>

    <!-- Full Width Image -->
    <div class="mb-16 -mx-4 md:-mx-6 lg:-mx-8 overflow-hidden">
        <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=2000&h=620&fit=crop" 
             alt="Happy customer support team - enono MARKET" 
             class="w-full h-[420px] md:h-[520px] object-cover">
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        
        <!-- Recommended Topics (from database) -->
        <div class="lg:col-span-4 relative pr-8">
            <h3 class="font-semibold text-lg mb-6 flex items-center gap-3">
                <i class="fa-solid fa-circle-question text-amber-500"></i> 
                Recommended Topics
            </h3>
            
            <div class="bg-white rounded-3xl p-6 shadow-sm">
                <div onclick="filterCategory(this)" data-category="all" 
                     class="category-item active flex justify-between items-center py-4 px-4 rounded-2xl cursor-pointer mb-1">
                    <span>All Topics</span>
                </div>

                <?php foreach ($categories as $cat): ?>
                <div onclick="filterCategory(this)" 
                     data-category="<?= htmlspecialchars($cat['name']) ?>" 
                     class="category-item flex justify-between items-center py-4 px-4 rounded-2xl cursor-pointer mb-1">
                    <span><?= htmlspecialchars($cat['display_name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="hidden lg:block absolute top-0 bottom-0 right-0 w-px bg-gray-200"></div>
        </div>

        <!-- FAQ Content -->
        <div class="lg:col-span-8 bg-white p-8" id="faq-container"></div>
    </div>

    <!-- Contact -->
    <div class="mt-20 text-center">
        <p class="text-gray-600 mb-6 text-lg">Still can't solve the problem?</p>
        <a href="contactus.php">
            <button 
                class="inline-flex items-center gap-3 bg-white hover:bg-amber-50 border border-gray-300 px-10 py-5 rounded-full text-lg font-medium">
            <i class="fa-solid fa-comment-dots text-amber-500"></i>
            Contact Us
        </button>
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Data from PHP / database
    const faqs = <?= json_encode($faqs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;

    function renderFaqs(filteredFaqs) {
        const container = document.getElementById('faq-container');
        container.innerHTML = '<h2 class="text-2xl font-semibold mb-8">Frequently Asked Questions</h2>';

        if (filteredFaqs.length === 0) {
            container.innerHTML += '<p class="text-gray-500 py-6">No questions found in this category.</p>';
            return;
        }

        filteredFaqs.forEach(faq => {
            const div = document.createElement('div');
            div.className = 'faq-item py-6 cursor-pointer';
            div.innerHTML = `
                <div class="flex justify-between items-start">
                    <p class="font-medium text-lg pr-8">${faq.question}</p>
                    <i class="fa-solid fa-chevron-down text-gray-400 mt-1 transition-transform duration-200"></i>
                </div>
                <div class="faq-answer text-gray-600 leading-relaxed">${faq.answer}</div>
            `;
            container.appendChild(div);

            // Only one question open at a time
            div.addEventListener('click', () => {
                const isCurrentlyOpen = div.classList.contains('open');

                // Close every question
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('open');
                });

                // Open the clicked one only if it was closed
                if (!isCurrentlyOpen) {
                    div.classList.add('open');
                }
            });
        });
    }

    function filterCategory(el) {
        document.querySelectorAll('.category-item').forEach(item => item.classList.remove('active'));
        el.classList.add('active');

        const category = el.getAttribute('data-category');
        const filtered = category === 'all' 
            ? faqs 
            : faqs.filter(f => f.category === category);
        renderFaqs(filtered);
    }

    window.onload = () => renderFaqs(faqs);

   
</script>
</body>
</html>