<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Exception;

class MONExport implements FromArray, WithStyles, WithEvents, WithTitle
{
    protected $headers;
    protected $monthName;
    protected $year;
    protected $totalRows;
    protected $colorMap = [];
    protected $triwulan;
    protected $departmentName;

    // Constants for better maintainability
    private const HEADER_ROWS = 10;
    private const DATA_START_ROW = 11;
    private const DEFAULT_DEPARTMENT = 'TIDAK DIKETAHUI';
    private const COMPANY_NAME = 'PT. KAWASAN BERIKAT NUSANTARA';
    private const DOCUMENT_TITLE = 'KERTAS KERJA MONITORING RISIKO';

    // Risk level mapping
    private const RISK_LEVEL_MAP = [
        'Low' => 1,
        'Low to Moderate' => 8,
        'Moderate' => 13,
        'Moderate to High' => 17,
        'High' => 22
    ];

    // Fallback colors if database is not available
    private const FALLBACK_COLORS = [
        'Low' => '00B050',
        'Low to Moderate' => '92D050',
        'Moderate' => 'FFFF00',
        'Moderate to High' => 'FFC000',
        'High' => 'FF0000'
    ];

    // Column configurations
    private const COLUMN_WIDTHS = [
        'A' => 5,   // NO
        'B' => 12,  // KODE RISIKO
        'C' => 15,  // JENIS RISIKO
        'D' => 25,  // PENYEBAB RISIKO
        'E' => 25,  // TARGET BULANAN
        'F' => 15,  // REALISASI BULANAN
        'G' => 15,  // TARGET TAHUNAN
        'H' => 15,  // REALISASI BULANAN
        'I' => 12,  // % BULANAN
        'J' => 12,  // % TAHUNAN
        'K' => 12,  // BIAYA
        'L' => 18,  // LEVEL DAMPAK
        'M' => 15,  // LEVEL KEMUNGKINAN
        'N' => 15,  // POSISI RISIKO
        'O' => 12,  // LEVEL RISIKO
        'p' => 12
    ];

    /**
     * Constructor
     */
   public function __construct($headers, $monthName = null, $year = null, $departmentName = null)
    {
        $this->headers = collect($headers); // Convert to collection for consistency
        $this->monthName = $monthName ?? 'SEMUA_BULAN';
        $this->year = $year ?? date('Y');
        $this->totalRows = $this->headers->count() + self::HEADER_ROWS;
        $this->triwulan = $this->get_triwulan($monthName);
        $this->departmentName = $departmentName;

        $this->loadColorMapping();
    }

    private function get_triwulan($monthName){
        switch (true) {
            case in_array($monthName, ['JANUARI', 'FEBRUARI', 'MARET']):
                $triwulan = 'PERTAMA';
                break;
            case in_array($monthName, ['APRIL', 'MEI', 'JUNI']):
                $triwulan = 'KEDUA';
                break;
            case in_array($monthName, ['JULI', 'AGUSTUS', 'SEPTEMBER']):
                $triwulan = 'KETIGA';
                break;
            case in_array($monthName, ['OKTOBER', 'NOVEMBER', 'DESEMBER']):
                $triwulan = 'KEEMPAT';
                break;
            default:
                $triwulan = 'PERTAMA';
                break;
        }

        return $triwulan;
    }

    /**
     * Sheet title
     */
    public function title(): string
{
    $deptName = $this->departmentName ? strtoupper(str_replace(' ', '_', $this->departmentName)) : 'ALL_DEPT';
    return 'Monitoring ' . strtoupper($this->monthName) . ' ' . $this->year . ' - ' . $deptName;
}


    /**
     * Load color mapping from database
     */
    private function loadColorMapping(): void
    {
        try {
            $colorRanges = DB::table('mst_heatmap_risk_range')->get();

            foreach ($colorRanges as $range) {
                for ($i = $range->start; $i <= $range->end; $i++) {
                    $this->colorMap[$i] = [
                        'name' => $range->name,
                        'color' => $range->color
                    ];
                }
            }
        } catch (Exception $e) {
            // Log error if needed - fallback colors will be used
            logger('Failed to load color mapping: ' . $e->getMessage());
        }
    }

    /**
     * Get risk color based on risk level
     */
    private function getRiskColor(string $riskLevel): string
    {
        if (empty($riskLevel)) {
            return 'FFFFFF';
        }

        $numLevel = self::RISK_LEVEL_MAP[$riskLevel] ?? 1;

        if (isset($this->colorMap[$numLevel])) {
            return ltrim($this->colorMap[$numLevel]['color'], '#');
        }

        return self::FALLBACK_COLORS[$riskLevel] ?? 'FFFFFF';
    }

