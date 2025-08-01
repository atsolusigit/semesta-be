<?php

namespace App\Http\Controllers;

use App\Models\TrRiskMonthly;
use App\Models\TrRiskHeader;
use App\Models\MstHeatmap;
use App\Http\Controllers\UploadController;
use App\Models\TrRiskMonthlyUpload;
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

            // Remove null optional fields
            if (is_null($arr['target_option_position'] ?? null)) {
                unset($arr['target_option_position']);
            }
            if (is_null($arr['realization_option_position'] ?? null)) {
                unset($arr['realization_option_position']);
            }

            // Format uploads with filename
            $arr['uploads'] = collect($item->uploads)->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'filepath' => $upload->filepath,
                    'domain' => $upload->domain,
                    'filename' => basename($upload->filepath),
                ];
            });

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

        if (is_null($arr['target_option_position'] ?? null)) {
            unset($arr['target_option_position']);
        }
        if (is_null($arr['realization_option_position'] ?? null)) {
            unset($arr['realization_option_position']);
        }

        $arr['uploads'] = collect($data->uploads)->map(function ($upload) {
            return [
                'id' => $upload->id,
                'filepath' => $upload->filepath,
                'domain' => $upload->domain,
                'filename' => basename($upload->filepath),
            ];
        });

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

            if (is_null($arr['target_option_position'] ?? null)) {
                unset($arr['target_option_position']);
            }
            if (is_null($arr['realization_option_position'] ?? null)) {
                unset($arr['realization_option_position']);
            }

            $arr['uploads'] = collect($item->uploads)->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'filepath' => $upload->filepath,
                    'domain' => $upload->domain,
                    'filename' => basename($upload->filepath),
                ];
            });
            return $arr;
        });

        $followUpInfo = get_follow_up_info($header, $cleaned);

        return json(200, true, 'Data Ditemukan', 'Data monthly untuk header berhasil diambil.', [
            'header' => $header,
            'monthly_data' => $cleaned,
            'follow_up_info' => $followUpInfo
        ]);
    }

    public function updateResidualAndFinalize(Request $request, $id)
{
    $data = TrRiskMonthly::with('header')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize) {
        return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', ['id' => $id]);
    }

    // Validasi semua bulan sebelumnya harus sudah difinalisasi
    if ($data->month > 1) {
        $unfinalized = TrRiskMonthly::where('header_id', $data->header_id)
            ->where('month', '<', $data->month)
            ->where('is_finalize', false)
            ->pluck('month');

        if ($unfinalized->count() > 0) {
            return json(400, false, 'Finalisasi Ditolak', 'Bulan sebelumnya belum difinalisasi.', [
                'id' => $id,
                'unfinalized_months' => $unfinalized
            ]);
        }
    }

    // Generate tanggal default jika tidak diisi
    $autoGeneratedDates = generate_risk_monthly_dates($data->header->year, $data->month);

    $validationRules = [
        'status_risiko' => 'required|in:open,close',
        'start_date' => 'nullable|date',
        'expired_date' => 'nullable|date|after_or_equal:start_date',
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
    ];

    $validation = check_validation($request->all(), $validationRules);
    if ($validation[0] == 1) {
        return $validation[1];
    }

    $dateValidation = validate_risk_monthly_dates($request, $data->header->year, $data->month);
    if (!$dateValidation['valid']) {
        return json(422, false, 'Validasi Gagal', $dateValidation['message'], ['id' => $id]);
    }

    DB::beginTransaction();
    try {
        $residualRiskHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->residual_risk_level_dampak)
            ->where('kemungkinan', $request->residual_risk_level_kemungkinan)
            ->first();

        if (!$residualRiskHeatmap) {
            return json(400, false, 'Kombinasi Tidak Ditemukan', 'Kombinasi dampak dan kemungkinan tidak ditemukan.', ['id' => $id]);
        }

        // Auto Note untuk Desember jika status open
        $noteTambahan = ($data->month == 12 && $request->status_risiko === 'open')
            ? 'Tindak lanjut di tahun berikutnya.'
            : null;

        $realizationNoteFinal = $request->realization_notes;
        if ($noteTambahan) {
            $realizationNoteFinal = $request->realization_notes
                ? $request->realization_notes . ' | ' . $noteTambahan
                : $noteTambahan;
        }

        $updateData = [
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date ?? $autoGeneratedDates['start_date'],
            'expired_date' => $request->expired_date ?? $autoGeneratedDates['expired_date'],
            'realization_quantitative' => $request->realization_quantitative,
            'realization_option' => $request->realization_option,
            'realization_note' => $realizationNoteFinal,
            'realization_option_position' => $request->realization_option_position,
            'target_option' => $request->target_option,
            'target_option_position' => $request->target_option_position,
            'residual_risk_level_dampak' => $request->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $request->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $residualRiskHeatmap->result,
            'residual_risk_level_risiko' => $residualRiskHeatmap->riskRange->name ?? null,
        ];

        // Optional: proses field tahunan jika valid
        if (should_process_yearly_residual_risk($request)) {
            $yearlyData = process_yearly_residual_risk($request);
            if ($yearlyData) {
                $updateData = array_merge($updateData, $yearlyData);
            }
        }

        $data->update($updateData);

        // Finalisasi otomatis
        $data->is_finalize = true;
        $data->finalized_at = Carbon::now();
        $data->finalized_by = auth()->id() ?? null;
        $data->save();

        // Upload dokumen
        if ($request->has('uploaded_files')) {
            process_risk_monthly_file_uploads($request->uploaded_files, $data);
        }

        DB::commit();

        $warnings = [];
        if ($data->month == 12 && $data->status_risiko === 'open') {
            $warnings[] = "Status Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.";
        }

        $data->load(['realizationOption:id,name,position', 'targetOption:id,name,position']);
        $data->makeHidden(['realization_option_position', 'target_option_position']);

        $responseData = $data->toArray();
        $responseData['id'] = $data->id;
        $responseData['warnings'] = $warnings;

        return json(200, true, 'Berhasil Diperbarui & Difinalisasi', 'Data berhasil disimpan dan difinalisasi.', $responseData, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diproses', 'Terjadi kesalahan sistem.', ['id' => $id, 'error' => $e->getMessage()]);
    }
}


    public function updateResidual(Request $request, $id)
{
    $data = TrRiskMonthly::with('header')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize) {
        return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', ['id' => $id]);
    }

    // Validasi semua bulan sebelumnya harus sudah difinalisasi
    if ($data->month > 1) {
        $unfinalized = TrRiskMonthly::where('header_id', $data->header_id)
            ->where('month', '<', $data->month)
            ->where('is_finalize', false)
            ->pluck('month');

        if ($unfinalized->count() > 0) {
            return json(400, false, 'Finalisasi Ditolak', 'Bulan sebelumnya belum difinalisasi.', [
                'id' => $id,
                'unfinalized_months' => $unfinalized
            ]);
        }
    }

    // Auto-generate tanggal default jika tidak disediakan
    $autoGeneratedDates = generate_risk_monthly_dates($data->header->year, $data->month);

    $validationRules = [
        'status_risiko' => 'required|in:open,close',
        'start_date' => 'nullable|date',
        'expired_date' => 'nullable|date|after_or_equal:start_date',
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
    ];

    $validation = check_validation($request->all(), $validationRules);
    if ($validation[0] == 1) {
        return $validation[1];
    }

    // Validasi tanggal sesuai tahun & bulan
    $dateValidation = validate_risk_monthly_dates($request, $data->header->year, $data->month);
    if (!$dateValidation['valid']) {
        return json(422, false, 'Validasi Gagal', $dateValidation['message'], ['id' => $id]);
    }

    DB::beginTransaction();
    try {
        // Ambil heatmap residual
        $residualRiskHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->residual_risk_level_dampak)
            ->where('kemungkinan', $request->residual_risk_level_kemungkinan)
            ->first();

        if (!$residualRiskHeatmap) {
            return json(400, false, 'Kombinasi Tidak Ditemukan', 'Kombinasi dampak dan kemungkinan tidak ditemukan.', ['id' => $id]);
        }

        $updateData = [
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date ?? $autoGeneratedDates['start_date'],
            'expired_date' => $request->expired_date ?? $autoGeneratedDates['expired_date'],
            'realization_quantitative' => $request->realization_quantitative,
            'realization_option' => $request->realization_option,

            'realization_note' => ($data->month == 12 && $request->status_risiko === 'open')
            ? trim(($request->realization_notes ? $request->realization_notes . ' | ' : '') . 'Tindak lanjut di tahun berikutnya.')
            : $request->realization_notes,

            'realization_option_position' => $request->realization_option_position,
            'target_option' => $request->target_option,
            'target_option_position' => $request->target_option_position,
            'residual_risk_level_dampak' => $request->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $request->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $residualRiskHeatmap->result,
            'residual_risk_level_risiko' => $residualRiskHeatmap->riskRange->name ?? null,
        ];

        // Proses data risiko residual tahunan jika lengkap
        if (should_process_yearly_residual_risk($request)) {
            $yearlyData = process_yearly_residual_risk($request);
            if ($yearlyData) {
                $updateData = array_merge($updateData, $yearlyData);
            }
        }

        $data->update($updateData);

        // Tangani file upload jika dikirim
        if ($request->has('uploaded_files')) {
            process_risk_monthly_file_uploads($request->uploaded_files, $data);
        }

        DB::commit();

        // Berikan warning khusus jika bulan Desember masih open
        $warnings = [];
        if ($data->month == 12 && $request->status_risiko === 'open') {
            $warnings[] = 'Perhatian: Status Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.';
        }

        $data->load(['realizationOption:id,name,position', 'targetOption:id,name,position']);
        $data->makeHidden(['realization_option_position', 'target_option_position']);

        $responseData = $data->toArray();
        $responseData['id'] = $data->id;
        $responseData['warnings'] = $warnings;

        return json(200, true, 'Berhasil Diperbarui', 'Data residual risk berhasil diperbarui.', $responseData, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diperbarui', 'Terjadi kesalahan sistem.', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);
    }
}

    public function updateQuantitative(Request $request, $id)
    {
        $monthly = TrRiskMonthly::with('header')->find($id);
        if (!$monthly) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
        }

        if ($monthly->is_finalize) {
            return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', null);
        }

        $validationRules = [
            'target_quantitative' => 'required|numeric',
            'target_notes' => 'nullable|string',
        ];

        $validation = check_validation($request->all(), $validationRules);
        if ($validation[0] == 1) {
            return $validation[1];
        }

        DB::beginTransaction();
        try {
            $monthly->update([
                'target_quantitative' => $request->target_quantitative,
                'target_notes' => $request->target_notes,
            ]);

            DB::commit();

            $monthly->load('header');
            $result = clean_monthly_data($monthly->toArray());

            return json(200, true, 'Berhasil Diupdate', 'Data target kuantitatif berhasil diupdate.', $result);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500, false, 'Gagal Diupdate', 'Terjadi kesalahan pada sistem.', $e->getMessage());
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

            $data->load(['realizationOption:id,name,position', 'targetOption:id,name,position']);

            // Handle file uploads
            if ($request->has('uploaded_files')) {
                process_risk_monthly_file_uploads($request->uploaded_files, $data);
            }

            DB::commit();

            $dataArray = clean_monthly_data($data->toArray());

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

        $validationErrors = [];
        $decemberOpenRisks = [];

        foreach ($monthlyData as $monthly) {
            $validationResult = validate_monthly_data_for_finalization($monthly);
            if (!$validationResult['valid']) {
                $validationErrors[] = "Bulan {$monthly->month}: " . $validationResult['message'];
            }

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

            // Handle file uploads
            if ($request->has('uploaded_files')) {
                $firstMonthly = $monthlyData->first();
                process_risk_monthly_file_uploads($request->uploaded_files, $firstMonthly);
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

        if (empty($request->monthly_data)) {
            return json(400, false, 'Data Kosong', 'Data perbulan tidak boleh kosong.', ['header_id' => $headerId]);
        }

        // Check if request has month field
        $hasMonthField = collect($request->monthly_data)->first() && isset(collect($request->monthly_data)->first()['month']);

        // Enhanced validation for target_quantitative using existing helper
        $bulkValidation = validate_bulk_quantitative_data($request->monthly_data, $hasMonthField, $headerId);
        if (!$bulkValidation['valid']) {
            return json(400, false, $bulkValidation['title'], $bulkValidation['message'], $bulkValidation['data']);
        }

        // Basic validation rules
        if ($hasMonthField) {
            $validationRules = [
                'monthly_data' => 'required|array|min:1|max:12',
                'monthly_data.*.month' => 'required|integer|min:1|max:12',
                'monthly_data.*.target_quantitative' => 'required|numeric|min:0',
                'monthly_data.*.target_notes' => 'nullable|string|max:1000',
                'require_all_months' => 'nullable|boolean',
                'update_mode' => 'nullable|string|in:selective,complete',
            ];
        } else {
            $validationRules = [
                'monthly_data' => 'required|array|min:1|max:12',
                'monthly_data.*.target_quantitative' => 'required|numeric|min:0',
                'monthly_data.*.target_notes' => 'nullable|string|max:1000',
                'require_all_months' => 'nullable|boolean',
                'update_mode' => 'nullable|string|in:selective,complete',
            ];

            if ($request->require_all_months === true) {
                $validationRules['monthly_data'] = 'required|array|size:12';
            }
        }

        $validation = check_validation($request->all(), $validationRules);
        if ($validation[0] == 1) {
            return $validation[1];
        }

        $updateMode = $request->update_mode ?? 'complete';

        // Process monthly data
        $processedData = process_bulk_monthly_data($request->monthly_data, $hasMonthField);

        // Get existing monthly data
        $existingMonthly = TrRiskMonthly::where('header_id', $headerId)->get()->keyBy('month');

        // Validate finalization and completeness
        $bulkValidationResult = validate_bulk_monthly_constraints(
            $processedData['monthly_data'],
            $existingMonthly,
            $request->require_all_months,
            $headerId
        );

        if (!$bulkValidationResult['valid']) {
            return json(400, false, $bulkValidationResult['title'], $bulkValidationResult['message'], $bulkValidationResult['data']);
        }

        $warnings = $bulkValidationResult['warnings'] ?? [];

        if (!validate_header_year($header->year)) {
            return json(400, false, 'Tahun Tidak Valid', 'Tahun pada header tidak valid untuk pembuatan tanggal.', [
                'header_id' => $headerId,
                'year' => $header->year
            ]);
        }

        DB::beginTransaction();
        try {
            $result = execute_bulk_quantitative_update(
                $processedData['monthly_data'],
                $existingMonthly,
                $header,
                $updateMode
            );

            DB::commit();

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

    public function uploadDocument(Request $request, $monthlyId)
    {
        $monthly = TrRiskMonthly::with('header')->find($monthlyId);
        if (!$monthly) {
            return json(404, false, 'Not Found', 'Data risk monthly tidak ditemukan.', null);
        }

        if ($monthly->is_finalize) {
            return json(400, false, 'Data Sudah Difinalisasi', 'Data sudah difinalisasi dan tidak bisa diubah.', null);
        }

        $validationRules = [
            'file' => 'required|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'domain' => 'nullable|string|max:255',
        ];

        $validation = check_validation($request->all(), $validationRules);
        if ($validation[0] == 1) {
            return $validation[1];
        }

        try {
            $uploadController = new UploadController();
            $upload = $uploadController->singleUpload($request);

            $response = $upload instanceof \Illuminate\Http\JsonResponse
                ? json_decode($upload->getContent(), true)
                : null;

            if (!($response['status'] ?? false)) {
                return $upload;
            }

            $fileUrl = $response['data'];
            $originalName = $request->file('file')->getClientOriginalName();

            $responseData = [
                'filepath' => $fileUrl,
                'domain' => $request->domain ?? $originalName,
                'filename' => basename($fileUrl),
            ];

            return json(200, true, 'Berhasil Upload', 'File berhasil diupload. Silakan simpan atau finalisasi untuk menyimpan file ini ke sistem.', $responseData);

        } catch (\Throwable $e) {
            return json(500, false, 'Gagal Upload', 'Terjadi kesalahan saat upload file.', $e->getMessage());
        }
    }

    public function checkFollowUpStatus($headerId)
    {
        $header = TrRiskHeader::find($headerId);
        if (!$header) {
            return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        $monthlyData = TrRiskMonthly::where('header_id', $headerId)->get();
        $followUpInfo = get_follow_up_info($header, $monthlyData);

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
