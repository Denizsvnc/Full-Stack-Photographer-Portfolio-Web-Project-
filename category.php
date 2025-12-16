<?php
require_once 'config.php';

// URL'den kategori slug'ını al
$categorySlug = $_GET['slug'] ?? '';

if (empty($categorySlug)) {
    // Slug yoksa ana sayfaya yönlendir
    header('Location: index.php');
    exit;
}

// Veritabanından kategori bilgilerini çek
$category = null;
$pageTitle = "Kategori - Kürşad Karakuş Digital Portfolio";
$categoryFilter = $categorySlug;

try {
    // Categories tablosu var mı kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    $categoriesTableExists = $stmt->rowCount() > 0;
    
    if ($categoriesTableExists) {
        // Veritabanından kategori bilgilerini çek
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->execute([$categorySlug]);
        $category = $stmt->fetch();
        
        if ($category) {
            $pageTitle = $category['name'] . " - Kürşad Karakuş Digital Portfolio";
            $categoryFilter = $category['slug'];
        }
    }
} catch(PDOException $e) {
    // Hata durumunda slug'ı direkt kullan
}

// project_layouts tablosu var mı kontrol et
$layoutsTableExists = false;
$pageType = $categoryFilter;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_layouts'");
    $layoutsTableExists = $stmt->rowCount() > 0;
} catch(PDOException $e) {
    $layoutsTableExists = false;
}

