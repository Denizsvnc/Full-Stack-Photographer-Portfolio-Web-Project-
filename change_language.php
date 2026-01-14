<?php
/**
 * Dil değiştirme endpoint'i
 */
require_once 'config.php';

$lang = $_GET['lang'] ?? 'tr';

if (setLanguage($lang)) {
    // Referer'a yönlendir veya ana sayfaya
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $redirect);
    exit;
} else {
    // Geçersiz dil
    header('Location: index.php');
    exit;
}


