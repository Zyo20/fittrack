<?php
// Setup script for the chatbot knowledge base
require_once 'includes/db_connect.php';

// Create chatbot_knowledge table if it doesn't exist
$conn->query("DROP TABLE IF EXISTS chatbot_knowledge");
$conn->query("CREATE TABLE IF NOT EXISTS chatbot_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (question(191))
)");

// Create a prepared statement for inserting data
$stmt = $conn->prepare("INSERT INTO chatbot_knowledge (question, answer, category) VALUES (?, ?, ?)");

// Define questions and answers
$questions = [
    ["What is FitTrack", "FitTrack is a fitness management system that connects customers with coaches to follow customized fitness programs and track progress.", "general"],
    ["How do I sign up", "You can sign up by clicking the Register button and filling out the registration form with your details.", "account"],
    ["How do I choose a coach", "As a customer, you can go to the \"Choose Coach\" page to view available coaches and select one that matches your fitness goals.", "customer"],
    ["How do I track my progress", "You can track your progress on the Progress page where you can log your measurements and view your improvement over time.", "customer"],
    ["How do I create a program", "As a coach, you can create new programs from your dashboard by going to the Programs section and clicking \"Create New Program\".", "coach"],
    ["How do I assign a program to a customer", "As a coach, go to your Customers list, select a customer, and use the \"Assign Program\" option to assign a program to them.", "coach"],
    ["What types of programs are available", "FitTrack offers various program types including Weight Loss, Muscle Building, Cardio Fitness, Strength Training, HIIT, and Flexibility & Mobility.", "programs"],
    ["How do I manage users", "As an admin, you can manage all users from the Admin Dashboard by going to the Users section where you can edit, delete or add new users.", "admin"]
];

// Insert the data
$success = true;
foreach ($questions as $q) {
    $stmt->bind_param("sss", $q[0], $q[1], $q[2]);
    if (!$stmt->execute()) {
        $success = false;
        echo "Error inserting question: " . $conn->error . "<br>";
    }
}

// Create chat_logs table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS chat_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 0,
    query TEXT NOT NULL,
    response TEXT NOT NULL,
    match_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Output results
if ($success) {
    echo "Chatbot knowledge base setup successfully!<br>";
    echo "Total questions added: " . count($questions) . "<br>";
    echo "<a href='index.php'>Return to homepage</a>";
} else {
    echo "There were some errors setting up the chatbot knowledge base.<br>";
}

// Display current knowledge base for verification
echo "<h2>Current Chatbot Knowledge:</h2>";
$result = $conn->query("SELECT id, question, answer, category FROM chatbot_knowledge");
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Question</th><th>Answer</th><th>Category</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['question'] . "</td>";
        echo "<td>" . $row['answer'] . "</td>";
        echo "<td>" . $row['category'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No questions in the knowledge base.";
}
?>