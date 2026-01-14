<?php
require_once __DIR__ . '/../config.php';

echo "<pre>";
echo "=== SON 5 PROJE ===\n\n";

try {
    $stmt = $pdo->query("SELECT id, title, image_path, video_path, created_at FROM projects ORDER BY id DESC LIMIT 5");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projects as $project) {
        echo "ID: " . $project['id'] . "\n";
        echo "Başlık: " . $project['title'] . "\n";
        echo "Görsel: " . ($project['image_path'] ?: 'YOK') . "\n";
        echo "Video: " . ($project['video_path'] ?: 'YOK') . "\n";
        echo "Eklenme: " . $project['created_at'] . "\n";

        // Dosya kontrolü
        if ($project['image_path']) {
            $filepath = __DIR__ . '/../' . $project['image_path'];
            echo "Dosya var mı: " . (file_exists($filepath) ? "EVET" : "HAYIR - $filepath") . "\n";
        }

        echo "\n---\n\n";
    }

    // project_media kontrolü
    echo "=== PROJECT_MEDIA TABLOSU ===\n\n";
    try {
        $stmt = $pdo->query("SELECT pm.*, p.title FROM project_media pm JOIN projects p ON pm.project_id = p.id ORDER BY pm.id DESC LIMIT 10");
        $media = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($media) > 0) {
            foreach ($media as $m) {
                echo "Media ID: " . $m['id'] . "\n";
                echo "Proje: " . $m['title'] . " (ID: " . $m['project_id'] . ")\n";
                echo "Tip: " . $m['media_type'] . "\n";
                echo "Yol: " . $m['media_path'] . "\n";
                $filepath = __DIR__ . '/../' . $m['media_path'];
                echo "Dosya var mı: " . (file_exists($filepath) ? "EVET" : "HAYIR") . "\n";
                echo "\n";
            }
        } else {
            echo "project_media tablosunda hiç kayıt yok.\n";
        }
    } catch (PDOException $e) {
        echo "project_media tablosu mevcut değil veya hata: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>