// Veritabanından kategoriye ait projeleri çek
$projects = [];
try {
    // Önce kategoriye ait projeleri basit bir sorgu ile kontrol et
    if ($categoriesTableExists && $category) {
        // category_id veya category slug ile eşleşen projeleri bul
        $testStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM projects WHERE category_id = ? OR category = ?");
        $testStmt->execute([$category['id'], $category['slug']]);
        $testResult = $testStmt->fetch();
        // Debug için
        if (isset($_GET['debug'])) {
            error_log("Category ID: " . $category['id'] . ", Slug: " . $category['slug'] . ", Project Count: " . ($testResult['cnt'] ?? 0));
        }
    }
    
    if ($layoutsTableExists) {
        // project_layouts tablosundan grid pozisyonlarını çek
        // Önce grid pozisyonu olan projeleri çek
        if ($categoriesTableExists && $category) {
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       COALESCE(pl.grid_x, NULL) as grid_x,
                       COALESCE(pl.grid_y, NULL) as grid_y,
                       COALESCE(pl.grid_w, 4) as grid_w,
                       COALESCE(pl.grid_h, 2) as grid_h
                FROM projects p 
                LEFT JOIN project_layouts pl ON p.id = pl.project_id AND pl.page_type = ?
                WHERE (p.category_id = ? OR p.category = ?) 
                AND pl.grid_x IS NOT NULL AND pl.grid_y IS NOT NULL
                ORDER BY pl.grid_y ASC, pl.grid_x ASC
            ");
            $stmt->execute([$pageType, $category['id'], $categoryFilter]);
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       COALESCE(pl.grid_x, NULL) as grid_x,
                       COALESCE(pl.grid_y, NULL) as grid_y,
                       COALESCE(pl.grid_w, 4) as grid_w,
                       COALESCE(pl.grid_h, 2) as grid_h
                FROM projects p 
                LEFT JOIN project_layouts pl ON p.id = pl.project_id AND pl.page_type = ?
                WHERE p.category = ? 
                AND pl.grid_x IS NOT NULL AND pl.grid_y IS NOT NULL
                ORDER BY pl.grid_y ASC, pl.grid_x ASC
            ");
            $stmt->execute([$pageType, $categoryFilter]);
        }
        $projects = $stmt->fetchAll();
        
        // Eğer hiç grid pozisyonu olan proje yoksa, tüm projeleri çek (grid pozisyonu olmayanlar dahil)
        if (empty($projects)) {
            if ($categoriesTableExists && $category) {
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                           COALESCE(pl.grid_x, NULL) as grid_x,
                           COALESCE(pl.grid_y, NULL) as grid_y,
                           COALESCE(pl.grid_w, 4) as grid_w,
                           COALESCE(pl.grid_h, 2) as grid_h
                    FROM projects p 
                    LEFT JOIN project_layouts pl ON p.id = pl.project_id AND pl.page_type = ?
                    WHERE p.category_id = ? OR p.category = ?
                    ORDER BY display_order ASC, created_at DESC
                ");
                $stmt->execute([$pageType, $category['id'], $categoryFilter]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                           COALESCE(pl.grid_x, NULL) as grid_x,
                           COALESCE(pl.grid_y, NULL) as grid_y,
                           COALESCE(pl.grid_w, 4) as grid_w,
                           COALESCE(pl.grid_h, 2) as grid_h
                    FROM projects p 
                    LEFT JOIN project_layouts pl ON p.id = pl.project_id AND pl.page_type = ?
                    WHERE p.category = ?
                    ORDER BY display_order ASC, created_at DESC
                ");
                $stmt->execute([$pageType, $categoryFilter]);
            }
            $projects = $stmt->fetchAll();
            $layoutsTableExists = false; // Grid layout kullanma
        }
    } else {
        // Eski yöntem (projects tablosundan)
        $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'grid_x'");
        $gridColumnsExist = $stmt->rowCount() > 0;
        
        if ($gridColumnsExist) {
            // Önce grid pozisyonu olan projeleri çek
            if ($categoriesTableExists && $category) {
                $stmt = $pdo->prepare("
                    SELECT * FROM projects 
                    WHERE (category_id = ? OR category = ?) AND grid_x IS NOT NULL AND grid_y IS NOT NULL
                    ORDER BY grid_y ASC, grid_x ASC
                ");
                $stmt->execute([$category['id'], $categoryFilter]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM projects 
                    WHERE category = ? AND grid_x IS NOT NULL AND grid_y IS NOT NULL
                    ORDER BY grid_y ASC, grid_x ASC
                ");
                $stmt->execute([$categoryFilter]);
            }
            $projects = $stmt->fetchAll();
            
            // Eğer hiç grid pozisyonu olan proje yoksa, tüm projeleri çek
            if (empty($projects)) {
                if ($categoriesTableExists && $category) {
                    $stmt = $pdo->prepare("SELECT * FROM projects WHERE category_id = ? OR category = ? ORDER BY display_order ASC, created_at DESC");
                    $stmt->execute([$category['id'], $categoryFilter]);
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM projects WHERE category = ? ORDER BY display_order ASC, created_at DESC");
                    $stmt->execute([$categoryFilter]);
                }
                $projects = $stmt->fetchAll();
            }
        } else {
            // Grid sütunları yoksa normal sıralama
            if ($categoriesTableExists && $category) {
                $stmt = $pdo->prepare("SELECT * FROM projects WHERE category_id = ? OR category = ? ORDER BY display_order ASC, created_at DESC");
                $stmt->execute([$category['id'], $categoryFilter]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM projects WHERE category = ? ORDER BY display_order ASC, created_at DESC");
                $stmt->execute([$categoryFilter]);
            }
            $projects = $stmt->fetchAll();
        }
    }
} catch(PDOException $e) {
    $projects = [];
}

// Eğer proje yoksa mesaj göster (yönlendirme yapma, kullanıcıya bilgi ver)

include 'includes/header.php';
?>

<section class="mainContent">
    <div class="grid">
      <div class="wrap"<?php 
        $useGridLayout = $layoutsTableExists && !empty($projects) && isset($projects[0]['grid_x']) && $projects[0]['grid_x'] !== null;
        if ($useGridLayout) {
            // GridStack ile uyumlu: cellHeight 80px, margin 10px
            // CSS Grid'de auto-rows kullanarak yükseklikleri ayarla
            // Margin ayarlarını veritabanından çek
            $horizontalMargin = 10; // Varsayılan
            $verticalMargin = 10; // Varsayılan
            try {
                $stmt = $pdo->query("SELECT value FROM layout_settings WHERE setting_key = 'horizontal_margin' LIMIT 1");
                $result = $stmt->fetch();
                if ($result) $horizontalMargin = intval($result['value']);
            } catch(PDOException $e) {}
            try {
                $stmt = $pdo->query("SELECT value FROM layout_settings WHERE setting_key = 'vertical_margin' LIMIT 1");
                $result = $stmt->fetch();
                if ($result) $verticalMargin = intval($result['value']);
            } catch(PDOException $e) {}
            echo ' id="grid-layout-wrap" style="display: grid; grid-template-columns: repeat(12, 1fr); grid-auto-rows: 80px; column-gap: ' . $horizontalMargin . 'px; row-gap: ' . $verticalMargin . 'px; width: 100%;"';
        }
      ?>>
        <?php if (empty($projects)): ?>
            <div class="box">
                <h2><?php echo htmlspecialchars($category ? $category['name'] : $categorySlug); ?></h2>
                <p>Bu kategoride henüz proje bulunmamaktadır.</p>
                <?php if (isset($_GET['debug'])): ?>
                    <p style="color: red; font-size: 12px;">
                        Debug: Kategori ID: <?php echo $category ? $category['id'] : 'N/A'; ?>, 
                        Slug: <?php echo htmlspecialchars($categorySlug); ?>, 
                        Category Filter: <?php echo htmlspecialchars($categoryFilter); ?>
                    </p>
                <?php endif; ?>
                <p><a href="index.php">&larr; Ana Sayfaya Dön</a></p>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $project): ?>
                <?php 
                // Grid pozisyonlarını al
                $gridX = isset($project['grid_x']) && $project['grid_x'] !== null ? intval($project['grid_x']) : null;
                $gridY = isset($project['grid_y']) && $project['grid_y'] !== null ? intval($project['grid_y']) : null;
                $gridW = isset($project['grid_w']) && $project['grid_w'] !== null ? intval($project['grid_w']) : null;
                $gridH = isset($project['grid_h']) && $project['grid_h'] !== null ? intval($project['grid_h']) : null;
                
                // Grid style oluştur
                $gridStyle = '';
                if ($layoutsTableExists && $gridX !== null && $gridY !== null) {
                    // Grid pozisyonu varsa CSS Grid ile yerleştir
                    // 12 sütunlu grid sisteminde (GridStack ile uyumlu)
                    $gridColumnStart = $gridX + 1;
                    $gridColumnEnd = $gridX + ($gridW ?? 4) + 1;
                    $gridRowStart = $gridY + 1;
                    $rowSpan = $gridH ?? 2;
                    
                    // GridStack'te cellHeight 80px, margin 10px
                    // CSS Grid'de span kullanarak yüksekliği ayarla
                    $gridStyle = sprintf(
                        'grid-column: %d / %d; grid-row: %d / span %d; margin-bottom: 0;',
                        $gridColumnStart,
                        $gridColumnEnd,
                        $gridRowStart,
                        $rowSpan
                    );
                }
                ?>
                <?php if ($project['category'] === 'film' || !empty($project['video_path']) || !empty($project['vimeo_url'])): ?>
                    <!-- Video Projesi -->
                    <div class="box"<?php echo $gridStyle ? ' style="' . htmlspecialchars($gridStyle) . '"' : ''; ?>>
                        <a href="gallery.php?id=<?php echo $project['id']; ?>">
                            <figure>
                                <?php if ($project['video_path']): ?>
                                    <video autoplay loop muted class="video-canplay">
                                        <source type="video/mp4" src="<?php echo htmlspecialchars($project['video_path']); ?>">
                                    </video>
                                <?php elseif ($project['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($project['image_path']); ?>" alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>">
                                <?php endif; ?>
                            </figure>
                        </a>
                        <h3>
                            <?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>
                            <?php if ($project['date']): ?>
                                <i><!-- <?php echo htmlspecialchars($project['date']); ?> --></i>
                            <?php endif; ?>
                        </h3>
                    </div>
                <?php else: ?>
                    <!-- Görsel Projesi -->
                    <div class="box editorial"<?php echo $gridStyle ? ' style="' . htmlspecialchars($gridStyle) . '"' : ''; ?>>
                        <a href="gallery.php?id=<?php echo $project['id']; ?>">
                            <figure>
                                <?php if ($project['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($project['image_path']); ?>" alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>">
                                <?php endif; ?>
                            </figure>
                        </a>
                        <h3>
                            <?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>
                            <?php if ($project['date']): ?>
                                <i><!-- <?php echo htmlspecialchars($project['date']); ?> --></i>
                            <?php endif; ?>
                        </h3>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
