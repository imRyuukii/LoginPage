<?php
// src/app/controllers/upload-profile-picture.php

global $db;
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();

require_once '../security/csrf.php';
require_once __DIR__ . '/../security/headers.php';
require_once __DIR__ . '/../security/session_guard.php';
require_once '../../config/database.php';

csrf_ensure_initialized();
apply_default_security_headers();
apply_sensitive_nocache();
session_enforce_password_rotation();
csrf_require_post();

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    header('Location: /LoginPage/src/app/controllers/login.php');
    exit;
}

$userId = $_SESSION['user']['id'];
$uploadDir = __DIR__ . '/../../public/images/profile-pictures/';
$maxFileSize = 2 * 1024 * 1024; // 2MB (PHP limit)
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $_SESSION['upload_error'] = 'Failed to create upload directory. Path: ' . $uploadDir;
        header('Location: profile.php');
        exit;
    }
}

// Ensure directory is writable
if (!is_writable($uploadDir)) {
    $_SESSION['upload_error'] = 'Upload directory is not writable.';
    header('Location: profile.php');
    exit;
}

// Validate file upload
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded or upload error occurred.';
    if (isset($_FILES['profile_picture']['error'])) {
        $errorMsg .= ' Error code: ' . $_FILES['profile_picture']['error'];
    }
    $_SESSION['upload_error'] = $errorMsg;
    header('Location: profile.php');
    exit;
}

$file = $_FILES['profile_picture'];

// Check file size
if ($file['size'] > $maxFileSize) {
    $_SESSION['upload_error'] = 'File size exceeds 2MB limit.';
    header('Location: profile.php');
    exit;
}

// Verify MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    $_SESSION['upload_error'] = 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
    header('Location: profile.php');
    exit;
}

// Verify file extension
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    $_SESSION['upload_error'] = 'Invalid file extension. Only JPG, PNG, GIF, and WebP are allowed.';
    header('Location: profile.php');
    exit;
}

// Generate unique filename
$filename = 'user_' . $userId . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Get old profile picture to delete it
$stmt = $db->query('SELECT profile_picture FROM users WHERE id = ?', [$userId]);
$oldPicture = $stmt->fetchColumn();

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    $_SESSION['upload_error'] = 'Failed to save uploaded file.';
    header('Location: profile.php');
    exit;
}

// Extra hardening: ensure the saved file is a real image and set safe permissions
$validImage = false;
if (function_exists('exif_imagetype')) {
    $imgType = @exif_imagetype($filepath);
    $validImage = ($imgType !== false);
} else {
    // Fallback when EXIF extension is not available
    $probe = @getimagesize($filepath); // returns false if not a valid image
    $validImage = ($probe !== false);
}
if (!$validImage) {
    @unlink($filepath);
    $_SESSION['upload_error'] = 'Uploaded file is not a valid image.';
    header('Location: profile.php');
    exit;
}
// Enforce dimensions (max 1024x1024)
$dim = @getimagesize($filepath);
if (!$dim || $dim[0] > 1024 || $dim[1] > 1024) {
    @unlink($filepath);
    $_SESSION['upload_error'] = 'Image too large. Max 1024x1024 pixels.';
    header('Location: profile.php');
    exit;
}
// Set safe perms (rw-r--r--)
@chmod($filepath, 0644);

// Update database
try {
    $db->query('UPDATE users SET profile_picture = ? WHERE id = ?', [$filename, $userId]);

    // Update session data
    $_SESSION['user']['profile_picture'] = $filename;

    // Delete old profile picture if it exists
    if ($oldPicture && file_exists($uploadDir . $oldPicture)) {
        unlink($uploadDir . $oldPicture);
    }

    $_SESSION['upload_success'] = 'Profile picture updated successfully!';
} catch (Exception $e) {
    // If database update fails, remove the uploaded file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    $_SESSION['upload_error'] = 'Failed to update profile picture in database.';
}

header('Location: profile.php');
exit;