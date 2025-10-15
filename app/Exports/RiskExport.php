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
                'note_recommendation' => '',
            ];
        } else {
            $target = $monthly->target_quantitative ?? 0;
            $realization = $monthly->realization_quantitative ?? 0;

            if (is_numeric($target) && is_numeric($realization) && floatval($target) > 0) {
                $percentage = round((floatval($realization) / floatval($target)) * 100, 2);
            } else {
                $percentage = 0;
            }

            $monthlyData = $monthly;
        }

        $riskCode = $this->getRiskCodeName($header);

        $data[] = [
            $no++,
            $riskCode,
            $header->jenis_risiko ?? '',
            $header->sasaran ?? '',
            $header->peristiwa_risiko ?? '',
            $header->penyebab_risiko ?? '',
            $header->dampak_risiko ?? '',
            $header->inherent_risk_level_dampak ?? '',
            $header->inherent_risk_level_kemungkinan ?? '',
            $header->inherent_risk_posisi_risiko ?? '',
            $header->inherent_risk_level_risiko ?? '',
            $header->internal_control ?? '',
            $this->formatTargetBulan($monthlyData),
            $this->formatRealizationBulan($monthlyData),
            $percentage . '%',
            $monthlyData->residual_risk_level_dampak ?? '',
            $monthlyData->residual_risk_level_kemungkinan ?? '',
            $monthlyData->residual_risk_posisi_risiko ?? '',
            $monthlyData->residual_risk_level_risiko ?? '',
            $this->formatTarget($header->target_quantitative_satu_tahun ?? 0, $header->target_kualitatif_satu_tahun ?? ''),
            $this->formatRealizationBulan($monthlyData),
            $header->residual_target_level_dampak ?? '',
            $header->residual_target_level_kemungkinan ?? '',
            $header->residual_target_posisi_risiko ?? '',
            $header->residual_target_level_risiko ?? '',
            $header->mitigasi ?? '',
            $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),
            $header->residual_target_level_dampak ?? '',
            $header->residual_target_level_kemungkinan ?? '',
            $header->residual_target_posisi_risiko ?? '',
            $header->residual_target_level_risiko ?? '',
            $monthlyData->status_risiko ?? '',
            $monthlyData->note_recommendation ?? '',
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
        [],
        [],
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
            'REKOMENDASI', // TAMBAHAN BARU - kolom AG
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

   // Method untuk set style header tertentu
    private function getRiskColorByPosition($posisi)
    {
    // Convert posisi ke integer
    $posisi = (int)$posisi;

    // Cek di colorMap yang sudah di-load dari database
    if (isset($this->colorMap[$posisi])) {
        return $this->colorMap[$posisi]['color'];
    }

    // Fallback manual berdasarkan range dari database
    if ($posisi >= 1 && $posisi <= 5) {
        return '#00B050'; // Low - Hijau
    } elseif ($posisi >= 6 && $posisi <= 11) {
        return '#62b334'; // Low To Moderate - Hijau Muda
    } elseif ($posisi >= 12 && $posisi <= 15) {
        return '#d2da15'; // Moderate - Kuning
    } elseif ($posisi >= 16 && $posisi <= 19) {
        return '#FFC000'; // Moderate To High - Orange
    } elseif ($posisi >= 20 && $posisi <= 25) {
        return '#FF0000'; // High - Merah
    }

    // Default putih jika tidak ada match
    return '#FFFFFF';
}

// Method untuk set style header
public function registerEvents(): array
{
    return [
        AfterSheet::class => function(AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();

            // Merge cells untuk header
            $sheet->mergeCells('A1:AG1');
            $sheet->mergeCells('A2:AG2');
            $sheet->mergeCells('A3:AG3');
            $sheet->mergeCells('A4:AG4');

            // merge header horizontal
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

            // merge header vertical
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
                [
                    "column" => "AG",
                    "name" => "REKOMENDASI"
                ],
            );

            foreach ($mergeVertical as $value) {
                $sheet->mergeCells($value['column'].'6:'.$value['column'].'7');
                $sheet->setCellValue($value['column'].'6', $value['name']);
                $this->setHeaderStyle($sheet, $value['column'].'6:'.$value['column'].'7', 'd8e4bc', 8, true);
            }

            // Set column widths
            $columnWidths = [
                'A' => 4,
                'B' => 15,
                'C' => 20,
                'D' => 25,
                'E' => 25,
                'F' => 25,
                'G' => 25,
                'H' => 6,
                'I' => 8,
                'J' => 6,
                'K' => 8,
                'L' => 30,
                'M' => 15,
                'N' => 15,
                'O' => 5,
                'P' => 6,
                'Q' => 8,
                'R' => 6,
                'S' => 8,
                'T' => 15,
                'U' => 15,
                'V' => 6,
                'W' => 8,
                'X' => 6,
                'Y' => 8,
                'Z' => 35,
                'AA' => 12,
                'AB' => 6,
                'AC' => 8,
                'AD' => 6,
                'AE' => 8,
                'AF' => 12,
                'AG' => 35,
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            // ===== PERBAIKAN UTAMA: Apply colors berdasarkan POSISI RISIKO =====
            $dataStartRow = 8;
            $totalRows = count($this->headers) + $dataStartRow - 1;
            for ($row = $dataStartRow; $row <= $totalRows; $row++) {
                $dataIndex = $row - $dataStartRow;
                if (isset($this->headers[$dataIndex])) {
                    $header = $this->headers[$dataIndex];
                    $monthly = $header->monthlyData->first();

                    // Color Inherent Risk Level (column K) - GUNAKAN POSISI RISIKO
                    $inherentPosisi = $header->inherent_risk_posisi_risiko ?? 0;
                    if ($inherentPosisi > 0) {
                        $color = $this->getRiskColorByPosition($inherentPosisi);
                        $sheet->getStyle('K' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                            ],
                        ]);
                    }

                    // Color Residual Risk Level Saat Ini (column S) - GUNAKAN POSISI RISIKO
                    if ($monthly) {
                        $residualPosisi = $monthly->residual_risk_posisi_risiko ?? 0;
                        if ($residualPosisi > 0) {
                            $color = $this->getRiskColorByPosition($residualPosisi);
                            $sheet->getStyle('S' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                        }
                    }

                    // Color Residual Target Risk (column Y & AE) - GUNAKAN POSISI RISIKO
                    $targetPosisi = $header->residual_target_posisi_risiko ?? 0;
                    if ($targetPosisi > 0) {
                        $color = $this->getRiskColorByPosition($targetPosisi);
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

            // Apply borders
            $sheet->getStyle('A6:AG' . $totalRows)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
            $sheet->getStyle('A7:AG' . $totalRows)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);

            // Set text alignment
            $sheet->getStyle('A' . $dataStartRow . ':AG' . $totalRows)->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'wrapText' => true,
                    'shrinkToFit' => false
                ],
                'font' => ['size' => 9]
            ]);

            $sheet->getStyle('A' . $dataStartRow . ':C' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('O' . $dataStartRow . ':Q' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('V' . $dataStartRow . ':W' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Kolom dengan teks panjang
            $longTextColumns = ['D', 'E', 'F', 'G', 'L', 'Z', 'AG'];
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

            $combinedColumns = ['M', 'N', 'T', 'U'];
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

            $sheet->getStyle('B' . $dataStartRow . ':B' . $totalRows)->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'wrapText' => true,
                    'shrinkToFit' => false
                ]
            ]);

            $sheet->getRowDimension(7)->setRowHeight(50);
            for ($row = $dataStartRow; $row <= $totalRows; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(80);
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

