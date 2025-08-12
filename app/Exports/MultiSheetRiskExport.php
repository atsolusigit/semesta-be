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
    protected $departmentName;

    public function __construct($headers, $monthName = 'Semua Bulan', $year = 2025, $departmentName = null)
    {
        $this->headers = $headers;
        $this->monthName = $monthName;
        $this->year = $year;
        $this->departmentName = $departmentName;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Risk Register (RiskExport)
        $sheets[] = new RiskExport($this->headers, $this->monthName, $this->year, $this->departmentName);

        // Sheet 2: Monitoring Risiko (MONExport)
        $sheets[] = new MONExport($this->headers, $this->monthName, $this->year, $this->departmentName);

        // Sheet 3: Peta Risiko (HMExport)
        $sheets[] = new HMExport($this->headers, $this->monthName, $this->year, $this->departmentName);

        return $sheets;
    }
}
