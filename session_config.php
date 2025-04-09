<?php
/**
 * Session configuration settings to improve reliability
 * Include this file at the top of all entry point PHP files
 */

// Make sure this is included before any output is sent to the browser

// Only start session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings
    // These need to be set before session_start()
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cache_limiter', 'nocache');
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    // Now start the session
    session_start();
}
?> 