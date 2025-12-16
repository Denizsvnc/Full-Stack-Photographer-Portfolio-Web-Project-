<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Kategorileri çek (dinamik)
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY display_order ASC, name ASC")->fetchAll();
} catch(PDOException $e) {
    // Tablo yoksa varsayılan kategorileri kullan
    $categories = [
        ['id' => null, 'name' => 'Editorial', 'slug' => 'editorial'],
        ['id' => null, 'name' => 'Advertising', 'slug' => 'advertising'],
        ['id' => null, 'name' => 'Film', 'slug' => 'film'],
        ['id' => null, 'name' => 'Cover', 'slug' => 'cover']
    ];
}

// Dosya yükleme klasörü
$uploadDir = '../images/';
$allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$allowedVideoTypes = ['video/mp4', 'video/webm'];

// Toplu silme işlemi
if ($action === 'bulk-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idsJson = $_POST['ids'] ?? '';
    $ids = json_decode($idsJson, true);
    
    if (!empty($ids) && is_array($ids)) {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, function($id) { return $id > 0; });
        
        if (empty($ids)) {
            $message = 'Geçersiz proje ID\'leri!';
            $messageType = 'danger';
            $action = 'list';
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Silinecek projelerin dosyalarını al
            $stmt = $pdo->prepare("SELECT image_path, video_path FROM projects WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $projectsToDelete = $stmt->fetchAll();
            
            // Dosyaları sil
            foreach ($projectsToDelete as $project) {
                if ($project['image_path'] && file_exists('../' . $project['image_path'])) {
                    @unlink('../' . $project['image_path']);
                }
                if ($project['video_path'] && file_exists('../' . $project['video_path'])) {
                    @unlink('../' . $project['video_path']);
                }
            }
            
            // Projeleri sil
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            // Manifest.json'u güncelle (kategori proje sayıları değişebilir)
            require_once dirname(__DIR__) . '/config.php';
            updateManifestJson();
            
            $count = count($ids);
            $message = $count . ' proje başarıyla silindi!';
            $messageType = 'success';
            $action = 'list';
        }
    } else {
        $message = 'Lütfen silinecek projeleri seçin!';
        $messageType = 'danger';
        $action = 'list';
    }
}

