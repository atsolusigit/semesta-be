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

    // Column configurations - Hanya menambah kolom R untuk Evaluasi Perlakuan Risiko
    private const COLUMN_WIDTHS = [
        'A' => 5,   // NO
        'B' => 20,  // KODE RISIKO
        'C' => 20,  // JENIS RISIKO
        'D' => 25,  // PENYEBAB RISIKO
        'E' => 25,  // TARGET BULANAN
        'F' => 20,  // REALISASI BULANAN
        'G' => 20,  // TARGET TAHUNAN
        'H' => 20,  // REALISASI BULANAN
        'I' => 20,  // % BULANAN
        'J' => 12,  // % TAHUNAN
        'K' => 12,  // BIAYA
        'L' => 25,  // EVALUASI PERLAKUAN RISIKO - KEMBALI KE POSISI ASLI
<<<<<<< HEAD
        'M' => 18,  // LEVEL DAMPAK
        'N' => 15,  // LEVEL KEMUNGKINAN
        'O' => 15,  // POSISI RISIKO
        'P' => 12,  // LEVEL RISIKO
        'Q' => 25   // EVALUASI PERLAKUAN RISIKO - POSISI BARU
=======
        'M' => 12,  // LEVEL DAMPAK
        'N' => 12,  // LEVEL KEMUNGKINAN
        'O' => 12,  // POSISI RISIKO
        'P' => 12,  // LEVEL RISIKO
        'Q' => 12   // EVALUASI PERLAKUAN RISIKO - POSISI BARU
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
     * Format target bulanan - handles both qualitative and quantitative data
     */
    private function formatTargetBulan($monthly): string
{
    if (!$monthly) {
        return 'Rp.0';
    }

    $targetKualitatif = trim($monthly->target_kualitatif ?? '');
    $targetQuantitative = trim($monthly->target_quantitative ?? '');

    // Jika target_kualitatif ada isi (bukan kosong/null) = data kualitatif
    if (!empty($targetKualitatif)) {
        // Format: "50%\r\nKualitatif 1" - PERSENTASE DULU
        $result = $targetKualitatif;
        // Tambahkan % jika belum ada dan numeric
        if (is_numeric($targetKualitatif)) {
            $result .= '%';
        }

        // Tampilkan deskripsi kualitatif dari target_quantitative (di bawah)
        if (!empty($targetQuantitative)) {
            $result .= "\r\n" . $targetQuantitative;
        }

        return $result;
    }

    // Jika kosong = data quantitative
    return $this->formatCurrency($targetQuantitative);
}

    /**
     * Format realisasi bulanan - handles both qualitative and quantitative data
     */
   private function formatRealizationBulan($monthly): string
{
    if (!$monthly) {
        return 'Rp.0';
    }

    $realizationKualitatif = trim($monthly->realization_kualitatif ?? '');
    $realizationQuantitative = trim($monthly->realization_quantitative ?? '');

    // Jika realization_kualitatif ada isi (bukan kosong/null) = data kualitatif
    if (!empty($realizationKualitatif)) {
        // Format: "50%\r\nKualitatif 1" - PERSENTASE DULU
        $result = $realizationKualitatif;
        // Tambahkan % jika belum ada dan numeric
        if (is_numeric($realizationKualitatif)) {
            $result .= '%';
        }

        // Tampilkan deskripsi kualitatif dari realization_quantitative (di bawah)
        if (!empty($realizationQuantitative)) {
            $result .= "\r\n" . $realizationQuantitative;
        }

        return $result;
    }

    // Jika kosong = data quantitative
    return $this->formatCurrency($realizationQuantitative);
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
            $this->buildColumnHeaders()
        ];
    }

    /**
     * Build column headers - Hanya memindahkan Evaluasi Perlakuan Risiko ke akhir
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
            'LEVEL RISIKO',
            'EVALUASI PERLAKUAN RISIKO' // DIPINDAH KE AKHIR
        ];
    }

    /**
 * Build data rows from headers - Updated to use new format methods
 */
