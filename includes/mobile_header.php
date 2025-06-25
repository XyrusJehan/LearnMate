<header class="header">
    <div style="display: flex; align-items: center; gap: var(--space-md);">
        <a href="student_group.php" class="header-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="header-title">Group Details</h1>
    </div>
    <div class="header-actions">
        <?php if ($group['is_member']): ?>
            <button class="header-btn" onclick="document.getElementById('createPostForm').style.display='block'">
                <i class="fas fa-folder-open"></i>
            </button>
        <?php endif; ?>
    </div>
</header> 