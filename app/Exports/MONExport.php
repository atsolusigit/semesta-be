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

    // Constants for better maintainability
    private const HEADER_ROWS = 8;
    private const DATA_START_ROW = 9;
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
        'E' => 15,  // TARGET BULANAN
        'F' => 15,  // REALISASI BULANAN
        'G' => 15,  // TARGET TAHUNAN
        'H' => 15,  // REALISASI BULANAN
        'I' => 12,  // % BULANAN
        'J' => 12,  // % TAHUNAN
        'K' => 18,  // BIAYA
        'L' => 12,  // LEVEL DAMPAK
        'M' => 15,  // LEVEL KEMUNGKINAN
        'N' => 12,  // POSISI RISIKO
        'O' => 12,  // LEVEL RISIKO
    ];

    /**
     * Constructor
     */
    public function __construct($headers, $monthName = null, $year = null)
    {
        $this->headers = collect($headers); // Convert to collection for consistency
        $this->monthName = $monthName ?? 'SEMUA_BULAN';
        $this->year = $year ?? date('Y');
        $this->totalRows = $this->headers->count() + self::HEADER_ROWS;

        $this->loadColorMapping();
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Monitoring ' . strtoupper($this->monthName) . ' ' . $this->year;
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
     * Format currency value
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
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
            [], // Empty row
            [
                '', '', '', '',
                'WAKTU PELAKSANAAN', '', '', '', '', '', '',
                'RESIDUAL TARGET RISK', '', '', ''
            ],
            [
                '', '', '', '',
                "TAHUN {$this->year}", '', '', '', '', '', '',
                '', '', '', ''
            ],
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
            'PENYEBAB RISIKO',
            "TARGET BULAN {$this->monthName}",
            "REALISASI BULAN {$this->monthName}",
            'TARGET 1 TAHUN',
            "REALISASI BULAN {$this->monthName}",
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

            // Get values with null safety and proper casting
            $targetBulanan = (float)($monthly->target_quantitative ?? 0);
            $realisasiBulanan = (float)($monthly->realization_quantitative ?? 0);
            $targetTahunan = (float)($header->target_quantitative_satu_tahun ?? 0);

            // Calculate percentages
            $percentageBulanan = $this->calculatePercentage($realisasiBulanan, $targetBulanan);
            $percentageTahunan = $this->calculatePercentage($realisasiBulanan, $targetTahunan);

            $dataRows[] = [
                $no,                                                              // 1. NO auto increment
                $header->risk_code ?? '',                                        // 2. KODE RISIKO
                $header->jenis_risiko ?? '',                                     // 3. JENIS RISIKO
                $header->penyebab_risiko ?? '',                                  // 4. PENYEBAB RISIKO
                $targetBulanan,                                                  // 5. TARGET BULAN
                $realisasiBulanan,                                               // 6. REALISASI BULAN
                $targetTahunan,                                                  // 7. TARGET 1 TAHUN
                $realisasiBulanan,                                               // 8. REALISASI BULAN (duplikasi)
                $percentageBulanan . '%',                                        // 9. BULAN %
                $percentageTahunan . '%',                                        // 10. TARGET TAHUN %
                $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),     // 11. BIAYA PERLAKUAN
                $header->residual_target_level_dampak ?? '',                     // 12. LEVEL DAMPAK
                $header->residual_target_level_kemungkinan ?? '',                // 13. LEVEL KEMUNGKINAN
                $header->residual_target_posisi_risiko ?? '',                    // 14. POSISI RISIKO
                $header->residual_target_level_risiko ?? ''                      // 15. LEVEL RISIKO
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
            6 => $this->getSubHeaderStyle(),
            7 => $this->getSubHeaderStyle(),

            // Column headers
            8 => $this->getColumnHeaderStyle()
        ];
    }

    /**
     * Get header style configuration
     */
    private function getHeaderStyle(int $fontSize): array
    {
        return [
            'font' => ['bold' => true, 'size' => $fontSize],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
    }

    /**
     * Get sub header style configuration
     */
    private function getSubHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E6E6FA']
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
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D3D3D3']
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
            'E6:K6', // WAKTU PELAKSANAAN
            'E7:K7', // TAHUN
            'L6:O6', // RESIDUAL TARGET RISK
            'L7:O7'  // Empty cell untuk RESIDUAL TARGET RISK
        ];

        foreach ($mergeRanges as $range) {
            $sheet->mergeCells($range);
        }
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
        $dataRange = "A" . self::DATA_START_ROW . ":O{$lastRow}";

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
        $centerColumns = ['A', 'B', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'O'];
        foreach ($centerColumns as $col) {
            $range = "{$col}" . self::DATA_START_ROW . ":{$col}{$lastRow}";
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Left alignment untuk kolom text yang panjang
        $leftColumns = ['C', 'D']; // Jenis dan Penyebab Risiko
        foreach ($leftColumns as $col) {
            $range = "{$col}" . self::DATA_START_ROW . ":{$col}{$lastRow}";
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Right alignment untuk currency column
        $currencyRange = "K" . self::DATA_START_ROW . ":K{$lastRow}";
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
            $sheet->getStyle("O{$rowIndex}")->applyFromArray([
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
}
