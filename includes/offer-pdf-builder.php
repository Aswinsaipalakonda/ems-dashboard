<?php
/**
 * Official Clientura Offer Letter PDF Builder
 * Generates exact A4 single-page vector PDF documents on branded letterhead matching reference offer.pdf
 */
require_once __DIR__ . '/fpdf/fpdf.php';

class OfferPDFBuilder extends FPDF {
    public function __construct() {
        parent::__construct('P', 'mm', 'A4');
        $this->SetAutoPageBreak(false); // Exactly 1 page
        $this->SetMargins(12, 10, 12);
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

    // 1. Draw Branded Letterhead Background (210 x 297 mm full bleed)
    $bgImg = __DIR__ . '/../assets/img/offer-letterhead-template.png';
    if (file_exists($bgImg)) {
        $pdf->Image($bgImg, 0, 0, 210, 297);
    }

    // 2. Heading: Title
    // Exactly matches reference: Centered at Y=46mm, 16pt Bold
    $heading = ucfirst($empType) . ' Offer Letter';
    if (!empty($roleCode)) {
        $heading .= ' - ' . $roleCode;
    }

    $pdf->SetY(46);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(186, 7, $heading, 0, 1, 'C');

    // 3. Meta: Date & Subject (Y ~ 63mm)
    $pdf->SetY(63);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(4.8, 'Date: ');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(4.8, $issueDate);
    $pdf->Ln(5.0);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(4.8, 'Subject: ');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(4.8, 'Offer Letter for ' . $designation);
    $pdf->Ln(6.5);

    // 4. Salutation
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(186, 4.8, 'Dear ' . $candName . ',', 0, 1, 'L');
    $pdf->Ln(1.8);

    // 5. Intro Paragraph
    $pdf->SetFont('Arial', '', 9.6);
    $introText = "We are pleased to offer you the position of {$designation} at Clientura, for a duration of {$durationText}, starting from {$startDate} to {$endDate}. This {$empType} is part of our commitment to nurturing talented individuals and providing practical exposure to modern software engineering practices.";
    $pdf->MultiCell(186, 4.8, $introText, 0, 'L');
    $pdf->Ln(2.6);

    // 6. Responsibilities Intro
    $pdf->SetFont('Arial', 'B', 9.6);
    $pdf->MultiCell(186, 4.8, "During your {$empType}, you will work closely with our Engineering Team on real-world software development projects involving:", 0, 'L');
    $pdf->Ln(1.5);

    // Responsibilities bullets
    $pdf->SetFont('Arial', '', 9.4);
    foreach ($resp as $r) {
        $pdf->SetX(16);
        $pdf->Cell(4, 4.4, chr(149), 0, 0, 'L');
        $pdf->MultiCell(166, 4.4, $r, 0, 'L');
    }
    $pdf->Ln(2.5);

    // Professionalism paragraph
    $pdf->SetFont('Arial', '', 9.6);
    $pdf->MultiCell(186, 4.8, 'You are expected to maintain a high level of professionalism, meet project deadlines, actively collaborate with team members, and contribute to the successful delivery of software solutions.', 0, 'L');
    $pdf->Ln(2.8);

    // 7. Details Block (Inline label + value with exact natural spacing)
    $pdf->SetFont('Arial', 'B', 9.8);
    $pdf->Cell(186, 4.8, ucfirst($empType) . ' Details:', 0, 1, 'L');
    
    $details = [
        'Position'   => $designation,
        'Location'   => $location,
        'Start Date' => $startDate,
        'End Date'   => $endDate,
    ];
    foreach ($details as $k => $v) {
        $pdf->SetX(12);
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Write(4.5, $k . ': ');
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->Write(4.5, $v);
        $pdf->Ln(4.5);
    }
    $pdf->Ln(2.8);

    // 8. Terms & Conditions
    $pdf->SetFont('Arial', 'B', 9.8);
    $pdf->Cell(186, 4.8, 'Terms & Conditions:', 0, 1, 'L');
    foreach ($terms as $t) {
        $pdf->SetX(16);
        $pdf->SetFont('Arial', '', 9.4);
        $pdf->Cell(4, 4.4, chr(149), 0, 0, 'L');
        $pdf->MultiCell(166, 4.4, $t, 0, 'L');
    }
    $pdf->Ln(2.6);

    // 9. Closing Text
    $pdf->SetFont('Arial', '', 9.6);
    $closingText = "To confirm your acceptance of this offer, please reply to this email. Upon receiving your confirmation, we will proceed with the onboarding process and provide further details regarding your {$empType} responsibilities and project assignments.";
    $pdf->MultiCell(186, 4.8, $closingText, 0, 'L');
    $pdf->Ln(2.0);

    $pdf->MultiCell(186, 4.8, "We look forward to welcoming you to our team and supporting your professional growth as a {$designation}.", 0, 'L');

    // 10. Sign-off Footer Row (Pinned at Y=254mm matching reference offer.pdf)
    $pdf->SetY(254);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(93, 4.8, 'Warm regards,', 0, 0, 'L');
    $pdf->Cell(93, 4.8, '', 0, 1, 'R');

    $pdf->Cell(93, 4.8, 'Clientura', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(93, 4.8, 'Signature of candidate', 0, 1, 'R');

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
