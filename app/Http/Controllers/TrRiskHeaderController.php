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

class TrRiskHeaderController extends Controller
{
 public function index(Request $request)
{
     $user = auth()->user();

    $perPage = $request->input('per_page', 10);

    $query = TrRiskHeader::with([
        'riskCode:id,name',
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
        'headerEntry.riskCode:id,name',
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

    // Pagination, ambil data per halaman
    $data = $query->paginate($perPage);

    // Mapping data pada halaman saat ini
    $orderedData = collect($data->items())->map(function ($item) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        $monthlyDataMap = [];
        $monthly = [];

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);

            if ($dataBulanan) {
                $target = $dataBulanan->target_quantitative ?? 0;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                $monthlyDataMap[$i] = $dataBulanan;

                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $percentage . '%',
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
                    'residual_risk_posisi_risiko' => null,
                    'residual_risk_posisi_risiko_color' => null,
                    'realization_percentage' => '0%',
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

        $entryData = $item->headerEntry->map(function ($entry) {
            $monthlyEntries = collect();
            for ($i = 1; $i <= 12; $i++) {
                $monthlyEntry = $entry->monthlyEntryData->firstWhere('month', $i);
                if ($monthlyEntry) {
                    $target = $monthlyEntry->target_quantitative ?? 0;
                    $realization = $monthlyEntry->realization_quantitative ?? 0;
                    $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'residual_risk_level' => $monthlyEntry->residual_risk_level_risiko,
                        'residual_risk_posisi_risiko' => $monthlyEntry->residual_risk_posisi_risiko,
                        'residual_risk_posisi_risiko_color' => get_color_by_position($monthlyEntry->residual_risk_posisi_risiko),
                        'realization_percentage' => $percentage . '%',
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
                        'residual_risk_posisi_risiko' => null,
                        'residual_risk_posisi_risiko_color' => null,
                        'realization_percentage' => '0%',
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
            'risk_code' => $item->riskCode ?? null,
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
            'target_satu_tahun_notes' => $item->target_satu_tahun_notes ?? '',
            'target_satu_tahun_position' => $item->optionTargetSatuTahun->position ?? 0,
            'target_quantitative_satu_tahun' => number_format($item->target_quantitative_satu_tahun, 0, ',', '.'),

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
            'created_by_name' => get_decrypted_username($item->createdBy),
            'updated_by' => $item->updated_by ?? null,
            'updated_by_name' => get_decrypted_username($item->updatedBy),

            'ir_dampak' => $item->irDampak ?? null,
            'ir_kemungkinan' => $item->irKemungkinan ?? null,
            'rr_dampak' => $item->rrDampak ?? null,
            'rr_kemungkinan' => $item->rrKemungkinan ?? null,
            'department' => $item->department ?? null,

            'monthly_data' => $item->monthlyData->map(function ($dataBulanan) {
                $target = $dataBulanan->target_quantitative ?? 0;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                return [
                    'id' => $dataBulanan->id,
                    'header_id' => $dataBulanan->header_id,
                    'month' => $dataBulanan->month,
                    'risk_code' => $dataBulanan->risk_code,
                    'status_risiko' => $dataBulanan->status_risiko,
                    'process_code' => $dataBulanan->process_code,
                    'start_date' => $dataBulanan->start_date ? $dataBulanan->start_date->format('Y-m-d H:i:s') : null,
                    'expired_date' => $dataBulanan->expired_date ? $dataBulanan->expired_date->format('Y-m-d H:i:s') : null,
                    'realization_quantitative' => $realization,
                    'realization_note' => $dataBulanan->realization_note,
                    'target_quantitative' => $target,
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
    $data = TrRiskHeader::with([
        'riskCode:id,name',
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position',
        'createdBy',
        'updatedBy',
        'monthlyData' => function($query) {
            $query->orderBy('month', 'asc')->with(['uploads', 'createdBy', 'updatedBy']);
        }
    ])->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
    }

    $inherentColor = get_color_by_position($data->inherent_risk_posisi_risiko);
    $residualTargetColor = get_color_by_position($data->residual_target_posisi_risiko);

    $monthly = [];
    $monthlyData = [];

    for ($i = 1; $i <= 12; $i++) {
        $dataBulanan = $data->monthlyData->firstWhere('month', $i);

        if ($dataBulanan) {
            $target = $dataBulanan->target_quantitative ?? 0;
            $realization = $dataBulanan->realization_quantitative ?? 0;
            $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

            $monthly[] = [
                'bulan' => $i,
                'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                'realization_percentage' => $percentage . '%',
                'is_finalized' => (bool) $dataBulanan->is_finalize,
                'monthly_data' => $dataBulanan,
            ];

            $monthlyItem = [
                'id' => $dataBulanan->id,
                'header_id' => $dataBulanan->header_id,
                'month' => $dataBulanan->month,
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
                'finalized_at' => $dataBulanan->finalized_at,
                'finalized_by' => $dataBulanan->finalized_by,
                'created_at' => $dataBulanan->created_at,
                'updated_at' => $dataBulanan->updated_at,
                // Tambahkan created_by_name dan updated_by_name menggunakan helper yang sudah ada
                'created_by_name' => $dataBulanan->createdBy ? clean_string(get_decrypted_username($dataBulanan->createdBy)) : 'Unknown User',
                'updated_by_name' => $dataBulanan->updatedBy ? clean_string(get_decrypted_username($dataBulanan->updatedBy)) : 'Unknown User',
                'uploads' => $dataBulanan->uploads ? $dataBulanan->uploads->map(function ($upload) {
                    return [
                        'id' => $upload->id,
                        'filepath' => $upload->filepath,
                        'domain' => $upload->domain,
                    ];
                })->toArray() : [],
            ];

            $monthlyData[] = $monthlyItem;

        } else {
            $monthly[] = [
                'bulan' => $i,
                'residual_risk_level' => null,
                'residual_risk_posisi_risiko' => null,
                'residual_risk_posisi_risiko_color' => null,
                'realization_percentage' => '0%',
                'is_finalized' => false,
                'monthly_data' => null,
            ];

            $monthlyData[] = [
                'id' => null,
                'header_id' => $data->id,
                'month' => $i,
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
                'finalized_at' => null,
                'finalized_by' => null,
                'created_at' => null,
                'updated_at' => null,
                'created_by_name' => 'Unknown User',
                'updated_by_name' => 'Unknown User',
                'uploads' => [],
            ];
        }
    }

    // Siapkan data utama
    $orderedData = [
        'id' => $data->id,
        'risk_code' => $data->riskCode ?? null,
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
        'target_satu_tahun_notes' => $data->target_satu_tahun_notes ?? '',
        'target_satu_tahun_position' => $data->optionTargetSatuTahun->position ?? 0,
        'target_quantitative_satu_tahun' => number_format($data->target_quantitative_satu_tahun, 0, ',', '.'),

        'biaya_perlakuan_risiko' => number_format($data->biaya_perlakuan_risiko, 0, ',', '.'),
        'residual_target_level_dampak' => $data->residual_target_level_dampak ?? 0,
        'residual_target_level_kemungkinan' => $data->residual_target_level_kemungkinan ?? 0,
        'residual_target_posisi_risiko' => $data->residual_target_posisi_risiko ?? '',
        'residual_target_level_risiko' => $data->residual_target_level_risiko ?? 0,
        'residual_target_posisi_risiko_color' => $residualTargetColor,
        'department_id' => $data->department_id,
        'year' => $data->year,
        'created_at' => $data->created_at,
        'updated_at' => $data->updated_at,

        // Tambahkan created_by_name dan updated_by_name untuk data utama
        'created_by_name' => $data->createdBy ? clean_string(get_decrypted_username($data->createdBy)) : 'Unknown User',
        'updated_by_name' => $data->updatedBy ? clean_string(get_decrypted_username($data->updatedBy)) : 'Unknown User',

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
    $validator = Validator::make($request->all(), [
        'risk_code' => 'required|exists:mst_risk_code,id',
        // 'process_code' => 'required|integer',
        'jenis_risiko' => 'required|string',
        'sasaran' => 'required|string',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'internal_control' => 'required|string',
        'target_satu_tahun_option' => 'nullable|exists:mst_option,id',
        'target_satu_tahun_notes' => 'nullable|string',
        'target_quantitative_satu_tahun' => 'nullable|numeric',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
        'department_id' => 'required|exists:mst_department,id',
        'year' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
    }

    try {
        DB::beginTransaction();

        $data = $request->all();
        $data['created_by'] = auth()->id();

        if (!empty($data['target_satu_tahun_option'])) {
            $option = MstOption::find($data['target_satu_tahun_option']);
            if ($option) {
                $data['target_satu_tahun_position'] = $option->position;
            } else {
                $data['target_satu_tahun_position'] = null;
            }
        } else {
            $data['target_satu_tahun_position'] = null;
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
        generate_monthly_data($riskHeader);

        DB::commit();

        // Load relasi yang diperlukan
        $riskHeader->load([
            'riskCode:id,name',
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionTargetSatuTahun:id,name,position',
            'createdBy:id,username',
            'monthlyData' => function ($query) {
                $query->orderBy('month', 'asc');
            }
        ]);

        $createdByName = 'Unknown User';
        try {
            $createdByName = get_decrypted_username($riskHeader->createdBy);
        } catch (\Throwable $e) {
            \Log::warning("Error handling createdBy: {$e->getMessage()}");
        }

        $responseData = [
            'id' => $riskHeader->id,
            'risk_code' => $riskHeader->risk_code,
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
            'target_satu_tahun_option' => $riskHeader->target_satu_tahun_option,
            'target_satu_tahun_notes' => clean_string($riskHeader->target_satu_tahun_notes),
            'target_quantitative_satu_tahun' => number_format($riskHeader->target_quantitative_satu_tahun, 0, ',', '.'),
            'biaya_perlakuan_risiko' => number_format($riskHeader->biaya_perlakuan_risiko, 0, ',', '.'),
            'department_id' => $riskHeader->department_id,
            'year' => $riskHeader->year,
            'target_satu_tahun_position' => clean_string($riskHeader->target_satu_tahun_position),
            'inherent_risk_posisi_risiko' => $riskHeader->inherent_risk_posisi_risiko,
            'inherent_risk_level_risiko' => clean_string($riskHeader->inherent_risk_level_risiko),
            'residual_target_posisi_risiko' => $riskHeader->residual_target_posisi_risiko,
            'residual_target_level_risiko' => clean_string($riskHeader->residual_target_level_risiko),
            'process_code' => $riskHeader->process_code ?? null,
            'updated_at' => $riskHeader->updated_at,
            'created_at' => $riskHeader->created_at,
            'created_by' => $riskHeader->created_by,
            'created_by_name' => $createdByName,
        ];

        $responseData['risk_code'] = $riskHeader->riskCode ? [
            'id' => $riskHeader->riskCode->id,
            'name' => clean_string($riskHeader->riskCode->name)
        ] : null;

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

        // Tambahkan target_satu_tahun_option_name jika ada
        if ($riskHeader->optionTargetSatuTahun) {
            $responseData['target_satu_tahun_option_name'] = clean_string($riskHeader->optionTargetSatuTahun->name);
        }

        $monthlyData = [];
        if ($riskHeader->monthlyData) {
            foreach ($riskHeader->monthlyData as $monthly) {
                $monthlyArray = $monthly->toArray();
                foreach ($monthlyArray as $key => $value) {
                    if (is_string($value)) {
                        $monthlyArray[$key] = clean_string($value);
                    }
                }
                $monthlyData[] = $monthlyArray;
            }
        }
        $responseData['monthly_data'] = $monthlyData;

        // Test JSON encode sebelum return
        $jsonTest = json_encode($responseData, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('JSON encode error: ' . json_last_error_msg());
            \Log::error('Problematic data: ', $responseData);

            return json(200, true, 'Berhasil Disimpan', 'Risk header berhasil disimpan.', [
                'id' => $riskHeader->id,
                'created_by' => $riskHeader->created_by,
                'created_by_name' => 'Unknown User',
                'year' => $riskHeader->year,
                'department_id' => $riskHeader->department_id,
                'risk_code' => $riskHeader->risk_code,
                'created_at' => $riskHeader->created_at,
                'updated_at' => $riskHeader->updated_at,
            ]);
        }

        return json(200, true, 'Berhasil Disimpan', 'Risk header berhasil disimpan.', $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

public function update(Request $request, $id)
{
    $data = TrRiskHeader::find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
    }

    // Check if any monthly data is finalized
    $finalizedMonthly = TrRiskMonthly::where('header_id', $id)
        ->where('is_finalize', true)
        ->exists();

    if ($finalizedMonthly) {
        return json(400, false, 'Data Tidak Dapat Diubah', 'Terdapat data monthly yang sudah difinalisasi. Header tidak dapat diubah.', null);
    }

    $validator = Validator::make($request->all(), [
        'risk_code' => 'required|exists:mst_risk_code,id',
        // 'process_code' => 'required|integer',
        'jenis_risiko' => 'required|string',
        'sasaran' => 'required|string',
        'peristiwa_risiko' => 'required|string',
        'penyebab_risiko' => 'required|string',
        'dampak_risiko' => 'required|string',
        'inherent_risk_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'residual_target_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        'internal_control' => 'required|string',
        'target_satu_tahun_option' => 'nullable|exists:mst_option,id',
        'target_satu_tahun_notes' => 'nullable|string',
        'target_quantitative_satu_tahun' => 'nullable|numeric',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
        'department_id' => 'required|exists:mst_department,id',
        'year' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
    }

    try {
        DB::beginTransaction();
        $updateData = $request->all();
        $updateData['updated_by'] = auth()->id();

        // Auto-fill position from mst_option if target_satu_tahun_option is provided
        if (!empty($updateData['target_satu_tahun_option'])) {
            $option = MstOption::find($updateData['target_satu_tahun_option']);
            if ($option) {
                $updateData['target_satu_tahun_position'] = $option->position;
            }
        } else {
            $updateData['target_satu_tahun_position'] = null;
        }

        if ($request->inherent_risk_level_dampak != $data->inherent_risk_level_dampak ||
            $request->inherent_risk_level_kemungkinan != $data->inherent_risk_level_kemungkinan) {

            $irHeatmap = MstHeatmap::with('riskRange')
                ->where('dampak', $updateData['inherent_risk_level_dampak'])
                ->where('kemungkinan', $updateData['inherent_risk_level_kemungkinan'])
                ->first();

            if (!$irHeatmap) {
                return json(400, false, 'IR Tidak Ditemukan', 'Kombinasi IR tidak ditemukan.', null);
            }

            $updateData['inherent_risk_posisi_risiko'] = $irHeatmap->result;
            $updateData['inherent_risk_level_risiko'] = $irHeatmap->riskRange->name ?? null;
        }

        if ($request->residual_target_level_dampak != $data->residual_target_level_dampak ||
            $request->residual_target_level_kemungkinan != $data->residual_target_level_kemungkinan) {

            $rrHeatmap = MstHeatmap::with('riskRange')
                ->where('dampak', $updateData['residual_target_level_dampak'])
                ->where('kemungkinan', $updateData['residual_target_level_kemungkinan'])
                ->first();

            if (!$rrHeatmap) {
                return json(400, false, 'RR Tidak Ditemukan', 'Kombinasi RR tidak ditemukan.', null);
            }

            $updateData['residual_target_posisi_risiko'] = $rrHeatmap->result;
            $updateData['residual_target_level_risiko'] = $rrHeatmap->riskRange->name ?? null;

            TrRiskMonthly::where('header_id', $id)
                ->where('is_finalize', false)
                ->update([
                    'rr_level_dampak' => $updateData['residual_target_level_dampak'],
                    'rr_level_kemungkinan' => $updateData['residual_target_level_kemungkinan'],
                    'rr_posisi_risiko' => $updateData['residual_target_posisi_risiko'],
                    'rr_level_risiko' => $updateData['residual_target_level_risiko'],
                ]);
        }

        $data->update($updateData);
        DB::commit();

        // Load relasi dengan nama yang tepat
        $data->load([
            'riskCode:id,name',
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionTargetSatuTahun:id,name,position',
            'createdBy:id,username',
            'monthlyData' => function($query) {
                $query->orderBy('month', 'asc');
            }
        ]);

        // Gunakan helper get_decrypted_username dan clean_string
        $createdByName = 'Unknown User';
        try {
            $createdByName = get_decrypted_username($data->createdBy);
        } catch (\Throwable $e) {
            \Log::warning("Error handling createdBy: {$e->getMessage()}");
        }

        // Buat response array dengan struktur yang diinginkan - bersihkan setiap field
        $responseData = [
            'id' => $data->id,
            'risk_code' => $data->risk_code,
            'jenis_risiko' => clean_string($data->jenis_risiko),
            'sasaran' => clean_string($data->sasaran),
            'peristiwa_risiko' => clean_string($data->peristiwa_risiko),
            'penyebab_risiko' => clean_string($data->penyebab_risiko),
            'dampak_risiko' => clean_string($data->dampak_risiko),
            'inherent_risk_level_dampak' => $data->inherent_risk_level_dampak,
            'inherent_risk_level_kemungkinan' => $data->inherent_risk_level_kemungkinan,
            'residual_target_level_dampak' => $data->residual_target_level_dampak,
            'residual_target_level_kemungkinan' => $data->residual_target_level_kemungkinan,
            'internal_control' => clean_string($data->internal_control),
            'target_satu_tahun_option' => $data->target_satu_tahun_option,
            'target_satu_tahun_option_name' => $data->optionTargetSatuTahun ? clean_string($data->optionTargetSatuTahun->name) : null,
            'target_satu_tahun_position' => clean_string($data->target_satu_tahun_position),
            'target_satu_tahun_notes' => clean_string($data->target_satu_tahun_notes),
            'target_quantitative_satu_tahun' => number_format($data->target_quantitative_satu_tahun, 0, ',', '.'),
            'biaya_perlakuan_risiko' => number_format($data->biaya_perlakuan_risiko, 0, ',', '.'),
            'department_id' => $data->department_id,
            'year' => $data->year,
            'inherent_risk_posisi_risiko' => $data->inherent_risk_posisi_risiko,
            'inherent_risk_level_risiko' => clean_string($data->inherent_risk_level_risiko),
            'residual_target_posisi_risiko' => $data->residual_target_posisi_risiko,
            'residual_target_level_risiko' => clean_string($data->residual_target_level_risiko),
            'process_code' => $data->process_code ?? null,
            'updated_at' => $data->updated_at,
            'created_at' => $data->created_at,
            'created_by' => $data->created_by,
            'created_by_name' => $createdByName,
        ];

        // Ubah nama key relasi sesuai format yang diinginkan
        $responseData['risk_code'] = $data->riskCode ? [
            'id' => $data->riskCode->id,
            'name' => clean_string($data->riskCode->name)
        ] : null;

        $responseData['ir_dampak'] = $data->irDampak ? [
            'id' => $data->irDampak->id,
            'label' => clean_string($data->irDampak->label)
        ] : null;

        $responseData['ir_kemungkinan'] = $data->irKemungkinan ? [
            'id' => $data->irKemungkinan->id,
            'label' => clean_string($data->irKemungkinan->label)
        ] : null;

        $responseData['rr_dampak'] = $data->rrDampak ? [
            'id' => $data->rrDampak->id,
            'label' => clean_string($data->rrDampak->label)
        ] : null;

        $responseData['rr_kemungkinan'] = $data->rrKemungkinan ? [
            'id' => $data->rrKemungkinan->id,
            'label' => clean_string($data->rrKemungkinan->label)
        ] : null;

        $responseData['department'] = $data->department ? [
            'id' => $data->department->id,
            'name' => clean_string($data->department->name)
        ] : null;

        $responseData['option_target_satu_tahun'] = $data->optionTargetSatuTahun ? [
            'id' => $data->optionTargetSatuTahun->id,
            'name' => clean_string($data->optionTargetSatuTahun->name),
            'position' => clean_string($data->optionTargetSatuTahun->position)
        ] : null;

        // Bersihkan monthly data
        $monthlyData = [];
        if ($data->monthlyData) {
            foreach ($data->monthlyData as $monthly) {
                $monthlyArray = $monthly->toArray();
                // Bersihkan string fields di monthly data
                foreach ($monthlyArray as $key => $value) {
                    if (is_string($value)) {
                        $monthlyArray[$key] = clean_string($value);
                    }
                }
                $monthlyData[] = $monthlyArray;
            }
        }
        $responseData['monthly_data'] = $monthlyData;

        // Test JSON encoding sebelum return
        $jsonTest = json_encode($responseData, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('JSON encode error: ' . json_last_error_msg());
            \Log::error('Problematic data: ', $responseData);

            // Return simplified response jika masih error
            return json(200, true, 'Berhasil Diperbarui', 'Risk header berhasil diperbarui.', [
                'id' => $data->id,
                'created_by' => $data->created_by,
                'created_by_name' => 'Unknown User',
                'year' => $data->year,
                'department_id' => $data->department_id,
                'risk_code' => $data->risk_code,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ]);
        }

        return json(200, true, 'Berhasil Diperbarui', 'Risk header berhasil diperbarui.', $responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Diperbarui', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

    public function destroy($id)
    {
        $data = TrRiskHeader::find($id);

        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
        }

        $finalizedMonthly = TrRiskMonthly::where('header_id', $id)
            ->where('is_finalize', true)
            ->exists();

        if ($finalizedMonthly) {
            return json(400, false, 'Data Tidak Dapat Dihapus', 'Terdapat data monthly yang sudah difinalisasi. Header tidak dapat dihapus.', null);
        }

        try {
            $data->delete();
            return json(200, true, 'Berhasil Dihapus', 'Data risk header berhasil dihapus.', null);
        } catch (\Exception $e) {
            return json(500, false, 'Gagal Dihapus', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

public function monitoring(Request $request)
{
 $user = auth()->user();

    $perPage = $request->input('per_page', 10);

    $query = TrRiskHeader::with([
        'riskCode:id,name',
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
        'headerEntry.riskCode:id,name',
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
    ->orderBy('id', 'asc');

    // Paginate
    $data = $query->paginate($perPage);

    // Mapping data with same structure as index
    $orderedData = collect($data->items())->map(function ($item) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        $monthlyDataMap = [];
        $monthly = [];

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);

            if ($dataBulanan) {
                $target = $dataBulanan->target_quantitative ?? 0;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                $monthlyDataMap[$i] = $dataBulanan;

                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => $dataBulanan->residual_risk_level_risiko,
                    'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                    'residual_risk_posisi_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                    'realization_percentage' => $percentage . '%',
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
                    'residual_risk_posisi_risiko' => null,
                    'residual_risk_posisi_risiko_color' => null,
                    'realization_percentage' => '0%',
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

        $entryData = $item->headerEntry->map(function ($entry) {
            $monthlyEntries = collect();
            for ($i = 1; $i <= 12; $i++) {
                $monthlyEntry = $entry->monthlyEntryData->firstWhere('month', $i);
                if ($monthlyEntry) {
                    $target = $monthlyEntry->target_quantitative ?? 0;
                    $realization = $monthlyEntry->realization_quantitative ?? 0;
                    $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                    $monthlyEntries[] = [
                        'bulan' => $i,
                        'residual_risk_level' => $monthlyEntry->residual_risk_level_risiko,
                        'residual_risk_posisi_risiko' => $monthlyEntry->residual_risk_posisi_risiko,
                        'residual_risk_posisi_risiko_color' => get_color_by_position($monthlyEntry->residual_risk_posisi_risiko),
                        'realization_percentage' => $percentage . '%',
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
                        'residual_risk_posisi_risiko' => null,
                        'residual_risk_posisi_risiko_color' => null,
                        'realization_percentage' => '0%',
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
            'risk_code' => $item->riskCode ?? null,
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
            'target_satu_tahun_notes' => $item->target_satu_tahun_notes ?? '',
            'target_satu_tahun_position' => $item->optionTargetSatuTahun->position ?? 0,
            'target_quantitative_satu_tahun' => number_format($item->target_quantitative_satu_tahun, 0, ',', '.'),

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
            'created_by_name' => get_decrypted_username($item->createdBy),
            'updated_by' => $item->updated_by ?? null,
            'updated_by_name' => get_decrypted_username($item->updatedBy),

            'ir_dampak' => $item->irDampak ?? null,
            'ir_kemungkinan' => $item->irKemungkinan ?? null,
            'rr_dampak' => $item->rrDampak ?? null,
            'rr_kemungkinan' => $item->rrKemungkinan ?? null,
            'department' => $item->department ?? null,

            'monthly_data' => $item->monthlyData->map(function ($dataBulanan) {
                $target = $dataBulanan->target_quantitative ?? 0;
                $realization = $dataBulanan->realization_quantitative ?? 0;
                $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                return [
                    'id' => $dataBulanan->id,
                    'header_id' => $dataBulanan->header_id,
                    'month' => $dataBulanan->month,
                    'risk_code' => $dataBulanan->risk_code,
                    'status_risiko' => $dataBulanan->status_risiko,
                    'process_code' => $dataBulanan->process_code,
                    'start_date' => $dataBulanan->start_date ? $dataBulanan->start_date->format('Y-m-d H:i:s') : null,
                    'expired_date' => $dataBulanan->expired_date ? $dataBulanan->expired_date->format('Y-m-d H:i:s') : null,
                    'realization_quantitative' => $realization,
                    'realization_note' => $dataBulanan->realization_note,
                    'target_quantitative' => $target,
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

}
