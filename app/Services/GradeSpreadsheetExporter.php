<?php

namespace App\Services;

use App\Models\PresentationCategory;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel (.xlsx) export of the Generated Grades table (user-directed
 * 2026-09-13) — the exact rows GradeReportService::generate() returns for the
 * page, including its filters, so the file always matches what the admin is
 * looking at. $filterLabel is printed above the table as a record of which
 * filters produced it.
 */
class GradeSpreadsheetExporter
{
    private const HEADERS = ['Name', 'Section', 'Group', 'Individual Grade Avg.', 'Group Grade', 'Total', 'Outcome', 'Attempt'];

    /**
     * $paymentTypes (category_payment_types, empty when the category doesn't
     * require payment) — each becomes its own trailing column, labelled with
     * the type's own name rather than a generic "Payment" header, e.g.
     * "Defense Fee" / "Documentation Fee".
     */
    public function build(PresentationCategory $category, Collection $rows, ?string $filterLabel = null, ?Collection $paymentTypes = null): Spreadsheet
    {
        $paymentTypes ??= collect();
        $headers = array_merge(self::HEADERS, $paymentTypes->pluck('name')->all());

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setTitle("{$category->name} — Generated Grades");

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Grades');

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->setCellValue('A1', $category->name);
        $sheet->setCellValue('A2', implode(' · ', array_filter([
            $category->academicYear->name ?? null,
            $category->semester->name ?? null,
            $category->college->name ?? null,
        ])));
        $sheet->setCellValue('A3', trim(($filterLabel ? $filterLabel . '   ·   ' : '') . 'Generated ' . now()->format('M j, Y g:i A')));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('6B6560');

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C1712E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        $row = $headerRow;
        foreach ($rows as $data) {
            $row++;
            $paymentCells = $paymentTypes->map(fn ($type) => ($data['payment'][$type->id] ?? false) ? 'Paid' : 'Unpaid')->all();

            $sheet->fromArray(array_merge([
                $data['name'],
                $data['section'] ?? '',
                $data['group_reference'],
                $data['individual_avg'],
                $data['group_grade'],
                $data['total'],
                $data['outcome'] ?? '',
                $data['attempt_number'],
            ], $paymentCells), null, "A{$row}", true);
        }

        if ($row > $headerRow) {
            $firstData = $headerRow + 1;
            $sheet->getStyle("D{$firstData}:F{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            $sheet->getStyle("D{$firstData}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$firstData}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$row}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E8E1D9');
        }

        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}" . max($row, $headerRow));
        $sheet->freezePane('A' . ($headerRow + 1));

        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function writer(Spreadsheet $spreadsheet): Xlsx
    {
        return new Xlsx($spreadsheet);
    }
}
