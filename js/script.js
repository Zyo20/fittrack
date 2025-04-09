/**
 * Main JavaScript file for FitTrack
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Tailwind utilities
    if (typeof initTailwindUtilities === 'function') {
        initTailwindUtilities();
    }
    
    // Configure Tailwind with custom configuration
    if (window.tailwind && typeof tailwind.config === 'function') {
        tailwind.config({
            theme: {
                extend: {
                    colors: {
                        primary: '#4361ee',
                        secondary: '#6c757d',
                        success: '#4aa96c',
                        danger: '#dc3545',
                        warning: '#ff9f43',
                        info: '#3db2ff',
                        dark: '#2b2d42',
                    }
                }
            }
        });
    }
    
    // Initialize any custom functionality
    initCustomFunctionality();
});

/**
 * Initialize custom app functionality
 */
function initCustomFunctionality() {
    // Mobile navigation toggle
    const navbarToggle = document.getElementById('navbarToggle');
    if (navbarToggle) {
        navbarToggle.addEventListener('click', function() {
            const menu = document.getElementById('navbarMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        });
    }
    
    // User dropdown functionality
    const userDropdown = document.getElementById('userDropdown');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdown && userDropdownMenu) {
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            if (!userDropdownMenu.classList.contains('hidden')) {
                userDropdownMenu.classList.add('hidden');
            }
        });
    }
} 