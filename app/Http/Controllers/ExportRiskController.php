<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MultiSheetRiskExport;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ExportRiskController extends Controller
{
    public function export(Request $request, $format)
    {
        // Validasi format yang didukung
        if (!in_array($format, ['excel', 'pdf'])) {
            return response()->json([
                'status' => 400,
                'success' => false,
                'message' => 'Format tidak didukung',
                'data' => 'Format yang didukung: excel, pdf'
            ], 400);
        }

        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');
        $filterDepartment = $request->get('department_id');

        // Normalisasi filter bulan
        if (is_array($filterMonth) && isset($filterMonth['month'])) {
            $filterMonth = (int) $filterMonth['month'];
        } elseif (!is_null($filterMonth)) {
            $filterMonth = (int) $filterMonth;
        }

        // Normalisasi filter department
        if (!is_null($filterDepartment)) {
            $filterDepartment = (int) $filterDepartment;
        }

        // Ambil data header dengan semua field yang diperlukan untuk semua export
        $headers = TrRiskHeader::with([
            'department:id,name',
            'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                $q->select([
                    'id',
                    'header_id',
                    'target_quantitative',
                    'target_option',
                    'realization_quantitative',
                    'realization_option',
                    'residual_risk_level_dampak',
                    'residual_risk_level_kemungkinan',
                    'residual_risk_posisi_risiko',
                    'residual_risk_level_risiko',
                    'realization_note',
                    'status_risiko',
                    'start_date',
                    'month'
                ]);

                if (!empty($filterYear)) {
                    $q->whereYear('start_date', $filterYear);
                }
                if (!empty($filterMonth)) {
                    $q->where('month', $filterMonth);
                }
            },
        ])
        ->select([
            'id',
            'risk_code',
            'jenis_risiko',
            'sasaran',
            'peristiwa_risiko',
            'penyebab_risiko',
            'dampak_risiko',
            'department_id',
            // Inherent risk fields
            'inherent_risk_level_dampak',
            'inherent_risk_level_kemungkinan',
            'inherent_risk_posisi_risiko',
            'inherent_risk_level_risiko',
            // Residual target risk fields
            'residual_target_level_dampak',
            'residual_target_level_kemungkinan',
            'residual_target_posisi_risiko',
            'residual_target_level_risiko',
            // Other fields
            'internal_control',
            'target_quantitative_satu_tahun',
            'target_satu_tahun_notes',
            'target_satu_tahun_option',
            'mitigasi',
            'biaya_perlakuan_risiko'
        ])
        // Filter berdasarkan department jika ada
        ->when(!is_null($filterDepartment), function ($query) use ($filterDepartment) {
            $query->where('department_id', $filterDepartment);
        })
        // Filter berdasarkan department user jika bukan admin (opsional)
        ->when($this->shouldFilterByUserDepartment(), function ($query) {
            $query->where('department_id', Auth::user()->department_id);
        })
        // PERBAIKAN: Hapus whereHas karena kita ingin semua header, biarkan filter monthlyData di relation
        ->orderBy('risk_code')
        ->get();

        // Setelah data di-load, tambahkan risk code names secara manual
        $this->loadRiskCodeNames($headers);

        // Kalau tidak ada data
        if ($headers->isEmpty()) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Data Tidak Ditemukan',
                'data' => 'Data risiko untuk filter tersebut tidak ditemukan.'
            ], 404);
        }

        // Nama file dengan format yang sesuai
        $monthName = $this->getMonthName($filterMonth);
        $departmentName = $this->getDepartmentNameFromHeaders($headers, $filterDepartment);

        try {
            if ($format === 'excel') {
                return $this->exportExcel($headers, $monthName, $filterYear, $departmentName);
            } else {
                return $this->exportPdf($headers, $monthName, $filterYear, $departmentName);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Gagal melakukan export',
                'data' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Load risk code names untuk multiple risk codes
     */
    private function loadRiskCodeNames($headers)
    {
        foreach ($headers as $header) {
            if (!empty($header->risk_code)) {
                // Parse risk_code jika berupa JSON string
                $riskCodeIds = is_string($header->risk_code) ?
                    json_decode($header->risk_code, true) : $header->risk_code;

                if (is_array($riskCodeIds)) {
                    // Query multiple risk codes
                    $riskCodes = \DB::table('mst_risk_code')
                        ->whereIn('id', $riskCodeIds)
                        ->get(['id', 'name']);

                    $header->riskCode = $riskCodes;
                } else {
                    // Single risk code
                    $riskCode = \DB::table('mst_risk_code')
                        ->where('id', $riskCodeIds)
                        ->first(['id', 'name']);

                    $header->riskCode = $riskCode ? collect([$riskCode]) : collect([]);
                }
            } else {
                $header->riskCode = collect([]);
            }
        }
    }

    /**
     * Export ke Excel (Multi-sheet)
     */
    private function exportExcel($headers, $monthName, $filterYear, $departmentName)
    {
        $filename = "Risk_Report_Complete_{$departmentName}_{$monthName}_{$filterYear}_".time().".xlsx";

        return Excel::download(
            new MultiSheetRiskExport($headers, $monthName, $filterYear ?? date('Y'), $departmentName),
            $filename
        );
    }

    /**
     * Export ke PDF (Multi-halaman)
     */
   private function exportPdf($headers, $monthName, $filterYear, $departmentName)
{
    $filename = "Risk_Report_Complete_{$departmentName}_{$monthName}_{$filterYear}_" . time() . ".pdf";

    // Pastikan dapat angka bulan
    if (is_numeric($monthName)) {
        $monthNumber = (int)$monthName;
    } else {
        try {
            $monthNumber = \Carbon\Carbon::parse("1 {$monthName} {$filterYear}")->month;
        } catch (\Exception $e) {
            $monthNumber = 1; // default Januari
        }
    }

    // Load data untuk 3 halaman PDF
    $riskExportData = $this->getRiskRegisterData($headers, $monthName, $filterYear);
    $monitoringData = $this->getMonitoringData($headers, $monthName, $filterYear);
    $heatmapData = $this->getHeatmapData($headers, $monthName, $filterYear);

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.risk_pdf', [
        'headers' => $headers,
        'monthName' => $monthName,
        'monthNumber' => $monthNumber,
        'year' => $filterYear ?? date('Y'),
        'departmentName' => $departmentName,
        'riskRegisterData' => $riskExportData,
        'monitoringData' => $monitoringData,
        'heatmapData' => $heatmapData
    ]);

    $pdf->setPaper('A4', 'landscape');

    return $pdf->download($filename);
}
    /**
     * Prepare data untuk Risk Register PDF
     */
    private function getRiskRegisterData($headers, $monthName, $year)
{
    $data = [];
    $no = 1;

    foreach ($headers as $header) {
        $monthly = $header->monthlyData->first();

        if (!$monthly) {
            $targetBulan = '';
            $realisasiBulan = '';
            $percentage = 0;
            $monthlyData = (object) [
                'target_quantitative' => 0,
                'target_kualitatif' => '',
                'realization_quantitative' => 0,
                'realization_kualitatif' => '',
                'residual_risk_level_dampak' => '',
                'residual_risk_level_kemungkinan' => '',
                'residual_risk_posisi_risiko' => '',
                'residual_risk_level_risiko' => '',
                'realization_note' => '',
                'status_risiko' => '',
            ];
        } else {
            // Format target bulan menggunakan formatValueWithOption yang sudah ada
            $targetBulan = $this->formatValueWithOption($monthly->target_quantitative ?? '0', $monthly->target_option);

            // Format realisasi bulan menggunakan formatValueWithOption yang sudah ada
            $realisasiBulan = $this->formatValueWithOption($monthly->realization_quantitative ?? '0', $monthly->realization_option);

            // Hitung persentase seperti yang sudah ada di method asli
            $targetNumeric = is_numeric(str_replace('.', '', $monthly->target_quantitative))
                ? (float)str_replace('.', '', $monthly->target_quantitative)
                : 0;
            $realisasiNumeric = is_numeric(str_replace('.', '', $monthly->realization_quantitative))
                ? (float)str_replace('.', '', $monthly->realization_quantitative)
                : 0;
            $percentage = ($targetNumeric > 0) ? round(($realisasiNumeric / $targetNumeric) * 100, 2) : 0;

            $monthlyData = $monthly;
        }

        // Target 1 Tahun menggunakan formatValueWithOption yang sudah ada
        $target1Tahun = $this->formatValueWithOption(
            $header->target_quantitative_satu_tahun ?? '0',
            $header->target_satu_tahun_option
        );

        $data[] = [
            'no' => $no++,
            'risk_code' => $this->getRiskCodeName($header),
            'jenis_risiko' => $header->jenis_risiko ?? '',
            'sasaran' => $header->sasaran ?? '',
            'peristiwa_risiko' => $header->peristiwa_risiko ?? '',
            'penyebab_risiko' => $header->penyebab_risiko ?? '',
            'dampak_risiko' => $header->dampak_risiko ?? '',
            'inherent_risk_level_dampak' => $header->inherent_risk_level_dampak ?? '',
            'inherent_risk_level_kemungkinan' => $header->inherent_risk_level_kemungkinan ?? '',
            'inherent_risk_posisi_risiko' => $header->inherent_risk_posisi_risiko ?? '',
            'inherent_risk_level_risiko' => $header->inherent_risk_level_risiko ?? '',
            'internal_control' => $header->internal_control ?? '',
            'target_bulan' => $targetBulan,
            'realisasi_bulan' => $realisasiBulan,
            'percentage' => $percentage . '%',
            'residual_risk_level_dampak' => $monthlyData->residual_risk_level_dampak ?? '',
            'residual_risk_level_kemungkinan' => $monthlyData->residual_risk_level_kemungkinan ?? '',
            'residual_risk_posisi_risiko' => $monthlyData->residual_risk_posisi_risiko ?? '',
            'residual_risk_level_risiko' => $monthlyData->residual_risk_level_risiko ?? '',
            'target_1_tahun' => $target1Tahun,
            'realisasi_duplicate' => $realisasiBulan,
            'residual_target_level_dampak' => $header->residual_target_level_dampak ?? '',
            'residual_target_level_kemungkinan' => $header->residual_target_level_kemungkinan ?? '',
            'residual_target_posisi_risiko' => $header->residual_target_posisi_risiko ?? '',
            'residual_target_level_risiko' => $header->residual_target_level_risiko ?? '',
            'perlakuan_risiko' => $header->mitigasi ?? '',
            'biaya_perlakuan' => $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),
            'status_risiko' => $monthlyData->status_risiko ?? '',
            'realization_note' => $monthlyData->realization_note ?? '',
        ];
    }

    return $data;
}

    /**
     * Prepare data untuk Monitoring PDF
     */
    private function getMonitoringData($headers, $monthName, $year)
{
    $data = [];
    $no = 1;

    foreach ($headers as $header) {
        $monthly = $header->monthlyData?->first();

        // Format menggunakan formatValueWithOption yang sudah ada
        $targetBulan = $monthly ?
            $this->formatValueWithOption($monthly->target_quantitative ?? '0', $monthly->target_option) :
            '0';

        $realisasiBulan = $monthly ?
            $this->formatValueWithOption($monthly->realization_quantitative ?? '0', $monthly->realization_option) :
            '0';

        // Format target tahunan menggunakan formatValueWithOption yang sudah ada
        $targetTahunan = $this->formatValueWithOption(
            $header->target_quantitative_satu_tahun ?? '0',
            $header->target_satu_tahun_option
        );

        // Perhitungan percentage seperti yang sudah ada
        $targetBulananNumeric = $monthly && is_numeric($monthly->target_quantitative) ? (float)$monthly->target_quantitative : 0;
        $realisasiBulananNumeric = $monthly && is_numeric($monthly->realization_quantitative) ? (float)$monthly->realization_quantitative : 0;
        $targetTahunanNumeric = is_numeric($header->target_quantitative_satu_tahun) ? (float)$header->target_quantitative_satu_tahun : 0;

        $percentageBulanan = $targetBulananNumeric > 0 ? round(($realisasiBulananNumeric / $targetBulananNumeric) * 100, 2) : 0;
        $percentageTahunan = $targetTahunanNumeric > 0 ? round(($realisasiBulananNumeric / $targetTahunanNumeric) * 100, 2) : 0;

        // PERBAIKAN: Ambil evaluasi perlakuan risiko dari field realization_note (singular)
        $evaluasiPerlakuanRisiko = $monthly?->realization_note ?? '';

        $data[] = [
            'no' => $no++,
            'risk_code' => $this->getRiskCodeName($header),
            'jenis_risiko' => $header->jenis_risiko ?? '',
            'peristiwa_risiko' => $header->peristiwa_risiko ?? '',
            'penyebab_risiko' => $header->penyebab_risiko ?? '',
            'target_bulan' => $targetBulan,
            'realisasi_bulan' => $realisasiBulan,
            'target_1_tahun' => $targetTahunan,
            'realisasi_duplicate' => $realisasiBulan,
            'percentage_bulan' => $percentageBulanan . '%',
            'percentage_tahun' => $percentageTahunan . '%',
            'biaya_perlakuan' => $this->formatCurrency($header->biaya_perlakuan_risiko ?? 0),
            'level_dampak' => $header->residual_target_level_dampak ?? '',
            'level_kemungkinan' => $header->residual_target_level_kemungkinan ?? '',
            'posisi_risiko' => $header->residual_target_posisi_risiko ?? '',
            'level_risiko' => $header->residual_target_level_risiko ?? '',
            'target_satu_tahun_notes' => $evaluasiPerlakuanRisiko,
            'evaluasi_perlakuan_risiko' => $evaluasiPerlakuanRisiko,
            'evaluasi_perlakuan' => $evaluasiPerlakuanRisiko,
        ];
    }

    return $data;
}
private function getRiskCodeName($header)
{
    // Kalau sudah ada relasi riskCode
    if (isset($header->riskCode) && $header->riskCode && $header->riskCode->isNotEmpty()) {
        return $header->riskCode->map(function ($code) {
            return (!empty($code->code) ? $code->code . ' - ' : '') . $code->name;
        })->implode(', ');
    }

    if (!empty($header->risk_code)) {
        $riskCodeIds = $header->risk_code;

        // Coba decode JSON
        if (is_string($riskCodeIds)) {
            $decoded = json_decode($riskCodeIds, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $riskCodeIds = $decoded;
            }
        }

        // String dengan koma → array
        if (is_string($riskCodeIds) && str_contains($riskCodeIds, ',')) {
            $riskCodeIds = explode(',', $riskCodeIds);
        }

        // Single id → array
        if (is_numeric($riskCodeIds)) {
            $riskCodeIds = [$riskCodeIds];
        }

        if (is_array($riskCodeIds) && !empty($riskCodeIds)) {
            $riskCodes = DB::table('mst_risk_code')
                ->whereIn('id', $riskCodeIds)
                ->orderBy('id')
                ->get(['name']);

            return $riskCodes->map(function ($rc) {
                return (!empty($rc->code) ? $rc->code . ' - ' : '') . $rc->name;
            })->implode(', ');
        }
    }

    return '';
}

    /**
     * Prepare data untuk Heatmap PDF
     */
    private function getHeatmapData($headers, $monthName, $year)
    {
        // Load heatmap structure dan risk counts
        $heatmapStructure = \DB::table('mst_heatmap')
            ->select('dampak', 'kemungkinan', 'result')
            ->orderBy('kemungkinan', 'desc')
            ->orderBy('dampak', 'asc')
            ->get();

        $inherentCounts = $this->countRisksByLevel($headers, 'inherent');
        $residualCurrentCounts = $this->countRisksByLevel($headers, 'residual_current');
        $residualTargetCounts = $this->countRisksByLevel($headers, 'residual_target');

        return [
            'structure' => $heatmapStructure,
            'inherent_counts' => $inherentCounts,
            'residual_current_counts' => $residualCurrentCounts,
            'residual_target_counts' => $residualTargetCounts,
            'color_map' => $this->getColorMapping()
        ];
    }

    /**
     * Count risks by level untuk heatmap
     */
    private function countRisksByLevel($headers, $riskType)
    {
        $counts = [];

        foreach ($headers as $header) {
            $dampak = 0;
            $kemungkinan = 0;

            switch ($riskType) {
                case 'inherent':
                    $dampak = $header->inherent_risk_level_dampak ?? 0;
                    $kemungkinan = $header->inherent_risk_level_kemungkinan ?? 0;
                    break;

                case 'residual_current':
                    $monthly = $header->monthlyData->first();
                    if ($monthly) {
                        $dampak = $monthly->residual_risk_level_dampak ?? 0;
                        $kemungkinan = $monthly->residual_risk_level_kemungkinan ?? 0;
                    }
                    break;

                case 'residual_target':
                    $dampak = $header->residual_target_level_dampak ?? 0;
                    $kemungkinan = $header->residual_target_level_kemungkinan ?? 0;
                    break;
            }

            if ($dampak > 0 && $kemungkinan > 0) {
                $key = $kemungkinan . '_' . $dampak;
                if (!isset($counts[$key])) {
                    $counts[$key] = 0;
                }
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /**
     * Get color mapping untuk heatmap
     */
    private function getColorMapping()
    {
        $colorMap = [];
        try {
            $colorRanges = \DB::table('mst_heatmap_risk_range')->get();
            foreach ($colorRanges as $range) {
                for ($i = $range->start; $i <= $range->end; $i++) {
                    $colorMap[$i] = [
                        'name' => $range->name,
                        'color' => $range->color
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fallback colors
            $colorMap = [
                1 => ['name' => 'Low', 'color' => '#00B050'],
                8 => ['name' => 'Low to Moderate', 'color' => '#92D050'],
                13 => ['name' => 'Moderate', 'color' => '#FFFF00'],
                17 => ['name' => 'Moderate to High', 'color' => '#FFC000'],
                22 => ['name' => 'High', 'color' => '#FF0000']
            ];
        }
        return $colorMap;
    }

    /**
     * Format value with option untuk menentukan mata uang atau format lainnya
     */
    private function formatValueWithOption($value, $optionId)
    {
        if (empty($value) || $value === '0') {
            return '0';
        }

        // Jika tidak ada option atau option kosong, return value as is
        if (empty($optionId)) {
            return $value;
        }

        try {
            // Get option dari database
            $option = \DB::table('mst_option')->where('id', $optionId)->first();

            if (!$option) {
                return $value;
            }

            // Jika value adalah numeric dan option adalah mata uang (id 1-4 biasanya mata uang)
            if (is_numeric($value) && in_array($optionId, [1, 2, 3, 4])) {
                switch ($optionId) {
                    case 1: // Rupiah
                        return 'Rp.' . number_format((float)$value, 0, ',', '.');
                    case 2: // Euro
                        return '€' . number_format((float)$value, 2, ',', '.');
                    case 3: // Dollar
                        return '$' . number_format((float)$value, 2, ',', '.');
                    case 4: // Yen
                        return '¥' . number_format((float)$value, 0, ',', '.');
                    default:
                        return $value;
                }
            } else {
                // Jika bukan numeric atau bukan mata uang, return value as is
                return $value;
            }

        } catch (\Exception $e) {
            // Jika error, return value original
            return $value;
        }
    }

    private function formatCurrency($value)
    {
        return 'Rp.' . number_format($value, 0, ',', '.');
    }

    private function getMonthName($month)
    {
        if (empty($month)) {
            return 'SEMUA_BULAN';
        }

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET',
            4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
            7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER',
            10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
        ];

        return $monthNames[$month] ?? 'BULAN_TIDAK_VALID';
    }

    private function getDepartmentName($departmentId)
    {
        if (empty($departmentId)) {
            return 'SEMUA_DEPT';
        }

        $department = \App\Models\Department::find($departmentId);
        return $department ? strtoupper(str_replace(' ', '_', $department->name)) : 'DEPT_' . $departmentId;
    }

    private function getDepartmentNameFromHeaders($headers, $filterDepartment = null)
    {
        if (!is_null($filterDepartment)) {
            return $this->getDepartmentName($filterDepartment);
        }

        if ($headers->isNotEmpty()) {
            $firstHeader = $headers->first();
            if ($firstHeader && $firstHeader->department) {
                return strtoupper(str_replace(' ', '_', $firstHeader->department->name));
            }
        }

        return 'SEMUA_DEPT';
    }

    private function shouldFilterByUserDepartment()
    {
        $user = Auth::user();

        return $user &&
               !in_array($user->role, ['admin', 'super_admin']) &&
               $user->department_id;
    }

    public function preview(Request $request, $id)
    {
        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');
        $filterDepartment = $request->get('department_id');

        if (is_array($filterMonth) && isset($filterMonth['month'])) {
            $filterMonth = (int) $filterMonth['month'];
        } elseif (!is_null($filterMonth)) {
            $filterMonth = (int) $filterMonth;
        }

        if (!is_null($filterDepartment)) {
            $filterDepartment = (int) $filterDepartment;
        }

        try {
            $headers = TrRiskHeader::with([
                'department:id,name',
                'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                    $q->select([
                        'id',
                        'header_id',
                        'target_quantitative',
                        'target_option',
                        'realization_quantitative',
                        'realization_option',
                        'residual_risk_level_dampak',
                        'residual_risk_level_kemungkinan',
                        'residual_risk_posisi_risiko',
                        'residual_risk_level_risiko',
                        'start_date',
                        'month'
                    ]);

                    if (!empty($filterYear)) {
                        $q->whereYear('start_date', $filterYear);
                    }
                    if (!empty($filterMonth)) {
                        $q->where('month', $filterMonth);
                    }
                },
            ])
            ->select([
                'id',
                'risk_code',
                'jenis_risiko',
                'penyebab_risiko',
                'department_id',
                'inherent_risk_level_dampak',
                'inherent_risk_level_kemungkinan',
                'inherent_risk_posisi_risiko',
                'inherent_risk_level_risiko',
                'residual_target_level_dampak',
                'residual_target_level_kemungkinan',
                'residual_target_posisi_risiko',
                'residual_target_level_risiko',
                'target_quantitative_satu_tahun',
                'target_satu_tahun_option',
                'target_satu_tahun_notes',
                'mitigasi',
                'biaya_perlakuan_risiko'
            ])
            ->when(!is_null($filterDepartment), function ($query) use ($filterDepartment) {
                $query->where('department_id', $filterDepartment);
            })
            ->when($this->shouldFilterByUserDepartment(), function ($query) {
                $query->where('department_id', Auth::user()->department_id);
            })
            // PERBAIKAN: Hapus whereHas juga di preview
            ->when($id, function ($query) use ($id) {
                if ($id > 0) {
                    $query->where('id', $id);
                }
            })
            ->limit(10)
            ->get();

            // Load risk code names untuk preview juga
            $this->loadRiskCodeNames($headers);

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Preview data berhasil',
                'data' => [
                    'total_records' => TrRiskHeader::count(),
                    'filtered_records' => $headers->count(),
                    'preview_data' => $headers,
                    'sheets_info' => [
                        'risk_register' => 'Sheet 1: Data lengkap risk register',
                        'monitoring' => 'Sheet 2: Data monitoring risiko',
                        'peta_risiko' => 'Sheet 3: Peta risiko (heatmap)'
                    ],
                    'formats' => [
                        'excel' => 'Multi-sheet Excel (.xlsx)',
                        'pdf' => 'Multi-page PDF (.pdf)'
                    ],
                    'filters' => [
                        'id' => $id,
                        'year' => $filterYear,
                        'month' => $filterMonth,
                        'month_name' => $this->getMonthName($filterMonth),
                        'department_id' => $filterDepartment,
                        'department_name' => $this->getDepartmentNameFromHeaders($headers, $filterDepartment)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Gagal mengambil preview data',
                'data' => ['error' => $e->getMessage()]
            ], 500);
        }
    }
}
