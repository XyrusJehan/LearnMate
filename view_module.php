<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get module ID from URL
$moduleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch module details
$stmt = $conn->prepare("
    SELECT m.*, u.first_name, u.last_name, c.class_name 
    FROM modules m 
    JOIN users u ON m.uploaded_by = u.id 
    JOIN classes c ON m.class_id = c.id 
    WHERE m.id = ?
");
$stmt->bind_param('i', $moduleId);
$stmt->execute();
$result = $stmt->get_result();
$module = $result->fetch_assoc();

if (!$module) {
    header('Location: index.php');
    exit();
}

// Check if user has access to this module
$stmt = $conn->prepare("
    SELECT 1 FROM class_students 
    WHERE class_id = ? AND student_id = ?
    UNION
    SELECT 1 FROM classes 
    WHERE id = ? AND teacher_id = ?
");
$stmt->bind_param('iiii', $module['class_id'], $_SESSION['user_id'], $module['class_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['title']); ?> - LearnMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #7F56D9;
            --primary-light: #9E77ED;
            --primary-dark: #6941C6;
            --text-dark: #101828;
            --text-medium: #475467;
            --text-light: #98A2B3;
            --bg-light: #F9FAFB;
            --bg-white: #FFFFFF;
            --border-light: #EAECF0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: var(--bg-white);
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(16, 24, 40, 0.1);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 200;
            transition: box-shadow 0.2s;
        }

        .header.sticky-shadow {
            box-shadow: 0 4px 16px rgba(16,24,40,0.12);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-medium);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
            background: var(--bg-light);
        }

        .back-button:hover {
            background-color: var(--primary-light);
            color: white;
        }

        .module-info {
            background-color: var(--bg-white);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(16, 24, 40, 0.1);
            margin-bottom: 24px;
        }

        .module-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text-dark);
        }

        .module-meta {
            display: flex;
            gap: 24px;
            color: var(--text-medium);
            font-size: 14px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-light);
            padding: 8px 12px;
            border-radius: 8px;
        }

        .pdf-container {
            background-color: var(--bg-white);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(16, 24, 40, 0.1);
            height: calc(297mm + 100px);
            min-height: 500px;
            margin-bottom: 24px;
            position: relative;
        }

        .pdf-viewer {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .pdf-controls {
            position: absolute;
            bottom: 40px;
            right: 40px;
            display: flex;
            gap: 12px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .page-controls {
            position: absolute;
            bottom: 40px;
            left: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 8px 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .page-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-medium);
            font-size: 14px;
        }

        .page-number {
            font-weight: 600;
            color: var(--text-dark);
        }

        .total-pages {
            color: var(--text-light);
        }

        .control-button {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            background: var(--bg-light);
            color: var(--text-medium);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .control-button:hover {
            background: var(--primary-light);
            color: white;
        }

        .control-button.active {
            background: var(--primary);
            color: white;
        }

        .control-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--bg-light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .tooltip {
            position: relative;
        }

        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 4px 8px;
            background: var(--text-dark);
            color: white;
            font-size: 12px;
            border-radius: 4px;
            white-space: nowrap;
            margin-bottom: 4px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }

            .header {
                padding: 12px 16px;
                position: static;
            }

            .module-info {
                padding: 16px;
            }

            .module-title {
                font-size: 20px;
            }

            .module-meta {
                flex-direction: column;
                gap: 12px;
            }

            .pdf-container {
                height: calc(297mm + 50px);
                padding: 16px;
            }

            .pdf-controls {
                bottom: 20px;
                right: 20px;
                padding: 4px;
            }

            .page-controls {
                bottom: 20px;
                left: 20px;
                padding: 4px 8px;
            }

            .control-button {
                width: 36px;
                height: 36px;
            }
        }

        /* Fullscreen styles */
        .pdf-container.fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000;
            border-radius: 0;
            padding: 0;
        }

        .pdf-container.fullscreen .pdf-viewer {
            border-radius: 0;
        }

        .pdf-container.fullscreen .pdf-controls {
            bottom: 20px;
            right: 20px;
        }

        .pdf-container.fullscreen .page-controls {
            bottom: 20px;
            left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" id="stickyHeader">
            <a href="class_details.php?id=<?php echo $module['class_id']; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Class
            </a>
            <div class="header-actions">
                <a href="<?php echo htmlspecialchars($module['file_path']); ?>" download class="back-button">
                    <i class="fas fa-download"></i>
                    Download PDF
                </a>
            </div>
        </div>

        <div class="module-info">
            <h1 class="module-title"><?php echo htmlspecialchars($module['title']); ?></h1>
            <div class="module-meta">
                <div class="meta-item">
                    <i class="fas fa-book"></i>
                    <span>Class: <?php echo htmlspecialchars($module['class_name']); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <span>Uploaded by: <?php echo htmlspecialchars($module['first_name'] . ' ' . $module['last_name']); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Uploaded: <?php echo date('F j, Y', strtotime($module['uploaded_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="pdf-container" id="pdfContainer">
            <iframe 
                src="<?php echo htmlspecialchars($module['file_path']); ?>" 
                class="pdf-viewer"
                title="PDF Viewer"
                id="pdfViewer"
            ></iframe>
            <div class="page-controls">
                <button class="control-button tooltip" data-tooltip="Previous Page" onclick="previousPage()" id="prevPageBtn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="page-info">
                    Page <span class="page-number" id="currentPage">1</span> of <span class="total-pages" id="totalPages">...</span>
                </div>
                <button class="control-button tooltip" data-tooltip="Next Page" onclick="nextPage()" id="nextPageBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="pdf-controls">
                <button class="control-button tooltip" data-tooltip="Zoom In" onclick="zoomIn()">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="control-button tooltip" data-tooltip="Zoom Out" onclick="zoomOut()">
                    <i class="fas fa-search-minus"></i>
                </button>
                <button class="control-button tooltip" data-tooltip="Fullscreen" onclick="toggleFullscreen()">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <script>
        let currentZoom = 100;
        const zoomStep = 25;
        const pdfContainer = document.getElementById('pdfContainer');
        const pdfViewer = document.getElementById('pdfViewer');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const currentPageSpan = document.getElementById('currentPage');
        const totalPagesSpan = document.getElementById('totalPages');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        let currentPage = 1;
        let totalPages = 0;

        // Hide loading overlay when PDF is loaded
        pdfViewer.onload = function() {
            loadingOverlay.style.display = 'none';
            // Get total pages from PDF viewer
            try {
                const pdf = pdfViewer.contentWindow.PDFViewerApplication;
                if (pdf) {
                    totalPages = pdf.pagesCount;
                    totalPagesSpan.textContent = totalPages;
                    updatePageButtons();
                }
            } catch (e) {
                console.log('Could not get PDF viewer application');
            }
        };

        function updatePageButtons() {
            prevPageBtn.disabled = currentPage <= 1;
            nextPageBtn.disabled = currentPage >= totalPages;
        }

        function previousPage() {
            if (currentPage > 1) {
                currentPage--;
                currentPageSpan.textContent = currentPage;
                updatePageButtons();
                try {
                    const pdf = pdfViewer.contentWindow.PDFViewerApplication;
                    if (pdf) {
                        pdf.page = currentPage;
                    }
                } catch (e) {
                    console.log('Could not navigate to previous page');
                }
            }
        }

        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                currentPageSpan.textContent = currentPage;
                updatePageButtons();
                try {
                    const pdf = pdfViewer.contentWindow.PDFViewerApplication;
                    if (pdf) {
                        pdf.page = currentPage;
                    }
                } catch (e) {
                    console.log('Could not navigate to next page');
                }
            }
        }

        // Add keyboard shortcuts for page navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                previousPage();
            } else if (e.key === 'ArrowRight') {
                nextPage();
            }
        });

        function zoomIn() {
            currentZoom = Math.min(currentZoom + zoomStep, 200);
            updateZoom();
        }

        function zoomOut() {
            currentZoom = Math.max(currentZoom - zoomStep, 50);
            updateZoom();
        }

        function updateZoom() {
            pdfViewer.style.transform = `scale(${currentZoom / 100})`;
            pdfViewer.style.transformOrigin = 'top left';
        }

        function toggleFullscreen() {
            pdfContainer.classList.toggle('fullscreen');
            const button = document.querySelector('[data-tooltip="Fullscreen"]');
            const icon = button.querySelector('i');
            
            if (pdfContainer.classList.contains('fullscreen')) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
                button.setAttribute('data-tooltip', 'Exit Fullscreen');
            } else {
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
                button.setAttribute('data-tooltip', 'Fullscreen');
            }
        }

        // Handle escape key for fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && pdfContainer.classList.contains('fullscreen')) {
                toggleFullscreen();
            }
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case '+':
                        e.preventDefault();
                        zoomIn();
                        break;
                    case '-':
                        e.preventDefault();
                        zoomOut();
                        break;
                    case 'f':
                        e.preventDefault();
                        toggleFullscreen();
                        break;
                }
            }
        });

        // Sticky header shadow on scroll
        const stickyHeader = document.getElementById('stickyHeader');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 4) {
                stickyHeader.classList.add('sticky-shadow');
            } else {
                stickyHeader.classList.remove('sticky-shadow');
            }
        });
    </script>
</body>
</html> 