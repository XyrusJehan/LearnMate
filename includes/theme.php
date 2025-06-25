<?php
// Get user's theme preference
function getUserTheme($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? $result['theme_preference'] : 'light';
    } catch (PDOException $e) {
        error_log("Error getting theme preference: " . $e->getMessage());
        return 'light';
    }
}

// Set user's theme preference
function setUserTheme($pdo, $userId, $theme) {
    try {
        if (!in_array($theme, ['light', 'dark'])) {
            return false;
        }
        $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
        return $stmt->execute([$theme, $userId]);
    } catch (PDOException $e) {
        error_log("Error setting theme preference: " . $e->getMessage());
        return false;
    }
}

// Get current theme for the page
function getCurrentTheme() {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        return getUserTheme($pdo, $_SESSION['user_id']);
    }
    return 'light';
}
?> 