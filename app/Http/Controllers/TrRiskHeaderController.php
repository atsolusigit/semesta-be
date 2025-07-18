<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\TrRiskHeader;
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
        ])->orderBy('id', 'asc')->get();

        return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'risk_code' => 'required|exists:mst_risk_code,id',
            'process_code' => 'required|string',
            'prefix_risiko' => 'required|string',
            'sasaran' => 'required|string',
            'permasalahan_risiko' => 'required|string',
            'dampak' => 'required|string',
            'dampak_risiko' => 'required|string',

            'ir_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'ir_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'rr_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'rr_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',

            'internal_control' => 'required|string',
            'target_waktu_selesai' => 'nullable|date',
            'target_waktu_selesai_option' => 'nullable|string|exists:mst_option,name',
            'target_waktu_selesai_other' => 'nullable|string',
            'target_waktu_selesai_notes' => 'nullable|string',
            'target_waktu_selesai_position' => 'nullable|string|exists:mst_option,position',
            'biaya_pertolongan_risiko' => 'nullable|numeric',
            'department_id' => 'required|exists:mst_department,id',
            'year' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return json(422, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = $request->all();

        $irHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $data['ir_level_dampak'])
            ->where('kemungkinan', $data['ir_level_kemungkinan'])
            ->first();

        if (!$irHeatmap) {
            return json(422, false, 'IR Tidak Ditemukan', 'Kombinasi IR level dampak dan kemungkinan tidak ditemukan di heatmap.', null);
        }

        $data['ir_posisi_risiko'] = $irHeatmap->result;
        $data['ir_level_risiko'] = $irHeatmap->riskRange->name ?? null;

        $rrHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $data['rr_level_dampak'])
            ->where('kemungkinan', $data['rr_level_kemungkinan'])
            ->first();

        if (!$rrHeatmap) {
            return json(422, false, 'RR Tidak Ditemukan', 'Kombinasi RR level dampak dan kemungkinan tidak ditemukan di heatmap.', null);
        }

        $data['rr_posisi_risiko'] = $rrHeatmap->result;
        $data['rr_level_risiko'] = $rrHeatmap->riskRange->name ?? null;

        $riskHeader = TrRiskHeader::create($data);

        return json(200, true, 'Berhasil Disimpan', 'Risk header berhasil disimpan.', $riskHeader);
    }

    public function update(Request $request, $id)
    {
        $risk = TrRiskHeader::find($id);

        if (!$risk) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $rules = [
            'process_code' => 'required|string',
            'prefix_risiko' => 'required|string',
            'sasaran' => 'required|string',
            'permasalahan_risiko' => 'required|string',
            'dampak' => 'required|string',
            'dampak_risiko' => 'required|string',
            'ir_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'ir_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'internal_control' => 'required|string',
            'target_waktu_selesai' => 'required|date',
            'target_waktu_selesai_option' => 'nullable|exists:mst_option,name',
            'target_waktu_selesai_other' => 'nullable|string',
            'target_waktu_selesai_notes' => 'nullable|string',
            'target_waktu_selesai_position' => 'nullable|exists:mst_option,position',
            'biaya_pertolongan_risiko' => 'required|numeric',
            'rr_level_dampak' => 'required|exists:mst_heatmap_dampak,id',
            'rr_level_kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'department_id' => 'required|integer',
            'year' => 'required|integer',
        ];

        if ($request->has('risk_code') && $risk->risk_code != $request->risk_code) {
            $rules['risk_code'] = [
                'required',
                'exists:mst_risk_code,id',
                'unique:tr_risk_header,risk_code,' . $risk->id . ',id',
            ];
        } elseif ($request->has('risk_code')) {
            $rules['risk_code'] = [
                'required',
                'exists:mst_risk_code,id'
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return json(422, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $updateData = $request->only([
            'process_code', 'prefix_risiko', 'sasaran', 'permasalahan_risiko',
            'dampak', 'dampak_risiko', 'ir_level_dampak', 'ir_level_kemungkinan',
            'internal_control', 'target_waktu_selesai', 'target_waktu_selesai_option',
            'target_waktu_selesai_other', 'target_waktu_selesai_notes',
            'target_waktu_selesai_position', 'biaya_pertolongan_risiko',
            'rr_level_dampak', 'rr_level_kemungkinan', 'department_id', 'year'
        ]);

        if ($request->has('risk_code')) {
            $updateData['risk_code'] = (string) $request->risk_code;
        }

        $irHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->ir_level_dampak)
            ->where('kemungkinan', $request->ir_level_kemungkinan)
            ->first();

        if (!$irHeatmap) {
            return json(422, false, 'IR Tidak Ditemukan', 'Kombinasi IR (Inherent Risk) antara dampak dan kemungkinan tidak tersedia dalam heatmap.', null);
        }

        $updateData['ir_posisi_risiko'] = $irHeatmap->result;
        $updateData['ir_level_risiko'] = $irHeatmap->riskRange->name ?? null;

        $rrHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->rr_level_dampak)
            ->where('kemungkinan', $request->rr_level_kemungkinan)
            ->first();

        if (!$rrHeatmap) {
            return json(422, false, 'RR Tidak Ditemukan', 'Kombinasi RR (Residual Risk) antara dampak dan kemungkinan tidak tersedia dalam heatmap.', null);
        }

        $updateData['rr_posisi_risiko'] = $rrHeatmap->result;
        $updateData['rr_level_risiko'] = $rrHeatmap->riskRange->name ?? null;

        $risk->update($updateData);

        return json(200, true, 'Berhasil Diperbarui', 'Risk header berhasil diupdate.', $risk->fresh());
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
        ])->find($id);

        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail risk header berhasil diambil.', $data);
    }

    public function destroy($id)
    {
        $risk = TrRiskHeader::find($id);

        if (!$risk) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        try {
            $risk->delete();
            return json(200, true, 'Berhasil Dihapus', 'Risk header berhasil dihapus.', null);
        } catch (\Exception $e) {
            return json(500, false, 'Gagal Dihapus', 'Gagal menghapus risk header.', $e->getMessage());
        }
    }
}
