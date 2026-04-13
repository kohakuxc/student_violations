/**
 * Sidebar Toggle Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const body = document.body;

    // Open sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            body.classList.add('sidebar-open');
        });
    }

    // Close sidebar when close button clicked
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function() {
            body.classList.remove('sidebar-open');
        });
    }

    // Close sidebar when backdrop clicked
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            body.classList.remove('sidebar-open');
        });
    }

    // Close sidebar when a link is clicked
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            body.classList.remove('sidebar-open');
        });
    });

    // Close sidebar on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            body.classList.remove('sidebar-open');
        }
    });
});