<div class="passcode-form">
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <h3 style="margin-bottom: var(--space-md);">Join this group</h3>
    
    <form method="POST">
        <?php if ($group['privacy'] === 'private'): ?>
            <div class="form-group">
                <label for="passcode" class="form-label required">Passcode</label>
                <input type="password" id="passcode" name="passcode" class="form-control" 
                       placeholder="Enter group passcode" required>
                <small style="font-size: 12px; color: var(--text-light);">
                    This is a private group - you need the passcode to join
                </small>
            </div>
        <?php endif; ?>
        
        <button type="submit" name="join-group" class="btn-submit">
            <i class="fas fa-user-plus"></i>
            Join Group
        </button>
    </form>
</div> 