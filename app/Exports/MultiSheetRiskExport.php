<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiSheetRiskExport implements WithMultipleSheets
{
    use Exportable;

    protected $headers;
    protected $monthName;
    protected $year;

    public function __construct($headers, $monthName = 'Semua Bulan', $year = 2025)
    {
        $this->headers = $headers;
        $this->monthName = $monthName;
        $this->year = $year;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Risk Register (RiskExport)
        $sheets[] = new RiskExport($this->headers, $this->monthName, $this->year);

        // Sheet 2: Monitoring Risiko (Export2)
        $sheets[] = new Export2($this->headers, $this->monthName, $this->year);

        // Sheet 3: Peta Risiko (Export3)
        $sheets[] = new Export3($this->headers, $this->monthName, $this->year);

        return $sheets;
    }
}