// Tekil silme işlemi
if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("SELECT image_path, video_path FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if ($project) {
        // Dosyaları sil
        if ($project['image_path'] && file_exists('../' . $project['image_path'])) {
            unlink('../' . $project['image_path']);
        }
        if ($project['video_path'] && file_exists('../' . $project['video_path'])) {
            unlink('../' . $project['video_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        
        // Manifest.json'u güncelle (kategori proje sayısı değişebilir)
        require_once dirname(__DIR__) . '/config.php';
        updateManifestJson();
        
        $message = 'Proje başarıyla silindi!';
        $messageType = 'success';
        $action = 'list';
    }
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Çok dilli alanlar
    $tr_title = $_POST['tr_title'] ?? '';
    $en_title = $_POST['en_title'] ?? '';
    $tr_description = $_POST['tr_description'] ?? '';
    $en_description = $_POST['en_description'] ?? '';
    
    // Geriye dönük uyumluluk için eski alanlar
    $title = $_POST['title'] ?? $tr_title;
    $description = $_POST['description'] ?? $tr_description;
    
    $category = $_POST['category'] ?? '';
    $gallery_id = $_POST['gallery_id'] ?? null;
    $date = $_POST['date'] ?? '';
    $vimeo_url = $_POST['vimeo_url'] ?? '';
    $instagram_url = $_POST['instagram_url'] ?? '';
    $website_url = $_POST['website_url'] ?? '';
    $youtube_url = $_POST['youtube_url'] ?? '';
    $display_order = $_POST['display_order'] ?? 0;
    
    // Link kolonlarının varlığını kontrol et
    $linkColumnsExist = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'instagram_url'");
        $linkColumnsExist = $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        $linkColumnsExist = false;
    }
    
    // Dosya yükleme
    $imagePath = $_POST['existing_image'] ?? '';
    $videoPath = $_POST['existing_video'] ?? '';
    
    // Kapak içeriği yükleme (cover_media - tek dosya, görsel veya video)
    if (isset($_FILES['cover_media']) && $_FILES['cover_media']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_media'];
        
        // Görsel mi video mu kontrol et
        if (in_array($file['type'], $allowedImageTypes)) {
            // Görsel yükle
            $fileName = uniqid() . '_' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Eski görseli ve videoyu sil (kapak sadece bir tane olmalı)
                if ($imagePath && file_exists('../' . $imagePath)) {
                    unlink('../' . $imagePath);
                }
                if ($videoPath && file_exists('../' . $videoPath)) {
                    unlink('../' . $videoPath);
                }
                $imagePath = 'images/' . $fileName;
                $videoPath = ''; // Video'yu temizle
            }
        } elseif (in_array($file['type'], $allowedVideoTypes)) {
            // Video yükle
            $fileName = uniqid() . '_' . basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Eski görseli ve videoyu sil (kapak sadece bir tane olmalı)
                if ($imagePath && file_exists('../' . $imagePath)) {
                    unlink('../' . $imagePath);
                }
                if ($videoPath && file_exists('../' . $videoPath)) {
                    unlink('../' . $videoPath);
                }
                $videoPath = 'images/' . $fileName;
                $imagePath = ''; // Görseli temizle
            }
        }
    } else {
        // cover_media yoksa, eski image ve video alanlarını kontrol et (geriye dönük uyumluluk)
        
        // Görsel yükleme
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            if (in_array($file['type'], $allowedImageTypes)) {
                $fileName = uniqid() . '_' . basename($file['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Eski görseli sil
                    if ($imagePath && file_exists('../' . $imagePath)) {
                        unlink('../' . $imagePath);
                    }
                    $imagePath = 'images/' . $fileName;
                }
            }
        }
        
        // Video yükleme
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['video'];
            if (in_array($file['type'], $allowedVideoTypes)) {
                $fileName = uniqid() . '_' . basename($file['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Eski videoyu sil
                    if ($videoPath && file_exists('../' . $videoPath)) {
                        unlink('../' . $videoPath);
                    }
                    $videoPath = 'images/' . $fileName;
                }
            }
        }
    }
    
    // Kategori ID'yi bul
    $categoryId = null;
    if (!empty($category)) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? OR name = ?");
        $stmt->execute([$category, $category]);
        $cat = $stmt->fetch();
        if ($cat) {
            $categoryId = $cat['id'];
        }
    }
    
    // project_media tablosu var mı kontrol et, yoksa oluştur
    $mediaTableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'project_media'");
        $mediaTableExists = $stmt->rowCount() > 0;
        
        // Tablo yoksa oluştur
        if (!$mediaTableExists) {
            $sql = "CREATE TABLE IF NOT EXISTS project_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                media_type ENUM('image', 'video') NOT NULL,
                media_path VARCHAR(500) NOT NULL,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                INDEX idx_project_id (project_id),
                INDEX idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $pdo->exec($sql);
            $mediaTableExists = true;
        }
    } catch(PDOException $e) {
        // Foreign key hatası olabilir, tekrar dene
        try {
            $sql = "CREATE TABLE IF NOT EXISTS project_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                media_type ENUM('image', 'video') NOT NULL,
                media_path VARCHAR(500) NOT NULL,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_project_id (project_id),
                INDEX idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $pdo->exec($sql);
            $mediaTableExists = true;
        } catch(PDOException $e2) {
            error_log("Project media table creation error: " . $e2->getMessage());
            $mediaTableExists = false;
        }
    }
    
    // Çeviri kolonlarının varlığını kontrol et
    $translationColumnsExist = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'tr_title'");
        $translationColumnsExist = $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        $translationColumnsExist = false;
    }
    
    if ($action === 'add') {
        if ($translationColumnsExist) {
            // Çeviri kolonları varsa onları kullan
            if ($linkColumnsExist) {
                $stmt = $pdo->prepare("INSERT INTO projects (title, tr_title, en_title, category, category_id, image_path, video_path, vimeo_url, instagram_url, website_url, youtube_url, gallery_id, description, tr_description, en_description, date, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $tr_title, $en_title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $instagram_url ?: null, $website_url ?: null, $youtube_url ?: null, $gallery_id ?: null, $description, $tr_description, $en_description, $date ?: null, $display_order]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO projects (title, tr_title, en_title, category, category_id, image_path, video_path, vimeo_url, gallery_id, description, tr_description, en_description, date, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $tr_title, $en_title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $gallery_id ?: null, $description, $tr_description, $en_description, $date ?: null, $display_order]);
            }
        } else {
            // Eski yöntem (geriye dönük uyumluluk)
            if ($linkColumnsExist) {
                $stmt = $pdo->prepare("INSERT INTO projects (title, category, category_id, image_path, video_path, vimeo_url, instagram_url, website_url, youtube_url, gallery_id, description, date, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $instagram_url ?: null, $website_url ?: null, $youtube_url ?: null, $gallery_id ?: null, $description ?: null, $date ?: null, $display_order]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO projects (title, category, category_id, image_path, video_path, vimeo_url, gallery_id, description, date, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $gallery_id ?: null, $description ?: null, $date ?: null, $display_order]);
            }
        }
        $newProjectId = $pdo->lastInsertId();
        
        // Manifest.json'u güncelle (kategori değişikliği olabilir)
        require_once dirname(__DIR__) . '/config.php';
        updateManifestJson();
        
        // Çoklu medya yükleme (project_media tablosu varsa)
        if ($mediaTableExists) {
            $sortOrder = 0;
            
            // Birleşik medya yükleme (media_files - hem görsel hem video)
            if (isset($_FILES['media_files']) && is_array($_FILES['media_files']['name'])) {
                foreach ($_FILES['media_files']['name'] as $key => $fileName) {
                    if ($_FILES['media_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['media_files']['name'][$key],
                            'type' => $_FILES['media_files']['type'][$key],
                            'tmp_name' => $_FILES['media_files']['tmp_name'][$key],
                            'error' => $_FILES['media_files']['error'][$key]
                        ];
                        
                        $mediaType = null;
                        if (in_array($file['type'], $allowedImageTypes)) {
                            $mediaType = 'image';
                        } elseif (in_array($file['type'], $allowedVideoTypes)) {
                            $mediaType = 'video';
                        }
                        
                        if ($mediaType) {
                            $uniqueFileName = uniqid() . '_' . basename($file['name']);
                            $targetPath = $uploadDir . $uniqueFileName;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $mediaPath = 'images/' . $uniqueFileName;
                                $mediaStmt = $pdo->prepare("INSERT INTO project_media (project_id, media_type, media_path, sort_order) VALUES (?, ?, ?, ?)");
                                $mediaStmt->execute([$newProjectId, $mediaType, $mediaPath, $sortOrder]);
                                $sortOrder++;
                            }
                        }
                    }
                }
            }
            
            // Geriye dönük uyumluluk için eski alanlar (media_images ve media_videos)
            if (isset($_FILES['media_images']) && is_array($_FILES['media_images']['name'])) {
                foreach ($_FILES['media_images']['name'] as $key => $fileName) {
                    if ($_FILES['media_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['media_images']['name'][$key],
                            'type' => $_FILES['media_images']['type'][$key],
                            'tmp_name' => $_FILES['media_images']['tmp_name'][$key],
                            'error' => $_FILES['media_images']['error'][$key]
                        ];
                        if (in_array($file['type'], $allowedImageTypes)) {
                            $uniqueFileName = uniqid() . '_' . basename($file['name']);
                            $targetPath = $uploadDir . $uniqueFileName;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $mediaPath = 'images/' . $uniqueFileName;
                                $mediaStmt = $pdo->prepare("INSERT INTO project_media (project_id, media_type, media_path, sort_order) VALUES (?, 'image', ?, ?)");
                                $mediaStmt->execute([$newProjectId, $mediaPath, $sortOrder]);
                                $sortOrder++;
                            }
                        }
                    }
                }
            }
            
            if (isset($_FILES['media_videos']) && is_array($_FILES['media_videos']['name'])) {
                foreach ($_FILES['media_videos']['name'] as $key => $fileName) {
                    if ($_FILES['media_videos']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['media_videos']['name'][$key],
                            'type' => $_FILES['media_videos']['type'][$key],
                            'tmp_name' => $_FILES['media_videos']['tmp_name'][$key],
                            'error' => $_FILES['media_videos']['error'][$key]
                        ];
                        if (in_array($file['type'], $allowedVideoTypes)) {
                            $uniqueFileName = uniqid() . '_' . basename($file['name']);
                            $targetPath = $uploadDir . $uniqueFileName;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $mediaPath = 'images/' . $uniqueFileName;
                                $mediaStmt = $pdo->prepare("INSERT INTO project_media (project_id, media_type, media_path, sort_order) VALUES (?, 'video', ?, ?)");
                                $mediaStmt->execute([$newProjectId, $mediaPath, $sortOrder]);
                                $sortOrder++;
                            }
                        }
                    }
                }
            }
        }
        
        $message = 'Proje başarıyla eklendi!';
        $messageType = 'success';
        $action = 'list';
    } elseif ($action === 'edit' && $id) {
        if ($translationColumnsExist) {
            // Çeviri kolonları varsa onları kullan
            if ($linkColumnsExist) {
                $stmt = $pdo->prepare("UPDATE projects SET title = ?, tr_title = ?, en_title = ?, category = ?, category_id = ?, image_path = ?, video_path = ?, vimeo_url = ?, instagram_url = ?, website_url = ?, youtube_url = ?, gallery_id = ?, description = ?, tr_description = ?, en_description = ?, date = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$title, $tr_title, $en_title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $instagram_url ?: null, $website_url ?: null, $youtube_url ?: null, $gallery_id ?: null, $description, $tr_description, $en_description, $date ?: null, $display_order, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE projects SET title = ?, tr_title = ?, en_title = ?, category = ?, category_id = ?, image_path = ?, video_path = ?, vimeo_url = ?, gallery_id = ?, description = ?, tr_description = ?, en_description = ?, date = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$title, $tr_title, $en_title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $gallery_id ?: null, $description, $tr_description, $en_description, $date ?: null, $display_order, $id]);
            }
        } else {
            // Eski yöntem (geriye dönük uyumluluk)
            if ($linkColumnsExist) {
                $stmt = $pdo->prepare("UPDATE projects SET title = ?, category = ?, category_id = ?, image_path = ?, video_path = ?, vimeo_url = ?, instagram_url = ?, website_url = ?, youtube_url = ?, gallery_id = ?, description = ?, date = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $instagram_url ?: null, $website_url ?: null, $youtube_url ?: null, $gallery_id ?: null, $description ?: null, $date ?: null, $display_order, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE projects SET title = ?, category = ?, category_id = ?, image_path = ?, video_path = ?, vimeo_url = ?, gallery_id = ?, description = ?, date = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$title, $category, $categoryId, $imagePath ?: null, $videoPath ?: null, $vimeo_url ?: null, $gallery_id ?: null, $description ?: null, $date ?: null, $display_order, $id]);
            }
        }
        
        // Manifest.json'u güncelle (kategori değişikliği olabilir)
        require_once dirname(__DIR__) . '/config.php';
        updateManifestJson();
        
        // Çoklu medya yükleme (project_media tablosu varsa)
        if ($mediaTableExists) {
            // Mevcut medya sayısını al
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM project_media WHERE project_id = ?");
            $countStmt->execute([$id]);
            $sortOrder = $countStmt->fetchColumn();
            
            // Birleşik medya yükleme (media_files - hem görsel hem video)
            if (isset($_FILES['media_files']) && is_array($_FILES['media_files']['name'])) {
                foreach ($_FILES['media_files']['name'] as $key => $fileName) {
                    if ($_FILES['media_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['media_files']['name'][$key],
                            'type' => $_FILES['media_files']['type'][$key],
                            'tmp_name' => $_FILES['media_files']['tmp_name'][$key],
                            'error' => $_FILES['media_files']['error'][$key]
                        ];
                        
                        $mediaType = null;
                        if (in_array($file['type'], $allowedImageTypes)) {
                            $mediaType = 'image';
                        } elseif (in_array($file['type'], $allowedVideoTypes)) {
                            $mediaType = 'video';
                        }
                        
                        if ($mediaType) {
                            $uniqueFileName = uniqid() . '_' . basename($file['name']);
                            $targetPath = $uploadDir . $uniqueFileName;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $mediaPath = 'images/' . $uniqueFileName;
                                $mediaStmt = $pdo->prepare("INSERT INTO project_media (project_id, media_type, media_path, sort_order) VALUES (?, ?, ?, ?)");
                                $mediaStmt->execute([$id, $mediaType, $mediaPath, $sortOrder]);
                                $sortOrder++;
                            }
                        }
                    }
                }
            }
            
            // Geriye dönük uyumluluk için eski alanlar
            if (isset($_FILES['media_images']) && is_array($_FILES['media_images']['name'])) {
                foreach ($_FILES['media_images']['name'] as $key => $fileName) {
                    if ($_FILES['media_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['media_images']['name'][$key],
                            'type' => $_FILES['media_images']['type'][$key],
                            'tmp_name' => $_FILES['media_images']['tmp_name'][$key],
                            'error' => $_FILES['media_images']['error'][$key]
                        ];
                        if (in_array($file['type'], $allowedImageTypes)) {
                            $uniqueFileName = uniqid() . '_' . basename($file['name']);
                            $targetPath = $uploadDir . $uniqueFileName;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $mediaPath = 'images/' . $uniqueFileName;
                                $mediaStmt = $pdo->prepare("INSERT INTO project_media (project_id, media_type, media_path, sort_order) VALUES (?, 'image', ?, ?)");
                                $mediaStmt->execute([$id, $mediaPath, $sortOrder]);
                                $sortOrder++;
                            }
                        }
                    }
                }
            }
            
            if (isset($_FILES['media_videos']) && is_array($_FILES['media_videos']['name'])) {
                foreach ($_FILES['media_videos']['name'] as $key => $fileName) {
                    if ($_FILES['media_videos']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['media_videos']['name'][$key],
                            'type' => $_FILES['media_videos']['type'][$key],
                            'tmp_name' => $_FILES['media_videos']['tmp_name'][$key],
                            'error' => $_FILES['media_videos']['error'][$key]
                        ];
                        if (in_array($file['type'], $allowedVideoTypes)) {
                            $uniqueFileName = uniqid() . '_' . basename($file['name']);
                            $targetPath = $uploadDir . $uniqueFileName;
                            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $mediaPath = 'images/' . $uniqueFileName;
                                $mediaStmt = $pdo->prepare("INSERT INTO project_media (project_id, media_type, media_path, sort_order) VALUES (?, 'video', ?, ?)");
                                $mediaStmt->execute([$id, $mediaPath, $sortOrder]);
                                $sortOrder++;
                            }
                        }
                    }
                }
            }
            
            // Medya silme işlemi
            if (isset($_POST['delete_media']) && is_array($_POST['delete_media'])) {
                foreach ($_POST['delete_media'] as $mediaId) {
                    $mediaId = intval($mediaId);
                    // Medya dosyasını al
                    $mediaStmt = $pdo->prepare("SELECT media_path FROM project_media WHERE id = ? AND project_id = ?");
                    $mediaStmt->execute([$mediaId, $id]);
                    $media = $mediaStmt->fetch();
                    if ($media && file_exists('../' . $media['media_path'])) {
                        @unlink('../' . $media['media_path']);
                    }
                    // Veritabanından sil
                    $deleteStmt = $pdo->prepare("DELETE FROM project_media WHERE id = ? AND project_id = ?");
                    $deleteStmt->execute([$mediaId, $id]);
                }
            }
            
            // Sıralama güncelleme
            if (isset($_POST['media_sort']) && is_array($_POST['media_sort'])) {
                foreach ($_POST['media_sort'] as $mediaId => $sortOrder) {
                    $mediaId = intval($mediaId);
                    $sortOrder = intval($sortOrder);
                    $sortStmt = $pdo->prepare("UPDATE project_media SET sort_order = ? WHERE id = ? AND project_id = ?");
                    $sortStmt->execute([$sortOrder, $mediaId, $id]);
                }
            }
        }
        
        // project_media tablosu var mı kontrol et, yoksa oluştur (edit için)
        if (!$mediaTableExists) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'project_media'");
                $mediaTableExists = $stmt->rowCount() > 0;
                
                if (!$mediaTableExists) {
                    $sql = "CREATE TABLE IF NOT EXISTS project_media (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        project_id INT NOT NULL,
                        media_type ENUM('image', 'video') NOT NULL,
                        media_path VARCHAR(500) NOT NULL,
                        sort_order INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_project_id (project_id),
                        INDEX idx_sort_order (sort_order)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    
                    $pdo->exec($sql);
                    $mediaTableExists = true;
                }
            } catch(PDOException $e) {
                error_log("Project media table creation error: " . $e->getMessage());
            }
        }
        
        $message = 'Proje başarıyla güncellendi!';
        $messageType = 'success';
        $action = 'list';
    }
}

