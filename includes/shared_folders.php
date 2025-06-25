<div class="posts-container">
    <h2 style="margin-bottom: var(--space-lg);">
        <i class="fas fa-folder-open"></i>
        Shared Folders
    </h2>
    
    <?php if (empty($sharedFolders)): ?>
        <div style="text-align: center; padding: var(--space-xl); color: var(--text-light);">
            <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: var(--space-md); opacity: 0.5;"></i>
            <p>No folders shared yet. Be the first to share!</p>
        </div>
    <?php else: ?>
        <?php foreach ($sharedFolders as $folder): ?>
            <div class="post-card">
                <div class="post-author">
                    <div class="post-author-avatar">
                        <?php 
                            $words = explode(' ', $folder['first_name'] . ' ' . $folder['last_name']);
                            $initials = '';
                            foreach ($words as $word) {
                                $initials .= strtoupper(substr($word, 0, 1));
                                if (strlen($initials) >= 2) break;
                            }
                            echo $initials;
                        ?>
                    </div>
                    <div>
                        <div class="post-author-name"><?php echo htmlspecialchars($folder['first_name'] . ' ' . $folder['last_name']); ?></div>
                        <div class="post-date">Shared <?php echo date('M j, Y \a\t g:i a', strtotime($folder['shared_at'])); ?></div>
                    </div>
                </div>
                <div class="post-content">
                    <div class="shared-folder">
                        <i class="fas fa-folder"></i>
                        <div class="folder-info">
                            <div class="folder-name"><?php echo htmlspecialchars($folder['folder_name']); ?></div>
                            <div class="folder-meta"><?php echo $folder['flashcard_count']; ?> flashcards</div>
                        </div>
                        <a href="view_shared_folder.php?group_id=<?php echo $groupId; ?>&folder_id=<?php echo $folder['folder_id']; ?>" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Flashcards
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div> 