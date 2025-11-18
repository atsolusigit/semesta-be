<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use App\Models\LostEvent;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MultiSheetRiskExport;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ExportRiskController extends Controller
{
    private $colorMap = [];

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

    $user = Auth::user();

    // Cek akses role
    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return response()->json([
            'status' => 403,
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk export Risk.',
        ], 403);
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
        'jenisRisiko:id,nama_jenis_risiko',
        'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
            $q->select([
                'id',
                'header_id',
                'target_kualitatif',
                'target_quantitative',
                'target_option',
                'realization_quantitative',
                'realization_kualitatif',
                'realization_option',
                'residual_risk_level_dampak',
                'residual_risk_level_kemungkinan',
                'residual_risk_posisi_risiko',
                'residual_risk_level_risiko',
                'realization_note',
                'status_risiko',
                'start_date',
                'month',
                'note_recommendation'
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
    // Filter berdasarkan department untuk role 2 dan 3
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        $query->where('department_id', $user->department_id);
    })
    // Filter berdasarkan department jika ada (untuk role 1)
    ->when(!is_null($filterDepartment), function ($query) use ($filterDepartment) {
        $query->where('department_id', $filterDepartment);
    })
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
            $riskCodeIds = $header->risk_code;

            // String dengan koma → array
            if (is_string($riskCodeIds) && strpos($riskCodeIds, ',') !== false) {
                $riskCodeIds = explode(',', $riskCodeIds);
            }

            // Single id → array
            if (!is_array($riskCodeIds)) {
                $riskCodeIds = [$riskCodeIds];
            }

            // Query risk codes
            $riskCodes = \DB::table('mst_risk_code')
                ->whereIn('id', $riskCodeIds)
                ->get(['id', 'code']);

            $header->riskCode = $riskCodes;
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
        'heatmapData' => $heatmapData,
        'colorMap' => $this->getColorMapping()
    ]);

    $pdf->setPaper('A4', 'landscape');

    return $pdf->download($filename);
}

    /**
     * Format value with option untuk menentukan mata uang atau format lainnya
     */
    private function formatValueWithOption($value, $option = null)
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return '';
        }

        // Jika value adalah string numeric, convert ke number dulu
        if (is_string($value) && is_numeric($value)) {
            $value = (float)$value;
        }

        switch ($option) {
            case 'currency':
            case 'rupiah':
                return $this->formatCurrency($value);
            case 'percent':
            case 'percentage':
                return $value . '%';
            default:
                // Jika numeric tapi tidak ada option khusus, format sebagai number biasa
                if (is_numeric($value)) {
                    return number_format($value, 0, ',', '.');
                }
                return $value;
        }
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
            $targetBulan = $this->formatTargetBulan($monthly);
            $realisasiBulan = $this->formatRealizationBulan($monthly);

            $target = $monthly->target_quantitative ?? 0;
            $realization = $monthly->realization_quantitative ?? 0;

            if (is_numeric($target) && is_numeric($realization) && floatval($target) > 0) {
                $percentage = round((floatval($realization) / floatval($target)) * 100, 2);
            } else {
                $percentage = 0;
            }

            $monthlyData = $monthly;
        }

        $target1Tahun = $this->formatTarget(
            $header->target_quantitative_satu_tahun ?? 0,
            $header->target_kualitatif_satu_tahun ?? ''
        );

        $data[] = [
            'no' => $no++,
            'risk_code' => $this->getRiskCodeName($header),
            'jenis_risiko' => $header->jenisRisiko->nama_jenis_risiko ?? '',
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
            'note_recommendation' => $monthlyData->note_recommendation ?? '',
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

        // PERBAIKAN: Gunakan formatTargetBulan dan formatRealizationBulan
        $targetBulan = $this->formatTargetBulan($monthly);
        $realisasiBulan = $this->formatRealizationBulan($monthly);

        // PERBAIKAN: Format target tahunan menggunakan method formatTarget
        $targetTahunan = $this->formatTarget(
            $header->target_quantitative_satu_tahun ?? 0,
            $header->target_kualitatif_satu_tahun ?? ''
        );

        // Perhitungan percentage
        $targetBulananNumeric = $monthly && is_numeric($monthly->target_quantitative) ? (float)$monthly->target_quantitative : 0;
        $realisasiBulananNumeric = $monthly && is_numeric($monthly->realization_quantitative) ? (float)$monthly->realization_quantitative : 0;
        $targetTahunanNumeric = is_numeric($header->target_quantitative_satu_tahun) ? (float)$header->target_quantitative_satu_tahun : 0;

        $percentageBulanan = $targetBulananNumeric > 0 ? round(($realisasiBulananNumeric / $targetBulananNumeric) * 100, 2) : 0;
        $percentageTahunan = $targetTahunanNumeric > 0 ? round(($realisasiBulananNumeric / $targetTahunanNumeric) * 100, 2) : 0;

        $evaluasiPerlakuanRisiko = $monthly?->realization_note ?? '';

        $data[] = [
            'no' => $no++,
            'risk_code' => $this->getRiskCodeName($header),
            'jenis_risiko' => $header->jenisRisiko->nama_jenis_risiko ?? '',
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

private function formatTargetBulan($monthly)
{
    if (!$monthly) {
        return '';
    }

    $quantitative = $monthly->target_quantitative ?? '';
    $kualitatif = $monthly->target_kualitatif ?? '';

    // Jika target_kualitatif ada isi dan bukan kosong/null, berarti data kualitatif
    if (!empty($kualitatif) && $kualitatif !== null && trim($kualitatif) !== '') {
        $result = '';

        // Tampilkan persentase dari target_kualitatif dulu (di atas)
        $result = $kualitatif;
        // Tambahkan % jika belum ada dan numeric
        if (is_numeric($kualitatif)) {
            $result .= '%';
        }

        // Tampilkan deskripsi kualitatif dari target_quantitative (di bawah)
        if (!empty($quantitative)) {
            $result .= "\n"; // Line break untuk memisahkan
            $result .= $quantitative;
        }

        return $result;
    }

    // Jika target_kualitatif kosong/null, berarti data quantitative murni
    if (!empty($quantitative)) {
        if (is_numeric($quantitative)) {
            return $this->formatCurrency($quantitative);
        } else {
            return $quantitative;
        }
    }

    return '';
}

private function formatRealizationBulan($monthly)
{
    if (!$monthly) {
        return '';
    }

    $quantitative = $monthly->realization_quantitative ?? '';
    $kualitatif = $monthly->realization_kualitatif ?? '';

    // Jika realization_kualitatif ada isi dan bukan kosong/null, berarti data kualitatif
    if (!empty($kualitatif) && $kualitatif !== null && trim($kualitatif) !== '') {
        $result = '';

        // Tampilkan persentase dari realization_kualitatif dulu (di atas)
        $result = $kualitatif;
        // Tambahkan % jika belum ada dan numeric
        if (is_numeric($kualitatif)) {
            $result .= '%';
        }

        // Tampilkan deskripsi kualitatif dari realization_quantitative (di bawah)
        if (!empty($quantitative)) {
            $result .= "\n"; // Line break untuk memisahkan
            $result .= $quantitative;
        }

        return $result;
    }

    // Jika realization_kualitatif kosong/null, berarti data quantitative murni
    if (!empty($quantitative)) {
        if (is_numeric($quantitative)) {
            return $this->formatCurrency($quantitative);
        } else {
            return $quantitative;
        }
    }

    return '';
}

private function formatTarget($quantitative, $qualitative)
{
    $result = '';

    // Format quantitative jika ada
    if (!empty($quantitative) && $quantitative !== null && $quantitative !== '') {
        if (is_numeric($quantitative) && floatval($quantitative) > 0) {
            $result .= $this->formatCurrency($quantitative);
        } else {
            $result .= $quantitative;
        }
    }

    // Format qualitative jika ada
    if (!empty($qualitative) && $qualitative !== null && $qualitative !== '') {
        if (!empty($result)) {
            $result .= "\n"; // Line break untuk memisahkan
        }
        $result .= $qualitative;
    }

    return $result;
}

           private function getRiskCodeName($header)
{
    // Kalau sudah ada relasi riskCode
    if (isset($header->riskCode) && $header->riskCode && $header->riskCode->isNotEmpty()) {
        return $header->riskCode->pluck('code')->implode(', ');
    }

    if (!empty($header->risk_code)) {
        $riskCodeIds = $header->risk_code;

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
                ->pluck('code');

            return $riskCodes->implode(', ');
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

    // Jika user tidak ada, tidak perlu filter
    if (!$user) {
        return false;
    }

    // Role ID 1 = Admin/Super Admin (tidak perlu filter)
    // Role ID 2, 3 = User department (perlu filter)
    // Sesuaikan dengan role_id di sistem Anda
    if (in_array($user->role_id, [2, 3]) && !empty($user->department_id)) {
        return true;
    }

    return false;
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
                'jenisRisiko:id,nama_jenis_risiko',
                'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                    $q->select([
                        'id',
                        'header_id',
                        'target_kualitatif',
                        'target_quantitative',
                        'target_option',
                        'realization_quantitative',
                        'realization_kualitatif',
                        'realization_option',
                        'residual_risk_level_dampak',
                        'residual_risk_level_kemungkinan',
                        'residual_risk_posisi_risiko',
                        'residual_risk_level_risiko',
                        'start_date',
                        'month',
                        'note_recommendation',
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

    /**
 * Get risk color based on position PDF
 */
private function getRiskColorByPosition($posisi)
{
    // Convert posisi ke integer
    $posisi = (int)$posisi;

    // Load color map jika belum
    if (empty($this->colorMap)) {
        $this->colorMap = $this->getColorMapping();
    }

    // Cek di colorMap yang sudah di-load dari database
    if (isset($this->colorMap[$posisi])) {
        return $this->colorMap[$posisi]['color'];
    }

    // Fallback manual berdasarkan range dari database
    if ($posisi >= 1 && $posisi <= 5) {
        return '#00B050'; // Low - Hijau
    } elseif ($posisi >= 6 && $posisi <= 11) {
        return '#62b334'; // Low To Moderate - Hijau Muda
    } elseif ($posisi >= 12 && $posisi <= 15) {
        return '#d2da15'; // Moderate - Kuning
    } elseif ($posisi >= 16 && $posisi <= 19) {
        return '#FFC000'; // Moderate To High - Orange
    } elseif ($posisi >= 20 && $posisi <= 25) {
        return '#FF0000'; // High - Merah
    }

    // Default putih jika tidak ada match
    return '#FFFFFF';
}

/**
 * Export Lost Event
 */
public function exportLostEvent(Request $request, $format)
{
    // Validasi format
    if (!in_array($format, ['excel', 'pdf'])) {
        return response()->json([
            'status' => 400,
            'success' => false,
            'message' => 'Format tidak didukung',
            'data' => 'Format yang didukung: excel, pdf'
        ], 400);
    }

    $user = Auth::user();

    // Cek akses role
    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return response()->json([
            'status' => 403,
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk export Lost Event.',
        ], 403);
    }

    $filterYear = $request->get('year');
    $filterType = strtolower($request->get('type', ''));
    $filterDepartment = $request->get('department_id');
    $search = $request->get('search');

    // Ambil data dari LostEvent HANYA yang sudah approved
    // Mengambil berdasarkan lost_event_id (id), bukan header_id atau rcsa_id
    $lostEvents = \App\Models\LostEvent::with([
        'header' => function ($query) {
            $query->with([
                'optionTargetSatuTahun:id,name,type',
                'monthlyData' => function ($q) {
                    $q->where('is_finalize', true)->orderBy('month', 'asc');
                }
            ]);
        },
        'riskOwnerDepartmentRelation:id,name',
        'jenisRisikoRelation:id,nama_jenis_risiko'
    ])
    ->where('status', 'approved')
    // Filter berdasarkan department user jika perlu
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        $query->where('risk_owner_department_id', $user->department_id);
    })
    ->when($filterYear, function ($query) use ($filterYear) {
        $query->where('tahun', $filterYear);
    })
    ->when($filterDepartment, function ($query) use ($filterDepartment) {
        $query->where('risk_owner_department_id', $filterDepartment);
    })
    ->when($search, function ($query) use ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('tahun', 'like', '%' . $search . '%')
              ->orWhere('nama_kejadian', 'like', '%' . $search . '%')
              ->orWhere('identifikasi_kejadian', 'like', '%' . $search . '%')
              ->orWhereHas('riskOwnerDepartmentRelation', function ($dept) use ($search) {
                  $dept->where('name', 'like', '%' . $search . '%');
              })
              ->orWhereHas('jenisRisikoRelation', function ($jr) use ($search) {
                  $jr->where('nama_jenis_risiko', 'like', '%' . $search . '%');
              });
        });
    })
    ->withTrashed()
    ->orderBy('id', 'asc')
    ->get();

    if ($lostEvents->isEmpty()) {
        return response()->json([
            'status' => 404,
            'success' => false,
            'message' => 'Tidak ada data Lost Event yang sudah approved untuk diexport.',
        ], 404);
    }

    // Hitung persentase untuk setiap lost event (sama seperti di index)
    // TIDAK memfilter berdasarkan header, ambil SEMUA lost event yang approved
    foreach ($lostEvents as $lostEvent) {
        $percentage = 0;
        $detectedType = '';

        // Jika ada header, hitung persentase
        if ($lostEvent->header && $lostEvent->header->monthlyData->isNotEmpty()) {
            $targetType = optional($lostEvent->header->optionTargetSatuTahun)->type;

            // Deteksi type jika tidak ada di optionTargetSatuTahun
            if (!$targetType) {
                if (!empty($lostEvent->header->target_quantitative_satu_tahun) &&
                    preg_match('/\d/', $lostEvent->header->target_quantitative_satu_tahun)) {
                    $targetType = 'kuantitatif';
                } elseif (!empty($lostEvent->header->target_satu_tahun_notes)) {
                    $targetType = 'kualitatif';
                }
            }

            $normalizedType = strtolower($targetType ?? '');
            $detectedType = $normalizedType;

            // Hitung persentase untuk Kuantitatif
            if (in_array($normalizedType, ['kuantitatif', 'quantitative'])) {
                $totalTarget = 0;
                $totalRealisasi = 0;

                foreach ($lostEvent->header->monthlyData as $monthly) {
                    $targetNum = (float) preg_replace('/[^0-9]/', '', $monthly->target_quantitative ?? '0');
                    $realNum = (float) preg_replace('/[^0-9]/', '', $monthly->realization_quantitative ?? '0');
                    $totalTarget += $targetNum;
                    $totalRealisasi += $realNum;
                }

                if ($totalTarget > 0) {
                    $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
                }
            }
            // Hitung persentase untuk Kualitatif
            elseif (in_array($normalizedType, ['kualitatif', 'qualitative'])) {
                $totalTarget = 0;
                $totalRealisasi = 0;

                foreach ($lostEvent->header->monthlyData as $monthly) {
                    $targetText = trim(str_replace(['%', ','], ['', '.'], $monthly->target_kualitatif ?? '0'));
                    $targetNum = (float) $targetText;

                    $realText = trim(str_replace(['%', ','], ['', '.'], $monthly->realization_kualitatif ?? '0'));
                    $realNum = (float) $realText;

                    $totalTarget += $targetNum;
                    $totalRealisasi += $realNum;
                }

                if ($totalTarget > 0) {
                    $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
                }
            }
        } else {
            // Jika tidak ada header, gunakan type dari lost_event
            $detectedType = $lostEvent->type ?? '';
        }

        $lostEvent->calculated_percentage = $percentage;
        $lostEvent->detected_type = $detectedType;
    }

    // Filter berdasarkan type jika diberikan
    // Filter hanya berdasarkan detected_type yang sudah dihitung
    if (!empty($filterType)) {
        $lostEvents = $lostEvents->filter(function ($lostEvent) use ($filterType) {
            $detectedType = strtolower($lostEvent->detected_type ?? '');
            return $detectedType === $filterType;
        })->values();

        if ($lostEvents->isEmpty()) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Data Lost Event tidak ditemukan untuk filter type tersebut.',
            ], 404);
        }
    }

    // Siapkan data export
    $exportData = $this->prepareLostEventData($lostEvents);
    if (empty($exportData)) {
        return response()->json([
            'status' => 404,
            'success' => false,
            'message' => 'Tidak ada data Lost Event untuk diexport.',
        ], 404);
    }

    // MODIFIKASI: Ambil nama department yang sebenarnya
        $departmentName = 'SEMUA_DEPARTEMEN';

        // Jika user role 2 atau 3, ambil dari department user
        if (in_array($user->role_id, [2, 3]) && $user->department_id) {
            $dept = \App\Models\MstDepartment::find($user->department_id);
            $departmentName = $dept ? $dept->name : 'SEMUA_DEPARTEMEN';
        }
        // Jika ada filter department dari parameter request (untuk role 1, 4, 5)
        elseif ($filterDepartment) {
            $dept = \App\Models\MstDepartment::find($filterDepartment);
            $departmentName = $dept ? $dept->name : 'SEMUA_DEPARTEMEN';
        }

        $yearName = $filterYear ?? 'SEMUA_TAHUN';

    try {
        if ($format === 'excel') {
            return $this->exportLostEventExcel($exportData, $yearName, $departmentName);
        } else {
            return $this->exportLostEventPdf($exportData, $yearName, $departmentName);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 500,
            'success' => false,
            'message' => 'Gagal melakukan export.',
            'data' => ['error' => $e->getMessage()],
        ], 500);
    }
}

