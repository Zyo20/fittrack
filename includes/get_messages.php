<?php
session_start();
include_once 'db_connect.php';
include_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Get parameters
$user1_id = isset($_GET['user1_id']) ? (int)$_GET['user1_id'] : 0;
$user2_id = isset($_GET['user2_id']) ? (int)$_GET['user2_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

// Validate parameters
if ($user1_id <= 0 || $user2_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Mark messages from the other user as read
mark_messages_as_read($user2_id, $user1_id);

// Get new messages
$messages = get_new_messages($user1_id, $user2_id, $last_message_id);

// Format messages for response
$formatted_messages = [];
foreach ($messages as $message) {
    $formatted_messages[] = [
        'id' => $message['id'],
        'sender_id' => $message['sender_id'],
        'receiver_id' => $message['receiver_id'],
        'message' => $message['message'],
        'is_read' => $message['is_read'],
        'time' => date('h:i A, M d', strtotime($message['created_at']))
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($formatted_messages);
?> 