<?php
// This file will be included in all pages to maintain consistent navigation
?>
<style>
    .sidebar {
        width: 240px;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-x: hidden;
        z-index: 1000;
    }

    .sidebar.collapsed {
        width: 64px;
    }

    .sidebar-header {
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        height: 60px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .toggle-sidebar {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s ease;
    }

    .sidebar.collapsed .toggle-sidebar {
        transform: rotate(180deg);
    }

    .logo-text {
        font-size: 1.25rem;
        font-weight: bold;
        background: linear-gradient(to right, #a855f7, #ec4899);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        white-space: nowrap;
        transition: opacity 0.3s;
    }

    .sidebar.collapsed .logo-text {
        opacity: 0;
        width: 0;
    }

    .nav-sections {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
        padding: 0.5rem;
    }

    .nav-section {
        margin-bottom: 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 0.5rem;
    }

    .nav-section:last-child {
        border-bottom: none;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        color: white;
        text-decoration: none;
        border-radius: 0.5rem;
        transition: background-color 0.2s;
        white-space: nowrap;
        position: relative;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .nav-item.active {
        background: linear-gradient(to right, #a855f7, #ec4899);
    }

    .nav-icon {
        width: 1.25rem;
        height: 1.25rem;
        flex-shrink: 0;
        margin-right: 1rem;
    }

    .sidebar.collapsed .nav-icon {
        margin-right: 0;
    }

    .nav-text {
        transition: opacity 0.3s;
        opacity: 1;
        text-decoration: none;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
        width: 0;
        display: none;
    }

    .bottom-section {
        padding: 0.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Tooltip for collapsed sidebar */
    .sidebar.collapsed .nav-item:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        white-space: nowrap;
        margin-left: 0.5rem;
        z-index: 1000;
    }

    /* Scrollbar styling */
    .nav-sections::-webkit-scrollbar {
        width: 4px;
    }

    .nav-sections::-webkit-scrollbar-track {
        background: transparent;
    }

    .nav-sections::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .nav-sections::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.2);
    }
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="toggle-sidebar" onclick="toggleSidebar()">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>
        <span class="logo-text">TravelHub</span>
    </div>

    <div class="nav-sections">
        <!-- Main Navigation -->
        <div class="nav-section">
            <a href="dashboard.php" class="nav-item" data-tooltip="Dashboard">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>

        <!-- Trips Section -->
        <div class="nav-section">
            <a href="mytrips.php" class="nav-item" data-tooltip="My Trips">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="nav-text">My Trips</span>
            </a>
            <a href="newtrip.php" class="nav-item" data-tooltip="New Trip">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="nav-text">New Trip</span>
            </a>
            <a href="categories.php" class="nav-item" data-tooltip="Categories">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <span class="nav-text">Categories</span>
            </a>
        </div>

        <!-- Tools Section -->
        <div class="nav-section">
            <a href="expenses.php" class="nav-item" data-tooltip="Expenses">
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="nav-text">Expenses</span>
            </a>
        </div>
    </div>

    <!-- Bottom Section with Logout -->
    <div class="bottom-section">
        <a href="logout.php" class="nav-item" data-tooltip="Logout">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        sidebar.classList.toggle('collapsed');
        
        if (mainContent) {
            mainContent.style.marginLeft = sidebar.classList.contains('collapsed') ? '64px' : '240px';
            mainContent.style.width = sidebar.classList.contains('collapsed') ? 'calc(100% - 64px)' : 'calc(100% - 240px)';
        }

        // Store the sidebar state in localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.style.marginLeft = '64px';
                mainContent.style.width = 'calc(100% - 64px)';
            }
        }

        // Highlight active navigation item based on current page
        const currentPage = window.location.pathname.split('/').pop();
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPage) {
                item.classList.add('active');
            }
        });
    });
</script>