<<<<<<< HEAD
=======
/**
 * Build data rows from headers - Updated to use new format methods
 */
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
private function buildDataRows(): array
{
    $dataRows = [];
    $no = 1;

    foreach ($this->headers as $header) {
        // Get monthly data safely
        $monthly = $header->monthlyData?->first();

<<<<<<< HEAD
        // --- Resolve risk name (robust) ---
        $riskName = '';

        // 1) Jika ada field risk_name langsung gunakan
        if (!empty($header->risk_name)) {
            $riskName = $header->risk_name;
        }
        // 2) Jika relasi riskCode sudah di-load, ambil namanya
        elseif (isset($header->riskCode) && $header->riskCode && is_iterable($header->riskCode) && count($header->riskCode) > 0) {
            $riskName = collect($header->riskCode)->pluck('name')->filter()->implode(', ');
        }
        // 3) Jika ada field risk_code (bisa berupa JSON string, "1,2", array, atau numeric)
=======
        // --- Resolve risk code (robust) ---
        $riskCode = '';

        // 1) Jika relasi riskCode sudah di-load, ambil code-nya
        if (isset($header->riskCode) && $header->riskCode && is_iterable($header->riskCode) && count($header->riskCode) > 0) {
            $riskCode = collect($header->riskCode)->pluck('code')->filter()->implode(', ');
        }
        // 2) Jika ada field risk_code (bisa berupa JSON string, "1,2", array, atau numeric)
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        elseif (!empty($header->risk_code)) {
            $codesField = $header->risk_code;
            $ids = [];

            if (is_string($codesField)) {
                $decoded = json_decode($codesField, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $ids = $decoded;
                } elseif (strpos($codesField, ',') !== false) {
                    $ids = array_map('trim', explode(',', $codesField));
                } else {
                    $ids = [$codesField];
                }
            } elseif (is_array($codesField) || $codesField instanceof \Illuminate\Support\Collection) {
                $ids = (array)$codesField;
            } elseif (is_numeric($codesField)) {
                $ids = [$codesField];
            }

            // sanitize & cast to int
            $ids = array_filter($ids, function($v) { return $v !== null && $v !== ''; });
            if (!empty($ids)) {
                $ids = array_map('intval', $ids);
                $riskCodes = DB::table('mst_risk_code')
                    ->whereIn('id', $ids)
                    ->orderBy('id')
<<<<<<< HEAD
                    ->pluck('name');
                $riskName = $riskCodes->implode(', ');
            }
        }

        // 4) fallback: pakai jenis_risiko kalau masih kosong
        if (empty($riskName)) {
            $riskName = $header->jenis_risiko ?? '';
        }
        // --- end resolve ---

=======
                    ->pluck('code');
                $riskCode = $riskCodes->implode(', ');
            }
        }
        // --- end resolve ---

        // Get jenis risiko name from relation
        $jenisRisikoName = '';
        if (isset($header->jenisRisiko) && $header->jenisRisiko) {
            $jenisRisikoName = $header->jenisRisiko->nama_jenis_risiko ?? '';
        }

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        // Get formatted values using the new methods
        $targetBulanan = $this->formatTargetBulan($monthly);
        $realisasiBulanan = $this->formatRealizationBulan($monthly);

        // For percentage calculation, we need numeric values
        $targetNumeric = $this->toNumeric($monthly->target_quantitative ?? 0);
        $realisasiNumeric = $this->toNumeric($monthly->realization_quantitative ?? 0);
        $targetTahunanNumeric = $this->toNumeric($header->target_quantitative_satu_tahun ?? 0);

        // Calculate percentages
        $percentageBulanan = $this->calculatePercentage($realisasiNumeric, $targetNumeric);
        $percentageTahunan = $this->calculatePercentage($realisasiNumeric, $targetTahunanNumeric);

        $dataRows[] = [
            $no,                                                              // 1. NO auto increment
<<<<<<< HEAD
            $riskName,                                                        // 2. KODE RISIKO (HANYA NAME)
            $header->jenis_risiko ?? '',                                     // 3. JENIS RISIKO
=======
            $riskCode,                                                        // 2. KODE RISIKO (CODE)
            $jenisRisikoName,                                                 // 3. JENIS RISIKO (NAMA)
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
            $header->peristiwa_risiko ?? '',                                 // 4. PERISTIWA RISIKO
            $header->penyebab_risiko ?? '',                                  // 5. PENYEBAB RISIKO
            $targetBulanan,                                                  // 6. TARGET BULAN (formatted)
            $realisasiBulanan,                                               // 7. REALISASI BULAN (formatted)
            $this->formatCurrency($header->target_quantitative_satu_tahun ?? 0), // 8. TARGET 1 TAHUN
            $realisasiBulanan,                                               // 9. REALISASI BULAN (duplikasi)
            $percentageBulanan . '%',                                        // 10. BULAN %
            $percentageTahunan . '%',                                        // 11. TARGET TAHUN %
            $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),     // 12. BIAYA PERLAKUAN
            $header->residual_target_level_dampak ?? '',                     // 13. LEVEL DAMPAK
            $header->residual_target_level_kemungkinan ?? '',                // 14. LEVEL KEMUNGKINAN
            $header->residual_target_posisi_risiko ?? '',                    // 15. POSISI RISIKO
            $header->residual_target_level_risiko ?? '',                     // 16. LEVEL RISIKO
