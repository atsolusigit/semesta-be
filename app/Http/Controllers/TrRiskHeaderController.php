<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TrRiskHeader;
use App\Models\TrRiskMonthly;
use App\Models\MstHeatmap;
use App\Models\MstOption;
use App\Models\MstRiskCode;
use App\Models\MstHeatmapDampak;
use App\Models\MstHeatmapKemungkinan;
use App\Models\User;
use App\Models\MstDepartment;
use App\Models\MstJabatan;
use App\Models\MstApproval;

class TrRiskHeaderController extends Controller
{

public function index(Request $request)
{
    $user = auth()->user();

    $perPage = $request->input('per_page', 10);

    $query = TrRiskHeader::with([
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position,type',
        'uploads',
        'monthlyData' => function ($query) {
            $query->orderBy('month', 'asc')
                  ->with(['uploads'])
                  ->join('mst_month_recommendation as mr', 'tr_risk_monthly.month', '=', 'mr.id')
                  ->select('tr_risk_monthly.*', 'mr.name as month_recommendation_name');
        },
        'headerEntry.monthlyEntryData.uploads',
        'headerEntry.irDampak:id,label',
        'headerEntry.irKemungkinan:id,label',
        'headerEntry.rrDampak:id,label',
        'headerEntry.rrKemungkinan:id,label',
        'headerEntry.department:id,name',
        'headerEntry.optionTargetSatuTahun:id,name,position',
        'createdBy:id,username,id',
        'updatedBy:id,username,id',
    ])
    // Filter hanya data yang sudah di-approve
    // ->where('status', 'approved')
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
        $query->where('department_id', $user->department_id);
    })
    ->when($request->peristiwa, function ($query) use ($request) {
        $query->where('peristiwa_risiko', 'like', '%' . $request->peristiwa . '%');
    })
    ->when($request->unit_kerja, function ($query) use ($request) {
        $query->whereHas('department', function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->unit_kerja . '%');
        });
    })
    ->when($request->tahun, function ($query) use ($request) {
        $query->where('year', $request->tahun);
    })
    ->orderBy('id', 'desc');

    // Pagination, ambil data per halaman
    $data = $query->paginate($perPage);

    // Nama bulan dalam bahasa Indonesia
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    // Mapping data pada halaman saat ini
    $orderedData = collect($data->items())->map(function ($item) use ($monthNames) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        $monthlyDataMap = [];
        $monthly = [];
        $rekomendasi = []; // Array untuk rekomendasi di header

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);

            if ($dataBulanan) {
                // Safe numeric conversion untuk perhitungan
                $target = is_numeric($dataBulanan->target_quantitative) ? (float)$dataBulanan->target_quantitative : 0;
                $realization = is_numeric($dataBulanan->realization_quantitative) ? (float)$dataBulanan->realization_quantitative : 0;
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                $monthlyDataMap[$i] = $dataBulanan;

                $monthly[] = [
                    'bulan' => $i,
                    'month_name' => $monthNames[$i] ?? 'Unknown',
                    'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $item->year,
                    'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $percentage . '%',
                    'is_finalized' => (bool) $dataBulanan->is_finalize,
                    'note_recommendation' => $dataBulanan->note_recommendation ?? null,
                    'uploads' => $dataBulanan->uploads->map(function ($upload) {
                        return [
                            'id' => $upload->id,
                            'filepath' => $upload->filepath,
                            'domain' => $upload->domain,
                        ];
                    }),
                ];

                // Tambahkan ke array rekomendasi jika ada
                if (!empty($dataBulanan->note_recommendation)) {
                    $rekomendasi[] = [
                        'month_id' => $i,
                        'month_name' => $monthNames[$i] ?? 'Unknown',
                        'note_recommendation' => $dataBulanan->note_recommendation
                    ];
                }
            } else {
                $monthlyDataMap[$i] = null;
                $monthly[] = [
                    'bulan' => $i,
                    'month_name' => $monthNames[$i] ?? 'Unknown',
                    'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $item->year,
                    'residual_risk_level' => null,
                    'residual_risk_posisi_risiko' => null,
                    'residual_risk_posisi_risiko_color' => null,
                    'realization_percentage' => '0%',
                    'is_finalized' => false,
                    'note_recommendation' => null,
                    'uploads' => [],
                ];
            }
        }

        $headerUploads = $item->uploads->map(function ($upload) {
            return [
                'id' => $upload->id,
                'filepath' => $upload->filepath,
                'domain' => $upload->domain,
            ];
        });

        $entryData = $item->headerEntry->map(function ($entry) use ($monthNames, $item) {
            $monthlyEntries = collect();
            for ($i = 1; $i <= 12; $i++) {
                $monthlyEntry = $entry->monthlyEntryData->firstWhere('month', $i);
                if ($monthlyEntry) {
                    // Safe numeric conversion untuk perhitungan
                    $target = is_numeric($monthlyEntry->target_quantitative) ? (float)$monthlyEntry->target_quantitative : 0;
                    $realization = is_numeric($monthlyEntry->realization_quantitative) ? (float)$monthlyEntry->realization_quantitative : 0;
                    $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'month_name' => $monthNames[$i] ?? 'Unknown',
                        'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $item->year,
                        'residual_risk_level' => $monthlyEntry->residual_risk_level_risiko,
                        'residual_risk_posisi_risiko' => $monthlyEntry->residual_risk_posisi_risiko,
                        'residual_risk_posisi_risiko_color' => get_color_by_position($monthlyEntry->residual_risk_posisi_risiko),
                        'realization_percentage' => $percentage . '%',
                        'is_finalized' => (bool) $monthlyEntry->is_finalize,
                        'note_recommendation' => $monthlyEntry->monthRecommendation->note ?? null,
                        'monthly_entry_data' => $monthlyEntry,
                        'uploads' => $monthlyEntry->uploads->map(function ($upload) {
                            return [
                                'id' => $upload->id,
                                'filepath' => $upload->filepath,
                                'domain' => $upload->domain,
                            ];
                        }),
                    ];
                } else {
                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'month_name' => $monthNames[$i] ?? 'Unknown',
                        'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $item->year,
                        'residual_risk_level' => null,
                        'residual_risk_posisi_risiko' => null,
                        'residual_risk_posisi_risiko_color' => null,
                        'realization_percentage' => '0%',
                        'is_finalized' => false,
                        'note_recommendation' => null,
                        'monthly_entry_data' => null,
                        'uploads' => [],
                    ];
                }
            }

            $entryArray = [
                'id' => $entry->id,
                'monthly_entry' => $monthlyEntries,
            ];

            if ($entry->header_id !== null) $entryArray['header_id'] = $entry->header_id;
            if ($entry->judul_entry !== null) $entryArray['judul_entry'] = $entry->judul_entry;
            if ($entry->keterangan !== null) $entryArray['keterangan'] = $entry->keterangan;

            return $entryArray;
        });

        // Handle risk_code - sekarang berupa string yang dipisahkan koma
        $riskCodes = [];
        if (!empty($item->risk_code)) {
            $riskCodeIds = explode(',', $item->risk_code);
            $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                ->get(['id', 'name'])
                ->map(function ($riskCode) {
                    return [
                        'id' => $riskCode->id,
                        'name' => clean_string($riskCode->name)
                    ];
                })
                ->toArray();
        }

        // Safe numeric conversion untuk biaya_perlakuan_risiko
        $biayaPerlakuan = is_numeric($item->biaya_perlakuan_risiko) ? $item->biaya_perlakuan_risiko : 0;

        // *** TAMBAHAN BARU: CEK KELENGKAPAN DATA ***
        // Cek kelengkapan data header (sesuaikan field yang wajib diisi)
        $isHeaderComplete = !empty($item->peristiwa_risiko) &&
                           !empty($item->penyebab_risiko) &&
                           !empty($item->dampak_risiko) &&
                           !empty($item->mitigasi) &&
                           !empty($item->internal_control);

        // Cek apakah semua 12 bulan sudah finalisasi
        $allMonthsFinalized = true;
        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);
            if (!$dataBulanan || !$dataBulanan->is_finalize) {
                $allMonthsFinalized = false;
                break;
            }
        }

        // Tentukan risk status berdasarkan kondisi
        $riskStatus = $item->status;
               if ($isHeaderComplete && $allMonthsFinalized) {
            $riskStatus = 'close'; // Otomatis close jika semua data lengkap dan finalisasi
        } elseif ($item->status === 'approved' && !$isHeaderComplete) {
            $riskStatus = 'pending'; // Approved tapi data header belum lengkap
        } elseif ($item->status === 'approved' && $isHeaderComplete && !$allMonthsFinalized) {
            $riskStatus = 'open'; // Approved, header lengkap, tapi belum semua bulan finalisasi
        }
        // *** AKHIR TAMBAHAN BARU ***

        return [
            'id' => $item->id,
            'risk_status' => $riskStatus, // *** DIUBAH: sekarang menggunakan $riskStatus ***
            'department_id' => $item->department_id,
            'department_name' => $item->department->name ?? '',
            'is_header_complete' => $isHeaderComplete, // *** TAMBAHAN BARU ***
            'all_months_finalized' => $allMonthsFinalized, // *** TAMBAHAN BARU ***
            // 'is_complete' => (bool) $item->is_complete,
            'risk_code' => $riskCodes, // Sekarang berupa array dari multiple risk codes
            'process_code' => $item->process_code ?? '',
            'jenis_risiko' => $item->jenis_risiko ?? '',
            'sasaran' => $item->sasaran ?? '',
            'peristiwa_risiko' => $item->peristiwa_risiko ?? '',
            'penyebab_risiko' => $item->penyebab_risiko ?? '',
            'dampak_risiko' => $item->dampak_risiko ?? '',
            'inherent_risk_level_dampak' => $item->inherent_risk_level_dampak ?? 0,
            'inherent_risk_level_kemungkinan' => $item->inherent_risk_level_kemungkinan ?? 0,
            'inherent_risk_posisi_risiko' => $item->inherent_risk_posisi_risiko ?? '',
            'inherent_risk_level_risiko' => $item->inherent_risk_level_risiko ?? 0,
            'inherent_risk_posisi_risiko_color' => $inherentColor,
            'internal_control' => $item->internal_control ?? '',

            'target_satu_tahun_option' => $item->target_satu_tahun_option ?? null,
            'target_satu_tahun_option_name' => $item->optionTargetSatuTahun->name ?? '',
            'target_satu_tahun_option_type' => $item->optionTargetSatuTahun->type ?? null,
            'target_satu_tahun_notes' => $item->target_satu_tahun_notes ?? '',
            'target_satu_tahun_position' => $item->optionTargetSatuTahun->position ?? 0,
            "target_satu_tahun_type" => $item->optionTargetSatuTahun->type ?? null,
            'target_quantitative_satu_tahun' => format_target_quantitative($item->target_quantitative_satu_tahun),

            'biaya_perlakuan_risiko' => number_format($biayaPerlakuan, 0, ',', '.'),
            'mitigasi' => $item->mitigasi ?? '',
            'residual_target_level_dampak' => $item->residual_target_level_dampak ?? 0,
            'residual_target_level_kemungkinan' => $item->residual_target_level_kemungkinan ?? 0,
            'residual_target_posisi_risiko' => $item->residual_target_posisi_risiko ?? '',
            'residual_target_level_risiko' => $item->residual_target_level_risiko ?? 0,
            'residual_target_posisi_risiko_color' => $residualTargetColor,

            'year' => $item->year,
            'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
            'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,

            'created_by' => $item->created_by ?? null,
            'created_by_name' => get_decrypted_name($item->createdBy),
            'updated_by' => $item->updated_by ?? null,
            'updated_by_name' => get_decrypted_name($item->updatedBy),

            'ir_dampak' => $item->irDampak ?? null,
            'ir_kemungkinan' => $item->irKemungkinan ?? null,
            'rr_dampak' => $item->rrDampak ?? null,
            'rr_kemungkinan' => $item->rrKemungkinan ?? null,
            // 'department' => $item->department ?? null,

            // Array rekomendasi di level header
            'rekomendasi' => $rekomendasi,

            'monthly_data' => $item->monthlyData->map(function ($dataBulanan) use ($monthNames, $item) {
                // Safe numeric conversion untuk perhitungan
                $target = is_numeric($dataBulanan->target_quantitative) ? (float)$dataBulanan->target_quantitative : 0;
                $realization = is_numeric($dataBulanan->realization_quantitative) ? (float)$dataBulanan->realization_quantitative : 0;
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                return [
                    'id' => $dataBulanan->id,
                    'header_id' => $dataBulanan->header_id,
                    'month' => $dataBulanan->month,
                    'month_name' => $monthNames[$dataBulanan->month] ?? 'Unknown',
                    'month_full_name' => ($monthNames[$dataBulanan->month] ?? 'Unknown') . ' ' . $item->year,
                    'risk_code' => $dataBulanan->risk_code,
                    'status_risiko' => $dataBulanan->status_risiko,
                    'process_code' => $dataBulanan->process_code,
                    'start_date' => $dataBulanan->start_date ? $dataBulanan->start_date->format('Y-m-d H:i:s') : null,
                    'expired_date' => $dataBulanan->expired_date ? $dataBulanan->expired_date->format('Y-m-d H:i:s') : null,
                    // Return original value untuk display
                    'realization_quantitative' => $dataBulanan->realization_quantitative,
                    'realization_kualitatif' => $dataBulanan->realization_kualitatif,
                    'realization_note' => $dataBulanan->realization_note,
                    'target_quantitative' => $dataBulanan->target_quantitative,
                    'target_kualitatif' => $dataBulanan->target_kualitatif,
                    'target_notes' => $dataBulanan->target_notes,
                    'residual_risk_level_dampak' => $dataBulanan->residual_risk_level_dampak,
                    'residual_risk_level_kemungkinan' => $dataBulanan->residual_risk_level_kemungkinan,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_level_risiko' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_level_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $percentage . '%',
                    'is_finalize' => (bool) $dataBulanan->is_finalize,
                    'finalized_at' => $dataBulanan->finalized_at,
                    'finalized_by' => $dataBulanan->finalized_by,
                    'note_recommendation' => $dataBulanan->monthRecommendation->note ?? null,
                    'created_at' => $dataBulanan->created_at ? $dataBulanan->created_at->toISOString() : null,
                    'updated_at' => $dataBulanan->updated_at ? $dataBulanan->updated_at->toISOString() : null,
                    'uploads' => $dataBulanan->uploads->map(function ($upload) {
                        return [
                            'id' => $upload->id,
                            'filepath' => $upload->filepath,
                            'domain' => $upload->domain,
                        ];
                    }),
                ];
            }),
            'monthly' => $monthly,

            'uploads' => $headerUploads,
            'entry_data' => $entryData,
        ];
    });

    $cleanData = clean_recursive([
        'current_page' => $data->currentPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
        'last_page' => $data->lastPage(),
        'from' => $data->firstItem(),
        'to' => $data->lastItem(),
        'data' => $orderedData,
    ]);

    return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $cleanData);
}

