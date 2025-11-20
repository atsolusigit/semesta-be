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
use Illuminate\Support\Facades\Auth;

class TrRiskHeaderController extends Controller
{

// TAMBAHKAN helper function di awal method index (sebelum query)
public function index(Request $request)
{
    $user = auth()->user();

    $perPage = $request->input('per_page', 10);

    // PERBAIKAN: Helper function untuk realization percentage
    $getRealizationPercentage = function ($target, $realization, $realizationKualitatif) {
        // Jika target dan realization adalah numeric, hitung percentage
        if (is_numeric($target) && is_numeric($realization) && (float)$target > 0) {
            $percentage = round(((float)$realization / (float)$target) * 100, 2);
            return $percentage . '%';
        }

        // Jika tidak bisa hitung percentage tapi ada realization_kualitatif, tampilkan kualitatif
        if (!empty($realizationKualitatif)) {
            return $realizationKualitatif;
        }

        // Jika keduanya tidak ada, return '0%'
        return '0%';
    };

    $query = TrRiskHeader::with([
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position,type',
        'jenisRisiko:id,nama_jenis_risiko',
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
        'approval:document_id,type_document,status,tahun,jabatan_id'
    ])
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
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
    ->when($request->jenis_risiko, function ($query) use ($request) {
        if (is_numeric($request->jenis_risiko)) {
            $query->where('jenis_risiko', $request->jenis_risiko);
        } else {
            $query->whereHas('jenisRisiko', function ($q) use ($request) {
                $q->where('nama_jenis_risiko', 'like', '%' . $request->jenis_risiko . '%');
            });
        }
    })
    ->when($request->tahun, function ($query) use ($request) {
        $query->where('year', $request->tahun);
    })
    ->orderBy('id', 'desc');

    $data = $query->paginate($perPage);

    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $orderedData = collect($data->items())->map(function ($item) use ($monthNames, $getRealizationPercentage) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        $monthlyDataMap = [];
        $monthly = [];
        $rekomendasi = [];

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);

            if ($dataBulanan) {
                $target = $dataBulanan->target_quantitative;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $realizationKualitatif = $dataBulanan->realization_kualitatif ?? null;

                $monthlyDataMap[$i] = $dataBulanan;

                $monthly[] = [
                    'bulan' => $i,
                    'month_name' => $monthNames[$i] ?? 'Unknown',
                    'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $item->year,
                    'target_satu_tahun_option' => $item->target_satu_tahun_option ?? null,
                    'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $getRealizationPercentage($target, $realization, $realizationKualitatif), // PERBAIKAN
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
                    'target_satu_tahun_option' => $item->target_satu_tahun_option ?? null,
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

        $entryData = $item->headerEntry->map(function ($entry) use ($monthNames, $item, $getRealizationPercentage) {
            $monthlyEntries = collect();
            for ($i = 1; $i <= 12; $i++) {
                $monthlyEntry = $entry->monthlyEntryData->firstWhere('month', $i);
                if ($monthlyEntry) {
                    $target = $monthlyEntry->target_quantitative;
                    $realization = $monthlyEntry->realization_quantitative ?? 0;
                    $realizationKualitatif = $monthlyEntry->realization_kualitatif ?? null;

                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'month_name' => $monthNames[$i] ?? 'Unknown',
                        'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $item->year,
                        'residual_risk_level' => $monthlyEntry->residual_risk_level_risiko,
                        'residual_risk_posisi_risiko' => $monthlyEntry->residual_risk_posisi_risiko,
                        'residual_risk_posisi_risiko_color' => get_color_by_position($monthlyEntry->residual_risk_posisi_risiko),
                        'realization_percentage' => $getRealizationPercentage($target, $realization, $realizationKualitatif), // PERBAIKAN
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

        $riskCodes = [];
        if (!empty($item->risk_code)) {
            $riskCodeIds = explode(',', $item->risk_code);
            $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                ->get(['id', 'name', 'code'])
                ->map(function ($riskCode) {
                    return [
                        'id' => $riskCode->id,
                        'name' => clean_string($riskCode->name),
                        'code' => $riskCode->code ?? null,
                    ];
                })
                ->toArray();
        }

        $biayaPerlakuan = is_numeric($item->biaya_perlakuan_risiko) ? $item->biaya_perlakuan_risiko : 0;

        $isHeaderComplete = !empty($item->peristiwa_risiko) &&
                           !empty($item->penyebab_risiko) &&
                           !empty($item->dampak_risiko) &&
                           !empty($item->mitigasi) &&
                           !empty($item->internal_control);

        $allMonthsFinalized = true;
        $totalMonths = $item->monthlyData->count();

        if ($totalMonths < 12) {
            $allMonthsFinalized = false;
        } else {
            for ($i = 1; $i <= 12; $i++) {
                $dataBulanan = $item->monthlyData->firstWhere('month', $i);
                if (!$dataBulanan || !$dataBulanan->is_finalize) {
                    $allMonthsFinalized = false;
                    break;
                }
            }
        }

        $overrideStatus = $item->status;

        if ($item->menrisk_by !== null && $item->status === 'approved') {
            $overrideStatus = 'final';
        } elseif ($item->status === 'rejected') {
            $overrideStatus = 'rejected';
        }

        $riskStatus = $overrideStatus;

        switch (true) {
            case (in_array($overrideStatus, ['approved', 'final']) && !$isHeaderComplete):
                $riskStatus = 'pending';
                break;

            case (in_array($overrideStatus, ['approved', 'final']) && $isHeaderComplete && !$allMonthsFinalized):
                $riskStatus = 'open';
                break;

            case (in_array($overrideStatus, ['approved', 'final']) && $isHeaderComplete && $allMonthsFinalized):
                $riskStatus = 'close';
                break;

            default:
                $riskStatus = $overrideStatus;
                break;
        }

        return [
            'id' => $item->id,
            'rcsa_id' => $item->rcsa_id,
            'risk_status' => $riskStatus,
            'override_status' => $overrideStatus,
            'reviewed' => (bool) $item->reviewed,
            'reviewed_by' => $item->reviewed_by,
            'reviewed_at' => $item->reviewed_at,
            'type_document' => $item->approval->type_document ?? null,
            'department_id' => $item->department_id,
            'department_name' => $item->department->name ?? '',
            'is_header_complete' => $isHeaderComplete,
            'all_months_finalized' => $allMonthsFinalized,
            'risk_code' => $riskCodes,
            'process_code' => $item->process_code ?? '',
            'jenis_risiko_id' => $item->jenis_risiko ?? null,
            'jenis_risiko' => $item->jenisRisiko->nama_jenis_risiko ?? '',
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
            'target_quantitative_satu_tahun' => $item->target_quantitative_satu_tahun ?? '',

            'biaya_perlakuan_risiko' => number_format($biayaPerlakuan, 2, ',', '.'),
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
            'menrisk_note' => $item->menrisk_note ?? '',
            'spv_note' => $item->approval_notes ?? '',
            'vp_menrisk_note' => clean_string($item->vp_menrisk_note ?? null),

            'rekomendasi' => $rekomendasi,

            'monthly_data' => $item->monthlyData->map(function ($dataBulanan) use ($monthNames, $item, $getRealizationPercentage) {
                $target = $dataBulanan->target_quantitative;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $realizationKualitatif = $dataBulanan->realization_kualitatif ?? null;

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
                    'realization_quantitative' => $dataBulanan->realization_quantitative,
                    'realization_kualitatif' => $dataBulanan->realization_kualitatif,
                    'realization_note' => $dataBulanan->realization_note,
                    'penjelasan_realisasi' => $dataBulanan->penjelasan_realisasi,
                    'target_quantitative' => $dataBulanan->target_quantitative,
                    'target_kualitatif' => $dataBulanan->target_kualitatif,
                    'target_notes' => $dataBulanan->target_notes,
                    'target_satu_tahun_option' => $item->target_satu_tahun_option ?? null,
                    'residual_risk_level_dampak' => $dataBulanan->residual_risk_level_dampak,
                    'residual_risk_level_kemungkinan' => $dataBulanan->residual_risk_level_kemungkinan,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_level_risiko' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_level_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $getRealizationPercentage($target, $realization, $realizationKualitatif), // PERBAIKAN
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

// METHOD SHOW - TAMBAHKAN helper function dan perbaikan
public function show($id)
{
    $user = auth()->user();

    // PERBAIKAN: Helper function untuk realization percentage
    $getRealizationPercentage = function ($target, $realization, $realizationKualitatif) {
        // Jika target dan realization adalah numeric, hitung percentage
        if (is_numeric($target) && is_numeric($realization) && (float)$target > 0) {
            $percentage = round(((float)$realization / (float)$target) * 100, 2);
            return $percentage . '%';
        }

        // Jika tidak bisa hitung percentage tapi ada realization_kualitatif, tampilkan kualitatif
        if (!empty($realizationKualitatif)) {
            return $realizationKualitatif;
        }

        // Jika keduanya tidak ada, return '0%'
        return '0%';
    };

    $query = TrRiskHeader::with([
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position,type',
        'jenisRisiko:id,nama_jenis_risiko',
        'createdBy',
        'updatedBy',
        'monthlyData' => function($query) {
            $query->orderBy('month', 'asc')->with(['uploads', 'createdBy', 'updatedBy']);
        },
        'approval:document_id,type_document,status,tahun,jabatan_id'
    ])
    ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
        $query->where('department_id', $user->department_id);
    });

    $data = $query->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
    }

    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    $inherentColor = get_color_by_position($data->inherent_risk_posisi_risiko);
    $residualTargetColor = get_color_by_position($data->residual_target_posisi_risiko);

    $monthly = [];
    $monthlyData = [];
    $rekomendasi = [];

    for ($i = 1; $i <= 12; $i++) {
        $dataBulanan = $data->monthlyData->firstWhere('month', $i);

        if ($dataBulanan) {
            $target = $dataBulanan->target_quantitative;
            $realization = $dataBulanan->realization_quantitative ?? 0;
            $realizationKualitatif = $dataBulanan->realization_kualitatif ?? null;

            $isEditMonthly = !((bool) $dataBulanan->is_finalize);

            $monthly[] = [
                'bulan' => $i,
                'month_name' => $monthNames[$i] ?? 'Unknown',
                'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $data->year,
                'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                'realization_percentage' => $getRealizationPercentage($target, $realization, $realizationKualitatif),
                'target_satu_tahun_option' => $data->target_satu_tahun_option ?? null,
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
                'penjelasan_realisasi' => $dataBulanan->penjelasan_realisasi,
                'process_code' => $dataBulanan->process_code,
                'start_date' => $dataBulanan->start_date,
                'expired_date' => $dataBulanan->expired_date,
                'realization_quantitative' => $dataBulanan->realization_quantitative,
                'realiztion_kualitatif' => $dataBulanan->realization_kualitatif,
                'realization_note' => $dataBulanan->realization_note,
                'target_quantitative' => $dataBulanan->target_quantitative,
                'target_notes' => $dataBulanan->target_notes,
                'target_satu_tahun_option' => $data->target_satu_tahun_option ?? null,
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
                'is_edit' => true,
                'note_recommendation' => null,
                'monthly_data' => null,
            ];

            $monthlyData[] = [
                'id' => null,
                'header_id' => $data->id,
                'month' => $i,
                'month_name' => $monthNames[$i] ?? 'Unknown',
                'month_full_name' => ($monthNames[$i] ?? 'Unknown') . ' ' . $data->year,
                'penjelasan_realisasi' => null,
                'risk_code' => null,
                'status_risiko' => 'open',
                'process_code' => $data->process_code,
                'start_date' => null,
                'expired_date' => null,
                'realization_quantitative' => null,
                'realiztion_kualitatif' => null,
                'realization_note' => null,
                'target_quantitative' => null,
                'target_notes' => null,
                'target_satu_tahun_option' => $data->target_satu_tahun_option ?? null,
                'residual_risk_level_dampak' => null,
                'residual_risk_level_kemungkinan' => null,
                'residual_risk_posisi_risiko' => null,
                'residual_risk_level_risiko' => null,
                'residual_risk_level_risiko_color' => null,
                'is_finalize' => false,
                'is_edit' => true,
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

    $riskCodes = [];
    if (!empty($data->risk_code)) {
        $riskCodeIds = explode(',', $data->risk_code);
        $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
            ->get(['id', 'name', 'code'])
            ->map(function ($riskCode) {
                return [
                    'id' => $riskCode->id,
                    'name' => clean_string($riskCode->name),
                    'code' => $riskCode->code ?? null,
                ];
            })
            ->toArray();
    }

    $overrideStatus = $data->status;

    if ($data->menrisk_by !== null && $data->status === 'approved') {
        $overrideStatus = 'final';
    } elseif ($data->status === 'rejected') {
        $overrideStatus = 'rejected';
    }

    $riskStatus = $overrideStatus;

    $isHeaderComplete = !empty($data->peristiwa_risiko)
        && !empty($data->penyebab_risiko)
        && !empty($data->dampak_risiko)
        && !empty($data->mitigasi)
        && !empty($data->internal_control);

    $allMonthsFinalized = true;
    $totalMonths = $data->monthlyData->count();

    if ($totalMonths < 12) {
        $allMonthsFinalized = false;
    } else {
        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $data->monthlyData->firstWhere('month', $i);
            if (!$dataBulanan || !$dataBulanan->is_finalize) {
                $allMonthsFinalized = false;
                break;
            }
        }
    }

    switch (true) {
        case (in_array($overrideStatus, ['approved', 'final']) && !$isHeaderComplete):
            $riskStatus = 'pending';
            break;

        case (in_array($overrideStatus, ['approved', 'final']) && $isHeaderComplete && !$allMonthsFinalized):
            $riskStatus = 'open';
            break;

        case (in_array($overrideStatus, ['approved', 'final']) && $isHeaderComplete && $allMonthsFinalized):
            $riskStatus = 'close';
            break;

        default:
            $riskStatus = $overrideStatus;
            break;
    }

    $isEditHeader = !in_array($data->status, ['approved', 'close']);

    $orderedData = [
        'id' => $data->id,
        'rcsa_id' => $data->rcsa_id,
        'risk_status' => $riskStatus,
        'override_status' => $overrideStatus,
        'reviewed' => (bool) $data->reviewed,
        'reviewed_by' => $data->reviewed_by,
        'reviewed_at' => $data->reviewed_at,
        'type_document' => $data->approval->type_document ?? null,
        'department_id' => $data->department_id,
        'department_name' => $data->department->name ?? '',
        'is_header_complete' => $isHeaderComplete,
        'all_months_finalized' => $allMonthsFinalized,
        'risk_code' => $riskCodes,
        'process_code' => $data->process_code ?? '',
        'jenis_risiko_id' => $data->jenis_risiko ?? null,
        'jenis_risiko' => $data->jenisRisiko->nama_jenis_risiko ?? '',
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

        'target_satu_tahun_option' => $data->target_satu_tahun_option ?? null,
        'target_satu_tahun_option_name' => $data->optionTargetSatuTahun->name ?? '',
        'target_satu_tahun_option_type' => $data->optionTargetSatuTahun->type ?? null,
        'target_satu_tahun_notes' => $data->target_satu_tahun_notes ?? '',
        'target_satu_tahun_position' => $data->optionTargetSatuTahun->position ?? 0,
        "target_satu_tahun_type" => $data->optionTargetSatuTahun->type ?? '',
        'target_quantitative_satu_tahun' => $data->target_quantitative_satu_tahun ?? '',

        'biaya_perlakuan_risiko' => number_format($data->biaya_perlakuan_risiko, 2, ',', '.'),
        'mitigasi' => $data->mitigasi ?? '',
        'residual_target_level_dampak' => $data->residual_target_level_dampak ?? 0,
        'residual_target_level_kemungkinan' => $data->residual_target_level_kemungkinan ?? 0,
        'residual_target_posisi_risiko' => $data->residual_target_posisi_risiko ?? '',
        'residual_target_level_risiko' => $data->residual_target_level_risiko ?? 0,
        'residual_target_posisi_risiko_color' => $residualTargetColor,
        'year' => $data->year,
        'is_edit' => $isEditHeader,
        'created_at' => $data->created_at,
        'updated_at' => $data->updated_at,

        'created_by_name' => $data->createdBy ? clean_string(get_decrypted_name($data->createdBy)) : 'Unknown User',
        'updated_by_name' => $data->updatedBy ? clean_string(get_decrypted_name($data->updatedBy)) : 'Unknown User',

        'rekomendasi' => $rekomendasi,

        'ir_dampak' => $data->irDampak ?? null,
        'ir_kemungkinan' => $data->irKemungkinan ?? null,
        'rr_dampak' => $data->rrDampak ?? null,
        'rr_kemungkinan' => $data->rrKemungkinan ?? null,
        'department' => $data->department ?? null,
        'monthly_data' => $monthlyData,
        'monthly' => $monthly,
    ];

    $orderedData = clean_recursive($orderedData);

    return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $orderedData);
}

public function store(Request $request)
{
    // ============================================
    // VALIDASI ROLE: HANYA ROLE 1, 2, 3, 5 YANG DIIZINKAN
    // ============================================

    $result = check_role(auth()->user(), [1, 2, 3, 4, 5]);
    if ($result !== true) {
        return $result;
    }

    $currentUser = auth()->user();

    // ============================================
    // VALIDASI WAJIB: SEMUA 18 FIELD DAPAT DIISI
    // ============================================

    $allowedFields = [
        'rcsa_id',
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
        'biaya_perlakuan_risiko',
        'internal_control',
        'target_quantitative_satu_tahun',
        'target_satu_tahun_option',
        'target_satu_tahun_notes',
        'penjelasan_realisasi'
    ];

    // ============================================
    // VALIDASI DUPLIKASI RCSA_ID
    // ============================================

    $rcsaId = $request->input('rcsa_id');

    // Cek apakah rcsa_id sudah digunakan oleh header lain
    if ($rcsaId !== null) {
        $existingHeader = TrRiskHeader::where('rcsa_id', $rcsaId)->first();

        if ($existingHeader) {
            return json(400, false, 'RCSA ID Sudah Digunakan', 'RCSA ID ini sudah digunakan oleh risk profile lain. Silakan gunakan RCSA ID yang berbeda.', [
                'rcsa_id' => $rcsaId,
                'existing_header_id' => $existingHeader->id,
                'message' => 'RCSA ID harus unik untuk setiap risk header.'
            ]);
        }
    }

    // ============================================
    // LANJUT KE VALIDASI NORMAL
    // ============================================

    $validator = Validator::make($request->all(), [
        // 14 field wajib
        'rcsa_id' => 'nullable|integer',
        'risk_code' => 'required|array',
        'risk_code.*' => 'exists:mst_risk_code,id',
        'jenis_risiko' => 'required|exists:mst_jenis_risiko,id',
        'year' => 'required|integer',
        'sasaran' => 'required|string',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'penjelasan_realisasi' => 'nullable|string', // tambahan penjelasan realisasi
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'department_id' => 'required|exists:mst_department,id',
        'mitigasi' => 'nullable|string',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
        // 4 field tambahan (opsional saat store)
        'internal_control' => 'required|string',
        'target_satu_tahun_option' => 'nullable|exists:mst_option,id',
        'target_satu_tahun_notes' => 'nullable|string',
        'target_quantitative_satu_tahun' => 'nullable|string|max:500'
    ], [
        'risk_code.required' => 'risk code field is required.',
        'risk_code.array' => 'risk code must be an array.',
        'risk_code.*.exists' => 'selected risk code is invalid.',
        'jenis_risiko.required' => 'jenis risiko field is required.',
        'jenis_risiko.exists' => 'selected jenis risiko is invalid.',
        'year.required' => 'year field is required.',
        'year.integer' => 'year must be an integer.',
        'sasaran.required' => 'sasaran field is required.',
        'sasaran.string' => 'sasaran must be a string.',
        'peristiwa_risiko.required' => 'peristiwa risiko field is required.',
        'peristiwa_risiko.string' => 'peristiwa risiko must be a string.',
        'penyebab_risiko.required' => 'penyebab risiko field is required.',
        'penyebab_risiko.string' => 'penyebab risiko must be a string.',
        'dampak_risiko.required' => 'dampak risiko field is required.',
        'dampak_risiko.string' => 'dampak risiko must be a string.',
        'inherent_risk_level_dampak.required' => 'inherent risk level dampak field is required.',
        'inherent_risk_level_dampak.exists' => 'selected inherent risk level dampak is invalid.',
        'inherent_risk_level_kemungkinan.required' => 'inherent risk level kemungkinan field is required.',
        'inherent_risk_level_kemungkinan.exists' => 'selected inherent risk level kemungkinan is invalid.',
        'residual_target_level_dampak.required' => 'residual target level dampak field is required.',
        'residual_target_level_dampak.exists' => 'selected residual target level dampak is invalid.',
        'residual_target_level_kemungkinan.required' => 'residual target level kemungkinan field is required.',
        'residual_target_level_kemungkinan.exists' => 'selected residual target level kemungkinan is invalid.',
        'department_id.required' => 'department id field is required.',
        'department_id.exists' => 'selected department id is invalid.',
        'mitigasi.string' => 'mitigasi must be a string.',
        'biaya_perlakuan_risiko.numeric' => 'biaya perlakuan risiko must be a number.',
        'internal_control.string' => 'internal control must be a string.',
        'target_satu_tahun_option.exists' => 'selected target satu tahun option is invalid.',
        'target_satu_tahun_notes.string' => 'target satu tahun notes must be a string.',
        'target_quantitative_satu_tahun.string' => 'target quantitative satu tahun must be a string.',
        'target_quantitative_satu_tahun.max' => 'target quantitative satu tahun may not be greater than 500 characters.',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        // AMBIL SEMUA DATA YANG DIIZINKAN
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

        // Cek apakah semua 18 field sudah terisi
        $requiredFields = [
            'risk_code', 'jenis_risiko', 'year', 'sasaran', 'peristiwa_risiko',
            'penyebab_risiko', 'dampak_risiko', 'inherent_risk_level_dampak',
            'inherent_risk_level_kemungkinan', 'residual_target_level_dampak',
            'residual_target_level_kemungkinan', 'department_id',
            'internal_control', 'target_satu_tahun_option', 'target_satu_tahun_notes',
            'target_quantitative_satu_tahun'
        ];

        $allFieldsFilled = true;
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $allFieldsFilled = false;
                break;
            }
        }

        // Set status dan is_complete
        if ($allFieldsFilled) {
            $data['status'] = 'draft';
            $data['is_complete'] = true;
        } else {
            $data['status'] = 'draft';
            $data['is_complete'] = false;
        }

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

        // Handle target_satu_tahun_position
        if (!empty($data['target_satu_tahun_option'])) {
            $option = MstOption::find($data['target_satu_tahun_option']);
            $data['target_satu_tahun_position'] = $option ? $option->position : null;
        }

        $riskHeader = TrRiskHeader::create($data);

        DB::commit();

        // Load relasi yang diperlukan
        $riskHeader->load([
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'createdBy:id,username',
            'jenisRisiko:id,nama_jenis_risiko',
            'optionTargetSatuTahun:id,name,position,type',
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
            'rcsa_id' => $riskHeader->rcsa_id,
            'risk_code' => $riskHeader->risk_code ? explode(',', $riskHeader->risk_code) : [],
            'jenis_risiko' => $riskHeader->jenis_risiko,
            'jenis_risiko_name' => $riskHeader->jenisRisiko->nama_jenis_risiko ?? null,
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
            'is_complete' => $riskHeader->is_complete ?? false,
            'year' => $riskHeader->year,
            'updated_at' => $riskHeader->updated_at,
            'created_at' => $riskHeader->created_at,
            'created_by' => $riskHeader->created_by,
            'created_by_name' => $createdByName,
            'mitigasi' => clean_string($riskHeader->mitigasi),
            'biaya_perlakuan_risiko' => $riskHeader->biaya_perlakuan_risiko,
            'internal_control' => clean_string($riskHeader->internal_control),
            'target_satu_tahun_option' => $riskHeader->target_satu_tahun_option,
            'target_satu_tahun_notes' => clean_string($riskHeader->target_satu_tahun_notes),
            'target_satu_tahun_type' => optional($riskHeader->optionTargetSatuTahun)->type,
            'target_quantitative_satu_tahun' => $formattedTarget,
            'target_satu_tahun_position' => clean_string($riskHeader->target_satu_tahun_position),
            'target_satu_tahun_name' => $riskHeader->optionTargetSatuTahun ? $riskHeader->optionTargetSatuTahun->name : null,
            'approval_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];

        // Tambahkan relasi
        $this->addRelationsToResponse($riskHeader, $responseData);

        $message = $allFieldsFilled
            ? 'Risk header berhasil disimpan dengan lengkap. Data sudah siap untuk disubmit.'
            : 'Risk header berhasil disimpan sebagai draft. Lengkapi semua field untuk melanjutkan.';

        return json(200, true, 'Berhasil Disimpan', $message, $responseData);

    } catch (\Exception $e) {
        DB::rollBack();

        // ============================================
        // HANDLE DUPLICATE ENTRY ERROR
        // ============================================

        // Cek apakah error adalah duplicate entry
        if (strpos($e->getMessage(), 'Duplicate entry') !== false ||
            strpos($e->getMessage(), '1062') !== false) {

            // Extract header_id dan month dari error message
            preg_match("/Duplicate entry '(\d+)-(\d+)'/", $e->getMessage(), $matches);

            if (count($matches) >= 3) {
                $headerId = $matches[1];
                $month = $matches[2];

                // Cari data header yang duplicate
                $existingHeader = TrRiskHeader::find($headerId);

                if ($existingHeader) {
                    return json(400, false, 'Data Sudah Ada',
                        "Data dengan ID {$headerId} sudah memiliki data monthly untuk bulan {$month}. Data ini tidak dapat di-input karena sudah ada.",
                        [
                            'header_id' => $headerId,
                            'month' => $month,
                            'existing_header' => [
                                'id' => $existingHeader->id,
                                'rcsa_id' => $existingHeader->rcsa_id,
                                'year' => $existingHeader->year,
                                'department_id' => $existingHeader->department_id,
                                'status' => $existingHeader->status
                            ],
                            'solution' => 'Silakan gunakan data header yang sudah ada atau hapus data monthly yang duplicate terlebih dahulu.'
                        ]
                    );
                }
            }

            // Fallback jika tidak bisa extract ID
            return json(400, false, 'Data Duplikat',
                'Data monthly yang akan dibuat sudah ada di database. Silakan periksa data yang sudah ada.',
                [
                    'error_detail' => 'Duplicate entry detected in tr_risk_monthly table',
                    'solution' => 'Periksa data header dan monthly yang sudah ada sebelum membuat data baru.'
                ]
            );
        }

        // Error lainnya
        return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function update(Request $request, $id)
{
    $currentUser = auth()->user();

    // Validasi role: hanya role 1, 2, 3, 5 yang diizinkan
    $roleCheck = check_role($currentUser, [1, 2, 3, 4, 5]);
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

    // Role 1, 4, 5 bisa edit data meskipun status submit, close, atau approved
    // Role 2, 3 tetap mengikuti aturan status
    if (!in_array($currentUser->role_id, [1, 4, 5])) {
        // Status 'submit' - tidak boleh diedit sama sekali
        if ($currentStatus === 'submit') {
            return json(403, false, 'Akses Ditolak', 'Data sudah disubmit dan menunggu persetujuan. Data tidak dapat diubah.', null);
        }

        // Status 'close' - tidak boleh diedit sama sekali
        if ($currentStatus === 'close') {
            return json(403, false, 'Akses Ditolak', 'Data sudah ditutup dan tidak dapat diubah lagi.', null);
        }

        // Status 'approved' dengan is_complete true - tidak boleh diedit
        if ($currentStatus === 'approved' && $riskHeader->is_complete) {
            return json(403, false, 'Akses Ditolak', 'Data sudah disetujui dan lengkap, tidak dapat diubah lagi.', null);
        }
    }

    // Untuk role 1, 4, 5 atau status draft, rejected, atau approved yang belum complete - boleh edit semua field
    return $this->handleGeneralUpdate($request, $riskHeader);
}

private function handleGeneralUpdate(Request $request, $riskHeader)
{
    // SEMUA 18 FIELD DAPAT DIEDIT
    $allowedFields = [
        'rcsa_id',
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
        'biaya_perlakuan_risiko',
        'internal_control',
        'target_quantitative_satu_tahun',
        'target_satu_tahun_option',
        'target_satu_tahun_notes',
        'penjelasan_realisasi'
    ];

    // ============================================
    // VALIDASI DUPLIKASI RCSA_ID
    // ============================================

    $rcsaId = $request->input('rcsa_id');

    // Cek apakah rcsa_id sudah digunakan oleh header lain (KECUALI header yang sedang di-update)
    if ($rcsaId !== null) {
        $existingHeader = TrRiskHeader::where('rcsa_id', $rcsaId)
            ->where('id', '!=', $riskHeader->id)
            ->first();

        if ($existingHeader) {
            return json(400, false, 'RCSA ID Sudah Digunakan', 'RCSA ID ini sudah digunakan oleh risk profile lain. Silakan gunakan RCSA ID yang berbeda.', [
                'rcsa_id' => $rcsaId,
                'existing_header_id' => $existingHeader->id,
                'message' => 'RCSA ID harus unik untuk setiap risk header.'
            ]);
        }
    }

    // Validasi semua field
    $validator = Validator::make($request->all(), [
        'rcsa_id' => 'nullable|integer',
        'risk_code' => 'required|array',
        'risk_code.*' => 'exists:mst_risk_code,id',
        'jenis_risiko' => 'required|exists:mst_jenis_risiko,id',
        'sasaran' => 'required|string',
        'year' => 'required|integer',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'penjelasan_realisasi' => 'nullable|string', // tambahan penjelasan realisasi
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'department_id' => 'required|exists:mst_department,id',
        'mitigasi' => 'nullable|string',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
        'internal_control' => 'nullable|string',
        'target_satu_tahun_option' => 'nullable|exists:mst_option,id',
        'target_satu_tahun_notes' => 'nullable|string',
        'target_quantitative_satu_tahun' => 'nullable|string|max:500'
    ], [
        'risk_code.required' => 'risk code field is required.',
        'risk_code.array' => 'risk code must be an array.',
        'risk_code.*.exists' => 'selected risk code is invalid.',
        'jenis_risiko.required' => 'jenis risiko field is required.',
        'jenis_risiko.exists' => 'selected jenis risiko is invalid.',
        'sasaran.required' => 'sasaran field is required.',
        'sasaran.string' => 'sasaran must be a string.',
        'year.required' => 'year field is required.',
        'year.integer' => 'year must be an integer.',
        'peristiwa_risiko.required' => 'peristiwa risiko field is required.',
        'peristiwa_risiko.string' => 'peristiwa risiko must be a string.',
        'penyebab_risiko.required' => 'penyebab risiko field is required.',
        'penyebab_risiko.string' => 'penyebab risiko must be a string.',
        'dampak_risiko.required' => 'dampak risiko field is required.',
        'dampak_risiko.string' => 'dampak risiko must be a string.',
        'inherent_risk_level_dampak.required' => 'inherent risk level dampak field is required.',
        'inherent_risk_level_dampak.exists' => 'selected inherent risk level dampak is invalid.',
        'inherent_risk_level_kemungkinan.required' => 'inherent risk level kemungkinan field is required.',
        'inherent_risk_level_kemungkinan.exists' => 'selected inherent risk level kemungkinan is invalid.',
        'residual_target_level_dampak.required' => 'residual target level dampak field is required.',
        'residual_target_level_dampak.exists' => 'selected residual target level dampak is invalid.',
        'residual_target_level_kemungkinan.required' => 'residual target level kemungkinan field is required.',
        'residual_target_level_kemungkinan.exists' => 'selected residual target level kemungkinan is invalid.',
        'department_id.required' => 'department id field is required.',
        'department_id.exists' => 'selected department id is invalid.',
        'mitigasi.string' => 'mitigasi must be a string.',
        'biaya_perlakuan_risiko.numeric' => 'biaya perlakuan risiko must be a number.',
        'internal_control.string' => 'internal control must be a string.',
        'target_satu_tahun_option.exists' => 'selected target satu tahun option is invalid.',
        'target_satu_tahun_notes.string' => 'target satu tahun notes must be a string.',
        'target_quantitative_satu_tahun.string' => 'target quantitative satu tahun must be a string.',
        'target_quantitative_satu_tahun.max' => 'target quantitative satu tahun may not be greater than 500 characters.',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
    }

    // ============================================
    // VALIDASI DEPARTMENT BERDASARKAN ROLE
    // ============================================

    $currentUser = auth()->user();

    // Role 2 & 3 tidak boleh mengubah department_id, harus sesuai department mereka
    // Role 1, 4, 5 bebas mengubah department_id
    if (in_array($currentUser->role_id, [2, 3])) {
        if ($request->has('department_id') && $request->input('department_id') != $currentUser->department_id) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak dapat mengubah department_id ke department lain.', null);
        }
    }

    try {
        DB::beginTransaction();

        // AMBIL SEMUA DATA YANG DIIZINKAN
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

        // Superadmin (role 1, 4, 5) boleh ubah departemen dari request
        if (in_array($currentUser->role_id, [1, 4, 5])) {
            if ($request->has('department_id')) {
                $updateData['department_id'] = $request->input('department_id');
            }
        } else {
            // Role lain (2, 3) selalu pakai department_id user
            $updateData['department_id'] = $currentUser->department_id;
        }

        // Cek apakah semua 18 field sudah terisi
        $requiredFields = [
            'risk_code', 'jenis_risiko', 'year', 'sasaran', 'peristiwa_risiko',
            'penyebab_risiko', 'dampak_risiko', 'inherent_risk_level_dampak',
            'inherent_risk_level_kemungkinan', 'residual_target_level_dampak',
            'residual_target_level_kemungkinan', 'department_id',
            'internal_control', 'target_satu_tahun_option', 'target_satu_tahun_notes',
            'target_quantitative_satu_tahun'
        ];

        // Merge existing data dengan update data untuk pengecekan
        $mergedData = array_merge($riskHeader->toArray(), $updateData);

        $allFieldsFilled = true;
        foreach ($requiredFields as $field) {
            if (empty($mergedData[$field])) {
                $allFieldsFilled = false;
                break;
            }
        }

        // Set status dan is_complete
        // Untuk semua role: jika status rejected, set kembali ke draft
        // Untuk role 1, 4, 5: jika status approved/close, status tidak berubah otomatis
        if ($riskHeader->status === 'rejected') {
            // Semua role (termasuk 1, 4, 5) yang update data rejected akan set kembali ke draft
            $updateData['status'] = 'draft';
            $updateData['approval_notes'] = null;
            $updateData['approved_by'] = null;
            $updateData['approved_at'] = null;
        }

        $updateData['is_complete'] = $allFieldsFilled;

        // Handle risk_code
        if (!empty($updateData['risk_code']) && is_array($updateData['risk_code'])) {
            $updateData['risk_code'] = implode(',', $updateData['risk_code']);
        }

        // Update heatmap calculations if dampak/kemungkinan changed
        if (isset($updateData['inherent_risk_level_dampak']) || isset($updateData['inherent_risk_level_kemungkinan'])) {
            $dampak = $updateData['inherent_risk_level_dampak'] ?? $riskHeader->inherent_risk_level_dampak;
            $kemungkinan = $updateData['inherent_risk_level_kemungkinan'] ?? $riskHeader->inherent_risk_level_kemungkinan;

            $irHeatmap = MstHeatmap::with('riskRange')
                ->where('dampak', $dampak)
                ->where('kemungkinan', $kemungkinan)
                ->first();

            if (!$irHeatmap) {
                return json(400, false, 'IR Tidak Ditemukan', 'Kombinasi IR tidak ditemukan.', null);
            }

            $updateData['inherent_risk_posisi_risiko'] = $irHeatmap->result;
            $updateData['inherent_risk_level_risiko'] = $irHeatmap->riskRange->name ?? null;
        }

        if (isset($updateData['residual_target_level_dampak']) || isset($updateData['residual_target_level_kemungkinan'])) {
            $dampak = $updateData['residual_target_level_dampak'] ?? $riskHeader->residual_target_level_dampak;
            $kemungkinan = $updateData['residual_target_level_kemungkinan'] ?? $riskHeader->residual_target_level_kemungkinan;

            $rrHeatmap = MstHeatmap::with('riskRange')
                ->where('dampak', $dampak)
                ->where('kemungkinan', $kemungkinan)
                ->first();

            if (!$rrHeatmap) {
                return json(400, false, 'RR Tidak Ditemukan', 'Kombinasi RR tidak ditemukan.', null);
            }

            $updateData['residual_target_posisi_risiko'] = $rrHeatmap->result;
            $updateData['residual_target_level_risiko'] = $rrHeatmap->riskRange->name ?? null;
        }

        // Handle target_satu_tahun_position
        if (!empty($updateData['target_satu_tahun_option'])) {
            $option = MstOption::find($updateData['target_satu_tahun_option']);
            $updateData['target_satu_tahun_position'] = $option ? $option->position : null;
        }

        $wasComplete = $riskHeader->is_complete;
        $riskHeader->update($updateData);

        DB::commit();

        $riskHeader->refresh();
        $responseData = $this->buildResponse($riskHeader);

        $message = $updateData['is_complete']
            ? 'Data berhasil diupdate dan sudah lengkap. Data siap untuk disubmit.'
            : 'Data berhasil diupdate. Lengkapi semua field untuk melanjutkan.';

        return json(200, true, 'Berhasil Diupdate', $message, $responseData);

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
        'jenisRisiko:id,nama_jenis_risiko',
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
        'jenis_risiko_id' => $riskHeader->jenis_risiko,
        'jenis_risiko' => $riskHeader->jenisRisiko->nama_jenis_risiko ?? null,
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
    $roleCheck = check_role($currentUser, [1, 2, 3, 4, 5]);

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
                'type_document' => 'Risk Profile',
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
                'type_document' => 'Risk Profile',
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

    // PERBAIKAN: Tambahkan select untuk memastikan semua kolom ter-load
    $query = TrRiskHeader::select('tr_risk_header.*')
        ->with([
            // PERBAIKAN: Hapus relasi riskCode karena sekarang risk_code adalah string comma-separated
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionTargetSatuTahun:id,name,position',
            'jenisRisiko:id,nama_jenis_risiko', //relasi ke mst_jenis_risiko
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

    // Nama bulan dalam bahasa Indonesia
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

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

    // PERBAIKAN: Helper function untuk menentukan realization_percentage
    $getRealizationPercentage = function ($percentage, $realizationKualitatif) {
        // Jika percentage ada (data kuantitatif), tampilkan percentage
        if ($percentage !== null) {
            return $percentage . '%';
        }

        // Jika percentage null tapi ada realization_kualitatif, tampilkan kualitatif
        if (!empty($realizationKualitatif)) {
            return $realizationKualitatif;
        }

        // Jika keduanya tidak ada, return '-'
        return '-';
    };

    // Mapping data with same structure as index
    $orderedData = collect($data->items())->map(function ($item) use ($calculatePercentage, $formatTargetQuantitative, $monthNames, $getRealizationPercentage) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        // PERBAIKAN: Process risk_code dari string comma-separated menjadi array
        $riskCodes = [];
        if (!empty($item->risk_code)) {
            $riskCodeIds = explode(',', $item->risk_code);
            $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                ->get(['id', 'name', 'code'])
                ->map(function ($riskCode) {
                    return [
                        'id' => $riskCode->id,
                        'name' => clean_string($riskCode->name),
                        'code' => clean_string($riskCode->code)
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
                $realizationKualitatif = $dataBulanan->realization_kualitatif ?? null;

                $monthlyDataMap[$i] = $dataBulanan;

                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'target_quantitative' => $formatTargetQuantitative($target),
                    'realization_percentage' => $getRealizationPercentage($percentage, $realizationKualitatif), // PERBAIKAN
                    'realization_kualitatif' => $realizationKualitatif,
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
                    'realization_kualitatif' => null,
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

        $entryData = $item->headerEntry->map(function ($entry) use ($calculatePercentage, $getRealizationPercentage) {
            $monthlyEntries = collect();
            for ($i = 1; $i <= 12; $i++) {
                $monthlyEntry = $entry->monthlyEntryData->firstWhere('month', $i);
                if ($monthlyEntry) {
                    $target = $monthlyEntry->target_quantitative;
                    $realization = $monthlyEntry->realization_quantitative ?? 0;
                    $percentage = $calculatePercentage($target, $realization);
                    $realizationKualitatif = $monthlyEntry->realization_kualitatif ?? null;

                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'residual_risk_level' => $monthlyEntry->residual_risk_level_risiko,
                        'residual_risk_posisi_risiko_color' => get_color_by_position($monthlyEntry->residual_risk_posisi_risiko),
                        'realization_percentage' => $getRealizationPercentage($percentage, $realizationKualitatif), // PERBAIKAN
                        'realization_kualitatif' => $realizationKualitatif,
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
                        'realization_kualitatif' => null,
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
            'rcsa_id' => $item->rcsa_id,
            'risk_code' => $riskCodes, // PERBAIKAN: Gunakan array yang sudah diproses
            'process_code' => $item->process_code ?? '',
            'jenis_risiko_id' => $item->jenis_risiko ?? null, // DIUBAH: ID
            'jenis_risiko' => $item->jenisRisiko->nama_jenis_risiko ?? '', // DIUBAH: NAMA
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

            'biaya_perlakuan_risiko' => number_format($item->biaya_perlakuan_risiko, 2, ',', '.'),
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

            'monthly_data' => $item->monthlyData->map(function ($dataBulanan) use ($riskCodes, $calculatePercentage, $formatTargetQuantitative, $monthNames, $item, $getRealizationPercentage) {
                $target = $dataBulanan->target_quantitative;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = $calculatePercentage($target, $realization);
                $realizationKualitatif = $dataBulanan->realization_kualitatif ?? null;

                return [
                    'id' => $dataBulanan->id,
                    'header_id' => $dataBulanan->header_id,
                    'month' => $dataBulanan->month,
                    'month_name' => $monthNames[$dataBulanan->month] ?? 'Unknown',
                    'month_full_name' => ($monthNames[$dataBulanan->month] ?? 'Unknown') . ' ' . $item->year,
                    'risk_code' => $riskCodes, // PERBAIKAN: Gunakan array risk codes dari header
                    'status_risiko' => $dataBulanan->status_risiko,
                    'process_code' => $dataBulanan->process_code,
                    'start_date' => $dataBulanan->start_date ? $dataBulanan->start_date->format('Y-m-d H:i:s') : null,
                    'expired_date' => $dataBulanan->expired_date ? $dataBulanan->expired_date->format('Y-m-d H:i:s') : null,
                    'realization_quantitative' => $realization,
                    'realization_kualitatif' => $realizationKualitatif,
                    'realization_note' => $dataBulanan->realization_note,
                    'penjelasan_realisasi' => $dataBulanan->penjelasan_realisasi,
                    'target_quantitative' => $formatTargetQuantitative($target), // PERBAIKAN: Format target sesuai jenis data
                    'target_kualitatif' => $dataBulanan->target_kualitatif ?? null,
                    'target_notes' => $dataBulanan->target_notes,
                    'residual_risk_level_dampak' => $dataBulanan->residual_risk_level_dampak,
                    'residual_risk_level_kemungkinan' => $dataBulanan->residual_risk_level_kemungkinan,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_level_risiko' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_level_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $getRealizationPercentage($percentage, $realizationKualitatif), // PERBAIKAN
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

        // Query tanpa filter status - TAMBAHKAN penjelasan_realisasi di select
        $query = TrRiskHeader::select('tr_risk_header.*')
            ->with([
                'irDampak:id,label',
                'irKemungkinan:id,label',
                'rrDampak:id,label',
                'rrKemungkinan:id,label',
                'department:id,name',
                'optionTargetSatuTahun:id,name,position',
                'jenisRisiko:id,nama_jenis_risiko',
                'createdBy:id,name',
                'approval:document_id,type_document,status,tahun,jabatan_id'
            ]);

        // Filter department berdasarkan role
        $query->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
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

        // Filter berdasarkan tahun
        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        // Filter pencarian
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('jenis_risiko', 'like', '%' . $searchTerm . '%')
                    ->orWhere('sasaran', 'like', '%' . $searchTerm . '%')
                    ->orWhere('peristiwa_risiko', 'like', '%' . $searchTerm . '%')
                    ->orWhere('penyebab_risiko', 'like', '%' . $searchTerm . '%')
                    ->orWhere('dampak_risiko', 'like', '%' . $searchTerm . '%')
                    ->orWhere('internal_control', 'like', '%' . $searchTerm . '%')
                    ->orWhere('mitigasi', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('department', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('createdBy', function ($q) use ($searchTerm) {
                        $q->whereRaw("CAST(AES_DECRYPT(name, CONCAT('SM', id)) AS CHAR) LIKE ?", ['%' . $searchTerm . '%']);
                    });
            });
        }

        \Log::info('Pending approval query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // Pagination
        $perPage = $request->has('per_page') ? (int)$request->per_page : 10;
        $pendingData = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $responseData = $pendingData->getCollection()->map(function ($riskHeader) {
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
                    ->get(['id', 'name', 'code'])
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => clean_string($item->name),
                            'code' => clean_string($item->code)
                        ];
                    })
                    ->toArray();
            }

            // Pisahkan risk_status dan override_status
            $riskStatus = $riskHeader->status;
            $overrideStatus = $riskHeader->status;

            if ($riskHeader->menrisk_by !== null && $riskHeader->status === 'approved') {
                $overrideStatus = 'final';
                $riskStatus = 'final';
            } elseif ($riskHeader->status === 'rejected') {
                $overrideStatus = 'rejected';
                $riskStatus = 'rejected';
            }

            return [
                'id' => $riskHeader->id,
                'type_document' => $riskHeader->approval->type_document ?? null,
                'department_id' => $riskHeader->department_id,
                'department_name' => $riskHeader->department ? clean_string($riskHeader->department->name) : null,
                'year' => $riskHeader->year,
                'risk_code' => $riskHeader->risk_code ? explode(',', $riskHeader->risk_code) : [],
                'risk_codes' => $riskCodes,
                'jenis_risiko_id' => $riskHeader->jenis_risiko,
                'jenis_risiko' => $riskHeader->jenisRisiko->nama_jenis_risiko ?? null,
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
                'risk_status' => $riskStatus,
                'override_status' => $overrideStatus,
                'reviewed' => (bool) $riskHeader->reviewed,
                'reviewed_by' => $riskHeader->reviewed_by,
                'reviewed_at' => $riskHeader->reviewed_at,
                'desc' => clean_string($riskHeader->desc),
                'created_at' => $riskHeader->created_at,
                'created_by_name' => $createdByName,
                'target_quantitative_satu_tahun' => format_target_quantitative($riskHeader->target_quantitative_satu_tahun),
                'biaya_perlakuan_risiko' => number_format($riskHeader->biaya_perlakuan_risiko ?? 0, 0, ',', '.'),
                'notes' => $riskHeader->approval_notes,
                'menrisk_note' => clean_string($riskHeader->menrisk_note),
                'vp_menrisk_note' => clean_string($riskHeader->vp_menrisk_note ?? null),
            ];
        });

        $paginationData = [
            'data' => $responseData,
            'current_page' => $pendingData->currentPage(),
            'last_page' => $pendingData->lastPage(),
            'per_page' => $pendingData->perPage(),
            'total' => $pendingData->total(),
            'from' => $pendingData->firstItem(),
            'to' => $pendingData->lastItem(),
        ];

        return json(200, true, 'Data Berhasil Diambil', 'Data pending approval berhasil diambil.', $paginationData);

    } catch (\Exception $e) {
        return json(500, false, 'Gagal Mengambil Data', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

// Approve risk header oleh SPV Unit (role 1 dan 2)
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

        $currentUser = auth()->user();
        $roleId = $currentUser->role_id ?? null;

        // Hanya role 1 dan 2 yang boleh approve
        if (!in_array($roleId, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki hak untuk menyetujui data ini.', null);
        }

        // Jika role 2, hanya boleh approve data departemen sendiri
        if ($roleId === 2 && $riskHeader->department_id !== $currentUser->department_id) {
            return json(403, false, 'Tidak Diizinkan', 'Anda hanya dapat menyetujui data dari departemen Anda sendiri.', null);
        }

        // Cek apakah data di-reject oleh MenRisk
        if ($riskHeader->status === 'rejected' && $riskHeader->menrisk_by !== null) {
            return json(400, false, 'Perlu Revisi User',
                'Data ini telah direject oleh Manajemen Risiko. User harus memperbaiki data dan submit ulang sebelum dapat di-review SPV Unit.',
                [
                    'menrisk_note' => $riskHeader->menrisk_note,
                    'menrisk_rejected_at' => $riskHeader->menrisk_at,
                    'next_step' => 'Tunggu user update dan submit ulang'
                ]);
        }

        // Hanya bisa approve jika status submit
        if ($riskHeader->status !== 'submit') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status submit yang dapat disetujui.', null);
        }

        // CEK KELENGKAPAN DATA SECARA DINAMIS
        // Validasi 14 field wajib
        $requiredFields = [
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
            'department_id'
        ];

        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($riskHeader->$field)) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return json(400, false, 'Data Tidak Lengkap', 'Field wajib belum diisi: ' . implode(', ', $missingFields), [
                'missing_fields' => $missingFields
            ]);
        }

        // CEK apakah ada 4 field tambahan yang diisi (untuk set is_complete)
        $hasAdditionalFields = !empty($riskHeader->internal_control) ||
                               !empty($riskHeader->target_quantitative_satu_tahun) ||
                               !empty($riskHeader->target_satu_tahun_option) ||
                               !empty($riskHeader->target_satu_tahun_notes);

        // Update: Status approved, set is_complete berdasarkan field tambahan
        $riskHeader->update([
            'status' => 'approved',
            'approval_notes' => $request->approval_notes,
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
            'is_complete' => $hasAdditionalFields, // Set true jika ada field tambahan
            // Reset MenRisk fields jika sebelumnya pernah direject
            'menrisk_by' => null,
            'menrisk_at' => null,
            'menrisk_note' => null
        ]);

        \App\Models\MstApproval::where('document_id', $id)
            ->update([
                'status' => 'approved',
                'tanggal' => now(),
                'note' => $request->approval_notes ?? 'Approved by SPV Unit'
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

        return json(200, true, 'Berhasil Disetujui', 'Risk header telah disetujui oleh SPV Unit dan menunggu persetujuan Manajemen Risiko.', [
            'id' => $riskHeader->id,
            'status' => $riskHeader->status,
            'is_complete' => $riskHeader->is_complete,
            'approval_notes' => clean_string($riskHeader->approval_notes),
            'approved_by' => $riskHeader->approved_by,
            'approved_by_name' => $approvedByName,
            'approved_at' => $riskHeader->approved_at,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Menyetujui', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

// Reject risk header oleh SPV Unit (role 1 dan 2)
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

        $currentUser = auth()->user();
        $roleId = $currentUser->role_id ?? null;

        // Hanya role 1 dan 2 yang boleh reject
        if (!in_array($roleId, [1, 2])) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk menolak data ini.', null);
        }

        // Role 2 hanya boleh reject departemen sendiri
        if ($roleId === 2 && $riskHeader->department_id !== $currentUser->department_id) {
            return json(403, false, 'Akses Ditolak', 'Anda hanya dapat menolak data dari departemen Anda sendiri.', null);
        }

        // Hanya bisa reject jika status submit
        if ($riskHeader->status !== 'submit') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status submit yang dapat ditolak.', null);
        }

        // PENTING: Jangan ubah is_complete saat reject SPV Unit
        // Karena data yang sudah lengkap tetap lengkap
        $riskHeader->update([
            'status' => 'rejected',
            'approval_notes' => $request->approval_notes,
            'approved_by' => $currentUser->id,
            'approved_at' => now()
            // is_complete TIDAK diubah
        ]);

        \App\Models\MstApproval::where('document_id', $id)
            ->update([
                'status' => 'rejected',
                'tanggal' => now(),
                'note' => $request->approval_notes
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

        return json(200, true, 'Berhasil Ditolak', 'Risk header berhasil ditolak oleh SPV Unit. Silakan perbaiki data sesuai catatan penolakan.', [
            'id' => $riskHeader->id,
            'status' => $riskHeader->status,
            'is_complete' => $riskHeader->is_complete,
            'rejection_notes' => clean_string($riskHeader->approval_notes),
            'rejected_by' => $riskHeader->approved_by,
            'rejected_by_name' => $rejectedByName,
            'rejected_at' => $riskHeader->approved_at,
            'next_step' => 'Perbaiki data dan submit ulang untuk review SPV Unit'
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
        ])->where('status', 'rejected');

        // Filter berdasarkan role
        if ($user->role_id == 2 || $user->role_id == 3) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        // Handle parameter department_id
        if ($request->has('department_id') && $request->department_id) {
            if ($user->role_id == 2 || $user->role_id == 3) {
                if ((int)$request->department_id !== (int)$user->department_id) {
                    return json(403, false, 'Akses Ditolak', 'Anda hanya dapat melihat data dari department Anda sendiri.', null);
                }
            }
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
                'override_status' => $riskHeader->status,
                'reviewed' => (bool) $riskHeader->reviewed,
                'reviewed_by' => $riskHeader->reviewed_by,
                'reviewed_at' => $riskHeader->reviewed_at,
                'desc' => clean_string($riskHeader->desc),
                'notes' => clean_string($riskHeader->approval_notes),
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

// Review by Staf MenRisk (role 1 dan 5)
public function reviewRiskHeader(Request $request, $id)
{
    try {
        $user = Auth::user();

        // Hanya role 1 (superadmin) dan 5 (staf menrisk) yang boleh review
        if (!in_array($user->role_id, [1, 5])) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk mereview data ini.', null);
        }

        $header = TrRiskHeader::with(['reviewedBy:id,username'])->find($id);
        if (!$header) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        // Hanya data dengan status approved yang bisa direview
        if ($header->status !== 'approved') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status approved yang dapat direview oleh Staf MenRisk.', null);
        }

        // Cek apakah sudah pernah direview
        if ($header->reviewed === 1 || $header->reviewed === true) {
            $reviewedByNameExisting = 'Unknown User';
            try {
                if ($header->reviewedBy) {
                    $reviewedByNameExisting = get_decrypted_name($header->reviewedBy);
                }
            } catch (\Throwable $e) {
                \Log::warning("Error handling reviewedBy: {$e->getMessage()}");
            }

            return json(400, false, 'Sudah Direview', 'Data ini sudah pernah direview oleh Staf MenRisk.', [
                'reviewed_by' => $header->reviewed_by,
                'reviewed_by_name' => $reviewedByNameExisting,
                'reviewed_at' => $header->reviewed_at
            ]);
        }

        // Update status review
        $header->reviewed = true;
        $header->reviewed_by = $user->id;
        $header->reviewed_at = now();
        $header->save();

        // Reload relasi setelah save
        $header->load(['reviewedBy:id,username']);

        $reviewedByName = 'Unknown User';
        try {
            if ($header->reviewedBy) {
                $reviewedByName = get_decrypted_name($header->reviewedBy);
            }
        } catch (\Throwable $e) {
            \Log::warning("Error handling reviewedBy: {$e->getMessage()}");
        }

        return json(200, true, 'Berhasil Direview', 'Data berhasil direview oleh Staf MenRisk. Manajemen Risiko sekarang dapat melakukan approve atau reject.', [
            'id' => $header->id,
            'status' => $header->status,
            'reviewed' => $header->reviewed,
            'reviewed_by' => $header->reviewed_by,
            'reviewed_by_name' => $reviewedByName,
            'reviewed_at' => $header->reviewed_at,
            'next_step' => 'Menunggu approval atau reject dari Manajemen Risiko'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error reviewRiskHeader: ' . $e->getMessage());
        return json(500, false, 'Gagal Review', 'Terjadi kesalahan sistem saat review data.', $e->getMessage());
    }
}

// Approve by MenRisk
public function approveMenrisk(Request $request, $id)
{
    try {
        DB::beginTransaction();

        $user = Auth::user();

        if (!in_array($user->role_id, [1, 4])) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk approve tahap Manajemen Risiko.', null);
        }

        $header = TrRiskHeader::find($id);
        if (!$header) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        $validated = $request->validate([
            'menrisk_note' => 'nullable|string',
        ]);

        if ($header->status !== 'approved') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status approved yang dapat di-approve MenRisk.', null);
        }

        // Pengecekan review - WAJIB sudah direview oleh Staf MenRisk
        if ($header->reviewed != 1 && $header->reviewed !== true) {
            return json(400, false, 'Belum Direview', 'Data ini belum direview oleh Staf MenRisk. Staf MenRisk harus mereview terlebih dahulu sebelum dapat di-approve.', [
                'next_step' => 'Tunggu Staf MenRisk melakukan review terlebih dahulu'
            ]);
        }

        if ($header->menrisk_by !== null) {
            return json(400, false, 'Sudah Disetujui', 'Data ini sudah pernah di-approve oleh Manajemen Risiko.', null);
        }

        // Setelah MenRisk approve, set is_complete = true (data sudah final)
        $header->menrisk_note = $request->input('menrisk_note');
        $header->menrisk_by = $user->id;
        $header->menrisk_at = now();
        $header->is_complete = true; // Set true karena sudah final approved
        $header->save();

        // GENERATE 12 BULAN SETELAH MENRISK APPROVE
        generate_monthly_data($header);

        // Update created_by untuk monthly data
        TrRiskMonthly::where('header_id', $header->id)
            ->whereNull('created_by')
            ->update([
                'created_by' => $header->created_by,
                'updated_by' => $user->id
            ]);

        DB::commit();

        // Status database tetap 'approved', tapi JSON override jadi 'final'
        return json(200, true, 'Berhasil Disetujui', 'Header berhasil di-approve oleh Manajemen Risiko. Data 12 bulan telah di-generate dan data sudah final.', [
            'id' => $header->id,
            'status' => 'final',
            'is_complete' => $header->is_complete,
            'menrisk_note' => $header->menrisk_note,
            'menrisk_by' => $header->menrisk_by,
            'menrisk_at' => $header->menrisk_at,
            'next_step' => 'Data sudah final dan tidak dapat diubah lagi'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error approveMenrisk: ' . $e->getMessage());
        return json(500, false, 'Gagal Approve', 'Terjadi kesalahan sistem saat approve MenRisk.', $e->getMessage());
    }
}

// Reject by MenRisk
public function rejectMenrisk(Request $request, $id)
{
    try {
        $user = Auth::user();

        // Hanya role 1 dan 4 yang boleh reject MenRisk
        if (!in_array($user->role_id, [1, 4])) {
            return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk reject tahap Manajemen Risiko.', null);
        }

        $header = TrRiskHeader::find($id);
        if (!$header) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        // Validasi catatan (WAJIB saat reject)
        $validated = $request->validate([
            'menrisk_note' => 'required|string',
        ]);

        // Pastikan hanya data dengan status approved yang bisa direject MenRisk
        if ($header->status !== 'approved') {
            return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status approved yang dapat di-reject MenRisk.', null);
        }

        // Pengecekan review - WAJIB sudah direview oleh Staf MenRisk
        if ($header->reviewed != 1 && $header->reviewed !== true) {
            return json(400, false, 'Belum Direview', 'Data ini belum direview oleh Staf MenRisk. Staf MenRisk harus mereview terlebih dahulu sebelum dapat di-reject.', [
                'next_step' => 'Tunggu Staf MenRisk melakukan review terlebih dahulu'
            ]);
        }

        // Cek apakah sudah pernah di-approve MenRisk
        if ($header->menrisk_by !== null && $header->is_complete) {
            return json(400, false, 'Tidak Dapat Direject', 'Data yang sudah fully approved tidak dapat direject. Gunakan fitur revisi jika perlu perubahan.', null);
        }

        // Saat MenRisk reject, kembalikan ke status rejected
        // SPV Unit perlu approve ulang setelah user memperbaiki
        $header->menrisk_note = $request->input('menrisk_note');
        $header->menrisk_by = $user->id;
        $header->menrisk_at = now();
        $header->status = 'rejected';
        // PENTING: is_complete TIDAK diubah karena data tetap lengkap

        // Reset approval SPV Unit agar harus approve ulang
        $header->approved_by = null;
        $header->approved_at = null;
        $header->approval_notes = null;

        // Reset review agar harus direview ulang setelah diperbaiki
        $header->reviewed = false;
        $header->reviewed_by = null;
        $header->reviewed_at = null;

        // Reset approval VP MenRisk agar bisa approve ulang setelah revisi
        $header->vp_menrisk_by = null;
        $header->vp_menrisk_at = null;
        $header->vp_menrisk_note = null;

        $header->save();

        // Hapus monthly data karena direject
        TrRiskMonthly::where('header_id', $header->id)->delete();

        return json(200, true, 'Berhasil Ditolak', 'Header berhasil di-reject oleh Manajemen Risiko. User perlu memperbaiki data dan submit ulang untuk review SPV Unit.', [
            'id' => $header->id,
            'status' => $header->status,
            'is_complete' => $header->is_complete,
            'menrisk_note' => $header->menrisk_note,
            'menrisk_by' => $header->menrisk_by,
            'menrisk_at' => $header->menrisk_at,
            'next_step' => 'User perbaiki data → Submit → SPV Unit approve → Staf MenRisk review → MenRisk approve/reject'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error rejectMenrisk: ' . $e->getMessage());
        return json(500, false, 'Gagal Reject', 'Terjadi kesalahan sistem saat reject MenRisk.', $e->getMessage());
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
            return json(400, false, 'Tidak Dapat Dihapus', 'Maaf data yang sudah di approve tidak dapat di hapus.', null);
        }

        // ===== VALIDASI STATUS YANG BOLEH DIHAPUS =====
        // HANYA BOLEH HAPUS JIKA STATUS = draft
        if ($riskHeader->status !== 'draft') {
            return json(
                400,
                false,
                'Tidak Dapat Dihapus',
                'Risk header hanya dapat dihapus jika masih berstatus draft.',
                null
            );
        }
        // =================================================

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

        // 5. Log aktivitas delete
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

public function getTaskRealisasiMonitoring(Request $request)
{
    try {
        $user = \Auth::user();

        $query = TrRiskHeader::with([
            'department:id,name',
            'monthlyData' => function ($q) {
                $q->where('is_finalize', true);
            },
            'createdBy:id,name'
        ]);

        // Filter berdasarkan role
        if (in_array($user->role_id, [2, 3])) {
            $query->where('department_id', $user->department_id);
        }
        // Filter pencarian
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('peristiwa_risiko', 'like', '%' . $searchTerm . '%')
                    ->orWhere('mitigasi', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('department', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('createdBy', function ($q) use ($searchTerm) {
                        $q->whereRaw("CAST(AES_DECRYPT(name, CONCAT('SM', id)) AS CHAR) LIKE ?", ['%' . $searchTerm . '%']);
                    });
            });
        }

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        $perPage = $request->has('per_page') ? (int)$request->per_page : 10;
        $headers = $query->paginate($perPage);

        $result = [];
        $no = ($headers->currentPage() - 1) * $headers->perPage() + 1;

        foreach ($headers as $header) {
            $riskOwner = $header->department->name ?? '-';
            $peristiwa = $header->peristiwa_risiko ?? '-';
            $rencana = $header->mitigasi ?? '-';

            // ==================== WAKTU PELAKSANAAN =====================
            $finalizedMonths = $header->monthlyData->pluck('month')->toArray();
            $waktuPelaksanaan = '-';

            if (!empty($finalizedMonths)) {
                $startMonth = min($finalizedMonths);
                $endMonth   = max($finalizedMonths);

                $startDate = \Carbon\Carbon::createFromDate($header->year, $startMonth, 1)->startOfMonth();
                $endDate   = \Carbon\Carbon::createFromDate($header->year, $endMonth, 1)->endOfMonth();

                $waktuPelaksanaan = $startDate->format('Y-m-d') . ' s/d ' . $endDate->format('Y-m-d');
            }

            $pic = get_decrypted_name((object)['id' => $header->created_by]) .
                ' - ' . ($header->department->name ?? '');

            // ==================== KUANTITATIF =====================
            $targetText = $header->target_quantitative_satu_tahun ?? '';
            preg_match('/\d+/', str_replace('.', '', $targetText), $matches);
            $targetValue = isset($matches[0]) ? (float)$matches[0] : 0;

            $totalRealisasiQuant = $header->monthlyData->sum(function ($m) {
                return (float) str_replace(',', '', $m->realization_quantitative ?? 0);
            });

            $realisasiPercent = 0;
            if ($targetValue > 0 && $totalRealisasiQuant > 0) {
                $realisasiPercent = round(($totalRealisasiQuant / $targetValue) * 100, 2);
            }

            // ==================== KUALITATIF =====================
            if ($realisasiPercent === 0) {
                // Cek data bulan Desember (12) yang finalize
                $desemberMonthly = $header->monthlyData->firstWhere('month', 12);
                if ($desemberMonthly && $desemberMonthly->realization_kualitatif) {
                    $qualVal = (float) str_replace('%', '', $desemberMonthly->realization_kualitatif);
                    $realisasiPercent = $qualVal;
                } else {
                    // Jika tidak ada Desember, ambil bulan terakhir finalize
                    $lastMonthly = $header->monthlyData->sortByDesc('month')->first();
                    if ($lastMonthly && $lastMonthly->realization_kualitatif) {
                        $qualVal = (float) str_replace('%', '', $lastMonthly->realization_kualitatif);
                        $realisasiPercent = $qualVal;
                    }
                }
            }

            $result[] = [
                'no' => $no++,
                'risk_owner' => $riskOwner,
                'peristiwa_risiko' => $peristiwa,
                'rencana_penanganan' => $rencana,
                'waktu_pelaksanaan' => $waktuPelaksanaan,
                'pic' => $pic,
                'realisasi' => $realisasiPercent . '%',
            ];
        }

        $paginationData = [
            'data' => $result,
            'current_page' => $headers->currentPage(),
            'last_page' => $headers->lastPage(),
            'per_page' => $headers->perPage(),
            'total' => $headers->total(),
            'from' => $headers->firstItem(),
            'to' => $headers->lastItem(),
        ];

        return json(200, true, 'Task Realisasi Monitoring Mitigasi berhasil diambil', null, $paginationData);

    } catch (\Exception $e) {
        return json(500, false, 'Terjadi kesalahan', $e->getMessage(), null);
    }
}

}
