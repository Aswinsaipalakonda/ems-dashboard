<?php
/**
 * Simple PDF Generator Class
 * Employee Management System
 * 
 * A lightweight PDF generator without external dependencies
 * For complex PDFs, consider using TCPDF or FPDF library
 */

class PDFGenerator {
    private $content = '';
    private $title = '';
    private $author = 'EMS Dashboard';
    private $pageWidth = 210; // A4 width in mm
    private $pageHeight = 297; // A4 height in mm
    
    public function __construct($title = 'Report') {
        $this->title = $title;
    }
    
    /**
     * Set document title
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Add heading
     */
    public function addHeading($text, $level = 1) {
        $sizes = [1 => 24, 2 => 20, 3 => 16, 4 => 14];
        $size = $sizes[$level] ?? 14;
        $this->content .= "<h$level style='font-size: {$size}px; color: #333; margin: 20px 0 10px 0;'>$text</h$level>";
        return $this;
    }
    
    /**
     * Add paragraph
     */
    public function addParagraph($text) {
        $this->content .= "<p style='font-size: 12px; color: #666; line-height: 1.6; margin: 10px 0;'>$text</p>";
        return $this;
    }
    
    /**
     * Add table
     */
    public function addTable($headers, $rows, $options = []) {
        $headerBg = $options['headerBg'] ?? '#667eea';
        $headerColor = $options['headerColor'] ?? '#ffffff';
        $stripedBg = $options['stripedBg'] ?? '#f8f9fa';
        
        $html = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 11px;">';
        
        // Headers
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= "<th style='background: $headerBg; color: $headerColor; padding: 10px 8px; text-align: left; border: 1px solid #ddd;'>$header</th>";
        }
        $html .= '</tr></thead>';
        
