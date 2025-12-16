<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$pageTitle = "Sayfa Düzeni";

// Kategori/sayfa seçimi - Dinamik olarak veritabanından çek
$validPages = ['all', 'index']; // Sabit sayfalar
$pageNames = [
    'all' => 'Tüm Projeler',
    'index' => 'Anasayfa'
];

// Veritabanından kategorileri çek
$categories = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    $categoriesTableExists = $stmt->rowCount() > 0;
    
    if ($categoriesTableExists) {
        // Proje sayısı > 0 olan kategorileri çek
        $stmt = $pdo->query("
            SELECT c.*, COUNT(DISTINCT p.id) as project_count 
            FROM categories c 
            LEFT JOIN projects p ON (p.category_id = c.id OR p.category = c.slug)
            GROUP BY c.id, c.name, c.slug, c.description, c.display_order
            HAVING project_count > 0
            ORDER BY c.display_order ASC, c.name ASC
        ");
        $categories = $stmt->fetchAll();
        
        // Kategorileri validPages ve pageNames'e ekle
        foreach ($categories as $cat) {
            $validPages[] = $cat['slug'];
            $pageNames[$cat['slug']] = $cat['name'];
        }
    } else {
        // Categories tablosu yoksa eski sabit kategorileri kullan
        $fallbackCategories = ['editorial', 'advertising', 'film', 'cover'];
        $fallbackNames = [
            'editorial' => 'Editorial',
            'advertising' => 'Advertising',
            'film' => 'Film',
            'cover' => 'Cover'
        ];
        foreach ($fallbackCategories as $cat) {
            $validPages[] = $cat;
            $pageNames[$cat] = $fallbackNames[$cat];
        }
    }
} catch(PDOException $e) {
    // Hata durumunda eski sabit kategorileri kullan
    $fallbackCategories = ['editorial', 'advertising', 'film', 'cover'];
    $fallbackNames = [
        'editorial' => 'Editorial',
        'advertising' => 'Advertising',
        'film' => 'Film',
        'cover' => 'Cover'
    ];
    foreach ($fallbackCategories as $cat) {
        $validPages[] = $cat;
        $pageNames[$cat] = $fallbackNames[$cat];
    }
}

$selectedPage = $_GET['page'] ?? 'all';
// Güvenlik: Sadece geçerli sayfa değerlerine izin ver
if (!in_array($selectedPage, $validPages)) {
    $selectedPage = 'all';
}

// project_layouts tablosu var mı kontrol et
$layoutsTableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_layouts'");
    $layoutsTableExists = $stmt->rowCount() > 0;
} catch(PDOException $e) {
    $layoutsTableExists = false;
}

// Layout settings tablosu var mı kontrol et
$layoutSettingsTableExists = false;
$layoutSettings = ['horizontal_margin' => 'none', 'vertical_margin' => 'medium'];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'layout_settings'");
    $layoutSettingsTableExists = $stmt->rowCount() > 0;
    
    if ($layoutSettingsTableExists) {
        $stmt = $pdo->query("SELECT * FROM layout_settings LIMIT 1");
        $settings = $stmt->fetch();
        if ($settings) {
            $layoutSettings = [
                'horizontal_margin' => $settings['horizontal_margin'] ?? 'none',
                'vertical_margin' => $settings['vertical_margin'] ?? 'medium'
            ];
        }
    }
} catch(PDOException $e) {
    $layoutSettingsTableExists = false;
}

// Margin ayarlarını kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_margins'])) {
    $horizontalMargin = $_POST['horizontal_margin'] ?? 'none';
    $verticalMargin = $_POST['vertical_margin'] ?? 'medium';
    
    if (!in_array($horizontalMargin, ['none', 'medium', 'large'])) {
        $horizontalMargin = 'none';
    }
    if (!in_array($verticalMargin, ['none', 'medium', 'large'])) {
        $verticalMargin = 'medium';
    }
    
    try {
        if ($layoutSettingsTableExists) {
            $stmt = $pdo->prepare("UPDATE layout_settings SET horizontal_margin = ?, vertical_margin = ? WHERE id = 1");
            $stmt->execute([$horizontalMargin, $verticalMargin]);
            
            if ($stmt->rowCount() == 0) {
                // Kayıt yoksa ekle
                $stmt = $pdo->prepare("INSERT INTO layout_settings (horizontal_margin, vertical_margin) VALUES (?, ?)");
                $stmt->execute([$horizontalMargin, $verticalMargin]);
            }
            
            $layoutSettings = [
                'horizontal_margin' => $horizontalMargin,
                'vertical_margin' => $verticalMargin
            ];
        }
    } catch(PDOException $e) {
        // Hata durumunda sessizce devam et
    }
}

