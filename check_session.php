<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Output session configuration
echo "<h2>PHP Session Configuration</h2>";
echo "<pre>";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.name: " . ini_get('session.name') . "\n";
echo "session.save_handler: " . ini_get('session.save_handler') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session.use_strict_mode: " . ini_get('session.use_strict_mode') . "\n";
echo "session.use_cookies: " . ini_get('session.use_cookies') . "\n";
echo "session.use_only_cookies: " . ini_get('session.use_only_cookies') . "\n";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . "\n";
echo "</pre>";

// Check if we can set and retrieve a session value
echo "<h2>Session Test</h2>";
$_SESSION['test_value'] = "This is a test value set at " . date('Y-m-d H:i:s');
echo "Session value set. Current session data:<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Database connection check
echo "<h2>Database Connection Check</h2>";
include_once 'includes/db_connect.php';
if (isset($conn) && $conn) {
    echo "Database connection successful.<br>";
    
    // Test query
    $query = "SHOW TABLES";
    $result = mysqli_query($conn, $query);
    if ($result) {
        echo "Tables in database:<br><ul>";
        while ($row = mysqli_fetch_row($result)) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "Error running query: " . mysqli_error($conn);
    }
} else {
    echo "Database connection failed.";
}

// Cookie check
echo "<h2>Cookie Test</h2>";
$cookie_name = "test_cookie";
$cookie_value = "Test value";
setcookie($cookie_name, $cookie_value, time() + 3600, "/");
echo "Cookie '$cookie_name' set. Check if it appears in your browser cookies.";

// Session ID info
echo "<h2>Session ID Information</h2>";
echo "Current session ID: " . session_id() . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session status: " . session_status() . " (0=disabled, 1=none, 2=active)<br>";

// Instructions for next steps
echo "<h2>Next Steps</h2>";
echo "1. Note any issues with the session configuration above.<br>";
echo "2. <a href='login.php'>Try logging in</a><br>";
echo "3. If login fails, check the PHP error log for details.<br>";
?> 