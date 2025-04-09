<?php
include_once 'includes/db_connect.php';

// SQL to create the chat messages table
$sql = "
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (sender_id, receiver_id),
    INDEX (receiver_id, sender_id)
)
";

if ($conn->query($sql) === TRUE) {
    echo "Chat messages table created successfully";
} else {
    echo "Error creating chat messages table: " . $conn->error;
}
?> 