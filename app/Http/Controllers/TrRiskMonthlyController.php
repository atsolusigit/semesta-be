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
    // GET all
    public function index()
    {
        $data = TrRiskMonthly::with('header')->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // POST
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
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal.',
            'errors' => $validator->errors(),
        ], 422);
    }

    // Cari kombinasi dampak + kemungkinan di mst_heatmap
    $heatmap = MstHeatmap::with('riskRange')
        ->where('dampak', $request->rr_level_dampak)
        ->where('kemungkinan', $request->rr_level_kemungkinan)
        ->first();

    if (!$heatmap) {
        return response()->json([
            'success' => false,
            'message' => 'Kombinasi level dampak dan kemungkinan tidak ditemukan di mst_heatmap.',
        ], 422);
    }

    // Buat data
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

    return response()->json([
        'success' => true,
        'message' => 'Data risk monthly berhasil disimpan.',
        'data' => $data,
    ]);
}

// PUT / PATCH
 public function update(Request $request, $id)
{
    $data = TrRiskMonthly::find($id);
    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan.',
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'header_id' => [
            'required',
            'exists:tr_risk_header,id',
            Rule::unique('tr_risk_monthly', 'header_id')->ignore($data->id),
        ],
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
        return response()->json([
            'success' => false,
            'message' => 'Validasi gagal.',
            'errors' => $validator->errors(),
        ], 422);
    }

    // Ambil data heatmap sesuai kombinasi
    $heatmap = MstHeatmap::with('riskRange')
        ->where('dampak', $request->rr_level_dampak)
        ->where('kemungkinan', $request->rr_level_kemungkinan)
        ->first();

    if (!$heatmap) {
        return response()->json([
            'success' => false,
            'message' => 'Kombinasi level dampak dan kemungkinan tidak ditemukan di heatmap.',
        ], 422);
    }

    // Update data
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

    return response()->json([
        'success' => true,
        'message' => 'Data berhasil diupdate.',
        'data' => $data,
    ]);
}


    // GET by ID
    public function show($id)
    {
        $data = TrRiskMonthly::with('header')->find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }


    // DELETE
    public function destroy($id)
    {
        $data = TrRiskMonthly::find($id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }
}

