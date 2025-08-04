<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RiskExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle, WithStyles
{
    protected $header;

    public function __construct($header)
    {
        $this->header = $header;
    }

    public function array(): array
    {
        $data = [];

        if ($this->header->monthlyData && $this->header->monthlyData->count() > 0) {
            foreach ($this->header->monthlyData as $monthly) {
                $data[] = [
                    $this->header->id ?? '',
                    $this->header->year ?? '-',
                    $this->header->department->name ?? '-',
                    $this->getMonthName($monthly->month),
                    $monthly->status_risiko ?? '',
                    $this->formatDate($monthly->start_date),
                    $this->formatDate($monthly->expired_date),
                    $this->header->riskCode->name ?? '-',
                    $this->header->peristiwa_risiko ?? '-',
                    $this->header->penyebab_risiko ?? '-',
                    $this->header->dampak_risiko ?? '-',
                    $this->header->internal_control ?? '-',
                    $this->header->target_satu_tahun_notes ?? '-',
                    $this->header->target_quantitative_satu_tahun ?? 0,
                    $monthly->realization_quantitative ?? 0, // Tambahan realisasi di sini
                    $this->header->biaya_perlakuan_risiko ?? 0,
                ];
            }
        } else {
            $data[] = [
                $this->header->id ?? '',
                $this->header->year ?? '-',
                $this->header->department->name ?? '-',
                '',
                '',
                '',
                '',
                $this->header->riskCode->name ?? '-',
                $this->header->peristiwa_risiko ?? '-',
                $this->header->penyebab_risiko ?? '-',
                $this->header->dampak_risiko ?? '-',
                $this->header->internal_control ?? '-',
                $this->header->target_satu_tahun_notes ?? '-',
                $this->header->target_quantitative_satu_tahun ?? 0,
                0, // Default realization jika tidak ada monthly
                $this->header->biaya_perlakuan_risiko ?? 0,
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Header ID',
            'Tahun',
            'Department',
            'Bulan',
            'Status Risiko',
            'Start Date',
            'Expired Date',
            'Risk Code',
            'Peristiwa Risiko',
            'Penyebab Risiko',
            'Dampak Risiko',
            'Kontrol Internal',
            'Target 1 Tahun Notes',
            'Target Kuantitatif 1 Tahun',
            'Realisasi Kuantitatif', // Tambahan heading baru
            'Biaya Perlakuan Risiko',
        ];
    }

    public function title(): string
    {
        return 'Laporan Risiko';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFEFEFEF'],
                ],
            ],
        ];
    }

    private function formatDate($date)
    {
        return $date ? \Carbon\Carbon::parse($date)->format('d-m-Y') : '';
    }

    private function getMonthName($month)
    {
        if (!$month) return '';
        return \Carbon\Carbon::create()->month($month)->locale('id')->isoFormat('MMMM');
    }
}
