<?php

namespace App\Http\Controllers;
use App\Models\TrRiskMonthly;
use App\Models\TrRiskHeader;
use App\Models\TrRiskMonthlyEntry;
use App\Models\MstHeatmap;
use App\Http\Controllers\UploadController;
use App\Models\TrRiskMonthlyUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;


class TrRiskMonthlyEntryController extends Controller
{

public function updateQuantitative(Request $request, $id)
{
    $data = TrRiskMonthlyEntry::with(['header', 'monthly'])->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly entry tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize || $data->monthly?->is_finalize) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', ['id' => $id]);
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
        $data->update([
            'target_quantitative' => $request->target_quantitative,
            'target_notes' => $request->target_notes,
        ]);

        DB::commit();

        $data->load('monthly.header.uploads');
        $data->tr_risk_header_entry_id = $headerEntryId ?? null;
        $result = clean_monthly_data($data->toArray());

        $monthlyUploads = $data->monthly->header && $data->monthly->header->uploads
            ? $data->monthly->header->uploads->where('risk_monthly_id', $data->monthly_id)
            : collect([]);

        $result['uploaded_files'] = $monthlyUploads->count() > 0
            ? $monthlyUploads->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain,
                ];
            })->values()->toArray()
            : [];

        unset($result['monthly']['header']['uploads']);

        return json(200, true, 'Berhasil Diupdate', 'Data target kuantitatif berhasil diupdate.', $result);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diupdate', 'Terjadi kesalahan pada sistem.', $e->getMessage());
    }
}

// Additional methods for handling bulk updates, residual updates, and finalization can be added here as needed.
//   public function bulkUpdateQuantitative(Request $request, $headerId)
// {
//     $header = TrRiskHeader::with('monthlyData', 'uploads')->find($headerId);
//     if (!$header) {
//         return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', ['header_id' => $headerId]);
//     }

//     if (empty($request->monthly_data)) {
//         return json(400, false, 'Data Kosong', 'Data perbulan tidak boleh kosong.', ['header_id' => $headerId]);
//     }

//     $validationRules = [
//         'monthly_data' => 'required|array|min:1|max:12',
//         'monthly_data.*.target_quantitative' => 'required|numeric|min:0',
//         'monthly_data.*.target_notes' => 'nullable|string|max:1000',
//         'require_all_months' => 'nullable|boolean',
//     ];

//     if ($request->require_all_months === true) {
//         $validationRules['monthly_data'] = 'required|array|size:12';
//     }

//     $validation = check_validation($request->all(), $validationRules);
//     if ($validation[0] == 1) {
//         return $validation[1];
//     }

//     $monthlyIdMap = $header->monthlyData->pluck('id', 'month'); // [1 => id1, 2 => id2, ...]

//     DB::beginTransaction();
//     try {
//         $createdEntries = [];

//         foreach ($request->monthly_data as $index => $data) {
//             $month = $index + 1;

//             if (!isset($monthlyIdMap[$month])) {
//                 DB::rollBack();
//                 return json(400, false, 'Monthly ID Tidak Ditemukan', "Data bulan ke-{$month} tidak ditemukan pada header ini.", [
//                     'header_id' => $headerId,
//                     'month' => $month
//                 ]);
//             }

//             $monthlyId = $monthlyIdMap[$month];

//             $entry = new TrRiskMonthlyEntry();
//             $entry->header_id = $headerId;
//             $entry->monthly_id = $monthlyId;
//             $entry->month = $month;
//             $entry->target_quantitative = $data['target_quantitative'];
//             $entry->target_notes = $data['target_notes'] ?? null;
//             $entry->created_at = Carbon::create($header->year, $month, 1);
//             $entry->updated_at = now();
//             $entry->save();

//             $entryArray = clean_monthly_data($entry->toArray());

//             // Ambil file upload jika ada
//             $monthlyUploads = $header->uploads ? $header->uploads->where('risk_monthly_id', $monthlyId) : collect([]);

//             $entryArray['uploaded_files'] = $monthlyUploads->map(function ($file) {
//                 return [
//                     'filepath' => $file->filepath,
//                     'domain' => $file->domain,
//                 ];
//             })->values()->toArray();

//             $createdEntries[] = $entryArray;
//         }

//         DB::commit();

