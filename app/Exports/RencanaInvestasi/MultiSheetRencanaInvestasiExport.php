<?php
namespace App\Exports\RencanaInvestasi;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Collection;

class MultiSheetRencanaInvestasiExport implements WithMultipleSheets
{
    protected Collection $rows;
    protected int $year;
    protected int $month;
    protected int $week;
    protected ?string $departmentName;
    protected string $monthName;

    public function __construct($flatRows, int $year, int $month, int $week, ?string $departmentName, string $monthName)
    {
        $this->rows = collect($flatRows)->map(fn($r) => (array)$r)->values();
        $this->year = $year;
        $this->month = $month;
        $this->week = $week;
        $this->departmentName = $departmentName;
        $this->monthName = $monthName;
    }

    public function sheets(): array
    {
        return [
            new RIRegisterExport($this->rows, $this->departmentName, $this->monthName, $this->year),
            new RITimelineExport($this->rows, $this->year, $this->month, $this->week, $this->departmentName, $this->monthName),
        ];
    }
}
