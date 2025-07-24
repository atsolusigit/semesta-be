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
    public function index()
    {
        $data = TrRiskHeader::with([
            'riskCode:id,name',
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionWaktuSelesai:id,name,position',
            'optionWaktuSelesaiPosition:id,name,position',
            'monthlyData' => function($query) {
                $query->orderBy('month', 'asc');
            }
        ])->orderBy('id', 'asc')->get();

        return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'risk_code' => 'required|exists:mst_risk_code,id',
            'process_code' => 'required|string',
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
            // CORRECTED: Field names sesuai dengan model/migration
            // 'target_satu_tahun' => 'nullable|numeric',
            'target_satu_tahun_option' => 'nullable|string|exists:mst_option,name',
            // 'target_satu_tahun_other' => 'nullable|string',
            'target_satu_tahun_notes' => 'nullable|string',
            'target_satu_tahun_position' => 'nullable|string|exists:mst_option,position',
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
                'optionWaktuSelesai:id,name,position',
                'optionWaktuSelesaiPosition:id,name,position',
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

    public function show($id)
    {
        $data = TrRiskHeader::with([
            'riskCode:id,name',
            'irDampak:id,label',
            'irKemungkinan:id,label',
            'rrDampak:id,label',
            'rrKemungkinan:id,label',
            'department:id,name',
            'optionWaktuSelesai:id,name,position',
            'optionWaktuSelesaiPosition:id,name,position',
            'monthlyData' => function($query) {
                $query->orderBy('month', 'asc');
            }
        ])->find($id);

        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data risk header tidak ditemukan.', null);
        }

        return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $data);
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
            'process_code' => 'required|string',
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
            // 'target_satu_tahun' => 'nullable|numeric',
            'target_satu_tahun_option' => 'nullable|string|exists:mst_option,name',
            // 'target_satu_tahun_other' => 'nullable|string',
            'target_satu_tahun_notes' => 'nullable|string',
            'target_satu_tahun_position' => 'nullable|string|exists:mst_option,position',
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

            // Recalculate IR heatmap if dampak/kemungkinan changed
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

            // Recalculate RR heatmap if dampak/kemungkinan changed
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

                // Update all unfinalised monthly data with new RR values
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
                'optionWaktuSelesai:id,name,position',
                'optionWaktuSelesaiPosition:id,name,position',
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

        // Check if any monthly data is finalized
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
}