//         return json(200, true, 'Berhasil Bulk Update', 'Berhasil menyimpan data per bulan.', [
//             'header_id' => $headerId,
//             'created_entries' => $createdEntries,
//             'created_count' => count($createdEntries)
//         ]);
//     } catch (\Throwable $e) {
//         DB::rollBack();
//         return json(500, false, 'Gagal Bulk Update', 'Terjadi kesalahan sistem.', [
//             'header_id' => $headerId,
//             'error' => $e->getMessage()
//         ]);
//     }
// }

    public function updateResidual(Request $request, $id)
{
     $data = TrRiskMonthlyEntry::with(['header', 'monthly'])->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly entry tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize || $data->monthly?->is_finalize) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', ['id' => $id]);
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

    $dateValidation = validate_risk_monthly_dates($request, $data->header->year, $data->month);
    if (!$dateValidation['valid']) {
        return json(400, false, 'Validasi Gagal', $dateValidation['message'], ['id' => $id]);
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

        if (should_process_yearly_residual_risk($request)) {
            $yearlyData = process_yearly_residual_risk($request);
            if ($yearlyData) {
                $updateData = array_merge($updateData, $yearlyData);
            }
        }

        $data->update($updateData);

        if ($request->has('uploaded_files')) {
            process_risk_monthly_entry_file_uploads($request->uploaded_files, $data);
        }

        DB::commit();

        $warnings = [];
        if ($data->month == 12 && $request->status_risiko === 'open') {
            $warnings[] = 'Perhatian: Status Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.';
        }

        $data->load(['realizationOption:id,name,position', 'targetOption:id,name,position','uploads']);
        $data->makeHidden(['realization_option_position', 'target_option_position']);

        $responseData = [
            'id' => $data->id,
            'header_id' => $data->header_id,
            'month' => $data->month,
            'risk_code' => $data->risk_code,
            'status_risiko' => $data->status_risiko,
            'process_code' => $data->process_code,
            'start_date' => $data->start_date,
            'expired_date' => $data->expired_date,
            'realization_quantitative' => $data->realization_quantitative,
            'realization_note' => $data->realization_note,
            'target_quantitative' => $data->target_quantitative,
            'target_notes' => $data->target_notes,
            'residual_risk_level_dampak' => $data->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $data->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $data->residual_risk_posisi_risiko,
            'residual_risk_level_risiko' => $data->residual_risk_level_risiko,
            'residual_risk_level_risiko_color' => $residualRiskHeatmap->riskRange->color ?? null,
            'is_finalize' => $data->is_finalize,
            'finalized_at' => $data->finalized_at,
            'finalized_by' => $data->finalized_by,
            'created_at' => $data->created_at,
            'updated_at' => $data->updated_at,
            'header' => $data->header,
            'uploaded_files' => $data->uploads->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain,
                ];
            }),
            'warnings' => $warnings,
        ];

        return json(200, true, 'Berhasil Diperbarui', 'Data residual risk berhasil diperbarui.', $responseData, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diperbarui', 'Terjadi kesalahan sistem.', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);
    }
}

  public function updateResidualAndFinalize(Request $request, $id)
{
    $data = TrRiskMonthlyEntry::with(['monthly', 'header'])->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly entry tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize || $data->monthly?->is_finalize) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', ['id' => $id]);
    }

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
            'is_finalize' => true,
            'finalized_at' => now(),
            'finalized_by' => auth()->id() ?? null,
        ];

        if (should_process_yearly_residual_risk($request)) {
            $yearlyData = process_yearly_residual_risk($request);
            if ($yearlyData) {
                $updateData = array_merge($updateData, $yearlyData);
            }
        }

        $data->update($updateData);

        if ($request->has('uploaded_files')) {
            process_risk_monthly_entry_file_uploads($request->uploaded_files, $data);
        }

        DB::commit();

        $warnings = [];
        if ($data->month == 12 && $request->status_risiko === 'open') {
            $warnings[] = 'Status Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.';
        }

        $data->load([
            'realizationOption:id,name,position',
            'targetOption:id,name,position',
            'uploads'
        ]);
        $data->makeHidden(['realization_option_position', 'target_option_position']);

        $responseData = [
            'id' => $data->id,
            'header_id' => $data->header_id,
            'month' => $data->month,
            'risk_code' => $data->risk_code,
            'status_risiko' => $data->status_risiko,
            'process_code' => $data->process_code,
            'start_date' => $data->start_date,
            'expired_date' => $data->expired_date,
            'realization_quantitative' => $data->realization_quantitative,
            'realization_note' => $data->realization_note,
            'target_quantitative' => $data->target_quantitative,
            'target_notes' => $data->target_notes,
            'residual_risk_level_dampak' => $data->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $data->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $data->residual_risk_posisi_risiko,
            'residual_risk_level_risiko' => $data->residual_risk_level_risiko,
            'residual_risk_level_risiko_color' => $residualRiskHeatmap->riskRange->color ?? null,
            'is_finalize' => $data->is_finalize,
            'finalized_at' => $data->finalized_at,
            'finalized_by' => $data->finalized_by,
            'created_at' => $data->created_at,
            'updated_at' => $data->updated_at,
            'header' => $data->header,
            'uploaded_files' => $data->uploads->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain,
                ];
            }),
            'warnings' => $warnings,
        ];

        return json(200, true, 'Berhasil Diperbarui & Difinalisasi', 'Data berhasil disimpan dan difinalisasi.', $responseData, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diproses', 'Terjadi kesalahan sistem.', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);
    }
}
}
