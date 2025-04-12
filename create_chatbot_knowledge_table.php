<?php
// Create the chatbot knowledge base table
require_once 'includes/db_connect.php';

try {
    // Read the SQL file
    $sql = file_get_contents('create_chatbot_table.sql');
    
    // Execute the SQL queries
    $conn->multi_query($sql);
    
    // Process all result sets
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "Chatbot knowledge base table created successfully!";
} catch (Exception $e) {
    echo "Error creating chatbot knowledge base table: " . $e->getMessage();
}
?>