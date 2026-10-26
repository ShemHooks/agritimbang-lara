<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExportService
{
    public function download(array $report, string $format): Response
    {
        $filename = 'agritimbang_'.$report['type'].'_'.now()->format('Ymd_His');
        if ($format === 'pdf') {
            $bytes = Pdf::loadView('reports.export', ['report' => $report])
                ->setPaper('a4', 'landscape')->output();
            return response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $sheet->setCellValue('A1', $report['title']);
        $sheet->setCellValue('A2', 'Municipality ID: '.$report['municipality_id']);
        $sheet->setCellValue('A3', 'Generated: '.$report['generated_at']);
        if ($report['note']) $sheet->setCellValue('A4', $report['note']);
        $headers = array_values($report['columns']);
        $keys = array_keys($report['columns']);
        foreach ($headers as $i => $label) $sheet->setCellValue([$i + 1, 6], $label);
        $sheet->getStyle('A6:'.$sheet->getHighestColumn().'6')->getFont()->setBold(true);
        foreach ($report['rows'] as $rowIndex => $row) {
            foreach ($keys as $colIndex => $key) {
                // Explicit string type prevents formulas in database-derived text from executing.
                $value = $row[$key] ?? '';
                $sheet->setCellValueExplicit([$colIndex + 1, $rowIndex + 7], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        $sheet->freezePane('A7');
        $sheet->setAutoFilter('A6:'.$sheet->getHighestColumn().max(6, count($report['rows']) + 6));
        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'w+b');
        try {
            $writer->save($stream);
            rewind($stream);
            $bytes = stream_get_contents($stream);
        } finally {
            fclose($stream);
            $spreadsheet->disconnectWorksheets();
        }
        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
