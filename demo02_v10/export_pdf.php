<?php
require_once('tcpdf/tcpdf.php');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learnmate');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

class FlashcardsPDF extends TCPDF {
    public $lastY = 0; // Track the last Y position
    public $startY = 0; // Track the starting Y position for center line
    
    public function __construct() {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->SetCreator('Flashcard App');
        $this->SetAuthor('Your Name');
        $this->SetTitle('Flashcards Export');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(15, 20, 15);
        $this->SetAutoPageBreak(true, 15);
        $this->SetFont('helvetica', '', 10);
    }
}

$pdf = new FlashcardsPDF();
$pdf->AddPage();

// Get flashcards
$folderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
$flashcards = getFlashcards($conn, $folderId);
$folderName = getFolderName($conn, $folderId);

// Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Flashcards - ' . $folderName, 0, 1, 'C');
$pdf->Ln(10);

// Set starting Y position for center line (after heading)
$pdf->startY = $pdf->GetY();

// Layout parameters
$page_width = 210 - 30; // A4 width minus margins
$column_width = $page_width / 2 - 5;
$center_line_x = 105; // Center of A4 page
$y = $pdf->GetY(); // Current Y position after heading

// Process flashcards in pairs
for ($i = 0; $i < count($flashcards); $i += 2) {
    // Left card
    if (isset($flashcards[$i])) {
        $pdf->SetXY(15, $y);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell($column_width, 6, $flashcards[$i]['term_text'], 0, 'L');
        $pdf->SetXY(15, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell($column_width, 6, '-' . $flashcards[$i]['definition_text'], 0, 'L');
        $left_height = $pdf->GetY() - $y;
    }
    
    // Right card
    if (isset($flashcards[$i+1])) {
        $pdf->SetXY($center_line_x + 5, $y);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell($column_width, 6, $flashcards[$i+1]['term_text'], 0, 'L');
        $pdf->SetXY($center_line_x + 5, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell($column_width, 6, '-' . $flashcards[$i+1]['definition_text'], 0, 'L');
        $right_height = $pdf->GetY() - $y;
    }
    
    // Determine row height
    $row_height = max($left_height ?? 0, $right_height ?? 0) + 5;
    
    // New page if needed
    if ($y + $row_height > 270) {
        // Draw center line up to current position
        $pdf->Line($center_line_x, $pdf->startY, $center_line_x, $y);
        
        $pdf->AddPage();
        $y = 30;
        $pdf->startY = $y; // Reset startY for new page
        
        // Redraw center line on new page
        $pdf->Line($center_line_x, $pdf->startY, $center_line_x, $y);
    } else {
        // Draw horizontal divider if not last row
        if ($i + 2 < count($flashcards)) {
            $pdf->Line(15, $y + $row_height - 3, $page_width + 15, $y + $row_height - 3);
        }
        $y += $row_height;
    }
    
    // Update last Y position
    $pdf->lastY = $y;
}

// Draw final center line (from startY to lastY)
$pdf->Line($center_line_x, $pdf->startY, $center_line_x, $pdf->lastY);

$pdf->Output('flashcards_' . $folderName . '.pdf', 'D');

// Helper functions remain the same
function getFolderName($conn, $folderId) {
    $stmt = $conn->prepare("SELECT name FROM folders WHERE id = ?");
    $stmt->bind_param("i", $folderId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['name'] : 'All Flashcards';
}

function getFlashcards($conn, $folderId) {
    $query = "SELECT t.term_text, d.definition_text 
              FROM flashcards f
              JOIN terms t ON f.term_id = t.id
              JOIN definitions d ON f.definition_id = d.id
              WHERE " . ($folderId ? "f.folder_id = ?" : "f.folder_id IS NULL") . "
              ORDER BY t.term_text";
    $stmt = $conn->prepare($query);
    if ($folderId) $stmt->bind_param("i", $folderId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}