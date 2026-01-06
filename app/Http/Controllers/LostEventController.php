<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LostEvent;
use App\Models\TrRiskHeader;
use App\Models\MstRcsa;
use App\Models\LostEventUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
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
            $query->where('is_finalize', true)->orderBy('month', 'desc');
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

                if ($item->rcsa && $thresholdBahaya > 0) {
                    $shouldInclude = $percentage <= $thresholdBahaya;
                } else {
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

                if ($item->rcsa && $thresholdBahaya > 0) {
                    $shouldInclude = $percentage <= $thresholdBahaya;
                } else {
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
        ->with([
            'createdBy:id,username,name',
            'updatedBy:id,username,name',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ])
        ->withTrashed()
        ->get()
        ->keyBy('header_id');

    // Ambil lost events yang independen (tanpa header_id)
    // TAMBAHAN: Filter berdasarkan type untuk independent lost events
    $independentLostEvents = LostEvent::whereNull('header_id')
        ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            $query->where('risk_owner_department_id', $user->department_id);
        })
        ->when($request->tahun, function ($query) use ($request) {
            $query->where('tahun', $request->tahun);
        })
        ->when($request->department_id, function ($query) use ($request) {
            $query->where('risk_owner_department_id', $request->department_id);
        })
        ->when($request->jenis_risiko_id, function ($query) use ($request) {
            $query->where('jenis_risiko_id', $request->jenis_risiko_id);
        })
        ->when($filterType, function ($query) use ($filterType) {
            $query->where('type', $filterType);
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
        ->with([
            'createdBy:id,username,name',
            'updatedBy:id,username,name',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ])
        ->withTrashed()
        ->get();

    // Gabungkan data dari headers dan independent lost events
    $allData = collect();

    // Data dari headers (dengan lost event atau tanpa)
    foreach ($filteredData as $item) {
        $lostEvent = $lostEventsWithHeader->get($item->id);

        // Format uploaded files
        $uploadedFiles = [];
        if ($lostEvent && $lostEvent->uploadedFiles) {
            $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain
                ];
            })->toArray();
        }

        $allData->push([
            'lost_event_id' => $lostEvent->id ?? null,
            'header_id' => $item->id,
            'rcsa_id' => $item->rcsa_id,
            'status' => $lostEvent->status ?? 'draft',
            'tahun' => $item->year,
            'risk_owner_department_id' => $item->department_id ?? null,
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
            'kategori_risiko_bumn' => $lostEvent ? $lostEvent->kategori_risiko_bumn : ($item->rcsa->kategori_risiko_bumn ?? null),
            'kategori_risiko_t2_t3_kbumn' => $lostEvent ? $lostEvent->kategori_risiko_t2_t3_kbumn : ($item->rcsa->kategori_risiko_t2_t3_kbumn ?? null),
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
            'uploaded_files' => $uploadedFiles,
        ]);
    }

    // Data dari lost events (tanpa header)
    foreach ($independentLostEvents as $lostEvent) {
        // Format uploaded files
        $uploadedFiles = [];
        if ($lostEvent->uploadedFiles) {
            $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain
                ];
            })->toArray();
        }

        $allData->push([
            'lost_event_id' => $lostEvent->id,
            'header_id' => null,
            'rcsa_id' => $lostEvent->rcsa_id,
            'status' => $lostEvent->status ?? 'draft',
            'tahun' => $lostEvent->tahun,
            'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
            'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
            'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
            'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian,
            'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
            'kategori_kejadian' => $lostEvent->kategori_kejadian,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
            'pihak_terkait' => $lostEvent->pihak_terkait,
            'status_asuransi' => $lostEvent->status_asuransi,
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
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
            'type' => $lostEvent->type ?? 'unknown',
            'realization_percentage' => null,
            'threshold_aman' => null,
            'threshold_hati_hati' => null,
            'threshold_bahaya' => null,
            'uploaded_files' => $uploadedFiles,
        ]);
    }

    // Sort: yang punya lost_event_id di atas (DESC), lalu yang null di bawah
    $sortedData = $allData->sort(function ($a, $b) {
        if ($a['lost_event_id'] !== null && $b['lost_event_id'] !== null) {
            return $b['lost_event_id'] - $a['lost_event_id'];
        }
        if ($a['lost_event_id'] !== null) {
            return -1;
        }
        if ($b['lost_event_id'] !== null) {
            return 1;
        }
        return 0;
    })->values();

    // Jika per_page kosong atau = "all", ambil semua data
    if (empty($perPage) || $perPage === 'all') {
        $cleanData = clean_recursive([
            'total' => $sortedData->count(),
            'data' => $sortedData->toArray(),
        ]);

        return json(200, true, 'Data Ditemukan', 'Data header dengan realisasi di bawah threshold bahaya berhasil diambil.', $cleanData);
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

    return json(200, true, 'Data Ditemukan', 'Data header dengan realisasi di bawah threshold bahaya berhasil diambil.', $cleanData);
}

