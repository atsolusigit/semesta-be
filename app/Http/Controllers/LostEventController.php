<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LostEvent;
use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LostEventController extends Controller
{

    //=====================================
    // LIST LOST EVENT WITH FILTER < 50%
    //=====================================
   public function index(Request $request)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data ini.', null);
    }

    $perPage = $request->input('per_page', 10);
    $filterType = strtolower($request->query('type', ''));
    $search = $request->query('search');

    $headers = TrRiskHeader::with([
        'department:id,name',
        'optionTargetSatuTahun:id,name,type',
        'monthlyData' => function ($query) {
            $query->where('is_finalize', true)->orderBy('month', 'asc');
        }
    ])
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        $query->where('department_id', $user->department_id);
    })
    ->when($request->tahun, function ($query) use ($request) {
        $query->where('year', $request->tahun);
    })
    ->when($request->department_id, function ($query) use ($request) {
        $query->where('department_id', $request->department_id);
    })
    ->when($request->department, function ($query) use ($request) {
        $query->whereHas('department', function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->department . '%');
        });
    })
    ->when($request->jenis_risiko, function ($query) use ($request) {
        $query->where('jenis_risiko', 'like', '%' . $request->jenis_risiko . '%');
    })
    ->when($search, function ($query) use ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('year', 'like', '%' . $search . '%')
              ->orWhere('jenis_risiko', 'like', '%' . $search . '%')
              ->orWhere('peristiwa_risiko', 'like', '%' . $search . '%')
              ->orWhere('mitigasi', 'like', '%' . $search . '%')
              ->orWhereHas('department', function ($dept) use ($search) {
                  $dept->where('name', 'like', '%' . $search . '%');
              });
        });
    })
    ->orderBy('id', 'desc')
    ->get();

    $filteredData = collect();

    foreach ($headers as $item) {
        $targetType = optional($item->optionTargetSatuTahun)->type;

        if (!$targetType) {
            if (!empty($item->target_quantitative_satu_tahun) && preg_match('/\d/', $item->target_quantitative_satu_tahun)) {
                $targetType = 'kuantitatif';
            } elseif (!empty($item->target_satu_tahun_notes)) {
                $targetType = 'kualitatif';
            }
        }

        $normalizedType = strtolower($targetType);
        if ($filterType && $filterType !== $normalizedType) {
            continue;
        }

        $shouldInclude = false;
        $percentage = 0;
        $targetValue = null;
        $realizationValue = null;

        if (in_array($normalizedType, ['kuantitatif', 'quantitative'])) {
            $totalTarget = 0;
            $totalRealisasi = 0;

            foreach ($item->monthlyData as $monthly) {
                $targetNum = (float) preg_replace('/[^0-9]/', '', $monthly->target_quantitative ?? '0');
                $realNum = (float) preg_replace('/[^0-9]/', '', $monthly->realization_quantitative ?? '0');
                $totalTarget += $targetNum;
                $totalRealisasi += $realNum;
            }

            if ($totalTarget > 0) {
                $targetValue = $totalTarget;
                $realizationValue = $totalRealisasi;
                $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
                $shouldInclude = $percentage <= 50;
            }

        } elseif (in_array($normalizedType, ['kualitatif', 'qualitative'])) {
            $targetValue = 100;
            $desemberData = $item->monthlyData->firstWhere('month', 12);

            if ($desemberData && !empty($desemberData->realization_kualitatif)) {
                $realText = trim(str_replace(['%', ','], ['', '.'], $desemberData->realization_kualitatif));
                $realizationValue = (float) $realText;
                $percentage = round($realizationValue, 2);
                $shouldInclude = $percentage <= 50;
            }
        }

        if ($shouldInclude) {
            $item->calculated_percentage = $percentage;
            $item->calculated_target = $targetValue;
            $item->calculated_realization = $realizationValue;
            $item->detected_type = $normalizedType;
            $filteredData->push($item);
        }
    }

    $headerIds = $filteredData->pluck('id')->toArray();

    $lostEvents = LostEvent::whereIn('header_id', $headerIds)
        ->with(['createdBy:id,username,name', 'updatedBy:id,username,name'])
        ->withTrashed()
        ->get()
        ->keyBy('header_id');

    $page = $request->input('page', 1);
    $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
        $filteredData->forPage($page, $perPage),
        $filteredData->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    $orderedData = $paginatedData->getCollection()->map(function ($item) use ($lostEvents) {
        $lostEvent = $lostEvents->get($item->id);

        return [
            'lost_event_id' => $lostEvent->id ?? null,
            'header_id' => $item->id,
            'tahun' => $item->year,
            'risk_owner_department' => optional($item->department)->name ?? '',
            'jenis_risiko' => $item->jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian ?? '',
            'identifikasi_kejadian' => $item->peristiwa_risiko ?? '',
            'kategori_kejadian' => $lostEvent->kategori_kejadian ?? null,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian ?? null,
            'penyebab_kejadian' => $item->penyebab_risiko ?? '',
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian ?? null,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian ?? null,
            'pihak_terkait' => $lostEvent->pihak_terkait ?? null,
            'status_asuransi' => $lostEvent->status_asuransi ?? null,
            'kategori_risiko_bumn' => null,
            'kategori_risiko_t2_t3_kbumn' => null,
            'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian ?? null,
            'nilai_kerugian' => $lostEvent->nilai_kerugian ?? null,
            'kejadian_berulang' => $lostEvent->kejadian_berulang ?? null,
            'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian ?? null,
            'mitigasi_yang_direncanakan' => $item->mitigasi ?? '',
            'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi ?? null,
            'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang ?? null,
            'nilai_premi' => $lostEvent->nilai_premi ?? null,
            'nilai_klaim' => $lostEvent->nilai_klaim ?? null,
            'has_lost_event' => (bool) $lostEvent,
            'created_at' => $lostEvent?->created_at?->format('Y-m-d'),
            'updated_at' => $lostEvent?->updated_at?->format('Y-m-d'),
            'created_by' => $lostEvent->created_by ?? null,
            'created_by_name' => $lostEvent ? get_decrypted_name($lostEvent->createdBy) : null,
            'updated_by' => $lostEvent->updated_by ?? null,
            'updated_by_name' => $lostEvent ? get_decrypted_name($lostEvent->updatedBy) : null,
            'type' => $item->detected_type ?? 'unknown',
            'realization_percentage' => $item->calculated_percentage !== null
                ? rtrim(rtrim(number_format($item->calculated_percentage, 2), '0'), '.') . '%'
                : null,
        ];
    })->values();

    $cleanData = clean_recursive([
        'current_page' => $paginatedData->currentPage(),
        'per_page' => $paginatedData->perPage(),
        'total' => $paginatedData->total(),
        'last_page' => $paginatedData->lastPage(),
        'from' => $paginatedData->firstItem(),
        'to' => $paginatedData->lastItem(),
        'data' => $orderedData,
    ]);

    return json(200, true, 'Data Ditemukan', 'Data header dengan realisasi < 50% berhasil diambil.', $cleanData);
}

   //=====================================
    // SHOW DETAIL LOST EVENT
    //=====================================
  public function show($headerId)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data ini.', null);
    }

    $header = TrRiskHeader::with([
        'department:id,name',
        'optionTargetSatuTahun:id,name,type',
        'monthlyData' => function ($query) {
            $query->where('is_finalize', true)->orderBy('month', 'asc');
        }
    ])
        ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            $query->where('department_id', $user->department_id);
        })
        ->find($headerId);

    if (!$header) {
        return json(404, false, 'Tidak Ditemukan', 'Header tidak ditemukan.', null);
    }

    // Hitung realization percentage dan type
    $targetType = $header->optionTargetSatuTahun->type ?? null;

    // Deteksi manual kalau type kosong
    if (!$targetType) {
        if (!empty($header->target_quantitative_satu_tahun)) {
            if (preg_match('/\d/', $header->target_quantitative_satu_tahun)) {
                $targetType = 'kuantitatif';
            }
        }
        if (!empty($header->target_satu_tahun_notes) && empty($targetType)) {
            $targetType = 'kualitatif';
        }
    }

    // Normalisasi penulisan
    $normalizedType = strtolower($targetType);

    $percentage = 0;
    $targetValue = null;
    $realizationValue = null;

    if ($normalizedType === 'kuantitatif' || $normalizedType === 'quantitative') {
        // Hitung total target dan realisasi 12 bulan
        $totalTarget = 0;
        $totalRealisasi = 0;

        foreach ($header->monthlyData as $monthly) {
            $targetText = $monthly->target_quantitative ?? '0';
            $targetNum = (float)str_replace([',', '.', ' '], ['', '', ''], $targetText);
            $totalTarget += $targetNum;

            $realText = $monthly->realization_quantitative ?? '0';
            $realNum = (float)str_replace([',', '.', ' '], ['', '', ''], $realText);
            $totalRealisasi += $realNum;
        }

        if ($totalTarget > 0) {
            $targetValue = $totalTarget;
            $realizationValue = $totalRealisasi;
            $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
        }

    } elseif ($normalizedType === 'kualitatif' || $normalizedType === 'qualitative') {
        // Ambil hanya bulan Desember
        $targetValue = 100;
        $desemberData = $header->monthlyData->firstWhere('month', 12);

        if ($desemberData && !empty($desemberData->realization_kualitatif)) {
            $realText = $desemberData->realization_kualitatif;
            $realizationValue = (float)str_replace(['%', ' ', ','], ['', '', '.'], trim($realText));
            $percentage = round($realizationValue, 2);
        }
    }

    $lostEvent = LostEvent::where('header_id', $headerId)
        ->with(['createdBy:id,username', 'updatedBy:id,username'])
        ->first();

    if (!$lostEvent) {
        try {
            DB::beginTransaction();

            // Ambil penyebab_risiko dari header - SAMA seperti di index()
            $penyebabKejadian = $header->penyebab_risiko ?? '';

            $lostEvent = LostEvent::create([
                'header_id' => $header->id,
                'tahun' => $header->year,
                'risk_owner_department' => optional($header->department)->name ?? '',
                'jenis_risiko' => $header->jenis_risiko ?? '',
                'nama_kejadian' => '',
                'identifikasi_kejadian' => $header->peristiwa_risiko ?? '',
                'kategori_kejadian' => null,
                'sumber_penyebab_kejadian' => null,
                'penyebab_kejadian' => $penyebabKejadian,
                'penanganan_saat_kejadian' => null,
                'deskripsi_kejadian' => null,
                'pihak_terkait' => null,
                'status_asuransi' => null,
                'kategori_risiko_bumn' => null,
                'kategori_risiko_t2_t3_kbumn' => null,
                'penjelasan_kerugian' => null,
                'nilai_kerugian' => null,
                'kejadian_berulang' => null,
                'frekuensi_kejadian' => null,
                'mitigasi_yang_direncanakan' => $header->mitigasi ?? '',
                'realisasi_mitigasi' => null,
                'perbaikan_mendatang' => null,
                'nilai_premi' => null,
                'nilai_klaim' => null,
                'created_by' => $user->id,
            ]);

            DB::commit();

            $lostEvent->load(['createdBy:id,username', 'updatedBy:id,username']);
        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Error', 'Terjadi kesalahan saat membuat lost event.', [
                'error' => $e->getMessage()
            ]);
        }
    }

    $data = [
        'lost_event_id' => $lostEvent->id,
        'header_id' => $lostEvent->header_id,
        'tahun' => $lostEvent->tahun,
        'risk_owner_department' => $lostEvent->risk_owner_department,
        'jenis_risiko' => $lostEvent->jenis_risiko,
        'nama_kejadian' => $lostEvent->nama_kejadian,
        'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
        'kategori_kejadian' => $lostEvent->kategori_kejadian,
        'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
        'penyebab_kejadian' => $lostEvent->penyebab_kejadian ?: ($header->penyebab_risiko ?? ''),
        'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
        'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
        'pihak_terkait' => $lostEvent->pihak_terkait,
        'status_asuransi' => $lostEvent->status_asuransi,
        'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn,
        'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn,
        'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian,
        'nilai_kerugian' => $lostEvent->nilai_kerugian,
        'kejadian_berulang' => $lostEvent->kejadian_berulang,
        'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian,
        'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan,
        'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi,
        'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang,
        'nilai_premi' => $lostEvent->nilai_premi,
        'nilai_klaim' => $lostEvent->nilai_klaim,
        'created_at' => $lostEvent && $lostEvent->created_at? $lostEvent->created_at->format('Y-m-d'): null,
        'updated_at' => $lostEvent && $lostEvent->updated_at? $lostEvent->updated_at->format('Y-m-d')  : null,
        'created_by' => $lostEvent->created_by,
        'created_by_name' => get_decrypted_name($lostEvent->createdBy),
        'updated_by' => $lostEvent->updated_by,
        'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
        'type' => $normalizedType ?? 'unknown',
        'realization_percentage' => $percentage !== null
            ? rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%'
            : null,
    ];

    $cleanData = clean_recursive($data);

    return json(200, true, 'Data Ditemukan', 'Detail lost event berhasil diambil.', $cleanData);
}

