<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>LearnMate</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="flashcards.php" class="nav-link">
                    <i class="fas fa-layer-group"></i>
                    <span>Flashcards</span>
                </a>
            </li>
            <li>
                <a href="student_group.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Study Groups</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    background: var(--bg-secondary);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
}

.sidebar-header {
    padding: var(--space-lg);
    border-bottom: 1px solid var(--border-color);
}

.logo {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    color: var(--text-primary);
    text-decoration: none;
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar-nav {
    flex: 1;
    padding: var(--space-md);
}

.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: var(--radius-md);
    transition: all 0.2s ease;
}

.nav-link:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.nav-link i {
    width: 20px;
    text-align: center;
}

.sidebar-footer {
    padding: var(--space-md);
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .sidebar {
        display: none;
    }
}
</style> 