<<<<<<< HEAD
            $monthly->realization_note ?? ''                                 // 17. EVALUASI PERLAKUAN RISIKO (DIPINDAH KE AKHIR)
=======
            $monthly->realization_note ?? ''                                 // 17. EVALUASI PERLAKUAN RISIKO
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
     * Merge cells for headers - Update range ke Q untuk menampung kolom baru
     */
<<<<<<< HEAD
    private function mergeCells(Worksheet $sheet): void
    {
        $mergeRanges = [
            'A1:Q1', // KERTAS KERJA MONITORING RISIKO
            'A2:Q2', // PT. KAWASAN BERIKAT NUSANTARA
            'A3:Q3', // UNIT KERJA
            'A4:Q4', // PERIODE
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
                "range_end" => "P7", // RESIDUAL TARGET RISK hanya M-P (tidak termasuk Q)
                "name" => "RESIDUAL TARGET RISK"
            ],
        );

        foreach ($mergeHorizontal as $value) {
            $sheet->mergeCells($value['range_start'].':'.$value['range_end']);
            $sheet->setCellValue($value['range_start'], $value['name']);

            $this->setHeaderHorizontalStyle($sheet, $value['range_start'].':'.$value['range_end'], 'FFd8e4bc', 8, true);
        }

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
                "name" => " % s/d BULAN ".$this->monthName,
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
            [
                "column" => "Q",
                "name" => "EVALUASI PERLAKUAN RISIKO", // KOLOM Q TERPISAH
                "start_row" => "6"
            ],
        );

        foreach ($mergeVertical as $value) {
            $sheet->mergeCells($value['column'].$value['start_row'].':'.$value['column'].'10');
            $sheet->setCellValue($value['column'].$value['start_row'], $value['name']);

            $this->setHeaderHorizontalStyle($sheet, $value['column'].$value['start_row'].':'.$value['column'].'10', 'FFd8e4bc', 8, true);
        }
    }

=======
    /**
 * Merge cells for headers - Update range ke Q untuk menampung kolom baru
 */
