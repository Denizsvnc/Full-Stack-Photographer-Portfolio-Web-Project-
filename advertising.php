<?php
require_once 'config.php';

$pageTitle = "Advertising - Kürşad Karakuş Digital Portfolio";
$categoryFilter = 'advertising';

// project_layouts tablosu var mı kontrol et
$layoutsTableExists = false;
$pageType = $categoryFilter; // advertising
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_layouts'");
    $layoutsTableExists = $stmt->rowCount() > 0;
} catch(PDOException $e) {
    $layoutsTableExists = false;
}

// Veritabanından kategoriye ait projeleri çek
try {
    if ($layoutsTableExists) {
        // project_layouts tablosundan grid pozisyonlarını çek
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COALESCE(pl.grid_x, NULL) as grid_x,
                   COALESCE(pl.grid_y, NULL) as grid_y,
                   COALESCE(pl.grid_w, 4) as grid_w,
                   COALESCE(pl.grid_h, 2) as grid_h
            FROM projects p 
            LEFT JOIN project_layouts pl ON p.id = pl.project_id AND pl.page_type = ?
            WHERE p.category = ? AND pl.grid_x IS NOT NULL AND pl.grid_y IS NOT NULL
            ORDER BY pl.grid_y ASC, pl.grid_x ASC
        ");
        $stmt->execute([$pageType, $categoryFilter]);
        $projects = $stmt->fetchAll();
        
        // Eğer hiç grid pozisyonu olan proje yoksa, eski sıralamayı kullan
        if (empty($projects)) {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE category = ? ORDER BY display_order ASC, created_at DESC");
            $stmt->execute([$categoryFilter]);
            $projects = $stmt->fetchAll();
            $layoutsTableExists = false; // Grid layout kullanma
        }
    } else {
        // Eski yöntem (projects tablosundan)
        $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'grid_x'");
        $gridColumnsExist = $stmt->rowCount() > 0;
        
        if ($gridColumnsExist) {
            $stmt = $pdo->prepare("
                SELECT * FROM projects 
                WHERE category = ? AND grid_x IS NOT NULL AND grid_y IS NOT NULL
                ORDER BY grid_y ASC, grid_x ASC
            ");
            $stmt->execute([$categoryFilter]);
            $projects = $stmt->fetchAll();
            
            if (empty($projects)) {
                $stmt = $pdo->prepare("SELECT * FROM projects WHERE category = ? ORDER BY display_order ASC, created_at DESC");
                $stmt->execute([$categoryFilter]);
                $projects = $stmt->fetchAll();
                $gridColumnsExist = false;
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE category = ? ORDER BY display_order ASC, created_at DESC");
            $stmt->execute([$categoryFilter]);
            $projects = $stmt->fetchAll();
        }
    }
} catch(PDOException $e) {
    // Veritabanı hatası durumunda boş array
    $projects = [];
}

include 'includes/header.php';
?>

<section class="mainContent">
    <div class="grid">
      <div class="wrap"<?php 
        $useGridLayout = $layoutsTableExists && !empty($projects) && isset($projects[0]['grid_x']) && $projects[0]['grid_x'] !== null;
        if ($useGridLayout) {
            // Margin ayarlarını veritabanından çek
            $horizontalMargin = getMarginValue('horizontal_margin');
            $verticalMargin = getMarginValue('vertical_margin');
            echo ' id="grid-layout-wrap" style="display: grid; grid-template-columns: repeat(12, 1fr); grid-auto-rows: 80px; column-gap: ' . $horizontalMargin . 'px; row-gap: ' . $verticalMargin . 'px; width: 100%;"';
        }
      ?>>
        <?php if (empty($projects)): ?>
            <div class="box">
                <h2><?php echo t('category.advertising'); ?></h2>
                <p><?php echo t('category.advertising_no_projects'); ?></p>
                <p><a href="index.php">&larr; <?php echo t('general.back_to_portfolio'); ?></a></p>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $project): ?>
                <?php 
                $gridX = isset($project['grid_x']) && $project['grid_x'] !== null ? intval($project['grid_x']) : null;
                $gridY = isset($project['grid_y']) && $project['grid_y'] !== null ? intval($project['grid_y']) : null;
                $gridW = isset($project['grid_w']) && $project['grid_w'] !== null ? intval($project['grid_w']) : null;
                $gridH = isset($project['grid_h']) && $project['grid_h'] !== null ? intval($project['grid_h']) : null;
                
                $gridStyle = '';
                if ($layoutsTableExists && $gridX !== null && $gridY !== null) {
                    $gridColumnStart = $gridX + 1;
                    $gridColumnEnd = $gridX + ($gridW ?? 4) + 1;
                    $gridRowStart = $gridY + 1;
                    $rowSpan = $gridH ?? 2;
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

