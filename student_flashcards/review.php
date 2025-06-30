<?php
session_start();
require '../db.php';
require '../includes/theme.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$studentId = $_SESSION['user_id'];
$message = '';

// Fetch all folders for the dropdown
$allFolders = [];
$stmt = $pdo->prepare("SELECT id, name FROM folders WHERE user_id = ? ORDER BY name");
$stmt->execute([$studentId]);
$allFolders = $stmt->fetchAll();

// Get active folder from URL or session
$activeFolderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
if ($activeFolderId !== null) {
    $_SESSION['active_folder_id'] = $activeFolderId;
} elseif (isset($_SESSION['active_folder_id'])) {
    $activeFolderId = $_SESSION['active_folder_id'];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $ajaxFolderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : $activeFolderId;
    
    if ($_POST['action'] === 'start' || !isset($_SESSION['review_flashcards'])) {
        // Clear previous session if folder changed
        if (isset($_SESSION['review_flashcards_folder']) && $_SESSION['review_flashcards_folder'] !== $ajaxFolderId) {
            unset($_SESSION['review_flashcards']);
        }
        
        // Fetch all flashcards for this user and folder
        if ($ajaxFolderId) {
            $stmt = $pdo->prepare("
                SELECT f.id, t.term_text, d.definition_text
                FROM flashcards f
                JOIN terms t ON f.term_id = t.id
                JOIN definitions d ON f.definition_id = d.id
                WHERE f.folder_id = ?
                ORDER BY RAND()
            ");
            $stmt->execute([$ajaxFolderId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT f.id, t.term_text, d.definition_text
                FROM flashcards f
                JOIN terms t ON f.term_id = t.id
                JOIN definitions d ON f.definition_id = d.id
                JOIN folders fo ON f.folder_id = fo.id
                WHERE fo.user_id = ?
                ORDER BY RAND()
            ");
            $stmt->execute([$studentId]);
        }
        
        $cards = $stmt->fetchAll();
        $_SESSION['review_flashcards'] = [
            'cards' => $cards,
            'index' => 0,
            'correct' => 0,
            'incorrect' => 0
        ];
        $_SESSION['review_flashcards_folder'] = $ajaxFolderId;
        
        if (count($cards) === 0) {
            echo json_encode(['status' => 'empty']);
            exit;
        }
        
        $card = $cards[0];
        echo json_encode([
            'status' => 'ok',
            'definition' => $card['definition_text'],
            'progress' => 1,
            'total' => count($cards),
            'correct' => 0,
            'incorrect' => 0
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'check') {
        $answer = trim($_POST['answer'] ?? '');
        $session = &$_SESSION['review_flashcards'];
        $card = $session['cards'][$session['index']];
        $isCorrect = (mb_strtolower($answer) === mb_strtolower($card['term_text']));
        
        if ($isCorrect) {
            $session['correct']++;
            // Update card_progress
            $progressStmt = $pdo->prepare("
                INSERT INTO card_progress (student_id, card_id, correct_attempts, total_attempts, accuracy, status)
                VALUES (?, ?, 1, 1, 100, 'learned')
                ON DUPLICATE KEY UPDATE
                    correct_attempts = correct_attempts + 1,
                    total_attempts = total_attempts + 1,
                    accuracy = ROUND(100 * (correct_attempts + 1) / (total_attempts + 1)),
                    status = 'learned'
            ");
            $progressStmt->execute([$studentId, $card['id']]);
        } else {
            $session['incorrect']++;
            // Update card_progress
            $progressStmt = $pdo->prepare("
                INSERT INTO card_progress (student_id, card_id, correct_attempts, total_attempts, accuracy, status)
                VALUES (?, ?, 0, 1, 0, 'in_progress')
                ON DUPLICATE KEY UPDATE
                    total_attempts = total_attempts + 1,
                    accuracy = ROUND(100 * correct_attempts / (total_attempts + 1)),
                    status = IF(status = 'learned', 'learned', 'in_progress')
            ");
            $progressStmt->execute([$studentId, $card['id']]);
        }
        
        echo json_encode([
            'status' => 'ok',
            'isCorrect' => $isCorrect,
            'correctTerm' => $card['term_text'],
            'correct' => $session['correct'],
            'incorrect' => $session['incorrect']
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'next') {
        $session = &$_SESSION['review_flashcards'];
        $session['index']++;
        
        if ($session['index'] >= count($session['cards'])) {
            echo json_encode([
                'status' => 'done',
                'correct' => $session['correct'],
                'incorrect' => $session['incorrect'],
                'total' => count($session['cards'])
            ]);
            unset($_SESSION['review_flashcards']);
            exit;
        }
        
        $card = $session['cards'][$session['index']];
        echo json_encode([
            'status' => 'ok',
            'definition' => $card['definition_text'],
            'progress' => $session['index'] + 1,
            'total' => count($session['cards']),
            'correct' => $session['correct'],
            'incorrect' => $session['incorrect']
        ]);
        exit;
    }
    
    exit;
}

// Get theme for the page
$theme = getCurrentTheme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Flashcards - LearnMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/theme.css">
    <style>
        :root {
            --primary: #7F56D9;
            --primary-light: #9E77ED;
            --primary-dark: #6941C6;
            --secondary: #36BFFA;
            --success: #32D583;
            --warning: #FDB022;
            --danger: #F97066;
            --text-dark: #101828;
            --text-medium: #475467;
            --text-light: #98A2B3;
            --bg-light: #F9FAFB;
            --bg-white: #FFFFFF;
            --border-light: #EAECF0;
            --shadow-xs: 0 1px 2px rgba(16, 24, 40, 0.05);
            --shadow-sm: 0 1px 3px rgba(16, 24, 40, 0.1), 0 1px 2px rgba(16, 24, 40, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(16, 24, 40, 0.1), 0 2px 4px -1px rgba(16, 24, 40, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(16, 24, 40, 0.1), 0 4px 6px -2px rgba(16, 24, 40, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(16, 24, 40, 0.1), 0 10px 10px -5px rgba(16, 24, 40, 0.04);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Inter', sans-serif;
            line-height: 1.5;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--space-xl);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background-color: var(--bg-light);
            color: var(--text-medium);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 500;
            box-shadow: var(--shadow-xs);
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .back-btn:hover {
            background-color: var(--primary-dark);
            box-shadow: var(--shadow-sm);
        }

        .title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .title i {
            color: var(--primary);
        }

        .progress-container {
            margin-bottom: var(--space-lg);
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-sm);
            font-size: 14px;
            color: var(--text-light);
        }

        .progress-bar {
            height: 8px;
            background-color: var(--border-light);
            border-radius: var(--radius-full);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--primary);
            border-radius: var(--radius-full);
            width: 0%;
            transition: width 0.5s cubic-bezier(0.65, 0, 0.35, 1);
        }

        .stats {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .stat-box {
            flex: 1;
            padding: var(--space-md);
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .stat-correct .stat-value {
            color: var(--success);
        }

        .stat-incorrect .stat-value {
            color: var(--danger);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
        }

        .flashcard-container {
            background-color: var(--bg-white);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-lg);
            transition: var(--transition);
        }

        .flashcard-container:hover {
            box-shadow: var(--shadow-md);
        }

        .definition {
            font-size: 20px;
            text-align: center;
            margin-bottom: var(--space-xl);
            color: var(--text-dark);
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .answer-input {
            width: 100%;
            padding: var(--space-md);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 16px;
            margin-bottom: var(--space-md);
            transition: var(--transition);
        }

        .answer-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.1);
        }

        .feedback {
            min-height: 24px;
            font-size: 16px;
            margin-bottom: var(--space-md);
            text-align: center;
            opacity: 0;
            transform: translateY(10px);
            transition: var(--transition);
        }

        .feedback.show {
            opacity: 1;
            transform: translateY(0);
        }

        .feedback.correct {
            color: var(--success);
        }

        .feedback.incorrect {
            color: var(--danger);
        }

        .next-btn {
            width: 100%;
            padding: var(--space-md);
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            opacity: 0;
            transform: translateY(10px);
        }

        .next-btn.show {
            opacity: 1;
            transform: translateY(0);
        }

        .next-btn:hover {
            background-color: var(--primary-dark);
        }

        .completion-screen {
            background-color: var(--bg-white);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }

        .completion-icon {
            width: 80px;
            height: 80px;
            background-color: var(--success-light);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            color: var(--success);
            font-size: 32px;
        }

        .completion-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: var(--space-md);
            color: var(--text-dark);
        }

        .score {
            font-size: 18px;
            margin-bottom: var(--space-xl);
            color: var(--text-medium);
        }

        .review-again-btn {
            padding: var(--space-md) var(--space-xl);
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .review-again-btn:hover {
            background-color: var(--primary-dark);
        }

        .empty-state {
            background-color: var(--bg-white);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            background-color: var(--bg-light);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-md);
            color: var(--danger);
            font-size: 32px;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: var(--space-sm);
            color: var(--text-dark);
        }

        .empty-text {
            color: var(--text-medium);
            margin-bottom: var(--space-lg);
        }

        .create-btn {
            padding: var(--space-md) var(--space-xl);
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .create-btn:hover {
            background-color: var(--primary-dark);
        }

        .folder-selector {
            margin-bottom: var(--space-lg);
        }

        .folder-select {
            width: 100%;
            padding: var(--space-md);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 16px;
            background-color: var(--bg-white);
            color: var(--text-dark);
            transition: var(--transition);
        }

        .folder-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                padding: var(--space-md);
            }
            
            .stats {
                flex-direction: column;
            }
            
            .flashcard-container {
                padding: var(--space-lg);
            }
            
            .definition {
                font-size: 18px;
            }
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="container">
        <div class="header">
            <a href="../student_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
        
        <h1 class="title">
            <i class="fas fa-book-open"></i>
            <span>Review Flashcards</span>
        </h1>
        
        <div class="folder-selector">
            <select id="folderSelect" class="folder-select">
                <option value="">All Folders</option>
                <?php foreach ($allFolders as $folder): ?>
                    <option value="<?php echo $folder['id']; ?>" <?php if ($activeFolderId == $folder['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($folder['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="progress-container">
            <div class="progress-info">
                <span>Progress: <span id="progressText">0</span>/<span id="totalText">0</span></span>
                <span><span id="percentage">0</span>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-box stat-correct">
                <div class="stat-value" id="correctCount">0</div>
                <div class="stat-label">Correct</div>
            </div>
            <div class="stat-box stat-incorrect">
                <div class="stat-value" id="incorrectCount">0</div>
                <div class="stat-label">Incorrect</div>
            </div>
        </div>
        
        <div id="reviewArea">
            <div class="flashcard-container" id="flashcard" style="display: none;">
                <div class="definition" id="definitionText"></div>
                <input type="text" class="answer-input" id="answerInput" placeholder="Type the term..." autocomplete="off">
                <div class="feedback" id="feedback"></div>
                <button class="next-btn" id="nextBtn">Next</button>
            </div>
            
            <div class="completion-screen" id="completionScreen" style="display: none;">
                <div class="completion-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h2 class="completion-title">Review Complete!</h2>
                <div class="score">
                    <span id="finalCorrect">0</span> correct, 
                    <span id="finalIncorrect">0</span> incorrect
                </div>
                <button class="review-again-btn" id="reviewAgainBtn">
                    <i class="fas fa-sync-alt"></i> Review Again
                </button>
            </div>
            
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="empty-title">No Flashcards Found</h3>
                <p class="empty-text">You don't have any flashcards to review in this folder.</p>
                <a href="../student_flashcards/flashcard.php" class="create-btn">
                    <i class="fas fa-plus"></i> Create Flashcards
                </a>
            </div>
        </div>
    </div>

    <script>
    let currentState = {
        progress: 1,
        total: 0,
        correct: 0,
        incorrect: 0
    };
    let waitingForNext = false;
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const totalText = document.getElementById('totalText');
    const percentageText = document.getElementById('percentage');
    const correctCount = document.getElementById('correctCount');
    const incorrectCount = document.getElementById('incorrectCount');
    const flashcard = document.getElementById('flashcard');
    const definitionText = document.getElementById('definitionText');
    const answerInput = document.getElementById('answerInput');
    const feedback = document.getElementById('feedback');
    const nextBtn = document.getElementById('nextBtn');
    const completionScreen = document.getElementById('completionScreen');
    const finalCorrect = document.getElementById('finalCorrect');
    const finalIncorrect = document.getElementById('finalIncorrect');
    const reviewAgainBtn = document.getElementById('reviewAgainBtn');
    const emptyState = document.getElementById('emptyState');
    const folderSelect = document.getElementById('folderSelect');

    function updateProgress() {
        const progress = currentState.progress-1;
        const total = currentState.total;
        const percentage = total > 0 ? Math.round((progress / total) * 100) : 0;
        
        progressFill.style.width = `${percentage}%`;
        progressText.textContent = progress;
        totalText.textContent = total;
        percentageText.textContent = percentage;
        correctCount.textContent = currentState.correct;
        incorrectCount.textContent = currentState.incorrect;
    }

    function showCard(definition) {
        flashcard.style.display = '';
        completionScreen.style.display = 'none';
        emptyState.style.display = 'none';
        definitionText.textContent = definition;
        answerInput.value = '';
        feedback.textContent = '';
        feedback.className = 'feedback';
        nextBtn.className = 'next-btn';
        answerInput.disabled = false;
        answerInput.focus();
        waitingForNext = false;
    }

    function showCompletion() {
        flashcard.style.display = 'none';
        completionScreen.style.display = '';
        finalCorrect.textContent = currentState.correct;
        finalIncorrect.textContent = currentState.incorrect;
        progressFill.style.width = '100%';
        progressText.textContent = currentState.total;
        percentageText.textContent = '100';
    }

    function showEmpty() {
        flashcard.style.display = 'none';
        completionScreen.style.display = 'none';
        emptyState.style.display = '';
        progressFill.style.width = '0%';
        progressText.textContent = '0';
        totalText.textContent = '0';
        percentageText.textContent = '0';
        correctCount.textContent = '0';
        incorrectCount.textContent = '0';
    }

    function startReview() {
        const folderId = folderSelect.value;
        let body = 'action=start';
        if (folderId) body += '&folder_id=' + encodeURIComponent(folderId);
        
        fetch('review.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body 
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'empty') {
                showEmpty();
            } else {
                currentState.progress = data.progress;
                currentState.total = data.total;
                currentState.correct = data.correct;
                currentState.incorrect = data.incorrect;
                updateProgress();
                showCard(data.definition);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function checkAnswer() {
        if (waitingForNext) return;
        const answer = answerInput.value.trim();
        if (!answer) return;
        
        const folderId = folderSelect.value;
        let body = 'action=check&answer=' + encodeURIComponent(answer);
        if (folderId) body += '&folder_id=' + encodeURIComponent(folderId);
        
        fetch('review.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body 
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                if (data.isCorrect) {
                    feedback.textContent = '✓ Correct! Well done!';
                    feedback.className = 'feedback correct show';
                    waitingForNext = true;
                    setTimeout(() => { nextCard(); }, 1000);
                } else {
                    feedback.textContent = '✗ Incorrect. The correct answer is: ' + data.correctTerm;
                    feedback.className = 'feedback incorrect show';
                    nextBtn.className = 'next-btn show';
                    answerInput.disabled = true;
                }
                currentState.correct = data.correct;
                currentState.incorrect = data.incorrect;
                updateProgress();
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    function nextCard() {
        const folderId = folderSelect.value;
        let body = 'action=next';
        if (folderId) body += '&folder_id=' + encodeURIComponent(folderId);
        
        fetch('review.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body 
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'done') {
                showCompletion();
            } else if (data.status === 'ok') {
                currentState.progress = data.progress;
                currentState.total = data.total;
                currentState.correct = data.correct;
                currentState.incorrect = data.incorrect;
                updateProgress();
                showCard(data.definition);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    // Event listeners
    answerInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') checkAnswer();
    });
    
    nextBtn.addEventListener('click', function() {
        nextCard();
    });
    
    reviewAgainBtn.addEventListener('click', function() {
        startReview();
    });
    
    folderSelect.addEventListener('change', function() {
        const folderId = this.value;
        const url = folderId ? `review.php?folder_id=${folderId}` : 'review.php';
        window.history.pushState({}, '', url);
        startReview();
    });
    
    // Start on load
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const folderId = urlParams.get('folder_id');
        if (folderId) {
            folderSelect.value = folderId;
        }
        startReview();
    });
    </script>
</body>
</html>