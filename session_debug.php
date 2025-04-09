<?php
// Start or continue the session - MUST be before any output
include_once 'session_config.php';

// Now it's okay to output content
echo "<h1>Session Debugging Information</h1>";

// Show session ID
echo "<h2>Session Status</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>Session Status: " . session_status() . " (2 = active)</p>";

// Show all session variables
echo "<h2>Current Session Data</h2>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

// Show session cookie parameters
echo "<h2>Session Cookie Parameters</h2>";
echo "<pre>";
print_r(session_get_cookie_params());
echo "</pre>";

// Show all PHP settings related to sessions
echo "<h2>PHP Session Configuration</h2>";
echo "<pre>";
$session_settings = [];
foreach (ini_get_all() as $key => $value) {
    if (strpos($key, 'session') === 0) {
        $session_settings[$key] = $value;
    }
}
print_r($session_settings);
echo "</pre>";

// Show all cookies
echo "<h2>Cookies</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Test setting a session variable
$_SESSION['test_value'] = 'This is a test value set at ' . date('Y-m-d H:i:s');
echo "<p>Test session variable set. Refresh the page to see if it persists.</p>";

// Test if headers have been sent
echo "<h2>Headers Status</h2>";
if (headers_sent($file, $line)) {
    echo "<p>Headers already sent in $file on line $line</p>";
} else {
    echo "<p>Headers not yet sent</p>";
}

// Show path info
echo "<h2>Path Information</h2>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p>Session Save Path: " . ini_get('session.save_path') . "</p>";

// Check session save path permissions
$save_path = ini_get('session.save_path');
echo "<p>Session Save Path exists: " . (file_exists($save_path) ? 'Yes' : 'No') . "</p>";
echo "<p>Session Save Path writable: " . (is_writable($save_path) ? 'Yes' : 'No') . "</p>";

// Set a test cookie
setcookie('test_cookie', 'Test value', time() + 3600, '/');
echo "<p>Test cookie set. Check if it appears after refresh.</p>";

// Show available login test 
echo "<h2>Try Login</h2>";
echo "<p><a href='login.php'>Go to login page</a></p>";
?> 