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
    $data = TrRiskHeader::with([
        'riskCode:id,name',
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position',
        'monthlyData' => function($query) {
            $query->orderBy('month', 'asc');
        }
    ])
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
    ->orderBy('id', 'asc')
    ->get();

    $orderedData = $data->map(function ($item) {
        // Cari data heatmap berdasarkan dampak dan kemungkinan
        $heatmap = MstHeatmap::where('dampak', $item->residual_target_level_dampak)
            ->where('kemungkinan', $item->residual_target_level_kemungkinan)
            ->first();

        // Generate monthly monitoring data seperti di method monitoring
        $monthly = [];
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $item->monthlyData->firstWhere('month', $i);

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
                ];
            } else {
                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => null,
                    'residual_risk_posisi_risiko' => null,
                    'residual_risk_posisi_risiko_color' => null,
                    'realization_percentage' => '0%',
                    'is_finalized' => false,
                ];
            }
        }

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
            'target_quantitative_satu_tahun' => $item->target_quantitative_satu_tahun ?? 0,

            'biaya_perlakuan_risiko' => $item->biaya_perlakuan_risiko ?? 0,
            'residual_target_level_dampak' => $item->residual_target_level_dampak ?? 0,
            'residual_target_level_kemungkinan' => $item->residual_target_level_kemungkinan ?? 0,
            'residual_target_posisi_risiko' => $item->residual_target_posisi_risiko ?? '',
            'residual_target_level_risiko' => $item->residual_target_level_risiko ?? 0,
            'residual_target_posisi_risiko_color' => $residualTargetColor,

            'department_id' => $item->department_id,
            'year' => $item->year,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,

            'ir_dampak' => $item->irDampak ?? null,
            'ir_kemungkinan' => $item->irKemungkinan ?? null,
            'rr_dampak' => $item->rrDampak ?? null,
            'rr_kemungkinan' => $item->rrKemungkinan ?? null,
            'department' => $item->department ?? null,
            'monthly_data' => $item->monthlyData ?? collect([]),
            'monthly' => $monthly,
        ];
    });

    return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $orderedData);
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
        'monthlyData' => function($query) {
            $query->orderBy('month', 'asc');
        }
    ])->find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
    }

    $heatmap = MstHeatmap::where('dampak', $data->residual_target_level_dampak)
    ->where('kemungkinan', $data->residual_target_level_kemungkinan)
    ->first();

    // Generate monthly monitoring data seperti di method monitoring
    $monthly = [];
    $inherentColor = get_color_by_position($data->inherent_risk_posisi_risiko);
    $residualTargetColor = get_color_by_position($data->residual_target_posisi_risiko);

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
            ];
        } else {
            $monthly[] = [
                'bulan' => $i,
                'residual_risk_level' => null,
                'residual_risk_posisi_risiko' => null,
                'residual_risk_posisi_risiko_color' => null,
                'realization_percentage' => '0%',
                'is_finalized' => false,
            ];
        }
    }

    // Transform untuk mengatur urutan field
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
        'target_quantitative_satu_tahun' => $data->target_quantitative_satu_tahun ?? 0,

        'biaya_perlakuan_risiko' => $data->biaya_perlakuan_risiko ?? 0,
        'residual_target_level_dampak' => $data->residual_target_level_dampak ?? 0,
        'residual_target_level_kemungkinan' => $data->residual_target_level_kemungkinan ?? 0,
        'residual_target_posisi_risiko' => $data->residual_target_posisi_risiko ?? '',
        'residual_target_level_risiko' => $data->residual_target_level_risiko ?? 0,
        'residual_target_posisi_risiko_color' => $residualTargetColor,
        'department_id' => $data->department_id,
        'year' => $data->year,
        'created_at' => $data->created_at,
        'updated_at' => $data->updated_at,

        // Relationships
        'ir_dampak' => $data->irDampak ?? null,
        'ir_kemungkinan' => $data->irKemungkinan ?? null,
        'rr_dampak' => $data->rrDampak ?? null,
        'rr_kemungkinan' => $data->rrKemungkinan ?? null,
        'department' => $data->department ?? null,
        'monthly_data' => $data->monthlyData ?? collect([]),
        'monthly' => $monthly,
    ];

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

            // Auto-fill position from mst_option if target_satu_tahun_option is provided
            if (!empty($data['target_satu_tahun_option'])) {
                $option = MstOption::find($data['target_satu_tahun_option']);
                if ($option) {
                    $data['target_satu_tahun_position'] = $option->position;
                }
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

            $riskHeader->load([
                'riskCode:id,name',
                'irDampak:id,label',
                'irKemungkinan:id,label',
                'rrDampak:id,label',
                'rrKemungkinan:id,label',
                'department:id,name',
                'optionTargetSatuTahun:id,name,position',
                'monthlyData' => function($query) {
                    $query->orderBy('month', 'asc');
                }
            ]);

            return json(200, true, 'Berhasil Disimpan', 'Risk header berhasil disimpan.', $riskHeader);
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

            $data->load([
                'riskCode:id,name',
                'irDampak:id,label',
                'irKemungkinan:id,label',
                'rrDampak:id,label',
                'rrKemungkinan:id,label',
                'department:id,name',
                'optionTargetSatuTahun:id,name,position',
                'monthlyData' => function($query) {
                    $query->orderBy('month', 'asc');
                }
            ]);

            return json(200, true, 'Berhasil Diperbarui', 'Risk header berhasil diperbarui.', $data);
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
    $sortOrder = strtolower($request->get('sort', 'desc')) === 'asc' ? 'asc' : 'desc';

    $headers = TrRiskHeader::with([
        'monthlyData', // ambil semua data bulanan tanpa filter finalize
        'department',
        'riskCode',
        'optionTargetSatuTahun'
    ])
    ->when($request->tahun, function ($q) use ($request) {
        $q->where('year', $request->tahun);
    })
    ->when($request->peristiwa, function ($q) use ($request) {
        $q->where('peristiwa_risiko', 'like', '%' . $request->peristiwa . '%');
    })
    ->when($request->unit_kerja, function ($q) use ($request) {
        $q->whereHas('department', function ($q2) use ($request) {
            $q2->where('name', 'like', '%' . $request->unit_kerja . '%');
        });
    })
    ->orderBy('year', $sortOrder)
    ->get();

    $data = $headers->map(function ($header) {
        $monthly = [];

        $inherentColor = get_color_by_position($header->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($header->residual_target_posisi_risiko);

        for ($i = 1; $i <= 12; $i++) {
            $dataBulanan = $header->monthlyData->firstWhere('month', $i);

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
                ];
            } else {
                $monthly[] = [
                    'bulan' => $i,
                    'residual_risk_level' => null,
                    'residual_risk_posisi_risiko' => null,
                    'residual_risk_posisi_risiko_color' => null,
                    'realization_percentage' => '0%',
                    'is_finalized' => false,
                ];
            }
        }

        return [
            'id' => $header->id,
            'risk_code' => $header->riskCode->code ?? '-',
            'tahun' => $header->year,
            'peristiwa' => $header->peristiwa_risiko,
            'inherent_risk_level' => $header->inherent_risk_level_risiko,
            'inherent_risk_posisi_risiko' => $header->inherent_risk_posisi_risiko,
            'inherent_risk_posisi_risiko_color' => $inherentColor,
            'target_risk_level' => $header->residual_target_level_risiko,
            'residual_target_posisi_risiko' => $header->residual_target_posisi_risiko,
            'residual_target_posisi_risiko_color' => $residualTargetColor,
            'target_quantitative_satu_tahun' => $header->target_quantitative_satu_tahun,
            'unit_kerja' => $header->department->name ?? '-',
            'monthly' => $monthly,
        ];
    });

    return json(200, true, 'Data Monitoring Risk Profile Bulanan', 'Data Monitoring', $data);
}

}