// Seçilen sayfa/kategoriye göre projeleri çek
$projects = [];
$whereClause = "";
$params = [];
$pageType = $selectedPage === 'all' ? 'index' : $selectedPage; // 'all' seçildiğinde 'index' kullan

if ($selectedPage !== 'all' && $selectedPage !== 'index') {
    // Belirli bir kategori seçildiyse - hem category_id hem de category (slug) kontrol et
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
        $categoriesTableExists = $stmt->rowCount() > 0;
        
        if ($categoriesTableExists) {
            // Kategori ID'sini bul
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
            $stmt->execute([$selectedPage]);
            $categoryData = $stmt->fetch();
            
            if ($categoryData) {
                // category_id veya category (slug) ile eşleşen projeleri bul
                $whereClause = "WHERE (p.category_id = ? OR p.category = ?)";
                $params[] = $categoryData['id'];
                $params[] = $selectedPage;
            } else {
                // Kategori bulunamadıysa sadece slug ile kontrol et
                $whereClause = "WHERE p.category = ?";
                $params[] = $selectedPage;
            }
        } else {
            // Categories tablosu yoksa sadece slug ile kontrol et
            $whereClause = "WHERE p.category = ?";
            $params[] = $selectedPage;
        }
    } catch(PDOException $e) {
        // Hata durumunda sadece slug ile kontrol et
    $whereClause = "WHERE p.category = ?";
    $params[] = $selectedPage;
    }
}

if ($layoutsTableExists) {
    // project_layouts tablosundan grid pozisyonlarını çek
    $query = "
        SELECT p.*, c.name as category_name,
               COALESCE(pl.grid_x, 0) as grid_x,
               COALESCE(pl.grid_y, 0) as grid_y,
               COALESCE(pl.grid_w, 4) as grid_w,
               COALESCE(pl.grid_h, 2) as grid_h
        FROM projects p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN project_layouts pl ON p.id = pl.project_id AND pl.page_type = ?
        " . $whereClause . "
        ORDER BY pl.grid_y ASC, pl.grid_x ASC, p.display_order ASC
    ";
    
    $params = array_merge([$pageType], $params);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
} else {
    // Eski yöntem (projects tablosundan)
    $query = "
        SELECT p.*, c.name as category_name,
               COALESCE(p.grid_x, 0) as grid_x,
               COALESCE(p.grid_y, 0) as grid_y,
               COALESCE(p.grid_w, 4) as grid_w,
               COALESCE(p.grid_h, 2) as grid_h
        FROM projects p 
        LEFT JOIN categories c ON p.category_id = c.id 
        " . $whereClause . "
        ORDER BY p.grid_y ASC, p.grid_x ASC
    ";
    
    if (!empty($params)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $projects = $stmt->fetchAll();
    } else {
        $projects = $pdo->query($query)->fetchAll();
    }
}

include 'includes/header.php';
?>

<?php if (!$layoutsTableExists): ?>
<div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:bg-brand-500/15 dark:border-brand-500/20">
    <p class="text-sm font-medium text-brand-800 dark:text-brand-400 mb-3">
        project_layouts tablosu henüz oluşturulmamış. Her sayfa/kategori için ayrı grid düzeni saklamak için bu tabloyu oluşturmanız gerekiyor.
    </p>
    <a href="create_project_layouts_table.php" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
        project_layouts Tablosunu Oluştur
    </a>
</div>
<?php endif; ?>

<?php if (!$layoutSettingsTableExists): ?>
<div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:bg-brand-500/15 dark:border-brand-500/20">
    <p class="text-sm font-medium text-brand-800 dark:text-brand-400 mb-3">
        layout_settings tablosu henüz oluşturulmamış. Margin ayarlarını kullanmak için bu tabloyu oluşturmanız gerekiyor.
    </p>
    <a href="create_layout_settings_table.php" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
        layout_settings Tablosunu Oluştur
    </a>
</div>
<?php endif; ?>

