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

        return [
            ["LAPORAN LOST EVENT"],
            // Hilangkan baris nama departemen
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
        $headerRow = 4; // karena hanya 3 baris header sekarang
        $lastRow = $this->data->count() + $headerRow;

        // Merge dan isi header
        $sheet->mergeCells('A1:Y1');
        $sheet->mergeCells('A2:Y2');

        $sheet->setCellValue('A1', 'LAPORAN LOST EVENT');
        $sheet->setCellValue('A2', 'Tanggal Cetak: ' . now()->format('d/m/Y'));

        $sheet->getStyle('A1:A2')->applyFromArray([
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
            ],
        ]);

        // Border semua sel
        $sheet->getStyle("A{$headerRow}:Y{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Wrap text untuk kolom tertentu
        $wrapCols = ['F', 'H', 'I', 'J', 'K', 'P', 'T', 'U', 'V'];
        foreach ($wrapCols as $col) {
            $sheet->getStyle("{$col}5:{$col}{$lastRow}")->getAlignment()->setWrapText(true);
        }

        // Center alignment
        $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B5:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("Y5:Y{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   'B' => 8,   'C' => 30,  'D' => 20,  'E' => 25,
            'F' => 30,  'G' => 20,  'H' => 30,  'I' => 30,  'J' => 30,
            'K' => 35,  'L' => 20,  'M' => 15,  'N' => 25,  'O' => 35,
            'P' => 30,  'Q' => 18,  'R' => 20,  'S' => 25,  'T' => 35,
            'U' => 30,  'V' => 30,  'W' => 18,  'X' => 18,  'Y' => 12,
        ];
    }

    public function title(): string
    {
        return 'Lost Event Report';
    }
}
