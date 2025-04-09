/**
 * Dark Mode Toggle Functionality
 * This script handles dark mode toggle and remembers user preference using localStorage
 */

document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;
    
    // Check if user previously enabled dark mode
    if (localStorage.getItem('darkMode') === 'enabled') {
        enableDarkMode();
    }
    
    // Add event listener if the toggle exists
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                enableDarkMode();
            } else {
                disableDarkMode();
            }
        });
    }
    
    /**
     * Enables dark mode by adding the dark-mode class and saving preference
     */
    function enableDarkMode() {
        body.classList.add('dark-mode');
        if (darkModeToggle) {
            darkModeToggle.checked = true;
        }
        localStorage.setItem('darkMode', 'enabled');
    }
    
    /**
     * Disables dark mode by removing the dark-mode class and saving preference
     */
    function disableDarkMode() {
        body.classList.remove('dark-mode');
        if (darkModeToggle) {
            darkModeToggle.checked = false;
        }
        localStorage.setItem('darkMode', 'disabled');
    }
    
    // Expose functions to window for potential external use
    window.enableDarkMode = enableDarkMode;
    window.disableDarkMode = disableDarkMode;
}); 