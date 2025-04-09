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

// Get unread message count
$unread_count = count_unread_messages($user_id);

// Return response
header('Content-Type: application/json');
echo json_encode(['unread_count' => $unread_count]);
?> 