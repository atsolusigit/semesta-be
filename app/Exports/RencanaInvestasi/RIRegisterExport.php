<?php

namespace App\Exports\RencanaInvestasi;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RIRegisterExport implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $rows;
    protected string $departmentName;
    protected string $monthName;
    protected int $year;

    public function __construct(Collection $flatRows, ?string $departmentName, string $monthName, int $year)
    {
        $this->rows = $flatRows->values();
        $this->departmentName = $departmentName ?: 'All-Dept';
        $this->monthName = $monthName;
        $this->year = $year;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return array_keys($this->rows->first() ?? []);
    }

    public function title(): string
    {
        return "Rencana Investasi {$this->monthName} {$this->year}";
    }
}

