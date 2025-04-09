<?php
// Script to update the database schema

include_once 'includes/db_connect.php';

// Alter step_progress table to add pending_approval status
$query = "ALTER TABLE step_progress MODIFY status ENUM('pending', 'in_progress', 'pending_approval', 'completed') DEFAULT 'pending'";

if (mysqli_query($conn, $query)) {
    echo "Database schema updated successfully!";
} else {
    echo "Error updating database schema: " . mysqli_error($conn);
}
?> 