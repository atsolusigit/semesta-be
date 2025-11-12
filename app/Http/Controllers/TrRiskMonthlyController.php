<?php

namespace App\Http\Controllers;

use App\Models\TrRiskMonthly;
use App\Models\TrRiskHeader;
use App\Models\MstMonthRecommendation;
use App\Models\MstHeatmap;
use App\Http\Controllers\UploadController;
use App\Models\TrRiskMonthlyUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TrRiskMonthlyController extends Controller
{

 public function index()
{
    $user = auth()->user();

    $query = TrRiskMonthly::with([
        'header.optionTargetSatuTahun',
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
    ]);

    // Filter berdasarkan role
    if ($user->role_id == 2 || $user->role_id == 3) {
        // Role 2 dan 3: Hanya bisa melihat data dari department mereka sendiri
        $query->whereHas('header', function($q) use ($user) {
            $q->where('department_id', $user->department_id);
        });
    }
    // Role 1, 4, 5: Bisa melihat semua data tanpa filter

    $data = $query->orderBy('header_id')
        ->orderBy('month')
        ->get();

    $cleaned = $data->map(function ($item) {
        $arr = collect($item)->toArray();

        // Risk code handle multiple
        $arr['risk_code'] = $item->riskCodes()->map(function ($rc) {
            return [
                'id'   => $rc->id,
                'name' => $rc->name,
                'code' => $rc->code ?? null, // Tambahkan field code jika ada
            ];
        })->toArray();

        // Format angka pada level monthly
        if (isset($arr['realization_quantitative']) && $arr['realization_quantitative'] !== null && $arr['realization_quantitative'] !== '') {
            if (is_numeric($arr['realization_quantitative'])) {
                $arr['realization_quantitative'] = number_format((float)$arr['realization_quantitative'], 0, ',', '.');
            }
        }
        if (isset($arr['target_quantitative']) && $arr['target_quantitative'] !== null && $arr['target_quantitative'] !== '') {
            if (is_numeric($arr['target_quantitative'])) {
                $arr['target_quantitative'] = number_format((float)$arr['target_quantitative'], 0, ',', '.');
            }
        }

        // Format angka pada header
        if (isset($arr['header'])) {
            if (isset($arr['header']['target_quantitative_satu_tahun']) && $arr['header']['target_quantitative_satu_tahun']) {
                $arr['header']['target_quantitative_satu_tahun'] = format_target_quantitative($arr['header']['target_quantitative_satu_tahun']);
            }
            if (isset($arr['header']['biaya_perlakuan_risiko']) && $arr['header']['biaya_perlakuan_risiko']) {
                $arr['header']['biaya_perlakuan_risiko'] = number_format((float)$arr['header']['biaya_perlakuan_risiko'], 2, ',', '.');
            }
            $arr['header']['target_satu_tahun_type'] = $item->header->optionTargetSatuTahun->type ?? '';
        }

        if (is_null($arr['target_option_position'] ?? null)) unset($arr['target_option_position']);
        if (is_null($arr['realization_option_position'] ?? null)) unset($arr['realization_option_position']);

        // Format uploads
        $arr['uploaded_files'] = collect($item['uploads'])->map(function ($upload) {
            return [
                'id' => $upload['id'],
                'filepath' => $upload['filepath'],
                'domain' => $upload['domain'],
            ];
        });

        // Residual data
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

        // Quantitative data
        $quantitative = collect($item['entries'])->filter(function ($entry) {
            return isset($entry['target_quantitative']) || isset($entry['realization_quantitative']);
        })->values();
        $arr['quantitative_data'] = $quantitative->map(function ($entry) {
            $realizationQuantitative = $entry['realization_quantitative'];
            if ($realizationQuantitative !== null && $realizationQuantitative !== '') {
                if (is_numeric($realizationQuantitative)) {
                    $realizationQuantitative = number_format((float)$realizationQuantitative, 0, ',', '.');
                }
            }

            $targetQuantitative = $entry['target_quantitative'];
            if ($targetQuantitative !== null && $targetQuantitative !== '') {
                if (is_numeric($targetQuantitative)) {
                    $targetQuantitative = number_format((float)$targetQuantitative, 0, ',', '.');
                }
            }

            return [
                'id' => $entry['id'],
                'target_quantitative' => $targetQuantitative,
                'realization_quantitative' => $realizationQuantitative,
                'target_notes' => $entry['target_notes'],
                'realization_notes' => $entry['realization_note'],
                'created_at' => $entry['created_at'],
            ];
        });

        // Tambahkan bulan dan note untuk rekomendasi
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $arr['month_name'] = ($monthNames[$item->month] ?? 'Unknown') . ' ' . ($item->year ?? ($item->header->year ?? ''));
        $arr['rekomendasi_note'] = $arr['rekomendasi'] ?? null;

        $arr['created_by_name'] = $item->createdBy ? clean_string(get_decrypted_username($item->createdBy)) : 'Unknown User';
        $arr['updated_by_name'] = $item->updatedBy ? clean_string(get_decrypted_username($item->updatedBy)) : 'Unknown User';

        return $arr;
    });

    $cleaned = clean_recursive($cleaned->toArray());
    return json(200, true, 'List data', 'Data risk monthly berhasil diambil.', $cleaned);
}

public function show($id)
{
    $user = auth()->user();

    $query = TrRiskMonthly::with([
        'header.optionTargetSatuTahun',
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
    ]);

    // Filter berdasarkan role
    if ($user->role_id == 2 || $user->role_id == 3) {
        // Role 2 dan 3: Hanya bisa melihat data dari department mereka sendiri
        $query->whereHas('header', function($q) use ($user) {
            $q->where('department_id', $user->department_id);
        });
    }
    // Role 1, 4, 5: Bisa melihat semua data tanpa filter

    $data = $query->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
    }

    $arr = collect($data)->toArray();

    // Risk code handle multiple
    $arr['risk_code'] = $data->riskCodes()->map(function ($rc) {
        return [
            'id'   => $rc->id,
            'name' => $rc->name,
            'code' => $rc->code ?? null, // Tambahkan field code jika ada
        ];
    })->toArray();

    // Format angka pada level monthly - handle both numeric and text
    if (isset($arr['realization_quantitative']) && $arr['realization_quantitative'] !== null && $arr['realization_quantitative'] !== '') {
        if (is_numeric($arr['realization_quantitative'])) {
            $arr['realization_quantitative'] = number_format((float)$arr['realization_quantitative'], 0, ',', '.');
        }
    }

    if (isset($arr['target_quantitative']) && $arr['target_quantitative'] !== null && $arr['target_quantitative'] !== '') {
        if (is_numeric($arr['target_quantitative'])) {
            $arr['target_quantitative'] = number_format((float)$arr['target_quantitative'], 0, ',', '.');
        }
    }

    // Format angka pada header jika ada
    if (isset($arr['header'])) {
        if (isset($arr['header']['target_quantitative_satu_tahun']) && $arr['header']['target_quantitative_satu_tahun']) {
            $arr['header']['target_quantitative_satu_tahun'] = format_target_quantitative($arr['header']['target_quantitative_satu_tahun']);
        }
        if (isset($arr['header']['biaya_perlakuan_risiko']) && $arr['header']['biaya_perlakuan_risiko']) {
            $arr['header']['biaya_perlakuan_risiko'] = number_format((float)$arr['header']['biaya_perlakuan_risiko'], 2, ',', '.');
        }
        $arr['header']['target_satu_tahun_type'] = $data->header->optionTargetSatuTahun->type ?? '';
    }

    if (is_null($arr['target_option_position'] ?? null)) unset($arr['target_option_position']);
    if (is_null($arr['realization_option_position'] ?? null)) unset($arr['realization_option_position']);

    $arr['uploaded_files'] = collect($data['uploads'])->map(function ($upload) {
        return [
            'id' => $upload['id'],
            'filepath' => $upload['filepath'],
            'domain' => $upload['domain'],
        ];
    });

    $arr['created_by_name'] = $data->createdBy ? clean_string(get_decrypted_username($data->createdBy)) : 'Unknown User';
    $arr['updated_by_name'] = $data->updatedBy ? clean_string(get_decrypted_username($data->updatedBy)) : 'Unknown User';

    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $arr['month_name'] = ($monthNames[$data->month] ?? 'Unknown') . ' ' . ($data->year ?? ($data->header->year ?? ''));

    // Tambahkan rekomendasi note
    $arr['rekomendasi_note'] = $arr['rekomendasi'] ?? null;

    $arr = clean_recursive($arr);

    return json(200, true, 'Data Ditemukan', 'Detail data risk monthly berhasil diambil.', $arr);
}

