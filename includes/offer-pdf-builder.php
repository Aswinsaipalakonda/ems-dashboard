<?php
/**
 * Official Clientura Offer Letter PDF Builder
 * Generates exact A4 single-page vector PDF documents on branded letterhead using FPDF
 */
require_once __DIR__ . '/fpdf/fpdf.php';

class OfferPDFBuilder extends FPDF {
    public function __construct() {
        parent::__construct('P', 'mm', 'A4');
        $this->SetAutoPageBreak(false); // Exactly 1 page
        $this->SetMargins(16, 12, 16);
    }
}

/**
 * Generate official Offer Letter PDF file
 * 
 * @param array $data Offer letter data parameters
 * @param string|null $outputPath Destination file path (if null, outputs to browser)
 * @param string $dest 'F' = save to file, 'D' = download to browser, 'I' = inline preview
 * @return string|bool File path or true on output
 */
function buildOfferLetterPDF(array $data, ?string $outputPath = null, string $dest = 'F') {
    $empType = strtolower($data['employment_type'] ?? 'internship');
    $candName = trim($data['candidate_name'] ?? 'Candidate');
    $designation = trim($data['designation'] ?? 'Software Development Engineer');
    $roleCode = trim($data['role_code'] ?? '');
    $durationText = trim($data['duration_text'] ?? '45 days');
    $startDate = trim($data['start_date'] ?? date('d-m-Y'));
    $endDate = trim($data['end_date'] ?? date('d-m-Y', strtotime('+45 days')));
    $issueDate = trim($data['issue_date'] ?? date('d-m-Y'));
    $location = trim($data['location'] ?? 'Hybrid');

    // Parse responsibilities & terms
    $resp = $data['responsibilities'] ?? [];
    if (is_string($resp)) {
        $resp = array_filter(array_map('trim', explode("\n", $resp)));
    }
    $terms = $data['terms'] ?? [];
    if (is_string($terms)) {
        $terms = array_filter(array_map('trim', explode("\n", $terms)));
    }

    $pdf = new OfferPDFBuilder();
    $pdf->AddPage();

    // 1. Draw Branded Letterhead Canvas Background (210 x 297 mm full bleed)
    $bgImg = __DIR__ . '/../assets/img/offer-letterhead-template.png';
    if (file_exists($bgImg)) {
        $pdf->Image($bgImg, 0, 0, 210, 297);
    }

    // 2. Heading: Title
    // FIX: Never output trailing dash if roleCode is empty
    $heading = ucfirst($empType) . ' Offer Letter';
    if (!empty($roleCode)) {
        $heading .= ' - ' . $roleCode;
    }

    $pdf->SetY(41);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(178, 7, $heading, 0, 1, 'C');

    $pdf->Ln(2);

    // 3. Meta: Date & Subject
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Cell(178, 4.8, 'Date: ' . $issueDate, 0, 1, 'L');
    $pdf->Cell(178, 4.8, 'Subject: Offer Letter for ' . $designation, 0, 1, 'L');
    $pdf->Ln(2);

    // 4. Salutation
    $pdf->Cell(178, 4.8, 'Dear ' . $candName . ',', 0, 1, 'L');
    $pdf->Ln(1);

    // 5. Intro Paragraph
    $pdf->SetFont('Arial', '', 9.2);
    $introText = "We are pleased to offer you the position of {$designation} at Clientura, for a duration of {$durationText}, starting from {$startDate} to {$endDate}. This {$empType} is part of our commitment to nurturing talented individuals and providing practical exposure to modern software engineering practices.";
    $pdf->MultiCell(178, 4.4, $introText, 0, 'L');
    $pdf->Ln(2);

    // 6. Responsibilities Intro
    $pdf->SetFont('Arial', 'B', 9.2);
    $pdf->MultiCell(178, 4.4, "During your {$empType}, you will work closely with our Engineering Team on real-world software development projects involving:", 0, 'L');
    $pdf->Ln(1);

    // Responsibilities bullets
    $pdf->SetFont('Arial', '', 8.8);
    foreach ($resp as $r) {
        $pdf->Cell(5, 4.0, chr(149), 0, 0, 'R');
        $pdf->Cell(2, 4.0, '', 0, 0);
        $pdf->Cell(171, 4.0, $r, 0, 1, 'L');
    }
    $pdf->Ln(2);

    // Professionalism paragraph
    $pdf->SetFont('Arial', '', 9.2);
    $pdf->MultiCell(178, 4.4, 'You are expected to maintain a high level of professionalism, meet project deadlines, actively collaborate with team members, and contribute to the successful delivery of software solutions.', 0, 'L');
    $pdf->Ln(2);

    // 7. Details Block
    $pdf->SetFont('Arial', 'B', 9.2);
    $pdf->Cell(178, 4.4, ucfirst($empType) . ' Details:', 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 8.8);
    $details = [
        'Position'   => $designation,
        'Location'   => $location,
        'Start Date' => $startDate,
        'End Date'   => $endDate,
    ];
    foreach ($details as $k => $v) {
        $pdf->SetFont('Arial', 'B', 8.8);
        $pdf->Cell(24, 4.0, $k . ':', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8.8);
        $pdf->Cell(154, 4.0, $v, 0, 1, 'L');
    }
    $pdf->Ln(2);

    // 8. Terms & Conditions
    $pdf->SetFont('Arial', 'B', 9.2);
    $pdf->Cell(178, 4.4, 'Terms & Conditions:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8.8);
    foreach ($terms as $t) {
        $pdf->Cell(5, 4.0, chr(149), 0, 0, 'R');
        $pdf->Cell(2, 4.0, '', 0, 0);
        $pdf->MultiCell(171, 4.0, $t, 0, 'L');
    }
    $pdf->Ln(2);

    // 9. Closing Text
    $pdf->SetFont('Arial', '', 9.2);
    $closingText = "To confirm your acceptance of this offer, please reply to this email / letter. Upon receiving your confirmation, we will proceed with the onboarding process and provide further details regarding your {$empType} responsibilities and project assignments.";
    $pdf->MultiCell(178, 4.3, $closingText, 0, 'L');
    $pdf->Ln(1.5);

    $pdf->MultiCell(178, 4.3, "We look forward to welcoming you to our team and supporting your professional growth as a {$designation}.", 0, 'L');

    // 10. Sign-off Footer Row (Pinned cleanly at the bottom)
    $pdf->SetY(260);
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Cell(90, 4.5, 'Warm regards,', 0, 0, 'L');
    $pdf->Cell(88, 4.5, '', 0, 1, 'R');

    $pdf->Cell(90, 4.5, 'Clientura', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9.0);
    $pdf->Cell(88, 4.5, 'Signature of candidate', 0, 1, 'R');

    if ($dest === 'F') {
        if ($outputPath) {
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $pdf->Output('F', $outputPath);
            return $outputPath;
        }
        return false;
    } else {
        $fileName = $outputPath ? basename($outputPath) : 'Offer_Letter.pdf';
        $pdf->Output($dest, $fileName);
        exit;
    }
}
