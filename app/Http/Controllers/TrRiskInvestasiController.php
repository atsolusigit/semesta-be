<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\TrRiskInvestasi;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class TrRiskInvestasiController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = (int) $request->input('per_page', 10);
        $sortBy    = $request->input('sortBy');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = TrRiskInvestasi::query()
        ->with([
            'investasi:erkap_id,department_name,nama_investasi,jenis_investasi,kategori_investasi,year,nilai_rkap,nilai_revisi,unit_kerja_id',
            'approvedByUser:id,username,name'
        ]);


        if ($request->filled('tahun')) {
            $query->whereHas('investasi', fn($q) => $q->where('year', (int) $request->tahun));
        }
        if ($request->filled('jenis_investasi')) {
            $ji = $request->jenis_investasi;
            $query->whereHas('investasi', fn($q) => $q->where('jenis_investasi', 'like', "%{$ji}%"));
        }
        if ($request->filled('department_name')) {
            $dn = $request->department_name;
            $query->whereHas('investasi', fn($q) => $q->where('department_name', 'like', "%{$dn}%"));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($qq) use ($s) {
                $qq->where('kategori_risiko', 'like', "%{$s}%")
                ->orWhere('sub_kategori_risiko', 'like', "%{$s}%")
                ->orWhere('sasaran', 'like', "%{$s}%")
                ->orWhere('peristiwa_risiko', 'like', "%{$s}%")
                ->orWhere('penyebab_risiko', 'like', "%{$s}%")
                ->orWhereHas('investasi', function ($qh) use ($s) {
                    $qh->where('nama_investasi', 'like', "%{$s}%")
                        ->orWhere('department_name', 'like', "%{$s}%");
                });
            });
        }


        $sortMap = [
            'tahun'        => 'ri.year',
            'nilai'        => 'ri.nilai_rkap',
            'nilai_erkap'  => 'ri.nilai_rkap',
            'nilai_revisi' => 'ri.nilai_revisi',
        ];

        if (isset($sortMap[$sortBy])) {
            $query->leftJoin('rencana_investasi as ri', 'ri.erkap_id', '=', 'tr_risk_investasi.erkap_id')
                ->select('tr_risk_investasi.*')
                ->orderBy($sortMap[$sortBy], $sortOrder);
        } else {
            $query->orderBy('tr_risk_investasi.id', $sortOrder);
        }


        $data = $query->paginate($perPage);

        if (empty($data->items())) {
            return json(404, false, 'Tidak Ada Data', 'Risk Profile Investasi tidak ditemukan.', null);
        }

        $res = collect($data->items())->map(function ($it) {
            return [
                'id' => $it->id,
                'erkap_id' => $it->erkap_id,
                'kategori_risiko' => $it->kategori_risiko,
                'sub_kategori_risiko' => $it->sub_kategori_risiko,
                'sasaran' => $it->sasaran,
                'peristiwa_risiko' => $it->peristiwa_risiko,
                'penyebab_risiko' => $it->penyebab_risiko,
                'dampak_inherent' => $it->dampak_inherent,
                'dampak_risiko_awal' => $it->dampak_risiko_awal,
                'kemungkinan_awal' => $it->kemungkinan_awal,
                'eksposure_level_awal' => $it->eksposure_level_awal,
                'eksposure_ltmh_awal' => $it->eksposure_ltmh_awal,
                'internal_external' => $it->internal_external,
                'mitigasi_risiko' => $it->mitigasi_risiko,
                'dampak_residual' => $it->dampak_residual,
                'dampak_risiko_akhir' => $it->dampak_risiko_akhir,
                'kemungkinan_akhir' => $it->kemungkinan_akhir,
                'eksposure_level_akhir' => $it->eksposure_level_akhir,
                'eksposure_ltmh_akhir' => $it->eksposure_ltmh_akhir,
                'biaya_mitigasi_risiko' => $it->biaya_mitigasi_risiko,
                'status' => $it->status ?? 'draft',
                'approval_notes' => $it->approval_notes,
                'approved_by' => $it->approved_by,
                'approved_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
                'approved_at' => optional($it->approved_at)->toISOString(),
                'vp_menrisk_note' => $it->vp_menrisk_note,
                'vp_menrisk_by' => $it->vp_menrisk_by,
                'vp_menrisk_at' => optional($it->vp_menrisk_at)->toISOString(),
                'menrisk_note' => $it->menrisk_note,
                'menrisk_by' => $it->menrisk_by,
                'menrisk_at' => optional($it->menrisk_at)->toISOString(),
                'created_by' => $it->created_by,
                'updated_by' => $it->updated_by,
                'created_at' => optional($it->created_at)->toISOString(),
                'updated_at' => optional($it->updated_at)->toISOString(),
                'rencana_investasi' => [
                    'id' => optional($it->investasi)->id,
                    'erkap_id' => optional($it->investasi)->erkap_id,
                    'nama_investasi' => optional($it->investasi)->nama_investasi,
                    'department_name' => optional($it->investasi)->department_name,
                    'jenis_investasi' => optional($it->investasi)->jenis_investasi,
                    'kategori_investasi' => optional($it->investasi)->kategori_investasi,
                    'year' => optional($it->investasi)->year,
                    'nilai_rkap' => optional($it->investasi)->nilai_rkap,
                    'nilai_revisi' => optional($it->investasi)->nilai_revisi,
                    'unit_kerja_id' => optional($it->investasi)->unit_kerja_id,
                ],
            ];
        });

        $payload = clean_recursive([
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'data' => $res,
        ]);

        return json(200, true, 'Data Ditemukan', 'Data risk profile investasi berhasil diambil.', $payload);
    }

    public function show($id)
    {
        $it = TrRiskInvestasi::with([
            'investasi:erkap_id,department_name,nama_investasi,jenis_investasi,kategori_investasi,year,nilai_rkap,nilai_revisi,unit_kerja_id',
            'approvedByUser:id,username,name'
        ])->find($id);

        if (!$it) {
            return json(404, false, 'Tidak Ditemukan', 'Risk Profile Investasi tidak ditemukan.', null);
        }

        $resp = [
            'id' => $it->id,
            'erkap_id' => $it->erkap_id,
            'kategori_risiko' => $it->kategori_risiko,
            'sub_kategori_risiko' => $it->sub_kategori_risiko,
            'sasaran' => $it->sasaran,
            'peristiwa_risiko' => $it->peristiwa_risiko,
            'penyebab_risiko' => $it->penyebab_risiko,
            'dampak_inherent' => $it->dampak_inherent,
            'dampak_risiko_awal' => $it->dampak_risiko_awal,
            'kemungkinan_awal' => $it->kemungkinan_awal,
            'eksposure_level_awal' => $it->eksposure_level_awal,
            'eksposure_ltmh_awal' => $it->eksposure_ltmh_awal,
            'internal_external' => $it->internal_external,
            'mitigasi_risiko' => $it->mitigasi_risiko,
            'dampak_residual' => $it->dampak_residual,
            'dampak_risiko_akhir' => $it->dampak_risiko_akhir,
            'kemungkinan_akhir' => $it->kemungkinan_akhir,
            'eksposure_level_akhir' => $it->eksposure_level_akhir,
            'eksposure_ltmh_akhir' => $it->eksposure_ltmh_akhir,
            'biaya_mitigasi_risiko' => $it->biaya_mitigasi_risiko,
            'status' => $it->status ?? 'draft',
            'approval_notes' => $it->approval_notes,
            'approved_by' => $it->approved_by,
            'approved_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
            'approved_at' => optional($it->approved_at)->toISOString(),
            'rencana_investasi' => [
                'id' => optional($it->investasi)->id,
                'erkap_id' => optional($it->investasi)->erkap_id,
                'nama_investasi' => optional($it->investasi)->nama_investasi,
                'department_name' => optional($it->investasi)->department_name,
                'jenis_investasi' => optional($it->investasi)->jenis_investasi,
                'kategori_investasi' => optional($it->investasi)->kategori_investasi,
                'year' => optional($it->investasi)->year,
                'nilai_rkap' => optional($it->investasi)->nilai_rkap,
                'nilai_revisi' => optional($it->investasi)->nilai_revisi,
                'unit_kerja_id' => optional($it->investasi)->unit_kerja_id,
            ],
        ];

        return json(200, true, 'Data Ditemukan', 'Data risk profile investasi berhasil diambil.', $resp);
    }

    public function store(Request $request)
    {
        $result = check_role(auth()->user(), [3]);
        if ($result !== true) return $result;

        $validator = Validator::make($request->all(), [
            'erkap_id' => 'required|integer|exists:rencana_investasi,erkap_id',
            'kategori_risiko' => 'nullable|string',
            'sub_kategori_risiko' => 'nullable|string',
            'sasaran' => 'nullable|string',
            'peristiwa_risiko' => 'nullable',
            'penyebab_risiko' => 'nullable',
            'dampak_inherent' => 'nullable|string',
            'dampak_risiko_awal' => 'nullable|integer',
            'kemungkinan_awal' => 'nullable|integer',
            'eksposure_level_awal' => 'nullable|integer',
            'eksposure_ltmh_awal' => 'nullable|string',
            'internal_external' => 'nullable',
            'mitigasi_risiko' => 'nullable',
            'dampak_residual' => 'nullable|string',
            'dampak_risiko_akhir' => 'nullable|integer',
            'kemungkinan_akhir' => 'nullable|integer',
            'eksposure_level_akhir' => 'nullable|integer',
            'eksposure_ltmh_akhir' => 'nullable|string',
            'biaya_mitigasi_risiko' => 'nullable|numeric',
            'status' => 'nullable|string',
        ]);
        if ($validator->fails()) return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());

        $exists = TrRiskInvestasi::where('erkap_id', $request->erkap_id)->exists();
        if ($exists) return json(409, false, 'Sudah Ada', 'Risk Profile untuk Rencana Investasi ini sudah dibuat.', null);

        try {
            DB::beginTransaction();

            $toArray = fn($v) => is_array($v) ? $v : ((is_null($v) || $v === '') ? null : [$v]);

            $payload = $request->only([
                'erkap_id','kategori_risiko','sub_kategori_risiko','sasaran',
                'dampak_inherent','dampak_risiko_awal','kemungkinan_awal','eksposure_level_awal','eksposure_ltmh_awal',
                'dampak_residual','dampak_risiko_akhir','kemungkinan_akhir','eksposure_level_akhir','eksposure_ltmh_akhir',
                'biaya_mitigasi_risiko','status'
            ]);

            $payload['peristiwa_risiko'] = $toArray($request->peristiwa_risiko);
            $payload['penyebab_risiko'] = $toArray($request->penyebab_risiko);
            $payload['internal_external'] = $toArray($request->internal_external);
            $payload['mitigasi_risiko'] = $toArray($request->mitigasi_risiko);
            $payload['status'] = $payload['status'] ?? 'draft';
            $payload['created_by'] = auth()->id();

            $it = TrRiskInvestasi::create($payload);

            DB::commit();

            return json(201, true, 'Berhasil Disimpan', 'Risk Profile Investasi berhasil dibuat.', $it->only(['id','erkap_id','status']));

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $result = check_role(auth()->user(), [1,2,3]);
        if ($result !== true) return $result;

        $it = TrRiskInvestasi::find($id);
        if (!$it) return json(404, false, 'Tidak Ditemukan', 'Risk Profile Investasi tidak ditemukan.', null);

        $validator = Validator::make($request->all(), [
            'kategori_risiko' => 'nullable|string',
            'sub_kategori_risiko' => 'nullable|string',
            'sasaran' => 'nullable|string',
            'peristiwa_risiko' => 'nullable',
            'penyebab_risiko' => 'nullable',
            'dampak_inherent' => 'nullable|string',
            'dampak_risiko_awal' => 'nullable|integer',
            'kemungkinan_awal' => 'nullable|integer',
            'eksposure_level_awal' => 'nullable|integer',
            'eksposure_ltmh_awal' => 'nullable|string',
            'internal_external' => 'nullable',
            'mitigasi_risiko' => 'nullable',
            'dampak_residual' => 'nullable|string',
            'dampak_risiko_akhir' => 'nullable|integer',
            'kemungkinan_akhir' => 'nullable|integer',
            'eksposure_level_akhir' => 'nullable|integer',
            'eksposure_ltmh_akhir' => 'nullable|string',
            'biaya_mitigasi_risiko' => 'nullable|numeric',
            'status' => 'nullable|string',
        ]);
        if ($validator->fails()) return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());

        try {
            DB::beginTransaction();

            $toArray = fn($v) => is_array($v) ? $v : ((is_null($v) || $v === '') ? null : [$v]);

            $payload = $request->only([
                'kategori_risiko','sub_kategori_risiko','sasaran',
                'dampak_inherent','dampak_risiko_awal','kemungkinan_awal','eksposure_level_awal','eksposure_ltmh_awal',
                'dampak_residual','dampak_risiko_akhir','kemungkinan_akhir','eksposure_level_akhir','eksposure_ltmh_akhir',
                'biaya_mitigasi_risiko','status'
            ]);

            if ($request->has('peristiwa_risiko')) $payload['peristiwa_risiko'] = $toArray($request->peristiwa_risiko);
            if ($request->has('penyebab_risiko')) $payload['penyebab_risiko'] = $toArray($request->penyebab_risiko);
            if ($request->has('internal_external')) $payload['internal_external'] = $toArray($request->internal_external);
            if ($request->has('mitigasi_risiko')) $payload['mitigasi_risiko'] = $toArray($request->mitigasi_risiko);

            if (!empty($payload)) {
                $payload['updated_by'] = auth()->id();
                $it->update($payload);
            }

            DB::commit();

            return json(200, true, 'Berhasil Diperbarui', 'Risk Profile Investasi berhasil diupdate.', $it->only(['id','erkap_id','status']));

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }

    public function destroy($id)
    {
        $result = check_role(auth()->user(), [1,2]);
        if ($result !== true) return $result;

        $it = TrRiskInvestasi::find($id);
        if (!$it) return json(404, false, 'Tidak Ditemukan', 'Risk Profile Investasi tidak ditemukan.', null);

        try {
            DB::beginTransaction();

            $it->delete();

            DB::commit();

            return json(200, true, 'Berhasil Dihapus', 'Risk Profile Investasi berhasil dihapus.', ['deleted_id' => $id]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Hapus', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['approval_notes' => 'nullable|string']);
        if ($validator->fails()) return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());

        try {
            DB::beginTransaction();

            $it = TrRiskInvestasi::find($id);
            if (!$it) return json(404, false, 'Tidak Ditemukan', 'Risk Profile Investasi tidak ditemukan.', null);

            $user = auth()->user();
            $roleId = (int) ($user->role_id ?? 0);
            if (!in_array($roleId, [1,2], true)) return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki hak untuk menyetujui.', null);

            if ($it->status !== 'submit') {
                return json(400, false, 'Status Tidak Valid', 'Hanya data submit yang dapat disetujui.', ['current_status' => $it->status]);
            }

            $notes = (string) ($request->approval_notes ?? '');
            try { $notes = clean_string($notes); } catch (\Throwable $e) { $notes = mb_convert_encoding($notes, 'UTF-8', 'UTF-8'); }

            $it->update([
                'status' => 'approved',
                'approval_notes' => $notes,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            DB::commit();

            $it->load('approvedByUser:id,username,name');

            $resp = [
                'id' => $it->id,
                'erkap_id' => $it->erkap_id,
                'status' => $it->status,
                'approval_notes' => clean_string($it->approval_notes),
                'approved_by' => $it->approved_by,
                'approved_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
                'approved_at' => optional($it->approved_at)->toISOString(),
            ];

            return json(200, true, 'Berhasil Disetujui', 'Risk Profile Investasi disetujui.', $resp);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500, false, 'Gagal Menyetujui', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['approval_notes' => 'required|string']);
        if ($validator->fails()) return json(400, false, 'Validasi Gagal', 'Catatan penolakan wajib diisi.', $validator->errors());

        try {
            DB::beginTransaction();

            $it = TrRiskInvestasi::find($id);
            if (!$it) return json(404, false, 'Tidak Ditemukan', 'Risk Profile Investasi tidak ditemukan.', null);

            $user = auth()->user();
            $roleId = (int) ($user->role_id ?? 0);
            if (!in_array($roleId, [1,2], true)) return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk menolak.', null);

            if ($it->status !== 'submit') {
                return json(400, false, 'Status Tidak Valid', 'Hanya data submit yang dapat ditolak.', ['current_status' => $it->status]);
            }

            $notes = (string) $request->approval_notes;
            try { $notes = clean_string($notes); } catch (\Throwable $e) { $notes = mb_convert_encoding($notes, 'UTF-8', 'UTF-8'); }

            $it->update([
                'status' => 'rejected',
                'approval_notes' => $notes,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            DB::commit();

            $it->load('approvedByUser:id,username,name');

            $resp = [
                'id' => $it->id,
                'erkap_id' => $it->erkap_id,
                'status' => $it->status,
                'rejection_notes' => clean_string($it->approval_notes),
                'rejected_by' => $it->approved_by,
                'rejected_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
                'rejected_at' => optional($it->approved_at)->toISOString(),
            ];

            return json(200, true, 'Berhasil Ditolak', 'Risk Profile Investasi ditolak.', $resp);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500, false, 'Gagal Menolak', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }


   public function getByErkapID($erkap_id)
    {
        $it = TrRiskInvestasi::with([
            'investasi' => function ($q) {
                $q->select(
                    'id',            
                    'erkap_id',       
                    'department_name',
                    'nama_investasi',
                    'jenis_investasi',
                    'kategori_investasi',
                    'year',
                    'nilai_rkap',
                    'nilai_revisi',
                    'unit_kerja_id'
                );
            },
            'approvedByUser:id,username,name'
        ])
        ->where('erkap_id', $erkap_id)
        ->first();

        if (!$it) {
            return json(404, false, 'Tidak Ditemukan', 'Risk Profile Investasi tidak ditemukan berdasarkan ERKAP ID.', null);
        }

        $resp = [
            'id' => $it->id,
            'erkap_id' => $it->erkap_id,
            'kategori_risiko' => $it->kategori_risiko,
            'sub_kategori_risiko' => $it->sub_kategori_risiko,
            'sasaran' => $it->sasaran,
            'peristiwa_risiko' => $it->peristiwa_risiko,
            'penyebab_risiko' => $it->penyebab_risiko,
            'dampak_inherent' => $it->dampak_inherent,
            'dampak_risiko_awal' => $it->dampak_risiko_awal,
            'kemungkinan_awal' => $it->kemungkinan_awal,
            'eksposure_level_awal' => $it->eksposure_level_awal,
            'eksposure_ltmh_awal' => $it->eksposure_ltmh_awal,
            'internal_external' => $it->internal_external,
            'mitigasi_risiko' => $it->mitigasi_risiko,
            'dampak_residual' => $it->dampak_residual,
            'dampak_risiko_akhir' => $it->dampak_risiko_akhir,
            'kemungkinan_akhir' => $it->kemungkinan_akhir,
            'eksposure_level_akhir' => $it->eksposure_level_akhir,
            'eksposure_ltmh_akhir' => $it->eksposure_ltmh_akhir,
            'biaya_mitigasi_risiko' => $it->biaya_mitigasi_risiko,
            'status' => $it->status ?? 'draft',
            'approval_notes' => $it->approval_notes,
            'approved_by' => $it->approved_by,
            'approved_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
            'approved_at' => optional($it->approved_at)->toISOString(),
            'rencana_investasi' => [
                'id' => optional($it->investasi)->id,
                'erkap_id' => optional($it->investasi)->erkap_id,
                'nama_investasi' => optional($it->investasi)->nama_investasi,
                'department_name' => optional($it->investasi)->department_name,
                'jenis_investasi' => optional($it->investasi)->jenis_investasi,
                'kategori_investasi' => optional($it->investasi)->kategori_investasi,
                'year' => optional($it->investasi)->year,
                'nilai_rkap' => optional($it->investasi)->nilai_rkap,
                'nilai_revisi' => optional($it->investasi)->nilai_revisi,
                'unit_kerja_id' => optional($it->investasi)->unit_kerja_id,
            ],
        ];

        return json(200, true, 'Data Ditemukan', 'Data risk profile investasi berdasarkan ERKAP ID berhasil diambil.', $resp);
    }




    public function export(Request $request, string $format)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);
        if (!in_array($format, ['excel', 'pdf'])) {
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Format tidak didukung',
                'data'    => 'Format yang didukung: excel, pdf',
            ], 400);
        }

        $query = TrRiskInvestasi::query()
            ->with([
                'investasi:erkap_id,department_name,nama_investasi,jenis_investasi,kategori_investasi,year,nilai_rkap,nilai_revisi,unit_kerja_id',
                'approvedByUser:id,username,name'
            ]);

        if ($request->filled('tahun')) {
            $query->whereHas('investasi', fn($q) => $q->where('year', (int) $request->tahun));
        }

        if ($request->filled('jenis_investasi')) {
            $ji = $request->jenis_investasi;
            $query->whereHas('investasi', fn($q) => $q->where('jenis_investasi', 'like', "%{$ji}%"));
        }

        if ($request->filled('department_name')) {
            $dn = $request->department_name;
            $query->whereHas('investasi', fn($q) => $q->where('department_name', 'like', "%{$dn}%"));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($qq) use ($s) {
                $qq->where('kategori_risiko', 'like', "%{$s}%")
                ->orWhere('sub_kategori_risiko', 'like', "%{$s}%")
                ->orWhere('sasaran', 'like', "%{$s}%")
                ->orWhere('peristiwa_risiko', 'like', "%{$s}%")
                ->orWhere('penyebab_risiko', 'like', "%{$s}%")
                ->orWhereHas('investasi', function ($qh) use ($s) {
                    $qh->where('nama_investasi', 'like', "%{$s}%")
                        ->orWhere('department_name', 'like', "%{$s}%");
                });
            });
        }

        $rows = $query
            ->orderBy('tr_risk_investasi.id', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'status'  => 404,
                'success' => false,
                'message' => 'Tidak Ada Data',
                'data'    => 'Risk Profile Investasi tidak ditemukan.',
            ], 404);
        }

        $flatRows = $rows->map(function ($it) {
            $inv = $it->investasi;

            return [
                'ERKAP ID'                 => $it->erkap_id,
                'Nama Investasi'           => optional($inv)->nama_investasi,
                'Department'               => optional($inv)->department_name,
                'Jenis Investasi'          => optional($inv)->jenis_investasi,
                'Kategori Investasi'       => optional($inv)->kategori_investasi,
                'Tahun'                    => optional($inv)->year,
                'Kategori Risiko'          => $it->kategori_risiko,
                'Sub Kategori Risiko'      => $it->sub_kategori_risiko,
                'Sasaran'                  => $it->sasaran,
                'Peristiwa Risiko'         => $it->peristiwa_risiko,
                'Penyebab Risiko'          => $it->penyebab_risiko,
                'Dampak Inherent'          => $it->dampak_inherent,
                'Dampak Risiko Awal'       => $it->dampak_risiko_awal,
                'Kemungkinan Awal'         => $it->kemungkinan_awal,
                'Eksposure Level Awal'     => $it->eksposure_level_awal,
                'Eksposure LTMH Awal'      => $it->eksposure_ltmh_awal,
                'Internal / External'      => $it->internal_external,
                'Mitigasi Risiko'          => $it->mitigasi_risiko,
                'Dampak Residual'          => $it->dampak_residual,
                'Dampak Risiko Akhir'      => $it->dampak_risiko_akhir,
                'Kemungkinan Akhir'        => $it->kemungkinan_akhir,
                'Eksposure Level Akhir'    => $it->eksposure_level_akhir,
                'Eksposure LTMH Akhir'     => $it->eksposure_ltmh_akhir,
                'Biaya Mitigasi Risiko'    => $it->biaya_mitigasi_risiko,
                'Status'                   => $it->status ?? 'draft',
                'Approval Notes'           => $it->approval_notes,
                'Approved By'              => $it->approved_by,
                'Approved By Name'         => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
                'Approved At'              => optional($it->approved_at)->format('Y-m-d H:i:s'),
                'Created At'               => optional($it->created_at)->format('Y-m-d H:i:s'),
                'Updated At'               => optional($it->updated_at)->format('Y-m-d H:i:s'),
            ];
        })->values();

        try {
            if ($format === 'excel') {
                $filename = 'RiskProfileInvestasi_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new \App\Exports\RiskInvestasi\RiskInvestasiExport($flatRows),
                    $filename
                );
            }

            $filename = 'RiskProfileInvestasi_' . now()->format('Ymd_His') . '.pdf';
            $departmentName = $rows->first()?->investasi->department_name ?? 'SEMUA UNIT';
            $year = $request->filled('tahun') ? (int)$request->tahun : null;
            $pdf = Pdf::loadView('exports.risk_investasi_pdf', [
                'rows' => $flatRows,
                'departmentName' => $departmentName,
                'year' => $year,
            ])->setPaper('A4', 'landscape');

            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Gagal melakukan export',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }




}
