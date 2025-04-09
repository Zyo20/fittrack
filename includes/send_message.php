<?php
session_start();
include_once 'db_connect.php';
include_once 'functions.php';

// Ensure all output is JSON
header('Content-Type: application/json');

// Disable error display to avoid HTML in output
ini_set('display_errors', 0);
error_reporting(0);

// Custom error handler to output JSON instead of HTML
function json_error_handler($errno, $errstr, $errfile, $errline) {
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr in $errfile on line $errline"
    ]);
    exit();
}
set_error_handler('json_error_handler');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit();
    }

    // Get data from POST
    $user_id = $_SESSION['user_id'];
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $message = isset($_POST['message']) ? $_POST['message'] : '';

    // Validate parameters
    if ($receiver_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid receiver ID']);
        exit();
    }

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }

    // Send the message
    $result = send_message($user_id, $receiver_id, $message);

    if ($result) {
        // Get the message ID that was just inserted
        $message_id = mysqli_insert_id($conn);
        
        // Get the message details
        $query = "SELECT * FROM chat_messages WHERE id = $message_id";
        $result = mysqli_query($conn, $query);
        $message_data = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $message_data['id'],
                'message' => $message_data['message'],
                'is_sent' => true,
                'is_read' => false,
                'time' => date('h:i A, M d', strtotime($message_data['created_at'])),
                'created_at' => $message_data['created_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . mysqli_error($conn)]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
?> 