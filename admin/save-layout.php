<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

// CORS headers (gerekirse)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz JSON: ' . json_last_error_msg()]);
    exit;
}

if (!isset($data['layout']) || !is_array($data['layout'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri formatı. Layout array bekleniyor.']);
    exit;
}

try {
    // project_layouts tablosu var mı kontrol et
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_layouts'");
    $layoutsTableExists = $stmt->rowCount() > 0;
    
    if (!$layoutsTableExists) {
        echo json_encode(['success' => false, 'message' => 'project_layouts tablosu bulunamadı. Lütfen önce create_project_layouts_table.php sayfasını çalıştırın.']);
        exit;
    }
    
    // Sayfa tipini al (URL parametresinden veya POST verisinden)
    $pageType = $_GET['page'] ?? $_POST['page'] ?? 'index';
    $validPages = ['index', 'editorial', 'advertising', 'film', 'cover'];
    if (!in_array($pageType, $validPages)) {
        $pageType = 'index';
    }
    
    $pdo->beginTransaction();
    $updatedCount = 0;
    
    foreach ($data['layout'] as $item) {
        if (!isset($item['id']) || !isset($item['x']) || !isset($item['y']) || !isset($item['w']) || !isset($item['h'])) {
            continue;
        }
        
        $projectId = intval($item['id']);
        $x = intval($item['x']);
        $y = intval($item['y']);
        $w = intval($item['w']);
        $h = intval($item['h']);
        
        // Proje var mı kontrol et
        $checkStmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
        $checkStmt->execute([$projectId]);
        if ($checkStmt->rowCount() == 0) {
            continue; // Proje yoksa atla
        }
        
        // project_layouts tablosuna kaydet (INSERT ... ON DUPLICATE KEY UPDATE)
        $stmt = $pdo->prepare("
            INSERT INTO project_layouts (project_id, page_type, grid_x, grid_y, grid_w, grid_h)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                grid_x = VALUES(grid_x),
                grid_y = VALUES(grid_y),
                grid_w = VALUES(grid_w),
                grid_h = VALUES(grid_h),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$projectId, $pageType, $x, $y, $w, $h]);
        $updatedCount++;
    }
    
    $pdo->commit();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => $updatedCount . ' proje düzeni başarıyla kaydedildi (' . $pageType . ' sayfası için)'], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Genel hata: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

