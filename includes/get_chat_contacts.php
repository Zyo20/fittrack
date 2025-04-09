<?php
session_start();
include_once 'db_connect.php';
include_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all chat contacts for this user
$contacts = get_chat_contacts($user_id);

// Calculate total unread messages
$total_unread = count_unread_messages($user_id);

// Return response
header('Content-Type: application/json');
echo json_encode([
    'contacts' => $contacts,
    'total_unread' => $total_unread
]);
?> 