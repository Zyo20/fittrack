<?php
/**
 * Session configuration settings to improve reliability and security
 * Include this file at the top of all entry point PHP files
 */

// Make sure this is included before any output is sent to the browser

// Only start session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters before session_start
    session_name('OPFIT_SESSID'); // Custom session name instead of default PHPSESSID
    
    // Get current session cookie params to override only what we need
    $current = session_get_cookie_params();
    
    // Set secure, httponly cookies with strict SameSite policy
    session_set_cookie_params([
        'lifetime' => 0,                      // Session cookie (destroyed on browser close)
        'path' => '/',                        // Available across the entire domain
        'domain' => $current['domain'],       // Keep the current domain
        'secure' => isset($_SERVER['HTTPS']), // Secure flag if on HTTPS
        'httponly' => true,                   // Prevent JavaScript access
        'samesite' => 'Strict'                // Strict SameSite policy for CSRF protection
    ]);
    
    // Configure session settings
    // These need to be set before session_start()
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 1800);  // 30 minutes session maximum lifetime
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cache_limiter', 'nocache');
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    ini_set('session.hash_function', 'sha256'); // Use stronger hash function for session IDs
    ini_set('session.sid_length', 48);          // Use longer session IDs
    ini_set('session.sid_bits_per_character', 6); // More entropy per character
    
    // Regenerate session ID at times to prevent session fixation attacks
    $regenerate_session = false;
    
    // Regenerate session if it was created more than 30 minutes ago
    if (isset($_SESSION['created_at'])) {
        if (time() - $_SESSION['created_at'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created_at'] = time();
            $regenerate_session = true;
        }
    }
    
    // Now start the session
    session_start();
    
    // Set creation time if this is a new session or we regenerated the ID
    if (!isset($_SESSION['created_at']) || $regenerate_session) {
        $_SESSION['created_at'] = time();
    }
    
    // Set last activity time for session expiration tracking
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}
?> 