// Düzenleme için proje bilgilerini getir
$project = null;
$projectMedia = [];
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) {
        $message = 'Proje bulunamadı!';
        $messageType = 'danger';
        $action = 'list';
    } else {
        // project_media tablosu var mı kontrol et
        $mediaTableExists = false;
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'project_media'");
            $mediaTableExists = $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            $mediaTableExists = false;
        }
        
        if ($mediaTableExists) {
            $mediaStmt = $pdo->prepare("SELECT * FROM project_media WHERE project_id = ? ORDER BY sort_order ASC");
            $mediaStmt->execute([$id]);
            $projectMedia = $mediaStmt->fetchAll();
        }
    }
}

// Liste sayfası
if ($action === 'list') {
    $pageTitle = "Projeler";
    include 'includes/header.php';
    
    $projects = $pdo->query("SELECT * FROM projects ORDER BY display_order ASC, created_at DESC")->fetchAll();
    ?>
    
    <?php if ($message): ?>
        <div class="mb-5 rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 dark:bg-success-500/15 dark:border-success-500/20' : 'bg-error-50 border-error-200 dark:bg-error-500/15 dark:border-error-500/20'; ?>">
            <p class="text-sm <?php echo $messageType === 'success' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-4 sm:px-6 sm:py-5">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Tüm Projeler</h3>
                <div class="flex items-center gap-3">
                    <button id="bulk-delete-btn" disabled class="inline-flex items-center gap-2 rounded-lg border border-error-500 bg-error-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-error-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 6H5H21M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Seçilenleri Sil (<span id="selected-count">0</span>)
                    </button>
                    <a href="?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4.5C12.4142 4.5 12.75 4.83579 12.75 5.25V11.25H18.75C19.1642 11.25 19.5 11.5858 19.5 12C19.5 12.4142 19.1642 12.75 18.75 12.75H12.75V18.75C12.75 19.1642 12.4142 19.5 12 19.5C11.5858 19.5 11.25 19.1642 11.25 18.75V12.75H5.25C4.83579 12.75 4.5 12.4142 4.5 12C4.5 11.5858 4.83579 11.25 5.25 11.25H11.25V5.25C11.25 4.83579 11.5858 4.5 12 4.5Z" fill="currentColor"/>
                        </svg>
                        Yeni Proje Ekle
                    </a>
                </div>
            </div>
        </div>
        <div class="p-5 border-t border-gray-100 dark:border-gray-800 sm:p-6">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <div class="max-w-full overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                  <th class="px-5 py-3 sm:px-6 admin-table-checkbox-header">
                    <div class="flex items-center">
                      <input type="checkbox" id="select-all" class="w-4 h-4 text-brand-500 bg-gray-100 border-gray-300 rounded focus:ring-brand-500 dark:focus:ring-brand-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    </div>
                  </th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">ID</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Başlık</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kategori</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Görsel</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Video</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tarih</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sıra</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">İşlemler</p></div></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="9" class="px-5 py-8 text-center">
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Henüz proje eklenmemiş.</p>
                            <a href="?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">İlk Projeyi Ekle</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                        <tr>
                            <td class="px-5 py-4 sm:px-6">
                              <div class="flex items-center">
                                <input type="checkbox" name="project_ids[]" value="<?php echo $proj['id']; ?>" class="project-checkbox w-4 h-4 text-brand-500 bg-gray-100 border-gray-300 rounded focus:ring-brand-500 dark:focus:ring-brand-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                              </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">#<?php echo $proj['id']; ?></p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="font-medium text-gray-800 text-theme-sm dark:text-white/90"><?php echo htmlspecialchars($proj['title']); ?></p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="rounded-full bg-brand-50 px-2 py-0.5 text-theme-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-500"><?php echo ucfirst($proj['category']); ?></p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <?php if ($proj['image_path']): ?>
                                    <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                                        <img src="../<?php echo htmlspecialchars($proj['image_path']); ?>" alt="" class="h-full w-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-theme-xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <?php if ($proj['video_path']): ?>
                                    <span class="text-success-600 text-theme-xs dark:text-success-500">✓ Video</span>
                                <?php elseif ($proj['vimeo_url']): ?>
                                    <span class="text-brand-600 text-theme-xs dark:text-brand-500">✓ Vimeo</span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-theme-xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400"><?php echo htmlspecialchars($proj['date'] ?? '-'); ?></p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400"><?php echo $proj['display_order']; ?></p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-2">
                                    <a href="?action=edit&id=<?php echo $proj['id']; ?>" class="inline-flex items-center gap-1 rounded-lg border border-brand-500 bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">Düzenle</a>
                                    <a href="?action=delete&id=<?php echo $proj['id']; ?>" class="inline-flex items-center gap-1 rounded-lg border border-error-500 bg-error-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-error-600 btn-delete">Sil</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        </div>
    </div>
    
    <form id="bulk-delete-form" method="POST" action="?action=bulk-delete" style="display: none;">
        <input type="hidden" name="ids" id="bulk-delete-ids">
    </form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const projectCheckboxes = document.querySelectorAll('.project-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const bulkDeleteIdsInput = document.getElementById('bulk-delete-ids');
    
    // Tümünü seç/seçimi kaldır
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            projectCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkDeleteButton();
        });
    }
    
    // Tekil checkbox değişiklikleri
    projectCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            updateBulkDeleteButton();
        });
    });
    
    // Select all checkbox'ı güncelle
    function updateSelectAllCheckbox() {
        if (selectAllCheckbox) {
            const allChecked = Array.from(projectCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(projectCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
    }
    
    // Bulk delete butonunu güncelle
    function updateBulkDeleteButton() {
        const selectedCount = Array.from(projectCheckboxes).filter(cb => cb.checked).length;
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedCount;
        }
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = selectedCount === 0;
        }
    }
    
    // Toplu silme
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const selectedIds = Array.from(projectCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                alert('Lütfen silinecek projeleri seçin!');
                return;
            }
            
            if (!confirm('Seçili ' + selectedIds.length + ' projeyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                return;
            }
            
            // Formu gönder
            if (bulkDeleteIdsInput && bulkDeleteForm) {
                bulkDeleteIdsInput.value = JSON.stringify(selectedIds);
                bulkDeleteForm.submit();
            }
        });
    }
    
    // İlk yüklemede butonu güncelle
    updateBulkDeleteButton();
});
</script>
    
    <?php include 'includes/footer.php'; ?>
    
