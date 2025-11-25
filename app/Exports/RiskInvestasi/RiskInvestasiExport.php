<?php

namespace App\Exports\RiskInvestasi;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RiskInvestasiExport implements FromCollection, WithHeadings
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->rows->isEmpty()) {
            return [];
        }

        return array_keys($this->rows->first());
    }
}
