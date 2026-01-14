<?php
/**
 * Config dosyasını yüklemek için yardımcı dosya
 * Bu dosya admin klasöründeki tüm dosyalar tarafından kullanılabilir
 */

// Admin klasöründen config.php'ye ulaş
$configFile = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'config.php';

if (file_exists($configFile)) {
    require_once $configFile;
} else {
    die("Config dosyası bulunamadı: " . $configFile);
}

