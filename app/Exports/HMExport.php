<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\DB;

class HMExport implements FromArray, WithStyles, WithEvents
{
    protected $headers;
    protected $monthName;
    protected $year;
    protected $heatmapData;
    protected $colorMap = [];
    protected $riskLevelMap = [];

    public function __construct($headers, $monthName = null, $year = null)
    {
        $this->headers = $headers;
        $this->monthName = $monthName ?? 'MARET';
        $this->year = $year ?? date('Y');

        $this->loadHeatmapData();
        $this->loadColorMapping();
        $this->loadRiskLevelMapping();
    }

    private function loadHeatmapData()
    {
        // Load struktur heatmap dari tabel mst_heatmap
        $this->heatmapData = DB::table('mst_heatmap')
            ->select('dampak', 'kemungkinan', 'result')
            ->orderBy('kemungkinan', 'desc')
            ->orderBy('dampak', 'asc')
            ->get();
    }

    private function loadColorMapping()
    {
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

    private function loadRiskLevelMapping()
    {
        $this->riskLevelMap = [
            1 => 'Sangat rendah',
            2 => 'Rendah',
            3 => 'Menengah',
            4 => 'Tinggi',
            5 => 'Sangat tinggi'
        ];
    }

    private function getRiskColor($riskScore)
    {
        if (isset($this->colorMap[$riskScore])) {
            return $this->colorMap[$riskScore]['color'];
        }

        // Fallback colors sesuai gambar
        if ($riskScore <= 5) return '#00B050';      // Low - Green
        if ($riskScore <= 11) return '#92D050';     // Low to Moderate - Light Green
        if ($riskScore <= 15) return '#FFFF00';     // Moderate - Yellow
        if ($riskScore <= 19) return '#FFC000';     // Moderate to High - Orange
        return '#FF0000';                           // High - Red
    }

    private function countRisksByLevel($riskType)
    {
        $counts = [];

        foreach ($this->headers as $header) {
            $dampak = 0;
            $kemungkinan = 0;

            switch ($riskType) {
                case 'inherent':
                    $dampak = $header->inherent_risk_level_dampak ?? 0;
                    $kemungkinan = $header->inherent_risk_level_kemungkinan ?? 0;
                    break;

                case 'residual_current':
                    $monthly = $header->monthlyData->first();
                    if ($monthly) {
                        $dampak = $monthly->residual_risk_level_dampak ?? 0;
                        $kemungkinan = $monthly->residual_risk_level_kemungkinan ?? 0;
                    }
                    break;

                case 'residual_target':
                    $dampak = $header->residual_target_level_dampak ?? 0;
                    $kemungkinan = $header->residual_target_level_kemungkinan ?? 0;
                    break;
            }

            if ($dampak > 0 && $kemungkinan > 0) {
                $key = $kemungkinan . '_' . $dampak;
                if (!isset($counts[$key])) {
                    $counts[$key] = 0;
                }
                $counts[$key]++;
            }
        }

        return $counts;
    }

    public function array(): array
    {
        $data = [];

        // Header utama - sesuai gambar
        $data[] = ['PETA RISIKO'];
        $data[] = ['PT. KBN GRAHA MEDIKA'];
        $data[] = ["PERIODE : {$this->monthName} {$this->year}"];
        $data[] = []; // Empty row

        // Baris 5: Header dengan DAMPAK di tengah
        $data[] = ['', '', 'DAMPAK', '', '', '', '', 'LEVEL RISIKO', 'POSISI RISIKO'];

        // Baris 6: Level dampak
        $data[] = ['', 'Sangat rendah', 'Rendah', 'Menengah', 'Tinggi', 'Sangat tinggi', '', '', ''];

        // Baris 7: Angka dampak
        $data[] = ['', '1', '2', '3', '4', '5', '', '', ''];

        // Baris 8-12: Data heatmap dengan kemungkinan di kiri
        $kemungkinanLabels = [
            ['Hampir pasti terjadi', '5'],
            ['Sangat mungkin terjadi', '4'],
            ['Bisa terjadi', '3'],
            ['Jarang terjadi', '2'],
            ['Sangat jarang terjadi', '1']
        ];

        // Legend data untuk kolom I-J
        $legends = [
            ['Low', '1 - 5'],
            ['Low to Moderate', '6 - 11'],
            ['Moderate', '12 - 15'],
            ['Moderate to High', '16 - 19'],
            ['High', '20 - 25']
        ];

        foreach ($kemungkinanLabels as $index => $prob) {
            $row = [];

            // Kolom A: Label kemungkinan
            $row[] = $prob[0];

            // Kolom B-F: Cells heatmap (akan diisi dengan risk score dan dots)
            for ($i = 1; $i <= 5; $i++) {
                $row[] = ''; // Akan diisi di registerEvents
            }

            // Kolom G: Kosong
            $row[] = '';

            // Kolom H-I: Legend
            if (isset($legends[$index])) {
                $row[] = $legends[$index][0];
                $row[] = $legends[$index][1];
            } else {
                $row[] = '';
                $row[] = '';
            }

            $data[] = $row;
        }

        // Spacing dan halaman
        $data[] = [];
        $data[] = [];
        $data[] = ['', '', '', '', '', '', '', '', '', '', 'Halaman 3'];

        // Keterangan
        $data[] = [];
        $data[] = ['Keterangan :'];
        $data[] = ['', ': Inherent Risk'];
        $data[] = ['', ": Residual Current Risk s.d. 31 {$this->monthName} {$this->year}"];
        $data[] = ['', ": Residual Current Risk (Residual saat ini realisasi berbanding dengan target {$this->year})."];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header utama
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            3 => [
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],

            // Header tabel
            5 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => 'E6E6FA']
                ]
            ],
            6 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            7 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],

            // Keterangan
            16 => [
                'font' => ['bold' => true]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Merge cells untuk header
                $sheet->mergeCells('A1:I1'); // PETA RISIKO
                $sheet->mergeCells('A2:I2'); // PT. KBN GRAHA MEDIKA
                $sheet->mergeCells('A3:I3'); // PERIODE

                // Merge DAMPAK header - sesuai gambar
                $sheet->mergeCells('B5:F5'); // DAMPAK

                // KEMUNGKINAN vertikal merge - akan ditambahkan manual

                // Set column widths sesuai gambar
                $columnWidths = [
                    'A' => 25,  // Kemungkinan descriptions
                    'B' => 12,  // Level 1
                    'C' => 12,  // Level 2
                    'D' => 12,  // Level 3
                    'E' => 12,  // Level 4
                    'F' => 12,  // Level 5
                    'G' => 3,   // Spacing
                    'H' => 18,  // Legend Level
                    'I' => 12,  // Legend Range
                ];

                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                // Get heatmap data matrix
                $heatmapMatrix = [];
                foreach ($this->heatmapData as $item) {
                    $heatmapMatrix[$item->kemungkinan][$item->dampak] = $item->result;
                }

                // Apply heatmap data dan styling (B8:F12)
                $cellMapping = [
                    5 => ['B8', 'C8', 'D8', 'E8', 'F8'], // Hampir pasti
                    4 => ['B9', 'C9', 'D9', 'E9', 'F9'], // Sangat mungkin
                    3 => ['B10', 'C10', 'D10', 'E10', 'F10'], // Bisa terjadi
                    2 => ['B11', 'C11', 'D11', 'E11', 'F11'], // Jarang
                    1 => ['B12', 'C12', 'D12', 'E12', 'F12'], // Sangat jarang
                ];

                foreach ($cellMapping as $kemungkinan => $cells) {
                    foreach ($cells as $index => $cellRef) {
                        $impact = $index + 1;
                        $riskScore = $heatmapMatrix[$kemungkinan][$impact] ?? 0;

                        // Set risk score
                        $sheet->setCellValue($cellRef, $riskScore);

                        // Apply color
                        $color = $this->getRiskColor($riskScore);
                        $sheet->getStyle($cellRef)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['argb' => 'FF000000'],
                                ],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER
                            ],
                            'font' => ['bold' => true, 'size' => 11]
                        ]);
                    }
                }

                // Apply colors to legend (H8:H12)
                $legendCells = ['H8', 'H9', 'H10', 'H11', 'H12'];
                $legendScores = [4, 7, 13, 17, 22]; // Representative scores

                foreach ($legendCells as $index => $cell) {
                    $color = $this->getRiskColor($legendScores[$index]);
                    $sheet->getStyle($cell)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF' . ltrim($color, '#')],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ]
                    ]);
                }

                // Apply borders ke seluruh tabel
                $sheet->getStyle('A5:F12')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ]
                ]);

                // Legend table borders
                $sheet->getStyle('H5:I12')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ]
                ]);

                // Center alignment
                $sheet->getStyle('B5:F12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B5:F12')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('H5:I12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H5:I12')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // Set row heights
                for ($row = 8; $row <= 12; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(50);
                }

                // Add KEMUNGKINAN label manually di sebelah kiri
                $sheet->setCellValue('A5', 'KEMUNGKINAN');
                $sheet->getStyle('A5')->getAlignment()->setTextRotation(90);
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A5')->getFont()->setBold(true);

                // Apply background untuk KEMUNGKINAN label
                $sheet->getStyle('A5')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'E6E6FA']
                    ]
                ]);

                // Merge KEMUNGKINAN vertically (A5:A12)
                $sheet->mergeCells('A5:A12');

                // Add risk counts/dots
                $this->addRiskCounts($sheet);

                // Add legend symbols
                $this->addLegendSymbols($sheet);
            }
        ];
    }

    private function addRiskCounts($sheet)
    {
        $inherentCounts = $this->countRisksByLevel('inherent');
        $residualCurrentCounts = $this->countRisksByLevel('residual_current');
        $residualTargetCounts = $this->countRisksByLevel('residual_target');

        $cellMapping = [
            5 => [1 => 'B8', 2 => 'C8', 3 => 'D8', 4 => 'E8', 5 => 'F8'],
            4 => [1 => 'B9', 2 => 'C9', 3 => 'D9', 4 => 'E9', 5 => 'F9'],
            3 => [1 => 'B10', 2 => 'C10', 3 => 'D10', 4 => 'E10', 5 => 'F10'],
            2 => [1 => 'B11', 2 => 'C11', 3 => 'D11', 4 => 'E11', 5 => 'F11'],
            1 => [1 => 'B12', 2 => 'C12', 3 => 'D12', 4 => 'E12', 5 => 'F12'],
        ];

        foreach ($cellMapping as $kemungkinan => $impacts) {
            foreach ($impacts as $impact => $cellRef) {
                $key = $kemungkinan . '_' . $impact;

                $inherentCount = $inherentCounts[$key] ?? 0;
                $currentCount = $residualCurrentCounts[$key] ?? 0;
                $targetCount = $residualTargetCounts[$key] ?? 0;

                $currentValue = $sheet->getCell($cellRef)->getValue();
                $displayText = $currentValue;

                if ($inherentCount > 0 || $currentCount > 0 || $targetCount > 0) {
                    $displayText .= "\n";

                    if ($inherentCount > 0) {
                        $displayText .= str_repeat('●', min($inherentCount, 10)) . " ";
                    }

                    if ($currentCount > 0) {
                        $displayText .= str_repeat('●', min($currentCount, 10)) . " ";
                    }

                    if ($targetCount > 0) {
                        $displayText .= str_repeat('●', min($targetCount, 10));
                    }
                }

                $sheet->setCellValue($cellRef, trim($displayText));
                $sheet->getStyle($cellRef)->getAlignment()->setWrapText(true);
            }
        }
    }

    private function addLegendSymbols($sheet)
    {
        // Blue dot untuk inherent
        $sheet->setCellValue('A17', '●');
        $sheet->getStyle('A17')->getFont()->getColor()->setARGB('FF0070C0');

        // Gray dot untuk residual current
        $sheet->setCellValue('A18', '●');
        $sheet->getStyle('A18')->getFont()->getColor()->setARGB('FF7F7F7F');

        // Purple dot untuk residual target
        $sheet->setCellValue('A19', '●');
        $sheet->getStyle('A19')->getFont()->getColor()->setARGB('FF7030A0');
    }
}
