<?php

namespace App\Http\Controllers;

use App\Models\TrRiskMonthly;
use App\Models\TrRiskHeader;
use App\Models\MstHeatmap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TrRiskMonthlyController extends Controller
{
    public function index()
{
    $data = TrRiskMonthly::with([
        'header',
        'riskCode:id,name',
        'rrLevelDampak',
        'rrLevelKemungkinan',
        'rrYearLevelDampak',
        'rrYearLevelKemungkinan',
        'mitigations',
        'uploads',
    ])->orderBy('header_id')->orderBy('month')->get();

    return json(200, true, 'List data', 'Data risk monthly berhasil diambil.', $data);
}

public function show($id)
{
    $data = TrRiskMonthly::with([
        'header',
        'riskCode:id,name',
        'rrLevelDampak',
        'rrLevelKemungkinan',
        'rrYearLevelDampak',
        'rrYearLevelKemungkinan',
        'mitigations',
        'uploads',
    ])->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    return json(200, true, 'Data Ditemukan', 'Detail data risk monthly berhasil diambil.', $data);
}

    public function getByHeader($headerId)
{
    $header = TrRiskHeader::find($headerId);
    if (!$header) {
        return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
    }

    $data = TrRiskMonthly::with([
        'header',
        'riskCode:id,name',
        'rrLevelDampak',
        'rrLevelKemungkinan',
        'rrYearLevelDampak',
        'rrYearLevelKemungkinan',
        'mitigations',
        'uploads',
    ])->where('header_id', $headerId)
      ->orderBy('month')
      ->get();

    // Gunakan fungsi helper get_follow_up_info
    $followUpInfo = get_follow_up_info($header, $data);

    return json(200, true, 'Data Ditemukan', 'Data monthly untuk header berhasil diambil.', [
        'header' => $header,
        'monthly_data' => $data,
        'follow_up_info' => $followUpInfo
    ]);
}

    public function update(Request $request, $id)
    {
        $data = TrRiskMonthly::with('header')->find($id);
        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
        }

        // Check if data is finalized
        if ($data->is_finalize) {
            return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', null);
        }

        // Enhanced validation with better date rules
        $validator = Validator::make($request->all(), [
            'status_risiko' => 'required|in:open,close',
            // 'process_code' => 'required|integer',
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($data) {
                    $expectedStartDate = Carbon::create($data->header->year, $data->month, 1)->startOfMonth();
                    $inputDate = Carbon::parse($value)->startOfMonth();

                    if (!$inputDate->isSameMonth($expectedStartDate)) {
                        $fail('Start date harus dalam bulan ' . $expectedStartDate->format('F Y'));
                    }
                },
            ],
            'expired_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) use ($data) {
                    $expectedEndDate = Carbon::create($data->header->year, $data->month, 1)->endOfMonth();
                    $inputDate = Carbon::parse($value)->endOfMonth();

                    if (!$inputDate->isSameMonth($expectedEndDate)) {
                        $fail('Expired date harus dalam bulan ' . $expectedEndDate->format('F Y'));
                    }
                },
            ],

            'realization_quantitative' => 'nullable|numeric',
            'realization_option' => 'nullable|string',
            // 'realization_other' => 'nullable|string',
            'realization_note' => 'nullable|string',
            'realization_option_position' => 'nullable|string',

            'target_quantitative' => 'nullable|numeric',
            'target_option' => 'nullable|string',
            // 'target_other' => 'nullable|string',
            'target_notes' => 'nullable|string',
            'target_option_position' => 'nullable|string',

            'residual_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'residual_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',

            'residual_risk_satutahun_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
            'residual_risk_satutahun_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        DB::beginTransaction();
        try {
            // Calculate risk position based on dampak and kemungkinan
            $residualRiskHeatmap = MstHeatmap::with('riskRange')
                ->where('dampak', $request->residual_risk_level_dampak)
                ->where('kemungkinan', $request->residual_risk_level_kemungkinan)
                ->first();

            if (!$residualRiskHeatmap) {
                return json(400, false, 'Kombinasi Tidak Ditemukan', 'Kombinasi dampak dan kemungkinan tidak ditemukan.', null);
            }

            $updateData = $request->all();
            $updateData['residual_risk_posisi_risiko'] = $residualRiskHeatmap->result;
            $updateData['residual_risk_level_risiko'] = $residualRiskHeatmap->riskRange->name ?? null;

            // Calculate yearly risk if provided
            if ($request->residual_risk_satutahun_level_dampak && $request->residual_risk_satutahun_level_kemungkinan) {
                $residualRiskSatutahunHeatmap = MstHeatmap::with('riskRange')
                    ->where('dampak', $request->residual_risk_satutahun_level_dampak)
                    ->where('kemungkinan', $request->residual_risk_satutahun_level_kemungkinan)
                    ->first();

                if ($residualRiskSatutahunHeatmap) {
                    $updateData['residual_risk_satutahun_posisi_risiko'] = $residualRiskSatutahunHeatmap->result;
                    $updateData['residual_risk_satutahun_level_risiko'] = $residualRiskSatutahunHeatmap->riskRange->name ?? null;
                }
            }

            $data->fill($updateData);
            $data->save();

            DB::commit();

            // Return with follow-up warning if December and still open
            $response = $data;
            $warnings = [];

            if ($data->month == 12 && $request->status_risiko == 'open') {
                $warnings[] = 'Perhatian: Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.';
            }

            return json(200, true, 'Berhasil Diperbarui', 'Data risk monthly berhasil diperbarui.', $response, $warnings);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500, false, 'Gagal Diperbarui', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

   public function finalize(Request $request, $id)
{
    $data = TrRiskMonthly::with('header')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    if ($data->is_finalize) {
        return json(400, false, 'Sudah Difinalisasi', 'Data sudah difinalisasi sebelumnya.', null);
    }

    // Enhanced validation for finalization
    $validationResult = validate_monthly_data_for_finalization($data);
    if (!$validationResult['valid']) {
        return json(400, false, 'Data Tidak Lengkap', $validationResult['message'], $validationResult['missing_fields']);
    }

    DB::beginTransaction();
    try {
        $data->is_finalize = true;
        $data->finalized_at = Carbon::now();
        $data->finalized_by = auth()->id() ?? null;
        $data->save();

        DB::commit();

        // Convert to array before injecting custom key
        $dataArray = $data->toArray();

        if ($data->month == 12 && $data->status_risiko === 'open') {
            $dataArray['warnings'] = [
                "Risiko di bulan Desember masih open. Ini akan menjadi tindak lanjut di tahun " . ($data->header->year + 1)
            ];
        }

        return json(200, true, 'Berhasil Difinalisasi', 'Data risk monthly berhasil difinalisasi.', $dataArray);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Difinalisasi', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}


    public function finalizeAll(Request $request, $headerId)
    {
        $header = TrRiskHeader::find($headerId);
        if (!$header) {
            return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        $monthlyData = TrRiskMonthly::where('header_id', $headerId)
            ->where('is_finalize', false)
            ->orderBy('month')
            ->get();

        if ($monthlyData->isEmpty()) {
            return json(400, false, 'Tidak Ada Data', 'Tidak ada data yang dapat difinalisasi.', null);
        }

        // Enhanced validation for all monthly data
        $validationErrors = [];
        $decemberOpenRisks = [];

        foreach ($monthlyData as $monthly) {
            $validationResult = validate_monthly_data_for_finalization ($monthly);
            if (!$validationResult['valid']) {
                $validationErrors[] = "Bulan {$monthly->month}: " . $validationResult['message'];
            }

            // Check for December open risks
            if ($monthly->month == 12 && $monthly->status_risiko == 'open') {
                $decemberOpenRisks[] = "Risiko bulan Desember masih open dan akan menjadi tindak lanjut tahun " . ($header->year + 1);
            }
        }

        if (!empty($validationErrors)) {
            return json(400, false, 'Data Tidak Lengkap', 'Beberapa data belum lengkap.', $validationErrors);
        }

        DB::beginTransaction();
        try {
            $finalizedCount = 0;
            foreach ($monthlyData as $monthly) {
                $monthly->is_finalize = true;
                $monthly->finalized_at = Carbon::now();
                $monthly->finalized_by = auth()->id() ?? null;
                $monthly->save();
                $finalizedCount++;
            }

            DB::commit();

            $warnings = array_merge($decemberOpenRisks);

            return json(200, true, 'Berhasil Difinalisasi', "$finalizedCount data risk monthly berhasil difinalisasi.", null, $warnings);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500, false, 'Gagal Difinalisasi', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

    public function checkFollowUpStatus($headerId)
    {
        $header = TrRiskHeader::find($headerId);
        if (!$header) {
            return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        $monthlyData = TrRiskMonthly::where('header_id', $headerId)->get();
        $followUpInfo = get_follow_up_info ($header, $monthlyData);

        return json(200, true, 'Status Follow-up', $followUpInfo['message'], $followUpInfo);
    }

    public function getStatistics($headerId)
    {
        $header = TrRiskHeader::find($headerId);
        if (!$header) {
            return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        $monthlyData = TrRiskMonthly::where('header_id', $headerId)->get();

        $statistics = [
            'total_months' => $monthlyData->count(),
            'finalized_months' => $monthlyData->where('is_finalize', true)->count(),
            'unfinalized_months' => $monthlyData->where('is_finalize', false)->count(),
            'open_risks' => $monthlyData->where('status_risiko', 'open')->count(),
            'closed_risks' => $monthlyData->where('status_risiko', 'close')->count(),
            'completion_percentage' => $monthlyData->count() > 0
                ? round(($monthlyData->where('is_finalize', true)->count() / $monthlyData->count()) * 100, 2)
                : 0,
            'december_status' => $monthlyData->where('month', 12)->first()?->status_risiko ?? 'not_set',
            'follow_up_required' => check_if_follow_up_required($header, $monthlyData)
        ];

        return json(200, true, 'Statistik Data', 'Statistik risk monthly berhasil diambil.', $statistics);
    }

    public function destroy($id)
    {
        $data = TrRiskMonthly::find($id);
        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
        }

        if ($data->is_finalize) {
            return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa dihapus.', null);
        }

        try {
            $data->delete();
            return json(200, true, 'Berhasil Dihapus', 'Data risk monthly berhasil dihapus.', null);
        } catch (\Throwable $e) {
            return json(500, false, 'Gagal Dihapus', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

}