    /**
     * Get department name from headers
     */
    private function getDepartmentName(): string
    {
        if ($this->headers->isEmpty()) {
            return self::DEFAULT_DEPARTMENT;
        }

        $firstHeader = $this->headers->first();
        return $firstHeader->department->name ?? self::DEFAULT_DEPARTMENT;
    }

    /**
     * Calculate percentage with division by zero protection
     */
    private function calculatePercentage(float $realization, float $target): float
    {
        return $target > 0 ? round(($realization / $target) * 100, 2) : 0;
    }

    /**
     * Format currency value - now handles both string and numeric input
     */
    private function formatCurrency($amount): string
    {
        // Handle null or empty values
        if ($amount === null || $amount === '') {
            return 'Rp.0';
        }

        // If it's already a string that looks like formatted currency, return as is
        if (is_string($amount) && (strpos($amount, 'Rp.') === 0 || !is_numeric($amount))) {
            return $amount;
        }

        // Convert to float for numeric formatting
        $numericAmount = is_numeric($amount) ? (float)$amount : 0;

        return 'Rp.' . number_format($numericAmount, 0, ',', '.');
    }

    /**
     * Safe numeric conversion - handles both string and numeric input
     */
    private function toNumeric($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // If it's already numeric, return as float
        if (is_numeric($value)) {
            return (float)$value;
        }

        // If it's a string, try to extract numeric value
        if (is_string($value)) {
            // Remove currency symbols and formatting
            $cleaned = preg_replace('/[^\d.,\-]/', '', $value);
            $cleaned = str_replace(',', '.', $cleaned);
            return is_numeric($cleaned) ? (float)$cleaned : 0;
        }

        return 0;
    }

    /**
     * Build document header rows
     */
    private function buildHeaderRows(): array
    {
        $departmentName = $this->getDepartmentName();

        return [
            [self::DOCUMENT_TITLE],
            [self::COMPANY_NAME],
            ['UNIT KERJA : ' . $departmentName],
            ["PERIODE : BULAN {$this->monthName} {$this->year}"],
            [''], // Empty row
            [''], // Empty row
            [''], // Empty row
            [''], // Empty row
            [''], // Empty row
            // [
            //     '', '', '', '',
            //     'WAKTU PELAKSANAAN', '', '', '', '', '', '',
            //     'RESIDUAL TARGET RISK', '', '', ''
            // ],
            // [
            //     '', '', '', '',
            //     "TAHUN {$this->year}", '', '', '', '', '', '',
            //     '', '', '', ''
            // ],
            $this->buildColumnHeaders()
        ];
    }

    /**
     * Build column headers
     */
    private function buildColumnHeaders(): array
    {
        return [
            'NO',
            'KODE RISIKO',
            'JENIS RISIKO',
            'PERISTIWA RISIKO',
            'PENYEBAB RISIKO',
            "TARGET s/d BULAN {$this->monthName}",
            "REALISASI s/d BULAN {$this->monthName}",
            'TARGET 1 TAHUN',
            "REALISASI s/d BULAN {$this->monthName}",
            "BULAN {$this->monthName} %",
            'TARGET TAHUN %',
            'BIAYA PERLAKUAN RISIKO',
            'LEVEL DAMPAK',
            'LEVEL KEMUNGKINAN',
            'POSISI RISIKO',
            'LEVEL RISIKO'
        ];
    }

