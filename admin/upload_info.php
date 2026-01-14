<?php
/**
 * PHP Upload Settings Info Page
 * Displays current PHP file upload configuration
 */

echo "<h2>PHP Dosya Yükleme Ayarları</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; font-family: monospace;'>";
echo "<tr><th>Ayar</th><th>Değer</th><th>Açıklama</th></tr>";

$settings = [
    'upload_max_filesize' => [
        'value' => ini_get('upload_max_filesize'),
        'desc' => 'Tek bir dosya için maksimum yükleme boyutu'
    ],
    'post_max_size' => [
        'value' => ini_get('post_max_size'),
        'desc' => 'Form POST isteği için maksimum boyut (tüm dosyalar dahil)'
    ],
    'max_file_uploads' => [
        'value' => ini_get('max_file_uploads'),
        'desc' => 'Tek seferde yüklenebilecek maksimum dosya sayısı'
    ],
    'memory_limit' => [
        'value' => ini_get('memory_limit'),
        'desc' => 'PHP için maksimum bellek kullanımı'
    ],
    'max_execution_time' => [
        'value' => ini_get('max_execution_time') . ' saniye',
        'desc' => 'Script çalışma süresi limiti'
    ]
];

foreach ($settings as $name => $info) {
    echo "<tr>";
    echo "<td><strong>{$name}</strong></td>";
    echo "<td style='color: blue;'>{$info['value']}</td>";
    echo "<td>{$info['desc']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3 style='margin-top: 20px;'>Genel Bilgi:</h3>";
echo "<ul>";
echo "<li><strong>upload_max_filesize:</strong> 40MB - Tek bir dosya 40MB'dan büyük olamaz</li>";
echo "<li><strong>post_max_size:</strong> 40MB - Toplam form verisi (tüm dosyalar + form alanları) 40MB'dan büyük olamaz</li>";
echo "<li><strong>max_file_uploads:</strong> 20 - Bir formda maximum 20 dosya yüklenebilir</li>";
echo "</ul>";

echo "<p style='background: #ffffcc; padding: 10px; border-left: 4px solid orange;'>";
echo "<strong>NOT:</strong> Eğer daha büyük dosyalar yüklemek isterseniz, ";
echo "<code>/opt/lampp/etc/php.ini</code> dosyasındaki bu değerleri artırıp ";
echo "<strong>LAMPP'ı yeniden başlatmanız</strong> gerekir.";
echo "</p>";
