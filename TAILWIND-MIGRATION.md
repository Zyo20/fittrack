# Bootstrap to Tailwind CSS Migration Guide

This document provides guidelines for migrating the FitTrack application from Bootstrap to Tailwind CSS.

## What Has Been Done

- Added Tailwind CSS via CDN to replace Bootstrap
- Created utility JavaScript files to handle common Bootstrap functionality
- Updated core pages (index.php, login.php, register.php)
- Created a basic Tailwind configuration file
- Updated the site's CSS with Tailwind-compatible styles
- Added mobile menu and dropdown functionality

## Migration Steps for Remaining Pages

### 1. Update Head Section

Replace Bootstrap CSS with Tailwind:

```html
<!-- Remove this -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Add this -->
<script src="https://cdn.tailwindcss.com"></script>
```

### 2. Update Navigation

Replace Bootstrap navbar with Tailwind equivalent:

```html
<!-- Example Tailwind navigation -->
<nav class="bg-gray-800 text-white">
    <div class="container mx-auto px-4 py-3">
        <div class="flex flex-wrap justify-between items-center">
            <a class="text-xl font-bold" href="dashboard.php">FitTrack</a>
            <button class="md:hidden" type="button" id="navbarToggle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0" id="navbarMenu">
                <!-- Navigation links here -->
            </div>
        </div>
    </div>
</nav>
```

### 3. Common Bootstrap to Tailwind Class Conversions

| Bootstrap Class | Tailwind Equivalent |
|-----------------|---------------------|
| container | container mx-auto px-4 |
| row | flex flex-wrap |
| col / col-md-6 | w-full md:w-1/2 |
| mt-3 | mt-3 |
| mb-3 | mb-3 |
| p-3 | p-3 |
| btn | py-2 px-4 rounded |
| btn-primary | bg-blue-600 hover:bg-blue-700 text-white |
| btn-success | bg-green-600 hover:bg-green-700 text-white |
| btn-danger | bg-red-600 hover:bg-red-700 text-white |
| card | bg-white rounded-lg shadow-md overflow-hidden |
| alert-success | bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded |
| alert-danger | bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded |
| form-label | block text-gray-700 mb-2 |
| form-control | w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 |
| table | w-full divide-y divide-gray-200 |

### 4. Update JavaScript

Add dropdown and mobile menu functionality:

```html
<script src="../js/tailwind-utilities.js"></script>
<script>
    // Mobile menu toggle
    document.getElementById('navbarToggle').addEventListener('click', function() {
        const menu = document.getElementById('navbarMenu');
        menu.classList.toggle('hidden');
    });
    
    // User dropdown toggle
    const userDropdown = document.getElementById('userDropdown');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    
    if (userDropdown && userDropdownMenu) {
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('hidden');
        });
        
        document.addEventListener('click', function() {
            if (!userDropdownMenu.classList.contains('hidden')) {
                userDropdownMenu.classList.add('hidden');
            }
        });
    }
</script>
```

### 5. Grid System Conversion

Bootstrap uses a 12-column grid system, while Tailwind uses a fraction-based approach:

- col-12: w-full
- col-6: w-1/2
- col-4: w-1/3
- col-3: w-1/4
- col-8: w-2/3
- col-9: w-3/4

For responsive design:
- col-md-6: md:w-1/2
- col-lg-4: lg:w-1/3

### 6. Testing

After converting each page:
1. Test all functionality
2. Verify responsive behavior on different screen sizes
3. Ensure dropdowns, modals, and other interactive elements work
4. Check for any visual regressions

## Resources

- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Bootstrap to Tailwind Cheat Sheet](https://tailwindcomponents.com/cheatsheet/)
- [Tailwind UI Components](https://tailwindui.com/components)

## Migration Order Recommendation

1. Shared components (headers, footers, sidebars)
2. Customer pages
3. Coach pages
4. Admin pages
5. Utility pages (profile, settings, etc.)

## Notes

- Maintain consistent styling across all pages
- Refer to existing converted pages as examples
- Use the tailwind-utilities.js file for Bootstrap-like JavaScript functionality
- Consider using a build process for production to optimize Tailwind CSS 