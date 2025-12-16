<?php
require_once 'config.php';

// Proje ID'sini al (ident veya id parametresinden)
$projectId = isset($_GET['ident']) ? intval($_GET['ident']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

// Debug: ID'yi kontrol et
if ($projectId <= 0) {
    error_log("Gallery: Invalid project ID. GET params: " . print_r($_GET, true));
}

// Projeyi veritabanından çek
$project = null;
$projectMedia = [];
if ($projectId > 0) {
    try {
        // Önce tüm projeleri kontrol et
        $allProjects = $pdo->query("SELECT id, title FROM projects LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        error_log("Gallery: Available projects: " . print_r($allProjects, true));
        
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Proje bulundu mu?
        if (!$project) {
            error_log("Gallery: Project with ID $projectId not found in database");
        } else {
            error_log("Gallery: Project found - " . ($project['title'] ?? 'No title'));
            error_log("Gallery: Project data - " . print_r($project, true));
        }
        
        // project_media tablosu var mı kontrol et ve medyaları çek
        if ($project) {
            try {
                $mediaTableCheck = $pdo->query("SHOW TABLES LIKE 'project_media'");
                if ($mediaTableCheck->rowCount() > 0) {
                    $mediaStmt = $pdo->prepare("SELECT * FROM project_media WHERE project_id = ? ORDER BY sort_order ASC");
                    $mediaStmt->execute([$projectId]);
                    $projectMedia = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch(PDOException $e) {
                // Tablo yoksa devam et
                error_log("Project media error: " . $e->getMessage());
            }
        }
    } catch(PDOException $e) {
        error_log("Gallery error: " . $e->getMessage());
    }
} else {
    error_log("Gallery: No project ID provided");
}

// Debug: Eğer proje bulunamazsa loglama
if ($projectId > 0 && !$project) {
    error_log("Gallery: Project ID $projectId not found");
}

if (!$project) {
    $pageTitle = "Proje Bulunamadı - Kürşad Karakuş Digital Portfolio";
    include 'includes/header.php';
    ?>
    <section class="mainContent">
        <div class="grid">
          <div class="wrap">
            <div class="box">
              <h2>Proje Bulunamadı</h2>
              <p>İstediğiniz proje bulunamadı veya silinmiş olabilir.</p>
              <p><a href="index.php">&larr; Anasayfaya Dön</a></p>
            </div>
          </div>
        </div>
    </section>
    <?php include 'includes/footer.php'; ?>
    <?php
    exit;
}

$pageTitle = htmlspecialchars(getLocalizedProject($project, 'title') ?: t('gallery.title')) . " - Kürşad Karakuş Digital Portfolio";
include 'includes/header.php';
?>

<section class="mainContent gallery-detail-page">
    <div class="grid gallery-grid">
      <div class="wrap gallery-wrap">
        <div class="box gallery-container">
          <?php 
          // Debug modu - sadece ?debug=1 parametresi varsa göster
          if (isset($_GET['debug']) && $_GET['debug'] == '1'): 
          ?>
            <div class="gallery-debug-info">
              <strong>Debug Bilgileri:</strong><br>
              Project ID: <?php echo $projectId; ?><br>
              Project Found: <?php echo $project ? 'YES' : 'NO'; ?><br>
              <?php if ($project): ?>
                Title: <?php echo htmlspecialchars($project['title'] ?? 'N/A'); ?><br>
                Image Path: <?php echo htmlspecialchars($project['image_path'] ?? 'N/A'); ?><br>
                Video Path: <?php echo htmlspecialchars($project['video_path'] ?? 'N/A'); ?><br>
                Vimeo URL: <?php echo htmlspecialchars($project['vimeo_url'] ?? 'N/A'); ?><br>
                Description: <?php echo !empty($project['description']) ? 'YES (' . strlen($project['description']) . ' chars)' : 'NO'; ?><br>
                Media Count: <?php echo count($projectMedia); ?><br>
                Has Media: <?php echo (!empty($projectMedia) || !empty($project['image_path']) || !empty($project['video_path']) || !empty($project['vimeo_url'])) ? 'YES' : 'NO'; ?><br>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          
          <?php if (empty($project)): ?>
            <div class="error-message gallery-error-message">
              <h2><?php echo t('general.project_not_found'); ?></h2>
              <p>Proje ID: <?php echo htmlspecialchars($projectId > 0 ? $projectId : 'Belirtilmedi'); ?></p>
              <p><?php echo t('general.project_not_found_description', ['id' => $projectId > 0 ? $projectId : 'Belirtilmedi']); ?></p>
              <a href="index.php" class="gallery-error-link"><?php echo t('general.back_to_home'); ?></a>
            </div>
          <?php else: ?>
          
          <!-- Proje Başlık ve Meta Bilgileri -->
          <div class="gallery-header">
            <h1 class="gallery-title"><?php echo htmlspecialchars(getLocalizedProject($project, 'title') ?: 'Başlıksız Proje'); ?></h1>
            
            <?php if (isset($project['date']) && !empty($project['date'])): ?>
              <p class="gallery-date"><?php echo htmlspecialchars($project['date']); ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Açıklama -->
          <?php 
          $localizedDescription = getLocalizedProject($project, 'description');
          if (!empty($localizedDescription)): 
          ?>
            <div class="gallery-description">
              <p><?php echo nl2br(htmlspecialchars($localizedDescription)); ?></p>
            </div>
          <?php endif; ?>
          
          <!-- Görsel/Video Galeri -->
          <div class="gallery-media">
            <?php 
            // Önce project_media tablosundan medyaları kontrol et
            // Eğer project_media varsa, sadece onları göster (eski image_path/video_path'i gösterme)
            if (!empty($projectMedia)): 
            ?>
              <div class="project-media-slider">
                <div class="carousel" data-flickity='{"cellAlign": "center", "contain": true, "pageDots": true, "prevNextButtons": true, "wrapAround": true, "adaptiveHeight": true}'>
                  <?php foreach ($projectMedia as $media): ?>
                    <div class="carousel-cell">
                      <?php if ($media['media_type'] === 'image'): ?>
                        <div class="media-wrapper">
                          <?php 
                          // Path'i normalize et
                          $originalPath = trim($media['media_path']);
                          $imagePath = $originalPath;
                          
                          // images/ prefix'ini normalize et (case-insensitive)
                          $imagePath = preg_replace('#^images?/#i', 'images/', $imagePath);
                          
                          // Eğer path zaten images/ ile başlamıyorsa ekle
                          if (!empty($imagePath) && stripos($imagePath, 'images/') !== 0 && strpos($imagePath, '/') !== 0) {
                              $imagePath = 'images/' . $imagePath;
                          }
                          
                          // Dosya var mı kontrol et (case-insensitive)
                          // gallery.php kök dizinde, images/ de kök dizinde, yani ../ gerekmez
                          $filePath = $imagePath;
                          $fileExists = @file_exists($filePath);
                          
                          // Eğer dosya bulunamazsa, case-insensitive arama yap
                          if (!$fileExists && !empty($imagePath)) {
                              $baseDir = 'images/';
                              $fileName = basename($imagePath);
                              if (is_dir($baseDir)) {
                                  $files = @scandir($baseDir);
                                  if ($files !== false) {
                                      foreach ($files as $file) {
                                          if ($file !== '.' && $file !== '..' && strcasecmp($file, $fileName) === 0) {
                                              // Gerçek dosya adını kullan (orijinal case ile)
                                              $imagePath = 'images/' . $file;
                                              $filePath = $imagePath;
                                              $fileExists = @file_exists($filePath);
                                              break;
                                          }
                                      }
                                  }
                              }
                          }
                          ?>
                          <?php 
                          // Son kontrol - dosya gerçekten var mı?
                          if (!$fileExists) {
                              // Tekrar dene - belki path normalize edilirken bir sorun oldu
                              $finalPath = 'images/' . basename($imagePath);
                              if (@file_exists($finalPath)) {
                                  $imagePath = $finalPath;
                                  $fileExists = true;
                              }
                          }
                          ?>
                          <?php if ($fileExists): ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>" class="gallery-image" loading="lazy">
                          <?php else: ?>
                            <div class="media-error-fallback">
                              <p><?php echo t('gallery.image_not_loaded'); ?></p>
                              <p><?php echo t('gallery.original'); ?>: <?php echo htmlspecialchars($originalPath); ?></p>
                              <p><?php echo t('gallery.searched'); ?>: <?php echo htmlspecialchars($imagePath); ?></p>
                              <?php if (isset($_GET['debug'])): ?>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya yolu: <?php echo htmlspecialchars($filePath); ?></p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya var mı: <?php echo @file_exists($filePath) ? 'EVET' : 'HAYIR'; ?></p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">images/ klasörü var mı: <?php echo is_dir('images/') ? 'EVET' : 'HAYIR'; ?></p>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php elseif ($media['media_type'] === 'video'): ?>
                        <div class="media-wrapper">
                          <?php 
                          // Path'i normalize et
                          $originalPath = trim($media['media_path']);
                          $videoPath = $originalPath;
                          
                          // images/ prefix'ini normalize et (case-insensitive)
                          $videoPath = preg_replace('#^images?/#i', 'images/', $videoPath);
                          
                          // Eğer path zaten images/ ile başlamıyorsa ekle
                          if (!empty($videoPath) && stripos($videoPath, 'images/') !== 0 && strpos($videoPath, '/') !== 0) {
                              $videoPath = 'images/' . $videoPath;
                          }
                          
                          // Dosya var mı kontrol et (case-insensitive)
                          // gallery.php kök dizinde, images/ de kök dizinde, yani ../ gerekmez
                          $filePath = $videoPath;
                          $fileExists = @file_exists($filePath);
                          
                          // Eğer dosya bulunamazsa, case-insensitive arama yap
                          if (!$fileExists && !empty($videoPath)) {
                              $baseDir = 'images/';
                              $fileName = basename($videoPath);
                              if (is_dir($baseDir)) {
                                  $files = @scandir($baseDir);
                                  if ($files !== false) {
                                      foreach ($files as $file) {
                                          if ($file !== '.' && $file !== '..' && strcasecmp($file, $fileName) === 0) {
                                              // Gerçek dosya adını kullan (orijinal case ile)
                                              $videoPath = 'images/' . $file;
                                              $filePath = $videoPath;
                                              $fileExists = @file_exists($filePath);
                                              break;
                                          }
                                      }
                                  }
                              }
                          }
                          ?>
                          <?php if ($fileExists): ?>
                            <video controls class="gallery-video" preload="metadata">
                              <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
                              <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/webm">
                              <?php echo t('gallery.browser_no_video_support'); ?>
                            </video>
                          <?php else: ?>
                            <div class="media-error-fallback" style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                              <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;"><?php echo t('gallery.video_not_loaded'); ?></p>
                              <p style="color: #9ca3af; font-size: 0.75rem;"><?php echo t('gallery.original'); ?>: <?php echo htmlspecialchars($originalPath); ?></p>
                              <p style="color: #9ca3af; font-size: 0.75rem;"><?php echo t('gallery.searched'); ?>: <?php echo htmlspecialchars($videoPath); ?></p>
                              <?php if (isset($_GET['debug'])): ?>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya yolu: <?php echo htmlspecialchars($filePath); ?></p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya var mı: <?php echo @file_exists($filePath) ? 'EVET' : 'HAYIR'; ?></p>
                              <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php 
            // Eski yöntem (tek görsel/video) - SADECE project_media yoksa göster
            // project_media varsa eski image_path/video_path'i gösterme (çift yükleme önleme)
            elseif (empty($projectMedia) && !empty($project['vimeo_url'])): 
            ?>
              <div class="vimeo-container">
                <a href="<?php echo htmlspecialchars($project['vimeo_url']); ?>" class="vimeo">
                  <?php if (!empty($project['image_path'])): ?>
                    <div class="media-wrapper">
                      <?php 
                      // Path'i normalize et
                      $imagePath = trim($project['image_path']);
                      $imagePath = preg_replace('#^images?/#i', 'images/', $imagePath);
                      if (!empty($imagePath) && stripos($imagePath, 'images/') !== 0 && strpos($imagePath, '/') !== 0) {
                          $imagePath = 'images/' . $imagePath;
                      }
                      // Case-insensitive dosya kontrolü
                      $filePath = $imagePath;
                      $fileExists = @file_exists($filePath);
                      if (!$fileExists && !empty($imagePath)) {
                          $baseDir = 'images/';
                          $fileName = basename($imagePath);
                          if (is_dir($baseDir)) {
                              $files = @scandir($baseDir);
                              if ($files !== false) {
                                  foreach ($files as $file) {
                                      if ($file !== '.' && $file !== '..' && strcasecmp($file, $fileName) === 0) {
                                          $imagePath = 'images/' . $file;
                                          $filePath = $imagePath;
                                          $fileExists = @file_exists($filePath);
                                          break;
                                      }
                                  }
                              }
                          }
                      }
                      ?>
                      <?php if ($fileExists): ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>" class="gallery-image">
                      <?php else: ?>
                        <div class="media-error" style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                          <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;"><?php echo t('gallery.image_not_loaded'); ?></p>
                          <p style="color: #9ca3af; font-size: 0.75rem;">Dosya: <?php echo htmlspecialchars($imagePath); ?></p>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="media-placeholder">
                      <p style="color: #6b7280; font-size: 0.875rem;">Video için tıklayın</p>
                    </div>
                  <?php endif; ?>
                </a>
              </div>
            <?php elseif (empty($projectMedia) && !empty($project['video_path'])): ?>
              <div class="media-wrapper">
                <?php 
                // Path'i normalize et
                $videoPath = trim($project['video_path']);
                $videoPath = preg_replace('#^images?/#i', 'images/', $videoPath);
                if (!empty($videoPath) && stripos($videoPath, 'images/') !== 0 && strpos($videoPath, '/') !== 0) {
                    $videoPath = 'images/' . $videoPath;
                }
                // Case-insensitive dosya kontrolü
                $filePath = $videoPath;
                $fileExists = @file_exists($filePath);
                if (!$fileExists && !empty($videoPath)) {
                    $baseDir = 'images/';
                    $fileName = basename($videoPath);
                    if (is_dir($baseDir)) {
                        $files = @scandir($baseDir);
                        if ($files !== false) {
                            foreach ($files as $file) {
                                if ($file !== '.' && $file !== '..' && strcasecmp($file, $fileName) === 0) {
                                    $videoPath = 'images/' . $file;
                                    $filePath = $videoPath;
                                    $fileExists = @file_exists($filePath);
                                    break;
                                }
                            }
                        }
                    }
                }
                ?>
                <?php if ($fileExists): ?>
                  <video controls class="gallery-video" preload="metadata">
                    <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
                    Tarayıcınız video oynatmayı desteklemiyor.
                  </video>
                <?php else: ?>
                  <div class="media-error" style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                              <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;"><?php echo t('gallery.video_not_loaded'); ?></p>
                    <p style="color: #9ca3af; font-size: 0.75rem;">Dosya: <?php echo htmlspecialchars($videoPath); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php elseif (empty($projectMedia) && !empty($project['image_path'])): ?>
              <div class="media-wrapper">
                <?php 
                // Path'i normalize et
                $imagePath = trim($project['image_path']);
                $imagePath = preg_replace('#^images?/#i', 'images/', $imagePath);
                if (!empty($imagePath) && stripos($imagePath, 'images/') !== 0 && strpos($imagePath, '/') !== 0) {
                    $imagePath = 'images/' . $imagePath;
                }
                // Case-insensitive dosya kontrolü
                $filePath = $imagePath;
                $fileExists = @file_exists($filePath);
                if (!$fileExists && !empty($imagePath)) {
                    $baseDir = 'images/';
                    $fileName = basename($imagePath);
                    if (is_dir($baseDir)) {
                        $files = @scandir($baseDir);
                        if ($files !== false) {
                            foreach ($files as $file) {
                                if ($file !== '.' && $file !== '..' && strcasecmp($file, $fileName) === 0) {
                                    $imagePath = 'images/' . $file;
                                    $filePath = $imagePath;
                                    $fileExists = @file_exists($filePath);
                                    break;
                                }
                            }
                        }
                    }
                }
                ?>
                <?php if ($fileExists): ?>
                  <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" class="gallery-image" loading="lazy">
                <?php else: ?>
                  <div class="media-error" style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                          <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;"><?php echo t('gallery.image_not_loaded'); ?></p>
                          <p style="color: #9ca3af; font-size: 0.75rem;">Dosya: <?php echo htmlspecialchars($imagePath); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="media-placeholder">
                <p style="color: #6b7280; font-size: 0.95rem; margin-bottom: 0.5rem;">Bu proje için henüz görsel veya video eklenmemiş.</p>
                <p style="color: #9ca3af; font-size: 0.875rem;">Admin panelinden bu projeye medya ekleyebilirsiniz.</p>
              </div>
            <?php endif; ?>
          </div>
          
          <!-- Linkler -->
          <?php 
          // Link kolonlarının varlığını kontrol et
          $hasLinks = false;
          try {
              $linkCheck = $pdo->query("SHOW COLUMNS FROM projects LIKE 'instagram_url'");
              $linkColumnsExist = $linkCheck->rowCount() > 0;
              if ($linkColumnsExist) {
                  $hasLinks = !empty($project['instagram_url']) || !empty($project['website_url']) || !empty($project['youtube_url']) || !empty($project['vimeo_url']);
              } else {
                  $hasLinks = !empty($project['vimeo_url']);
              }
          } catch(PDOException $e) {
              $hasLinks = !empty($project['vimeo_url']);
          }
          ?>
          <?php if ($hasLinks): ?>
            <div class="project-links" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
              <h3 style="font-size: 0.875rem; margin-bottom: 1rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">Bağlantılar</h3>
              <div class="links-grid" style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                <?php if (isset($project['instagram_url']) && !empty($project['instagram_url'])): ?>
                  <a href="<?php echo htmlspecialchars($project['instagram_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-link social-instagram">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162 0 3.403 2.759 6.162 6.162 6.162 3.403 0 6.162-2.759 6.162-6.162 0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4 2.209 0 4 1.791 4 4 0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                    <span>Instagram</span>
                  </a>
                <?php endif; ?>
                
                <?php if (isset($project['website_url']) && !empty($project['website_url'])): ?>
                  <a href="<?php echo htmlspecialchars($project['website_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-link social-website">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <span>Website</span>
                  </a>
                <?php endif; ?>
                
                <?php if (isset($project['youtube_url']) && !empty($project['youtube_url'])): ?>
                  <a href="<?php echo htmlspecialchars($project['youtube_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-link social-youtube">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                    </svg>
                    <span>YouTube</span>
                  </a>
                <?php endif; ?>
                
                <?php if (!empty($project['vimeo_url'])): ?>
                  <a href="<?php echo htmlspecialchars($project['vimeo_url']); ?>" target="_blank" rel="noopener noreferrer" class="social-link social-vimeo vimeo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L3.322 7.401C2.603 4.816 1.837 3.522 1.022 3.522c-.179 0-.806.378-1.881 1.132L0 3.197c1.185-1.044 2.351-2.084 3.501-3.128C5.08.842 6.184.152 7.229.152c1.663 0 2.694 1.002 3.092 3.004.419 2.047.707 3.313.865 3.801.481 2.233.996 3.351 1.545 3.351.435 0 1.09-.688 1.963-2.065.872-1.376 1.339-2.373 1.401-2.991.125-1.28-.36-1.92-1.458-1.92-.518 0-1.05.12-1.594.36 1.056-3.45 3.064-5.13 6.026-5.04 2.197.06 3.24 1.485 3.13 4.28z"/>
                    </svg>
                    <span>Vimeo</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          
          <div class="gallery-footer" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
            <a href="javascript:history.back()" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: #6b7280; text-decoration: none; font-weight: 500; font-size: 0.875rem; transition: color 0.2s;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
              </svg>
              Geri Dön
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
</section>

<!-- Flickity CSS -->
<link rel="stylesheet" href="https://unpkg.com/flickity@2/dist/flickity.min.css">
<!-- Flickity JS -->
<script src="https://unpkg.com/flickity@2/dist/flickity.pkgd.min.js"></script>

<style>
/* Gallery sayfası için opacity sorununu düzelt - TÜM olası durumlar için */
.gallery-detail-page,
.gallery-detail-page *,
.gallery-detail-page .grid,
.gallery-detail-page .grid *,
.gallery-detail-page .wrap,
.gallery-detail-page .wrap *,
.gallery-detail-page .box,
.gallery-detail-page .box *,
.mainContent .gallery-detail-page,
.mainContent .gallery-detail-page .grid,
.mainContent .gallery-detail-page .grid .wrap,
.mainContent .gallery-detail-page .grid .wrap .box {
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
}

/* Özel durumlar için */
.gallery-detail-page .gallery-container,
.gallery-detail-page .gallery-header,
.gallery-detail-page .gallery-title,
.gallery-detail-page .gallery-date,
.gallery-detail-page .gallery-description,
.gallery-detail-page .gallery-media,
.gallery-detail-page .gallery-image,
.gallery-detail-page .gallery-video,
.gallery-detail-page .project-links,
.gallery-detail-page .gallery-footer {
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
}

/* Gallery Container */
.gallery-detail-page {
    background: #fff;
}

.gallery-container {
    max-width: 900px !important;
}

/* Gallery Header */
.gallery-header {
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.gallery-title {
    margin: 0 0 0.75rem 0;
    font-size: 1.75rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.3;
    letter-spacing: -0.02em;
}

.gallery-date {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 400;
}

/* Gallery Description */
.gallery-description {
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e5e7eb;
}

.gallery-description p {
    margin: 0;
    line-height: 1.7;
    color: #374151;
    font-size: 0.95rem;
    max-width: 700px;
}

/* Media Wrapper */
.media-wrapper {
    width: 100%;
    margin: 0 auto;
    border-radius: 0.5rem;
    overflow: hidden;
    background: #f9fafb;
}

.gallery-image {
    width: 100%;
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
    border-radius: 0.5rem;
    object-fit: contain;
}

.gallery-video {
    width: 100%;
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
    border-radius: 0.5rem;
    background: #000;
}

.media-placeholder {
    background: #f9fafb;
    padding: 4rem 2rem;
    text-align: center;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* Project Media Slider */
.project-media-slider {
    margin: 2rem 0;
    position: relative;
}

.carousel {
    background: transparent;
}

.carousel-cell {
    width: 100%;
    margin-right: 10px;
    counter-increment: carousel-cell;
}

.carousel-cell .media-wrapper {
    width: 100%;
}

/* Flickity navigation buttons */
.flickity-prev-next-button {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    opacity: 0.9;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.flickity-prev-next-button:hover {
    opacity: 1;
    background: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: scale(1.05);
}

.flickity-prev-next-button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.flickity-prev-next-button .arrow {
    fill: #111827;
}

.flickity-prev-next-button.previous {
    left: 15px;
}

.flickity-prev-next-button.next {
    right: 15px;
}

/* Flickity page dots */
.flickity-page-dots {
    bottom: -35px;
}

.flickity-page-dots .dot {
    width: 8px;
    height: 8px;
    opacity: 0.25;
    background: #111827;
    transition: all 0.2s;
}

.flickity-page-dots .dot.is-selected {
    opacity: 1;
    width: 24px;
    border-radius: 4px;
}

/* Social Links */
.social-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.social-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.social-instagram {
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
    color: white;
}

.social-website {
    background: #2563eb;
    color: white;
}

.social-youtube {
    background: #ff0000;
    color: white;
}

.social-vimeo {
    background: #1ab7ea;
    color: white;
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #6b7280;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.875rem;
    transition: color 0.2s;
}

.back-link:hover {
    color: #111827;
}

/* Responsive Design */
@media (max-width: 768px) {
    .gallery-container {
        padding: 0 1rem !important;
    }
    
    .gallery-title {
        font-size: 1.5rem;
    }
    
    .gallery-description p {
        font-size: 0.9rem;
    }
    
    .flickity-prev-next-button {
        width: 36px;
        height: 36px;
    }
    
    .flickity-prev-next-button.previous {
        left: 10px;
    }
    
    .flickity-prev-next-button.next {
        right: 10px;
    }
    
    .social-link {
        padding: 0.5rem 1rem;
        font-size: 0.8125rem;
    }
    
    .links-grid {
        flex-direction: column;
    }
    
    .social-link {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .gallery-title {
        font-size: 1.25rem;
    }
    
    .gallery-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
    }
    
    .gallery-description {
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
    }
    
    .project-media-slider {
        margin: 1.5rem 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gallery sayfası için opacity sorununu düzelt - TÜM elementler için
    function forceVisibility() {
        // Tüm gallery sayfası elementlerini bul
        var galleryPage = document.querySelector('.gallery-detail-page');
        if (galleryPage) {
            // Tüm child elementleri zorla görünür yap
            var allElements = galleryPage.querySelectorAll('*');
            allElements.forEach(function(el) {
                el.style.opacity = '1';
                el.style.visibility = 'visible';
            });
            
            // Özel elementler
            galleryPage.style.opacity = '1';
            galleryPage.style.visibility = 'visible';
            galleryPage.style.display = 'block';
        }
        
        // Grid ve box elementleri
        var galleryBoxes = document.querySelectorAll('.gallery-detail-page .grid, .gallery-detail-page .wrap, .gallery-detail-page .box, .gallery-container, .gallery-header, .gallery-title, .gallery-date, .gallery-description, .gallery-media, .gallery-image, .gallery-video, .project-links, .gallery-footer');
        galleryBoxes.forEach(function(box) {
            box.style.opacity = '1';
            box.style.visibility = 'visible';
            box.style.display = 'block';
        });
        
        // MainContent için de
        var mainContent = document.querySelector('.mainContent.gallery-detail-page');
        if (mainContent) {
            mainContent.style.opacity = '1';
            mainContent.style.visibility = 'visible';
            mainContent.style.display = 'block';
        }
    }
    
    // Hemen çalıştır
    forceVisibility();
    
    // Kısa bir gecikme ile tekrar çalıştır (CSS yüklenmesi için)
    setTimeout(forceVisibility, 100);
    setTimeout(forceVisibility, 500);
    
    // Flickity slider'ı başlat
    var carousel = document.querySelector('.carousel');
    if (carousel) {
        var flkty = new Flickity(carousel, {
            cellAlign: 'center',
            contain: true,
            pageDots: true,
            prevNextButtons: true,
            wrapAround: true,
            autoPlay: false,
            adaptiveHeight: true
        });
    }
    
    // MutationObserver ile dinamik değişiklikleri izle
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            forceVisibility();
        });
        
        var target = document.querySelector('.gallery-detail-page');
        if (target) {
            observer.observe(target, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>

