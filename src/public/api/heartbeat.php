<?php
session_start();
require_once '../../app/models/user-functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Check if a user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Update user activity
$userId = $_SESSION['user']['id'];
if (updateUserActivity($userId)) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'timestamp' => date('Y-m-d H:i:s')]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update activity']);
}

