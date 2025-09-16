<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;

class RiskExport implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $headers;
    protected $monthName;
    protected $year;
    protected $colorMap = [];
    protected $departmentName;

    public function __construct($headers, $monthName = 'Semua Bulan', $year = 2025, $departmentName = null)
    {
        $this->headers = $headers;
        $this->monthName = $monthName;
        $this->year = $year;
        $this->departmentName = $departmentName;

        // Load color mapping dari database
        $this->loadColorMapping();
    }
    private function loadColorMapping()
    {
        // Ambil mapping warna dari tabel mst_heatmap_risk_range
        $colorRanges = DB::table('mst_heatmap_risk_range')->get();

        foreach ($colorRanges as $range) {
            for ($i = $range->start; $i <= $range->end; $i++) {
                $this->colorMap[$i] = [
                    'name' => $range->name,
                    'color' => $range->color
                ];
            }
        }
    }

    private function getRiskColor($riskLevel)
    {
        // Convert risk level string ke number untuk mapping
        $levelMap = [
            'Low' => 1,
            'Low to Moderate' => 8,
            'Moderate' => 13,
            'Moderate to High' => 17,
            'High' => 22
        ];

        $numLevel = $levelMap[$riskLevel] ?? 1;

        if (isset($this->colorMap[$numLevel])) {
            return $this->colorMap[$numLevel]['color'];
        }

        // Fallback colors jika tidak ada di database
        $fallbackColors = [
            'Low' => '#00B050',
            'Low to Moderate' => '#92D050',
            'Moderate' => '#FFFF00',
            'Moderate to High' => '#FFC000',
            'High' => '#FF0000'
        ];

        return $fallbackColors[$riskLevel] ?? '#FFFFFF';
    }

    private function formatCurrency($value)
    {
        // Jika value kosong atau null, return kosong
        if (empty($value) || $value === null || $value === '') {
            return '';
        }

        // Jika value berupa string yang mengandung huruf (bukan angka), return as is
        if (is_string($value) && preg_match('/[a-zA-Z]/', $value)) {
            return $value;
        }

        // Jika sudah numeric, langsung format
        if (is_numeric($value)) {
            $numericValue = floatval($value);
            return 'Rp.' . number_format($numericValue, 0, ',', '.');
        }

        // Ekstrak angka dari string jika ada
        if (is_string($value)) {
            // Ambil hanya angka dan titik/koma dari string
            $numericValue = preg_replace('/[^\d.,]/', '', $value);

            // Jika tidak ada angka sama sekali, return string asli
            if (empty($numericValue)) {
                return $value;
            }

            // Replace koma dengan titik untuk decimal
            $numericValue = str_replace(',', '.', $numericValue);

            // Jika setelah ekstraksi tidak numeric, return string asli
            if (!is_numeric($numericValue)) {
                return $value;
            }

            $numericValue = floatval($numericValue);
            return 'Rp.' . number_format($numericValue, 0, ',', '.');
        }

        // Fallback - return original value
        return $value;
    }

    private function formatTarget($quantitative, $qualitative)
    {
        $result = '';

        // Format quantitative jika ada
        if (!empty($quantitative) && $quantitative !== null && $quantitative !== '') {
            if (is_numeric($quantitative) && floatval($quantitative) > 0) {
                $result .= $this->formatCurrency($quantitative);
            } else {
                $result .= $quantitative;
            }
        }

        // Format qualitative jika ada
        if (!empty($qualitative) && $qualitative !== null && $qualitative !== '') {
            if (!empty($result)) {
                $result .= "\n"; // Line break untuk memisahkan
            }
            $result .= $qualitative;
        }

        return $result;
    }

  // Perbaikan untuk method formatTargetBulan di RiskExport.php