<!-- Margin Ayarları -->
<?php if ($layoutSettingsTableExists): ?>
<div class="mb-5 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" id="margin-settings-container">
    <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Margin Ayarları</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Projeler arasındaki yatay ve dikey boşlukları ayarlayın</p>
    </div>
    <div class="p-5 sm:p-6">
        <form method="POST" action="" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Yatay Margin -->
                <div>
                    <label for="horizontal_margin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Yatay Margin
                    </label>
                    <select 
                        id="horizontal_margin" 
                        name="horizontal_margin" 
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                    >
                        <option value="none" <?php echo $layoutSettings['horizontal_margin'] === 'none' ? 'selected' : ''; ?>>Hiç yok (0px)</option>
                        <option value="medium" <?php echo $layoutSettings['horizontal_margin'] === 'medium' ? 'selected' : ''; ?>>Orta (10px)</option>
                        <option value="large" <?php echo $layoutSettings['horizontal_margin'] === 'large' ? 'selected' : ''; ?>>Çok (20px)</option>
                    </select>
                </div>
                
                <!-- Dikey Margin -->
                <div>
                    <label for="vertical_margin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Dikey Margin
                    </label>
                    <select 
                        id="vertical_margin" 
                        name="vertical_margin" 
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                    >
                        <option value="none" <?php echo $layoutSettings['vertical_margin'] === 'none' ? 'selected' : ''; ?>>Hiç yok (0px)</option>
                        <option value="medium" <?php echo $layoutSettings['vertical_margin'] === 'medium' ? 'selected' : ''; ?>>Orta (10px)</option>
                        <option value="large" <?php echo $layoutSettings['vertical_margin'] === 'large' ? 'selected' : ''; ?>>Çok (20px)</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button 
                    type="submit" 
                    name="save_margins"
                    class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Margin Ayarlarını Kaydet
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Sayfa Düzeni</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Projeleri sürükleyerek yeniden düzenleyin, boyutlarını değiştirin</p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Kategori/Sayfa Seçimi -->
                <select id="page-selector" class="h-11 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90">
                    <option value="all" <?php echo $selectedPage === 'all' ? 'selected' : ''; ?>>Tüm Projeler</option>
                    <option value="index" <?php echo $selectedPage === 'index' ? 'selected' : ''; ?>>Anasayfa (Index)</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['slug']); ?>" <?php echo $selectedPage === $cat['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <!-- Fallback: Eski sabit kategoriler -->
                    <option value="editorial" <?php echo $selectedPage === 'editorial' ? 'selected' : ''; ?>>Editorial</option>
                    <option value="advertising" <?php echo $selectedPage === 'advertising' ? 'selected' : ''; ?>>Advertising</option>
                    <option value="film" <?php echo $selectedPage === 'film' ? 'selected' : ''; ?>>Film</option>
                    <option value="cover" <?php echo $selectedPage === 'cover' ? 'selected' : ''; ?>>Cover</option>
                    <?php endif; ?>
                </select>
                <button id="save-layout" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Değişiklikleri Kaydet
                </button>
                <a href="projects.php" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                    Projeler
                </a>
            </div>
        </div>
    </div>
    <div class="p-5 border-t border-gray-100 dark:border-gray-800 sm:p-6">
        <?php 
        // $pageNames zaten yukarıda dinamik olarak oluşturuldu
        $currentPageName = $pageNames[$selectedPage] ?? 'Tüm Projeler';
        ?>
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium"><?php echo htmlspecialchars($currentPageName); ?></span> 
                - <span><?php echo count($projects); ?> proje</span>
            </p>
        </div>
        <?php if (empty($projects)): ?>
            <div class="text-center py-12">
                <p class="text-gray-500 dark:text-gray-400 mb-4">
                    <?php if ($selectedPage === 'all'): ?>
                        Henüz proje eklenmemiş.
                    <?php else: ?>
                        Bu kategori için henüz proje eklenmemiş.
                    <?php endif; ?>
                </p>
                <a href="projects.php?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                    Yeni Proje Ekle
                </a>
            </div>
        <?php else: ?>
            <div id="grid-container" class="grid-stack"></div>
        <?php endif; ?>
    </div>
</div>

<!-- GridStack CSS - Load after Tailwind to override if needed -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.1.2/dist/gridstack.min.css" />
<!-- Inline CSS - Load after GridStack to override GridStack styles -->
<link rel="stylesheet" href="<?php echo ADMIN_URL; ?>/assets/css/inline.css" />
<!-- GridStack JS -->
<script src="https://cdn.jsdelivr.net/npm/gridstack@10.1.2/dist/gridstack-all.js"></script>
<!-- Inline JS must load before page-layout.js for escapeHtml -->
<script src="<?php echo ADMIN_URL; ?>/assets/js/inline.js"></script>
<script src="<?php echo ADMIN_URL; ?>/assets/js/page-layout.js"></script>

    <?php if (!empty($projects)): ?>
<script id="projects-data" type="application/json"><?php echo json_encode($projects); ?></script>
    <?php endif; ?>

<?php include 'includes/footer.php'; ?>

