<div class="create-post-form" id="createPostForm" style="<?php echo isset($_POST['share-folder']) ? '' : 'display: none;'; ?>">
    <?php if ($error && isset($_POST['share-folder'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php elseif ($success && isset($_POST['share-folder'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <h3 style="margin-bottom: var(--space-md);">Share Folder</h3>
    
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Select Folder to Share</label>
            <div class="folders-grid">
                <?php foreach ($userFolders as $folder): ?>
                    <div class="folder-item">
                        <label class="folder-checkbox">
                            <input type="radio" name="selected_folder" value="<?php echo $folder['id']; ?>" required>
                            <div class="folder-preview">
                                <div class="folder-name">
                                    <i class="fas fa-folder"></i>
                                    <?php echo htmlspecialchars($folder['name']); ?>
                                </div>
                                <div class="folder-meta">
                                    <?php echo $folder['flashcard_count']; ?> flashcards
                                </div>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" name="share-folder" class="btn-submit">
            <i class="fas fa-share-alt"></i>
            Share Selected Folder
        </button>
    </form>
</div> 