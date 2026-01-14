# Admin Panel Kurulum Kılavuzu

## 1. Veritabanı Kurulumu

### Yöntem 1: Otomatik Kurulum (Önerilen)
Tarayıcınızda şu adresi açın:
```
http://localhost/kursad/setup.php
```

Bu script otomatik olarak:
- Veritabanını oluşturur
- Gerekli tabloları oluşturur
- Varsayılan admin kullanıcısını ekler

### Yöntem 2: Manuel Kurulum
1. phpMyAdmin'e giriş yapın
2. `database.sql` dosyasını içe aktarın veya SQL komutlarını çalıştırın

## 2. Veritabanı Ayarları

`config.php` dosyasında veritabanı bilgilerinizi kontrol edin:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kursad_portfolio');
```

## 3. Admin Giriş Bilgileri

Varsayılan giriş bilgileri:
- **Kullanıcı Adı:** `admin`
- **Şifre:** `admin123`

**ÖNEMLİ:** İlk girişten sonra şifrenizi değiştirmeniz önerilir!

## 4. Admin Paneline Erişim

```
http://localhost/kursad/admin/login.php
```

## 5. Özellikler

### Dashboard
- Toplam proje sayısı
- Kategori bazında istatistikler
- Son eklenen projeler

### Proje Yönetimi
- ✅ Proje ekleme
- ✅ Proje düzenleme
- ✅ Proje silme
- ✅ Görsel yükleme
- ✅ Video yükleme
- ✅ Vimeo URL desteği
- ✅ Kategori yönetimi
- ✅ Sıralama (display_order)

### Güvenlik
- Session tabanlı giriş sistemi
- Şifre hash'leme (bcrypt)
- Admin kontrolü

## 6. Dosya Yükleme

- Görseller: `images/` klasörüne yüklenir
- Videolar: `images/` klasörüne yüklenir
- İzin verilen formatlar:
  - Görseller: JPEG, JPG, PNG, GIF
  - Videolar: MP4, WebM

## 7. Proje Kategorileri

- **Editorial:** Editöryel çalışmalar
- **Advertising:** Reklam çalışmaları
- **Film:** Film/video çalışmaları
- **Cover:** Kapak çalışmaları

## 8. Sorun Giderme

### Veritabanı bağlantı hatası
- `config.php` dosyasındaki bilgileri kontrol edin
- MySQL servisinin çalıştığından emin olun

### Giriş yapamıyorum
- Şifrenin doğru olduğundan emin olun
- Veritabanında admin kullanıcısının olduğunu kontrol edin
- `setup.php` dosyasını tekrar çalıştırın

### Dosya yüklenmiyor
- `images/` klasörünün yazma izni olduğundan emin olun
- PHP `upload_max_filesize` ayarını kontrol edin

## 9. Şifre Değiştirme

Admin şifresini değiştirmek için:
1. phpMyAdmin'e giriş yapın
2. `kursad_portfolio` veritabanını seçin
3. `admins` tablosunu açın
4. Şifre alanını düzenleyin
5. PHP'de şifreyi hash'leyin:
```php
echo password_hash('yeni_sifre', PASSWORD_DEFAULT);
```
6. Hash'lenmiş değeri veritabanına kaydedin

## 10. Yedekleme

Düzenli olarak veritabanını yedekleyin:
```bash
mysqldump -u root kursad_portfolio > backup.sql
```