public function getByHeader($headerId)
{
    $user = auth()->user();

    // Cek akses ke header berdasarkan role
    $headerQuery = TrRiskHeader::with('optionTargetSatuTahun');

    if ($user->role_id == 2 || $user->role_id == 3) {
        // Role 2 dan 3: Hanya bisa akses header dari department mereka sendiri
        $headerQuery->where('department_id', $user->department_id);
    }
    // Role 1, 4, 5: Bisa akses semua header

    $header = $headerQuery->find($headerId);

    if (!$header) {
        return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan atau Anda tidak memiliki akses.', null);
    }

    $data = TrRiskMonthly::with([
        'header.optionTargetSatuTahun',
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

        // Risk code handle multiple
        $arr['risk_code'] = $item->riskCodes()->map(function ($rc) {
            return [
                'id'   => $rc->id,
                'name' => $rc->name,
                'code' => $rc->code ?? null, // Tambahkan field code jika ada
            ];
        })->toArray();

        // Format angka pada header jika ada
        if (isset($arr['header'])) {
             if (isset($arr['header']['target_quantitative_satu_tahun']) && $arr['header']['target_quantitative_satu_tahun']) {
                $arr['header']['target_quantitative_satu_tahun'] = format_target_quantitative($arr['header']['target_quantitative_satu_tahun']);
            }
            if (isset($arr['header']['biaya_perlakuan_risiko']) && $arr['header']['biaya_perlakuan_risiko']) {
                $arr['header']['biaya_perlakuan_risiko'] = number_format((float)str_replace(',', '', $arr['header']['biaya_perlakuan_risiko']), 2, ',', '.');
            }
            $arr['header']['target_satu_tahun_type'] = $item->header->optionTargetSatuTahun->type ?? '';
        }

        if (isset($arr['realization_quantitative']) && $arr['realization_quantitative'] !== null && $arr['realization_quantitative'] !== '') {
            if (is_numeric(str_replace(',', '', $arr['realization_quantitative']))) {
                $arr['realization_quantitative'] = number_format((float)str_replace(',', '', $arr['realization_quantitative']), 0, ',', '.');
            }
        }

        if (isset($arr['target_quantitative']) && $arr['target_quantitative'] !== null && $arr['target_quantitative'] !== '') {
            if (is_numeric(str_replace(',', '', $arr['target_quantitative']))) {
                $arr['target_quantitative'] = number_format((float)str_replace(',', '', $arr['target_quantitative']), 0, ',', '.');
            }
        }

        if (is_null($arr['target_option_position'] ?? null)) unset($arr['target_option_position']);
        if (is_null($arr['realization_option_position'] ?? null)) unset($arr['realization_option_position']);

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

        $quantitative = collect($item['entries'])->filter(function ($entry) {
            return isset($entry['target_quantitative']) || isset($entry['realization_quantitative']);
        })->values();

        $arr['quantitative_data'] = $quantitative->map(function ($entry) {
            $realizationQuantitative = $entry['realization_quantitative'];
            if ($realizationQuantitative !== null && $realizationQuantitative !== '') {
                if (is_numeric(str_replace(',', '', $realizationQuantitative))) {
                    $realizationQuantitative = number_format((float)str_replace(',', '', $realizationQuantitative), 0, ',', '.');
                }
            }

            $targetQuantitative = $entry['target_quantitative'];
            if ($targetQuantitative !== null && $targetQuantitative !== '') {
                if (is_numeric(str_replace(',', '', $targetQuantitative))) {
                    $targetQuantitative = number_format((float)str_replace(',', '', $targetQuantitative), 0, ',', '.');
                }
            }

            return [
                'id' => $entry['id'],
                'target_quantitative' => $targetQuantitative,
                'realization_quantitative' => $realizationQuantitative,
                'target_notes' => $entry['target_notes'],
                'realization_notes' => $entry['realization_note'],
                'created_at' => $entry['created_at'],
            ];
        });

        // Tambahkan bulan dan note untuk monthly data
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $arr['month_name'] = ($monthNames[$item->month] ?? 'Unknown') . ' ' . ($item->year ?? ($item->header->year ?? ''));
        $arr['rekomendasi_note'] = $arr['rekomendasi'] ?? null;

        $arr['created_by_name'] = $item->createdBy ? clean_string(get_decrypted_username($item->createdBy)) : 'Unknown User';
        $arr['updated_by_name'] = $item->updatedBy ? clean_string(get_decrypted_username($item->updatedBy)) : 'Unknown User';

        return $arr;
    });

    $cleaned = clean_recursive($cleaned->toArray());
    $headerArray = $header->toArray();

    if (isset($headerArray['target_quantitative_satu_tahun']) && $headerArray['target_quantitative_satu_tahun']) {
        $headerArray['target_quantitative_satu_tahun'] = format_target_quantitative($headerArray['target_quantitative_satu_tahun']);
    }
    if (isset($headerArray['biaya_perlakuan_risiko']) && $headerArray['biaya_perlakuan_risiko']) {
        $headerArray['biaya_perlakuan_risiko'] = number_format((float)str_replace(',', '', $headerArray['biaya_perlakuan_risiko']), 2, ',', '.');
    }
    $headerArray['target_satu_tahun_type'] = $header->optionTargetSatuTahun->type ?? '';

    // Tambahkan rekomendasi bulanan ke dalam header
    $monthlyRekomendasi = [];
    foreach ($cleaned as $monthlyData) {
        if (!empty($monthlyData['rekomendasi_note'])) {
            $monthlyRekomendasi[] = [
                'month' => $monthlyData['month'],
                'month_name' => $monthlyData['month_name'],
                'rekomendasi_note' => $monthlyData['rekomendasi_note']
            ];
        }
    }
    $headerArray['monthly_rekomendasi'] = $monthlyRekomendasi;

    return json(200, true, 'Data Ditemukan', 'Data monthly untuk header berhasil diambil.', [
        'header' => $headerArray,
        'monthly_data' => $cleaned,
    ]);
}

    public function updateResidualAndFinalize(Request $request, $id)
{
    // Check user role authorization
    $user = auth()->user();
    $roleCheck = check_role($user, [1, 2, 3]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

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

        // ======= NEW: cek mst_month_recommendation apakah bulan ini butuh rekomendasi =======
    $mst = \App\Models\MstMonthRecommendation::find($data->month);

    $requiresRecommendation = false;
    if ($mst && $mst->required) {
        $requiresRecommendation = true;
    }

    // Jika membutuhkan rekomendasi, pastikan data tr_risk_monthly sudah diapprove rekomendasinya
    if ($requiresRecommendation) {
        if ($data->approval_status !== 'approved') {
            $monthName = $mst->name ?? $data->month;
            return json(400, false, 'Terkunci', "Bulan {$monthName} membutuhkan rekomendasi yang belum disetujui. Silakan tunggu persetujuan rekomendasi terlebih dahulu sebelum mengisi data.", [
                'id' => $id,
                'month' => $data->month,
                'approval_status' => $data->approval_status ?? 'pending'
            ]);
        }
    }
    // ======= END NEW CHECK =======

    // Generate tanggal default jika tidak diisi
    $autoGeneratedDates = generate_risk_monthly_dates($data->header->year, $data->month);

    $validator = Validator::make($request->all(), [
    'status_risiko' => 'required|in:open,close',
    'start_date' => 'nullable|date',
    'expired_date' => 'nullable|date|after_or_equal:start_date',
    'realization_quantitative' => 'nullable|string',
    'realization_kualitatif' => 'nullable|string|max:255',
    'realization_option' => 'nullable|numeric|exists:mst_option,id',
    'realization_notes' => 'nullable|string',
    'realization_option_position' => 'nullable|string',
    'target_option' => 'nullable|numeric|exists:mst_option,id',
    'target_option_position' => 'nullable|string',
    'residual_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
    'residual_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
    'residual_risk_satutahun_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
    'residual_risk_satutahun_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
], [
    'status_risiko.required' => 'status risiko field is required.',
    'status_risiko.in' => 'status risiko must be open or close.',
    'start_date.date' => 'start date must be a valid date.',
    'expired_date.date' => 'expired date must be a valid date.',
    'expired_date.after_or_equal' => 'expired date must be after or equal to start date.',
    'realization_quantitative.string' => 'realization quantitative must be a string.',
    'realization_kualitatif.string' => 'realization kualitatif must be a string.',
    'realization_kualitatif.max' => 'realization kualitatif may not be greater than 50 characters.',
    'realization_option.numeric' => 'realization option must be a number.',
    'realization_option.exists' => 'selected realization option is invalid.',
    'realization_notes.string' => 'realization notes must be a string.',
    'realization_option_position.string' => 'realization option position must be a string.',
    'target_option.numeric' => 'target option must be a number.',
    'target_option.exists' => 'selected target option is invalid.',
    'target_option_position.string' => 'target option position must be a string.',
    'residual_risk_level_dampak.required' => 'residual risk level dampak field is required.',
    'residual_risk_level_dampak.exists' => 'selected residual risk level dampak is invalid.',
    'residual_risk_level_kemungkinan.required' => 'residual risk level kemungkinan field is required.',
    'residual_risk_level_kemungkinan.exists' => 'selected residual risk level kemungkinan is invalid.',
    'residual_risk_satutahun_level_dampak.exists' => 'selected residual risk satutahun level dampak is invalid.',
    'residual_risk_satutahun_level_kemungkinan.exists' => 'selected residual risk satutahun level kemungkinan is invalid.',
]);

if ($validator->fails()) {
    return json(400, false, 'Validasi Gagal', 'Data tidak boleh kosong', $validator->errors());
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

        // Proses realization_kualitatif %
        $rawRealizationKualitatif = $request->realization_kualitatif ?? null;
        $realizationKualitatifToSave = null;

        if ($rawRealizationKualitatif !== null && $rawRealizationKualitatif !== '') {
            // Hapus whitespace
            $v = trim($rawRealizationKualitatif);

            // Jika sudah ada trailing % (mis. "75%"), biarkan tapi normalisasi remove spaces then keep %
            if (str_ends_with($v, '%')) {
                // remove extra spaces before %, ensure single %
                $v = rtrim($v);
                $realizationKualitatifToSave = $v;
            } else {
                // Coba deteksi numeric (replace comma with dot to support decimal comma)
                $vNumeric = str_replace(',', '.', $v);
                // remove spaces
                $vNumeric = trim($vNumeric);
                if (is_numeric($vNumeric)) {
                    // simpan dengan % appended (keep original decimal point if any)
                    // Use original trimmed $v for preserving user's decimal separator if they used dot.
                    // But to be safe, we use the numeric normalized representation (no thousands separator)
                    // Format: keep as is (no extra formatting), append '%'
                    $realizationKualitatifToSave = $v . '%';
                } else {
                    // bukan numeric dan tidak ada %, simpan apa adanya
                    $realizationKualitatifToSave = $v;
                }
            }
        }

        $updateData = [
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date ?? $autoGeneratedDates['start_date'],
            'expired_date' => $request->expired_date ?? $autoGeneratedDates['expired_date'],
            'realization_quantitative' => $request->realization_quantitative,
            'realization_kualitatif' => $realizationKualitatifToSave,
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

        // Format realization_quantitative untuk display
        $formattedRealizationQuantitative = $data->realization_quantitative;
        if (is_numeric($data->realization_quantitative)) {
            $formattedRealizationQuantitative = number_format((float)$data->realization_quantitative, 0, ',', '.');
        }

        // Format realization_kualitatif untuk display:
        // Karena kita menyimpan nilai dengan '%' bila numeric, cukup tampilkan apa adanya.
        // Namun jika ada kemungkinan tersimpan tanpa '%' (legacy), kita tambahkan % saat response jika numeric.
        $displayRealizationKualitatif = null;
        if ($data->realization_kualitatif !== null && $data->realization_kualitatif !== '') {
            $rk = trim($data->realization_kualitatif);
            if (str_ends_with($rk, '%')) {
                $displayRealizationKualitatif = $rk;
            } else {
                // coba deteksi numeric (support comma decimal)
                $tmp = str_replace(',', '.', $rk);
                if (is_numeric($tmp)) {
                    $displayRealizationKualitatif = $rk . '%';
                } else {
                    $displayRealizationKualitatif = $rk;
                }
            }
        }

        $formattedTargetQuantitative = $data->target_quantitative
            ? number_format((float)$data->target_quantitative, 0, ',', '.')
            : '0';

        $responseData = [
            'id' => $data->id,
            'header_id' => $data->header_id,
            'month' => $data->month,
            'risk_code' => $data->risk_code,
            'status_risiko' => clean_string($data->status_risiko),
            'process_code' => clean_string($data->process_code),
            'start_date' => clean_string($data->start_date),
            'expired_date' => clean_string($data->expired_date),
            'realization_quantitative' => $formattedRealizationQuantitative, // Tampilkan sesuai format
            'realization_kualitatif' => $displayRealizationKualitatif !== null ? clean_string($displayRealizationKualitatif) : null, // Pastikan tampil persen
            'realization_note' => clean_string($data->realization_note),
            'target_quantitative' => $formattedTargetQuantitative,
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
    // Check user role authorization
     $user = auth()->user();
    $roleCheck = check_role($user, [1, 2, 3]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

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

    // ======= NEW: cek mst_month_recommendation apakah bulan ini butuh rekomendasi =======
    $mst = \App\Models\MstMonthRecommendation::find($data->month);

    $requiresRecommendation = false;
    if ($mst && $mst->required) {
        $requiresRecommendation = true;
    }

    // Jika membutuhkan rekomendasi, pastikan data tr_risk_monthly sudah diapprove rekomendasinya
    if ($requiresRecommendation) {
        if ($data->approval_status !== 'approved') {
            $monthName = $mst->name ?? $data->month;
            return json(400, false, 'Terkunci', "Bulan {$monthName} membutuhkan rekomendasi yang belum disetujui. Silakan tunggu persetujuan rekomendasi terlebih dahulu sebelum mengisi residual.", [
                'id' => $id,
                'month' => $data->month,
                'approval_status' => $data->approval_status ?? 'pending'
            ]);
        }
    }
    // ======= END NEW CHECK =======

    $autoGeneratedDates = generate_risk_monthly_dates($data->header->year, $data->month);

    $validator = Validator::make($request->all(), [
    'status_risiko' => 'required|in:open,close',
    'start_date' => 'nullable|date',
    'expired_date' => 'nullable|date|after_or_equal:start_date',
    'realization_quantitative' => 'nullable|string',
    'realization_kualitatif' => 'nullable|string|max:255',
    'realization_option' => 'nullable|numeric|exists:mst_option,id',
    'realization_notes' => 'nullable|string',
    'realization_option_position' => 'nullable|string',
    'target_option' => 'nullable|numeric|exists:mst_option,id',
    'target_option_position' => 'nullable|string',
    'residual_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
    'residual_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
    'residual_risk_satutahun_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
    'residual_risk_satutahun_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
], [
    'status_risiko.required' => 'status risiko field is required.',
    'status_risiko.in' => 'status risiko must be open or close.',
    'start_date.date' => 'start date must be a valid date.',
    'expired_date.date' => 'expired date must be a valid date.',
    'expired_date.after_or_equal' => 'expired date must be after or equal to start date.',
    'realization_quantitative.string' => 'realization quantitative must be a string.',
    'realization_kualitatif.string' => 'realization kualitatif must be a string.',
    'realization_kualitatif.max' => 'realization kualitatif may not be greater than 50 characters.',
    'realization_option.numeric' => 'realization option must be a number.',
    'realization_option.exists' => 'selected realization option is invalid.',
    'realization_notes.string' => 'realization notes must be a string.',
    'realization_option_position.string' => 'realization option position must be a string.',
    'target_option.numeric' => 'target option must be a number.',
    'target_option.exists' => 'selected target option is invalid.',
    'target_option_position.string' => 'target option position must be a string.',
    'residual_risk_level_dampak.required' => 'residual risk level dampak field is required.',
    'residual_risk_level_dampak.exists' => 'selected residual risk level dampak is invalid.',
    'residual_risk_level_kemungkinan.required' => 'residual risk level kemungkinan field is required.',
    'residual_risk_level_kemungkinan.exists' => 'selected residual risk level kemungkinan is invalid.',
    'residual_risk_satutahun_level_dampak.exists' => 'selected residual risk satutahun level dampak is invalid.',
    'residual_risk_satutahun_level_kemungkinan.exists' => 'selected residual risk satutahun level kemungkinan is invalid.',
]);

if ($validator->fails()) {
    return json(400, false, 'Validasi Gagal', 'Data tidak boleh kosong', $validator->errors());
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

        // Tambahkan % jika user input angka tanpa %
        $realizationKualitatif = $request->realization_kualitatif;
        if ($realizationKualitatif !== null && $realizationKualitatif !== '') {
            $realizationKualitatif = rtrim($realizationKualitatif, '%') . '%';
        }

        $updateData = [
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date ?? $autoGeneratedDates['start_date'],
            'expired_date' => $request->expired_date ?? $autoGeneratedDates['expired_date'],
            'realization_quantitative' => $request->realization_quantitative,
            'realization_kualitatif' => $realizationKualitatif,
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

        $formattedRealizationQuantitative = $data->realization_quantitative;
        if (is_numeric($data->realization_quantitative)) {
            $formattedRealizationQuantitative = number_format((float)$data->realization_quantitative, 0, ',', '.');
        }

        $responseData = [
            'id' => $data->id,
            'header_id' => $data->header_id,
            'month' => $data->month,
            'risk_code' => $data->risk_code,
            'status_risiko' => clean_string($data->status_risiko),
            'process_code' => clean_string($data->process_code),
            'start_date' => clean_string($data->start_date),
            'expired_date' => clean_string($data->expired_date),
            'realization_quantitative' => $formattedRealizationQuantitative,
            'realization_kualitatif' => clean_string($data->realization_kualitatif),
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

//   public function updateQuantitative(Request $request, $id)
// {
//     $monthly = TrRiskMonthly::with('header.createdBy', 'header.uploads')->find($id);
//     if (!$monthly) {
//         return json(404, false, 'Data Tidak Ditemukan', 'Data risk monthly tidak ditemukan.', null);
//     }

//     if ($monthly->is_finalize || $monthly->entries()->where('is_finalize', true)->exists()) {
//         return json(400, false, 'Finalisasi', 'Data sudah difinalisasi dan tidak bisa diubah lagi.', null);
//     }

//     $validationRules = [
//         'target_quantitative' => 'required|string',
//         'target_notes' => 'nullable|string',
//     ];

//     $validation = check_validation($request->all(), $validationRules);
//     if ($validation[0] == 1) {
//         $errors = $validation[1]->getData(true)['data'] ?? [];
//         return json(400, false, 'Data Kosong', 'Data tidak boleh kosong', $errors);
//     }

//     DB::beginTransaction();
//     try {
//         $monthly->update([
//             'target_quantitative' => $request->target_quantitative,
//             'target_notes' => $request->target_notes,
//             'updated_by' => auth()->id(),
//         ]);

//         DB::commit();

//         // Refresh data
//         $monthly->load('header.createdBy', 'header.uploads');

//         // Bersihkan string pada response
//         $cleanedData = [
//             'id' => $monthly->id,
//             'header_id' => $monthly->header_id,
//             'month' => $monthly->month,
//             'risk_code' => $monthly->header_id,
//             'target_quantitative' => clean_string($monthly->target_quantitative),
//             'target_notes' => clean_string($monthly->target_notes),
//             'status_risiko' => clean_string($monthly->status_risiko),
//             'is_finalize' => $monthly->is_finalize,
//             'created_at' => clean_string($monthly->created_at),
//             'updated_at' => clean_string($monthly->updated_at),
//             'header' => [
//                 'id' => $monthly->header->id,
//                 'year' => $monthly->header->year,
//                 'created_by' => $monthly->header->created_by,
//                 'created_at' => clean_string($monthly->header->created_at),
//                 'updated_at' => clean_string($monthly->header->updated_at),
//                 'created_by_name' => get_decrypted_username($monthly->header->createdBy ?? null),
//             ],
//             'uploaded_files' => $monthly->header->uploads
//                 ->filter(fn($file) => $file->risk_monthly_id == $monthly->id)
//                 ->map(fn($file) => [
//                     'id' => $file->id,
//                     'filepath' => clean_string($file->filepath),
//                     'domain' => clean_string($file->domain),
//                 ])->values()->toArray(),
//         ];

//         return json(200, true, 'Berhasil Diupdate', 'Data target kuantitatif berhasil diupdate.', $cleanedData);

//     } catch (\Throwable $e) {
//         DB::rollBack();
//         return json(500, false, 'Gagal Diupdate', 'Terjadi kesalahan pada sistem.', ['error' => $e->getMessage()]);
//     }
// }

public function bulkUpdateQuantitative(Request $request, $headerId)
{
    // Check user role authorization
    $user = auth()->user();
    $roleCheck = check_role($user, [1, 2, 3]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

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

    // Deteksi tipe target dari header
    $targetType = $header->optionTargetSatuTahun ? $header->optionTargetSatuTahun->type : 'Kuantitatif';

    // Validasi type sesuai dengan data yang dikirim
    foreach ($request->monthly_data as $index => $monthData) {
        $hasQualitativeField = (isset($monthData['target_kualitatif']) && !empty($monthData['target_kualitatif'])) ||
                              (isset($monthData['target_qualitatif']) && !empty($monthData['target_qualitatif']));

        if ($targetType === 'Kuantitatif' && $hasQualitativeField) {
            return json(400, false, 'Type Tidak Sesuai', 'Maaf type tidak sesuai. Header ini bertipe Kuantitatif, tidak dapat mengisi field target_kualitatif.', [
                'header_id' => $headerId,
                'expected_type' => 'Kuantitatif',
                'received_type' => 'Kualitatif',
                'error_index' => $index,
                'detected_target_type' => $targetType
            ]);
        }

        if ($targetType === 'Kualitatif' && !$hasQualitativeField) {
            return json(400, false, 'Type Tidak Sesuai', 'Maaf type tidak sesuai. Header ini bertipe Kualitatif, field target_kualitatif harus diisi.', [
                'header_id' => $headerId,
                'expected_type' => 'Kualitatif',
                'received_type' => 'Kuantitatif',
                'error_index' => $index,
                'detected_target_type' => $targetType
            ]);
        }
    }

    // MAPPING: target_qualitatif → target_kualitatif + tambahkan % jika numerik
    $originalData = $request->all();
    if (isset($originalData['monthly_data'])) {
        foreach ($originalData['monthly_data'] as &$monthData) {
            if (isset($monthData['target_qualitatif'])) {
                $monthData['target_kualitatif'] = $monthData['target_qualitatif'];
                unset($monthData['target_qualitatif']);
            }

            if (isset($monthData['target_kualitatif']) && is_numeric($monthData['target_kualitatif'])) {
                $monthData['target_kualitatif'] = $monthData['target_kualitatif'] . '%';
            }
        }
    }

    // === VALIDASI ===
    if ($hasMonthField) {
        $validationRules = [
            'monthly_data' => 'required|array|min:1|max:12',
            'monthly_data.*.month' => 'required|integer|min:1|max:12',
            'monthly_data.*.target_quantitative' => 'required|string',
            'monthly_data.*.target_notes' => 'nullable|string|max:1000',
            'require_all_months' => 'nullable|boolean',
            'update_mode' => 'nullable|string|in:selective,complete',
        ];
        if ($targetType === 'Kualitatif') {
            $validationRules['monthly_data.*.target_kualitatif'] = 'required|string|max:255';
        }
    } else {
        $validationRules = [
            'monthly_data' => 'required|array|min:1|max:12',
            'monthly_data.*.target_quantitative' => 'required|string',
            'monthly_data.*.target_notes' => 'nullable|string|max:1000',
            'require_all_months' => 'nullable|boolean',
            'update_mode' => 'nullable|string|in:selective,complete',
        ];
        if ($targetType === 'Kualitatif') {
            $validationRules['monthly_data.*.target_kualitatif'] = 'required|string|max:255';
        }
        if ($request->require_all_months === true) {
            $validationRules['monthly_data'] = 'required|array|size:12';
        }
    }

    $validation = check_validation($originalData, $validationRules);
    if ($validation[0] == 1) {
        $errors = $validation[1]->getData(true)['data'] ?? [];
        return json(400, false, 'Data Kosong', 'Data tidak boleh kosong', $errors);
    }

    $updateMode = $request->update_mode ?? 'complete';

    // FORCE mapping untuk processing
    $dataForProcessing = array_map(function($item) {
        if (isset($item['target_qualitatif'])) {
            $item['target_kualitatif'] = $item['target_qualitatif'];
            unset($item['target_qualitatif']);
        }
        if (isset($item['target_kualitatif']) && is_numeric($item['target_kualitatif'])) {
            $item['target_kualitatif'] = $item['target_kualitatif'] . '%';
        }
        return $item;
    }, $request->monthly_data);

    $processedData = process_bulk_monthly_data($dataForProcessing, $hasMonthField);
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

        // FORCE UPDATE: Pastikan target_kualitatif tersimpan dengan simbol %
        if ($targetType === 'Kualitatif') {
            foreach ($request->monthly_data as $index => $originalData) {
                $targetKualitatif = null;
                if (isset($originalData['target_kualitatif']) && !empty($originalData['target_kualitatif'])) {
                    $targetKualitatif = $originalData['target_kualitatif'];
                } elseif (isset($originalData['target_qualitatif']) && !empty($originalData['target_qualitatif'])) {
                    $targetKualitatif = $originalData['target_qualitatif'];
                }

                if ($targetKualitatif) {
                    if (is_numeric($targetKualitatif)) {
                        $targetKualitatif = $targetKualitatif . '%';
                    }
                    $month = isset($originalData['month']) ? $originalData['month'] : ($index + 1);

                    DB::table('tr_risk_monthly')
                        ->where('header_id', $headerId)
                        ->where('month', $month)
                        ->update([
                            'target_kualitatif' => $targetKualitatif,
                            'updated_at' => now(),
                            'updated_by' => auth()->id()
                        ]);
                }
            }
        }

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

        // Format data pada updated_data
        if (isset($result['updated_data']) && is_array($result['updated_data'])) {
            foreach ($result['updated_data'] as &$item) {
                if (isset($item['target_quantitative'])) {
                    $item['target_quantitative'] = clean_string($item['target_quantitative']);
                }

                if ($targetType === 'Kualitatif' && isset($item['target_kualitatif'])) {
                    $item['target_kualitatif'] = clean_string($item['target_kualitatif']);
                }

                if (isset($item['header'])) {
                    if (isset($item['header']['target_quantitative_satu_tahun']) && $item['header']['target_quantitative_satu_tahun']) {
                        $item['header']['target_quantitative_satu_tahun'] = clean_string($item['header']['target_quantitative_satu_tahun']);
                    }
                    if (isset($item['header']['biaya_perlakuan_risiko'])) {
                        $item['header']['biaya_perlakuan_risiko'] = $item['header']['biaya_perlakuan_risiko']
                            ? number_format((float)$item['header']['biaya_perlakuan_risiko'], 2, ',', '.')
                            : '0';
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

        // Format data pada created_data
        if (isset($result['created_data']) && is_array($result['created_data'])) {
            foreach ($result['created_data'] as &$item) {
                if (isset($item['target_quantitative'])) {
                    $item['target_quantitative'] = clean_string($item['target_quantitative']);
                }

                if ($targetType === 'Kualitatif' && isset($item['target_kualitatif'])) {
                    $item['target_kualitatif'] = clean_string($item['target_kualitatif']);
                }

                if (isset($item['header'])) {
                    if (isset($item['header']['target_quantitative_satu_tahun']) && $item['header']['target_quantitative_satu_tahun']) {
                        $item['header']['target_quantitative_satu_tahun'] = clean_string($item['header']['target_quantitative_satu_tahun']);
                    }
                    if (isset($item['header']['biaya_perlakuan_risiko'])) {
                        $item['header']['biaya_perlakuan_risiko'] = $item['header']['biaya_perlakuan_risiko']
                            ? number_format((float)$item['header']['biaya_perlakuan_risiko'], 2, ',', '.')
                            : '0';
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

        return json(200, true, 'Berhasil Menyimpan 12 Bulan', $message, $result, $warnings);

    } catch (\Throwable $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Menyimpan 12 Bulan', 'Terjadi kesalahan sistem.', [
            'header_id' => $headerId,
            'error' => $e->getMessage()
        ]);
    }
}

    // public function finalizeAll(Request $request, $headerId)
    // {
    //     $header = TrRiskHeader::find($headerId);
    //     if (!$header) {
    //         return json(404, false, 'Header Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
    //     }

    //     $monthlyData = TrRiskMonthly::where('header_id', $headerId)
    //         ->where('is_finalize', false)
    //         ->orderBy('month')
    //         ->get();

    //     if ($monthlyData->isEmpty()) {
    //         return json(400, false, 'Tidak Ada Data', 'Tidak ada data yang dapat difinalisasi.', null);
    //     }

    //     $validationErrors = [];
    //     $decemberOpenRisks = [];

    //     foreach ($monthlyData as $monthly) {
    //         $validationResult = validate_monthly_data_for_finalization($monthly);
    //         if (!$validationResult['valid']) {
    //             $validationErrors[] = "Bulan {$monthly->month}: " . $validationResult['message'];
    //         }

    //         if ($monthly->month == 12 && $monthly->status_risiko == 'open') {
    //             $decemberOpenRisks[] = "Risiko bulan Desember masih open dan akan menjadi tindak lanjut tahun " . ($header->year + 1);
    //         }
    //     }

    //     if (!empty($validationErrors)) {
    //         return json(400, false, 'Data Tidak Lengkap', 'Beberapa data belum lengkap.', $validationErrors);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $finalizedCount = 0;
    //         foreach ($monthlyData as $monthly) {
    //             $monthly->is_finalize = true;
    //             $monthly->finalized_at = Carbon::now();
    //             $monthly->finalized_by = auth()->id() ?? null;
    //             $monthly->save();
    //             $finalizedCount++;
    //         }

    //         // Handle file uploads
    //         if ($request->has('uploaded_files')) {
    //             $firstMonthly = $monthlyData->first();
    //             process_risk_monthly_file_uploads($request->uploaded_files, $firstMonthly);
    //         }

    //         DB::commit();

    //         $warnings = array_merge($decemberOpenRisks);

    //         return json(200, true, 'Berhasil Difinalisasi', "$finalizedCount data risk monthly berhasil difinalisasi.", null, $warnings);

    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return json(500, false, 'Gagal Difinalisasi', 'Terjadi kesalahan sistem.', $e->getMessage());
    //     }
    // }

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
    // Check if user has role id 1
      $user = auth()->user();
    $roleCheck = check_role($user, 1);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

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

public function getRecommendationMonths(Request $request, $headerId)
{
    $user = Auth::user();

    // Role check
    $roleCheck = check_role($user, [1,2,3,4,5]);
    if ($roleCheck !== true) {
        return $roleCheck;
    }

    // Get approval_notes from tr_risk_header
    $headerApprovalNotes = TrRiskHeader::where('id', $headerId)
        ->value('approval_notes');

    $monthlyQuery = TrRiskMonthly::select(
        'tr_risk_monthly.id',
        'tr_risk_monthly.month',
        'tr_risk_monthly.note_recommendation',
        'tr_risk_monthly.is_submitted_recommendation',
        'tr_risk_monthly.recommendation_submitted_by',
        'tr_risk_monthly.recommendation_submitted_at',
        'tr_risk_monthly.approval_status',
        'tr_risk_monthly.approved_by',
        'tr_risk_monthly.approved_at',
        'tr_risk_monthly.rejected_by',
        'tr_risk_monthly.rejected_at',
        'tr_risk_monthly.rejection_note',
        'tr_risk_monthly.approval_notes',
        'm.name as month_name',
        'm.required',
        'u_submitted.id as submitted_by_id',
        'u_approved.id as approved_by_id',
        'u_rejected.id as rejected_by_id'
    )
    ->join('mst_month_recommendation as m', 'tr_risk_monthly.month', '=', 'm.id')
    ->leftJoin('users as u_submitted', 'tr_risk_monthly.recommendation_submitted_by', '=', 'u_submitted.id')
    ->leftJoin('users as u_approved', 'tr_risk_monthly.approved_by', '=', 'u_approved.id')
    ->leftJoin('users as u_rejected', 'tr_risk_monthly.rejected_by', '=', 'u_rejected.id')
    ->where('tr_risk_monthly.header_id', $headerId)
    ->where(function($q) {
        $q->where('m.required', 1)
          ->orWhereNotNull('tr_risk_monthly.note_recommendation')
          ->orWhere('tr_risk_monthly.is_submitted_recommendation', true);
    });

    $data = $monthlyQuery->orderBy('m.id', 'asc')->orderBy('tr_risk_monthly.id', 'desc')->get()->map(function ($item) use ($headerApprovalNotes) {
        // Status berdasarkan approval_status enum
        $status = $item->approval_status ?? 'pending';
        if (!$item->is_submitted_recommendation) {
            $status = 'draft';
        }

        // Get decrypted names using the helper function
        $submitted_by_name = null;
        $approved_by_name = null;
        $rejected_by_name = null;

        if ($item->submitted_by_id) {
            $submittedUser = (object)['id' => $item->submitted_by_id];
            $submitted_by_name = get_decrypted_name($submittedUser);
        }

        if ($item->approved_by_id) {
            $approvedUser = (object)['id' => $item->approved_by_id];
            $approved_by_name = get_decrypted_name($approvedUser);
        }

        if ($item->rejected_by_id) {
            $rejectedUser = (object)['id' => $item->rejected_by_id];
            $rejected_by_name = get_decrypted_name($rejectedUser);
        }

        return [
            'monthly_id'          => $item->id,
            'month'               => $item->month_name,
            'required'            => (bool) $item->required,
            'note_recommendation' => $item->note_recommendation,
            'status'              => $status,
            'is_submitted'        => (bool) $item->is_submitted_recommendation,
            'submitted_by'        => $submitted_by_name,
            'submitted_at'        => $item->recommendation_submitted_at,
            'recommendation_submitted_at' => $item->recommendation_submitted_at,
            'approval_status'     => $item->approval_status,
            'approved_by'         => $approved_by_name,
            'approved_at'         => $item->approved_at,
            'rejected_by'         => $rejected_by_name,
            'rejected_at'         => $item->rejected_at,
            'rejection_note'      => $item->rejection_note,
            'approval_notes'      => $item->approval_notes,
            'header_approval_notes' => $headerApprovalNotes,
        ];
    });

    return json(200, true, 'Data Ditemukan', 'Daftar bulan rekomendasi berhasil diambil.', $data);
}

// Simpan note rekomendasi dan langsung submit(Yang digunakan sekarang)
public function saveNoteRecommendation(Request $request, $id)
{
    $user = Auth::user();

    // hanya role 1 dan 5 yang boleh simpan note rekomendasi
    $roleCheck = check_role($user, [1,5]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    // Validasi dengan custom message
    $validator = \Validator::make($request->all(), [
        'note_recommendation' => 'required|string',
    ], [
        'note_recommendation.required' => 'Maaf anda belum mengisi Rekomendasi'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Maaf anda belum mengisi Rekomendasi', null);
    }

    // Pengecekan tambahan untuk memastikan note_recommendation tidak kosong setelah di-trim
    $noteRecommendation = trim($request->note_recommendation);

    if (empty($noteRecommendation)) {
        return json(400, false, 'Validasi Gagal', 'Maaf anda belum mengisi data', null);
    }

    $monthly = TrRiskMonthly::find($id);
    if (!$monthly) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data bulan tidak ditemukan.', null);
    }

    // Role 2 dibatasi department (role 1,4,5 bebas)
    if (!in_array($user->role_id, [1,4,5]) && $monthly->department_id != $user->department_id) {
        return json(403, false, 'Forbidden', 'Tidak punya akses untuk mengubah data departemen lain.', null);
    }

    // Pengecekan status yang lebih ketat - hanya bisa diubah jika belum submit atau status rejected
    if ($monthly->is_submitted_recommendation && $monthly->approval_status !== 'rejected') {
        if ($monthly->approval_status === 'approved') {
            return json(400, false, 'Tidak Bisa Diubah', 'Rekomendasi bulan ini sudah disetujui dan tidak bisa diubah lagi.', null);
        } else {
            return json(400, false, 'Tidak Bisa Diubah', 'Rekomendasi bulan ini sudah disubmit dan sedang menunggu review. Tidak bisa diubah.', null);
        }
    }

    $monthly->note_recommendation = $noteRecommendation;
    $monthly->updated_by = $user->id;

    // Langsung submit setelah save
    $monthly->is_submitted_recommendation = true;
    $monthly->recommendation_submitted_by = $user->id;
    $monthly->recommendation_submitted_at = now();

    // Reset status jika sedang di-edit ulang setelah reject
    if ($monthly->approval_status === 'rejected') {
        $monthly->approval_status = 'pending';
        $monthly->rejected_by = null;
        $monthly->rejected_at = null;
        $monthly->rejection_note = null;
    } else if (!$monthly->approval_status || $monthly->approval_status === null) {
        // Set status pending untuk data baru
        $monthly->approval_status = 'pending';
    }

    $monthly->save();

    return json(200, true, 'Berhasil', 'Catatan rekomendasi berhasil disimpan dan disubmit. Menunggu review.', $monthly);
}

// Submit rekomendasi (opsi terpisah dari save note)
public function submitRecommendation(Request $request, $id)
{
    $user = Auth::user();

    // Hanya role 1 dan 5 yang boleh submit rekomendasi
    $roleCheck = check_role($user, [1,5]);
    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $monthly = TrRiskMonthly::find($id);
    if (!$monthly) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data bulan tidak ditemukan.', null);
    }

    // Role 2 dibatasi department (role 1,4,5 bebas)
    if (!in_array($user->role_id, [1,4,5]) && $monthly->department_id != $user->department_id) {
        return json(403, false, 'Forbidden', 'Tidak punya akses untuk submit data departemen lain.', null);
    }

    if (empty($monthly->note_recommendation)) {
        return json(400, false, 'Gagal', 'Note rekomendasi harus diisi sebelum submit.', null);
    }

    // Cek status submit
    if ($monthly->is_submitted_recommendation) {
        if ($monthly->approval_status === 'approved') {
            return json(400, false, 'Sudah Disetujui', 'Rekomendasi bulan ini sudah disetujui dan tidak bisa disubmit lagi.', null);
        }

        if ($monthly->approval_status === 'pending' || is_null($monthly->approval_status)) {
            return json(400, false, 'Sudah Disubmit', 'Maaf, anda sudah submit. Harap tunggu proses reviewnya.', null);
        }
        // kalau 'rejected' → lanjut ke bawah (boleh submit ulang)
    }

    // Set data submit baru
    $monthly->is_submitted_recommendation = true;
    $monthly->recommendation_submitted_by = $user->id;
    $monthly->recommendation_submitted_at = now();

    // Reset status kalau sebelumnya ditolak
    if ($monthly->approval_status === 'rejected') {
        $monthly->approval_status = 'pending';
        $monthly->rejected_by = null;
        $monthly->rejected_at = null;
        $monthly->rejection_note = null;
    } else if (!$monthly->approval_status || $monthly->approval_status === null) {
        // Set status pending untuk data baru
        $monthly->approval_status = 'pending';
    }

    $monthly->save();

    return json(200, true, 'Berhasil', 'Rekomendasi berhasil disubmit dan sedang menunggu review.', $monthly);
}

public function approveRecommendation(Request $request, $id)
{
    $user = Auth::user();

    // Hanya role 1,4 yang boleh approve (role 2,3,5 tidak boleh)
    $roleCheck = check_role($user, [1, 4]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $request->validate([
        'approval_notes' => 'nullable|string',
    ]);

    $monthly = TrRiskMonthly::find($id);
    if (!$monthly) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data bulan tidak ditemukan.', null);
    }

    if (!$monthly->is_submitted_recommendation) {
        return json(400, false, 'Belum Disubmit', 'Rekomendasi harus disubmit terlebih dahulu sebelum bisa disetujui.', null);
    }

    // PERUBAHAN: Pesan yang lebih informatif untuk approve berulang
    if ($monthly->approval_status === 'approved') {
        return json(400, false, 'Sudah Disetujui', 'Rekomendasi bulan ini sudah disetujui sebelumnya. Tidak perlu approve lagi.', null);
    }

    if ($monthly->approval_status === 'rejected') {
        return json(400, false, 'Sudah Ditolak', 'Rekomendasi bulan ini sudah ditolak. Harap tunggu submit ulang dari user.', null);
    }

    $monthly->approval_status = 'approved';
    $monthly->approved_by = $user->id;
    $monthly->approved_at = now();
    $monthly->approval_notes = $request->approval_notes;
    $monthly->save();

    return json(200, true, 'Berhasil', 'Rekomendasi berhasil disetujui.', $monthly);
}

public function rejectRecommendation(Request $request, $id)
{
    $user = Auth::user();

    // Hanya role 1,4 yang boleh reject (role 2,3,5 tidak boleh)
    $roleCheck = check_role($user, [1,4]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    // pengecekan validasi
    $validator = \Validator::make($request->all(), [
        'rejection_note' => 'required|string',
    ], [
        'rejection_note.required' => 'Maaf anda belum mengisi data'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Maaf anda belum mengisi data', null);
    }

    // Pengecekan tambahan untuk memastikan rejection_note tidak kosong setelah di-trim
    $rejectionNote = trim($request->rejection_note);

    if (empty($rejectionNote)) {
        return json(400, false, 'Validasi Gagal', 'Maaf anda belum mengisi data', null);
    }

    $monthly = TrRiskMonthly::find($id);
    if (!$monthly) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data bulan tidak ditemukan.', null);
    }

    if (!$monthly->is_submitted_recommendation) {
        return json(400, false, 'Belum Disubmit', 'Rekomendasi harus disubmit terlebih dahulu sebelum bisa ditolak.', null);
    }

    if ($monthly->approval_status === 'approved') {
        return json(400, false, 'Sudah Disetujui', 'Rekomendasi bulan ini sudah disetujui dan tidak bisa ditolak lagi.', null);
    }

    // Pesan yang lebih informatif untuk reject berulang
    if ($monthly->approval_status === 'rejected') {
        return json(400, false, 'Sudah Ditolak', 'Rekomendasi bulan ini sudah ditolak sebelumnya. Tidak perlu reject lagi.', null);
    }

    $monthly->approval_status = 'rejected';
    $monthly->rejected_by     = $user->id;
    $monthly->rejected_at     = now();
    $monthly->rejection_note  = $rejectionNote;
    $monthly->save();

    return json(200, true, 'Berhasil', 'Rekomendasi berhasil ditolak. User dapat melakukan perbaikan dan submit ulang.', $monthly);
}

}
