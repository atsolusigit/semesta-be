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
use Maatwebsite\Excel\Concerns\WithTitle;

class HMExport implements FromArray, WithStyles, WithEvents, WithTitle
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

    public function title(): string
    {
        return 'Heat Map '. strtoupper($this->monthName) . ' ' . $this->year;
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
        $data[] = ['', '','', '', '', '', '', '', 'LEVEL RISIKO', 'POSISI RISIKO'];

        // Baris 6: Level dampak
        // $data[] = ['', 'Sangat rendah', 'Rendah', 'Menengah', 'Tinggi', 'Sangat tinggi', '', '', ''];

        // Baris 7: Angka dampak
        // $data[] = ['', '1', '2', '3', '4', '5', '', '', ''];

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

            // Kolom H: Kosong
            $row[] = '';
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
        // $data[] = ['', '', '', '', '', '', '', '', '', '', 'Halaman 3'];

        // Keterangan
        $data[] = [''];
        $data[] = [''];
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
            ],
            6 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            7 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            8 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            9 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            10 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
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
                $sheet->mergeCells('A1:J1'); // PETA RISIKO
                $sheet->mergeCells('A2:J2'); // PT. KBN GRAHA MEDIKA
                $sheet->mergeCells('A3:J3'); // PERIODE

                // Merge DAMPAK header - sesuai gambar
                // $sheet->mergeCells('B5:F5'); // DAMPAK

                // KEMUNGKINAN vertikal merge - akan ditambahkan manual

                // Set column widths sesuai gambar
                $columnWidths = [
                    'A' => 5,  // Kemungkinan descriptions
                    'B' => 25,  // Spacing
                    'C' => 18,  // Level 1
                    'D' => 18,  // Level 2
                    'E' => 18,  // Level 3
                    'F' => 18,  // Level 4
                    'G' => 18,   // level 5
                    'H' => 3,   // Spacing
                    'I' => 18,  // Legend Level
                    'J' => 18,  // Legend Range
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
                    5 => ['C5', 'D5', 'E5', 'F5', 'G5'], // Hampir pasti
                    4 => ['C6', 'D6', 'E6', 'F6', 'G6'], // Sangat mungkin
                    3 => ['C7', 'D7', 'E7', 'F7', 'G7'], // Bisa terjadi
                    2 => ['C8', 'D8', 'E8', 'F8', 'G8'], // Jarang
                    1 => ['C9', 'D9', 'E9', 'F9', 'G9'], // Sangat jarang
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
                $legendCells = ['I5', 'I6', 'I7', 'I8', 'I9'];
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
                $sheet->getStyle('A5:G9')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ]
                ]);

                // Legend table borders
                $sheet->getStyle('I5:J9')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ]
                ]);

                // Center alignment
                $sheet->getStyle('C5:G9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C5:G9')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('I5:J9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('I5:J9')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // Set row heights
                for ($row = 5; $row <= 9; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(50);
                }

                $sheet->getRowDimension(10)->setRowHeight(30);

                //dampak style
                $dampakColumn = ['C10', 'D10', 'E10', 'F10', 'G10'];
                foreach ($dampakColumn as $key => $value) {
                    $sheet->getStyle($value)->applyFromArray([
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

                // dampak & kemungkinan section
                $dampakArr = array(
                    [
                        "column" => "C10",
                        "name" => "Sangat Rendah [1]",
                    ],
                    [
                        "column" => "D10",
                        "name" => "Rendah [2]",
                    ],
                    [
                        "column" => "E10",
                        "name" => "Menengah [3]",
                    ],
                    [
                        "column" => "F10",
                        "name" => "Tinggi [4]",
                    ],
                    [
                        "column" => "G10",
                        "name" => "Sangat Tinggi [5]",
                    ],
                    [
                        "column" => "B5",
                        "name" => "Hampir pasti terjadi [5]",
                    ],
                    [
                        "column" => "B6",
                        "name" => "Sangat mungkin terjadi [4]",
                    ],
                    [
                        "column" => "B7",
                        "name" => "Bisa terjadi [3]",
                    ],
                    [
                        "column" => "B8",
                        "name" => "Jarang terjadi [2]",
                    ],
                    [
                        "column" => "B9",
                        "name" => "Sangat jarang terjadi [1]",
                    ],
                );

                foreach ($dampakArr as $value) {
                    $sheet->setCellValue($value['column'], $value['name']);
                }

                $sheet->mergeCells('C11:G11');
                $sheet->setCellValue('C11', 'DAMPAK');
                $sheet->getStyle('C11:G11')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'E6E6FA']
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
                $sheet->getRowDimension(11)->setRowHeight(20);
                // dampak & kemungkinan section

                //heading level & posisi risiko
                $sheet->getStyle('I4')->applyFromArray([
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

                $sheet->getStyle('J4')->applyFromArray([
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
                //heading level & posisi risiko


                // Merge KEMUNGKINAN vertically (A5:A12)
                $sheet->mergeCells('A5:A9');

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
            5 => [1 => 'C5', 2 => 'D5', 3 => 'E5', 4 => 'F5', 5 => 'G5'],
            4 => [1 => 'C6', 2 => 'D6', 3 => 'E6', 4 => 'F6', 5 => 'G6'],
            3 => [1 => 'C7', 2 => 'D7', 3 => 'E7', 4 => 'F7', 5 => 'G7'],
            2 => [1 => 'C8', 2 => 'D8', 3 => 'E8', 4 => 'F8', 5 => 'G8'],
            1 => [1 => 'C9', 2 => 'D9', 3 => 'E9', 4 => 'F9', 5 => 'G9'],
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
        $sheet->setCellValue('A13', '●');
        $sheet->getStyle('A13')->getFont()->getColor()->setARGB('FF0070C0');

        // Gray dot untuk residual current
        $sheet->setCellValue('A14', '●');
        $sheet->getStyle('A14')->getFont()->getColor()->setARGB('FF7F7F7F');

        // Purple dot untuk residual target
        $sheet->setCellValue('A15', '●');
        $sheet->getStyle('A15')->getFont()->getColor()->setARGB('FF7030A0');
    }
}
