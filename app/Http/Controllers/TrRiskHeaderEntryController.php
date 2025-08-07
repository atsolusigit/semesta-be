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
use App\Models\MstHeatmap;
use App\Models\MstHeatmapRiskRange;
use App\Models\MstOption;
use Illuminate\Support\Facades\DB;

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

    try {
        DB::beginTransaction();

        $data = $request->all();

        // Auto-fill target_satu_tahun_position
        if (!empty($data['target_satu_tahun_option'])) {
            $option = MstOption::find($data['target_satu_tahun_option']);
            if ($option) {
                $data['target_satu_tahun_position'] = $option->position;
            }
        }

        // Auto-fill inherent risk result & level_risiko
        if (!empty($data['inherent_risk_level_dampak']) && !empty($data['inherent_risk_level_kemungkinan'])) {
            $ir = MstHeatmap::with('riskRange')
                ->where('dampak', $data['inherent_risk_level_dampak'])
                ->where('kemungkinan', $data['inherent_risk_level_kemungkinan'])
                ->first();

            if ($ir) {
                $data['inherent_risk_posisi_risiko'] = $ir->result;
                $data['inherent_risk_level_risiko'] = $ir->riskRange->name ?? null;
            }
        }

        // Auto-fill residual risk result & level_risiko
        if (!empty($data['residual_target_level_dampak']) && !empty($data['residual_target_level_kemungkinan'])) {
            $rr = MstHeatmap::with('riskRange')
                ->where('dampak', $data['residual_target_level_dampak'])
                ->where('kemungkinan', $data['residual_target_level_kemungkinan'])
                ->first();

            if ($rr) {
                $data['residual_target_posisi_risiko'] = $rr->result;
                $data['residual_target_level_risiko'] = $rr->riskRange->name ?? null;
            }
        }

        // Buat instance entry baru dan set process_code otomatis
        $entry = new TrRiskHeaderEntry(
            array_merge(
                $request->only((new TrRiskHeaderEntry)->getFillable()),
                ['tr_risk_header_id' => $headerId],
                [
                    'inherent_risk_posisi_risiko' => $data['inherent_risk_posisi_risiko'] ?? null,
                    'inherent_risk_level_risiko' => $data['inherent_risk_level_risiko'] ?? null,
                    'residual_target_posisi_risiko' => $data['residual_target_posisi_risiko'] ?? null,
                    'residual_target_level_risiko' => $data['residual_target_level_risiko'] ?? null,
                    'target_satu_tahun_position' => $data['target_satu_tahun_position'] ?? null,
                ]
            )
        );

        // Set process_code secara otomatis berdasarkan year dan department_id
        $entry->year = $data['year'] ?? $header->year;
        $entry->department_id = $data['department_id'] ?? $header->department_id;
        $entry->setNextProcessCode()->save();

        // Simpan entry monthly
        $monthlyEntry = TrRiskMonthlyEntry::create([
            'header_id' => $headerId,
            'tr_risk_header_entry_id' => $entry->id,
            'monthly_id' => $monthlyId,
            'month' => $monthly->month,
        ]);

        DB::commit();

        return json(200, true, 'Berhasil Disimpan', 'Data entry berhasil disimpan.', [
            'entry_header' => $entry,
            'monthly_entry' => $monthlyEntry,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $e->getMessage());
    }
}

   public function update(Request $request, $id)
{
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

    try {
        DB::beginTransaction();

        $data = $request->all();

        // Auto-fill posisi target satu tahun
        if (!empty($data['target_satu_tahun_option'])) {
            $option = MstOption::find($data['target_satu_tahun_option']);
            if ($option) {
                $data['target_satu_tahun_position'] = $option->position;
            }
        }

        // Auto-fill inherent risk result & level risiko
        if (!empty($data['inherent_risk_level_dampak']) && !empty($data['inherent_risk_level_kemungkinan'])) {
            $ir = MstHeatmap::with('riskRange')
                ->where('dampak', $data['inherent_risk_level_dampak'])
                ->where('kemungkinan', $data['inherent_risk_level_kemungkinan'])
                ->first();

            if ($ir) {
                $data['inherent_risk_posisi_risiko'] = $ir->result;
                $data['inherent_risk_level_risiko'] = $ir->riskRange->name ?? null;
            }
        }

        // Auto-fill residual risk result & level risiko
        if (!empty($data['residual_target_level_dampak']) && !empty($data['residual_target_level_kemungkinan'])) {
            $rr = MstHeatmap::with('riskRange')
                ->where('dampak', $data['residual_target_level_dampak'])
                ->where('kemungkinan', $data['residual_target_level_kemungkinan'])
                ->first();

            if ($rr) {
                $data['residual_target_posisi_risiko'] = $rr->result;
                $data['residual_target_level_risiko'] = $rr->riskRange->name ?? null;
            }
        }

        $entry->update(array_merge(
            $request->only((new TrRiskHeaderEntry)->getFillable()),
            [
                'inherent_risk_posisi_risiko' => $data['inherent_risk_posisi_risiko'] ?? null,
                'inherent_risk_level_risiko' => $data['inherent_risk_level_risiko'] ?? null,
                'residual_target_posisi_risiko' => $data['residual_target_posisi_risiko'] ?? null,
                'residual_target_level_risiko' => $data['residual_target_level_risiko'] ?? null,
                'target_satu_tahun_position' => $data['target_satu_tahun_position'] ?? null,
            ]
        ));

        DB::commit();

        return json(200, true, 'Berhasil', 'Data entry berhasil diperbarui.', $entry);
    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, false, 'Gagal', 'Terjadi kesalahan saat menyimpan data.', $e->getMessage());
    }
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
