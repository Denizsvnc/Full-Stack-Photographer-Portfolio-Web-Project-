<?php
require_once 'config.php';

$pageTitle = "Kürşad Karakuş Digital Portfolio";

// project_layouts tablosu var mı kontrol et
$layoutsTableExists = false;
$pageType = 'index';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_layouts'");
    $layoutsTableExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $layoutsTableExists = false;
}

// Veritabanından projeleri çek
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
            WHERE pl.grid_x IS NOT NULL AND pl.grid_y IS NOT NULL
            ORDER BY pl.grid_y ASC, pl.grid_x ASC
        ");
        $stmt->execute([$pageType]);
        $projects = $stmt->fetchAll();

        // Eğer hiç grid pozisyonu olan proje yoksa, eski sıralamayı kullan
        if (empty($projects)) {
            $projects = $pdo->query("SELECT * FROM projects ORDER BY display_order ASC, created_at DESC")->fetchAll();
            $layoutsTableExists = false; // Grid layout kullanma
        }
    } else {
        // Eski yöntem (projects tablosundan)
        $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'grid_x'");
        $gridColumnsExist = $stmt->rowCount() > 0;

        if ($gridColumnsExist) {
            $projects = $pdo->query("
                SELECT * FROM projects 
                WHERE grid_x IS NOT NULL AND grid_y IS NOT NULL
                ORDER BY grid_y ASC, grid_x ASC
            ")->fetchAll();

            if (empty($projects)) {
                $projects = $pdo->query("SELECT * FROM projects ORDER BY display_order ASC, created_at DESC")->fetchAll();
                $gridColumnsExist = false;
            }
        } else {
            $projects = $pdo->query("SELECT * FROM projects ORDER BY display_order ASC, created_at DESC")->fetchAll();
        }
    }
} catch (PDOException $e) {
    // Veritabanı hatası durumunda boş array
    $projects = [];
}

include 'includes/header.php';
?>

<section class="mainContent">
    <div class="grid">
        <div class="wrap" <?php
        $useGridLayout = $layoutsTableExists && !empty($projects) && isset($projects[0]['grid_x']) && $projects[0]['grid_x'] !== null;
        if ($useGridLayout) {
            // Margin ayarlarını veritabanından çek
            $horizontalMargin = getMarginValue('horizontal_margin');
            $verticalMargin = getMarginValue('vertical_margin');
            // GridStack ile uyumlu: cellHeight 80px
            // CSS Grid'de auto-rows kullanarak yükseklikleri ayarla
            echo ' id="grid-layout-wrap" style="display: grid; grid-template-columns: repeat(12, 1fr); grid-auto-rows: 80px; column-gap: ' . $horizontalMargin . 'px; row-gap: ' . $verticalMargin . 'px; width: 100%;"';
        }
        ?>>
        <?php if (empty($projects)): ?>
                <div class="box">
                    <h3>Henüz proje eklenmemiş</h3>
                    <p>Admin panelinden proje ekleyebilirsiniz.</p>
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
                        <div class="box" <?php echo $gridStyle ? ' style="' . htmlspecialchars($gridStyle) . '"' : ''; ?>>
                            <a href="gallery.php?id=<?php echo $project['id']; ?>">
                                <figure>
                                    <?php if ($project['video_path']): ?>
                                        <video autoplay loop muted class="video-canplay">
                                            <source type="video/mp4" src="<?php echo htmlspecialchars($project['video_path']); ?>">
                                        </video>
                                    <?php elseif ($project['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($project['image_path']); ?>"
                                            alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>">
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
                        <div class="box editorial" <?php echo $gridStyle ? ' style="' . htmlspecialchars($gridStyle) . '"' : ''; ?>>
                            <a href="gallery.php?id=<?php echo $project['id']; ?>">
                                <figure>
                                    <?php if ($project['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($project['image_path']); ?>"
                                            alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>">
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