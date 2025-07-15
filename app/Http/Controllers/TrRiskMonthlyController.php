<?php

namespace App\Http\Controllers;

use App\Models\TrRiskMonthly;
use App\Models\TrRiskHeader;
use App\Models\MstHeatmap;
use App\Models\MstHeatmapDampak;
use App\Models\MstHeatmapKemungkinan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrRiskMonthlyController extends Controller
{
    public function index()
    {
        $data = TrRiskMonthly::with('header')->get();
        return json(200, true, 'Berhasil', 'Data risk monthly berhasil diambil.', $data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'header_id' => 'required|exists:tr_risk_header,id|unique:tr_risk_monthly,header_id',
            'month' => 'required|integer|min:1|max:12',
            'status_risiko' => 'required|string',
            'start_date' => 'required|date',
            'expired_date' => 'required|date',
            'realization_quantitative' => 'nullable|numeric',
            'realization_option' => 'nullable|exists:mst_option,name',
            'realization_other' => 'nullable|string',
            'realization_note' => 'nullable|string',
            'realization_option_position' => 'nullable|exists:mst_option,position',
            'target_quantitative' => 'nullable|numeric',
            'target_option' => 'nullable|exists:mst_option,name',
            'target_other' => 'nullable|string',
            'target_notes' => 'nullable|string',
            'target_option_position' => 'nullable|exists:mst_option,position',
            'rr_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'rr_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $heatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->rr_level_dampak)
            ->where('kemungkinan', $request->rr_level_kemungkinan)
            ->first();

        if (!$heatmap) {
            return json(422, 'error_heatmap', 'Heatmap Tidak Ditemukan', 'Kombinasi dampak dan kemungkinan tidak ditemukan.', null);
        }

        $data = TrRiskMonthly::create([
            'header_id' => $request->header_id,
            'month' => $request->month,
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date,
            'expired_date' => $request->expired_date,
            'realization_quantitative' => $request->realization_quantitative,
            'realization_option' => $request->realization_option,
            'realization_other' => $request->realization_other,
            'realization_note' => $request->realization_note,
            'realization_option_position' => $request->realization_option_position,
            'target_quantitative' => $request->target_quantitative,
            'target_option' => $request->target_option,
            'target_other' => $request->target_other,
            'target_notes' => $request->target_notes,
            'target_option_position' => $request->target_option_position,
            'rr_level_dampak' => $request->rr_level_dampak,
            'rr_level_kemungkinan' => $request->rr_level_kemungkinan,
            'rr_posisi_risiko' => $heatmap->result,
            'rr_level_risiko' => $heatmap->riskRange->name ?? null,
        ]);

        return json(200, true, 'Berhasil', 'Data risk monthly berhasil disimpan.', $data);
    }

    public function update(Request $request, $id)
    {
        $data = TrRiskMonthly::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'header_id' => ['required', 'exists:tr_risk_header,id', Rule::unique('tr_risk_monthly', 'header_id')->ignore($id)],
            'month' => 'required|integer|min:1|max:12',
            'status_risiko' => 'required|string',
            'start_date' => 'required|date',
            'expired_date' => 'required|date',
            'realization_quantitative' => 'nullable|numeric',
            'realization_option' => 'nullable|string',
            'realization_other' => 'nullable|string',
            'realization_note' => 'nullable|string',
            'realization_option_position' => 'nullable|string',
            'target_quantitative' => 'nullable|numeric',
            'target_option' => 'nullable|string',
            'target_other' => 'nullable|string',
            'target_notes' => 'nullable|string',
            'target_option_position' => 'nullable|string',
            'rr_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'rr_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $heatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->rr_level_dampak)
            ->where('kemungkinan', $request->rr_level_kemungkinan)
            ->first();

        if (!$heatmap) {
            return json(422, false, 'Heatmap Tidak Ditemukan', 'Kombinasi dampak dan kemungkinan tidak ditemukan.', null);
        }

        $data->update([
            'header_id' => $request->header_id,
            'month' => $request->month,
            'status_risiko' => $request->status_risiko,
            'start_date' => $request->start_date,
            'expired_date' => $request->expired_date,
            'realization_quantitative' => $request->realization_quantitative,
            'realization_option' => $request->realization_option,
            'realization_other' => $request->realization_other,
            'realization_note' => $request->realization_note,
            'realization_option_position' => $request->realization_option_position,
            'target_quantitative' => $request->target_quantitative,
            'target_option' => $request->target_option,
            'target_other' => $request->target_other,
            'target_notes' => $request->target_notes,
            'target_option_position' => $request->target_option_position,
            'rr_level_dampak' => $request->rr_level_dampak,
            'rr_level_kemungkinan' => $request->rr_level_kemungkinan,
            'rr_posisi_risiko' => $heatmap->result,
            'rr_level_risiko' => $heatmap->riskRange->name ?? null,
        ]);

        return json(200, true, 'Berhasil', 'Data risk monthly berhasil diperbarui.', $data);
    }

    public function show($id)
    {
        $data = TrRiskMonthly::with('header')->find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Berhasil', 'Detail risk monthly berhasil diambil.', $data);
    }

    public function destroy($id)
    {
        $data = TrRiskMonthly::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();
        return json(200, true, 'Berhasil', 'Data risk monthly berhasil dihapus.', null);
    }
}
