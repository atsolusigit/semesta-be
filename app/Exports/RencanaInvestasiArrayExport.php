<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RencanaInvestasiArrayExport implements FromArray, WithHeadings, ShouldAutoSize
{
    protected array $rows;
    protected array $headings;

    public function __construct(array $rows, array $headings)
    {
        $this->rows = $rows;
        $this->headings = $headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
