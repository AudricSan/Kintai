/**
 * myshift — Core JS (Phase 1)
 */
'use strict';

// --- Sidebar toggle (mobile) ---
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
});