    /**
     * Build data rows from headers
     */
    private function buildDataRows(): array
    {
        $dataRows = [];
        $no = 1;

        foreach ($this->headers as $header) {
            // Get monthly data safely
            $monthly = $header->monthlyData?->first();

            // Get values with null safety and proper conversion
            $targetBulanan = $this->toNumeric($monthly->target_quantitative ?? 0);
            $realisasiBulanan = $this->toNumeric($monthly->realization_quantitative ?? 0);
            $targetTahunan = $this->toNumeric($header->target_quantitative_satu_tahun ?? 0);

            // Calculate percentages
            $percentageBulanan = $this->calculatePercentage($realisasiBulanan, $targetBulanan);
            $percentageTahunan = $this->calculatePercentage($realisasiBulanan, $targetTahunan);

            $dataRows[] = [
                $no,                                                              // 1. NO auto increment
                $header->risk_code ?? '',                                        // 2. KODE RISIKO
                $header->jenis_risiko ?? '',                                     // 3. JENIS RISIKO
                $header->peristiwa_risiko ?? '',                                 // 4. PERISTIWA RISIKO
                $header->penyebab_risiko ?? '',                                  // 5. PENYEBAB RISIKO
                $this->formatCurrency($monthly->target_quantitative ?? 0),       // 6. TARGET BULAN
                $this->formatCurrency($monthly->realization_quantitative ?? 0),  // 7. REALISASI BULAN
                $this->formatCurrency($header->target_quantitative_satu_tahun ?? 0), // 8. TARGET 1 TAHUN
                $this->formatCurrency($monthly->realization_quantitative ?? 0),  // 9. REALISASI BULAN (duplikasi)
                $percentageBulanan . '%',                                        // 10. BULAN %
                $percentageTahunan . '%',                                        // 11. TARGET TAHUN %
                $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),     // 12. BIAYA PERLAKUAN
                $header->residual_target_level_dampak ?? '',                     // 13. LEVEL DAMPAK
                $header->residual_target_level_kemungkinan ?? '',                // 14. LEVEL KEMUNGKINAN
                $header->residual_target_posisi_risiko ?? '',                    // 15. POSISI RISIKO
                $header->residual_target_level_risiko ?? ''                      // 16. LEVEL RISIKO
            ];

            $no++;
        }