//=====================================
// GET DETAIL LOST EVENT BY ID
//=====================================
public function detail($id)
{
    $user = auth()->user();

    // Batasi role yang boleh akses
    if (!in_array($user->role_id, [1, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data ini.', null);
    }

    // Ambil lost event berdasarkan ID dengan relasi header
    $lostEvent = LostEvent::with([
        'createdBy:id,username',
        'updatedBy:id,username',
        'header' => function($query) {
            $query->with([
                'optionTargetSatuTahun:id,name,type',
                'monthlyData' => function ($q) {
                    $q->where('is_finalize', true)->orderBy('month', 'asc');
                }
            ]);
        }
    ])->find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    // Hitung realization percentage dan type dari header
    $header = $lostEvent->header;
    $targetType = optional($header->optionTargetSatuTahun)->type ?? null;

    // Deteksi manual kalau type kosong
    if (!$targetType && $header) {
        if (!empty($header->target_quantitative_satu_tahun)) {
            if (preg_match('/\d/', $header->target_quantitative_satu_tahun)) {
                $targetType = 'kuantitatif';
            }
        }
        if (!empty($header->target_satu_tahun_notes) && empty($targetType)) {
            $targetType = 'kualitatif';
        }
    }

    // Normalisasi penulisan
    $normalizedType = strtolower($targetType ?? 'unknown');

    $percentage = 0;
    $targetValue = null;
    $realizationValue = null;

    if ($header && ($normalizedType === 'kuantitatif' || $normalizedType === 'quantitative')) {
        // Hitung total target dan realisasi 12 bulan
        $totalTarget = 0;
        $totalRealisasi = 0;

        foreach ($header->monthlyData as $monthly) {
            $targetText = $monthly->target_quantitative ?? '0';
            $targetNum = (float)str_replace([',', '.', ' '], ['', '', ''], $targetText);
            $totalTarget += $targetNum;

            $realText = $monthly->realization_quantitative ?? '0';
            $realNum = (float)str_replace([',', '.', ' '], ['', '', ''], $realText);
            $totalRealisasi += $realNum;
        }

        if ($totalTarget > 0) {
            $targetValue = $totalTarget;
            $realizationValue = $totalRealisasi;
            $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
        }

    } elseif ($header && ($normalizedType === 'kualitatif' || $normalizedType === 'qualitative')) {
        // Ambil hanya bulan Desember
        $targetValue = 100;
        $desemberData = $header->monthlyData->firstWhere('month', 12);

        if ($desemberData && !empty($desemberData->realization_kualitatif)) {
            $realText = $desemberData->realization_kualitatif;
            $realizationValue = (float)str_replace(['%', ' ', ','], ['', '', '.'], trim($realText));
            $percentage = round($realizationValue, 2);
        }
    }

    // Siapkan data respons
    $data = [
        'lost_event_id' => $lostEvent->id,
        'header_id' => $lostEvent->header_id,
        'tahun' => $lostEvent->tahun,
        'risk_owner_department' => $lostEvent->risk_owner_department,
        'jenis_risiko' => $lostEvent->jenis_risiko,
        'nama_kejadian' => $lostEvent->nama_kejadian,
        'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
        'kategori_kejadian' => $lostEvent->kategori_kejadian,
        'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
        'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
        'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
        'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
        'pihak_terkait' => $lostEvent->pihak_terkait,
        'status_asuransi' => $lostEvent->status_asuransi,
        'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn,
        'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn,
        'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian,
        'nilai_kerugian' => $lostEvent->nilai_kerugian,
        'kejadian_berulang' => $lostEvent->kejadian_berulang,
        'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian,
        'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan,
        'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi,
        'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang,
        'nilai_premi' => $lostEvent->nilai_premi,
        'nilai_klaim' => $lostEvent->nilai_klaim,
        'created_at' => $lostEvent->created_at ? $lostEvent->created_at->format('Y-m-d') : null,
        'updated_at' => $lostEvent->updated_at ? $lostEvent->updated_at->format('Y-m-d') : null,
        'created_by' => $lostEvent->created_by,
        'created_by_name' => get_decrypted_name($lostEvent->createdBy),
        'updated_by' => $lostEvent->updated_by,
        'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
        'type' => $normalizedType,
        'realization_percentage' => $percentage !== null
            ? rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%'
            : null,
    ];

    $cleanData = clean_recursive($data);

    return json(200, true, 'Data Ditemukan', 'Detail lost event berhasil diambil.', $cleanData);
}

    /**
     * Update lost event
     * Role 1: Bisa update semua data
     * Role 2: Bisa update hanya data department mereka
     */
    public function update(Request $request, $id)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk mengubah data.', null);
    }

    $lostEvent = LostEvent::find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    if ($user->role_id === 2) {
        $userDepartmentName = optional($user->department)->name ?? '';
        if ($lostEvent->risk_owner_department !== $userDepartmentName) {
            return json(403, false, 'Forbidden', 'Anda hanya dapat mengubah data untuk department Anda sendiri.', null);
        }
    }

    // Field bawaan header TIDAK required saat update
    $validator = Validator::make($request->all(), [
        'tahun' => 'nullable|string|max:4',
        'risk_owner_department' => 'nullable|string|max:255',
        'jenis_risiko' => 'nullable|string|max:255',
        'nama_kejadian' => 'required|string|max:255',
        'identifikasi_kejadian' => 'nullable|string',
        'kategori_kejadian' => 'nullable|string|max:255',
        'sumber_penyebab_kejadian' => 'nullable|string',
        'penyebab_kejadian' => 'nullable|string',
        'penanganan_saat_kejadian' => 'nullable|string',
        'deskripsi_kejadian' => 'nullable|string',
        'pihak_terkait' => 'nullable|string|max:255',
        'status_asuransi' => 'nullable|string|max:255',
        'kategori_risiko_bumn' => 'nullable|string|max:255',
        'kategori_risiko_t2_t3_kbumn' => 'nullable|string|max:255',
        'penjelasan_kerugian' => 'nullable|string',
        'nilai_kerugian' => 'nullable|numeric|min:0',
        'kejadian_berulang' => 'nullable|string|max:255',
        'frekuensi_kejadian' => 'nullable|string|max:255',
        'mitigasi_yang_direncanakan' => 'nullable|string',
        'realisasi_mitigasi' => 'nullable|string',
        'perbaikan_mendatang' => 'nullable|string',
        'nilai_premi' => 'nullable|numeric|min:0',
        'nilai_klaim' => 'nullable|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return json(422, false, 'Validasi Gagal', 'Data yang dikirim tidak valid.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        $data = $validator->validated();

        // Hapus field header agar tidak bisa diubah
        unset(
            $data['tahun'],
            $data['risk_owner_department'],
            $data['jenis_risiko'],
            $data['identifikasi_kejadian'],
            $data['mitigasi_yang_direncanakan']
        );

        if ($user->role_id === 2) {
            $userDepartmentName = optional($user->department)->name ?? '';
            if ($lostEvent->risk_owner_department !== $userDepartmentName) {
                return json(403, false, 'Forbidden', 'Anda tidak dapat mengubah department ke department lain.', null);
            }
        }

        $data['updated_by'] = $user->id;

        $lostEvent->update($data);

        DB::commit();

        return json(200, true, 'Berhasil', 'Lost event berhasil diupdate.', [
            'id' => $lostEvent->id,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Error', 'Terjadi kesalahan saat mengupdate data.', [
            'error' => $e->getMessage()
        ]);
    }
}
    /**
     * Delete lost event (soft delete)
     * Hanya Role 1 yang bisa delete
     */
    public function destroy($id)
{
    $user = auth()->user();

    // Hanya role_id 1 yang boleh hapus
    if ($user->role_id !== 1) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk menghapus data.', null);
    }

    // Cari lost event berdasarkan ID
    $lostEvent = LostEvent::where('id', $id)->first();

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    try {
        DB::beginTransaction();

        // Hapus data lost event secara permanen
        $lostEvent->forceDelete();

        DB::commit();

        return json(200, true, 'Berhasil', 'Lost event berhasil dihapus.', null);
    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Error', 'Terjadi kesalahan saat menghapus data.', [
            'error' => $e->getMessage()
        ]);
    }
}

}