private function mergeCells(Worksheet $sheet): void
{
    $mergeRanges = [
        'A1:Q1', // KERTAS KERJA MONITORING RISIKO
        'A2:Q2', // PT. KAWASAN BERIKAT NUSANTARA
        'A3:Q3', // UNIT KERJA
        'A4:Q4', // PERIODE
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
            "range_end" => "P7", // RESIDUAL TARGET RISK hanya M-P (tidak termasuk Q)
            "name" => "RESIDUAL TARGET RISK"
        ],
    );

    foreach ($mergeHorizontal as $value) {
        $sheet->mergeCells($value['range_start'].':'.$value['range_end']);
        $sheet->setCellValue($value['range_start'], $value['name']);

        $this->setHeaderHorizontalStyle($sheet, $value['range_start'].':'.$value['range_end'], 'FFd8e4bc', 8, true);
    }

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
        "name" => " % s/d BULAN ".$this->monthName,
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
        "start_row" => "8",
        "vertical" => true
    ],
    [
        "column" => "N",
        "name" => "LEVEL KEMUNGKINAN",
        "start_row" => "8",
        "vertical" => true
    ],
    [
        "column" => "O",
        "name" => "POSISI RISIKO",
        "start_row" => "8",
        "vertical" => true
    ],
    [
        "column" => "P",
        "name" => "LEVEL RISIKO",
        "start_row" => "8",
        "vertical" => true
    ],
    [
        "column" => "Q",
        "name" => "EVALUASI PERLAKUAN RISIKO",
        "start_row" => "6",
        "vertical" => true  // Tambah flag vertical
    ],
);

    foreach ($mergeVertical as $value) {
        $sheet->mergeCells($value['column'].$value['start_row'].':'.$value['column'].'10');
        $sheet->setCellValue($value['column'].$value['start_row'], $value['name']);

        // Cek apakah perlu vertical alignment
        $isVertical = isset($value['vertical']) && $value['vertical'] === true;
        $this->setHeaderHorizontalStyle($sheet, $value['column'].$value['start_row'].':'.$value['column'].'10', 'FFd8e4bc', 8, true, $isVertical);
    }
}

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
     * Apply styling to data rows - Update range ke Q
     */
    private function applyDataRowStyling(Worksheet $sheet): void
    {
        $lastRow = self::HEADER_ROWS + $this->headers->count();
        $dataRange = "A" . self::DATA_START_ROW . ":Q{$lastRow}";

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
     * Apply column-specific alignments - Menambah kolom Q untuk left alignment
     */
    private function applyColumnAlignments(Worksheet $sheet): void
    {
        $lastRow = self::HEADER_ROWS + $this->headers->count();

        // Center alignment untuk nomor, kode, target, realisasi, persentase, dan level
        $centerColumns = ['A', 'B', 'F', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'P'];
        foreach ($centerColumns as $col) {
            $range = "{$col}" . self::DATA_START_ROW . ":{$col}{$lastRow}";
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Left alignment untuk kolom text yang panjang
        $leftColumns = ['C', 'D', 'E', 'Q']; // Q untuk Evaluasi Perlakuan Risiko
        foreach ($leftColumns as $col) {
            $range = "{$col}" . self::DATA_START_ROW . ":{$col}{$lastRow}";
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Right alignment untuk currency column
        $currencyRange = "L" . self::DATA_START_ROW . ":L{$lastRow}";
        $sheet->getStyle($currencyRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }


    /**
 * Get risk color based on position
 */
private function getRiskColorByPosition($posisi): string
{
    // Convert posisi ke integer
    $posisi = (int)$posisi;

    // Cek di colorMap yang sudah di-load dari database
    if (isset($this->colorMap[$posisi])) {
        return ltrim($this->colorMap[$posisi]['color'], '#');
    }

    // Fallback manual berdasarkan range dari database
    if ($posisi >= 1 && $posisi <= 5) {
        return '00B050'; // Low - Hijau
    } elseif ($posisi >= 6 && $posisi <= 11) {
        return '62b334'; // Low To Moderate - Hijau Muda
    } elseif ($posisi >= 12 && $posisi <= 15) {
        return 'd2da15'; // Moderate - Kuning
    } elseif ($posisi >= 16 && $posisi <= 19) {
        return 'FFC000'; // Moderate To High - Orange
    } elseif ($posisi >= 20 && $posisi <= 25) {
        return 'FF0000'; // High - Merah
    }

    // Default putih jika tidak ada match
    return 'FFFFFF';
}

   /**
 * Apply colors to risk level column - MENGGUNAKAN POSISI RISIKO
 */
private function applyRiskLevelColors(Worksheet $sheet): void
{
    $rowIndex = self::DATA_START_ROW;

    foreach ($this->headers as $header) {
        // GUNAKAN POSISI RISIKO untuk color mapping (bukan level risiko string)
        $posisiRisiko = $header->residual_target_posisi_risiko ?? 0;

        // Hanya apply color jika posisi > 0
        if ($posisiRisiko > 0) {
            $color = $this->getRiskColorByPosition($posisiRisiko);

            // Apply color ke kolom P (Level Risiko)
            $sheet->getStyle("P{$rowIndex}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF' . $color]
                ]
            ]);
        }

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

<<<<<<< HEAD
    function setHeaderHorizontalStyle($sheet, $range, $bgColor = '4472C4', $fontSize = 8, $bold = true)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => $bold
=======
        function setHeaderHorizontalStyle($sheet, $range, $bgColor = '4472C4', $fontSize = 8, $bold = true, $vertical = false)
    {
        $styleArray = [
            'font' => [
                'bold' => $bold,
                'size' => $fontSize
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
<<<<<<< HEAD
        ]);
=======
        ];

        // Tambahkan textRotation untuk vertical text
        if ($vertical) {
            $styleArray['alignment']['textRotation'] = 90;
        }

        $sheet->getStyle($range)->applyFromArray($styleArray);
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    }
}
