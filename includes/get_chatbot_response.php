<?php
// Handle chatbot queries
require_once 'db_connect.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add error handling to catch any database or processing issues
try {
    // Ensure a query was sent
    if (!isset($_POST['query']) || empty($_POST['query'])) {
        echo json_encode(['status' => 'error', 'message' => 'No query provided']);
        exit;
    }

    $query = trim($_POST['query']);
    
    // Debug info
    $debug = [
        'original_query' => $query,
        'normalized_query' => strtolower($query),
        'matches' => []
    ];

    // Simple keyword matching system
    function findBestMatch($conn, $query, &$debug) {
        // Convert query to lowercase for better matching
        $query = strtolower($query);
        $debug['normalized_query'] = $query;
        
        // Get all questions from the knowledge base
        $stmt = $conn->prepare("SELECT id, question, answer FROM chatbot_knowledge");
        $stmt->execute();
        $result = $stmt->get_result();
        
        // If no results, the table might be empty
        if ($result->num_rows === 0) {
            $debug['error'] = "No entries found in chatbot_knowledge table";
            return [
                'answer' => "I'm unable to answer questions right now. The knowledge base appears to be empty.",
                'score' => 0
            ];
        }
        
        $bestMatch = null;
        $highestScore = 0;
        
        while ($row = $result->fetch_assoc()) {
            $question = strtolower($row['question']);
            
            // Calculate similarity score
            $score = 0;
            
            // Exact match gives highest score
            if ($query === $question) {
                $score = 10;
            }
            
            // Contains match (query contains question or question contains query)
            else if (strpos($query, $question) !== false) {
                $score = 5;
            }
            else if (strpos($question, $query) !== false) {
                $score = 5;
            }
            
            // Keyword matching
            else {
                $keywords = explode(' ', $question);
                
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) > 3 && strpos($query, $keyword) !== false) {
                        $score += 1;
                    }
                }
            }
            
            // Add this match to debug info
            $debug['matches'][] = [
                'question' => $row['question'],
                'score' => $score,
                'answer' => substr($row['answer'], 0, 50) . '...'
            ];
            
            // If this is a better match than previous best
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $row;
            }
        }
        
        // If no good match found or no rows returned
        if ($highestScore < 1 || $bestMatch === null) {
            return [
                'answer' => "I'm sorry, I don't have information about that yet. Please try asking about how to use FitTrack features like programs, progress tracking, or user management.",
                'score' => 0
            ];
        }
        
        return [
            'answer' => $bestMatch['answer'],
            'score' => $highestScore
        ];
    }

    // Get the best matching response
    $response = findBestMatch($conn, $query, $debug);

    // Log this interaction for future improvement
    try {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, query, response, match_score) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $user_id, $query, $response['answer'], $response['score']);
        $stmt->execute();
    } catch (Exception $e) {
        // If logging fails, don't fail the whole request
        $debug['log_error'] = $e->getMessage();
    }

    // Return the response as JSON
    echo json_encode([
        'status' => 'success',
        'message' => $response['answer'],
        'debug' => $debug  // Include debug info for troubleshooting
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Chatbot error: " . $e->getMessage(), 0);
    
    // Return friendly error message with debug info
    echo json_encode([
        'status' => 'error',
        'message' => "I'm having trouble answering questions right now. Please try again later.",
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>