        // Rows
        $html .= '<tbody>';
        foreach ($rows as $index => $row) {
            $bg = $index % 2 === 1 ? $stripedBg : '#ffffff';
            $html .= "<tr style='background: $bg;'>";
            foreach ($row as $cell) {
                $html .= "<td style='padding: 8px; border: 1px solid #ddd;'>$cell</td>";
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        $this->content .= $html;
        return $this;
    }
    
    /**
     * Add horizontal line
     */
    public function addLine() {
        $this->content .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">';
        return $this;
    }
    
    /**
     * Add statistics cards
     */
    public function addStats($stats) {
        $html = '<div style="display: flex; flex-wrap: wrap; margin: 15px 0;">';
        foreach ($stats as $stat) {
            $color = $stat['color'] ?? '#667eea';
            $html .= "
                <div style='flex: 1; min-width: 120px; margin: 5px; padding: 15px; background: linear-gradient(135deg, $color 0%, " . $this->adjustBrightness($color, -20) . " 100%); border-radius: 8px; text-align: center;'>
                    <div style='font-size: 24px; font-weight: bold; color: #fff;'>{$stat['value']}</div>
                    <div style='font-size: 11px; color: rgba(255,255,255,0.8);'>{$stat['label']}</div>
                </div>
            ";
        }
        $html .= '</div>';
        $this->content .= $html;
        return $this;
    }
    
    /**
     * Generate HTML for print/PDF
     */
    public function getHTML() {
        $date = date('F d, Y');
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($this->title) . '</title>
            <style>
                @page { size: A4; margin: 20mm; }
                @media print {
                    body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                    .no-print { display: none !important; }
                }
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px;
                    color: #333;
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #fff;
                    padding: 20px;
                    margin: -20px -20px 20px -20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .header h1 { margin: 0; font-size: 24px; }
                .header .date { font-size: 12px; opacity: 0.8; }
                .print-btn {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #667eea;
                    color: #fff;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .print-btn:hover { background: #5a6fd6; }
            </style>
        </head>
        <body>
            <button class="print-btn no-print" onclick="window.print()">
                Print / Save as PDF
            </button>
            
            <div class="header">
                <div>
                    <h1>' . htmlspecialchars($this->title) . '</h1>
                    <div class="date">Generated on ' . $date . '</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 18px; font-weight: bold;">' . APP_NAME . '</div>
                </div>
            </div>
            
            ' . $this->content . '
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #999; font-size: 10px;">
                Generated by ' . APP_NAME . ' &bull; ' . $date . '
            </div>
        </body>
        </html>';
    }
    
    /**
     * Output PDF (using browser print dialog)
     */
    public function output($filename = 'report.pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->getHTML();
        exit;
    }
    
    /**
     * Download as HTML file (can be converted to PDF)
     */
    public function download($filename = 'report.html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->getHTML();
        exit;
    }
    
    /**
     * Adjust color brightness
     */
    private function adjustBrightness($hex, $steps) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

/**
 * Generate Attendance Report PDF
 */
function generateAttendanceReportPDF($month, $year, $employeeId = null) {
    global $conn;
    
    $pdf = new PDFGenerator("Attendance Report - " . date('F Y', mktime(0, 0, 0, $month, 1, $year)));
    
    // Build query
    $where = "WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?";
    $params = [$month, $year];
    $types = "ii";
    
    if ($employeeId) {
        $where .= " AND a.employee_id = ?";
        $params[] = $employeeId;
        $types .= "i";
    }
    
    // Get statistics
    $stats = fetchOne(
        "SELECT 
            COUNT(DISTINCT a.employee_id) as total_employees,
            COUNT(*) as total_entries,
            SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected
         FROM attendance a $where",
        $types,
        $params
    );
    
    $pdf->addStats([
        ['label' => 'Total Employees', 'value' => $stats['total_employees'] ?? 0, 'color' => '#667eea'],
        ['label' => 'Total Entries', 'value' => $stats['total_entries'] ?? 0, 'color' => '#28a745'],
        ['label' => 'Approved', 'value' => $stats['approved'] ?? 0, 'color' => '#17a2b8'],
        ['label' => 'Pending', 'value' => $stats['pending'] ?? 0, 'color' => '#ffc107'],
        ['label' => 'Rejected', 'value' => $stats['rejected'] ?? 0, 'color' => '#dc3545']
    ]);
    
    $pdf->addLine();
    $pdf->addHeading('Attendance Details', 2);
    
    // Get attendance data
    $attendance = fetchAll(
        "SELECT a.*, e.name as employee_name, e.employee_id as emp_code
         FROM attendance a
         LEFT JOIN employees e ON a.employee_id = e.id
         $where
         ORDER BY a.date DESC, e.name",
        $types,
        $params
    );
    
    $headers = ['Date', 'Employee', 'Check In', 'Check Out', 'Hours', 'Status'];
    $rows = [];
    
    foreach ($attendance as $record) {
        $rows[] = [
            date('M d, Y', strtotime($record['date'])),
            $record['employee_name'] . ' (' . $record['emp_code'] . ')',
            $record['check_in_time'] ? formatTime($record['check_in_time'], 'h:i A') : '-',
            $record['check_out_time'] ? formatTime($record['check_out_time'], 'h:i A') : '-',
            $record['total_hours'] ?? '-',
            ucfirst($record['status'])
        ];
    }
    
    $pdf->addTable($headers, $rows);
    
    return $pdf;
}

/**
 * Generate Employee Report PDF
 */
function generateEmployeeReportPDF() {
    $pdf = new PDFGenerator("Employee Report - " . date('F d, Y'));
    
    // Get statistics
    $stats = fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            COUNT(DISTINCT domain_id) as domains
         FROM employees",
        "",
        []
    );
    
    $pdf->addStats([
        ['label' => 'Total Employees', 'value' => $stats['total'] ?? 0, 'color' => '#667eea'],
        ['label' => 'Active', 'value' => $stats['active'] ?? 0, 'color' => '#28a745'],
        ['label' => 'Inactive', 'value' => $stats['inactive'] ?? 0, 'color' => '#dc3545'],
        ['label' => 'Domains', 'value' => $stats['domains'] ?? 0, 'color' => '#17a2b8']
    ]);
    
    $pdf->addLine();
    $pdf->addHeading('Employee List', 2);
    
    $employees = fetchAll(
        "SELECT e.*, d.name as domain_name
         FROM employees e
         LEFT JOIN domains d ON e.domain_id = d.id
         ORDER BY e.name",
        "",
        []
    );
    
    $headers = ['ID', 'Name', 'Email', 'Domain', 'Designation', 'Status', 'Joined'];
    $rows = [];
    
    foreach ($employees as $emp) {
        $rows[] = [
            $emp['employee_id'],
            $emp['name'],
            $emp['email'],
            $emp['domain_name'] ?? 'N/A',
            $emp['designation'] ?? 'N/A',
            ucfirst($emp['status']),
            $emp['date_of_joining'] ? date('M d, Y', strtotime($emp['date_of_joining'])) : '-'
        ];
    }
    
    $pdf->addTable($headers, $rows);
    
    return $pdf;
}

/**
 * Generate Task Report PDF
 */
function generateTaskReportPDF() {
    $pdf = new PDFGenerator("Task Report - " . date('F d, Y'));
    
    $stats = fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'overdue' OR (deadline < CURDATE() AND status NOT IN ('completed', 'cancelled')) THEN 1 ELSE 0 END) as overdue
         FROM tasks",
        "",
        []
    );
    
    $pdf->addStats([
        ['label' => 'Total Tasks', 'value' => $stats['total'] ?? 0, 'color' => '#667eea'],
        ['label' => 'Completed', 'value' => $stats['completed'] ?? 0, 'color' => '#28a745'],
        ['label' => 'In Progress', 'value' => $stats['in_progress'] ?? 0, 'color' => '#17a2b8'],
        ['label' => 'Overdue', 'value' => $stats['overdue'] ?? 0, 'color' => '#dc3545']
    ]);
    
    $pdf->addLine();
    $pdf->addHeading('Task List', 2);
    
    $tasks = fetchAll(
        "SELECT t.*, e.name as employee_name
         FROM tasks t
         LEFT JOIN employees e ON t.assigned_to = e.id
         ORDER BY t.deadline DESC",
        "",
        []
    );
    
    $headers = ['Task', 'Assigned To', 'Priority', 'Deadline', 'Status'];
    $rows = [];
    
    foreach ($tasks as $task) {
        $rows[] = [
            $task['title'],
            $task['employee_name'] ?? 'Unassigned',
            ucfirst($task['priority']),
            $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : '-',
            ucfirst(str_replace('_', ' ', $task['status']))
        ];
    }
    
    $pdf->addTable($headers, $rows);
    
    return $pdf;
}