<?php } else { // Add/Edit Form ?>
    <?php
    $pageTitle = $action === 'add' ? "Yeni Proje Ekle" : "Proje Düzenle";
    include 'includes/header.php';
    ?>
    
    <?php if ($message): ?>
        <div class="mb-5 rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 dark:bg-success-500/15 dark:border-success-500/20' : 'bg-error-50 border-error-200 dark:bg-error-500/15 dark:border-error-500/20'; ?>">
            <p class="text-sm <?php echo $messageType === 'success' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-4 sm:px-6 sm:py-5">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90"><?php echo $action === 'add' ? 'Yeni Proje Ekle' : 'Proje Düzenle'; ?></h3>
                <a href="projects.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Geri Dön</a>
            </div>
        </div>
        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Temel Bilgiler -->
            <div class="space-y-4">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 uppercase tracking-wide">Temel Bilgiler</h4>
                
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Başlık <span class="text-error-500">*</span></label>
                    <div class="space-y-3">
                        <div>
                            <label for="tr_title" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-500">Türkçe Başlık</label>
                            <input type="text" id="tr_title" name="tr_title" value="<?php echo htmlspecialchars($project['tr_title'] ?? $project['title'] ?? ''); ?>" required class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <div>
                            <label for="en_title" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-500">English Title</label>
                            <input type="text" id="en_title" name="en_title" value="<?php echo htmlspecialchars($project['en_title'] ?? ''); ?>" required class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                    </div>
                    <!-- Geriye dönük uyumluluk için gizli alan -->
                    <input type="hidden" name="title" value="<?php echo htmlspecialchars($project['tr_title'] ?? $project['title'] ?? ''); ?>">
                </div>
                
                <div>
                    <label for="category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kategori <span class="text-error-500">*</span></label>
                    <select id="category" name="category" required class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">Seçiniz</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php 
                            $catValue = $cat['slug'] ?? $cat['name'];
                            $selected = (($project['category'] ?? '') === $catValue || ($project['category_id'] ?? null) == ($cat['id'] ?? null)) ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($catValue); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categories) || (count($categories) > 0 && !isset($categories[0]['id']))): ?>
                        <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">
                            ⚠ Kategoriler tablosu oluşturulmamış. <a href="migrate_categories.php" class="underline">Kategorileri oluşturun</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Kapak Görseli/Video -->
            <div class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 uppercase tracking-wide">Kapak İçeriği</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Ana sayfada ve liste sayfalarında gösterilecek kapak görseli veya video (sadece 1 adet)</p>
                
                <div>
                    <label for="cover_media" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kapak Görseli veya Video</label>
                    
                    <!-- Mevcut kapak içeriği -->
                    <?php if (isset($project['image_path']) && $project['image_path']): ?>
                        <div class="mb-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <img src="../<?php echo htmlspecialchars($project['image_path']); ?>" alt="" class="max-w-[200px] rounded-lg border border-gray-200 dark:border-gray-700 mb-2">
                            <p class="text-xs text-gray-600 dark:text-gray-400">Mevcut Kapak Görseli</p>
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($project['image_path']); ?>">
                        </div>
                    <?php elseif (isset($project['video_path']) && $project['video_path']): ?>
                        <div class="mb-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Video</p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(basename($project['video_path'])); ?></p>
                                </div>
                            </div>
                            <input type="hidden" name="existing_video" value="<?php echo htmlspecialchars($project['video_path']); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tek dosya seçimi -->
                    <input type="file" id="cover_media" name="cover_media" accept="image/*,video/mp4,video/webm" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Görsel (JPG, PNG) veya Video (MP4, WebM) seçin. Yeni dosya seçildiğinde mevcut kapak içeriği değiştirilir.</p>
                </div>
            </div>
            
            <?php 
            // project_media tablosu var mı kontrol et
            $mediaTableExists = false;
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'project_media'");
                $mediaTableExists = $stmt->rowCount() > 0;
            } catch(PDOException $e) {
                $mediaTableExists = false;
            }
            
            // Link kolonlarının varlığını kontrol et
            $linkColumnsExist = false;
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'instagram_url'");
                $linkColumnsExist = $stmt->rowCount() > 0;
            } catch(PDOException $e) {
                $linkColumnsExist = false;
            }
            ?>
            
            <!-- Galeri İçeriği -->
            <?php 
            // project_media tablosu var mı kontrol et
            $mediaTableExists = false;
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'project_media'");
                $mediaTableExists = $stmt->rowCount() > 0;
            } catch(PDOException $e) {
                $mediaTableExists = false;
            }
            ?>
            
            <div class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 uppercase tracking-wide">Galeri İçeriği</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Detay sayfasında slider olarak gösterilecek görseller ve videolar</p>
                
                <?php if (!$mediaTableExists): ?>
                    <div class="mb-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:bg-yellow-500/15 dark:border-yellow-500/20">
                        <p class="text-sm text-yellow-800 dark:text-yellow-400">
                            ⚠ project_media tablosu bulunamadı. Form gönderildiğinde otomatik oluşturulacak.
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Mevcut medyalar -->
                <?php if (!empty($projectMedia)): ?>
                    <div class="mb-4 space-y-2">
                        <?php foreach ($projectMedia as $media): ?>
                            <div class="flex items-center gap-3 p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <?php if ($media['media_type'] === 'image'): ?>
                                    <img src="../<?php echo htmlspecialchars($media['media_path']); ?>" alt="" class="w-16 h-16 object-cover rounded">
                                <?php else: ?>
                                    <div class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <p class="text-xs font-medium"><?php echo htmlspecialchars($media['media_type'] === 'image' ? 'Görsel' : 'Video'); ?></p>
                                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars(basename($media['media_path'])); ?></p>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="delete_media[]" value="<?php echo $media['id']; ?>" class="rounded">
                                    <span class="text-xs text-red-600 dark:text-red-400">Sil</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Yeni medya yükleme - Birleşik alan -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Görsel veya Video Ekle</label>
                        <button type="button" id="add-media-field" class="inline-flex items-center gap-1 rounded-lg border border-brand-500 bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4.5C12.4142 4.5 12.75 4.83579 12.75 5.25V11.25H18.75C19.1642 11.25 19.5 11.5858 19.5 12C19.5 12.4142 19.1642 12.75 18.75 12.75H12.75V18.75C12.75 19.1642 12.4142 19.5 12 19.5C11.5858 19.5 11.25 19.1642 11.25 18.75V12.75H5.25C4.83579 12.75 4.5 12.4142 4.5 12C4.5 11.5858 4.83579 11.25 5.25 11.25H11.25V5.25C11.25 4.83579 11.5858 4.5 12 4.5Z" fill="currentColor"/>
                            </svg>
                            Medya Ekle
                        </button>
                    </div>
                    <div id="media-fields-container" class="space-y-3">
                        <div class="media-field-item flex items-center gap-3">
                            <input type="file" name="media_files[]" accept="image/*,video/mp4,video/webm" class="flex-1 dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <button type="button" class="remove-field-btn inline-flex items-center justify-center rounded-lg border border-error-500 bg-error-500 px-3 py-2 text-xs font-medium text-white hover:bg-error-600">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Her alan için bir görsel veya video seçin. İstediğiniz kadar medya ekleyebilirsiniz.</p>
                </div>
            </div>
            
            <!-- Gelişmiş Ayarlar (Daraltılabilir) -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <details class="group">
                    <summary class="cursor-pointer list-none py-2 px-0 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 uppercase tracking-wide m-0">Gelişmiş Ayarlar</h4>
                            <svg class="w-4 h-4 text-gray-400 group-open:rotate-180 transition-transform flex-shrink-0 ml-2 admin-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </summary>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Açıklama</label>
                            <div class="space-y-3">
                                <div>
                                    <label for="tr_description" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-500">Türkçe Açıklama</label>
                                    <textarea id="tr_description" name="tr_description" rows="3" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><?php echo htmlspecialchars($project['tr_description'] ?? $project['description'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label for="en_description" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-500">English Description</label>
                                    <textarea id="en_description" name="en_description" rows="3" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><?php echo htmlspecialchars($project['en_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <!-- Geriye dönük uyumluluk için gizli alan -->
                            <input type="hidden" name="description" value="<?php echo htmlspecialchars($project['tr_description'] ?? $project['description'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tarih</label>
                            <input type="text" id="date" name="date" value="<?php echo htmlspecialchars($project['date'] ?? ''); ?>" placeholder="Örn: 11/19, 2015 SS" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        
                        <div>
                            <label for="gallery_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Galeri ID</label>
                            <input type="number" id="gallery_id" name="gallery_id" value="<?php echo htmlspecialchars($project['gallery_id'] ?? ''); ?>" placeholder="Örn: 506" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        
                        <div>
                            <label for="display_order" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Görüntülenme Sırası</label>
                            <input type="number" id="display_order" name="display_order" value="<?php echo htmlspecialchars($project['display_order'] ?? 0); ?>" min="0" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Düşük sayılar önce görüntülenir</p>
                        </div>
                        
                        <div>
                            <label for="vimeo_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Vimeo URL</label>
                            <input type="url" id="vimeo_url" name="vimeo_url" value="<?php echo htmlspecialchars($project['vimeo_url'] ?? ''); ?>" placeholder="https://player.vimeo.com/video/..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        
                        <?php 
                        // Link kolonlarının varlığını kontrol et
                        $linkColumnsExist = false;
                        try {
                            $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'instagram_url'");
                            $linkColumnsExist = $stmt->rowCount() > 0;
                        } catch(PDOException $e) {
                            $linkColumnsExist = false;
                        }
                        ?>
                        
                        <?php if ($linkColumnsExist): ?>
                        <div>
                            <label for="instagram_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Instagram URL</label>
                            <input type="url" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($project['instagram_url'] ?? ''); ?>" placeholder="https://www.instagram.com/p/..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        
                        <div>
                            <label for="website_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Website URL</label>
                            <input type="url" id="website_url" name="website_url" value="<?php echo htmlspecialchars($project['website_url'] ?? ''); ?>" placeholder="https://example.com" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        
                        <div>
                            <label for="youtube_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">YouTube URL</label>
                            <input type="url" id="youtube_url" name="youtube_url" value="<?php echo htmlspecialchars($project['youtube_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=..." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        </div>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"><?php echo $action === 'add' ? 'Proje Ekle' : 'Güncelle'; ?></button>
                <a href="projects.php" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">İptal</a>
            </div>
        </form>
        </div>
    </div>
    
    <style>
    /* Gelişmiş Ayarlar butonu için özel stiller */
    details summary {
        display: block !important;
        list-style: none !important;
        cursor: pointer;
        user-select: none;
    }
    
    details summary::-webkit-details-marker {
        display: none !important;
    }
    
    details summary::marker {
        display: none !important;
    }
    
    details[open] summary {
        margin-bottom: 1rem;
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Birleşik medya alanı ekle
        const addMediaBtn = document.getElementById('add-media-field');
        const mediaContainer = document.getElementById('media-fields-container');
        
        if (addMediaBtn && mediaContainer) {
            addMediaBtn.addEventListener('click', function() {
                const newField = document.createElement('div');
                newField.className = 'media-field-item flex items-center gap-3';
                newField.innerHTML = `
                    <input type="file" name="media_files[]" accept="image/*,video/mp4,video/webm" class="flex-1 dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <button type="button" class="remove-field-btn inline-flex items-center justify-center rounded-lg border border-error-500 bg-error-500 px-3 py-2 text-xs font-medium text-white hover:bg-error-600" style="display: none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                `;
                mediaContainer.appendChild(newField);
                updateRemoveButtons();
            });
        }
        
        // Sil butonlarını güncelle
        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.remove-field-btn');
            removeButtons.forEach(btn => {
                btn.style.display = 'inline-flex';
                // Önceki event listener'ları kaldır
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                // Yeni event listener ekle
                newBtn.addEventListener('click', function() {
                    const fieldItem = this.closest('.media-field-item');
                    if (fieldItem) {
                        // En az bir alan kalmalı
                        const container = fieldItem.parentElement;
                        if (container.children.length > 1) {
                            fieldItem.remove();
                        }
                    }
                });
            });
        }
        
        // İlk yüklemede sil butonlarını güncelle
        updateRemoveButtons();
    });
    </script>
    
    <?php include 'includes/footer.php'; ?>
<?php } ?>

