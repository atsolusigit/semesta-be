<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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

class RiskExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithStyles, WithEvents
{
    protected $headers;
    protected $monthName;
    protected $year;
    protected $colorMap = [];

    public function __construct($headers, $monthName = 'Semua Bulan', $year = 2025)
    {
        $this->headers = $headers;
        $this->monthName = $monthName;
        $this->year = $year;

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
                    'realization_quantitative' => 0,
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
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;
                $monthlyData = $monthly;
            }

            $data[] = [
                // DEBUG COLUMNS - Keep for now
                $header->id, // Header ID
                $monthlyData->id ?? 'NO_MONTHLY', // Monthly ID

                $no++, // No
                $header->risk_code ?? '', // Kode Risiko
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
                $target, // Target Bulan
                $realization, // Realisasi Bulan
                $percentage . '%', // Persentase Bulan

                // Residual Risk Saat Ini (4 kolom)
                $monthlyData->residual_risk_level_dampak ?? '',
                $monthlyData->residual_risk_level_kemungkinan ?? '',
                $monthlyData->residual_risk_posisi_risiko ?? '',
                $monthlyData->residual_risk_level_risiko ?? '',

                $header->target_quantitative_satu_tahun ?? 0, // Target 1 Tahun
                $realization, // Realisasi (duplicate)

                // Residual Target Risk (4 kolom)
                $header->residual_target_level_dampak ?? '',
                $header->residual_target_level_kemungkinan ?? '',
                $header->residual_target_posisi_risiko ?? '',
                $header->residual_target_level_risiko ?? '',

                $monthlyData->realization_note ?? '', // Perlakuan Risiko
                $header->biaya_perlakuan_risiko ?? 0, // Biaya Perlakuan

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
            [
                // DEBUG COLUMNS - Keep for now
                'Header ID',
                'Monthly ID',

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
        return 'Risk Register ' . strtoupper($this->monthName) . ' ' . $this->year;
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
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ],
            4 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ],
            // Table header
            6 => [
                'font' => ['bold' => true, 'size' => 8], // Font lebih kecil untuk header
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE6E6FA'],
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
                $sheet->mergeCells('A1:AJ1'); // KERTAS KERJA RISK REGISTER (updated untuk include debug columns)
                $sheet->mergeCells('A2:AJ2'); // PT. KAWASAN BERIKAT NUSANTARA

                // Set column widths - disesuaikan dengan gambar
                $columnWidths = [
                    // DEBUG COLUMNS
                    'A' => 8,   // Header ID
                    'B' => 10,  // Monthly ID

                    // MAIN COLUMNS
                    'C' => 4,   // NO (lebih kecil)
                    'D' => 6,   // KODE RISIKO (lebih kecil)
                    'E' => 10,  // JENIS RISIKO
                    'F' => 25,  // SASARAN (lebih lebar)
                    'G' => 25,  // PERISTIWA RISIKO (lebih lebar)
                    'H' => 25,  // PENYEBAB RISIKO (lebih lebar)
                    'I' => 25,  // DAMPAK RISIKO (lebih lebar)

                    // INHERENT RISK
                    'J' => 6,   // DAMPAK
                    'K' => 8,   // KEMUNGKINAN
                    'L' => 6,   // POSISI
                    'M' => 8,   // LEVEL

                    'N' => 30,  // INTERNAL CONTROL (lebih lebar)
                    'O' => 12,  // TARGET BULAN
                    'P' => 12,  // REALISASI BULAN
                    'Q' => 5,   // % (lebih kecil)

                    // RESIDUAL RISK SAAT INI
                    'R' => 6,   // DAMPAK
                    'S' => 8,   // KEMUNGKINAN
                    'T' => 6,   // POSISI
                    'U' => 8,   // LEVEL

                    'V' => 12,  // TARGET 1 TAHUN
                    'W' => 12,  // REALISASI

                    // RESIDUAL TARGET RISK
                    'X' => 6,   // DAMPAK
                    'Y' => 8,   // KEMUNGKINAN
                    'Z' => 6,   // POSISI
                    'AA' => 8,  // LEVEL

                    'AB' => 35, // PERLAKUAN RISIKO (sangat lebar untuk wrap text)
                    'AC' => 12, // BIAYA PERLAKUAN

                    // RESIDUAL TARGET RISK (duplicate)
                    'AD' => 6,  // DAMPAK
                    'AE' => 8,  // KEMUNGKINAN
                    'AF' => 6,  // POSISI
                    'AG' => 8,  // LEVEL

                    'AH' => 12, // STATUS RISIKO
                ];

                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                // Apply colors to risk level cells (update column references)
                $dataStartRow = 7;
                $totalRows = count($this->headers) + $dataStartRow - 1;

                for ($row = $dataStartRow; $row <= $totalRows; $row++) {
                    $dataIndex = $row - $dataStartRow;
                    if (isset($this->headers[$dataIndex])) {
                        $header = $this->headers[$dataIndex];
                        $monthly = $header->monthlyData->first();

                        // Color Inherent Risk Level (column M - adjusted for debug columns)
                        $inherentLevel = $header->inherent_risk_level_risiko ?? '';
                        if ($inherentLevel) {
                            $color = $this->getRiskColor($inherentLevel);
                            $sheet->getStyle('M' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                        }

                        // Color Residual Risk Level Saat Ini (column U - adjusted for debug columns)
                        if ($monthly) {
                            $residualLevel = $monthly->residual_risk_level_risiko ?? '';
                            if ($residualLevel) {
                                $color = $this->getRiskColor($residualLevel);
                                $sheet->getStyle('U' . $row)->applyFromArray([
                                    'fill' => [
                                        'fillType' => Fill::FILL_SOLID,
                                        'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                    ],
                                ]);
                            }
                        }

                        // Color Residual Target Risk (column AA & AG - adjusted for debug columns)
                        $targetLevel = $header->residual_target_level_risiko ?? '';
                        if ($targetLevel) {
                            $color = $this->getRiskColor($targetLevel);
                            $sheet->getStyle('AA' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                            $sheet->getStyle('AG' . $row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                                ],
                            ]);
                        }
                    }
                }

                // Apply borders to all data (update range for debug columns)
                $sheet->getStyle('A6:AH' . $totalRows)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Set text alignment for data rows with STRONG wrap text setting
                $sheet->getStyle('A' . $dataStartRow . ':AH' . $totalRows)->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'wrapText' => true, // PENTING: Pastikan wrap text aktif
                        'shrinkToFit' => false // Jangan shrink, tapi wrap
                    ],
                    'font' => ['size' => 9]
                ]);

                // Center align untuk kolom tertentu saja
                $sheet->getStyle('A' . $dataStartRow . ':C' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Debug + NO
                $sheet->getStyle('O' . $dataStartRow . ':Q' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Target, Realisasi, %
                $sheet->getStyle('V' . $dataStartRow . ':W' . $totalRows)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Target 1 Tahun, Realisasi

                // Khusus untuk kolom dengan teks panjang, pastikan wrap text dan height otomatis
                $longTextColumns = ['F', 'G', 'H', 'I', 'N', 'AB']; // Sasaran, Peristiwa, Penyebab, Dampak, Internal Control, Perlakuan Risiko
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

                // Set row heights
                $sheet->getRowDimension(6)->setRowHeight(50); // Header row lebih tinggi
                for ($row = $dataStartRow; $row <= $totalRows; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(60); // Set minimum height untuk wrap text
                }
            }
        ];
    }
}