//=====================================
// SHOW DETAIL LOST EVENT
//=====================================
public function show($headerId)
{
    // Wrap SEMUA operasi dalam try-catch dan transaction
    try {
        DB::beginTransaction();

        $user = auth()->user();

        if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
            DB::rollBack();
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
            DB::rollBack();
            return json(404, false, 'Tidak Ditemukan', 'Header tidak ditemukan.', null);
        }

        if ($header->monthlyData->count() !== 12) {
            DB::rollBack();
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
                $targetValue = $totalTarget;
                $realizationValue = $totalRealisasi;
                $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
            }
        }

        $lostEvent = LostEvent::where('header_id', $headerId)
            ->with([
                'createdBy:id,username',
                'updatedBy:id,username',
                'riskOwnerDepartmentRelation:id,name',
                'jenisRisikoRelation:id,nama_jenis_risiko',
                'uploadedFiles:id,lost_event_id,filepath,domain'
            ])
            ->first();

        if (!$lostEvent) {
            // Validasi data sebelum create
            $validationData = [
                'header_id' => $header->id,
                'tahun' => $header->year,
                'department_id' => $header->department_id,
                'jenis_risiko' => $header->jenis_risiko,
            ];

            $validationRules = [
                'header_id' => 'required|integer',
                'tahun' => 'required|integer|min:2000|max:2100',
                'department_id' => 'required|integer|exists:mst_department,id',
                'jenis_risiko' => 'required|integer',
            ];

            $checkValidation = check_validation($validationData, $validationRules);

            if ($checkValidation[0] == 1) {
                DB::rollBack();
                return $checkValidation[1];
            }

            // Ambil penyebab_kejadian dari header
            $penyebabKejadian = $header->penyebab_risiko ?? '';

            // Ambil nilai VARCHAR langsung dari RCSA
            $kategoriRisikoBumn = null;
            $kategoriRisikoT2T3Kbumn = null;

            if ($header->rcsa) {
                // Langsung ambil nilai string dari RCSA
                $kategoriRisikoBumn = $header->rcsa->kategori_risiko_bumn ?? null;
                $kategoriRisikoT2T3Kbumn = $header->rcsa->kategori_risiko_t2_t3_kbumn ?? null;
            }

            // Pastikan semua data valid dengan tipe yang benar
            $dataToInsert = [
                'header_id' => (int) $header->id,
                'rcsa_id' => $header->rcsa_id ? (int) $header->rcsa_id : null,
                'tahun' => (int) $header->year,
                'risk_owner_department_id' => $header->department_id ? (int) $header->department_id : null,
                'jenis_risiko_id' => $header->jenis_risiko ? (int) $header->jenis_risiko : null,
                'type' => $normalizedType ?? 'unknown',
                'nama_kejadian' => '',
                'identifikasi_kejadian' => $header->peristiwa_risiko ?? '',
                'kategori_kejadian' => null,
                'sumber_penyebab_kejadian' => null,
                'penyebab_kejadian' => $penyebabKejadian,
                'penanganan_saat_kejadian' => null,
                'deskripsi_kejadian' => null,
                'pihak_terkait' => null,
                'status_asuransi' => null,
                'kategori_risiko_bumn' => $kategoriRisikoBumn,
                'kategori_risiko_t2_t3_kbumn' => $kategoriRisikoT2T3Kbumn,
                'penjelasan_kerugian' => null,
                'nilai_kerugian' => null,
                'kejadian_berulang' => null,
                'frekuensi_kejadian' => null,
                'mitigasi_yang_direncanakan' => $header->mitigasi ?? '',
                'realisasi_mitigasi' => null,
                'perbaikan_mendatang' => null,
                'nilai_premi' => null,
                'nilai_klaim' => null,
                'status' => 'draft',
                'created_by' => (int) $user->id,
            ];

            $lostEvent = LostEvent::create($dataToInsert);

            if (!$lostEvent) {
                DB::rollBack();
                return json(500, false, 'Gagal Membuat Data', 'Gagal membuat lost event. Silakan coba lagi.', null);
            }

            $lostEvent->load([
                'createdBy:id,username',
                'updatedBy:id,username',
                'riskOwnerDepartmentRelation:id,name',
                'jenisRisikoRelation:id,nama_jenis_risiko',
                'uploadedFiles:id,lost_event_id,filepath,domain'
            ]);
        }

        // Format uploaded files
        $uploadedFiles = [];
        if ($lostEvent && $lostEvent->uploadedFiles) {
            $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain
                ];
            })->toArray();
        }

        $data = [
            'lost_event_id' => $lostEvent->id,
            'header_id' => $lostEvent->header_id,
            'rcsa_id' => $header->rcsa_id,
            'tahun' => $lostEvent->tahun,
            'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
            'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
            'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
            'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian,
            'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
            'kategori_kejadian' => $lostEvent->kategori_kejadian,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
            'pihak_terkait' => $lostEvent->pihak_terkait,
            'status_asuransi' => $lostEvent->status_asuransi,
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
            'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian,
            'nilai_kerugian' => $lostEvent->nilai_kerugian,
            'kejadian_berulang' => $lostEvent->kejadian_berulang,
            'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian,
            'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan,
            'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi,
            'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang,
            'nilai_premi' => $lostEvent->nilai_premi,
            'nilai_klaim' => $lostEvent->nilai_klaim,
            'status' => $lostEvent->status ?? 'draft',
            'created_at' => $lostEvent && $lostEvent->created_at ? $lostEvent->created_at->format('Y-m-d') : null,
            'updated_at' => $lostEvent && $lostEvent->updated_at ? $lostEvent->updated_at->format('Y-m-d') : null,
            'created_by' => $lostEvent->created_by,
            'created_by_name' => get_decrypted_name($lostEvent->createdBy),
            'updated_by' => $lostEvent->updated_by,
            'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
            'type' => $lostEvent->type ?? ($normalizedType ?? 'unknown'),
            'realization_percentage' => $percentage !== null
                ? rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%'
                : null,
            'threshold_aman' => $thresholdAman,
            'threshold_hati_hati' => $thresholdHatiHati,
            'threshold_bahaya' => $thresholdBahaya,
            'uploaded_files' => $uploadedFiles,
        ];

        $cleanData = clean_recursive($data);

        // Commit hanya jika SEMUA proses berhasil
        DB::commit();

        return json(200, true, 'Data Ditemukan', 'Detail lost event berhasil diambil.', $cleanData);

    } catch (QueryException $e) {
        DB::rollBack();

        // Handle specific database errors
        if ($e->getCode() == '23000') {
            return json(400, false, 'Kesalahan Data', 'Terjadi duplikasi data atau constraint violation. Periksa kembali data yang diinputkan.', [
                'error_detail' => config('app.debug') ? $e->getMessage() : 'Database constraint error',
            ]);
        }

        return json(500, false, 'Kesalahan Database', 'Terjadi kesalahan pada database. Silakan hubungi administrator.', [
            'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred',
        ]);

    } catch (Exception $e) {
        DB::rollBack();

        return json(500, false, 'Kesalahan Sistem', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.', [
            'error' => config('app.debug') ? $e->getMessage() : 'System error occurred',
            'line' => config('app.debug') ? $e->getLine() : null,
            'file' => config('app.debug') ? $e->getFile() : null,
        ]);
    }
}

