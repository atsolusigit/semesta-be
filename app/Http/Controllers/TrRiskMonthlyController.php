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
        'entries.rrLevelDampak',
        'entries.rrLevelKemungkinan',
        'createdBy',
        'updatedBy',
    ])
    ->orderBy('header_id')
    ->orderBy('month')
    ->get();

    $cleaned = $data->map(function ($item) {
        $arr = collect($item)->toArray();

  // Format angka pada level monthly
        if (isset($arr['realization_quantitative']) && $arr['realization_quantitative']) {
            $arr['realization_quantitative'] = number_format((float)$arr['realization_quantitative'], 0, ',', '.');
        }
        if (isset($arr['target_quantitative']) && $arr['target_quantitative']) {
            $arr['target_quantitative'] = number_format((float)$arr['target_quantitative'], 0, ',', '.');
        }

        // Format angka pada header jika ada
        if (isset($arr['header'])) {
            if (isset($arr['header']['target_quantitative_satu_tahun']) && $arr['header']['target_quantitative_satu_tahun']) {
                $arr['header']['target_quantitative_satu_tahun'] = number_format((float)$arr['header']['target_quantitative_satu_tahun'], 0, ',', '.');
            }
            if (isset($arr['header']['biaya_perlakuan_risiko']) && $arr['header']['biaya_perlakuan_risiko']) {
                $arr['header']['biaya_perlakuan_risiko'] = number_format((float)$arr['header']['biaya_perlakuan_risiko'], 0, ',', '.');
            }
        }

        // Remove null optional fields
        if (is_null($arr['target_option_position'] ?? null)) {
            unset($arr['target_option_position']);
        }
        if (is_null($arr['realization_option_position'] ?? null)) {
            unset($arr['realization_option_position']);
        }

        // Format uploads
        $arr['uploaded_files'] = collect($item['uploads'])->map(function ($upload) {
            return [
                'id' => $upload['id'],
                'filepath' => $upload['filepath'],
                'domain' => $upload['domain'],
            ];
        });

        // Filter entries residual
        $residual = collect($item['entries'])->filter(function ($entry) {
            return isset($entry['residual_risk_level_dampak']) || isset($entry['residual_risk_satutahun_level_dampak']);
        })->values();

        $arr['residual_data'] = $residual->map(function ($entry) {
            return [
                'dampak_id' => $entry['residual_risk_level_dampak'],
                'kemungkinan_id' => $entry['residual_risk_level_kemungkinan'],
                'LevelDampak' => $entry['level_dampak'] ?? null,
                'LevelKemungkinan' => $entry['level_kemungkinan'] ?? null,
                'created_at' => $entry['created_at'],
            ];
        });

        // Filter entries quantitative
        $quantitative = collect($item['entries'])->filter(function ($entry) {
            return isset($entry['target_quantitative']) || isset($entry['realization_quantitative']);
        })->values();

        $arr['quantitative_data'] = $quantitative->map(function ($entry) {
            return [
                'id' => $entry['id'],
                'target_quantitative' => $entry['target_quantitative'],
                'realization_quantitative' => $entry['realization_quantitative'],
                'target_notes' => $entry['target_notes'],
                'realization_notes' => $entry['realization_note'], // sesuai field migration
                'created_at' => $entry['created_at'],
            ];
        });

        // Tambahkan created_by_name dan updated_by_name, bersihkan dengan clean_string()
        $arr['created_by_name'] = $item->createdBy ? clean_string(get_decrypted_username($item->createdBy)) : 'Unknown User';
        $arr['updated_by_name'] = $item->updatedBy ? clean_string(get_decrypted_username($item->updatedBy)) : 'Unknown User';

        return $arr;
    });

    // Bersihkan seluruh data hasil map dengan clean_recursive untuk jaga-jaga
    $cleaned = clean_recursive($cleaned->toArray());

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
        'entries.rrLevelDampak',
        'entries.rrLevelKemungkinan',
        'createdBy',
        'updatedBy',
    ])->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    $arr = collect($data)->toArray();

  // Format angka pada level monthly
        if (isset($arr['realization_quantitative']) && $arr['realization_quantitative']) {
            $arr['realization_quantitative'] = number_format((float)$arr['realization_quantitative'], 0, ',', '.');
        }
        if (isset($arr['target_quantitative']) && $arr['target_quantitative']) {
            $arr['target_quantitative'] = number_format((float)$arr['target_quantitative'], 0, ',', '.');
        }

        // Format angka pada header jika ada
        if (isset($arr['header'])) {
            if (isset($arr['header']['target_quantitative_satu_tahun']) && $arr['header']['target_quantitative_satu_tahun']) {
                $arr['header']['target_quantitative_satu_tahun'] = number_format((float)$arr['header']['target_quantitative_satu_tahun'], 0, ',', '.');
            }
            if (isset($arr['header']['biaya_perlakuan_risiko']) && $arr['header']['biaya_perlakuan_risiko']) {
                $arr['header']['biaya_perlakuan_risiko'] = number_format((float)$arr['header']['biaya_perlakuan_risiko'], 0, ',', '.');
            }
        }

    // Bersihkan field opsional jika null
    if (is_null($arr['target_option_position'] ?? null)) {
        unset($arr['target_option_position']);
    }
    if (is_null($arr['realization_option_position'] ?? null)) {
        unset($arr['realization_option_position']);
    }

    // Ambil data uploads dari entry.uploads
    $arr['uploaded_files'] = collect($data->entry?->uploads ?? [])->map(function ($upload) {
        return [
            'id' => $upload['id'],
            'filepath' => $upload->filepath,
            'domain' => $upload->domain,
        ];
    });

    // Ambil data quantitative
    $arr['quantitative'] = [
        'target_quantitative' => $data->entry?->quantitative->target_quantitative ?? null,
        'target_notes' => $data->entry?->quantitative->target_notes ?? null,
        'realization_quantitative' => $data->entry?->quantitative->realization_quantitative ?? null,
        'realization_notes' => $data->entry?->quantitative->realization_notes ?? null,
    ];

    // Ambil data residual
    $arr['residual'] = [
        'status_risiko' => $data->entry?->residual->status_risiko ?? null,
        'residual_risk_level_dampak' => $data->entry?->residual->residual_risk_level_dampak ?? null,
        'residual_risk_level_kemungkinan' => $data->entry?->residual->residual_risk_level_kemungkinan ?? null,
    ];

    // Tambahkan created_by_name dan updated_by_name, bersihkan dengan clean_string()
    $arr['created_by_name'] = $data->createdBy ? clean_string(get_decrypted_username($data->createdBy)) : 'Unknown User';
    $arr['updated_by_name'] = $data->updatedBy ? clean_string(get_decrypted_username($data->updatedBy)) : 'Unknown User';

    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $arr['month_name'] = ($monthNames[$data->month] ?? 'Unknown') . ' ' . ($data->year ?? ($data->header->year ?? ''));

    // Bersihkan seluruh array untuk keamanan encoding
    $arr = clean_recursive($arr);

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
        'entries',
        'createdBy',
        'updatedBy',
    ])->where('header_id', $headerId)
      ->orderBy('month')
      ->get();

    $cleaned = $data->map(function ($item) {
        $arr = collect($item)->toArray();

        // Format angka pada header jika ada
        if (isset($arr['header'])) {
            if (isset($arr['header']['target_quantitative_satu_tahun']) && $arr['header']['target_quantitative_satu_tahun']) {
                $arr['header']['target_quantitative_satu_tahun'] = number_format((float)str_replace(',', '', $arr['header']['target_quantitative_satu_tahun']), 0, ',', '.');
            }
            if (isset($arr['header']['biaya_perlakuan_risiko']) && $arr['header']['biaya_perlakuan_risiko']) {
                $arr['header']['biaya_perlakuan_risiko'] = number_format((float)str_replace(',', '', $arr['header']['biaya_perlakuan_risiko']), 0, ',', '.');
            }
        }

        // Format angka pada level monthly
        if (isset($arr['realization_quantitative']) && $arr['realization_quantitative']) {
            $arr['realization_quantitative'] = number_format((float)str_replace(',', '', $arr['realization_quantitative']), 0, ',', '.');
        }
        if (isset($arr['target_quantitative']) && $arr['target_quantitative']) {
            $arr['target_quantitative'] = number_format((float)str_replace(',', '', $arr['target_quantitative']), 0, ',', '.');
        }


        if (is_null($arr['target_option_position'] ?? null)) {
            unset($arr['target_option_position']);
        }
        if (is_null($arr['realization_option_position'] ?? null)) {
            unset($arr['realization_option_position']);
        }

        // Map uploads
        $arr['uploads'] = collect($item->uploads)->map(function ($upload) {
            return [
                'id' => $upload->id,
                'filepath' => $upload->filepath,
                'domain' => $upload->domain,
            ];
        });

        $residual = collect($item['entries'])->filter(function ($entry) {
            return isset($entry['residual_risk_level_dampak']) || isset($entry['residual_risk_satutahun_level_dampak']);
        })->values();

        $arr['residual_data'] = $residual->map(function ($entry) {
            return [
                'dampak_id' => $entry['residual_risk_level_dampak'],
                'kemungkinan_id' => $entry['residual_risk_level_kemungkinan'],
                'LevelDampak' => $entry['level_dampak'] ?? null,
                'LevelKemungkinan' => $entry['level_kemungkinan'] ?? null,
                'created_at' => $entry['created_at'],
            ];
        });

        // Filter entries quantitative
        $quantitative = collect($item['entries'])->filter(function ($entry) {
            return isset($entry['target_quantitative']) || isset($entry['realization_quantitative']);
        })->values();

        $arr['quantitative_data'] = $quantitative->map(function ($entry) {
            return [
                'id' => $entry['id'],
                'target_quantitative' => $entry['target_quantitative'],
                'realization_quantitative' => $entry['realization_quantitative'],
                'target_notes' => $entry['target_notes'],
                'realization_notes' => $entry['realization_note'], // sesuai field migration
                'created_at' => $entry['created_at'],
            ];
        });

        // Tambahkan created_by_name dan updated_by_name
        $arr['created_by_name'] = $item->createdBy ? clean_string(get_decrypted_username($item->createdBy)) : 'Unknown User';
        $arr['updated_by_name'] = $item->updatedBy ? clean_string(get_decrypted_username($item->updatedBy)) : 'Unknown User';

        return $arr;
    });

    // Bersihkan seluruh data
    $cleaned = clean_recursive($cleaned->toArray());

    // Convert header ke array dan format angka
    $headerArray = $header->toArray();
    if (isset($headerArray['target_quantitative_satu_tahun']) && $headerArray['target_quantitative_satu_tahun']) {
        $headerArray['target_quantitative_satu_tahun'] = number_format((float)str_replace(',', '', $headerArray['target_quantitative_satu_tahun']), 0, ',', '.');
    }
    if (isset($headerArray['biaya_perlakuan_risiko']) && $headerArray['biaya_perlakuan_risiko']) {
        $headerArray['biaya_perlakuan_risiko'] = number_format((float)str_replace(',', '', $headerArray['biaya_perlakuan_risiko']), 0, ',', '.');
    }

    return json(200, true, 'Data Ditemukan', 'Data monthly untuk header berhasil diambil.', [
        'header' => $headerArray,
        'monthly_data' => $cleaned,
    ]);
}


    public function updateResidualAndFinalize(Request $request, $id)
{
    $data = TrRiskMonthly::with('header.createdBy', 'uploads')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize || $data->entries()->where('is_finalize', true)->exists()) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', null);
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
        $errors = $validation[1]->getData(true)['data'] ?? [];
        return json(400, false, 'Data Kosong', 'Data tidak boleh kosong', $errors);
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
            'updated_by' => auth()->id(),
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
        $data->finalized_at = now();
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

        $data->load(['realizationOption:id,name,position', 'targetOption:id,name,position', 'uploads']);
        $data->makeHidden(['realization_option_position', 'target_option_position']);

        // Gunakan helper decrypt username dan bersihkan string di semua output
        $createdByName = get_decrypted_username($data->header->createdBy ?? null);

        $responseData = [
            'id' => $data->id,
            'header_id' => $data->header_id,
            'month' => $data->month,
            'risk_code' => clean_string($data->risk_code),
            'status_risiko' => clean_string($data->status_risiko),
            'process_code' => clean_string($data->process_code),
            'start_date' => clean_string($data->start_date),
            'expired_date' => clean_string($data->expired_date),
            'realization_quantitative' => $data->realization_quantitative ? number_format((float)$data->realization_quantitative, 0, ',', '.') : '0',
            'realization_note' => clean_string($data->realization_note),
            'target_quantitative' => $data->target_quantitative ? number_format((float)$data->target_quantitative, 0, ',', '.') : '0',
            'target_notes' => clean_string($data->target_notes),
            'residual_risk_level_dampak' => $data->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $data->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $data->residual_risk_posisi_risiko,
            'residual_risk_level_risiko' => clean_string($data->residual_risk_level_risiko),
            'residual_risk_level_risiko_color' => $residualRiskHeatmap->riskRange->color ?? null,
            'is_finalize' => $data->is_finalize,
            'finalized_at' => clean_string($data->finalized_at),
            'finalized_by' => $data->finalized_by,
            'created_at' => clean_string($data->created_at),
            'updated_at' => clean_string($data->updated_at),
            'header' => [
                'id' => $data->header->id,
                'year' => $data->header->year,
                'created_by' => $data->header->created_by,
                'created_at' => clean_string($data->header->created_at),
                'updated_at' => clean_string($data->header->updated_at),
                'created_by_name' => $createdByName,
            ],
            'uploaded_files' => $data->uploads->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => clean_string($file->filepath),
                    'domain' => clean_string($file->domain),
                ];
            }),
            'warnings' => $warnings,
        ];

        return json(200, true, 'Berhasil Diperbarui & Difinalisasi', 'Data berhasil disimpan dan difinalisasi.', $responseData, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diproses', 'Terjadi kesalahan sistem.', ['id' => $id, 'error' => $e->getMessage()]);
    }
}

   public function updateResidual(Request $request, $id)
{
    $data = TrRiskMonthly::with('header.createdBy', 'uploads')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', ['id' => $id]);
    }

    if ($data->is_finalize || $data->entries()->where('is_finalize', true)->exists()) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', ['id' => $id]);
    }

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
        $errors = $validation[1]->getData(true)['data'] ?? [];
        return json(400, false, 'Data Kosong', 'Data tidak boleh kosong', $errors);
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
            'updated_by' => auth()->id(),
        ];

        if (should_process_yearly_residual_risk($request)) {
            $yearlyData = process_yearly_residual_risk($request);
            if ($yearlyData) {
                $updateData = array_merge($updateData, $yearlyData);
            }
        }

        $data->update($updateData);

        if ($request->has('uploaded_files')) {
            process_risk_monthly_file_uploads($request->uploaded_files, $data);
        }

        DB::commit();

        $warnings = [];
        if ($data->month == 12 && $request->status_risiko === 'open') {
            $warnings[] = 'Perhatian: Status Risiko masih open di bulan Desember. Ini akan menjadi tindak lanjut di tahun berikutnya.';
        }

        $data->load(['realizationOption:id,name,position', 'targetOption:id,name,position', 'uploads']);

        $data->makeHidden(['realization_option_position', 'target_option_position']);

        $createdByName = get_decrypted_username($data->header->createdBy ?? null);

        // Pastikan semua string dibersihkan dengan helper clean_string() kamu
        $responseData = [
            'id' => $data->id,
            'header_id' => $data->header_id,
            'month' => $data->month,
            'risk_code' => clean_string($data->risk_code),
            'status_risiko' => clean_string($data->status_risiko),
            'process_code' => clean_string($data->process_code),
            'start_date' => clean_string($data->start_date),
            'expired_date' => clean_string($data->expired_date),
            'realization_quantitative' => $data->realization_quantitative ? number_format((float)$data->realization_quantitative, 0, ',', '.') : '0',
            'realization_note' => clean_string($data->realization_note),
            'target_quantitative' => $data->target_quantitative ? number_format((float)$data->target_quantitative, 0, ',', '.') : '0',
            'target_notes' => clean_string($data->target_notes),
            'residual_risk_level_dampak' => $data->residual_risk_level_dampak,
            'residual_risk_level_kemungkinan' => $data->residual_risk_level_kemungkinan,
            'residual_risk_posisi_risiko' => $data->residual_risk_posisi_risiko,
            'residual_risk_level_risiko' => clean_string($data->residual_risk_level_risiko),
            'residual_risk_level_risiko_color' => $residualRiskHeatmap->riskRange->color ?? null,
            'is_finalize' => $data->is_finalize,
            'finalized_at' => $data->finalized_at,
            'finalized_by' => $data->finalized_by,
            'created_at' => clean_string($data->created_at),
            'updated_at' => clean_string($data->updated_at),
            'header' => [
                'id' => $data->header->id,
                'year' => $data->header->year,
                'created_by' => $data->header->created_by,
                'created_at' => clean_string($data->header->created_at),
                'updated_at' => clean_string($data->header->updated_at),
            ],
            'uploaded_files' => $data->uploads->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => clean_string($file->filepath),
                    'domain' => clean_string($file->domain),
                ];
            }),
            'created_by_name' => $createdByName,
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

  public function updateQuantitative(Request $request, $id)
{
    $monthly = TrRiskMonthly::with('header.createdBy', 'header.uploads')->find($id);
    if (!$monthly) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    if ($monthly->is_finalize || $monthly->entries()->where('is_finalize', true)->exists()) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', null);
    }

    $validationRules = [
        'target_quantitative' => 'required|numeric',
        'target_notes' => 'nullable|string',
    ];

    $validation = check_validation($request->all(), $validationRules);
    if ($validation[0] == 1) {
        $errors = $validation[1]->getData(true)['data'] ?? [];
        return json(400, false, 'Data Kosong', 'Data tidak boleh kosong', $errors);
    }

    DB::beginTransaction();
    try {
        $monthly->update([
            'target_quantitative' => $request->target_quantitative,
            'target_notes' => $request->target_notes,
            'updated_by' => auth()->id(),
        ]);

        DB::commit();

        // Refresh data
        $monthly->load('header.createdBy', 'header.uploads');

        // Bersihkan string pada response
        $cleanedData = [
            'id' => $monthly->id,
            'header_id' => $monthly->header_id,
            'month' => $monthly->month,
            'risk_code' => clean_string($monthly->risk_code),
            'target_quantitative' => $monthly->target_quantitative ? number_format((float)$monthly->target_quantitative, 0, ',', '.') : '0',
            'target_notes' => clean_string($monthly->target_notes),
            'status_risiko' => clean_string($monthly->status_risiko),
            'is_finalize' => $monthly->is_finalize,
            'created_at' => clean_string($monthly->created_at),
            'updated_at' => clean_string($monthly->updated_at),
            'header' => [
                'id' => $monthly->header->id,
                'year' => $monthly->header->year,
                'created_by' => $monthly->header->created_by,
                'created_at' => clean_string($monthly->header->created_at),
                'updated_at' => clean_string($monthly->header->updated_at),
                'created_by_name' => get_decrypted_username($monthly->header->createdBy ?? null),
            ],
            'uploaded_files' => $monthly->header->uploads
                ->filter(fn($file) => $file->risk_monthly_id == $monthly->id)
                ->map(fn($file) => [
                    'id' => $file->id,
                    'filepath' => clean_string($file->filepath),
                    'domain' => clean_string($file->domain),
                ])->values()->toArray(),
        ];

        return json(200, true, 'Berhasil Diupdate', 'Data target kuantitatif berhasil diupdate.', $cleanedData);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diupdate', 'Terjadi kesalahan pada sistem.', ['error' => $e->getMessage()]);
    }
}

    public function finalize(Request $request, $id)
{
    $data = TrRiskMonthly::with('header')->find($id);
    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    // Cek apakah data monthly atau entry-nya sudah difinalisasi
    if ($data->is_finalize || $data->entries()->where('is_finalize', true)->exists()) {
        return json(400, false, 'Tidak Bisa Finalisasi', 'Data sudah difinalisasi sebelumnya dari sisi bulanan atau entry.', null);
    }

    // Validasi kelengkapan data sebelum finalisasi
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

        // Handle file uploads jika ada
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

    $finalized = $header->monthly()->where(function ($query) {
        $query->where('is_finalize', true)
              ->orWhereHas('entries', function ($q) {
                  $q->where('is_finalize', true);
              });
    })->exists();

    if ($finalized) {
        return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', null);
    }

    $hasMonthField = collect($request->monthly_data)->first() && isset(collect($request->monthly_data)->first()['month']);

    $bulkValidation = validate_bulk_quantitative_data($request->monthly_data, $hasMonthField, $headerId);
    if (!$bulkValidation['valid']) {
        return json(400, false, $bulkValidation['title'], $bulkValidation['message'], $bulkValidation['data']);
    }

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
        $errors = $validation[1]->getData(true)['data'] ?? [];
        return json(400, false, 'Data Kosong', 'Data tidak boleh kosong', $errors);
    }

    $updateMode = $request->update_mode ?? 'complete';
    $processedData = process_bulk_monthly_data($request->monthly_data, $hasMonthField);
    $existingMonthly = TrRiskMonthly::where('header_id', $headerId)->get()->keyBy('month');

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
            $updateMode,
            auth()->id()
        );

        // Load uploads untuk header ini
        $header->load('uploads');

        // Ambil semua id monthly yang diupdate atau dibuat
        $idsToLoad = [];
        if (isset($result['updated_data']) && is_array($result['updated_data'])) {
            $idsToLoad = array_merge($idsToLoad, array_column($result['updated_data'], 'id'));
        }
        if (isset($result['created_data']) && is_array($result['created_data'])) {
            $idsToLoad = array_merge($idsToLoad, array_column($result['created_data'], 'id'));
        }

        // Load TrRiskMonthly dengan relasi createdBy dan updatedBy untuk semua id tersebut
        $monthlyRecords = TrRiskMonthly::with(['createdBy', 'updatedBy'])->whereIn('id', $idsToLoad)->get()->keyBy('id');

        // Nama pembuat header, fallback jika createdBy monthly gak ada
        $headerCreatorName = $header->createdBy ? get_decrypted_username($header->createdBy) : 'Unknown User';

        // Format angka pada updated_data
        if (isset($result['updated_data']) && is_array($result['updated_data'])) {
            foreach ($result['updated_data'] as &$item) {
                // Format target_quantitative
                if (isset($item['target_quantitative'])) {
                    $item['target_quantitative'] = $item['target_quantitative'] ? number_format((float)$item['target_quantitative'], 0, ',', '.') : '0';
                }

                // Format data dalam header jika ada
                if (isset($item['header'])) {
                    if (isset($item['header']['target_quantitative_satu_tahun'])) {
                        $item['header']['target_quantitative_satu_tahun'] = $item['header']['target_quantitative_satu_tahun'] ? number_format((float)$item['header']['target_quantitative_satu_tahun'], 0, ',', '.') : '0';
                    }
                    if (isset($item['header']['biaya_perlakuan_risiko'])) {
                        $item['header']['biaya_perlakuan_risiko'] = $item['header']['biaya_perlakuan_risiko'] ? number_format((float)$item['header']['biaya_perlakuan_risiko'], 0, ',', '.') : '0';
                    }
                }

                $monthlyUploads = $header->uploads ? $header->uploads->where('risk_monthly_id', $item['id']) : collect([]);

                $item['uploaded_files'] = $monthlyUploads->count() > 0
                    ? $monthlyUploads->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'filepath' => clean_string($file->filepath),
                            'domain' => clean_string($file->domain),
                        ];
                    })->values()->toArray()
                    : [];

                $monthlyModel = $monthlyRecords->get($item['id']);

                if ($monthlyModel) {
                    if ($monthlyModel->updatedBy) {
                        $item['updated_by_name'] = get_decrypted_username($monthlyModel->updatedBy);
                    } elseif ($monthlyModel->createdBy) {
                        $item['created_by_name'] = get_decrypted_username($monthlyModel->createdBy);
                    } else {
                        $item['created_by_name'] = $headerCreatorName;
                    }
                } else {
                    $item['created_by_name'] = $headerCreatorName;
                }
            }
        }

        // Format angka pada created_data
        if (isset($result['created_data']) && is_array($result['created_data'])) {
            foreach ($result['created_data'] as &$item) {
                // Format target_quantitative
                if (isset($item['target_quantitative'])) {
                    $item['target_quantitative'] = $item['target_quantitative'] ? number_format((float)$item['target_quantitative'], 0, ',', '.') : '0';
                }

                // Format data dalam header jika ada
                if (isset($item['header'])) {
                    if (isset($item['header']['target_quantitative_satu_tahun'])) {
                        $item['header']['target_quantitative_satu_tahun'] = $item['header']['target_quantitative_satu_tahun'] ? number_format((float)$item['header']['target_quantitative_satu_tahun'], 0, ',', '.') : '0';
                    }
                    if (isset($item['header']['biaya_perlakuan_risiko'])) {
                        $item['header']['biaya_perlakuan_risiko'] = $item['header']['biaya_perlakuan_risiko'] ? number_format((float)$item['header']['biaya_perlakuan_risiko'], 0, ',', '.') : '0';
                    }
                }

                $monthlyUploads = $header->uploads ? $header->uploads->where('risk_monthly_id', $item['id']) : collect([]);

                $item['uploaded_files'] = $monthlyUploads->count() > 0
                    ? $monthlyUploads->map(function ($file) {
                        return [
                            'filepath' => clean_string($file->filepath),
                            'domain' => clean_string($file->domain),
                        ];
                    })->values()->toArray()
                    : [];

                $monthlyModel = $monthlyRecords->get($item['id']);

                if ($monthlyModel) {
                    if ($monthlyModel->updatedBy) {
                        $item['updated_by_name'] = get_decrypted_username($monthlyModel->updatedBy);
                    } elseif ($monthlyModel->createdBy) {
                        $item['created_by_name'] = get_decrypted_username($monthlyModel->createdBy);
                    } else {
                        $item['created_by_name'] = $headerCreatorName;
                    }
                } else {
                    $item['created_by_name'] = $headerCreatorName;
                }
            }
        }

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

    // Deteksi apakah file tunggal atau array
    $files = $request->file('file');
    $isMultiple = is_array($files);

    // Validasi
    $validationRules = $isMultiple
        ? [
            'file' => 'required|array',
            'file.*' => 'file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'domain' => 'nullable|string|max:255',
        ]
        : [
            'file' => 'required|file|max:5120|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            'domain' => 'nullable|string|max:255',
        ];

    $validation = check_validation($request->all(), $validationRules);
    if ($validation[0] === 1) {
        return $validation[1];
    }

    try {
        $uploadController = new UploadController();

        if ($isMultiple) {
            $upload = $uploadController->multipleUpload($request);
        } else {
            $upload = $uploadController->singleUpload($request);
        }

        $response = $upload instanceof \Illuminate\Http\JsonResponse
            ? json_decode($upload->getContent(), true)
            : null;

        if (!($response['status'] ?? false)) {
            return $upload;
        }

        // Format response
        $responseData = [];

if ($isMultiple) {
    $responseData = [];
    foreach ($response['data'] as $item) {
        $responseData[] = [
            'filepath' => $item['filepath'],
            'domain' => $item['domain'],

        ];
    }
} else {
    $item = $response['data'];
    $responseData = [
        'filepath' => $item['filepath'],
        'domain' => $item['domain'],

    ];
}

        return json(
            200,
            true,
            'Berhasil Upload',
            $isMultiple
                ? 'Semua file berhasil diupload. Silakan simpan atau finalisasi untuk menyimpan file ke sistem.'
                : 'File berhasil diupload. Silakan simpan atau finalisasi untuk menyimpan file ke sistem.',
            $responseData
        );

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
