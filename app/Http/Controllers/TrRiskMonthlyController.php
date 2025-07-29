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

    $cleaned = $data->map(function ($item) {
        $arr = collect($item)->toArray();

        // Hapus jika null
        if (is_null($arr['target_option_position'])) unset($arr['target_option_position']);
        if (is_null($arr['realization_option_position'])) unset($arr['realization_option_position']);

        return $arr;
    });

    return json(200, true, 'List data', 'Data risk monthly berhasil diambil.', $cleaned);
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

    $arr = collect($data)->toArray();

    if (is_null($arr['target_option_position'])) unset($arr['target_option_position']);
    if (is_null($arr['realization_option_position'])) unset($arr['realization_option_position']);

    return json(200, true, 'Data Ditemukan', 'Detail data risk monthly berhasil diambil.', $arr);
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

    $cleaned = $data->map(function ($item) {
        $arr = collect($item)->toArray();

        if (is_null($arr['target_option_position'])) unset($arr['target_option_position']);
        if (is_null($arr['realization_option_position'])) unset($arr['realization_option_position']);

        return $arr;
    });

    $followUpInfo = get_follow_up_info($header, $cleaned);

    return json(200, true, 'Data Ditemukan', 'Data monthly untuk header berhasil diambil.', [
        'header' => $header,
        'monthly_data' => $cleaned,
        'follow_up_info' => $followUpInfo
    ]);
}


// Function 1: Update Residual Risk Data
public function updateResidual(Request $request, $id)
{
    $data = TrRiskMonthly::with('header')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', ['id' => $id]);
    }

    // Check if data is finalized
    if ($data->is_finalize) {
        return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', ['id' => $id]);
    }

    // NEW: Validate January finalization for other months
    if ($data->month > 1) { // If current month is not January
        $januaryData = TrRiskMonthly::where('header_id', $data->header_id)
            ->where('month', 1)
            ->first();

        if (!$januaryData) {
            return json(400, false, 'Data Januari Tidak Ditemukan', 'Data bulan Januari belum tersedia.', ['id' => $id]);
        }

        if (!$januaryData->is_finalize) {
            return json(400, false, 'Akses Ditolak', 'Bulan Januari harus difinalisasi terlebih dahulu sebelum mengakses bulan lain.', [
                'id' => $id,
                'current_month' => $data->month,
                'january_finalized' => false
            ]);
        }
    }

    // Enhanced validation with better date rules
    $validator = Validator::make($request->all(), [
        'status_risiko' => 'required|in:open,close',
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
        'realization_option' => 'nullable|numeric|exists:mst_option,id',
        'realization_notes' => 'nullable|string',
        'realization_option_position' => 'nullable|string',
        'target_option' => 'nullable|numeric|exists:mst_option,id',
        'target_option_position' => 'nullable|string',
        'residual_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_risk_satutahun_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
        'residual_risk_satutahun_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
    ]);

    if ($validator->fails()) {
        $errorData = ['id' => $id]; // Put id first
        $errorData = array_merge($errorData, $validator->errors()->toArray()); // Merge with validation errors
        return json(422, false, 'Validasi Gagal', $validator->errors()->first(), $errorData);
    }

    DB::beginTransaction();
    try {
        // Calculate risk position based on dampak and kemungkinan
        $residualRiskHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->residual_risk_level_dampak)
            ->where('kemungkinan', $request->residual_risk_level_kemungkinan)
            ->first();

        if (!$residualRiskHeatmap) {
            return json(400, false, 'Kombinasi Tidak Ditemukan', 'Kombinasi dampak dan kemungkinan tidak ditemukan.', ['id' => $id]);
        }

        $updateData = [
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date,
            'expired_date' => $request->expired_date,
            'realization_quantitative' => $request->realization_quantitative,
            'realization_option' => $request->realization_option,
            'realization_note' => $request->realization_notes, // Map to correct field name
            'realization_option_position' => $request->realization_option_position,
            'target_option' => $request->target_option,
            'target_option_position' => $request->target_option_position,
            'residual_risk_level_dampak' => $request->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $request->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $residualRiskHeatmap->result,
            'residual_risk_level_risiko' => $residualRiskHeatmap->riskRange->name ?? null,
        ];

        // Calculate yearly risk if provided
        if ($request->residual_risk_satutahun_level_dampak && $request->residual_risk_satutahun_level_kemungkinan) {
            $residualRiskSatutahunHeatmap = MstHeatmap::with('riskRange')
                ->where('dampak', $request->residual_risk_satutahun_level_dampak)
                ->where('kemungkinan', $request->residual_risk_satutahun_level_kemungkinan)
                ->first();

            if ($residualRiskSatutahunHeatmap) {
                $updateData['residual_risk_satutahun_level_dampak'] = $request->residual_risk_satutahun_level_dampak;
                $updateData['residual_risk_satutahun_level_kemungkinan'] = $request->residual_risk_satutahun_level_kemungkinan;
                $updateData['residual_risk_satutahun_posisi_risiko'] = $residualRiskSatutahunHeatmap->result;
                $updateData['residual_risk_satutahun_level_risiko'] = $residualRiskSatutahunHeatmap->riskRange->name ?? null;
            }
        }

        $data->update($updateData);

        // Load relationships for response
        $data->load([
            'realizationOption:id,name,position',
            'targetOption:id,name,position',
        ]);

        // Hide unnecessary fields from response
        $data->makeHidden([
            'realization_option_position',
            'target_option_position'
        ]);

        DB::commit();

        // Return with follow-up warning if December and still open
        $warnings = [];
        if ($data->month == 12 && $request->status_risiko == 'open') {
            $warnings[] = 'Perhatian: Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.';
        }

        // Prepare response data with id included
        $responseData = $data->toArray();
        $responseData['id'] = $data->id; // Explicitly add id to response

        return json(200, true, 'Berhasil Diperbarui', 'Data residual risk berhasil diperbarui.', $responseData, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diperbarui', 'Terjadi kesalahan sistem.', ['id' => $id, 'error' => $e->getMessage()]);
    }
}

