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
      } catch (PDOException $e) {
        // Tablo yoksa devam et
        error_log("Project media error: " . $e->getMessage());
      }
    }
  } catch (PDOException $e) {
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
              Description:
              <?php echo !empty($project['description']) ? 'YES (' . strlen($project['description']) . ' chars)' : 'NO'; ?><br>
              Media Count: <?php echo count($projectMedia); ?><br>
              Has Media:
              <?php echo (!empty($projectMedia) || !empty($project['image_path']) || !empty($project['video_path']) || !empty($project['vimeo_url'])) ? 'YES' : 'NO'; ?><br>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (empty($project)): ?>
          <div class="error-message gallery-error-message">
            <h2><?php echo t('general.project_not_found'); ?></h2>
            <p>Proje ID: <?php echo htmlspecialchars($projectId > 0 ? $projectId : 'Belirtilmedi'); ?></p>
            <p>
              <?php echo t('general.project_not_found_description', ['id' => $projectId > 0 ? $projectId : 'Belirtilmedi']); ?>
            </p>
            <a href="index.php" class="gallery-error-link"><?php echo t('general.back_to_home'); ?></a>
          </div>
        <?php else: ?>

          <!-- Proje Başlık ve Meta Bilgileri -->
          <div class="gallery-header">
            <h1 class="gallery-title">
              <?php echo htmlspecialchars(getLocalizedProject($project, 'title') ?: 'Başlıksız Proje'); ?>
            </h1>

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
                <div class="carousel"
                  data-flickity='{"cellAlign": "center", "contain": true, "pageDots": true, "prevNextButtons": true, "wrapAround": true, "adaptiveHeight": true}'>
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
                            <img src="<?php echo htmlspecialchars($imagePath); ?>"
                              alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>" class="gallery-image"
                              loading="lazy">
                          <?php else: ?>
                            <div class="media-error-fallback">
                              <p><?php echo t('gallery.image_not_loaded'); ?></p>
                              <p><?php echo t('gallery.original'); ?>: <?php echo htmlspecialchars($originalPath); ?></p>
                              <p><?php echo t('gallery.searched'); ?>: <?php echo htmlspecialchars($imagePath); ?></p>
                              <?php if (isset($_GET['debug'])): ?>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya yolu:
                                  <?php echo htmlspecialchars($filePath); ?>
                                </p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya var mı:
                                  <?php echo @file_exists($filePath) ? 'EVET' : 'HAYIR'; ?>
                                </p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">images/ klasörü var mı:
                                  <?php echo is_dir('images/') ? 'EVET' : 'HAYIR'; ?>
                                </p>
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
                            <video autoplay loop muted playsinline class="gallery-video" preload="metadata">
                              <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
                              <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/webm">
                              <?php echo t('gallery.browser_no_video_support'); ?>
                            </video>
                          <?php else: ?>
                            <div class="media-error-fallback"
                              style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                              <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <?php echo t('gallery.video_not_loaded'); ?>
                              </p>
                              <p style="color: #9ca3af; font-size: 0.75rem;"><?php echo t('gallery.original'); ?>:
                                <?php echo htmlspecialchars($originalPath); ?>
                              </p>
                              <p style="color: #9ca3af; font-size: 0.75rem;"><?php echo t('gallery.searched'); ?>:
                                <?php echo htmlspecialchars($videoPath); ?>
                              </p>
                              <?php if (isset($_GET['debug'])): ?>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya yolu:
                                  <?php echo htmlspecialchars($filePath); ?>
                                </p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">Dosya var mı:
                                  <?php echo @file_exists($filePath) ? 'EVET' : 'HAYIR'; ?>
                                </p>
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
                        <img src="<?php echo htmlspecialchars($imagePath); ?>"
                          alt="<?php echo htmlspecialchars(getLocalizedProject($project, 'title')); ?>" class="gallery-image">
                      <?php else: ?>
                        <div class="media-error"
                          style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                          <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                            <?php echo t('gallery.image_not_loaded'); ?>
                          </p>
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
                  <video autoplay loop muted playsinline class="gallery-video" preload="metadata">
                    <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
                    Tarayıcınız video oynatmayı desteklemiyor.
                  </video>
                <?php else: ?>
                  <div class="media-error"
                    style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                      <?php echo t('gallery.video_not_loaded'); ?>
                    </p>
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
                  <img src="<?php echo htmlspecialchars($imagePath); ?>"
                    alt="<?php echo htmlspecialchars($project['title']); ?>" class="gallery-image" loading="lazy">
                <?php else: ?>
                  <div class="media-error"
                    style="display: block; background: #f9fafb; padding: 3rem; text-align: center; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                      <?php echo t('gallery.image_not_loaded'); ?>
                    </p>
                    <p style="color: #9ca3af; font-size: 0.75rem;">Dosya: <?php echo htmlspecialchars($imagePath); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="media-placeholder">
                <p style="color: #6b7280; font-size: 0.95rem; margin-bottom: 0.5rem;">Bu proje için henüz görsel veya video
                  eklenmemiş.</p>
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
          } catch (PDOException $e) {
            $hasLinks = !empty($project['vimeo_url']);
          }
          ?>
          <?php if ($hasLinks): ?>
            <div class="project-links">
              <span class="project-links-title">BAĞLANTILAR</span>
              <?php if (!empty($project['instagram_url'])): ?>
                <a href="<?php echo htmlspecialchars($project['instagram_url']); ?>" target="_blank" class="project-link">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                  </svg>
                  INSTAGRAM
                </a>
              <?php endif; ?>

              <?php if (!empty($project['website_url'])): ?>
                <a href="<?php echo htmlspecialchars($project['website_url']); ?>" target="_blank" class="project-link">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z">
                    </path>
                  </svg>
                  WEBSITE
                </a>
              <?php endif; ?>

              <?php if (!empty($project['youtube_url'])): ?>
                <a href="<?php echo htmlspecialchars($project['youtube_url']); ?>" target="_blank" class="project-link">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path
                      d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z">
                    </path>
                    <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
                  </svg>
                  YOUTUBE
                </a>
              <?php endif; ?>

              <?php if (!empty($project['vimeo_url'])): ?>
                <a href="<?php echo htmlspecialchars($project['vimeo_url']); ?>" target="_blank" class="project-link">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 13.5l3.5-6.5h4L9 15.5l5.5-12.5h4.5l-6 18h-4.5l-4-9-2.5 4.5H3z" fill="none"
                      stroke="currentColor" />
                  </svg>
                  VIMEO
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="gallery-footer" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
            <a href="javascript:history.back()" class="back-link"
              style="display: inline-flex; align-items: center; gap: 0.5rem; color: #6b7280; text-decoration: none; font-weight: 500; font-size: 0.875rem; transition: color 0.2s;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7" />
              </svg>
              Geri Dön
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>