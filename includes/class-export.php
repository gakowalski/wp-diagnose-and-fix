<?php
class Export_Manager {
    public function export_to_pdf($data) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once('fpdf/fpdf.php');

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Raport diagnostyczny', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        
        foreach ($data as $section => $content) {
            $pdf->Cell(0, 10, $section, 0, 1, 'L');
            $pdf->MultiCell(0, 5, $content);
        }

        return $pdf->Output('D', 'network_diagnostic.pdf');
    }

    public function export_to_csv($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="network_diagnostic.csv"');
        
        $fp = fopen('php://output', 'w');
        foreach ($data as $section => $content) {
            fputcsv($fp, [$section, $content]);
        }
        fclose($fp);
    }
}
