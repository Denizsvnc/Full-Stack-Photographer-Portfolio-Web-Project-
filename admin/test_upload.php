<?php
// Test script for debugging file uploads
require_once __DIR__ . '/../config.php';

session_start();

// Simulate an add request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    echo "<h2>FILES Data</h2>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";

    if (isset($_FILES['cover_media'])) {
        echo "<h3>Cover Media Details:</h3>";
        echo "Error Code: " . $_FILES['cover_media']['error'] . "<br>";
        echo "Error Message: ";
        switch ($_FILES['cover_media']['error']) {
            case UPLOAD_ERR_OK:
                echo "No error";
                break;
            case UPLOAD_ERR_INI_SIZE:
                echo "File exceeds upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "File exceeds MAX_FILE_SIZE directive in HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "File was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "No file was uploaded";
                break;
            default:
                echo "Unknown error";
        }
        echo "<br>";
    }
} else {
    ?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Upload Test</title>
    </head>

    <body>
        <h1>File Upload Test</h1>
        <form method="POST" enctype="multipart/form-data">
            <label>Cover Media:</label>
            <input type="file" name="cover_media" accept="image/*,video/mp4,video/webm">
            <br><br>

            <label>Title:</label>
            <input type="text" name="tr_title" value="Test Project">
            <br><br>

            <button type="submit">Test Upload</button>
        </form>
    </body>

    </html>
    <?php
}
?>