/**
 * Prepare data untuk export - mengambil dari LostEvent dengan perhitungan persentase
 */
private function prepareLostEventData($lostEvents)
{
    $data = [];
    $no = 1;

    foreach ($lostEvents as $lostEvent) {
        // Format persentase: jika 0 atau tidak ada header maka 0%, jika ada maka tampilkan dengan 2 desimal
        $percentageFormatted = $lostEvent->calculated_percentage !== null && $lostEvent->calculated_percentage > 0
            ? rtrim(rtrim(number_format($lostEvent->calculated_percentage, 2), '0'), '.') . '%'
            : '0%';

        // Ambil nama department dan jenis risiko dari relasi
        $departmentName = optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '';
        $jenisRisikoName = optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '';

        $data[] = [
            'no' => $no++,
            'tahun' => $lostEvent->tahun ?? '',
            'risk_owner_department' => $departmentName,
            'jenis_risiko' => $jenisRisikoName,
            'nama_kejadian' => $lostEvent->nama_kejadian ?? '',
            'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian ?? '',
            'kategori_kejadian' => $lostEvent->kategori_kejadian ?? '',
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian ?? '',
            'penyebab_kejadian' => $lostEvent->penyebab_kejadian ?? '',
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian ?? '',
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian ?? '',
            'pihak_terkait' => $lostEvent->pihak_terkait ?? '',
            'status_asuransi' => $lostEvent->status_asuransi ?? '',
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
            'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian ?? '',
            'nilai_kerugian' => $lostEvent->nilai_kerugian ?? 0,
            'nilai_kerugian_formatted' => $this->formatCurrency($lostEvent->nilai_kerugian ?? 0),
            'kejadian_berulang' => $lostEvent->kejadian_berulang ?? '',
            'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian ?? '',
            'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan ?? '',
            'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi ?? '',
            'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang ?? '',
            'nilai_premi' => $lostEvent->nilai_premi ?? 0,
            'nilai_premi_formatted' => $this->formatCurrency($lostEvent->nilai_premi ?? 0),
            'nilai_klaim' => $lostEvent->nilai_klaim ?? 0,
            'nilai_klaim_formatted' => $this->formatCurrency($lostEvent->nilai_klaim ?? 0),
            'realization_percentage' => $percentageFormatted,
            'type' => $lostEvent->detected_type ?? '',
        ];
    }

    return $data;
}

/**
 * Export Lost Event ke Excel
 */
private function exportLostEventExcel($data, $year, $departmentName)
{
    $filename = "Lost_Event_Report_{$departmentName}_{$year}_" . time() . ".xlsx";

    return Excel::download(
        new \App\Exports\LostEventExport($data, $year, $departmentName),
        $filename
    );
}

/**
 * Export Lost Event ke PDF
 */
private function exportLostEventPdf($data, $year, $departmentName)
{
    $filename = "Lost_Event_Report_{$departmentName}_{$year}_" . time() . ".pdf";

    $pdf = Pdf::loadView('exports.lost_event_pdf', [
        'data' => $data,
        'year' => $year,
        'departmentName' => $departmentName,
    ]);

    $pdf->setPaper('A4', 'landscape');

    return $pdf->download($filename);
}


}
