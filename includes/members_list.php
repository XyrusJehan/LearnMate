<div class="members-list">
    <h2 style="margin-bottom: var(--space-lg);">
        <i class="fas fa-users"></i>
        Members
    </h2>
    
    <?php foreach ($members as $member): ?>
        <div class="member-card">
            <div class="member-avatar">
                <?php 
                    $words = explode(' ', $member['first_name'] . ' ' . $member['last_name']);
                    $initials = '';
                    foreach ($words as $word) {
                        $initials .= strtoupper(substr($word, 0, 1));
                        if (strlen($initials) >= 2) break;
                    }
                    echo $initials;
                ?>
            </div>
            <div class="member-info">
                <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
            </div>
            <div class="member-meta">
                <?php if ($member['is_admin']): ?>
                    <div class="member-role">Admin</div>
                <?php endif; ?>
                <div class="member-joined">Joined <?php echo date('M j, Y', strtotime($member['joined_at'])); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div> 