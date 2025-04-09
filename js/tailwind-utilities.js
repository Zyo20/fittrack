/**
 * Tailwind CSS Utilities
 * 
 * This file provides utility functions to help with the transition from Bootstrap to Tailwind CSS.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile navigation toggle functionality
    const navbarToggles = document.querySelectorAll('[id^="navbarToggle"]');
    navbarToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target') || 'navbarMenu';
            const menu = document.getElementById(targetId);
            if (menu) {
                menu.classList.toggle('hidden');
            }
        });
    });

    // Dropdown functionality
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Find the dropdown menu
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                menu.classList.toggle('hidden');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        const menus = document.querySelectorAll('.dropdown-menu');
        menus.forEach(menu => {
            if (!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });

        // Also handle any custom dropdowns
        const customDropdowns = document.querySelectorAll('[id$="DropdownMenu"]');
        customDropdowns.forEach(menu => {
            if (!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });
    });

    // Modal functionality
    const modalToggles = document.querySelectorAll('[data-modal-target]');
    modalToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-modal-target');
            const modal = document.getElementById(targetId);
            if (modal) {
                modal.classList.remove('hidden');
            }
        });
    });

    const modalCloses = document.querySelectorAll('[data-modal-close]');
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        });
    });

    // Close modals when clicking overlay
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });

    // Alert dismissal
    const alertCloses = document.querySelectorAll('.alert-close');
    alertCloses.forEach(close => {
        close.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.classList.add('hidden');
            }
        });
    });

    // Tab functionality
    const tabToggles = document.querySelectorAll('[data-tab-target]');
    tabToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Deactivate all tabs in the same group
            const tabGroup = this.getAttribute('data-tab-group') || 'default';
            const tabs = document.querySelectorAll(`[data-tab-group="${tabGroup}"]`);
            tabs.forEach(tab => {
                tab.classList.remove('active', 'bg-white', 'border-blue-500', 'text-blue-600');
                tab.classList.add('text-gray-500', 'hover:text-gray-700');
            });
            
            // Activate this tab
            this.classList.add('active', 'bg-white', 'border-blue-500', 'text-blue-600');
            this.classList.remove('text-gray-500', 'hover:text-gray-700');
            
            // Hide all content
            const contentPanes = document.querySelectorAll(`[data-tab-content][data-tab-group="${tabGroup}"]`);
            contentPanes.forEach(pane => {
                pane.classList.add('hidden');
            });
            
            // Show target content
            const targetId = this.getAttribute('data-tab-target');
            const target = document.getElementById(targetId);
            if (target) {
                target.classList.remove('hidden');
            }
        });
    });
}); 