//=====================================
// GET DETAIL LOST EVENT BY ID
//=====================================
public function detail($id)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data ini.', null);
    }

    $lostEvent = LostEvent::with([
        'createdBy:id,username',
        'updatedBy:id,username',
        'riskOwnerDepartmentRelation:id,name',
        'jenisRisikoRelation:id,nama_jenis_risiko',
        'uploadedFiles:id,lost_event_id,filepath,domain',
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

    // Cegah role 2 & 3 melihat data department lain
    if (in_array($user->role_id, [2, 3])) {
        if ($lostEvent->risk_owner_department_id != $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda tidak memiliki akses ke data ini.', null);
        }
    }

    $header = $lostEvent->header;

    // Format uploaded files
    $uploadedFiles = [];
    if ($lostEvent->uploadedFiles) {
        $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
            return [
                'id' => $file->id,
                'filepath' => $file->filepath,
                'domain' => $file->domain
            ];
        })->toArray();
    }

    // Jika lost event (tanpa header)
    if (!$header) {
        $data = [
            'lost_event_id' => $lostEvent->id,
            'header_id' => null,
            'rcsa_id' => $lostEvent->rcsa_id,
            'status' => $lostEvent->status ?? 'draft',
            'tahun' => $lostEvent->tahun,
            'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
            'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
            'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
            'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian,
            'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
            'kategori_kejadian' => $lostEvent->kategori_kejadian,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
            'pihak_terkait' => $lostEvent->pihak_terkait,
            'status_asuransi' => $lostEvent->status_asuransi,
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
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
            'type' => $lostEvent->type ?? 'unknown',
            'realization_percentage' => null,
            'threshold_aman' => null,
            'threshold_hati_hati' => null,
            'threshold_bahaya' => null,
            'uploaded_files' => $uploadedFiles,
        ];

        $cleanData = clean_recursive($data);
        return json(200, true, 'Data Ditemukan', 'Detail lost event berhasil diambil.', $cleanData);
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
        'status' => $lostEvent->status ?? 'draft',
        'tahun' => $lostEvent->tahun,
        'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
        'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
        'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
        'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
        'nama_kejadian' => $lostEvent->nama_kejadian,
        'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
        'kategori_kejadian' => $lostEvent->kategori_kejadian,
        'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
        'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
        'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
        'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
        'pihak_terkait' => $lostEvent->pihak_terkait,
        'status_asuransi' => $lostEvent->status_asuransi,
        'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
        'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
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
        'type' => $lostEvent->type ?? ($normalizedType ?? 'unknown'),
        'realization_percentage' => $percentage !== null
            ? rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%'
            : null,
        'threshold_aman' => $thresholdAman,
        'threshold_hati_hati' => $thresholdHatiHati,
        'threshold_bahaya' => $thresholdBahaya,
        'uploaded_files' => $uploadedFiles,
    ];

    $cleanData = clean_recursive($data);

    return json(200, true, 'Data Ditemukan', 'Detail lost event berhasil diambil.', $cleanData);
}

