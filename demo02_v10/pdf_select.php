<?php
// pdf_select.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'learnmate';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get PDF file details from database
if (isset($_GET['id'])) {
    $pdfId = (int)$_GET['id'];
    
    // Get PDF file details (without user restriction for now)
    $stmt = $conn->prepare("SELECT original_filename, storage_path FROM pdf_files WHERE id = ?");
    $stmt->bind_param("i", $pdfId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pdfFile = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pdfFile) {
        die("PDF file not found");
    }
} else {
    die("No PDF specified");
}

// Get all folders from database (only user's own folders)
$folders = [];
$folderResult = $conn->prepare("SELECT id, name FROM folders WHERE user_id = ? ORDER BY name");
$folderResult->bind_param("i", $_SESSION['user_id']);
$folderResult->execute();
$result = $folderResult->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $folders[] = $row;
    }
}
$folderResult->close();

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>View PDF: <?php echo htmlspecialchars($pdfFile['original_filename']); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #3f37c9;
            --secondary: #3a0ca3;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --white: #ffffff;
            --term-color: rgba(100, 200, 100, 0.3);
            --definition-color: rgba(200, 100, 100, 0.3);
            --term-selection: rgba(100, 200, 100, 0.5);
            --definition-selection: rgba(200, 100, 100, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            padding: 15px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: var(--primary);
            font-size: 1.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pdf-nav {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center;
            background: rgba(67, 97, 238, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            gap: 10px;
        }

        .pdf-nav button {
            margin: 0;
            padding: 8px 15px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .pdf-nav button:disabled {
            background-color: var(--gray);
            cursor: not-allowed;
        }

        .pdf-nav button:hover:not(:disabled) {
            background-color: var(--primary-light);
        }

        .page-info {
            min-width: 80px;
            text-align: center;
            font-weight: 600;
        }

        .pdf-viewer-container {
            width: 100%;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: auto;
            margin-top: 10px;
            position: relative;
            background: #f0f2f5;
            min-height: 300px;
            display: flex;
            justify-content: center;
        }

        #pdfViewer {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .pageContainer {
            position: relative;
            margin: 0 auto 20px auto;
            overflow: visible;
            border: 1px solid #ddd;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background: white;
            max-width: 100%;
        }

        .page {
            position: relative;
            direction: ltr;
        }

        .canvasWrapper {
            position: relative;
            overflow: hidden;
        }

        canvas {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .textLayer {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            line-height: 1.0;
        }

        .textLayer span {
            color: transparent;
            position: absolute;
            white-space: pre;
            cursor: text;
            transform-origin: 0% 0%;
            pointer-events: auto;
            user-select: text;
            -webkit-user-select: text;
        }

        .textLayer::selection {
            background: var(--term-selection);
        }

        .definition-selection::selection {
            background: var(--definition-selection) !important;
        }

        .back-btn {
            background-color: var(--primary);
            color: var(--white);
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            justify-content: center;
            margin-top: 10px;
        }

        .back-btn:hover {
            background-color: var(--primary-light);
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            font-size: 1.2rem;
            color: var(--gray);
        }

        /* Flashcard Input Boxes */
        .flashcard-inputs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .input-box {
            flex: 1;
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .input-box label {
            font-weight: 600;
            color: var(--primary);
        }

        .input-box textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            min-height: 80px;
            resize: vertical;
            font-family: inherit;
        }

        /* Highlight Controls */
        .highlight-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .highlight-btn {
            background-color: var(--white);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .highlight-btn.active {
            background-color: var(--primary);
            color: var(--white);
        }

        .highlight-btn:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }

        /* Folder Selection Styles */
        .folder-selector {
            position: relative;
            flex-grow: 1;
        }

        .folder-btn {
            background-color: var(--white);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 10px 15px;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .folder-btn:hover {
            border-color: var(--primary);
        }

        .folder-btn i {
            transition: transform 0.3s ease;
        }

        .folder-btn.active i {
            transform: rotate(180deg);
        }

        .folder-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: var(--white);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-top: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            animation: fadeIn 0.2s ease;
        }

        .folder-dropdown.show {
            display: block;
        }

        .folder-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .folder-item:hover {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .folder-item i {
            margin-right: 10px;
            color: var(--primary);
        }

        .folder-item.selected {
            background-color: rgba(67, 97, 238, 0.2);
            font-weight: 600;
        }

        .no-folders {
            padding: 15px;
            text-align: center;
            color: var(--gray);
        }

        .create-folder-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 0 0 8px 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }

        .create-folder-btn:hover {
            background-color: var(--primary-light);
        }

        /* Create Folder Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--white);
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body input {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-footer button {
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
        }

        .cancel-btn {
            background-color: var(--light);
            color: var(--dark);
        }

        .save-btn {
            background-color: var(--primary);
            color: var(--white);
        }

        /* Highlight styles */
        .highlight {
            position: absolute;
            z-index: 10;
            cursor: pointer;
            border-radius: 2px;
        }

        .highlight-term {
            background-color: var(--term-color);
            border: 1px solid rgba(100, 200, 100, 0.5);
        }

        .highlight-definition {
            background-color: var(--definition-color);
            border: 1px solid rgba(200, 100, 100, 0.5);
        }

        /* Selection styles */
        .selection-term {
            background-color: var(--term-selection);
            position: absolute;
            z-index: 5;
            pointer-events: none;
        }

        .selection-definition {
            background-color: var(--definition-selection);
            position: absolute;
            z-index: 5;
            pointer-events: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (min-width: 768px) {
            .container {
                max-width: 900px;
                padding: 20px;
            }
            
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .back-btn {
                width: auto;
                margin-top: 0;
            }
            
            .pdf-nav button {
                flex: 0 1 auto;
            }

            .folder-selector {
                width: 250px;
            }

            .highlight-controls {
                flex-wrap: nowrap;
            }
        }

        @media (max-width: 480px) {
            .pdf-nav {
                flex-wrap: wrap;
            }
            
            .pdf-nav button {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            
            .page-info {
                order: -1;
                flex: 1 0 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($pdfFile['original_filename']); ?></h1>
            </div>
            <a href="teacher_flashcard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        
        <!-- Flashcard Input Boxes -->
        <div class="flashcard-inputs">
            <div class="input-box">
                <label for="termText">Term</label>
                <textarea id="termText" placeholder="Selected term will appear here or you can type manually"></textarea>
            </div>
            <div class="input-box">
                <label for="definitionText">Definition</label>
                <textarea id="definitionText" placeholder="Selected definition will appear here or you can type manually"></textarea>
            </div>
        </div>
        
        <!-- Highlight Controls -->
        <div class="highlight-controls">
            <button class="highlight-btn" id="termBtn">
                <i class="fas fa-font"></i> Select Term
            </button>
            <button class="highlight-btn" id="definitionBtn">
                <i class="fas fa-font"></i> Select Definition
            </button>
            <div class="folder-selector">
                <button class="folder-btn" id="folderBtn">
                    <span id="selectedFolderText">Select folder</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="folder-dropdown" id="folderDropdown">
                    <?php if (count($folders) > 0): ?>
                        <?php foreach ($folders as $folder): ?>
                            <div class="folder-item" data-folder-id="<?php echo $folder['id']; ?>">
                                <i class="fas fa-folder"></i>
                                <?php echo htmlspecialchars($folder['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-folders">No folders found</div>
                    <?php endif; ?>
                    <button class="create-folder-btn" id="createFolderBtn">
                        <i class="fas fa-plus"></i> Create New Folder
                    </button>
                </div>
            </div>
            <button class="highlight-btn" id="saveFlashcardBtn" disabled>
                <i class="fas fa-save"></i> Save Flashcard
            </button>
        </div>
        
        <div class="pdf-nav">
            <button id="prevPage" disabled><i class="fas fa-arrow-left"></i> Previous</button>
            <div class="page-info">Page: <span id="pageNum">1</span> / <span id="pageCount">0</span></div>
            <button id="nextPage" disabled>Next <i class="fas fa-arrow-right"></i></button>
        </div>
        
        <div class="pdf-viewer-container">
            <div id="pdfViewer">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading PDF...
                </div>
            </div>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal" id="folderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Folder</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="folderName" placeholder="Enter folder name">
            </div>
            <div class="modal-footer">
                <button class="cancel-btn" id="cancelFolder">Cancel</button>
                <button class="save-btn" id="saveFolder">Create</button>
            </div>
        </div>
    </div>

    <script>
        // PDF.js configuration
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
        
        // PDF Viewer variables
        const pageNum = document.getElementById('pageNum');
        const pageCount = document.getElementById('pageCount');
        const prevPage = document.getElementById('prevPage');
        const nextPage = document.getElementById('nextPage');
        const pdfViewer = document.getElementById('pdfViewer');
        
        // Flashcard input boxes
        const termText = document.getElementById('termText');
        const definitionText = document.getElementById('definitionText');
        
        // Highlight controls
        const termBtn = document.getElementById('termBtn');
        const definitionBtn = document.getElementById('definitionBtn');
        const saveFlashcardBtn = document.getElementById('saveFlashcardBtn');
        
        // Folder selection variables
        const folderBtn = document.getElementById('folderBtn');
        const folderDropdown = document.getElementById('folderDropdown');
        const selectedFolderText = document.getElementById('selectedFolderText');
        const folderItems = document.querySelectorAll('.folder-item');
        const createFolderBtn = document.getElementById('createFolderBtn');
        
        // Modal variables
        const folderModal = document.getElementById('folderModal');
        const closeModal = document.getElementById('closeModal');
        const cancelFolder = document.getElementById('cancelFolder');
        const saveFolder = document.getElementById('saveFolder');
        const folderName = document.getElementById('folderName');
        
        // Highlight variables
        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.0;
        let currentViewport = null;
        let currentPageDiv = null;
        let currentTextLayer = null;
        let selectedFolderId = null;
        let highlightType = null;
        let currentSelection = null;
        let currentSelectionRect = null;
        let currentSelectionElement = null;
        let highlights = [];

        // Initialize highlight controls
        termBtn.addEventListener('click', function() {
            highlightType = 'term';
            termBtn.classList.add('active');
            definitionBtn.classList.remove('active');
            updateSelectionClass();
            updateSaveButtonState();
        });

        definitionBtn.addEventListener('click', function() {
            highlightType = 'definition';
            definitionBtn.classList.add('active');
            termBtn.classList.remove('active');
            updateSelectionClass();
            updateSaveButtonState();
        });

        // Update selection class based on current highlight type
        function updateSelectionClass() {
            if (currentSelectionElement) {
                currentSelectionElement.className = highlightType === 'term' ? 
                    'selection-term' : 'selection-definition';
            }
        }

        // Toggle folder dropdown
        folderBtn.addEventListener('click', function() {
            folderDropdown.classList.toggle('show');
            folderBtn.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!folderBtn.contains(e.target) && !folderDropdown.contains(e.target)) {
                folderDropdown.classList.remove('show');
                folderBtn.classList.remove('active');
            }
        });

        // Select folder
        folderItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove selected class from all items
                folderItems.forEach(i => i.classList.remove('selected'));
                
                // Add selected class to clicked item
                this.classList.add('selected');
                
                // Update selected folder
                selectedFolderId = this.getAttribute('data-folder-id');
                selectedFolderText.textContent = this.textContent.trim();
                
                // Close dropdown
                folderDropdown.classList.remove('show');
                folderBtn.classList.remove('active');
                
                updateSaveButtonState();
            });
        });

        // Update save button state based on selections
        function updateSaveButtonState() {
            const hasTerm = termText.value.trim() !== '';
            const hasDefinition = definitionText.value.trim() !== '';
            saveFlashcardBtn.disabled = !(selectedFolderId && hasTerm && hasDefinition);
        }

        // Open create folder modal
        createFolderBtn.addEventListener('click', function() {
            folderModal.style.display = 'flex';
            folderName.focus();
        });

        // Close modal
        function closeFolderModal() {
            folderModal.style.display = 'none';
            folderName.value = '';
        }

        closeModal.addEventListener('click', closeFolderModal);
        cancelFolder.addEventListener('click', closeFolderModal);

        // Save new folder
        saveFolder.addEventListener('click', function() {
            const name = folderName.value.trim();
            if (name) {
                // AJAX call to create folder
                $.ajax({
                    url: 'create_folder.php',
                    method: 'POST',
                    data: { name: name },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Add new folder to dropdown
                            const newFolderItem = document.createElement('div');
                            newFolderItem.className = 'folder-item selected';
                            newFolderItem.setAttribute('data-folder-id', response.id);
                            newFolderItem.innerHTML = `<i class="fas fa-folder"></i> ${name}`;
                            
                            // Insert before the create folder button
                            folderDropdown.insertBefore(newFolderItem, createFolderBtn);
                            
                            // Select the new folder
                            selectedFolderId = response.id;
                            selectedFolderText.textContent = name;
                            
                            // Close modal and reset
                            closeFolderModal();
                            
                            // Remove "no folders" message if it exists
                            const noFolders = document.querySelector('.no-folders');
                            if (noFolders) {
                                noFolders.remove();
                            }
                            
                            // Add click event to new folder
                            newFolderItem.addEventListener('click', function() {
                                folderItems.forEach(i => i.classList.remove('selected'));
                                this.classList.add('selected');
                                selectedFolderId = this.getAttribute('data-folder-id');
                                selectedFolderText.textContent = this.textContent.trim();
                                folderDropdown.classList.remove('show');
                                folderBtn.classList.remove('active');
                                updateSaveButtonState();
                            });
                        } else {
                            alert('Error creating folder: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        alert('Error creating folder. Please try again. Error: ' + error);
                    }
                });
            }
        });

        // Save flashcard
        saveFlashcardBtn.addEventListener('click', function() {
            if (!selectedFolderId) {
                alert('Please select a folder first');
                return;
            }

            const term = termText.value.trim();
            const definition = definitionText.value.trim();
            
            if (!term || !definition) {
                alert('Please enter both term and definition');
                return;
            }

            // AJAX call to save flashcard
            $.ajax({
                url: 'save_flashcard.php',
                method: 'POST',
                data: {
                    pdf_file_id: <?php echo $_GET['id']; ?>,
                    page_number: currentPage,
                    term: term,
                    definition: definition,
                    folder_id: selectedFolderId,
                    position_data: currentSelectionRect ? JSON.stringify({
                        rect: currentSelectionRect,
                        viewport: {
                            width: currentViewport.width,
                            height: currentViewport.height,
                            scale: currentViewport.scale
                        }
                    }) : null
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Clear inputs
                        termText.value = '';
                        definitionText.value = '';
                        
                        // Clear current selection if it exists
                        clearCurrentSelection();
                        
                        // Update button state
                        updateSaveButtonState();
                        
                        // Show success message
                        alert('Flashcard saved successfully!');
                        
                        // Reload highlights to show the new ones
                        loadHighlightsForPage(currentPage);
                    } else {
                        alert('Error saving flashcard: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    alert('Error saving flashcard. Please try again. Error: ' + error);
                }
            });
        });

        // Clear current selection
        function clearCurrentSelection() {
            if (currentSelectionElement && currentSelectionElement.parentNode) {
                currentSelectionElement.parentNode.removeChild(currentSelectionElement);
            }
            currentSelection = null;
            currentSelectionRect = null;
            currentSelectionElement = null;
            
            // Clear any text selection
            if (window.getSelection) {
                window.getSelection().removeAllRanges();
            }
        }

        // Calculate initial scale based on device width
        function calculateScale() {
            const screenWidth = window.innerWidth;
            if (screenWidth < 480) {
                return 0.8;
            } else if (screenWidth < 768) {
                return 1.0;
            } else if (screenWidth < 1024) {
                return 1.2;
            } else {
                return 1.5;
            }
        }

        // Load PDF file
        function loadPDF() {
            const pdfUrl = '../demo02_v10/uploads/<?php echo basename($pdfFile['storage_path']); ?>';
            
            // Show loading message
            pdfViewer.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading PDF...</div>';
            
            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                pageCount.textContent = pdfDoc.numPages;
                
                // Enable/disable buttons
                updatePager();
                
                // Calculate initial scale
                scale = calculateScale();
                
                // Render first page
                renderPage(currentPage);
                
            }).catch(function(error) {
                pdfViewer.innerHTML = '<div class="loading" style="color: #dc3545;">Error loading PDF: ' + error.message + '</div>';
                console.error('PDF loading error:', error);
            });
        }

        // Update pager buttons
        function updatePager() {
            prevPage.disabled = currentPage <= 1;
            nextPage.disabled = currentPage >= pdfDoc.numPages;
        }

        // Render specific page
        function renderPage(pageNumber) {
            currentPage = pageNumber;
            updatePager();
            
            pdfDoc.getPage(pageNumber).then(function(page) {
                // Calculate viewport with current scale
                currentViewport = page.getViewport({ scale: scale });
                
                // Create canvas element
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = currentViewport.height;
                canvas.width = currentViewport.width;
                
                // Create text layer div
                currentTextLayer = document.createElement('div');
                currentTextLayer.className = 'textLayer';
                currentTextLayer.style.width = `${currentViewport.width}px`;
                currentTextLayer.style.height = `${currentViewport.height}px`;
                
                // Create wrapper divs
                currentPageDiv = document.createElement('div');
                currentPageDiv.className = 'page';
                currentPageDiv.style.width = `${currentViewport.width}px`;
                currentPageDiv.style.height = `${currentViewport.height}px`;
                
                const pageContainer = document.createElement('div');
                pageContainer.className = 'pageContainer';
                pageContainer.dataset.pageNumber = pageNumber;
                
                const canvasWrapper = document.createElement('div');
                canvasWrapper.className = 'canvasWrapper';
                
                // Build the DOM structure
                canvasWrapper.appendChild(canvas);
                currentPageDiv.appendChild(canvasWrapper);
                currentPageDiv.appendChild(currentTextLayer);
                pageContainer.appendChild(currentPageDiv);
                
                // Clear previous content and add new page
                pdfViewer.innerHTML = '';
                pdfViewer.appendChild(pageContainer);
                
                // Render PDF page
                page.render({
                    canvasContext: context,
                    viewport: currentViewport
                });
                
                // Render text layer
                page.getTextContent().then(function(textContent) {
                    pdfjsLib.renderTextLayer({
                        textContent: textContent,
                        container: currentTextLayer,
                        viewport: currentViewport,
                        textDivs: []
                    });
                    
                    // Enable text selection and highlighting
                    setupTextSelection();
                    
                    // Load existing highlights for this page
                    loadHighlightsForPage(pageNumber);
                });
                
                // Update page number
                pageNum.textContent = pageNumber;
                
            }).catch(function(error) {
                pdfViewer.innerHTML = '<div class="loading" style="color: #dc3545;">Error rendering page: ' + error.message + '</div>';
                console.error('Page rendering error:', error);
            });
        }

        // Setup text selection for highlighting
        function setupTextSelection() {
            // Clear previous selection
            clearCurrentSelection();
            
            // Remove any existing selection listeners
            currentTextLayer.removeEventListener('mouseup', handleTextSelection);
            
            // Add new selection listener
            currentTextLayer.addEventListener('mouseup', handleTextSelection);
        }

        // Handle text selection
        function handleTextSelection() {
            const selection = window.getSelection();
            if (!selection || selection.isCollapsed) {
                if (currentSelectionElement) {
                    currentSelectionElement.parentNode.removeChild(currentSelectionElement);
                    currentSelectionElement = null;
                }
                currentSelection = null;
                currentSelectionRect = null;
                updateSaveButtonState();
                return;
            }
            
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            const pageRect = currentPageDiv.getBoundingClientRect();
            
            // Calculate position relative to the page
            currentSelectionRect = {
                left: rect.left - pageRect.left,
                top: rect.top - pageRect.top,
                width: rect.width,
                height: rect.height
            };
            
            // Remove previous selection element if it exists
            if (currentSelectionElement && currentSelectionElement.parentNode) {
                currentSelectionElement.parentNode.removeChild(currentSelectionElement);
            }
            
            // Create new selection element
            currentSelectionElement = document.createElement('div');
            currentSelectionElement.className = highlightType === 'term' ? 
                'selection-term' : 'selection-definition';
            currentSelectionElement.style.left = `${currentSelectionRect.left}px`;
            currentSelectionElement.style.top = `${currentSelectionRect.top}px`;
            currentSelectionElement.style.width = `${currentSelectionRect.width}px`;
            currentSelectionElement.style.height = `${currentSelectionRect.height}px`;
            
            // Add to page
            currentPageDiv.appendChild(currentSelectionElement);
            
            currentSelection = selection;
            
            // Copy selected text to appropriate textarea
            const selectedText = selection.toString().trim();
            if (highlightType === 'term') {
                termText.value = selectedText;
            } else {
                definitionText.value = selectedText;
            }
            
            updateSaveButtonState();
            
            // Change text selection color based on highlight type
            if (highlightType === 'definition') {
                currentTextLayer.classList.add('definition-selection');
            } else {
                currentTextLayer.classList.remove('definition-selection');
            }
        }

        // Load highlights for a specific page
        function loadHighlightsForPage(pageNumber) {
            // Clear existing highlights
            highlights.forEach(highlight => {
                if (highlight.element.parentNode) {
                    highlight.element.parentNode.removeChild(highlight.element);
                }
            });
            highlights = [];
            
            // AJAX call to get highlights for this page
            $.ajax({
                url: 'get_highlights.php',
                method: 'GET',
                data: {
                    pdf_file_id: <?php echo $_GET['id']; ?>,
                    page_number: pageNumber
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.highlights) {
                        response.highlights.forEach(highlight => {
                            try {
                                const positionData = JSON.parse(highlight.position_data);
                                const rect = positionData.rect;
                                
                                // Scale the rect based on current viewport
                                const scaleX = currentViewport.width / positionData.viewport.width;
                                const scaleY = currentViewport.height / positionData.viewport.height;
                                
                                const scaledRect = {
                                    left: rect.left * scaleX,
                                    top: rect.top * scaleY,
                                    width: rect.width * scaleX,
                                    height: rect.height * scaleY
                                };
                                
                                addHighlightToPage(scaledRect, highlight.content_type, highlight.id);
                            } catch (e) {
                                console.error('Error loading highlight:', e);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading highlights:', xhr.responseText);
                }
            });
        }

        // Add highlight to page
        function addHighlightToPage(rect, type, highlightId) {
            const highlightDiv = document.createElement('div');
            highlightDiv.className = `highlight highlight-${type}`;
            highlightDiv.dataset.highlightId = highlightId;
            
            // Position the highlight
            highlightDiv.style.left = `${rect.left}px`;
            highlightDiv.style.top = `${rect.top}px`;
            highlightDiv.style.width = `${rect.width}px`;
            highlightDiv.style.height = `${rect.height}px`;
            
            // Add to the page
            currentPageDiv.appendChild(highlightDiv);
            
            // Store in highlights array
            highlights.push({
                id: highlightId,
                element: highlightDiv,
                rect: rect,
                type: type
            });
        }

        // Button event handlers
        prevPage.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                renderPage(currentPage);
            }
        });

        nextPage.addEventListener('click', function() {
            if (currentPage < pdfDoc.numPages) {
                currentPage++;
                renderPage(currentPage);
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (pdfDoc) {
                const newScale = calculateScale();
                if (Math.abs(newScale - scale) > 0.1) { // Only re-render if significant scale change
                    scale = newScale;
                    renderPage(currentPage);
                }
            }
        });

        // Initialize with term highlighting selected by default
        window.onload = function() {
            highlightType = 'term';
            termBtn.classList.add('active');
            loadPDF();
            
            // Update save button when text changes
            termText.addEventListener('input', updateSaveButtonState);
            definitionText.addEventListener('input', updateSaveButtonState);
        };
    </script>
</body>
</html>