private function formatTargetBulan($monthly)
{
    if (!$monthly) {
        return '';
    }

    $quantitative = $monthly->target_quantitative ?? '';
    $kualitatif = $monthly->target_kualitatif ?? '';

    // Jika target_kualitatif ada isi dan bukan kosong/null, berarti data kualitatif
    if (!empty($kualitatif) && $kualitatif !== null && trim($kualitatif) !== '') {
        $result = '';

        // Tampilkan persentase dari target_kualitatif dulu (di atas)
        $result = $kualitatif;
        // Tambahkan % jika belum ada dan numeric
        if (is_numeric($kualitatif)) {
            $result .= '%';
        }

        // Tampilkan deskripsi kualitatif dari target_quantitative (di bawah)
        if (!empty($quantitative)) {
            $result .= "\n"; // Line break untuk memisahkan
            $result .= $quantitative;
        }

        return $result;
    }

    // Jika target_kualitatif kosong/null, berarti data quantitative murni
    if (!empty($quantitative)) {
        if (is_numeric($quantitative)) {
            return $this->formatCurrency($quantitative);
        } else {
            return $quantitative;
        }
    }

    return '';
}

// Perbaikan untuk method formatRealizationBulan di RiskExport.php
private function formatRealizationBulan($monthly)
{
    if (!$monthly) {
        return '';
    }

    $quantitative = $monthly->realization_quantitative ?? '';
    $kualitatif = $monthly->realization_kualitatif ?? '';

    // Jika realization_kualitatif ada isi dan bukan kosong/null, berarti data kualitatif
    if (!empty($kualitatif) && $kualitatif !== null && trim($kualitatif) !== '') {
        $result = '';

        // Tampilkan persentase dari realization_kualitatif dulu (di atas)
        $result = $kualitatif;
        // Tambahkan % jika belum ada dan numeric
        if (is_numeric($kualitatif)) {
            $result .= '%';
        }

        // Tampilkan deskripsi kualitatif dari realization_quantitative (di bawah)
        if (!empty($quantitative)) {
            $result .= "\n"; // Line break untuk memisahkan
            $result .= $quantitative;
        }

        return $result;
    }

    // Jika realization_kualitatif kosong/null, berarti data quantitative murni
    if (!empty($quantitative)) {
        if (is_numeric($quantitative)) {
            return $this->formatCurrency($quantitative);
        } else {
            return $quantitative;
        }
    }

    return '';
}

   private function getRiskCodeName($header)
{
    // Kalau sudah ada relasi riskCode
    if (isset($header->riskCode) && $header->riskCode && $header->riskCode->isNotEmpty()) {
        return $header->riskCode->map(function ($code) {
            return (!empty($code->code) ? $code->code . ' - ' : '') . $code->name;
        })->implode(', ');
    }

    if (!empty($header->risk_code)) {
        $riskCodeIds = $header->risk_code;

        // Coba decode JSON
        if (is_string($riskCodeIds)) {
            $decoded = json_decode($riskCodeIds, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $riskCodeIds = $decoded;
            }
        }

        // String dengan koma → array
        if (is_string($riskCodeIds) && str_contains($riskCodeIds, ',')) {
            $riskCodeIds = explode(',', $riskCodeIds);
        }

        // Single id → array
        if (is_numeric($riskCodeIds)) {
            $riskCodeIds = [$riskCodeIds];
        }

        if (is_array($riskCodeIds) && !empty($riskCodeIds)) {
            $riskCodes = DB::table('mst_risk_code')
                ->whereIn('id', $riskCodeIds)
                ->orderBy('id')
                ->get(['name']);

            return $riskCodes->map(function ($rc) {
                return (!empty($rc->code) ? $rc->code . ' - ' : '') . $rc->name;
            })->implode(', ');
        }
    }

    return '';
}


    public function array(): array
    {
        $data = [];
        $no = 1;

        foreach ($this->headers as $header) {
            $monthly = $header->monthlyData->first();

        //             // Debug tambahan
        // \Log::info("Header ID: " . $header->id);
        // \Log::info("Monthly data count: " . $header->monthlyData->count());
        // if ($monthly) {
        //     \Log::info("Monthly ID: " . $monthly->id);
        //     \Log::info("Monthly raw data: " . json_encode($monthly->toArray()));
        // }

            if (!$monthly) {
                $target = 0;
                $realization = 0;
                $percentage = 0;
                $monthlyData = (object) [
                    'id' => 'NO_MONTHLY',
                    'target_quantitative' => 0,
                    'target_kualitatif' => '',
                    'realization_quantitative' => 0,
                    'realization_kualitatif' => '',
                    'residual_risk_level_dampak' => '',
                    'residual_risk_level_kemungkinan' => '',
                    'residual_risk_posisi_risiko' => '',
                    'residual_risk_level_risiko' => '',
                    'realization_note' => '',
                    'status_risiko' => '',
                ];
            } else {
                $target = $monthly->target_quantitative ?? 0;
                $realization = $monthly->realization_quantitative ?? 0;

                // Fix division by zero or string issue
                if (is_numeric($target) && is_numeric($realization) && floatval($target) > 0) {
                    $percentage = round((floatval($realization) / floatval($target)) * 100, 2);
                } else {
                    $percentage = 0;
                }

                $monthlyData = $monthly;
            }

            // Get risk code name menggunakan method yang sudah diperbaiki
            $riskCode = $this->getRiskCodeName($header);

            $data[] = [
                $no++, // No
                $riskCode, // Kode Risiko - FIXED: ambil name dari relasi mst_risk_code
                $header->jenis_risiko ?? '', // Jenis Risiko
                $header->sasaran ?? '', // Sasaran
                $header->peristiwa_risiko ?? '', // Peristiwa Risiko
                $header->penyebab_risiko ?? '', // Penyebab Risiko
                $header->dampak_risiko ?? '', // Dampak Risiko

                // Inherent Risk (4 kolom)
                $header->inherent_risk_level_dampak ?? '',
                $header->inherent_risk_level_kemungkinan ?? '',
                $header->inherent_risk_posisi_risiko ?? '',
                $header->inherent_risk_level_risiko ?? '',

                $header->internal_control ?? '', // Internal Control
                $this->formatTargetBulan($monthlyData),           // Target Bulan (Quantitative + Qualitative)
                $this->formatRealizationBulan($monthlyData),      // Realisasi Bulan (Quantitative + Qualitative)
                $percentage . '%', // Persentase Bulan

                // Residual Risk Saat Ini (4 kolom)
                $monthlyData->residual_risk_level_dampak ?? '',
                $monthlyData->residual_risk_level_kemungkinan ?? '',
                $monthlyData->residual_risk_posisi_risiko ?? '',
                $monthlyData->residual_risk_level_risiko ?? '',

                $this->formatTarget($header->target_quantitative_satu_tahun ?? 0, $header->target_kualitatif_satu_tahun ?? ''), // Target 1 Tahun (Combined)
                $this->formatRealizationBulan($monthlyData),                                // Realisasi (duplicate)

                // Residual Target Risk (4 kolom)
                $header->residual_target_level_dampak ?? '',
                $header->residual_target_level_kemungkinan ?? '',
                $header->residual_target_posisi_risiko ?? '',
                $header->residual_target_level_risiko ?? '',

                $header->mitigasi ?? '', // Perlakuan Risiko
                $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),         // Biaya Perlakuan

                // Residual Target Risk (duplicate - 4 kolom)
                $header->residual_target_level_dampak ?? '',
                $header->residual_target_level_kemungkinan ?? '',
                $header->residual_target_posisi_risiko ?? '',
                $header->residual_target_level_risiko ?? '',

                $monthlyData->status_risiko ?? '', // Status Risiko
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        $firstHeader = $this->headers->first();
        $departmentName = $firstHeader->department->name ?? 'TIDAK DIKETAHUI';

        return [
            ['KERTAS KERJA RISK REGISTER'],
            ['PT. KAWASAN BERIKAT NUSANTARA'],
            ['UNIT KERJA : ' . $departmentName],
            ['PERIODE : BULAN ' . strtoupper($this->monthName) . ' ' . $this->year],
            [], // baris kosong
            [], // baris kosong
            [
                'NO',
                'KODE RISIKO',
                'JENIS RISIKO',
                'SASARAN',
                'PERISTIWA RISIKO',
                'PENYEBAB RISIKO',
                'DAMPAK RISIKO',
                'DAMPAK',
                'KEMUNGKINAN',
                'POSISI RISIKO',
                'LEVEL RISIKO',
                'INTERNAL CONTROL',
                'TARGET s/d BULAN ' . strtoupper($this->monthName),
                'REALISASI s/d BULAN ' . strtoupper($this->monthName),
                '%',
                'DAMPAK',
                'KEMUNGKINAN',
                'POSISI RISIKO',
                'LEVEL RISIKO',
                'TARGET 1 TAHUN',
                'REALISASI S/D BULAN ' . strtoupper($this->monthName),
                'DAMPAK',
                'KEMUNGKINAN',
                'POSISI RISIKO',
                'LEVEL RISIKO',
                'PERLAKUAN RISIKO (MITIGASI)',
                'BIAYA PERLAKUAN RISIKO',
                'DAMPAK',
                'KEMUNGKINAN',
                'POSISI RISIKO',
                'LEVEL RISIKO',
                'STATUS RISIKO',
            ]
        ];
    }

   public function title(): string
{
    $deptName = $this->departmentName ? strtoupper(str_replace(' ', '_', $this->departmentName)) : 'ALL_DEPT';
    return 'Risk Register ' . strtoupper($this->monthName) . ' ' . $this->year. ' - ' . $deptName;
}


    public function styles(Worksheet $sheet)
    {
        return [
            // Header styles
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            3 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            4 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            // Table header
            6 => [
                'font' => ['bold' => true, 'size' => 8], // Font lebih kecil untuk header
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFd8e4bc'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ]
            ],
            7 => [
                'font' => ['bold' => true, 'size' => 8], // Font lebih kecil untuk header
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFd8e4bc'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ]
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Merge cells untuk header
                $sheet->mergeCells('A1:AF1'); // KERTAS KERJA RISK REGISTER
                $sheet->mergeCells('A2:AF2'); // PT. KAWASAN BERIKAT NUSANTARA
                $sheet->mergeCells('A3:AF3'); // unit kerja
                $sheet->mergeCells('A4:AF4'); // periode

                //merge header horizontal (for inherent risk, residual risk and residual target)
                $mergeHorizontal = array(
                    [
                        "range_start" => "H",
                        "range_end" => "K",
                        "name" => "INHERENT RISK"
                    ],
                    [
                        "range_start" => "P",
                        "range_end" => "S",
                        "name" => "RESIDUAL RISK"
                    ],
                    [
                        "range_start" => "V",
                        "range_end" => "Y",
                        "name" => "RESIDUAL RISK"
                    ],
                    [
                        "range_start" => "AB",
                        "range_end" => "AE",
                        "name" => "RESIDUAL TARGET"
                    ],
                );

                foreach ($mergeHorizontal as $value) {
                    $sheet->mergeCells($value['range_start'].'6:'.$value['range_end'].'6');
                    $sheet->setCellValue($value['range_start'].'6', $value['name']);

                    $this->setHeaderStyle($sheet, $value['range_start'].'6:'.$value['range_end'].'6', 'd8e4bc', 8, true);
                }
                //merge header horizontal (for inherent risk, residual risk and residual target)

                //merge header vertical
                $mergeVertical = array(
                    [
                        "column" => "A",
                        "name" => "NO"
                    ],
                    [
                        "column" => "B",
                        "name" => "KODE RISIKO"
                    ],
                    [
                        "column" => "C",
                        "name" => "JENIS RISIKO"
                    ],
                    [
                        "column" => "D",
                        "name" => "SASARAN"
                    ],
                    [
                        "column" => "E",
                        "name" => "PERISTIWA RISIKO"
                    ],
                    [
                        "column" => "F",
                        "name" => "PENYEBAB RISIKO"
                    ],
                    [
                        "column" => "G",
                        "name" => "DAMPAK RISIKO"
                    ],
                    [
                        "column" => "L",
                        "name" => "INTERNAL CONTROL"
                    ],
                    [
                        "column" => "M",
                        "name" => "TARGET s/d BULAN " . strtoupper($this->monthName)
                    ],
                    [
                        "column" => "N",
                        "name" => "REALISASI s/d BULAN " . strtoupper($this->monthName)
                    ],
                    [
                        "column" => "O",
                        "name" => "% s/d BULAN " . strtoupper($this->monthName)
                    ],
                    [
                        "column" => "T",
                        "name" => "TARGET 1 TAHUN"
                    ],
                    [
                        "column" => "U",
                        "name" => "REALISASI s/d BULAN " . strtoupper($this->monthName)
                    ],
                    [
                        "column" => "Z",
                        "name" => "PERLAKUAN RISIKO (MITIGASI)"
                    ],
                    [
                        "column" => "AA",
                        "name" => "BIAYA PERLAKUAN RISIKO"
                    ],
                    [
                        "column" => "AF",
                        "name" => "STATUS RISIKO"
                    ],
                );

                foreach ($mergeVertical as $value) {
                    $sheet->mergeCells($value['column'].'6:'.$value['column'].'7');
                    $sheet->setCellValue($value['column'].'6', $value['name']);

                    $this->setHeaderStyle($sheet, $value['column'].'6:'.$value['column'].'7', 'd8e4bc', 8, true);
                }
                //merge header vertical

                // Set column widths - disesuaikan dengan gambar
                $columnWidths = [
                    // MAIN COLUMNS
                    'A' => 4,   // NO (lebih kecil)
                    'B' => 15,  // KODE RISIKO (lebih lebar untuk multiple codes)
                    'C' => 20,  // JENIS RISIKO
                    'D' => 25,  // SASARAN (lebih lebar)
                    'E' => 25,  // PERISTIWA RISIKO (lebih lebar)
                    'F' => 25,  // PENYEBAB RISIKO (lebih lebar)
                    'G' => 25,  // DAMPAK RISIKO (lebih lebar)

                    // INHERENT RISK
                    'H' => 6,   // DAMPAK
                    'I' => 8,   // KEMUNGKINAN
                    'J' => 6,   // POSISI
                    'K' => 8,   // LEVEL

                    'L' => 30,  // INTERNAL CONTROL (lebih lebar)
                    'M' => 15,  // TARGET BULAN (lebih lebar untuk kombinasi)
                    'N' => 15,  // REALISASI BULAN (lebih lebar untuk kombinasi)
                    'O' => 5,   // % (lebih kecil)

                    // RESIDUAL RISK SAAT INI
                    'P' => 6,   // DAMPAK
                    'Q' => 8,   // KEMUNGKINAN
                    'R' => 6,   // POSISI
                    'S' => 8,   // LEVEL

                    'T' => 15,  // TARGET 1 TAHUN (lebih lebar untuk kombinasi)
                    'U' => 15,  // REALISASI (lebih lebar untuk kombinasi)

                    // RESIDUAL TARGET RISK
                    'V' => 6,   // DAMPAK
                    'W' => 8,   // KEMUNGKINAN
                    'X' => 6,   // POSISI
                    'Y' => 8,  // LEVEL

                    'Z' => 35, // PERLAKUAN RISIKO (sangat lebar untuk wrap text)
                    'AA' => 12, // BIAYA PERLAKUAN

                    // RESIDUAL TARGET RISK (duplicate)
                    'AB' => 6,  // DAMPAK
                    'AC' => 8,  // KEMUNGKINAN
                    'AD' => 6,  // POSISI
                    'AE' => 8,  // LEVEL

                    'AF' => 12, // STATUS RISIKO
                ];


                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                // Apply colors to risk level cells
                $dataStartRow = 8;
                $totalRows = count($this->headers) + $dataStartRow - 1;
                for ($row = $dataStartRow; $row <= $totalRows; $row++) {
                    $dataIndex = $row - $dataStartRow;
                    if (isset($this->headers[$dataIndex])) {
                        $header = $this->headers[$dataIndex];
                        $monthly = $header->monthlyData->first();

                        // Color Inherent Risk Level (column K)
                        $inherentLevel = $header->inherent_risk_level_risiko ?? '';
                        if ($inherentLevel) {
                            $color = $this->getRiskColor($inherentLevel);
                            $sheet->getStyle('K' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                        }

                        // Color Residual Risk Level Saat Ini (column S)
                        if ($monthly) {
                            $residualLevel = $monthly->residual_risk_level_risiko ?? '';
                            if ($residualLevel) {
                                $color = $this->getRiskColor($residualLevel);
                                $sheet->getStyle('S' . $row)->applyFromArray([
                                    'fill' => [
                                        'fillType' => Fill::FILL_SOLID,
                                        'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                    ],
                                ]);
                            }
                        }

                        // Color Residual Target Risk (column Y & AE)
                        $targetLevel = $header->residual_target_level_risiko ?? '';
                        if ($targetLevel) {
                            $color = $this->getRiskColor($targetLevel);
                            $sheet->getStyle('Y' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                            $sheet->getStyle('AE' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                        }
                    }
                }

                // Apply borders to all data
                $sheet->getStyle('A6:AF' . $totalRows)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);
                $sheet->getStyle('A7:AF' . $totalRows)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Set text alignment for data rows with STRONG wrap text setting
                $sheet->getStyle('A' . $dataStartRow . ':AF' . $totalRows)->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'wrapText' => true, // PENTING: Pastikan wrap text aktif
                        'shrinkToFit' => false // Jangan shrink, tapi wrap
                    ],
                    'font' => ['size' => 9]
                ]);

                // Center align untuk kolom tertentu saja
                $sheet->getStyle('A' . $dataStartRow . ':C' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // NO
                $sheet->getStyle('O' . $dataStartRow . ':Q' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Target, Realisasi, %
                $sheet->getStyle('V' . $dataStartRow . ':W' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Target 1 Tahun, Realisasi

                // Khusus untuk kolom dengan teks panjang, pastikan wrap text dan height otomatis
                $longTextColumns = ['D', 'E', 'F', 'G', 'L', 'Z']; // Sasaran, Peristiwa, Penyebab, Dampak, Internal Control, Perlakuan Risiko
                foreach ($longTextColumns as $col) {
                    $sheet->getStyle($col . $dataStartRow . ':' . $col . $totalRows)->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_TOP,
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'wrapText' => true,
                            'shrinkToFit' => false
                        ]
                    ]);
                }

                // Khusus untuk kolom yang menggabungkan quantitative dan qualitative
                $combinedColumns = ['M', 'N', 'T', 'U']; // Target Bulan, Realisasi Bulan, Target 1 Tahun, Realisasi
                foreach ($combinedColumns as $col) {
                    $sheet->getStyle($col . $dataStartRow . ':' . $col . $totalRows)->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_TOP,
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'wrapText' => true,
                            'shrinkToFit' => false
                        ]
                    ]);
                }

                // Khusus untuk kolom kode risiko agar bisa wrap multiple codes
                $sheet->getStyle('B' . $dataStartRow . ':B' . $totalRows)->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'wrapText' => true,
                        'shrinkToFit' => false
                    ]
                ]);

                // Set row heights
                $sheet->getRowDimension(7)->setRowHeight(50); // Header row lebih tinggi
                for ($row = $dataStartRow; $row <= $totalRows; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(80); // Set height lebih tinggi untuk kombinasi data
                }
            }
        ];
    }

    function setHeaderStyle($sheet, $range, $bgColor = 'd8e4bc', $fontSize = 8, $bold = true)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => $bold,
                'size' => $fontSize
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
