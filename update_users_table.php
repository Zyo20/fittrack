<?php
/**
 * Updates the users table to add a notifications_enabled column
 */

include_once 'includes/db_connect.php';

// SQL to add notifications_enabled column
$sql = "
ALTER TABLE users
ADD COLUMN notifications_enabled TINYINT(1) NOT NULL DEFAULT 0
";

// Execute the SQL
if (mysqli_query($conn, $sql)) {
    echo "Users table updated successfully. Added notifications_enabled column.<br>";
} else {
    // If the column already exists, it will fail gracefully
    if (strpos(mysqli_error($conn), "Duplicate column name") !== false) {
        echo "Column notifications_enabled already exists in users table.<br>";
    } else {
        echo "Error updating users table: " . mysqli_error($conn) . "<br>";
    }
}

mysqli_close($conn);
?> 