public function show($id)
{
    $user = auth()->user();

    $query = TrRiskHeader::with([
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position,type',
        'createdBy',
        'updatedBy',
        'monthlyData' => function($query) {
            $query->orderBy('month', 'asc')->with(['uploads', 'createdBy', 'updatedBy']);
        }
    ])
    // Hapus filter status agar bisa menampilkan semua status
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
        $query->where('department_id', $user->department_id);
    });

    $data = $query->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
    }

    // Nama bulan dalam bahasa Indonesia
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $inherentColor = get_color_by_position($data->inherent_risk_posisi_risiko);
    $residualTargetColor = get_color_by_position($data->residual_target_posisi_risiko);

    $monthly = [];
    $monthlyData = [];
    $rekomendasi = []; // Array untuk rekomendasi di header

    for ($i = 1; $i <= 12; $i++) {
        $dataBulanan = $data->monthlyData->firstWhere('month', $i);

        if ($dataBulanan) {
            // Safe numeric conversion untuk perhitungan
            $target = is_numeric($dataBulanan->target_quantitative) ? (float)$dataBulanan->target_quantitative : 0;
            $realization = is_numeric($dataBulanan->realization_quantitative) ? (float)$dataBulanan->realization_quantitative : 0;
            $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

            // Logika untuk menentukan is_edit pada data monthly
            $isEditMonthly = !((bool) $dataBulanan->is_finalize); // Tidak bisa edit jika sudah finalize

            $monthly[] = [
                'bulan' => $i,
                'month_name' => $monthNames[$i] ?? 'Unknown',
                'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $data->year,
                'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                'realization_percentage' => $percentage . '%',
                'is_finalized' => (bool) $dataBulanan->is_finalize,
                'is_edit' => $isEditMonthly,
                'note_recommendation' => $dataBulanan->note_recommendation ?? null,
                'monthly_data' => $dataBulanan,
            ];

            $monthlyItem = [
                'id' => $dataBulanan->id,
                'header_id' => $dataBulanan->header_id,
                'month' => $dataBulanan->month,
                'month_name' => $monthNames[$dataBulanan->month] ?? 'Unknown',
                'month_full_name' => ($monthNames[$dataBulanan->month] ?? 'Unknown') . ' ' . $data->year,
                'risk_code' => $dataBulanan->risk_code,
                'status_risiko' => $dataBulanan->status_risiko,
                'process_code' => $dataBulanan->process_code,
                'start_date' => $dataBulanan->start_date,
                'expired_date' => $dataBulanan->expired_date,
                'realization_quantitative' => $dataBulanan->realization_quantitative,
                'realization_note' => $dataBulanan->realization_note,
                'target_quantitative' => $dataBulanan->target_quantitative,
                'target_notes' => $dataBulanan->target_notes,
                'residual_risk_level_dampak' => $dataBulanan->residual_risk_level_dampak,
                'residual_risk_level_kemungkinan' => $dataBulanan->residual_risk_level_kemungkinan,
                'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                'residual_risk_level_risiko' => $dataBulanan->residual_risk_level_risiko,
                'residual_risk_level_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                'is_finalize' => (bool) $dataBulanan->is_finalize,
                'is_edit' => $isEditMonthly,
                'finalized_at' => $dataBulanan->finalized_at,
                'finalized_by' => $dataBulanan->finalized_by,
                'note_recommendation' => $dataBulanan->note_recommendation ?? null,
                'created_at' => $dataBulanan->created_at,
                'updated_at' => $dataBulanan->updated_at,
                // Tambahkan created_by_name dan updated_by_name menggunakan helper yang sudah ada
                'created_by_name' => $dataBulanan->createdBy ? clean_string(get_decrypted_name($dataBulanan->createdBy)) : 'Unknown User',
                'updated_by_name' => $dataBulanan->updatedBy ? clean_string(get_decrypted_name($dataBulanan->updatedBy)) : 'Unknown User',
                'uploads' => $dataBulanan->uploads ? $dataBulanan->uploads->map(function ($upload) {
                    return [
                        'id' => $upload->id,
                        'filepath' => $upload->filepath,
                        'domain' => $upload->domain,
                    ];
                })->toArray() : [],
            ];

            $monthlyData[] = $monthlyItem;

            // Tambahkan ke array rekomendasi jika ada
            if (!empty($dataBulanan->note_recommendation)) {
                $rekomendasi[] = [
                    'month_id' => $i,
                    'month_name' => $monthNames[$i] ?? 'Unknown',
                    'note_recommendation' => $dataBulanan->note_recommendation
                ];
            }

        } else {
            $monthly[] = [
                'bulan' => $i,
                'month_name' => $monthNames[$i] ?? 'Unknown',
                'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $data->year,
                'residual_risk_level' => null,
                'residual_risk_posisi_risiko' => null,
                'residual_risk_posisi_risiko_color' => null,
                'realization_percentage' => '0%',
                'is_finalized' => false,
                'is_edit' => true, // Data kosong masih bisa diedit
                'note_recommendation' => null,
                'monthly_data' => null,
            ];

            $monthlyData[] = [
                'id' => null,
                'header_id' => $data->id,
                'month' => $i,
                'month_name' => $monthNames[$i] ?? 'Unknown',
                'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $data->year,
                'risk_code' => null,
                'status_risiko' => 'open',
                'process_code' => $data->process_code,
                'start_date' => null,
                'expired_date' => null,
                'realization_quantitative' => null,
                'realization_note' => null,
                'target_quantitative' => null,
                'target_notes' => null,
                'residual_risk_level_dampak' => null,
                'residual_risk_level_kemungkinan' => null,
                'residual_risk_posisi_risiko' => null,
                'residual_risk_level_risiko' => null,
                'residual_risk_level_risiko_color' => null,
                'is_finalize' => false,
                'is_edit' => true, // Data kosong masih bisa diedit
                'finalized_at' => null,
                'finalized_by' => null,
                'note_recommendation' => null,
                'created_at' => null,
                'updated_at' => null,
                'created_by_name' => 'Unknown User',
                'updated_by_name' => 'Unknown User',
                'uploads' => [],
            ];
        }
    }

    // Handle risk_code - sekarang berupa string yang dipisahkan koma
    $riskCodes = [];
    if (!empty($data->risk_code)) {
        $riskCodeIds = explode(',', $data->risk_code);
        $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
            ->get(['id', 'name'])
            ->map(function ($riskCode) {
                return [
                    'id' => $riskCode->id,
                    'name' => clean_string($riskCode->name)
                ];
            })
            ->toArray();
    }

    // *** TAMBAHAN BARU: CEK KELENGKAPAN DATA ***
    // Cek kelengkapan data header (sesuaikan field yang wajib diisi)
    $isHeaderComplete = !empty($data->peristiwa_risiko) &&
                       !empty($data->penyebab_risiko) &&
                       !empty($data->dampak_risiko) &&
                       !empty($data->mitigasi) &&
                       !empty($data->internal_control);

    // Cek apakah semua 12 bulan sudah finalisasi
    $allMonthsFinalized = true;
    for ($i = 1; $i <= 12; $i++) {
        $dataBulanan = $data->monthlyData->firstWhere('month', $i);
        if (!$dataBulanan || !$dataBulanan->is_finalize) {
            $allMonthsFinalized = false;
            break;
        }
    }

    // Tentukan risk status berdasarkan kondisi
    $riskStatus = $data->status;
    if ($isHeaderComplete && $allMonthsFinalized) {
        $riskStatus = 'close'; // Otomatis close jika semua data lengkap dan finalisasi
    } elseif ($data->status === 'approved' && !$isHeaderComplete) {
        $riskStatus = 'pending'; // Approved tapi data header belum lengkap
    } elseif ($data->status === 'approved' && $isHeaderComplete && !$allMonthsFinalized) {
        $riskStatus = 'open'; // Approved, header lengkap, tapi belum semua bulan finalisasi
    }
    // *** AKHIR TAMBAHAN BARU ***

    // Logika untuk menentukan is_edit pada data header
    // Perbaiki: Sesuaikan logika is_edit berdasarkan semua kemungkinan status
    $isEditHeader = !in_array($data->status, ['approved', 'close']);

    // Siapkan data utama
    $orderedData = [
        'id' => $data->id,
        'risk_status' => $riskStatus, // *** DIUBAH: sekarang menggunakan $riskStatus ***
        'department_id' => $data->department_id,
        'department_name' => $data->department->name ?? '',
        'is_header_complete' => $isHeaderComplete, // *** TAMBAHAN BARU ***
        'all_months_finalized' => $allMonthsFinalized, // *** TAMBAHAN BARU ***
        'risk_code' => $riskCodes, // Sekarang berupa array dari multiple risk codes
        'process_code' => $data->process_code ?? '',
        'jenis_risiko' => $data->jenis_risiko ?? '',
        'sasaran' => $data->sasaran ?? '',
        'peristiwa_risiko' => $data->peristiwa_risiko ?? '',
        'penyebab_risiko' => $data->penyebab_risiko ?? '',
        'dampak_risiko' => $data->dampak_risiko ?? '',
        'inherent_risk_level_dampak' => $data->inherent_risk_level_dampak ?? 0,
        'inherent_risk_level_kemungkinan' => $data->inherent_risk_level_kemungkinan ?? 0,
        'inherent_risk_posisi_risiko' => $data->inherent_risk_posisi_risiko ?? '',
        'inherent_risk_level_risiko' => $data->inherent_risk_level_risiko ?? 0,
        'inherent_risk_posisi_risiko_color' => $inherentColor,
        'internal_control' => $data->internal_control ?? '',

        // Grup target satu tahun
        'target_satu_tahun_option' => $data->target_satu_tahun_option ?? null,
        'target_satu_tahun_option_name' => $data->optionTargetSatuTahun->name ?? '',
        'target_satu_tahun_option_type' => $data->optionTargetSatuTahun->type ?? null,
        'target_satu_tahun_notes' => $data->target_satu_tahun_notes ?? '',
        'target_satu_tahun_position' => $data->optionTargetSatuTahun->position ?? 0,
        "target_satu_tahun_type" => $data->optionTargetSatuTahun->type ?? '',
        'target_quantitative_satu_tahun' => format_target_quantitative($data->target_quantitative_satu_tahun),

        'biaya_perlakuan_risiko' => number_format($data->biaya_perlakuan_risiko, 0, ',', '.'),
        'mitigasi' => $data->mitigasi ?? '',
        'residual_target_level_dampak' => $data->residual_target_level_dampak ?? 0,
        'residual_target_level_kemungkinan' => $data->residual_target_level_kemungkinan ?? 0,
        'residual_target_posisi_risiko' => $data->residual_target_posisi_risiko ?? '',
        'residual_target_level_risiko' => $data->residual_target_level_risiko ?? 0,
        'residual_target_posisi_risiko_color' => $residualTargetColor,
        'year' => $data->year,
        'is_edit' => $isEditHeader, // Properti untuk menentukan apakah header bisa diedit
        'created_at' => $data->created_at,
        'updated_at' => $data->updated_at,

        // Tambahkan created_by_name dan updated_by_name untuk data utama
        'created_by_name' => $data->createdBy ? clean_string(get_decrypted_name($data->createdBy)) : 'Unknown User',
        'updated_by_name' => $data->updatedBy ? clean_string(get_decrypted_name($data->updatedBy)) : 'Unknown User',

        // Array rekomendasi di level header
        'rekomendasi' => $rekomendasi,

        // Relationships
        'ir_dampak' => $data->irDampak ?? null,
        'ir_kemungkinan' => $data->irKemungkinan ?? null,
        'rr_dampak' => $data->rrDampak ?? null,
        'rr_kemungkinan' => $data->rrKemungkinan ?? null,
        'department' => $data->department ?? null,
        'monthly_data' => $monthlyData,
        'monthly' => $monthly,
    ];

    // Bersihkan seluruh data menggunakan helper yang sudah ada
    $orderedData = clean_recursive($orderedData);

    return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $orderedData);
}