// Lost Event Create dengan Upload
public function store(Request $request)
{
    $user = auth()->user();

    // Validasi akses role
    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk membuat data ini.', null);
    }

    // Ambil type dari query parameter
    $typeParam = strtolower($request->query('type', ''));

    // Validasi type hanya boleh kuantitatif atau kualitatif
    if (!in_array($typeParam, ['kuantitatif', 'kualitatif'])) {
        return json(403, false, 'Validasi Gagal', 'Parameter type harus diisi dengan nilai kuantitatif atau kualitatif.', [
            'type' => ['Parameter type harus berisi kuantitatif atau kualitatif']
        ]);
    }

    // TAMBAHAN: Validasi risk_owner_department_id untuk role 2 dan 3
    if (in_array($user->role_id, [2, 3])) {
        // Jika role 2 atau 3, risk_owner_department_id harus sesuai dengan department user
        if ($request->has('risk_owner_department_id') && $request->risk_owner_department_id != $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda hanya dapat membuat lost event untuk department Anda sendiri.', null);
        }
        // Force set risk_owner_department_id ke department user
        $request->merge(['risk_owner_department_id' => $user->department_id]);
    }

    // Validasi input - header_id opsional
    $validator = Validator::make($request->all(), [
        'header_id' => 'nullable|exists:tr_risk_headers,id',
        'rcsa_id' => 'nullable|exists:rcsa,id',
        'tahun' => 'required|string|max:4',
        'risk_owner_department_id' => 'required|exists:mst_department,id',
        'jenis_risiko_id' => 'required|exists:mst_jenis_risiko,id',
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
        return json(403, false, 'Validasi Gagal', 'Data yang dikirim tidak valid.', $validator->errors());
    }

    // Jika ada header_id, ambil data dari header
    $header = null;
    $headerId = $request->header_id;
    $rcsaId = $request->rcsa_id;
    $tahun = $request->tahun;
    $riskOwnerDepartmentId = $request->risk_owner_department_id;
    $jenisRisikoId = $request->jenis_risiko_id;
    $identifikasiKejadian = $request->identifikasi_kejadian ?? '';
    $penyebabKejadian = $request->penyebab_kejadian ?? '';
    $mitigasiYangDirencanakan = $request->mitigasi_yang_direncanakan ?? '';
    $kategoriRisikoBumn = $request->kategori_risiko_bumn;
    $kategoriRisikoT2T3Kbumn = $request->kategori_risiko_t2_t3_kbumn;

    if ($headerId) {
        $header = TrRiskHeader::with([
            'department:id,name',
            'jenisRisiko:id,nama_jenis_risiko',
            'rcsa:id,kategori_risiko_bumn,kategori_risiko_t2_t3_kbumn'
        ])
        // Cegah role 2 & 3 mengakses header di luar departmentnya
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
            return json(403, false, 'Conflict', 'Lost event untuk header ini sudah ada.', null);
        }

        // Override dengan data dari header
        $rcsaId = $header->rcsa_id;
        $tahun = $header->year;
        $riskOwnerDepartmentId = $header->department_id;
        $jenisRisikoId = $header->jenis_risiko;

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

        // Ambil kategori risiko dari RCSA jika belum diisi
        if (empty($kategoriRisikoBumn) && $header->rcsa) {
            $kategoriRisikoBumn = $header->rcsa->kategori_risiko_bumn ?? '';
        }

        if (empty($kategoriRisikoT2T3Kbumn) && $header->rcsa) {
            $kategoriRisikoT2T3Kbumn = $header->rcsa->kategori_risiko_t2_t3_kbumn ?? '';
        }
    }

    try {
        DB::beginTransaction();

        // Buat lost event baru
        $lostEvent = LostEvent::create([
            'header_id' => $headerId,
            'rcsa_id' => $rcsaId,
            'tahun' => $tahun,
            'risk_owner_department_id' => $riskOwnerDepartmentId,
            'jenis_risiko_id' => $jenisRisikoId,
            'type' => $typeParam,
            'nama_kejadian' => $request->nama_kejadian,
            'identifikasi_kejadian' => $identifikasiKejadian,
            'kategori_kejadian' => $request->kategori_kejadian,
            'sumber_penyebab_kejadian' => $request->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $penyebabKejadian,
            'penanganan_saat_kejadian' => $request->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $request->deskripsi_kejadian,
            'pihak_terkait' => $request->pihak_terkait,
            'status_asuransi' => $request->status_asuransi,
            'kategori_risiko_bumn' => $kategoriRisikoBumn,
            'kategori_risiko_t2_t3_kbumn' => $kategoriRisikoT2T3Kbumn,
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

        // Update lost_event_id di lost_event_uploads yang belum terkait
        LostEventUpload::whereNull('lost_event_id')
            ->where('user_id', $user->id)
            ->update(['lost_event_id' => $lostEvent->id]);

        // Proses file uploads jika ada
        if ($request->has('uploaded_files')) {
            process_lost_event_file_uploads($request->uploaded_files, $lostEvent);
        }

        DB::commit();

        // Load relasi termasuk uploads
        $lostEvent->load([
            'createdBy:id,username',
            'updatedBy:id,username',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ]);

        // Format uploaded files
        $uploadedFiles = [];
        if ($lostEvent->uploadedFiles) {
            $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain
                ];
            })->toArray();
        }

        // Prepare response data
        $data = [
            'lost_event_id' => $lostEvent->id,
            'header_id' => $lostEvent->header_id,
            'rcsa_id' => $lostEvent->rcsa_id,
            'tahun' => $lostEvent->tahun,
            'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
            'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
            'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
            'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian,
            'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
            'kategori_kejadian' => $lostEvent->kategori_kejadian,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
            'pihak_terkait' => $lostEvent->pihak_terkait,
            'status_asuransi' => $lostEvent->status_asuransi,
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
            'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian,
            'nilai_kerugian' => $lostEvent->nilai_kerugian,
            'kejadian_berulang' => $lostEvent->kejadian_berulang,
            'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian,
            'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan,
            'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi,
            'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang,
            'nilai_premi' => $lostEvent->nilai_premi,
            'nilai_klaim' => $lostEvent->nilai_klaim,
            'type' => $lostEvent->type,
            'uploaded_files' => $uploadedFiles,
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
 * Update lost event dengan upload file
 * Role 1: Bisa update semua data
 * Role 2: Bisa update hanya data department mereka
 * HANYA BISA UPDATE JIKA STATUS DRAFT
 */
public function update(Request $request, $id)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk mengubah data.', null);
    }

    $lostEvent = LostEvent::find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    // Hanya bisa diupdate jika status draft atau reject
    if (!in_array($lostEvent->status, ['draft', 'rejected'])) {
        return json(400, false, 'Gagal', 'Lost event hanya dapat diubah jika berstatus draft atau rejected.', null);
    }

    // Role 2 dan 3 hanya boleh update data departemen sendiri
    if (in_array($user->role_id, [2, 3])) {
        if ($lostEvent->risk_owner_department_id !== $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda hanya dapat mengubah data untuk department Anda sendiri.', null);
        }

        // TAMBAHAN: Validasi jika mencoba mengubah risk_owner_department_id
        if ($request->has('risk_owner_department_id') && $request->risk_owner_department_id != $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda tidak dapat mengubah department ke department lain.', null);
        }
    }

    $validator = Validator::make($request->all(), [
        'tahun' => 'nullable|string|max:4',
        'risk_owner_department_id' => 'nullable|exists:mst_department,id',
        'jenis_risiko_id' => 'nullable|exists:mst_jenis_risiko,id',
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
        return json(403, false, 'Validasi Gagal', 'Data yang dikirim tidak valid.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        // Field yang tidak boleh diubah jika terkait dengan header
        $protectedFields = [
            'tahun',
            'risk_owner_department_id',
            'jenis_risiko_id',
            'identifikasi_kejadian',
            'mitigasi_yang_direncanakan',
            'type'
        ];

        // Jangan update field yang tidak boleh diubah jika terkait dengan header
        if ($lostEvent->header_id) {
            foreach ($protectedFields as $field) {
                if ($request->has($field)) {
                    $request->request->remove($field);
                }
            }
        }

        // Update hanya field yang dikirim dalam request DAN tidak kosong
        $fillableFields = [
            'tahun',
            'risk_owner_department_id',
            'jenis_risiko_id',
            'nama_kejadian',
            'identifikasi_kejadian',
            'kategori_kejadian',
            'sumber_penyebab_kejadian',
            'penyebab_kejadian',
            'penanganan_saat_kejadian',
            'deskripsi_kejadian',
            'pihak_terkait',
            'status_asuransi',
            'kategori_risiko_bumn',
            'kategori_risiko_t2_t3_kbumn',
            'penjelasan_kerugian',
            'nilai_kerugian',
            'kejadian_berulang',
            'frekuensi_kejadian',
            'mitigasi_yang_direncanakan',
            'realisasi_mitigasi',
            'perbaikan_mendatang',
            'nilai_premi',
            'nilai_klaim',
        ];

        foreach ($fillableFields as $field) {
            // Hanya update jika field ada di request DAN tidak kosong (kecuali untuk numeric field)
            if ($request->has($field)) {
                $value = $request->input($field);

                // Untuk field numeric, tetap update meskipun kosong
                $numericFields = ['nilai_kerugian', 'nilai_premi', 'nilai_klaim'];

                if (in_array($field, $numericFields)) {
                    // Update numeric field bahkan jika kosong/null
                    $lostEvent->{$field} = $value;
                } else {
                    // Untuk field non-numeric, hanya update jika tidak kosong
                    if (!empty($value) || $value === '0' || $value === 0) {
                        $lostEvent->{$field} = $value;
                    }
                }
            }
        }

        // Ubah status menjadi draft jika sebelumnya rejected
        if ($lostEvent->status === 'rejected') {
            $lostEvent->status = 'draft';
            $lostEvent->note = null;
        }

        $lostEvent->updated_by = $user->id;
        $lostEvent->save();

        // Update lost_event_id di lost_event_uploads yang belum terkait
         LostEventUpload::whereNull('lost_event_id')
        ->where('user_id', $user->id)
        ->update(['lost_event_id' => $lostEvent->id]);

        // Proses file uploads jika ada
        if ($request->has('uploaded_files')) {
            process_lost_event_file_uploads($request->uploaded_files, $lostEvent);
        }

        DB::commit();

        // Load relasi untuk response
        $lostEvent->load([
            'createdBy:id,username',
            'updatedBy:id,username',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ]);

        // Format uploaded files
        $uploadedFiles = [];
        if ($lostEvent->uploadedFiles) {
            $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filepath' => $file->filepath,
                    'domain' => $file->domain
                ];
            })->toArray();
        }

        // Prepare response data
        $data = [
            'lost_event_id' => $lostEvent->id,
            'header_id' => $lostEvent->header_id,
            'rcsa_id' => $lostEvent->rcsa_id,
            'tahun' => $lostEvent->tahun,
            'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
            'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
            'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
            'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
            'nama_kejadian' => $lostEvent->nama_kejadian,
            'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
            'kategori_kejadian' => $lostEvent->kategori_kejadian,
            'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
            'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
            'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
            'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
            'pihak_terkait' => $lostEvent->pihak_terkait,
            'status_asuransi' => $lostEvent->status_asuransi,
            'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
            'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
            'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian,
            'nilai_kerugian' => $lostEvent->nilai_kerugian,
            'kejadian_berulang' => $lostEvent->kejadian_berulang,
            'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian,
            'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan,
            'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi,
            'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang,
            'nilai_premi' => $lostEvent->nilai_premi,
            'nilai_klaim' => $lostEvent->nilai_klaim,
            'status' => $lostEvent->status ?? 'draft',
            'note' => $lostEvent->note ?? null,
            'type' => $lostEvent->type,
            'uploaded_files' => $uploadedFiles,
            'created_at' => $lostEvent->created_at ? $lostEvent->created_at->format('Y-m-d') : null,
            'updated_at' => $lostEvent->updated_at ? $lostEvent->updated_at->format('Y-m-d') : null,
            'created_by' => $lostEvent->created_by,
            'created_by_name' => get_decrypted_name($lostEvent->createdBy),
            'updated_by' => $lostEvent->updated_by,
            'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
        ];

        $cleanData = clean_recursive($data);

        return json(200, true, 'Berhasil', 'Lost event berhasil diupdate.', $cleanData);
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
    /**
 * Delete lost event (soft delete)
 * Hanya Role 1 yang bisa delete dan hanya jika status draft
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

    // VALIDASI: Hanya bisa delete jika status draft
    if ($lostEvent->status !== 'draft') {
        return json(400, false, 'Gagal', 'Lost event hanya dapat dihapus jika berstatus draft.', null);
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

    /**
     * Delete uploaded file from lost event
     */
    public function deleteUploadedFile($fileId)
    {
        $user = auth()->user();

        if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
            return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk menghapus file.', null);
        }

        $upload = LostEventUpload::with('lostEvent')->find($fileId);

        if (!$upload) {
            return json(404, false, 'File Tidak Ditemukan', 'File yang ingin dihapus tidak ditemukan.', null);
        }

        $lostEvent = $upload->lostEvent;

        if (!$lostEvent) {
            return json(404, false, 'Data Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
        }

        if (!in_array($lostEvent->status, ['draft', 'rejected'])) {
            return json(400, false, 'Gagal', 'File hanya dapat dihapus jika lost event berstatus draft atau rejected.', null);
        }

        // Role 2 dan 3 hanya boleh hapus file dari department sendiri
        if (in_array($user->role_id, [2, 3])) {
            if ($lostEvent->risk_owner_department_id !== $user->department_id) {
                return json(403, false, 'Forbidden', 'Anda hanya dapat menghapus file untuk department Anda sendiri.', null);
            }
        }

        DB::beginTransaction();
        try {
            // Hapus file dari storage menggunakan helper
            delete_file_from_storage($upload->filepath);

            $lostEventId = $upload->lost_event_id;

            // Hapus record dari database
            $upload->delete();

            DB::commit();

            return json(200, true, 'Berhasil', 'File berhasil dihapus.', [
                'deleted_file_id' => $fileId,
                'lost_event_id' => $lostEventId
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat menghapus file.', [
                'error' => $e->getMessage()
            ]);
        }
    }

   public function uploadFile(Request $request)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses upload file.', null);
    }

    $validator = Validator::make(
        $request->all(),
        [
            'file'   => 'required|array',
            'file.*' => 'file|max:2048',
        ],
        [
            'file.*.max' => 'Ukuran file maksimal 2MB.',
        ]
    );

    if ($validator->fails()) {
        $errors = $validator->errors();
        $message = $errors->first('file.*') === 'Ukuran file maksimal 2MB.'
            ? 'Ukuran file maksimal 2MB.'
            : 'File tidak valid.';

        return json(400, false, 'Validasi Gagal', $message, $errors);
    }

    $uploadedList = [];

    foreach ($request->file('file') as $file) {
        $fileContent = file_get_contents($file->getRealPath());
        $base64      = base64_encode($fileContent);
        $mime        = $file->getMimeType();
        $base64Format = "data:{$mime};base64,{$base64}";

        $upload = LostEventUpload::create([
            'lost_event_id' => null,  // ← UBAH dari 0 ke null
            'user_id'       => $user->id,
            'filepath'      => $base64Format,
            'domain'        => $file->getClientOriginalName(),
            'is_confirmed'  => 0,
        ]);

        $uploadedList[] = [
            'upload_id' => $upload->id,
            'filename'  => $file->getClientOriginalName(),
            'filepath'  => $upload->filepath,
        ];
    }

    return json(200, true, 'Berhasil', 'File berhasil diupload (pending).', $uploadedList);
}

