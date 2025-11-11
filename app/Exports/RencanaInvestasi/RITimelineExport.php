<?php

namespace App\Exports\RencanaInvestasi;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RITimelineExport implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $rows;
    protected int $year;
    protected int $month;
    protected int $week;
    protected string $departmentName;
    protected string $monthName;

    public function __construct(Collection $flatRows, int $year, int $month, int $week, ?string $departmentName, string $monthName)
    {
        $this->rows = $flatRows->values();
        $this->year = $year;
        $this->month = $month;
        $this->week = max(1, min($week, 4));
        $this->departmentName = $departmentName ?: 'All-Dept';
        $this->monthName = $monthName;
    }

    public function collection()
    {
        return $this->rows->map(function (array $r) {
            return [
                'ERKAP ID'                 => $r['ERKAP ID'] ?? null,
                'Department'               => $r['Department'] ?? null,
                'Nama Investasi'           => $r['Nama Investasi'] ?? null,
                'Target Timeline Label'    => $r['Target Timeline Label'] ?? null,
                'Target Timeline Color'    => $r['Target Timeline Color'] ?? null,
                'Realisasi Timeline Label' => $r['Realisasi Timeline Label'] ?? null,
                'Realisasi Timeline Color' => $r['Realisasi Timeline Color'] ?? null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ERKAP ID','Department','Nama Investasi',
            'Target Timeline Label','Target Timeline Color',
            'Realisasi Timeline Label','Realisasi Timeline Color'
        ];
    }

    public function title(): string
    {
        return "Timeline {$this->monthName} {$this->year}";
    }
}
