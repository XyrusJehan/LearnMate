<?php
// pdf_viewer.php
session_start();

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

// Get PDF file details from database (only user's own PDFs)
if (isset($_GET['id'])) {
    $pdfId = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT original_filename, storage_path FROM pdf_files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pdfId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $pdfFile = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pdfFile) {
        die("PDF file not found or access denied");
    }
} else {
    die("No PDF specified");
}

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
            background: rgba(100, 200, 255, 0.3);
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
            <a href="flashcard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
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

    <script>
        // PDF.js configuration
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
        
        // PDF Viewer variables
        const pageNum = document.getElementById('pageNum');
        const pageCount = document.getElementById('pageCount');
        const prevPage = document.getElementById('prevPage');
        const nextPage = document.getElementById('nextPage');
        const pdfViewer = document.getElementById('pdfViewer');
        
        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.0;
        let currentViewport = null;
        let currentPageDiv = null;
        let currentTextLayer = null;

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
            const pdfUrl = '<?php echo $pdfFile['storage_path']; ?>';
            
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
                pdfViewer.innerHTML = '<div class="loading" style="color: var(--danger);">Error loading PDF: ' + error.message + '</div>';
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
                    
                    // Enable text selection
                    enableTextSelection();
                });
                
                // Update page number
                pageNum.textContent = pageNumber;
                
            }).catch(function(error) {
                pdfViewer.innerHTML = '<div class="loading" style="color: var(--danger);">Error rendering page: ' + error.message + '</div>';
            });
        }

        // Enable text selection
        function enableTextSelection() {
            const textLayer = currentTextLayer;
            
            // Enable standard text selection
            const textSpans = textLayer.querySelectorAll('span');
            textSpans.forEach(span => {
                span.style.pointerEvents = 'auto';
                span.style.cursor = 'text';
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

        // Initialize
        window.onload = loadPDF;
    </script>
</body>
</html>