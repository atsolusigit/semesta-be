<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LostEventExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected $data;
    protected $year;
    protected $departmentName;

    public function __construct($data, $year, $departmentName)
    {
        $this->data = collect($data);
        $this->year = $year;
        $this->departmentName = $departmentName;
    }

    public function collection()
    {
        return $this->data->map(function ($item) {
            return [
                $item['no'],
                $item['tahun'],
                $item['risk_owner_department'],
                $item['jenis_risiko'],
                $item['nama_kejadian'],
                $item['identifikasi_kejadian'],
                $item['kategori_kejadian'],
                $item['sumber_penyebab_kejadian'],
                $item['penyebab_kejadian'],
                $item['penanganan_saat_kejadian'],
                $item['deskripsi_kejadian'],
                $item['pihak_terkait'],
                $item['status_asuransi'],
                $item['kategori_risiko_bumn'],
                $item['kategori_risiko_t2_t3_kbumn'],
                $item['penjelasan_kerugian'],
                $item['nilai_kerugian_formatted'],
                $item['kejadian_berulang'],
                $item['frekuensi_kejadian'],
                $item['mitigasi_yang_direncanakan'],
                $item['realisasi_mitigasi'],
                $item['perbaikan_mendatang'],
                $item['nilai_premi_formatted'],
                $item['nilai_klaim_formatted'],
                $item['realization_percentage'],
            ];
        });
    }

    public function headings(): array
    {
        $tanggalCetak = now()->format('d/m/Y');
        $departmentDisplay = str_replace('_', ' ', $this->departmentName);

        return [
            ["LAPORAN LOST EVENT"],
            [$departmentDisplay],
            ["Tanggal Cetak: {$tanggalCetak}"],
            [],
            [
                'No',
                'Tahun',
                'Risk Owner Department',
                'Jenis Risiko',
                'Nama Kejadian',
                'Identifikasi Kejadian',
                'Kategori Kejadian',
                'Sumber Penyebab Kejadian',
                'Penyebab Kejadian',
                'Penanganan Saat Kejadian',
                'Deskripsi Kejadian',
                'Pihak Terkait',
                'Status Asuransi',
                'Kategori Risiko BUMN',
                'Kategori Risiko T2 & T3 KBUMN',
                'Penjelasan Kerugian',
                'Nilai Kerugian',
                'Kejadian Berulang',
                'Frekuensi Kejadian',
                'Mitigasi Yang Direncanakan',
                'Realisasi Mitigasi',
                'Perbaikan Mendatang',
                'Nilai Premi',
                'Nilai Klaim',
                'Realisasi (%)',
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow = 5; // Berubah dari 4 menjadi 5 karena ada tambahan baris department
        $lastRow = $this->data->count() + $headerRow;
        $departmentDisplay = str_replace('_', ' ', $this->departmentName);

        // Merge dan isi header
        $sheet->mergeCells('A1:Y1');
        $sheet->mergeCells('A2:Y2');
        $sheet->mergeCells('A3:Y3');

        $sheet->setCellValue('A1', 'LAPORAN LOST EVENT');
        $sheet->setCellValue('A2', $departmentDisplay);
        $sheet->setCellValue('A3', 'Tanggal Cetak: ' . now()->format('d/m/Y'));

        // Style untuk baris 1, 2, 3
        $sheet->getStyle('A1:A3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Style header kolom
        $sheet->getStyle("A{$headerRow}:Y{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Set minimum row height untuk header
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Border semua sel
        $sheet->getStyle("A{$headerRow}:Y{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Wrap text untuk SEMUA kolom data (kecuali No)
        $sheet->getStyle("B6:Y{$lastRow}")->getAlignment()->setWrapText(true);

        // Vertical alignment untuk semua data
        $sheet->getStyle("A6:Y{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // Center alignment untuk kolom tertentu
        $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B6:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("Y6:Y{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set minimum row height untuk setiap baris data agar text tidak terpotong
        for ($row = 6; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(-1); // Auto height
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 8,   // Tahun
            'C' => 30,  // Risk Owner Department
            'D' => 25,  // Jenis Risiko
            'E' => 30,  // Nama Kejadian
            'F' => 35,  // Identifikasi Kejadian
            'G' => 25,  // Kategori Kejadian
            'H' => 35,  // Sumber Penyebab Kejadian
            'I' => 35,  // Penyebab Kejadian
            'J' => 35,  // Penanganan Saat Kejadian
            'K' => 40,  // Deskripsi Kejadian
            'L' => 25,  // Pihak Terkait
            'M' => 20,  // Status Asuransi
            'N' => 30,  // Kategori Risiko BUMN
            'O' => 40,  // Kategori Risiko T2 & T3 KBUMN
            'P' => 35,  // Penjelasan Kerugian
            'Q' => 20,  // Nilai Kerugian
            'R' => 20,  // Kejadian Berulang
            'S' => 20,  // Frekuensi Kejadian
            'T' => 40,  // Mitigasi Yang Direncanakan
            'U' => 35,  // Realisasi Mitigasi
            'V' => 35,  // Perbaikan Mendatang
            'W' => 20,  // Nilai Premi
            'X' => 20,  // Nilai Klaim
            'Y' => 12,  // Realisasi (%)
        ];
    }

    public function title(): string
    {
        return 'Lost Event Report';
    }
}
