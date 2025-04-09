<?php
/**
 * Dark Mode Toggle Component
 * Include this file in admin pages to add dark mode functionality
 */
?>
<!-- Dark Mode Toggle Switch -->
<div class="flex items-center ml-4">
    <span class="mr-2 text-sm"><i class="fas fa-sun"></i></span>
    <div class="relative inline-block w-10 mr-2 align-middle select-none">
        <input type="checkbox" id="darkModeToggle" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
        <label for="darkModeToggle" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
    </div>
    <span class="text-sm"><i class="fas fa-moon"></i></span>
</div> 