public function updateQuantitative(Request $request, $id)
{
    $monthly = TrRiskMonthly::with('header')->find($id);
    if (!$monthly) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    // Check if data is finalized
    if ($monthly->is_finalize) {
        return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', null);
    }

    $validator = Validator::make($request->all(), [
        'target_quantitative' => 'required|numeric',
        'target_notes' => 'nullable|string',
    ]);

    if ($validator->fails()) {
    $errors = $validator->errors()->toArray();
    $firstField = array_key_first($errors);
    $firstMessage = $errors[$firstField][0];

    // Tambahkan ID bulanan ke dalam response
    $errorsWithId = array_merge(['id' => $monthly->id], $errors);

    return json(422, false, 'Validasi Gagal', $firstMessage, $errorsWithId);
}

    DB::beginTransaction();
    try {
        $monthly->update([
            'target_quantitative' => $request->target_quantitative,
            'target_notes' => $request->target_notes,
        ]);

        DB::commit();

        // Reload with relation
        $monthly->load('header');

        // Ubah ke array dan filter field null tertentu
        $result = collect($monthly->toArray())->filter(function ($value, $key) {
            return !in_array($key, ['target_option_position', 'realization_option_position']) || !is_null($value);
        });

        return json(200, true, 'Berhasil Diupdate', 'Data target kuantitatif berhasil diupdate.', $result);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diupdate', 'Terjadi kesalahan sistem.', $e->getMessage());
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

        $data->load([
            'realizationOption:id,name,position',
            'targetOption:id,name,position',
        ]);

        DB::commit();

        $dataArray = $data->toArray();

        // Hapus key null yang tidak diinginkan
        if (is_null($dataArray['target_option_position'] ?? null)) {
            unset($dataArray['target_option_position']);
        }
        if (is_null($dataArray['realization_option_position'] ?? null)) {
            unset($dataArray['realization_option_position']);
        }

        $warnings = [];
        if ($data->month == 12 && $data->status_risiko === 'open') {
            $warnings[] = "Risiko di bulan Desember masih open. Ini akan menjadi tindak lanjut di tahun " . ($data->header->year + 1);
        }

        return json(200, true, 'Berhasil Difinalisasi', 'Data risk monthly berhasil difinalisasi.', $dataArray, $warnings);

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

    public function bulkUpdateQuantitative(Request $request, $headerId)
{
    $header = TrRiskHeader::find($headerId);
    if (!$header) {
        return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', ['header_id' => $headerId]);
    }

    // Validasi input untuk 12 bulan
    $validator = Validator::make($request->all(), [
        'monthly_data' => 'required|array|min:1|max:12',
        'monthly_data.*.month' => 'required|integer|min:1|max:12',
        'monthly_data.*.target_quantitative' => 'required|numeric',
        'monthly_data.*.target_notes' => 'nullable|string|max:1000',
        'require_all_months' => 'nullable|boolean', // Optional: force complete 12 months
    ]);

    if ($validator->fails()) {
        $errorData = ['header_id' => $headerId];
        $errorData = array_merge($errorData, $validator->errors()->toArray());
        return json(400, false, 'Validasi Gagal', $validator->errors()->first(), $errorData);
    }

    // Ambil data monthly yang sudah ada untuk header ini
    $existingMonthly = TrRiskMonthly::where('header_id', $headerId)
        ->get()
        ->keyBy('month');

    // Validasi: cek apakah ada data yang sudah difinalisasi
    $finalizedMonths = [];
    foreach ($request->monthly_data as $monthData) {
        $month = $monthData['month'];
        if (isset($existingMonthly[$month]) && $existingMonthly[$month]->is_finalize) {
            $finalizedMonths[] = $month;
        }
    }

    if (!empty($finalizedMonths)) {
        return json(400, false, 'Data Sudah Difinalisasi', 'Bulan berikut sudah difinalisasi dan tidak bisa diubah: ' . implode(', ', $finalizedMonths), [
            'header_id' => $headerId,
            'finalized_months' => $finalizedMonths
        ]);
    }

    // Validasi: pastikan tidak ada duplikasi bulan dalam request
    $requestedMonths = collect($request->monthly_data)->pluck('month')->toArray();
    if (count($requestedMonths) !== count(array_unique($requestedMonths))) {
        return json(400, false, 'Duplikasi Bulan', 'Terdapat duplikasi bulan dalam data yang dikirim.', [
            'header_id' => $headerId,
            'requested_months' => $requestedMonths
        ]);
    }

    // Validasi: cek kelengkapan 12 bulan jika diminta
    if ($request->require_all_months === true) {
        $allMonths = range(1, 12);
        $missingMonths = array_diff($allMonths, $requestedMonths);

        if (!empty($missingMonths)) {
            return json(400, false, 'Data Tidak Lengkap', 'Semua bulan (1-12) harus diisi. Bulan yang belum diisi: ' . implode(', ', $missingMonths), [
                'header_id' => $headerId,
                'missing_months' => $missingMonths,
                'provided_months' => $requestedMonths
            ]);
        }
    }

    // Validasi: peringatan jika kurang dari 12 bulan (tidak wajib, hanya info)
    $warnings = [];
    if (count($requestedMonths) < 12 && $request->require_all_months !== true) {
        $allMonths = range(1, 12);
        $missingMonths = array_diff($allMonths, $requestedMonths);
        $warnings[] = 'Peringatan: Hanya ' . count($requestedMonths) . ' dari 12 bulan yang akan diupdate. Bulan yang tidak diupdate: ' . implode(', ', $missingMonths);
    }

    DB::beginTransaction();
    try {
        $updatedData = [];
        $createdData = [];

        foreach ($request->monthly_data as $monthData) {
            $month = $monthData['month'];

            if (isset($existingMonthly[$month])) {
                // Update existing data
                $monthly = $existingMonthly[$month];
                $monthly->update([
                    'target_quantitative' => $monthData['target_quantitative'],
                    'target_notes' => $monthData['target_notes'] ?? null,
                ]);
                $monthly->load('header');
                $updatedData[] = $this->cleanMonthlyData($monthly);
            } else {
                // Create new data (jika belum ada)
                $monthly = TrRiskMonthly::create([
                    'header_id' => $headerId,
                    'month' => $month,
                    'target_quantitative' => $monthData['target_quantitative'],
                    'target_notes' => $monthData['target_notes'] ?? null,
                    'is_finalize' => false,
                    // Set default values untuk field yang required
                    'status_risiko' => 'open',
                    'start_date' => Carbon::create($header->year, $month, 1)->startOfMonth(),
                    'expired_date' => Carbon::create($header->year, $month, 1)->endOfMonth(),
                ]);
                $monthly->load('header');
                $createdData[] = $this->cleanMonthlyData($monthly);
            }
        }

        DB::commit();

        $result = [
            'header_id' => $headerId,
            'updated_count' => count($updatedData),
            'created_count' => count($createdData),
            'updated_data' => $updatedData,
            'created_data' => $createdData,
            'total_processed' => count($updatedData) + count($createdData)
        ];

        $message = "Berhasil memproses {$result['total_processed']} data. ";
        $message .= "Updated: {$result['updated_count']}, Created: {$result['created_count']}.";

        return json(200, true, 'Berhasil Bulk Update', $message, $result, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Bulk Update', 'Terjadi kesalahan sistem.', [
            'header_id' => $headerId,
            'error' => $e->getMessage()
        ]);
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