//=====================================
// GET PENDING LOST EVENTS
//=====================================
public function getPending(Request $request)
{
    $user = auth()->user();

    // Hanya role 1, 2, 4, 5 yang bisa melihat pending submissions
    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk melihat data pending.', null);
    }

    $perPage = $request->input('per_page');
    $search = $request->query('search');
    $type = $request->query('type'); // Ambil parameter 'type'

    $query = LostEvent::with([
        'createdBy:id,username,name',
        'updatedBy:id,username,name',
        'riskOwnerDepartmentRelation:id,name',
        'jenisRisikoRelation:id,nama_jenis_risiko',
        'uploadedFiles:id,lost_event_id,filepath,domain',
        'header' => function($q) {
            $q->with([
                'jenisRisiko:id,nama_jenis_risiko',
                'rcsa:id,kategori_risiko_bumn,kategori_risiko_t2_t3_kbumn'
            ]);
        }
    ])
    // Filter berdasarkan type dari kolom 'type' di tabel lost_events
    ->when($type, function ($query) use ($type) {
        $typeNormalized = strtolower(trim($type));
        $query->whereRaw('LOWER(type) = ?', [$typeNormalized]);
    })
    // Role 2 dan 3 hanya bisa melihat department mereka
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        $query->where('risk_owner_department_id', $user->department_id);
    })
    ->when($request->tahun, function ($query) use ($request) {
        $query->where('tahun', $request->tahun);
    })
    ->when($request->department_id, function ($query) use ($request) {
        $query->where('risk_owner_department_id', $request->department_id);
    })
    ->when($request->jenis_risiko_id, function ($query) use ($request) {
        $query->where('jenis_risiko_id', $request->jenis_risiko_id);
    })
    // Filter berdasarkan status jika dikirim
    ->when($request->status, function ($query) use ($request) {
        $query->where('status', $request->status);
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
    ->orderBy('updated_at', 'desc');

    // Pagination atau all
    if (empty($perPage) || $perPage === 'all') {
        $lostEvents = $query->get();

        $data = $lostEvents->map(function ($lostEvent) {
            return $this->formatLostEventResponse($lostEvent);
        });

        $cleanData = clean_recursive([
            'total' => $data->count(),
            'data' => $data->toArray(),
        ]);

        return json(200, true, 'Data Ditemukan', 'Data lost event berhasil diambil.', $cleanData);
    }

    $page = $request->input('page');
    $lostEvents = $query->paginate($perPage);

    $data = $lostEvents->getCollection()->map(function ($lostEvent) {
        return $this->formatLostEventResponse($lostEvent);
    });

    $cleanData = clean_recursive([
        'current_page' => $lostEvents->currentPage(),
        'per_page' => $lostEvents->perPage(),
        'total' => $lostEvents->total(),
        'last_page' => $lostEvents->lastPage(),
        'from' => $lostEvents->firstItem(),
        'to' => $lostEvents->lastItem(),
        'data' => $data->toArray(),
    ]);

    return json(200, true, 'Data Ditemukan', 'Data lost event berhasil diambil.', $cleanData);
}

//=====================================
// SUBMIT LOST EVENT
//=====================================
public function submit($id)
{
    $user = auth()->user();

    if (!in_array($user->role_id, [1, 2, 3, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk submit data.', null);
    }

    $lostEvent = LostEvent::find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    // Role 2 & 3 hanya bisa submit data department mereka sendiri
    if (in_array($user->role_id, [2, 3])) {
        if ($lostEvent->risk_owner_department_id !== $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda hanya dapat submit data untuk department Anda sendiri.', null);
        }
    }

    // Hanya bisa submit jika status draft
    if ($lostEvent->status !== 'draft') {
        return json(400, false, 'Gagal', 'Lost event hanya dapat di-submit jika berstatus draft.', null);
    }

    // Validasi field wajib sebelum submit
    $requiredFields = [
        'nama_kejadian' => 'Nama kejadian',
        'identifikasi_kejadian' => 'Identifikasi kejadian',
        'kategori_kejadian' => 'Kategori kejadian',
        'sumber_penyebab_kejadian' => 'Sumber penyebab kejadian',
        'penyebab_kejadian' => 'Penyebab kejadian',
    ];

    $missingFields = [];
    foreach ($requiredFields as $field => $label) {
        if (empty($lostEvent->$field)) {
            $missingFields[] = $label;
        }
    }

    if (!empty($missingFields)) {
        return json(403, false, 'Validasi Gagal', 'Field berikut harus diisi sebelum submit: ' . implode(', ', $missingFields), [
            'missing_fields' => $missingFields
        ]);
    }

    try {
        DB::beginTransaction();

        $lostEvent->status = 'submit';
        $lostEvent->updated_by = $user->id;
        $lostEvent->save();

        DB::commit();

        $lostEvent->load([
            'createdBy:id,username,name',
            'updatedBy:id,username,name',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ]);

        $data = $this->formatLostEventResponse($lostEvent);
        $cleanData = clean_recursive($data);

        return json(200, true, 'Berhasil', 'Lost event berhasil di-submit untuk approval.', $cleanData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Error', 'Terjadi kesalahan saat submit lost event.', [
            'error' => $e->getMessage()
        ]);
    }
}

//=====================================
// APPROVE LOST EVENT
//=====================================
public function approve(Request $request, $id)
{
    $user = auth()->user();

    // Hanya role 1, 2, 4, 5 yang bisa approve
    if (!in_array($user->role_id, [1, 2, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk approve data.', null);
    }

    $lostEvent = LostEvent::find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    // Role 2 hanya bisa approve data department mereka sendiri
    if ($user->role_id === 2) {
        if ($lostEvent->risk_owner_department_id !== $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda hanya dapat approve data untuk department Anda sendiri.', null);
        }
    }

    // Hanya bisa approve jika status submit
    if ($lostEvent->status !== 'submit') {
        return json(400, false, 'Gagal', 'Lost event hanya dapat di-approve jika berstatus submitted.', null);
    }

    // Validasi note (opsional)
    $validator = Validator::make($request->all(), [
        'note' => 'nullable|string|max:2000'
    ]);

    if ($validator->fails()) {
        return json(403, false, 'Validasi Gagal', 'Data yang dikirim tidak valid.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        $lostEvent->status = 'approved';
        $lostEvent->note = $request->note;
        $lostEvent->updated_by = $user->id;
        $lostEvent->save();

        DB::commit();

        $lostEvent->load([
            'createdBy:id,username,name',
            'updatedBy:id,username,name',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ]);

        $data = $this->formatLostEventResponse($lostEvent);
        $cleanData = clean_recursive($data);

        return json(200, true, 'Berhasil', 'Lost event berhasil di-approve.', $cleanData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Error', 'Terjadi kesalahan saat approve lost event.', [
            'error' => $e->getMessage()
        ]);
    }
}

//=====================================
// REJECT LOST EVENT
//=====================================
public function reject(Request $request, $id)
{
    $user = auth()->user();

    // Hanya role 1, 2, 4, 5 yang bisa reject
    if (!in_array($user->role_id, [1, 2, 4, 5])) {
        return json(403, false, 'Forbidden', 'Anda tidak memiliki akses untuk reject data.', null);
    }

    $lostEvent = LostEvent::find($id);

    if (!$lostEvent) {
        return json(404, false, 'Tidak Ditemukan', 'Lost event tidak ditemukan.', null);
    }

    // Role 2 hanya bisa reject data department mereka sendiri
    if ($user->role_id === 2) {
        if ($lostEvent->risk_owner_department_id !== $user->department_id) {
            return json(403, false, 'Forbidden', 'Anda hanya dapat reject data untuk department Anda sendiri.', null);
        }
    }

    // Hanya bisa reject jika status submit
    if ($lostEvent->status !== 'submit') {
        return json(400, false, 'Gagal', 'Lost event hanya dapat di-reject jika berstatus submitted.', null);
    }

    // Validasi note (opsional)
    $validator = Validator::make($request->all(), [
        'note' => 'nullable|string|max:2000'
    ]);

    if ($validator->fails()) {
        return json(403, false, 'Validasi Gagal', 'Data yang dikirim tidak valid.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        $lostEvent->status = 'rejected';
        $lostEvent->note = $request->note;
        $lostEvent->updated_by = $user->id;
        $lostEvent->save();

        DB::commit();

        $lostEvent->load([
            'createdBy:id,username,name',
            'updatedBy:id,username,name',
            'riskOwnerDepartmentRelation:id,name',
            'jenisRisikoRelation:id,nama_jenis_risiko',
            'uploadedFiles:id,lost_event_id,filepath,domain'
        ]);

        $data = $this->formatLostEventResponse($lostEvent);
        $cleanData = clean_recursive($data);

        return json(200, true, 'Berhasil', 'Lost event berhasil di-reject.', $cleanData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Error', 'Terjadi kesalahan saat reject lost event.', [
            'error' => $e->getMessage()
        ]);
    }
}

//=====================================
// HELPER: FORMAT LOST EVENT RESPONSE
//=====================================
private function formatLostEventResponse($lostEvent)
{
    // Format uploaded files
    $uploadedFiles = [];
    if ($lostEvent->uploadedFiles) {
        $uploadedFiles = $lostEvent->uploadedFiles->map(function ($file) {
            return [
                'id' => $file->id,
                'filepath' => $file->filepath,
                'domain' => $file->domain
            ];
        })->toArray();
    }

    // Calculate realization percentage if header exists
    $realizationPercentage = null;
    $thresholdAman = null;
    $thresholdHatiHati = null;
    $thresholdBahaya = null;

    if ($lostEvent->header) {
        $header = $lostEvent->header;

        if ($header->rcsa) {
            $thresholdAman = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_aman ?? '0');
            $thresholdHatiHati = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_hati_hati ?? '0');
            $thresholdBahaya = (float) str_replace(['%', ','], ['', '.'], $header->rcsa->kategori_threshold_kri_bahaya ?? '0');
        }

        if ($header->monthlyData && $header->monthlyData->count() === 12) {
            $targetType = optional($header->optionTargetSatuTahun)->type;
            $normalizedType = strtolower($targetType ?? '');

            if (in_array($normalizedType, ['kuantitatif', 'quantitative'])) {
                $totalTarget = 0;
                $totalRealisasi = 0;

                foreach ($header->monthlyData as $monthly) {
                    $targetNum = (float) preg_replace('/[^0-9]/', '', $monthly->target_quantitative ?? '0');
                    $realNum = (float) preg_replace('/[^0-9]/', '', $monthly->realization_quantitative ?? '0');
                    $totalTarget += $targetNum;
                    $totalRealisasi += $realNum;
                }

                if ($totalTarget > 0) {
                    $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
                    $realizationPercentage = rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%';
                }

            } elseif (in_array($normalizedType, ['kualitatif', 'qualitative'])) {
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
                    $percentage = round(($totalRealisasi / $totalTarget) * 100, 2);
                    $realizationPercentage = rtrim(rtrim(number_format($percentage, 2), '0'), '.') . '%';
                }
            }
        }
    }

    return [
        'lost_event_id' => $lostEvent->id,
        'header_id' => $lostEvent->header_id,
        'rcsa_id' => $lostEvent->rcsa_id,
        'tahun' => $lostEvent->tahun,
        'risk_owner_department_id' => $lostEvent->risk_owner_department_id,
        'risk_owner_department' => optional($lostEvent->riskOwnerDepartmentRelation)->name ?? '',
        'jenis_risiko_id' => $lostEvent->jenis_risiko_id,
        'jenis_risiko' => optional($lostEvent->jenisRisikoRelation)->nama_jenis_risiko ?? '',
        'nama_kejadian' => $lostEvent->nama_kejadian,
        'identifikasi_kejadian' => $lostEvent->identifikasi_kejadian,
        'kategori_kejadian' => $lostEvent->kategori_kejadian,
        'sumber_penyebab_kejadian' => $lostEvent->sumber_penyebab_kejadian,
        'penyebab_kejadian' => $lostEvent->penyebab_kejadian,
        'penanganan_saat_kejadian' => $lostEvent->penanganan_saat_kejadian,
        'deskripsi_kejadian' => $lostEvent->deskripsi_kejadian,
        'pihak_terkait' => $lostEvent->pihak_terkait,
        'status_asuransi' => $lostEvent->status_asuransi,
        'kategori_risiko_bumn' => $lostEvent->kategori_risiko_bumn ?? '',
        'kategori_risiko_t2_t3_kbumn' => $lostEvent->kategori_risiko_t2_t3_kbumn ?? '',
        'penjelasan_kerugian' => $lostEvent->penjelasan_kerugian,
        'nilai_kerugian' => $lostEvent->nilai_kerugian,
        'kejadian_berulang' => $lostEvent->kejadian_berulang,
        'frekuensi_kejadian' => $lostEvent->frekuensi_kejadian,
        'mitigasi_yang_direncanakan' => $lostEvent->mitigasi_yang_direncanakan,
        'realisasi_mitigasi' => $lostEvent->realisasi_mitigasi,
        'perbaikan_mendatang' => $lostEvent->perbaikan_mendatang,
        'nilai_premi' => $lostEvent->nilai_premi,
        'nilai_klaim' => $lostEvent->nilai_klaim,
        'status' => $lostEvent->status ?? 'draft',
        'note' => $lostEvent->note ?? null,
        'type' => $lostEvent->type ?? 'unknown',
        'realization_percentage' => $realizationPercentage,
        'threshold_aman' => $thresholdAman,
        'threshold_hati_hati' => $thresholdHatiHati,
        'threshold_bahaya' => $thresholdBahaya,
        'uploaded_files' => $uploadedFiles,
        'created_at' => $lostEvent->created_at ? $lostEvent->created_at->format('Y-m-d') : null,
        'updated_at' => $lostEvent->updated_at ? $lostEvent->updated_at->format('Y-m-d') : null,
        'created_by' => $lostEvent->created_by,
        'created_by_name' => get_decrypted_name($lostEvent->createdBy),
        'updated_by' => $lostEvent->updated_by,
        'updated_by_name' => get_decrypted_name($lostEvent->updatedBy),
    ];
}

}
