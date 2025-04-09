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

// Get parameters
$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

// Validate parameters
if ($other_user_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

// Mark messages as read
mark_messages_as_read($other_user_id, $user_id);

// Get new messages
$messages = get_new_messages($user_id, $other_user_id, $last_message_id);

// Also get read status updates for messages sent by the current user
$read_status_query = "SELECT id, is_read FROM chat_messages 
                     WHERE sender_id = $user_id AND receiver_id = $other_user_id 
                     AND is_read = 1";
$read_status_result = mysqli_query($conn, $read_status_query);
$read_status_updates = [];
while ($row = mysqli_fetch_assoc($read_status_result)) {
    $read_status_updates[$row['id']] = $row['is_read'];
}

// Format the messages for the response
$formatted_messages = [];
foreach ($messages as $message) {
    $is_sent = $message['sender_id'] == $user_id;
    $formatted_messages[] = [
        'id' => $message['id'],
        'message' => $message['message'],
        'is_sent' => $is_sent,
        'is_read' => $message['is_read'] == 1,
        'time' => date('h:i A, M d', strtotime($message['created_at'])),
        'created_at' => $message['created_at']
    ];
}

// Return messages as JSON
header('Content-Type: application/json');
echo json_encode([
    'messages' => $formatted_messages,
    'read_status_updates' => $read_status_updates,
    'unread_count' => count_unread_messages($user_id)
]);
?> 