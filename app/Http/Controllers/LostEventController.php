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

    //===============================================================
    // LIST LOST EVENT HEADER WITH REALIZATION BELOW DANGER THRESHOLD
    //===============================================================
  public function index(Request $request)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data ini.', null);
    }

    $perPage = $request->input('per_page');
    $filterType = strtolower($request->query('type', ''));
    $search = $request->query('search');

    // Query headers dengan filter threshold
    $headers = TrRiskHeader::with([
        'department:id,name',
        'jenisRisiko:id,nama_jenis_risiko',
        'optionTargetSatuTahun:id,name,type',
        'rcsa:id,kategori_threshold_kri_aman,kategori_threshold_kri_hati_hati,kategori_threshold_kri_bahaya,kategori_risiko_bumn,kategori_risiko_t2_t3_kbumn',
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
        $search = $request->jenis_risiko;
        if (is_numeric($search)) {
            $query->where('jenis_risiko', $search);
        } else {
            $query->whereHas('jenisRisiko', function ($q) use ($search) {
                $q->where('nama_jenis_risiko', 'like', '%' . $search . '%');
            });
        }
    })
    ->when($search, function ($query) use ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('year', 'like', '%' . $search . '%')
            ->orWhere('peristiwa_risiko', 'like', '%' . $search . '%')
            ->orWhere('mitigasi', 'like', '%' . $search . '%')
            ->orWhereHas('department', function ($dept) use ($search) {
                $dept->where('name', 'like', '%' . $search . '%');
            })
            ->orWhereHas('jenisRisiko', function ($jr) use ($search) {
                $jr->where('nama_jenis_risiko', 'like', '%' . $search . '%');
            });
        });
    })
    ->orderBy('id', 'desc')
    ->get()
    ->filter(function ($header) {
        return $header->monthlyData->count() === 12;
    });

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

        // Ambil threshold dari RCSA
        $thresholdAman = null;
        $thresholdHatiHati = null;
        $thresholdBahaya = null;

        if ($item->rcsa) {
            $thresholdAman = (float) str_replace(['%', ','], ['', '.'], $item->rcsa->kategori_threshold_kri_aman ?? '0');
            $thresholdHatiHati = (float) str_replace(['%', ','], ['', '.'], $item->rcsa->kategori_threshold_kri_hati_hati ?? '0');
            $thresholdBahaya = (float) str_replace(['%', ','], ['', '.'], $item->rcsa->kategori_threshold_kri_bahaya ?? '0');
        }

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

                // LOGIKA BARU: Hanya muncul jika realisasi <= threshold_bahaya
                if ($item->rcsa && $thresholdBahaya > 0) {
                    $shouldInclude = $percentage <= $thresholdBahaya;
                } else {
                    // Fallback ke logika lama jika tidak ada RCSA
                    $shouldInclude = $percentage <= 50;
                }
            }

        } elseif (in_array($normalizedType, ['kualitatif', 'qualitative'])) {
            $totalTarget = 0;
            $totalRealisasi = 0;

            foreach ($item->monthlyData as $monthly) {
                $targetText = trim(str_replace(['%', ','], ['', '.'], $monthly->target_kualitatif ?? '0'));
                $targetNum = (float) $targetText;

                $realText = trim(str_replace(['%', ','], ['', '.'], $monthly->realization_kualitatif ?? '0'));
                $realNum = (float) $realText;

                $totalTarget += $targetNum;
                $totalRealisasi += $realNum;
            }

            if ($totalTarget > 0) {
                $targetValue = $totalTarget;
                $realizationValue = $totalRealisasi;
                $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);

                // LOGIKA BARU: Hanya muncul jika realisasi <= threshold_bahaya
                if ($item->rcsa && $thresholdBahaya > 0) {
                    $shouldInclude = $percentage <= $thresholdBahaya;
                } else {
                    // Fallback ke logika lama jika tidak ada RCSA
                    $shouldInclude = $percentage <= 50;
                }
            }
        }

        if ($shouldInclude) {
            $item->calculated_percentage = $percentage;
            $item->calculated_target = $targetValue;
            $item->calculated_realization = $realizationValue;
            $item->detected_type = $normalizedType;
            $item->threshold_aman = $thresholdAman;
            $item->threshold_hati_hati = $thresholdHatiHati;
            $item->threshold_bahaya = $thresholdBahaya;
            $filteredData->push($item);
        }
    }

    $headerIds = $filteredData->pluck('id')->toArray();

    // Ambil lost events yang terkait dengan headers
    $lostEventsWithHeader = LostEvent::whereIn('header_id', $headerIds)
        ->with(['createdBy:id,username,name', 'updatedBy:id,username,name'])
        ->withTrashed()
        ->get()
        ->keyBy('header_id');

    // Ambil lost events yang independen (tanpa header_id)
    $independentLostEvents = LostEvent::whereNull('header_id')
        ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            $userDepartmentName = optional($user->department)->name ?? '';
            if ($userDepartmentName) {
                $query->where('risk_owner_department', $userDepartmentName);
            }
        })
        ->when($request->tahun, function ($query) use ($request) {
            $query->where('tahun', $request->tahun);
        })
        ->when($request->department, function ($query) use ($request) {
            $query->where('risk_owner_department', 'like', '%' . $request->department . '%');
        })
        ->when($request->jenis_risiko, function ($query) use ($request) {
            $query->where('jenis_risiko', 'like', '%' . $request->jenis_risiko . '%');
        })
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tahun', 'like', '%' . $search . '%')
                ->orWhere('nama_kejadian', 'like', '%' . $search . '%')
                ->orWhere('identifikasi_kejadian', 'like', '%' . $search . '%')
                ->orWhere('risk_owner_department', 'like', '%' . $search . '%')
                ->orWhere('jenis_risiko', 'like', '%' . $search . '%');
            });
        })
        ->with(['createdBy:id,username,name', 'updatedBy:id,username,name'])
        ->withTrashed()
        ->get();

    // Gabungkan data dari headers dan independent lost events
    $allData = collect();

    // Data dari headers (dengan lost event atau tanpa)
    foreach ($filteredData as $item) {
        $lostEvent = $lostEventsWithHeader->get($item->id);

        $allData->push([
            'lost_event_id' => $lostEvent->id ?? null,
            'header_id' => $item->id,
            'rcsa_id' => $item->rcsa_id,
            'tahun' => $item->year,
            'risk_owner_department' => optional($item->department)->name ?? '',
            'jenis_risiko_id' => $item->jenis_risiko ?? null,
            'jenis_risiko' => $item->jenisRisiko->nama_jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian ?? '',
            'identifikasi_kejadian' => $item->peristiwa_risiko ?? '',
            'kategori_kejadian' => $lostEvent->kategori_kejadian ?? null,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian ?? null,
            'penyebab_kejadian' => $item->penyebab_risiko ?? '',
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian ?? null,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian ?? null,
            'pihak_terkait' => $lostEvent->pihak_terkait ?? null,
            'status_asuransi' => $lostEvent->status_asuransi ?? null,
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? ($item->rcsa->kategori_risiko_bumn ?? null),
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? ($item->rcsa->kategori_risiko_t2_t3_kbumn ?? null),
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
            'threshold_aman' => $item->threshold_aman,
            'threshold_hati_hati' => $item->threshold_hati_hati,
            'threshold_bahaya' => $item->threshold_bahaya,
        ]);
    }

    // Data dari independent lost events (tanpa header)
    foreach ($independentLostEvents as $lostEvent) {
        $allData->push([
            'lost_event_id' => $lostEvent->id,
            'header_id' => null,
            'rcsa_id' => $lostEvent->rcsa_id,
            'tahun' => $lostEvent->tahun,
            'risk_owner_department' => $lostEvent->risk_owner_department,
            'jenis_risiko_id' => null,
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
            'has_lost_event' => true,
            'created_at' => $lostEvent->created_at?->format('Y-m-d'),
            'updated_at' => $lostEvent->updated_at?->format('Y-m-d'),
            'created_by' => $lostEvent->created_by,
            'created_by_name' => get_decrypted_name($lostEvent->createdBy),
            'updated_by' => $lostEvent->updated_by,
            'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
            'type' => 'independent',
            'realization_percentage' => null,
            'threshold_aman' => null,
            'threshold_hati_hati' => null,
            'threshold_bahaya' => null,
        ]);
    }

    // Sort: null di atas, lalu DESC berdasarkan lost_event_id
    $sortedData = $allData->sort(function ($a, $b) {
        if ($a['lost_event_id'] === null && $b['lost_event_id'] === null) {
            return 0;
        }
        if ($a['lost_event_id'] === null) {
            return -1;
        }
        if ($b['lost_event_id'] === null) {
            return 1;
        }
        return $b['lost_event_id'] - $a['lost_event_id'];
    })->values();

    // Jika per_page kosong atau = "all", ambil semua data
    if (empty($perPage) || $perPage === 'all') {
        $cleanData = clean_recursive([
            'total' => $sortedData->count(),
            'data' => $sortedData->toArray(),
        ]);

        return json(200, true, 'Data Ditemukan', 'Data header dengan realisasi di bawah threshold bahaya dan lost event independen berhasil diambil.', $cleanData);
    }

    // Kalau per_page dikirim, gunakan pagination
    $page = $request->input('page', 1);
    $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
        $sortedData->forPage($page, $perPage),
        $sortedData->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    $cleanData = clean_recursive([
        'current_page' => $paginatedData->currentPage(),
        'per_page' => $paginatedData->perPage(),
        'total' => $paginatedData->total(),
        'last_page' => $paginatedData->lastPage(),
        'from' => $paginatedData->firstItem(),
        'to' => $paginatedData->lastItem(),
        'data' => array_values($paginatedData->items()),
    ]);

    return json(200, true, 'Data Ditemukan', 'Data header dengan realisasi di bawah threshold bahaya dan lost event independen berhasil diambil.', $cleanData);
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
        'jenisRisiko:id,nama_jenis_risiko',
        'optionTargetSatuTahun:id,name,type',
        'rcsa:id,kategori_threshold_kri_aman,kategori_threshold_kri_hati_hati,kategori_threshold_kri_bahaya,kategori_risiko_bumn,kategori_risiko_t2_t3_kbumn',
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

    if ($header->monthlyData->count() !== 12) {
        return json(400, false, 'Data Tidak Lengkap', 'Data risiko belum memiliki 12 bulan yang difinalisasi.', null);
    }

    $targetType = $header->optionTargetSatuTahun->type ?? null;

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

    $normalizedType = strtolower($targetType);

    $percentage = 0;
    $targetValue = null;
    $realizationValue = null;

    // Ambil threshold dari RCSA
    $thresholdAman = null;
    $thresholdHatiHati = null;
    $thresholdBahaya = null;

    if ($header->rcsa) {
        $thresholdAman = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_aman ?? '0');
        $thresholdHatiHati = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_hati_hati ?? '0');
        $thresholdBahaya = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_bahaya ?? '0');
    }

    if ($normalizedType === 'kuantitatif' || $normalizedType === 'quantitative') {
        $totalTarget = 0;
        $totalRealisasi = 0;

        foreach ($header->monthlyData as $monthly) {
            $targetNum = (float) preg_replace('/[^0-9]/', '', $monthly->target_quantitative ?? '0');
            $realNum = (float) preg_replace('/[^0-9]/', '', $monthly->realization_quantitative ?? '0');
            $totalTarget += $targetNum;
            $totalRealisasi += $realNum;
        }

        if ($totalTarget > 0) {
            $targetValue = $totalTarget;
            $realizationValue = $totalRealisasi;
            $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
        }

    } elseif ($normalizedType === 'kualitatif' || $normalizedType === 'qualitative') {
        $totalTarget = 0;
        $totalRealisasi = 0;

        foreach ($header->monthlyData as $monthly) {
            $targetText = trim(str_replace(['%', ','], ['', '.'], $monthly->target_kualitatif ?? '0'));
            $targetNum = (float) $targetText;

            $realText = trim(str_replace(['%', ','], ['', '.'], $monthly->realization_kualitatif ?? '0'));
            $realNum = (float) $realText;

            $totalTarget += $targetNum;
            $totalRealisasi += $realNum;
        }

        if ($totalTarget > 0) {
            $targetValue = $totalTarget; // DIPERBAIKI: $totalTarget bukan $totalTotal
            $realizationValue = $totalRealisasi;
            $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
        }
    }

    $lostEvent = LostEvent::where('header_id', $headerId)
        ->with(['createdBy:id,username', 'updatedBy:id,username'])
        ->first();

    if (!$lostEvent) {
        try {
            DB::beginTransaction();

            $penyebabKejadian = $header->penyebab_risiko ?? '';
            $kategoririsikobumn = optional($header->rcsa)->kategori_risiko_bumn ?? '';
            $kategoririsikot2t3kbumn = optional($header->rcsa)->kategori_risiko_t2_t3_kbumn ?? '';

            $lostEvent = LostEvent::create([
                'header_id' => $header->id,
                'rcsa_id' => $header->rcsa_id,
                'tahun' => $header->year,
                'risk_owner_department' => optional($header->department)->name ?? '',
                'jenis_risiko' => $header->jenisRisiko->nama_jenis_risiko ?? '',
                'nama_kejadian' => '',
                'identifikasi_kejadian' => $header->peristiwa_risiko ?? '',
                'kategori_kejadian' => null,
                'sumber_penyebab_kejadian' => null,
                'penyebab_kejadian' => $penyebabKejadian,
                'penanganan_saat_kejadian' => null,
                'deskripsi_kejadian' => null,
                'pihak_terkait' => null,
                'status_asuransi' => null,
                'kategori_risiko_bumn' => $kategoririsikobumn,
                'kategori_risiko_t2_t3_kbumn' => $kategoririsikot2t3kbumn,
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
        'rcsa_id' => $header->rcsa_id,
        'tahun' => $lostEvent->tahun,
        'risk_owner_department' => $lostEvent->risk_owner_department,
        'jenis_risiko_id' => $header->jenis_risiko ?? null,
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
        'created_at' => $lostEvent && $lostEvent->created_at ? $lostEvent->created_at->format('Y-m-d') : null,
        'updated_at' => $lostEvent && $lostEvent->updated_at ? $lostEvent->updated_at->format('Y-m-d') : null,
        'created_by' => $lostEvent->created_by,
        'created_by_name' => get_decrypted_name($lostEvent->createdBy),
        'updated_by' => $lostEvent->updated_by,
        'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
        'type' => $normalizedType ?? 'unknown',
        'realization_percentage' => $percentage !== null
            ? rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%'
            : null,
        'threshold_aman' => $thresholdAman,
        'threshold_hati_hati' => $thresholdHatiHati,
        'threshold_bahaya' => $thresholdBahaya,
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

    if (!in_array($user->role_id, [1, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data ini.', null);
    }

    $lostEvent = LostEvent::with([
        'createdBy:id,username',
        'updatedBy:id,username',
        'header' => function($query) {
            $query->with([
                'jenisRisiko:id,nama_jenis_risiko',
                'optionTargetSatuTahun:id,name,type',
                'rcsa:id,kategori_threshold_kri_aman,kategori_threshold_kri_hati_hati,kategori_threshold_kri_bahaya',
                'monthlyData' => function ($q) {
                    $q->where('is_finalize', true)->orderBy('month', 'asc');
                }
            ]);
        }
    ])->find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    $header = $lostEvent->header;

    // Jika lost event independen (tanpa header)
    if (!$header) {
        $data = [
            'lost_event_id' => $lostEvent->id,
            'header_id' => null,
            'rcsa_id' => $lostEvent->rcsa_id,
            'tahun' => $lostEvent->tahun,
            'risk_owner_department' => $lostEvent->risk_owner_department,
            'jenis_risiko_id' => null,
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
            'type' => 'independent',
            'realization_percentage' => null,
            'threshold_aman' => null,
            'threshold_hati_hati' => null,
            'threshold_bahaya' => null,
        ];

        $cleanData = clean_recursive($data);
        return json(200, true, 'Data Ditemukan', 'Detail lost event independen berhasil diambil.', $cleanData);
    }

    // Jika lost event terkait dengan header
    if ($header->monthlyData->count() !== 12) {
        return json(400, false, 'Data Tidak Lengkap', 'Data risiko belum memiliki 12 bulan yang difinalisasi.', null);
    }

    $targetType = optional($header->optionTargetSatuTahun)->type ?? null;

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

    $normalizedType = strtolower($targetType ?? 'unknown');

    $percentage = 0;
    $targetValue = null;
    $realizationValue = null;

    // Ambil threshold dari RCSA
    $thresholdAman = null;
    $thresholdHatiHati = null;
    $thresholdBahaya = null;

    if ($header->rcsa) {
        $thresholdAman = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_aman ?? '0');
        $thresholdHatiHati = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_hati_hati ?? '0');
        $thresholdBahaya = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_bahaya ?? '0');
    }

    if ($header && ($normalizedType === 'kuantitatif' || $normalizedType === 'quantitative')) {
        $totalTarget = 0;
        $totalRealisasi = 0;

        foreach ($header->monthlyData as $monthly) {
            $targetNum = (float) preg_replace('/[^0-9]/', '', $monthly->target_quantitative ?? '0');
            $realNum = (float) preg_replace('/[^0-9]/', '', $monthly->realization_quantitative ?? '0');
            $totalTarget += $targetNum;
            $totalRealisasi += $realNum;
        }

        if ($totalTarget > 0) {
            $targetValue = $totalTarget;
            $realizationValue = $totalRealisasi;
            $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
        }

    } elseif ($header && ($normalizedType === 'kualitatif' || $normalizedType === 'qualitative')) {
        $totalTarget = 0;
        $totalRealisasi = 0;

        foreach ($header->monthlyData as $monthly) {
            $targetText = trim(str_replace(['%', ','], ['', '.'], $monthly->target_kualitatif ?? '0'));
            $targetNum = (float) $targetText;

            $realText = trim(str_replace(['%', ','], ['', '.'], $monthly->realization_kualitatif ?? '0'));
            $realNum = (float) $realText;

            $totalTarget += $targetNum;
            $totalRealisasi += $realNum;
        }

        if ($totalTarget > 0) {
            $targetValue = $totalTarget;
            $realizationValue = $totalRealisasi;
            $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
        }
    }

    $data = [
        'lost_event_id' => $lostEvent->id,
        'header_id' => $lostEvent->header_id,
        'rcsa_id' => $header->rcsa_id,
        'tahun' => $lostEvent->tahun,
        'risk_owner_department' => $lostEvent->risk_owner_department,
        'jenis_risiko_id' => $header->jenis_risiko ?? null,
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
        'threshold_aman' => $thresholdAman,
        'threshold_hati_hati' => $thresholdHatiHati,
        'threshold_bahaya' => $thresholdBahaya,
    ];

    $cleanData = clean_recursive($data);

    return json(200, true, 'Data Ditemukan', 'Detail lost event berhasil diambil.', $cleanData);
}

// Lost Event Create
public function store(Request $request)
{
    $user = auth()->user();

    // Validasi akses role
    if (!in_array($user->role_id, [1, 2, 3, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk membuat data ini.', null);
    }

    // Validasi input - header_id opsional
    $validator = Validator::make($request->all(), [
        'header_id' => 'nullable|exists:tr_risk_headers,id',
        'rcsa_id' => 'nullable|exists:rcsa,id',
        'tahun' => 'required|string|max:4',
        'risk_owner_department' => 'required|string|max:255',
        'jenis_risiko' => 'required|string|max:255',
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

    // Jika ada header_id, ambil data dari header
    $header = null;
    $headerId = $request->header_id;
    $rcsaId = $request->rcsa_id;
    $tahun = $request->tahun;
    $riskOwnerDepartment = $request->risk_owner_department;
    $jenisRisiko = $request->jenis_risiko;
    $identifikasiKejadian = $request->identifikasi_kejadian ?? '';
    $penyebabKejadian = $request->penyebab_kejadian ?? '';
    $mitigasiYangDirencanakan = $request->mitigasi_yang_direncanakan ?? '';
    $jenisRisikoId = null;

    if ($headerId) {
        $header = TrRiskHeader::with([
            'department:id,name',
            'jenisRisiko:id,nama_jenis_risiko',
        ])
        ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            $query->where('department_id', $user->department_id);
        })
        ->find($headerId);

        if (!$header) {
            return json(404, false, 'Tidak Ditemukan', 'Header tidak ditemukan.', null);
        }

        // Cek apakah lost event sudah ada untuk header ini
        $existingLostEvent = LostEvent::where('header_id', $headerId)->first();

        if ($existingLostEvent) {
            return json(409, false, 'Conflict', 'Lost event untuk header ini sudah ada.', null);
        }

        // Override dengan data dari header
        $rcsaId = $header->rcsa_id;
        $tahun = $header->year;
        $riskOwnerDepartment = optional($header->department)->name ?? '';
        $jenisRisiko = $header->jenisRisiko->nama_jenis_risiko ?? '';
        $jenisRisikoId = $header->jenis_risiko ?? null;

        // Gunakan data dari header jika tidak diinput manual
        if (empty($identifikasiKejadian)) {
            $identifikasiKejadian = $header->peristiwa_risiko ?? '';
        }
        if (empty($penyebabKejadian)) {
            $penyebabKejadian = $header->penyebab_risiko ?? '';
        }
        if (empty($mitigasiYangDirencanakan)) {
            $mitigasiYangDirencanakan = $header->mitigasi ?? '';
        }
    }

    try {
        DB::beginTransaction();

        // Buat lost event baru
        $lostEvent = LostEvent::create([
            'header_id' => $headerId,
            'rcsa_id' => $rcsaId,
            'tahun' => $tahun,
            'risk_owner_department' => $riskOwnerDepartment,
            'jenis_risiko' => $jenisRisiko,
            'nama_kejadian' => $request->nama_kejadian,
            'identifikasi_kejadian' => $identifikasiKejadian,
            'kategori_kejadian' => $request->kategori_kejadian,
            'sumber_penyebab_kejadian' => $request->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $penyebabKejadian,
            'penanganan_saat_kejadian' => $request->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $request->deskripsi_kejadian,
            'pihak_terkait' => $request->pihak_terkait,
            'status_asuransi' => $request->status_asuransi,
            'kategori_risiko_bumn' => $request->kategori_risiko_bumn,
            'kategori_risiko_t2_t3_kbumn' => $request->kategori_risiko_t2_t3_kbumn,
            'penjelasan_kerugian' => $request->penjelasan_kerugian,
            'nilai_kerugian' => $request->nilai_kerugian,
            'kejadian_berulang' => $request->kejadian_berulang,
            'frekuensi_kejadian' => $request->frekuensi_kejadian,
            'mitigasi_yang_direncanakan' => $mitigasiYangDirencanakan,
            'realisasi_mitigasi' => $request->realisasi_mitigasi,
            'perbaikan_mendatang' => $request->perbaikan_mendatang,
            'nilai_premi' => $request->nilai_premi,
            'nilai_klaim' => $request->nilai_klaim,
            'created_by' => $user->id,
        ]);

        DB::commit();

        // Load relasi
        $lostEvent->load(['createdBy:id,username', 'updatedBy:id,username']);

        // Prepare response data
        $data = [
            'lost_event_id' => $lostEvent->id,
            'header_id' => $lostEvent->header_id,
            'rcsa_id' => $lostEvent->rcsa_id,
            'tahun' => $lostEvent->tahun,
            'risk_owner_department' => $lostEvent->risk_owner_department,
            'jenis_risiko_id' => $jenisRisikoId,
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
        ];

        $cleanData = clean_recursive($data);

        return json(200, true, 'Berhasil', 'Lost event berhasil dibuat.', $cleanData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Error', 'Terjadi kesalahan saat membuat lost event.', [
            'error' => $e->getMessage()
        ]);
    }
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

        // Update hanya field yang dikirim dan tidak null/kosong
        foreach ($data as $key => $value) {
            if ($request->exists($key) && $value !== null && $value !== '') {
                $lostEvent->{$key} = $value;
            }
        }

        $lostEvent->save();

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
