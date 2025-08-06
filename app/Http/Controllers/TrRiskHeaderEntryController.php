<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use App\Models\TrRiskHeaderEntry;
use App\Models\TrRiskMonthly;
use App\Models\TrRiskMonthlyEntry;
use App\Models\MstRiskCode;
use App\Http\Middleware\RoleAccessMiddleware;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TrRiskHeaderEntryController extends Controller
{
    public function index($headerId)
    {
        $entries = TrRiskHeaderEntry::with('header')
            ->where('tr_risk_header_id', $headerId)
            ->latest()
            ->get();

        return json(200, true, 'Data Entry', 'Data berhasil diambil', $entries);
    }

     public function show($id)
    {
        $data = TrRiskHeaderEntry::with('header')->find($id);
        if (!$data) {
            return json(404, false, 'Not Found', 'Data tidak ditemukan', null);
        }

        return json(200, true, 'Detail Entry', 'Data berhasil diambil', $data);
    }

 public function store(Request $request, $headerId, $monthlyId)
{
    $header = TrRiskHeader::find($headerId);
    if (!$header) {
        return json(404, false, 'Not Found', 'Risk Header tidak ditemukan.', null);
    }

    if ($header->is_finalize) {
        return json(403, false, 'Ditolak', 'Data tidak bisa ditambahkan karena risk header sudah final.', null);
    }

    $monthly = TrRiskMonthly::where('id', $monthlyId)
        ->where('header_id', $headerId)
        ->first();

    if (!$monthly) {
        return json(404, false, 'Not Found', 'Data monthly tidak ditemukan atau tidak sesuai dengan header.', null);
    }

    $validator = Validator::make($request->all(), [
        'risk_code' => 'nullable|exists:mst_risk_code,id',
        'process_code' => 'nullable|string',
        'jenis_risiko' => 'nullable|string',
        'sasaran' => 'nullable|string',
        'peristiwa_risiko' => 'nullable|string',
        'penyebab_risiko' => 'nullable|string',
        'dampak_risiko' => 'nullable|string',
        'inherent_risk_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
        'inherent_risk_posisi_risiko' => 'nullable|string',
        'inherent_risk_level_risiko' => 'nullable|string',
        'internal_control' => 'nullable|string',
        'target_satu_tahun_option' => 'nullable|exists:mst_option,id',
        'target_satu_tahun_notes' => 'nullable|string',
        'target_satu_tahun_position' => 'nullable|string',
        'target_quantitative_satu_tahun' => 'nullable|numeric',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
        'residual_target_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
        'residual_target_posisi_risiko' => 'nullable|integer',
        'residual_target_level_risiko' => 'nullable|string',
        'department_id' => 'nullable|exists:mst_department,id',
        'year' => 'nullable|integer',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', $validator->errors()->first(), null);
    }

    $entry = TrRiskHeaderEntry::create(array_merge(
        $request->only((new TrRiskHeaderEntry)->getFillable()),
        ['tr_risk_header_id' => $headerId]
    ));

    $monthlyEntry = TrRiskMonthlyEntry::create([
        'header_id' => $headerId,
        'tr_risk_header_entry_id' => $entry->id,
        'monthly_id' => $monthlyId,
        'month' => $monthly->month,
    ]);

    return json(200, true, 'Berhasil Disimpan', 'Data entry berhasil disimpan.', [
        'entry_header' => $entry,
        'monthly_entry' => $monthlyEntry,
    ]);
}


   public function update(Request $request, $id)
{
    // Ambil data entry beserta relasi header-nya
    $entry = TrRiskHeaderEntry::with('header')->find($id);
    if (!$entry) {
        return json(404, false, 'Not Found', 'Data entry tidak ditemukan.', null);
    }

    $header = $entry->header;
    if (!$header) {
        return json(404, false, 'Not Found', 'Risk header tidak ditemukan.', null);
    }

    if ($header->is_finalize) {
        return json(403, false, 'Ditolak', 'Data tidak dapat diubah karena risk header sudah difinalisasi.', null);
    }

    $validator = Validator::make($request->all(), [
        'risk_code' => 'nullable|exists:mst_risk_code,id',
        'process_code' => 'nullable|string',
        'jenis_risiko' => 'nullable|string',
        'sasaran' => 'nullable|string',
        'peristiwa_risiko' => 'nullable|string',
        'penyebab_risiko' => 'nullable|string',
        'dampak_risiko' => 'nullable|string',
        'inherent_risk_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
        'inherent_risk_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
        'inherent_risk_posisi_risiko' => 'nullable|string',
        'inherent_risk_level_risiko' => 'nullable|string',
        'internal_control' => 'nullable|string',
        'target_satu_tahun_option' => 'nullable|exists:mst_option,id',
        'target_satu_tahun_notes' => 'nullable|string',
        'target_satu_tahun_position' => 'nullable|string',
        'target_quantitative_satu_tahun' => 'nullable|numeric',
        'biaya_perlakuan_risiko' => 'nullable|numeric',
        'residual_target_level_dampak' => 'nullable|exists:mst_heatmap_dampak,id',
        'residual_target_level_kemungkinan' => 'nullable|exists:mst_heatmap_kemungkinan,id',
        'residual_target_posisi_risiko' => 'nullable|integer',
        'residual_target_level_risiko' => 'nullable|string',
        'department_id' => 'nullable|exists:mst_department,id',
        'year' => 'nullable|integer',
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', $validator->errors()->first(), null);
    }

    // Update hanya kolom yang boleh diisi
    $entry->update($request->only((new TrRiskHeaderEntry)->getFillable()));

    return json(200, true, 'Berhasil', 'Data entry berhasil diperbarui.', $entry);
}


    public function destroy($id)
    {
        $data = TrRiskHeaderEntry::find($id);
        if (!$data) {
            return json(404, false, 'Not Found', 'Data tidak ditemukan', null);
        }

        $header = $data->header;
        if ($header && $header->is_finalize) {
            return json(403, false, 'Ditolak', 'Data tidak bisa dihapus karena risk header sudah final.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Data entry berhasil dihapus.', null);
    }

    public function duplicateFromHeader($headerId)
{
    $header = TrRiskHeader::find($headerId);
    if (!$header) {
        return json(404, false, 'Not Found', 'Data Risk Header tidak ditemukan.', null);
    }

    try {
        $entry = TrRiskHeaderEntry::create([
            'risk_code' => $header->risk_code,
            'process_code' => $header->process_code,
            'jenis_risiko' => $header->jenis_risiko,
            'sasaran' => $header->sasaran,
            'peristiwa_risiko' => $header->peristiwa_risiko,
            'penyebab_risiko' => $header->penyebab_risiko,
            'dampak_risiko' => $header->dampak_risiko,
            'inherent_risk_level_dampak' => $header->inherent_risk_level_dampak,
            'inherent_risk_level_kemungkinan' => $header->inherent_risk_level_kemungkinan,
            'inherent_risk_posisi_risiko' => $header->inherent_risk_posisi_risiko,
            'inherent_risk_level_risiko' => $header->inherent_risk_level_risiko,
            'internal_control' => $header->internal_control,
            'target_satu_tahun_option' => $header->target_satu_tahun_option,
            'target_satu_tahun_notes' => $header->target_satu_tahun_notes,
            'target_satu_tahun_position' => $header->target_satu_tahun_position,
            'target_quantitative_satu_tahun' => $header->target_quantitative_satu_tahun,
            'biaya_perlakuan_risiko' => $header->biaya_perlakuan_risiko,
            'residual_target_level_dampak' => $header->residual_target_level_dampak,
            'residual_target_level_kemungkinan' => $header->residual_target_level_kemungkinan,
            'residual_target_posisi_risiko' => $header->residual_target_posisi_risiko,
            'residual_target_level_risiko' => $header->residual_target_level_risiko,
            'department_id' => $header->department_id,
            'year' => $header->year,
        ]);

        return json(200, true, 'Berhasil', 'Entry berhasil dibuat dari Risk Header.', $entry);
    } catch (\Exception $e) {
        return json(500, false, 'Error', 'Terjadi kesalahan saat menduplikasi data: ' . $e->getMessage(), null);
    }
}

}