        return $dataRows;
    }

    /**
     * Main array method for export
     */
    public function array(): array
    {
        $headerRows = $this->buildHeaderRows();
        $dataRows = $this->buildDataRows();

        return array_merge($headerRows, $dataRows);
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Main headers - bold and center
            1 => $this->getHeaderStyle(14),
            2 => $this->getHeaderStyle(12),
            3 => $this->getHeaderStyle(11),
            4 => $this->getHeaderStyle(11),

            // Sub headers with background
            10 => $this->getSubHeaderStyle(),
            // 7 => $this->getSubHeaderStyle(),

            // Column headers
            // 7 => $this->getColumnHeaderStyle()
        ];
    }

    /**
     * Get header style configuration
     */
    private function getHeaderStyle(int $fontSize): array
    {
        return [
            'font' => ['bold' => true, 'size' => $fontSize],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }

    /**
     * Get sub header style configuration
     */
    private function getSubHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'd8e4bc']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
    }

    /**
     * Get column header style configuration
     */
    private function getColumnHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];
    }

    /**
     * Register events for after sheet processing
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $this->configureSheet($event->sheet->getDelegate());
            }
        ];
    }

    /**
     * Configure sheet formatting and styling
     */
    private function configureSheet(Worksheet $sheet): void
    {
        $this->mergeCells($sheet);
        $this->setColumnWidths($sheet);
        $this->applyDataRowStyling($sheet);
        $this->applyColumnAlignments($sheet);
        $this->applyRiskLevelColors($sheet);
        $this->setRowHeights($sheet);
    }

    /**
     * Merge cells for headers
     */
    private function mergeCells(Worksheet $sheet): void
    {
        $mergeRanges = [
            'A1:O1', // KERTAS KERJA MONITORING RISIKO
            'A2:O2', // PT. KAWASAN BERIKAT NUSANTARA
            'A3:O3', // UNIT KERJA
            'A4:O4', // PERIODE
            // 'E6:K6', // WAKTU PELAKSANAAN
            // 'E7:K7', // TAHUN
            // 'L6:O6', // RESIDUAL TARGET RISK
            // 'L7:O7'  // Empty cell untuk RESIDUAL TARGET RISK
        ];

        foreach ($mergeRanges as $range) {
            $sheet->mergeCells($range);
        }

        //merge header horizontal (for inherent risk, residual risk and residual target)
        $mergeHorizontal = array(
            [
                "range_start" => "F6",
                "range_end" => "I6",
                "name" => "WAKTU PELAKSANAAN"
            ],
            [
                "range_start" => "F7",
                "range_end" => "I7",
                "name" => "TAHUN ".$this->year
            ],
            [
                "range_start" => "F8",
                "range_end" => "I8",
                "name" => "TRIWULAN ".$this->triwulan
            ],
            [
                "range_start" => "F9",
                "range_end" => "I9",
                "name" => "BULAN ".$this->monthName
            ],
            [
                "range_start" => "M6",
                "range_end" => "P7",
                "name" => "RESIDUAL TARGET RISK"
            ],
        );

        foreach ($mergeHorizontal as $value) {
            $sheet->mergeCells($value['range_start'].':'.$value['range_end']);
            $sheet->setCellValue($value['range_start'], $value['name']);

            $this->setHeaderHorizontalStyle($sheet, $value['range_start'].':'.$value['range_end'], 'FFd8e4bc', 8, true);
        }
        //merge header horizontal (for inherent risk, residual risk and residual target)

        //merge header vertical
        $mergeVertical = array(
            [
                "column" => "A",
                "name" => "NO",
                "start_row" => "6"
            ],
            [
                "column" => "B",
                "name" => "KODE RISIKO",
                "start_row" => "6"
            ],
            [
                "column" => "C",
                "name" => "JENIS RISIKO",
                "start_row" => "6"
            ],
            [
                "column" => "D",
                "name" => "PERISTIWA RISIKO",
                "start_row" => "6"
            ],
            [
                "column" => "E",
                "name" => "PENYEBAB RISIKO",
                "start_row" => "6"
            ],
            [
                "column" => "J",
                "name" => " % s/d BULAN".$this->monthName,
                "start_row" => "6"
            ],
            [
                "column" => "K",
                "name" => "% TARGET TAHUN ".$this->year,
                "start_row" => "6"
            ],
            [
                "column" => "L",
                "name" => "BIAYA PERLAKUAN RISIKO",
                "start_row" => "6"
            ],
            [
                "column" => "M",
                "name" => "LEVEL DAMPAK",
                "start_row" => "8"
            ],
            [
                "column" => "N",
                "name" => "LEVEL KEMUNGKINAN",
                "start_row" => "8"
            ],
            [
                "column" => "O",
                "name" => "POSISI RISIKO",
                "start_row" => "8"
            ],
            [
                "column" => "P",
                "name" => "LEVEL RISIKO",
                "start_row" => "8"
            ],
        );

        foreach ($mergeVertical as $value) {
            $sheet->mergeCells($value['column'].$value['start_row'].':'.$value['column'].'10');
            $sheet->setCellValue($value['column'].$value['start_row'], $value['name']);

            $this->setHeaderHorizontalStyle($sheet, $value['column'].$value['start_row'].':'.$value['column'].'10', 'FFd8e4bc', 8, true);
        }
        //merge header vertical
    }

    /**
     * Set column widths
     */
    private function setColumnWidths(Worksheet $sheet): void
    {
        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * Apply styling to data rows
     */
    private function applyDataRowStyling(Worksheet $sheet): void
    {
        $lastRow = self::HEADER_ROWS + $this->headers->count();
        $dataRange = "A" . self::DATA_START_ROW . ":P{$lastRow}";

        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ]);
    }

    /**
     * Apply column-specific alignments
     */
    private function applyColumnAlignments(Worksheet $sheet): void
    {
        $lastRow = self::HEADER_ROWS + $this->headers->count();

        // Center alignment untuk nomor, kode, target, realisasi, persentase, dan level
        $centerColumns = ['A', 'B', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'O', 'P'];
        foreach ($centerColumns as $col) {
            $range = "{$col}" . self::DATA_START_ROW . ":{$col}{$lastRow}";
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Left alignment untuk kolom text yang panjang
        $leftColumns = ['C', 'D', 'E']; // Jenis dan Penyebab Risiko
        foreach ($leftColumns as $col) {
            $range = "{$col}" . self::DATA_START_ROW . ":{$col}{$lastRow}";
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Right alignment untuk currency column
        $currencyRange = "L" . self::DATA_START_ROW . ":L{$lastRow}";
        $sheet->getStyle($currencyRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Apply colors to risk level column
     */
    private function applyRiskLevelColors(Worksheet $sheet): void
    {
        $rowIndex = self::DATA_START_ROW;

        foreach ($this->headers as $header) {
            $levelRisiko = $header->residual_target_level_risiko ?? '';
            $color = $this->getRiskColor($levelRisiko);

            // Apply color ke kolom O (Level Risiko)
            $sheet->getStyle("P{$rowIndex}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF' . $color]
                ]
            ]);

            $rowIndex++;
        }
    }

    /**
     * Set row heights
     */
    private function setRowHeights(Worksheet $sheet): void
    {
        // Header column height
        $sheet->getRowDimension(self::HEADER_ROWS)->setRowHeight(60);

        // Data rows height
        $lastRow = self::HEADER_ROWS + $this->headers->count();
        for ($row = self::DATA_START_ROW; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(40);
        }
    }

    function setHeaderHorizontalStyle($sheet, $range, $bgColor = '4472C4', $fontSize = 8, $bold = true)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => $bold
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $bgColor]
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
    }
}