public function store(Request $request)
{
    // ============================================
    // VALIDASI ROLE: HANYA ROLE 1, 2, 3 YANG DIIZINKAN
    // ============================================

    $result = check_role(auth()->user(), [1, 2, 3]);
    if ($result !== true) {
        return $result;
    }

    $currentUser = auth()->user();

    // ============================================
    // VALIDASI WAJIB: HANYA BOLEH 14 FIELD DASAR
    // ============================================

    $allowedFields = [
        'department_id',
        'risk_code',
        'jenis_risiko',
        'year',
        'sasaran',
        'peristiwa_risiko',
        'penyebab_risiko',
        'dampak_risiko',
        'inherent_risk_level_dampak',
        'inherent_risk_level_kemungkinan',
        'residual_target_level_dampak',
        'residual_target_level_kemungkinan',
        'mitigasi',
        'biaya_perlakuan_risiko'
    ];

    $forbiddenFields = [
        'internal_control',
        'target_quantitative_satu_tahun',
        'target_satu_tahun_option',
        'target_satu_tahun_notes'
    ];

    // CEK SETIAP FORBIDDEN FIELD
    $violations = [];
    foreach ($forbiddenFields as $field) {
        $value = $request->input($field);

        // Jika ada value apapun (selain null), langsung tolak
        if ($value !== null) {
            $violations[] = [
                'field' => $field,
                'value' => $value,
                'type' => gettype($value)
            ];
        }
    }

    // JIKA ADA PELANGGARAN, LANGSUNG EXIT
    if (!empty($violations)) {
        return response()->json([
            'code' => 404,
            'status' => false,
            'title' => 'AKSES DITOLAK',
            'message' => 'MAAF! Anda hanya diizinkan mengisi 14 field dasar saja. 4 field tambahan hanya bisa diisi setelah data 14 field disetujui.',
            'data' => [
                'violations' => $violations,
                'allowed_fields' => $allowedFields,
                'forbidden_fields' => $forbiddenFields,
                'instruction' => 'Hapus semua field yang tidak diizinkan dari request Anda.'
            ]
        ], 404);
    }

    // ============================================
    // LANJUT KE VALIDASI NORMAL
    // ============================================

    $validator = Validator::make($request->all(), [
        // 14 field wajib untuk penyimpanan awal
        'risk_code' => 'required|array',
        'risk_code.*' => 'exists:mst_risk_code,id',
        'jenis_risiko' => 'required|string',
        'year' => 'required|integer',
        'sasaran' => 'required|string',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'department_id' => 'required|exists:mst_department,id',
        'mitigasi' => 'nullable|string',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        // HANYA AMBIL DATA YANG DIIZINKAN
        $data = [];
        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        $data['created_by'] = auth()->id();
        $data['created_by_role'] = auth()->user()->role_id;

        // ============================================
        // SET DEPARTMENT SESUAI ROLE
        // ============================================

        // Superadmin (role 1) boleh pilih departemen dari request
        if ($currentUser->role_id == 1) {
            $data['department_id'] = $request->input('department_id');
        } else {
            // Role lain (2, 3) selalu pakai department_id user
            $data['department_id'] = $currentUser->department_id;
        }

        // Status selalu pending untuk 14 field
        $data['status'] = 'draft';
        $data['is_complete'] = false;


        if (!empty($data['risk_code']) && is_array($data['risk_code'])) {
            $data['risk_code'] = implode(',', $data['risk_code']);
        }

        $irHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $data['inherent_risk_level_dampak'])
            ->where('kemungkinan', $data['inherent_risk_level_kemungkinan'])
            ->first();

        if (!$irHeatmap) {
            return json(400, false, 'IR Tidak Ditemukan', 'Kombinasi IR tidak ditemukan.', null);
        }

        $data['inherent_risk_posisi_risiko'] = $irHeatmap->result;
        $data['inherent_risk_level_risiko'] = $irHeatmap->riskRange->name ?? null;

        $rrHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $data['residual_target_level_dampak'])
            ->where('kemungkinan', $data['residual_target_level_kemungkinan'])
            ->first();

        if (!$rrHeatmap) {
            return json(400, false, 'RR Tidak Ditemukan', 'Kombinasi RR tidak ditemukan.', null);
        }

        $data['residual_target_posisi_risiko'] = $rrHeatmap->result;
        $data['residual_target_level_risiko'] = $rrHeatmap->riskRange->name ?? null;

        $riskHeader = TrRiskHeader::create($data);

        // Selalu buat approval entry untuk 14 field
        // $currentUser = auth()->user();
        // $jabatanId = null;

        // if ($currentUser->jabatan_id) {
        //     $jabatanId = $currentUser->jabatan_id;
        // } else {
        //     $jabatan = MstJabatan::where('department_id', $data['department_id'])->first();
        //     $jabatanId = $jabatan ? $jabatan->id : null;
        // }

        // \App\Models\MstApproval::create([
        //     'document_id' => $riskHeader->id,
        //     'tahun' => $data['year'], // Gunakan year dari request
        //     'posisi' => 1,
        //     'jabatan_id' => $jabatanId,
        //     'status' => 'pending',
        //     'tanggal' => null,
        //     'note' => null
        // ]);

        DB::commit();

        // Load relasi yang diperlukan
        $riskHeader->load([
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'createdBy:id,username',
        ]);

        $createdByName = 'Unknown User';
        try {
            $createdByName = get_decrypted_name($riskHeader->createdBy);
        } catch (\Throwable $e) {
            \Log::warning("Error handling createdBy: {$e->getMessage()}");
        }

        $responseData = [
            'id' => $riskHeader->id,
            'risk_code' => $riskHeader->risk_code ? explode(',', $riskHeader->risk_code) : [],
            'jenis_risiko' => clean_string($riskHeader->jenis_risiko),
            'sasaran' => clean_string($riskHeader->sasaran),
            'peristiwa_risiko' => clean_string($riskHeader->peristiwa_risiko),
            'penyebab_risiko' => clean_string($riskHeader->penyebab_risiko),
            'dampak_risiko' => clean_string($riskHeader->dampak_risiko),
            'inherent_risk_level_dampak' => $riskHeader->inherent_risk_level_dampak,
            'inherent_risk_level_kemungkinan' => $riskHeader->inherent_risk_level_kemungkinan,
            'residual_target_level_dampak' => $riskHeader->residual_target_level_dampak,
            'residual_target_level_kemungkinan' => $riskHeader->residual_target_level_kemungkinan,
            'department_id' => $riskHeader->department_id,
            'inherent_risk_posisi_risiko' => $riskHeader->inherent_risk_posisi_risiko,
            'inherent_risk_level_risiko' => clean_string($riskHeader->inherent_risk_level_risiko),
            'residual_target_posisi_risiko' => $riskHeader->residual_target_posisi_risiko,
            'residual_target_level_risiko' => clean_string($riskHeader->residual_target_level_risiko),
            'process_code' => $riskHeader->process_code ?? null,
            // 'status' => $riskHeader->status,
            'is_complete' => $riskHeader->is_complete ?? false,
            'year' => $riskHeader->year,
            'updated_at' => $riskHeader->updated_at,
            'created_at' => $riskHeader->created_at,
            'created_by' => $riskHeader->created_by,
            'created_by_name' => $createdByName,
            'mitigasi' => clean_string($riskHeader->mitigasi),
            'biaya_perlakuan_risiko' => $riskHeader->biaya_perlakuan_risiko,

            // 4 field tambahan selalu NULL di response store
            'internal_control' => null,
            'target_satu_tahun_option' => null,
            'target_satu_tahun_notes' => null,
            'target_satu_tahun_type' => null,
            'target_quantitative_satu_tahun' => null,
            'target_satu_tahun_position' => null,
            'approval_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];

        // Tambahkan relasi
        $this->addRelationsToResponse($riskHeader, $responseData);

        $message = 'Risk header (14 field dasar) berhasil disimpan dengan status draft dan menunggu persetujuan. Setelah disetujui, 14 field akan terkunci dan Anda dapat melengkapi 4 field sisanya melalui proses update.';

        return json(200, true, 'Berhasil Disimpan', $message, $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function update(Request $request, $id)
{
    $currentUser = auth()->user();

    // Validasi role: hanya role 1, 2, 3 yang diizinkan
    $roleCheck = check_role($currentUser, [1, 2, 3]);
    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $riskHeader = TrRiskHeader::when(in_array($currentUser->role_id, [2, 3]), function ($query) use ($currentUser) {
        // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
        $query->where('department_id', $currentUser->department_id);
    })->find($id);

    if (!$riskHeader) {
        return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
    }

    // ============================================
    // VALIDASI BERDASARKAN STATUS
    // ============================================

    $currentStatus = $riskHeader->status;

    // Status 'submit' - tidak boleh diedit sama sekali
    if ($currentStatus === 'submit') {
        return json(403, false, 'Akses Ditolak', 'Data sudah disubmit dan menunggu persetujuan. Data tidak dapat diubah.', null);
    }

    // Status 'close' - tidak boleh diedit sama sekali
    if ($currentStatus === 'close') {
        return json(403, false, 'Akses Ditolak', 'Data sudah ditutup dan tidak dapat diubah lagi.', null);
    }

    // Cek apakah data sudah complete dan approved (tidak bisa diubah sama sekali)
    $isDataComplete = $currentStatus === 'approved' && $riskHeader->is_complete;

    if ($isDataComplete) {
        return json(403, false, 'Akses Ditolak', 'Semua data sudah terisi dan di-approve, tidak bisa dirubah lagi.', null);
    }

    // ============================================
    // TENTUKAN SKENARIO UPDATE BERDASARKAN STATUS
    // ============================================

    // Skenario 1: 14 field sudah di-approve, hanya boleh isi 4 field tambahan
    if ($currentStatus === 'approved' && !$riskHeader->is_complete) {
        return $this->handleApprovedPartialUpdate($request, $riskHeader);
    }

    // Skenario 2: 14 field di-reject, hanya boleh update 14 field (4 field harus kosong)
    if ($currentStatus === 'rejected') {
        return $this->handleRejectedUpdate($request, $riskHeader);
    }

    // Skenario 3: Status draft, boleh update 14 field
    if ($currentStatus === 'draft') {
        return $this->handleDraftUpdate($request, $riskHeader);
    }

    return json(400, false, 'Status Tidak Valid', 'Status data tidak memungkinkan untuk diupdate.', null);
}

private function handleApprovedPartialUpdate(Request $request, $riskHeader)
{
    // 14 field sudah approved, HANYA BOLEH ISI 4 FIELD TAMBAHAN
    $allowedFields = [
        'internal_control',
        'target_quantitative_satu_tahun',
        'target_satu_tahun_option',
        'target_satu_tahun_notes'
    ];

    $forbiddenFields = [
        'risk_code', 'jenis_risiko', 'year', 'sasaran', 'peristiwa_risiko', 'penyebab_risiko',
        'dampak_risiko', 'inherent_risk_level_dampak', 'inherent_risk_level_kemungkinan',
        'residual_target_level_dampak', 'residual_target_level_kemungkinan', 'department_id',
        'mitigasi', 'biaya_perlakuan_risiko'
    ];

    // CEK SETIAP FORBIDDEN FIELD (sama seperti di store)
    $violations = [];
    foreach ($forbiddenFields as $field) {
        $value = $request->input($field);

        // Jika ada value apapun (selain null), langsung tolak
        if ($value !== null) {
            $violations[] = [
                'field' => $field,
                'value' => $value,
                'type' => gettype($value)
            ];
        }
    }

    // JIKA ADA PELANGGARAN, LANGSUNG EXIT (sama seperti di store)
    if (!empty($violations)) {
        return response()->json([
            'code' => 404,
            'status' => false,
            'title' => 'AKSES DITOLAK',
            'message' => '14 field dasar sudah disetujui dan tidak dapat diubah. Anda hanya boleh mengisi 4 field tambahan.',
            'data' => [
                'violations' => $violations,
                'allowed_fields' => $allowedFields,
                'forbidden_fields' => $forbiddenFields,
                'instruction' => 'Hapus semua field yang tidak diizinkan dari request Anda.'
            ]
        ], 404);
    }

    // Validasi 4 field tambahan (wajib diisi)
    $validator = Validator::make($request->all(), [
        'internal_control' => 'required|string',
        'target_satu_tahun_option' => 'required|exists:mst_option,id',
        'target_satu_tahun_notes' => 'required|string',
        'target_quantitative_satu_tahun' => 'required|string|max:500'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Semua 4 field tambahan wajib diisi.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        // HANYA AMBIL DATA YANG DIIZINKAN (sama seperti di store)
        $updateData = [];
        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        // Set status menjadi approved dan complete setelah 4 field diisi
        $updateData['status'] = 'approved';
        $updateData['is_complete'] = true;
        $updateData['approved_by'] = auth()->id();
        $updateData['approved_at'] = now();

        // Handle target_satu_tahun_position
        if (!empty($updateData['target_satu_tahun_option'])) {
            $option = MstOption::find($updateData['target_satu_tahun_option']);
            $updateData['target_satu_tahun_position'] = $option ? $option->position : null;
        }

        $riskHeader->update($updateData);

        // Generate monthly data karena sekarang sudah lengkap
        generate_monthly_data($riskHeader);

        // Update created_by untuk monthly data
        TrRiskMonthly::where('header_id', $riskHeader->id)
            ->whereNull('created_by')
            ->update([
                'created_by' => $riskHeader->created_by,
                'updated_by' => auth()->id()
            ]);

        DB::commit();

        $riskHeader->refresh();
        $responseData = $this->buildResponse($riskHeader);

        return json(200, true, 'Berhasil Dilengkapi', 'Risk header berhasil dilengkapi dengan 4 field tambahan. Data sekarang sudah final dan tidak dapat diubah lagi.', $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

private function handleRejectedUpdate(Request $request, $riskHeader)
{
    // Data di-reject, HANYA BOLEH UPDATE 14 FIELD, 4 FIELD HARUS KOSONG
    $allowedFields = [
        'department_id',
        'risk_code',
        'jenis_risiko',
        'sasaran',
        'year',
        'peristiwa_risiko',
        'penyebab_risiko',
        'dampak_risiko',
        'inherent_risk_level_dampak',
        'inherent_risk_level_kemungkinan',
        'residual_target_level_dampak',
        'residual_target_level_kemungkinan',
        'mitigasi',
        'biaya_perlakuan_risiko'
    ];

    $forbiddenFields = [
        'internal_control',
        'target_quantitative_satu_tahun',
        'target_satu_tahun_option',
        'target_satu_tahun_notes'
    ];

    // CEK SETIAP FORBIDDEN FIELD
    $violations = [];
    foreach ($forbiddenFields as $field) {
        $value = $request->input($field);

        // Jika ada value apapun (selain null), langsung tolak
        if ($value !== null) {
            $violations[] = [
                'field' => $field,
                'value' => $value,
                'type' => gettype($value)
            ];
        }
    }

    // JIKA ADA PELANGGARAN, LANGSUNG EXIT
    if (!empty($violations)) {
        return response()->json([
            'code' => 404,
            'status' => false,
            'title' => 'AKSES DITOLAK',
            'message' => 'Data ditolak. Anda hanya boleh memperbaiki 14 field dasar saja. 4 field tambahan tidak boleh diisi.',
            'data' => [
                'violations' => $violations,
                'allowed_fields' => $allowedFields,
                'forbidden_fields' => $forbiddenFields,
                'instruction' => 'Hapus semua field yang tidak diizinkan dari request Anda.'
            ]
        ], 404);
    }

    // Validasi 14 field dasar
    $validator = Validator::make($request->all(), [
        'risk_code' => 'required|array',
        'risk_code.*' => 'exists:mst_risk_code,id',
        'jenis_risiko' => 'required|string',
        'sasaran' => 'required|string',
        'year' => 'required|integer',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'department_id' => 'required|exists:mst_department,id',
        'mitigasi' => 'nullable|string',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi 14 field dasar gagal.', $validator->errors());
    }

    // ============================================
    // VALIDASI DEPARTMENT BERDASARKAN ROLE
    // ============================================

    $currentUser = auth()->user();

    // Role 2 & 3 tidak boleh mengubah department_id, harus sesuai department mereka
    if (in_array($currentUser->role_id, [2, 3])) {
        if ($request->has('department_id') && $request->input('department_id') != $currentUser->department_id) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak dapat mengubah department_id ke department lain.', null);
        }
    }

    try {
        DB::beginTransaction();

        // HANYA AMBIL DATA YANG DIIZINKAN
        $updateData = [];
        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        $updateData['created_by'] = auth()->id();
        $updateData['created_by_role'] = auth()->user()->role_id;

        // ============================================
        // SET DEPARTMENT SESUAI ROLE
        // ============================================

        // Superadmin (role 1) boleh ubah departemen dari request
        if ($currentUser->role_id == 1) {
            if ($request->has('department_id')) {
                $updateData['department_id'] = $request->input('department_id');
            }
        } else {
            // Role lain (2, 3) selalu pakai department_id user
            $updateData['department_id'] = $currentUser->department_id;
        }

        // PAKSA kosongkan 4 field tambahan
        $updateData['internal_control'] = null;
        $updateData['target_quantitative_satu_tahun'] = null;
        $updateData['target_satu_tahun_option'] = null;
        $updateData['target_satu_tahun_notes'] = null;
        $updateData['target_satu_tahun_position'] = null;

        // Set status kembali ke draft
        $updateData['status'] = 'draft';
        $updateData['is_complete'] = false;
        $updateData['approval_notes'] = null;
        $updateData['approved_by'] = null;
        $updateData['approved_at'] = null;

        // Handle risk_code
        if (!empty($updateData['risk_code']) && is_array($updateData['risk_code'])) {
            $updateData['risk_code'] = implode(',', $updateData['risk_code']);
        }

        // Update heatmap calculations
        $irHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $updateData['inherent_risk_level_dampak'])
            ->where('kemungkinan', $updateData['inherent_risk_level_kemungkinan'])
            ->first();

        if (!$irHeatmap) {
            return json(400, false, 'IR Tidak Ditemukan', 'Kombinasi IR tidak ditemukan.', null);
        }

        $updateData['inherent_risk_posisi_risiko'] = $irHeatmap->result;
        $updateData['inherent_risk_level_risiko'] = $irHeatmap->riskRange->name ?? null;

        $rrHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $updateData['residual_target_level_dampak'])
            ->where('kemungkinan', $updateData['residual_target_level_kemungkinan'])
            ->first();

        if (!$rrHeatmap) {
            return json(400, false, 'RR Tidak Ditemukan', 'Kombinasi RR tidak ditemukan.', null);
        }

        $updateData['residual_target_posisi_risiko'] = $rrHeatmap->result;
        $updateData['residual_target_level_risiko'] = $rrHeatmap->riskRange->name ?? null;

        $riskHeader->update($updateData);

        // HAPUS BAGIAN APPROVAL ENTRY - status kembali ke draft, belum perlu approval
        // Approval entry akan dibuat saat user submit data

        DB::commit();

        $riskHeader->refresh();
        $responseData = $this->buildResponse($riskHeader);

        return json(200, true, 'Berhasil Diperbaiki', 'Data yang ditolak berhasil diperbaiki. Status kembali ke draft. Silakan submit data ketika sudah siap untuk proses approval.', $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

private function handleDraftUpdate(Request $request, $riskHeader)
{
    // Status draft, HANYA BOLEH UPDATE 14 FIELD, 4 FIELD HARUS KOSONG
    $allowedFields = [
        'department_id',
        'risk_code',
        'jenis_risiko',
        'sasaran',
        'year',
        'peristiwa_risiko',
        'penyebab_risiko',
        'dampak_risiko',
        'inherent_risk_level_dampak',
        'inherent_risk_level_kemungkinan',
        'residual_target_level_dampak',
        'residual_target_level_kemungkinan',
        'mitigasi',
        'biaya_perlakuan_risiko'
    ];

    $forbiddenFields = [
        'internal_control',
        'target_quantitative_satu_tahun',
        'target_satu_tahun_option',
        'target_satu_tahun_notes'
    ];

    // CEK SETIAP FORBIDDEN FIELD
    $violations = [];
    foreach ($forbiddenFields as $field) {
        $value = $request->input($field);

        if ($value !== null) {
            $violations[] = [
                'field' => $field,
                'value' => $value,
                'type' => gettype($value)
            ];
        }
    }

    // JIKA ADA PELANGGARAN, LANGSUNG EXIT
    if (!empty($violations)) {
        return response()->json([
            'code' => 404,
            'status' => false,
            'title' => 'AKSES DITOLAK',
            'message' => 'Data masih draft. Anda hanya boleh mengubah 14 field dasar saja. 4 field tambahan tidak boleh diisi.',
            'data' => [
                'violations' => $violations,
                'allowed_fields' => $allowedFields,
                'forbidden_fields' => $forbiddenFields,
                'instruction' => 'Hapus semua field yang tidak diizinkan dari request Anda.'
            ]
        ], 404);
    }

    // Validasi 14 field dasar
    $validator = Validator::make($request->all(), [
        'risk_code' => 'required|array',
        'risk_code.*' => 'exists:mst_risk_code,id',
        'jenis_risiko' => 'required|string',
        'sasaran' => 'required|string',
        'year' => 'required|integer',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'department_id' => 'required|exists:mst_department,id',
        'mitigasi' => 'nullable|string',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi 14 field dasar gagal.', $validator->errors());
    }

    // ============================================
    // VALIDASI DEPARTMENT BERDASARKAN ROLE
    // ============================================

    $currentUser = auth()->user();

    // Role 2 & 3 tidak boleh mengubah department_id, harus sesuai department mereka
    if (in_array($currentUser->role_id, [2, 3])) {
        if ($request->has('department_id') && $request->input('department_id') != $currentUser->department_id) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak dapat mengubah department_id ke department lain.', null);
        }
    }

    try {
        DB::beginTransaction();

        // HANYA AMBIL DATA YANG DIIZINKAN
        $updateData = [];
        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        // Update creator info
        $updateData['created_by'] = auth()->id();
        $updateData['created_by_role'] = auth()->user()->role_id;

        // ============================================
        // SET DEPARTMENT SESUAI ROLE
        // ============================================

        // Superadmin (role 1) boleh ubah departemen dari request
        if ($currentUser->role_id == 1) {
            if ($request->has('department_id')) {
                $updateData['department_id'] = $request->input('department_id');
            }
        } else {
            // Role lain (2, 3) selalu pakai department_id user
            $updateData['department_id'] = $currentUser->department_id;
        }

        // PAKSA kosongkan 4 field tambahan untuk memastikan
        $updateData['internal_control'] = null;
        $updateData['target_quantitative_satu_tahun'] = null;
        $updateData['target_satu_tahun_option'] = null;
        $updateData['target_satu_tahun_notes'] = null;
        $updateData['target_satu_tahun_position'] = null;

        // Status tetap draft, reset approval info
        $updateData['status'] = 'draft';
        $updateData['is_complete'] = false;
        $updateData['approval_notes'] = null;
        $updateData['approved_by'] = null;
        $updateData['approved_at'] = null;

        // Handle risk_code
        if (!empty($updateData['risk_code']) && is_array($updateData['risk_code'])) {
            $updateData['risk_code'] = implode(',', $updateData['risk_code']);
        }

        // Update heatmap calculations
        $irHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $updateData['inherent_risk_level_dampak'])
            ->where('kemungkinan', $updateData['inherent_risk_level_kemungkinan'])
            ->first();

        if (!$irHeatmap) {
            return json(400, false, 'IR Tidak Ditemukan', 'Kombinasi IR tidak ditemukan.', null);
        }

        $updateData['inherent_risk_posisi_risiko'] = $irHeatmap->result;
        $updateData['inherent_risk_level_risiko'] = $irHeatmap->riskRange->name ?? null;

        $rrHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $updateData['residual_target_level_dampak'])
            ->where('kemungkinan', $updateData['residual_target_level_kemungkinan'])
            ->first();

        if (!$rrHeatmap) {
            return json(400, false, 'RR Tidak Ditemukan', 'Kombinasi RR tidak ditemukan.', null);
        }

        $updateData['residual_target_posisi_risiko'] = $rrHeatmap->result;
        $updateData['residual_target_level_risiko'] = $rrHeatmap->riskRange->name ?? null;

        $riskHeader->update($updateData);

        // HAPUS BAGIAN APPROVAL ENTRY - untuk draft belum perlu approval entry
        // Approval entry akan dibuat saat user submit data

        DB::commit();

        $riskHeader->refresh();
        $responseData = $this->buildResponse($riskHeader);

        return json(200, true, 'Berhasil Diupdate', 'Data draft berhasil diupdate. Silakan submit data ketika sudah siap untuk proses approval.', $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

private function buildResponse($riskHeader)
{
    // Load relasi yang diperlukan
    $riskHeader->load([
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position,type',
        'createdBy:id,username',
    ]);

    $createdByName = 'Unknown User';
    try {
        $createdByName = get_decrypted_name($riskHeader->createdBy);
    } catch (\Throwable $e) {
        \Log::warning("Error handling createdBy: {$e->getMessage()}");
    }

    $formattedTarget = format_target_quantitative($riskHeader->target_quantitative_satu_tahun);

    $responseData = [
        'id' => $riskHeader->id,
        'risk_code' => $riskHeader->risk_code ? explode(',', $riskHeader->risk_code) : [],
        'jenis_risiko' => clean_string($riskHeader->jenis_risiko),
        'sasaran' => clean_string($riskHeader->sasaran),
        'peristiwa_risiko' => clean_string($riskHeader->peristiwa_risiko),
        'penyebab_risiko' => clean_string($riskHeader->penyebab_risiko),
        'dampak_risiko' => clean_string($riskHeader->dampak_risiko),
        'inherent_risk_level_dampak' => $riskHeader->inherent_risk_level_dampak,
        'inherent_risk_level_kemungkinan' => $riskHeader->inherent_risk_level_kemungkinan,
        'residual_target_level_dampak' => $riskHeader->residual_target_level_dampak,
        'residual_target_level_kemungkinan' => $riskHeader->residual_target_level_kemungkinan,
        'internal_control' => clean_string($riskHeader->internal_control),
        'mitigasi' => clean_string($riskHeader->mitigasi),
        'target_satu_tahun_option' => $riskHeader->target_satu_tahun_option,
        'target_satu_tahun_notes' => clean_string($riskHeader->target_satu_tahun_notes),
        'target_satu_tahun_name' => $riskHeader->optionTargetSatuTahun ? $riskHeader->optionTargetSatuTahun->name : null,
        'target_satu_tahun_type' => optional($riskHeader->optionTargetSatuTahun)->type,
        'target_quantitative_satu_tahun' => $formattedTarget,
        'biaya_perlakuan_risiko' => $riskHeader->biaya_perlakuan_risiko ? number_format($riskHeader->biaya_perlakuan_risiko, 0, ',', '.') : null,
        'department_id' => $riskHeader->department_id,
        'year' => $riskHeader->year,
        'target_satu_tahun_position' => clean_string($riskHeader->target_satu_tahun_position),
        'inherent_risk_posisi_risiko' => $riskHeader->inherent_risk_posisi_risiko,
        'inherent_risk_level_risiko' => clean_string($riskHeader->inherent_risk_level_risiko),
        'residual_target_posisi_risiko' => $riskHeader->residual_target_posisi_risiko,
        'residual_target_level_risiko' => clean_string($riskHeader->residual_target_level_risiko),
        'process_code' => $riskHeader->process_code ?? null,
        'status' => $riskHeader->status,
        'is_complete' => $riskHeader->is_complete ?? false,
        'approval_notes' => clean_string($riskHeader->approval_notes),
        'approved_by' => $riskHeader->approved_by,
        'approved_at' => $riskHeader->approved_at,
        'updated_at' => $riskHeader->updated_at,
        'created_at' => $riskHeader->created_at,
        'created_by' => $riskHeader->created_by,
        'created_by_name' => $createdByName,
    ];

    $this->addRelationsToResponse($riskHeader, $responseData);

    return $responseData;
}

public function submit(Request $request, $id)
{
    $currentUser = auth()->user();

    // Validasi role: hanya role 1, 2, 3 yang diizinkan
    $roleCheck = check_role($currentUser, [1, 2, 3]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $riskHeader = TrRiskHeader::when(in_array($currentUser->role_id, [2, 3]), function ($query) use ($currentUser) {
        // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
        $query->where('department_id', $currentUser->department_id);
    })->find($id);

    if (!$riskHeader) {
        return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
    }

    // Hanya data dengan status 'draft' yang bisa disubmit
    if ($riskHeader->status !== 'draft') {
        return json(403, false, 'Akses Ditolak', 'Hanya data dengan status draft yang dapat disubmit.', null);
    }

    // Validasi 14 field dasar harus terisi
    $requiredFields = [
        'risk_code', 'jenis_risiko', 'year', 'sasaran', 'peristiwa_risiko',
        'penyebab_risiko', 'dampak_risiko', 'inherent_risk_level_dampak',
        'inherent_risk_level_kemungkinan', 'residual_target_level_dampak',
        'residual_target_level_kemungkinan', 'department_id'
    ];

    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($riskHeader->$field)) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        return json(400, false, 'Data Tidak Lengkap', 'Field berikut harus diisi sebelum submit: ' . implode(', ', $missingFields), [
            'missing_fields' => $missingFields
        ]);
    }

    try {
        DB::beginTransaction();

        // Update status menjadi 'submit'
        $riskHeader->update([
            'status' => 'submit',
            'submitted_at' => now(),
            'submitted_by' => auth()->id()
        ]);

        // Buat approval entry untuk proses persetujuan
        $jabatanId = null;

        if ($currentUser->jabatan_id) {
            $jabatanId = $currentUser->jabatan_id;
        } else {
            $jabatan = MstJabatan::where('department_id', $riskHeader->department_id)->first();
            $jabatanId = $jabatan ? $jabatan->id : null;
        }

        // Cek apakah sudah ada approval entry
        $existingApproval = \App\Models\MstApproval::where('document_id', $riskHeader->id)->first();

        if ($existingApproval) {
            // Update existing approval entry
            $existingApproval->update([
                'tahun' => $riskHeader->year,
                'jabatan_id' => $jabatanId,
                'status' => 'pending',
                'tanggal' => null,
                'note' => null
            ]);
        } else {
            // Buat approval entry baru
            \App\Models\MstApproval::create([
                'document_id' => $riskHeader->id,
                'tahun' => $riskHeader->year,
                'posisi' => 1,
                'jabatan_id' => $jabatanId,
                'status' => 'pending',
                'tanggal' => null,
                'note' => null
            ]);
        }

        DB::commit();

        $riskHeader->refresh();
        $responseData = $this->buildResponse($riskHeader);

        return json(200, true, 'Berhasil Submit', 'Data berhasil disubmit untuk proses persetujuan. Status berubah menjadi submit dan data tidak dapat diedit hingga ada keputusan persetujuan.', $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Submit', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function monitoring(Request $request)
{
    $user = auth()->user();

    $perPage = $request->input('per_page', 10);

    $query = TrRiskHeader::with([
        // PERBAIKAN: Hapus relasi riskCode karena sekarang risk_code adalah string comma-separated
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position',
        'uploads',
        'monthlyData' => function ($query) {
            $query->orderBy('month', 'asc')->with('uploads');
        },
        'headerEntry.monthlyEntryData.uploads',
        // PERBAIKAN: Hapus relasi riskCode dari headerEntry juga
        'headerEntry.irDampak:id,label',
        'headerEntry.irKemungkinan:id,label',
        'headerEntry.rrDampak:id,label',
        'headerEntry.rrKemungkinan:id,label',
        'headerEntry.department:id,name',
        'headerEntry.optionTargetSatuTahun:id,name,position',
        'createdBy:id,username,id',
        'updatedBy:id,username,id',
    ])

    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
        $query->where('department_id', $user->department_id);
    })

    ->when($request->peristiwa, function ($query) use ($request) {
        $query->where('peristiwa_risiko', 'like', '%' . $request->peristiwa . '%');
    })
    ->when($request->unit_kerja, function ($query) use ($request) {
        $query->whereHas('department', function ($q) use ($request) {
            $q->where('name', 'like', '%' . $request->unit_kerja . '%');
        });
    })
    ->when($request->tahun, function ($query) use ($request) {
        $query->where('year', $request->tahun);
    })
    ->orderBy('id', 'desc');

    // Paginate
    $data = $query->paginate($perPage);

    // Helper function untuk menangani persentase dengan target yang bisa string atau angka
    $calculatePercentage = function ($target, $realization) {
        // Jika target adalah string atau tidak numeric, return null untuk menandakan tidak dapat dihitung
        if (!is_numeric($target) || $target <= 0) {
            return null;
        }

        // Jika realization bukan numeric, return 0
        if (!is_numeric($realization)) {
            return 0;
        }

        return round(($realization / $target) * 100, 2);
    };

    // Helper function untuk format target quantitative
    $formatTargetQuantitative = function ($target) {
        // Jika target adalah string atau mengandung huruf, return apa adanya
        if (!is_numeric($target)) {
            return $target;
        }

        // Jika numeric, format dengan number_format
        return number_format($target, 0, ',', '.');
    };

    // Mapping data with same structure as index
    $orderedData = collect($data->items())->map(function ($item) use ($calculatePercentage, $formatTargetQuantitative) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        // PERBAIKAN: Process risk_code dari string comma-separated menjadi array
        $riskCodes = [];
        if (!empty($item->risk_code)) {
            $riskCodeIds = explode(',', $item->risk_code);
            $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                ->get(['id', 'name'])
                ->map(function ($riskCode) {
                    return [
                        'id' => $riskCode->id,
                        'name' => clean_string($riskCode->name)
                    ];
                })
                ->toArray();
        }

        $monthlyDataMap = [];
        $monthly = [];

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);

            if ($dataBulanan) {
                $target = $dataBulanan->target_quantitative;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = $calculatePercentage($target, $realization);

                $monthlyDataMap[$i] = $dataBulanan;

                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'target_quantitative' => $formatTargetQuantitative($target),
                    'realization_percentage' => $percentage !== null ? $percentage . '%' : '-',
                    'is_finalized' => (bool) $dataBulanan->is_finalize,
                    'uploads' => $dataBulanan->uploads->map(function ($upload) {
                        return [
                            'id' => $upload->id,
                            'filepath' => $upload->filepath,
                            'domain' => $upload->domain,
                        ];
                    }),
                ];
            } else {
                $monthlyDataMap[$i] = null;
                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => null,
                    'residual_risk_posisi_risiko_color' => null,
                    'target_quantitative' => null,
                    'realization_percentage' => '-',
                    'is_finalized' => false,
                    'uploads' => [],
                ];
            }
        }

        $headerUploads = $item->uploads->map(function ($upload) {
            return [
                'id' => $upload->id,
                'filepath' => $upload->filepath,
                'domain' => $upload->domain,
            ];
        });

        $entryData = $item->headerEntry->map(function ($entry) use ($calculatePercentage) {
            $monthlyEntries = collect();
            for ($i = 1; $i <= 12; $i++) {
                $monthlyEntry = $entry->monthlyEntryData->firstWhere('month', $i);
                if ($monthlyEntry) {
                    $target = $monthlyEntry->target_quantitative;
                    $realization = $monthlyEntry->realization_quantitative ?? 0;
                    $percentage = $calculatePercentage($target, $realization);

                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'residual_risk_level' => $monthlyEntry->residual_risk_level_risiko,
                        'residual_risk_posisi_risiko_color' => get_color_by_position($monthlyEntry->residual_risk_posisi_risiko),
                        'realization_percentage' => $percentage !== null ? $percentage . '%' : '-',
                        'is_finalized' => (bool) $monthlyEntry->is_finalize,
                        'monthly_entry_data' => $monthlyEntry,
                        'uploads' => $monthlyEntry->uploads->map(function ($upload) {
                            return [
                                'id' => $upload->id,
                                'filepath' => $upload->filepath,
                                'domain' => $upload->domain,
                            ];
                        }),
                    ];
                } else {
                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'residual_risk_level' => null,
                        'residual_risk_posisi_risiko_color' => null,
                        'realization_percentage' => '-',
                        'is_finalized' => false,
                        'monthly_entry_data' => null,
                        'uploads' => [],
                    ];
                }
            }

            $entryArray = [
                'id' => $entry->id,
                'monthly_entry' => $monthlyEntries,
            ];

            if ($entry->header_id !== null) $entryArray['header_id'] = $entry->header_id;
            if ($entry->judul_entry !== null) $entryArray['judul_entry'] = $entry->judul_entry;
            if ($entry->keterangan !== null) $entryArray['keterangan'] = $entry->keterangan;

            return $entryArray;
        });

        return [
            'id' => $item->id,
            'risk_code' => $riskCodes, // PERBAIKAN: Gunakan array yang sudah diproses
            'process_code' => $item->process_code ?? '',
            'jenis_risiko' => $item->jenis_risiko ?? '',
            'sasaran' => $item->sasaran ?? '',
            'peristiwa_risiko' => $item->peristiwa_risiko ?? '',
            'penyebab_risiko' => $item->penyebab_risiko ?? '',
            'dampak_risiko' => $item->dampak_risiko ?? '',
            'inherent_risk_level_dampak' => $item->inherent_risk_level_dampak ?? 0,
            'inherent_risk_level_kemungkinan' => $item->inherent_risk_level_kemungkinan ?? 0,
            'inherent_risk_posisi_risiko' => $item->inherent_risk_posisi_risiko ?? '',
            'inherent_risk_level_risiko' => $item->inherent_risk_level_risiko ?? 0,
            'inherent_risk_posisi_risiko_color' => $inherentColor,
            'internal_control' => $item->internal_control ?? '',
            'mitigasi' => $item->mitigasi ?? '',

            'target_satu_tahun_option' => $item->target_satu_tahun_option ?? null,
            'target_satu_tahun_option_name' => $item->optionTargetSatuTahun->name ?? '',
            'target_satu_tahun_notes' => $item->target_satu_tahun_notes ?? '',
            'target_satu_tahun_position' => $item->optionTargetSatuTahun->position ?? 0,
            // 'target_quantitative_satu_tahun' => number_format($item->target_quantitative_satu_tahun, 0, ',', '.'),
            'target_quantitative_satu_tahun' => format_target_quantitative($item->target_quantitative_satu_tahun),

            'biaya_perlakuan_risiko' => number_format($item->biaya_perlakuan_risiko, 0, ',', '.'),
            'residual_target_level_dampak' => $item->residual_target_level_dampak ?? 0,
            'residual_target_level_kemungkinan' => $item->residual_target_level_kemungkinan ?? 0,
            'residual_target_posisi_risiko' => $item->residual_target_posisi_risiko ?? '',
            'residual_target_level_risiko' => $item->residual_target_level_risiko ?? 0,
            'residual_target_posisi_risiko_color' => $residualTargetColor,

            'department_id' => $item->department_id,
            'year' => $item->year,
            'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
            'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,

            'created_by' => $item->created_by ?? null,
            'created_by_name' => get_decrypted_name($item->createdBy),
            'updated_by' => $item->updated_by ?? null,
            'updated_by_name' => get_decrypted_name($item->updatedBy),

            'ir_dampak' => $item->irDampak ?? null,
            'ir_kemungkinan' => $item->irKemungkinan ?? null,
            'rr_dampak' => $item->rrDampak ?? null,
            'rr_kemungkinan' => $item->rrKemungkinan ?? null,
            'department' => $item->department ?? null,

            'monthly_data' => $item->monthlyData->map(function ($dataBulanan) use ($riskCodes, $calculatePercentage, $formatTargetQuantitative) {
                $target = $dataBulanan->target_quantitative;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = $calculatePercentage($target, $realization);

                return [
                    'id' => $dataBulanan->id,
                    'header_id' => $dataBulanan->header_id,
                    'month' => $dataBulanan->month,
                    'risk_code' => $riskCodes, // PERBAIKAN: Gunakan array risk codes dari header
                    'status_risiko' => $dataBulanan->status_risiko,
                    'process_code' => $dataBulanan->process_code,
                    'start_date' => $dataBulanan->start_date ? $dataBulanan->start_date->format('Y-m-d H:i:s') : null,
                    'expired_date' => $dataBulanan->expired_date ? $dataBulanan->expired_date->format('Y-m-d H:i:s') : null,
                    'realization_quantitative' => $realization,
                    'realization_note' => $dataBulanan->realization_note,
                    'target_quantitative' => $formatTargetQuantitative($target), // PERBAIKAN: Format target sesuai jenis data
                    'target_notes' => $dataBulanan->target_notes,
                    'residual_risk_level_dampak' => $dataBulanan->residual_risk_level_dampak,
                    'residual_risk_level_kemungkinan' => $dataBulanan->residual_risk_level_kemungkinan,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_level_risiko' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_level_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $percentage !== null ? $percentage . '%' : '-',
                    'is_finalize' => (bool) $dataBulanan->is_finalize,
                    'finalized_at' => $dataBulanan->finalized_at,
                    'finalized_by' => $dataBulanan->finalized_by,
                    'created_at' => $dataBulanan->created_at ? $dataBulanan->created_at->toISOString() : null,
                    'updated_at' => $dataBulanan->updated_at ? $dataBulanan->updated_at->toISOString() : null,
                    'uploads' => $dataBulanan->uploads->map(function ($upload) {
                        return [
                            'id' => $upload->id,
                            'filepath' => $upload->filepath,
                            'domain' => $upload->domain,
                        ];
                    }),
                ];
            }),
            'monthly' => $monthly,

            'uploads' => $headerUploads,
            'entry_data' => $entryData,
        ];
    });

    // Clean data
    $cleanData = clean_recursive([
        'current_page' => $data->currentPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
        'last_page' => $data->lastPage(),
        'from' => $data->firstItem(),
        'to' => $data->lastItem(),
        'data' => $orderedData,
    ]);

    return json(200, true, 'Data Monitoring Risk Profile Bulanan', 'Data Monitoring', $cleanData);
}

public function getPendingApproval(Request $request)
{
    try {
        $user = auth()->user();

        \Log::info('User info for pending approval', [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
        ]);

        // Validasi department untuk role 2 dan 3
        if (in_array($user->role_id, [2, 3]) && empty($user->department_id)) {
            return json(403, false, 'Akses Ditolak', 'Department tidak valid untuk akses ini.', null);
        }

        // Query tanpa filter status
        $query = TrRiskHeader::with([
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionTargetSatuTahun:id,name,position',
            'createdBy:id,username',
        ]);

        // Filter department berdasarkan role
        $query->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
            $query->where('department_id', $user->department_id);
        });

        // Validasi parameter department_id untuk role 2 dan 3
        if ($request->has('department_id') && $request->department_id) {
            if (in_array($user->role_id, [2, 3])) {
                if ((int)$request->department_id !== (int)$user->department_id) {
                    return json(403, false, 'Akses Ditolak', 'Anda hanya dapat melihat data dari department Anda sendiri.', null);
                }
            }
            $query->where('department_id', $request->department_id);
        }

        \Log::info('Pending approval query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        $pendingData = $query->orderBy('created_at', 'desc')->get();

        $responseData = $pendingData->map(function ($riskHeader) {
            $createdByName = 'Unknown User';
            try {
                $createdByName = get_decrypted_name($riskHeader->createdBy);
            } catch (\Throwable $e) {
                \Log::warning("Error handling createdBy: {$e->getMessage()}");
            }

            $riskCodes = [];
            if (!empty($riskHeader->risk_code)) {
                $riskCodeIds = explode(',', $riskHeader->risk_code);
                $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                    ->get(['id', 'name'])
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => clean_string($item->name)
                        ];
                    })
                    ->toArray();
            }

            return [
                'id' => $riskHeader->id,
                'department_id' => $riskHeader->department_id,
                'department_name' => $riskHeader->department ? clean_string($riskHeader->department->name) : null,
                'year' => $riskHeader->year,
                'risk_code' => $riskHeader->risk_code ? explode(',', $riskHeader->risk_code) : [],
                'risk_codes' => $riskCodes,
                'jenis_risiko' => clean_string($riskHeader->jenis_risiko),
                'sasaran' => clean_string($riskHeader->sasaran),
                'peristiwa_risiko' => clean_string($riskHeader->peristiwa_risiko),
                'penyebab_risiko' => clean_string($riskHeader->penyebab_risiko),
                'dampak_risiko' => clean_string($riskHeader->dampak_risiko),
                'internal_control' => clean_string($riskHeader->internal_control),
                'mitigasi' => clean_string($riskHeader->mitigasi),
                'inherent_risk_level_risiko' => clean_string($riskHeader->inherent_risk_level_risiko),
                'residual_target_level_risiko' => clean_string($riskHeader->residual_target_level_risiko),
                'department' => $riskHeader->department ? [
                    'id' => $riskHeader->department->id,
                    'name' => clean_string($riskHeader->department->name)
                ] : null,
                'risk_status' => $riskHeader->status,
                'desc' => clean_string($riskHeader->desc),
                'created_at' => $riskHeader->created_at,
                'created_by_name' => $createdByName,
                'target_quantitative_satu_tahun' => format_target_quantitative($riskHeader->target_quantitative_satu_tahun),
                'biaya_perlakuan_risiko' => number_format($riskHeader->biaya_perlakuan_risiko ?? 0, 0, ',', '.'),
            ];
        });

        return json(200, true, 'Data Berhasil Diambil', 'Data pending approval berhasil diambil.', $responseData);

    } catch (\Exception $e) {
        return json(500, false, 'Gagal Mengambil Data', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function approveRiskHeader(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'approval_notes' => 'nullable|string'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        $riskHeader = TrRiskHeader::with(['createdBy', 'department'])->find($id);

        if (!$riskHeader) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        // UPDATE: Cek status draft (bukan pending)
        if ($riskHeader->status !== 'submit') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status draft yang dapat disetujui.', null);
        }

        $currentUser = auth()->user();

        // Role-based approval logic
        if ($currentUser->role_id == 1) {
            // Role 1: Dapat approve semua tanpa pengecualian
            $hasApprovalRight = true;
        } elseif ($currentUser->role_id == 2) {
            // Role 2: Hanya bisa approve data dari departemen yang sama atau data yang dibuat sendiri
            $hasApprovalRight = ((int)$riskHeader->department_id === (int)$currentUser->department_id) ||
                               ((int)$riskHeader->created_by === (int)$currentUser->id);
        } elseif ($currentUser->role_id == 3) {
            // Role 3: Tidak bisa approve
            $hasApprovalRight = false;
        } elseif ($currentUser->role_id == 4 || $currentUser->role_id == 5) {
            // Role 4 dan 5: Hanya bisa approve data yang dibuat oleh role 3
            $createdByUser = \App\Models\User::find($riskHeader->created_by);
            $hasApprovalRight = $createdByUser && (int)$createdByUser->role_id === 3;
        } else {
            // Role lain tidak diizinkan
            $hasApprovalRight = false;
        }

        if (!$hasApprovalRight) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk menyetujui data ini.', null);
        }

        // UPDATE: Status menjadi approved dan is_complete false
        $riskHeader->update([
            'status' => 'approved',
            'is_complete' => false, // Masih belum complete karena masih ada 4 field yang perlu diisi
            'approval_notes' => $request->approval_notes,
            'approved_by' => auth()->id(),
            'approved_at' => now()
        ]);

        \App\Models\MstApproval::where('document_id', $id)
            ->update([
                'status' => 'approved',
                'tanggal' => now(),
                'note' => $request->approval_notes ?? 'Approved via risk header'
            ]);

        DB::commit();

        $riskHeader->load([
            'createdBy:id,username',
            'approvedBy:id,username'
        ]);

        $approvedByName = 'Unknown User';
        try {
            $approvedByName = get_decrypted_name($riskHeader->approvedBy);
        } catch (\Throwable $e) {
            \Log::warning("Error handling approvedBy: {$e->getMessage()}");
        }

        // UPDATE: Pesan dan field sesuai dengan store logic
        return json(200, true, 'Berhasil Disetujui', '14 field dasar berhasil disetujui dan terkunci. Silakan lengkapi 4 field tambahan untuk menyelesaikan data.', [
            'id' => $riskHeader->id,
            'status' => $riskHeader->status,
            'is_complete' => $riskHeader->is_complete,
            'approval_notes' => clean_string($riskHeader->approval_notes),
            'approved_by' => $riskHeader->approved_by,
            'approved_by_name' => $approvedByName,
            'approved_at' => $riskHeader->approved_at,
            'next_step' => 'Silakan isi 4 field tambahan melalui proses update',
            'fields_to_complete' => [
                'internal_control',
                'target_quantitative_satu_tahun',
                'target_satu_tahun_option',
                'target_satu_tahun_notes'
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Menyetujui', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function rejectRiskHeader(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'approval_notes' => 'required|string'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Catatan penolakan wajib diisi.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        $riskHeader = TrRiskHeader::with(['createdBy', 'department'])->find($id);

        if (!$riskHeader) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        // UPDATE: Cek status submit (bukan pending)
        if ($riskHeader->status !== 'submit') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status draft yang dapat ditolak.', null);
        }

        $currentUser = auth()->user();

        // Role-based rejection logic (sama dengan approval)
        if ($currentUser->role_id == 1) {
            // Role 1: Dapat reject semua tanpa pengecualian
            $hasRejectRight = true;
        } elseif ($currentUser->role_id == 2) {
            // Role 2: Hanya bisa reject data dari departemen yang sama atau data yang dibuat sendiri
            $hasRejectRight = ((int)$riskHeader->department_id === (int)$currentUser->department_id) ||
                             ((int)$riskHeader->created_by === (int)$currentUser->id);
        } elseif ($currentUser->role_id == 3) {
            // Role 3: Tidak bisa reject
            $hasRejectRight = false;
        } elseif ($currentUser->role_id == 4 || $currentUser->role_id == 5) {
            // Role 4 dan 5: Hanya bisa reject data yang dibuat oleh role 3
            $createdByUser = \App\Models\User::find($riskHeader->created_by);
            $hasRejectRight = $createdByUser && (int)$createdByUser->role_id === 3;
        } else {
            // Role lain tidak diizinkan
            $hasRejectRight = false;
        }

        if (!$hasRejectRight) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk menolak data ini.', null);
        }

        $riskHeader->update([
            'status' => 'rejected',
            'is_complete' => false,
            'approval_notes' => $request->approval_notes,
            'approved_by' => auth()->id(),
            'approved_at' => now()
        ]);

        \App\Models\MstApproval::where('document_id', $id)
            ->update([
                'status' => 'rejected',
                'tanggal' => now(),
                'note' => $request->approval_notes
            ]);

        // UPDATE: Clear hanya 4 field tambahan yang memang tidak boleh diisi di store
        $riskHeader->update([
            'internal_control' => null,
            'target_quantitative_satu_tahun' => null,
            'target_satu_tahun_option' => null,
            'target_satu_tahun_notes' => null,
            'target_satu_tahun_position' => null
        ]);

        // Hapus monthly data jika ada
        TrRiskMonthly::where('header_id', $riskHeader->id)->delete();

        DB::commit();

        $riskHeader->load('approvedBy:id,username');

        $rejectedByName = 'Unknown User';
        try {
            $rejectedByName = get_decrypted_name($riskHeader->approvedBy);
        } catch (\Throwable $e) {
            \Log::warning("Error handling approvedBy: {$e->getMessage()}");
        }

        // UPDATE: Pesan dan field list sesuai dengan store logic
        return json(200, true, 'Berhasil Ditolak', 'Risk header berhasil ditolak. Silakan perbaiki 14 field dasar sesuai catatan penolakan.', [
            'id' => $riskHeader->id,
            'status' => $riskHeader->status,
            'is_complete' => $riskHeader->is_complete,
            'rejection_notes' => clean_string($riskHeader->approval_notes),
            'rejected_by' => $riskHeader->approved_by,
            'rejected_by_name' => $rejectedByName,
            'rejected_at' => $riskHeader->approved_at,
            'next_step' => 'Perbaiki 14 field dasar dan submit ulang untuk review',
            'editable_fields' => [
                'department_id', 'risk_code', 'jenis_risiko', 'year', 'sasaran', 'peristiwa_risiko', 'penyebab_risiko',
                'dampak_risiko', 'inherent_risk_level_dampak', 'inherent_risk_level_kemungkinan',
                'residual_target_level_dampak', 'residual_target_level_kemungkinan', 'mitigasi', 'biaya_perlakuan_risiko'
            ],
            'locked_fields' => [
                'internal_control', 'target_quantitative_satu_tahun',
                'target_satu_tahun_option', 'target_satu_tahun_notes'
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Menolak', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function getRejectedData(Request $request)
{
    try {
        $user = auth()->user();

        // Validasi department hanya untuk role 2 dan 3
        if (($user->role_id == 2 || $user->role_id == 3) && empty($user->department_id)) {
            return json(403, false, 'Akses Ditolak', 'Department tidak valid untuk akses ini.', null);
        }

        $query = TrRiskHeader::with([
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionTargetSatuTahun:id,name,position',
            'createdBy:id,username',
            'approvedBy:id,username'
        ])->where('status', 'rejected'); // Hanya ambil yang statusnya 'rejected'

        // Filter berdasarkan role
        if ($user->role_id == 2 || $user->role_id == 3) {
            // Role 2 dan 3: Hanya bisa melihat data dari department mereka sendiri
            $query->where('department_id', $user->department_id);
        }
        // Role 1, 4, 5: Bisa melihat semua data tanpa filter department

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        // Handle parameter department_id
        if ($request->has('department_id') && $request->department_id) {
            if ($user->role_id == 2 || $user->role_id == 3) {
                // Role 2 dan 3: Validasi bahwa department_id yang diminta sama dengan department mereka
                if ((int)$request->department_id !== (int)$user->department_id) {
                    return json(403, false, 'Akses Ditolak', 'Anda hanya dapat melihat data dari department Anda sendiri.', null);
                }
            }
            // Role 1, 4, 5: Bisa filter berdasarkan department_id apapun
            $query->where('department_id', $request->department_id);
        }

        $rejectedData = $query->orderBy('approved_at', 'desc')->get();

        $responseData = $rejectedData->map(function ($riskHeader) {
            $createdByName = 'Unknown User';
            $approvedByName = 'Unknown User';

            try {
                $createdByName = get_decrypted_name($riskHeader->createdBy);
            } catch (\Throwable $e) {
                \Log::warning("Error handling createdBy: {$e->getMessage()}");
            }

            try {
                $approvedByName = get_decrypted_name($riskHeader->approvedBy);
            } catch (\Throwable $e) {
                \Log::warning("Error handling approvedBy: {$e->getMessage()}");
            }

            $riskCodes = [];
            if (!empty($riskHeader->risk_code)) {
                $riskCodeIds = explode(',', $riskHeader->risk_code);
                $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                    ->get(['id', 'name'])
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => clean_string($item->name)
                        ];
                    })
                    ->toArray();
            }

            return [
                'id' => $riskHeader->id,
                'year' => $riskHeader->year,
                'department_id' => $riskHeader->department_id,
                'department_name' => $riskHeader->department ? clean_string($riskHeader->department->name) : null,
                'risk_code' => $riskHeader->risk_code ? explode(',', $riskHeader->risk_code) : [],
                'risk_codes' => $riskCodes,
                'jenis_risiko' => clean_string($riskHeader->jenis_risiko),
                'sasaran' => clean_string($riskHeader->sasaran),
                'peristiwa_risiko' => clean_string($riskHeader->peristiwa_risiko),
                'penyebab_risiko' => clean_string($riskHeader->penyebab_risiko),
                'dampak_risiko' => clean_string($riskHeader->dampak_risiko),
                'internal_control' => clean_string($riskHeader->internal_control),
                'mitigasi' => clean_string($riskHeader->mitigasi),
                'inherent_risk_level_risiko' => clean_string($riskHeader->inherent_risk_level_risiko),
                'residual_target_level_risiko' => clean_string($riskHeader->residual_target_level_risiko),
                'department' => $riskHeader->department ? [
                    'id' => $riskHeader->department->id,
                    'name' => clean_string($riskHeader->department->name)
                ] : null,
                'risk_status' => $riskHeader->status,
                'approval_notes' => clean_string($riskHeader->approval_notes),
                'rejected_by' => $riskHeader->approved_by,
                'rejected_by_name' => $approvedByName,
                'rejected_at' => $riskHeader->approved_at,
                'created_at' => $riskHeader->created_at,
                'created_by_name' => $createdByName,
                'target_quantitative_satu_tahun' => format_target_quantitative($riskHeader->target_quantitative_satu_tahun),
                'biaya_perlakuan_risiko' => number_format($riskHeader->biaya_perlakuan_risiko ?? 0, 0, ',', '.'),
            ];
        });

        return json(200, true, 'Data Berhasil Diambil', 'Data yang ditolak berhasil diambil.', $responseData);

    } catch (\Exception $e) {
        return json(500, false, 'Gagal Mengambil Data', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

/**
 * Helper function untuk menentukan apakah request ini adalah penyimpanan lengkap
 */
private function isFullSaveRequest($request)
{
    $requiredFieldsForComplete = [
        'internal_control', 'mitigasi', 'target_satu_tahun_option',
        'target_satu_tahun_notes', 'target_quantitative_satu_tahun',
        'biaya_perlakuan_risiko', 'year'
    ];

    foreach ($requiredFieldsForComplete as $field) {
        if (!$request->filled($field)) {
            return false;
        }
    }

    return true;
}

/**
 * Helper function untuk menambahkan relasi ke response data
 */
private function addRelationsToResponse($riskHeader, &$responseData)
{
    $responseData['ir_dampak'] = $riskHeader->irDampak ? [
        'id' => $riskHeader->irDampak->id,
        'label' => clean_string($riskHeader->irDampak->label)
    ] : null;

    $responseData['ir_kemungkinan'] = $riskHeader->irKemungkinan ? [
        'id' => $riskHeader->irKemungkinan->id,
        'label' => clean_string($riskHeader->irKemungkinan->label)
    ] : null;

    $responseData['rr_dampak'] = $riskHeader->rrDampak ? [
        'id' => $riskHeader->rrDampak->id,
        'label' => clean_string($riskHeader->rrDampak->label)
    ] : null;

    $responseData['rr_kemungkinan'] = $riskHeader->rrKemungkinan ? [
        'id' => $riskHeader->rrKemungkinan->id,
        'label' => clean_string($riskHeader->rrKemungkinan->label)
    ] : null;

    $responseData['department'] = $riskHeader->department ? [
        'id' => $riskHeader->department->id,
        'name' => clean_string($riskHeader->department->name)
    ] : null;

    $responseData['option_target_satu_tahun'] = $riskHeader->optionTargetSatuTahun ? [
        'id' => $riskHeader->optionTargetSatuTahun->id,
        'name' => clean_string($riskHeader->optionTargetSatuTahun->name),
        'position' => clean_string($riskHeader->optionTargetSatuTahun->position)
    ] : null;

    $riskCodes = [];
    if (!empty($riskHeader->risk_code)) {
        $riskCodeIds = explode(',', $riskHeader->risk_code);
        $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
            ->get(['id', 'name'])
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => clean_string($item->name)
                ];
            })
            ->toArray();
    }
    $responseData['risk_codes'] = $riskCodes;

    if ($riskHeader->optionTargetSatuTahun) {
        $responseData['target_satu_tahun_option_name'] = clean_string($riskHeader->optionTargetSatuTahun->name);
    }

    $responseData['monthly_data'] = [];
}

public function destroy($id)
{
    try {
        DB::beginTransaction();

        $riskHeader = TrRiskHeader::find($id);

        if (!$riskHeader) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        $currentUser = auth()->user();

        // VALIDASI HAK AKSES DELETE - Hanya role 1 yang bisa hapus
        $roleCheck = check_role($currentUser, 1);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        // VALIDASI BUSINESS LOGIC
        // Cek apakah data sudah complete dan memiliki monthly data
        $hasMonthlyData = TrRiskMonthly::where('header_id', $riskHeader->id)->exists();

        if ($hasMonthlyData) {
            return json(400, false, 'Tidak Dapat Dihapus', 'Risk header ini sudah memiliki data monitoring bulanan dan tidak dapat dihapus. Gunakan fitur archive jika diperlukan.', null);
        }

        // OPTIONAL: Cek apakah sudah approved dan complete
        if ($riskHeader->status === 'approved' && $riskHeader->is_complete) {
            return json(400, false, 'Tidak Dapat Dihapus', 'Risk header yang sudah approved dan lengkap tidak dapat dihapus. Gunakan fitur archive jika diperlukan.', null);
        }

        // PROSES DELETE
        // 1. Hapus approval data terkait
        \App\Models\MstApproval::where('document_id', $riskHeader->id)->delete();

        // 2. Hapus monthly data jika ada (safety measure)
        TrRiskMonthly::where('header_id', $riskHeader->id)->delete();

        // 3. Simpan data untuk log sebelum dihapus
        $deletedData = [
            'id' => $riskHeader->id,
            'risk_code' => $riskHeader->risk_code,
            'jenis_risiko' => $riskHeader->jenis_risiko,
            'sasaran' => $riskHeader->sasaran,
            'department_id' => $riskHeader->department_id,
            'status' => $riskHeader->status,
            'is_complete' => $riskHeader->is_complete,
            'created_by' => $riskHeader->created_by,
            'created_at' => $riskHeader->created_at,
            'deleted_by' => $currentUser->id,
            'deleted_at' => now()
        ];

        // 4. Hapus risk header
        $riskHeader->delete();

        // 5. Log aktivitas delete (opsional)
        \Log::info('Risk Header Deleted', [
            'deleted_data' => $deletedData,
            'deleted_by_user' => $currentUser->id,
            'deleted_by_username' => $currentUser->username ?? 'Unknown'
        ]);

        DB::commit();

        return json(200, true, 'Berhasil Dihapus', 'Risk header berhasil dihapus dari sistem.', [
            'deleted_id' => $deletedData['id'],
            'deleted_risk_code' => $deletedData['risk_code'],
            'deleted_jenis_risiko' => clean_string($deletedData['jenis_risiko']),
            'deleted_at' => $deletedData['deleted_at'],
            'deleted_by' => $deletedData['deleted_by']
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        \Log::error('Error deleting risk header', [
            'risk_header_id' => $id,
            'user_id' => auth()->id(),
            'error' => $e->getMessage()
        ]);

        return json(500, false, 'Gagal Menghapus', 'Terjadi kesalahan sistem saat menghapus data.', $e->getMessage());
    }
}

public function getTaskRealisasiMonitoring()
{
    try {
        $user = \Auth::user();

        $query = TrRiskHeader::with([
            'department:id,name',
            'monthlyData' => function ($q) {
                $q->where('is_finalize', true);
            }
        ]);

        // Role 2 dan 3 hanya bisa melihat data department mereka
        if (in_array($user->role_id, [2, 3])) {
            $query->where('department_id', $user->department_id);
        }
        // Role 1,4,5 bisa melihat semua data (tidak ada filter)

        $headers = $query->get();

        $result = [];
        $no = 1;

        foreach ($headers as $header) {
            // Risk Owner
            $riskOwner = $header->department->name ?? '-';

            // Peristiwa Risiko
            $peristiwa = $header->peristiwa_risiko ?? '-';

            // Rencana Penanganan
            $rencana = $header->mitigasi ?? '-';

            // Waktu Pelaksanaan → ambil bulan awal & akhir finalisasi
            $finalizedMonths = $header->monthlyData->pluck('month')->toArray();
            $waktuPelaksanaan = '-';

            if (!empty($finalizedMonths)) {
                $startMonth = min($finalizedMonths);
                $endMonth   = max($finalizedMonths);

                $startDate = \Carbon\Carbon::createFromDate($header->year, $startMonth, 1)->startOfMonth();
                $endDate   = \Carbon\Carbon::createFromDate($header->year, $endMonth, 1)->endOfMonth();

                $waktuPelaksanaan = $startDate->format('Y-m-d') . ' s/d ' . $endDate->format('Y-m-d');
            }

            //  PIC
            $pic = get_decrypted_name((object)['id' => $header->created_by]) .
                ' - ' . ($header->department->name ?? '');

            //  Ambil target angka dari kolom target_quantitative_satu_tahun (tipe TEXT)
            $targetText = $header->target_quantitative_satu_tahun ?? '';
            preg_match('/\d+/', str_replace('.', '', $targetText), $matches);
            $targetValue = isset($matches[0]) ? (float)$matches[0] : 0;

            //  Hitung Total Realisasi
            $totalRealisasi = $header->monthlyData->sum(function ($m) {
                return (float) str_replace(',', '', $m->realization_quantitative ?? 0);
            });

            //  Hitung Persentase Realisasi
            $realisasi = $targetValue > 0 ? round(($totalRealisasi / $targetValue) * 100, 2) : 0;

            $result[] = [
                'no' => $no++,
                'risk_owner' => $riskOwner,
                'peristiwa_risiko' => $peristiwa,
                'rencana_penanganan' => $rencana,
                'waktu_pelaksanaan' => $waktuPelaksanaan,
                'pic' => $pic,
                'realisasi' => $realisasi . '%',
            ];
        }

        return json(200, true, 'Task Realisasi Monitoring Mitigasi berhasil diambil', null, $result);

    } catch (\Exception $e) {
        return json(500, false, 'Terjadi kesalahan', $e->getMessage(), null);
    }
}

}
