<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\TrRiskInvestasi;
use App\Models\RencanaInvestasi;
use App\Models\User;

class TrRiskInvestasiController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $sortBy = $request->input('sortBy');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'tahun' => 'rencana_investasi.year',
            'nilai' => 'rencana_investasi.nilai_rkap',
            'nilai_erkap' => 'rencana_investasi.nilai_rkap',
            'nilai_revisi' => 'rencana_investasi.nilai_revisi',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'tr_risk_investasi.id';

        $query = TrRiskInvestasi::query()
            ->with([
                'investasi:id,erkap_id,department_name,nama_investasi,jenis_investasi,kategori_investasi,year,nilai_rkap,nilai_revisi,unit_kerja_id',
                'approvedByUser:id,username,name'
            ])
            ->join('rencana_investasi','rencana_investasi.id','=','tr_risk_investasi.erkap_id')
            ->select('tr_risk_investasi.*');

        if ($request->filled('tahun')) $query->where('rencana_investasi.year', (int) $request->tahun);
        if ($request->filled('jenis_investasi')) $query->where('rencana_investasi.jenis_investasi', 'like', '%'.$request->jenis_investasi.'%');
        if ($request->filled('department_name')) $query->where('rencana_investasi.department_name', 'like', '%'.$request->department_name.'%');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s){
                $q->where('rencana_investasi.nama_investasi','like',"%$s%")
                  ->orWhere('rencana_investasi.department_name','like',"%$s%")
                  ->orWhere('tr_risk_investasi.kategori_risiko','like',"%$s%")
                  ->orWhere('tr_risk_investasi.sub_kategori_risiko','like',"%$s%");
            });
        }

        $query->orderBy($sortColumn, $sortOrder);

        $data = $query->paginate($perPage);

        if (empty($data->items())) {
            return json(404,false,'Tidak Ada Data','Risk Profile Investasi tidak ditemukan.',null);
        }

        $res = collect($data->items())->map(function($it){
            return [
                'id' => $it->id,
                'erkap_id' => $it->erkap_id,
                'nama_investasi' => optional($it->investasi)->nama_investasi,
                'department_name' => optional($it->investasi)->department_name,
                'jenis_investasi' => optional($it->investasi)->jenis_investasi,
                'kategori_investasi' => optional($it->investasi)->kategori_investasi,
                'year' => optional($it->investasi)->year,
                'nilai_rkap' => optional($it->investasi)->nilai_rkap,
                'nilai_revisi' => optional($it->investasi)->nilai_revisi,
                'kategori_risiko' => $it->kategori_risiko,
                'sub_kategori_risiko' => $it->sub_kategori_risiko,
                'status' => $it->status,
                'approved_at' => optional($it->approved_at)->toISOString(),
                'approved_by' => $it->approved_by,
                'approved_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
                'created_at' => optional($it->created_at)->toISOString(),
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

        return json(200,true,'Berhasil','List Risk Profile Investasi',$payload);
    }

    public function show($id)
    {
        $it = TrRiskInvestasi::with([
            'investasi:id,erkap_id,department_name,nama_investasi,jenis_investasi,kategori_investasi,year,nilai_rkap,nilai_revisi',
            'approvedByUser:id,username,name'
        ])->find($id);

        if (!$it) return json(404,false,'Tidak Ditemukan','Risk Profile Investasi tidak ditemukan.',null);

        $resp = [
            'id' => $it->id,
            'erkap_id' => $it->erkap_id,
            'header' => $it->investasi,
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
            'status' => $it->status,
            'approval_notes' => $it->approval_notes,
            'approved_by' => $it->approved_by,
            'approved_by_name' => $it->approvedByUser ? get_decrypted_name($it->approvedByUser) : null,
            'approved_at' => optional($it->approved_at)->toISOString(),
        ];

        return json(200,true,'Berhasil','Detail Risk Profile Investasi',$resp);
    }

    public function store(Request $request)
    {
        $result = check_role(auth()->user(), [3]);
        if ($result !== true) return $result;

        $validator = Validator::make($request->all(), [
            'erkap_id' => 'required|integer|exists:rencana_investasi,id',
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
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

        $exists = TrRiskInvestasi::where('erkap_id', $request->erkap_id)->exists();
        if ($exists) return json(409,false,'Sudah Ada','Risk Profile untuk Rencana Investasi ini sudah dibuat.',null);

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

            return json(201,true,'Berhasil Disimpan','Risk Profile Investasi berhasil dibuat.',$it->only(['id','erkap_id','status']));

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500,false,'Gagal Disimpan','Terjadi kesalahan sistem.',$th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $result = check_role(auth()->user(), [1,2,3]);
        if ($result !== true) return $result;

        $it = TrRiskInvestasi::find($id);
        if (!$it) return json(404,false,'Tidak Ditemukan','Risk Profile Investasi tidak ditemukan.',null);

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
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

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

            return json(200,true,'Berhasil Diperbarui','Risk Profile Investasi berhasil diupdate.',$it->only(['id','erkap_id','status']));

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500,false,'Gagal Update','Terjadi kesalahan sistem.',$th->getMessage());
        }
    }

    public function destroy($id)
    {
        $result = check_role(auth()->user(), [1,2]);
        if ($result !== true) return $result;

        $it = TrRiskInvestasi::find($id);
        if (!$it) return json(404,false,'Tidak Ditemukan','Risk Profile Investasi tidak ditemukan.',null);

        try {
            DB::beginTransaction();

            $it->delete();

            DB::commit();

            return json(200,true,'Berhasil Dihapus','Risk Profile Investasi berhasil dihapus.',['deleted_id'=>$id]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500,false,'Gagal Hapus','Terjadi kesalahan sistem.',$th->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['approval_notes' => 'nullable|string']);
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

        try {
            DB::beginTransaction();

            $it = TrRiskInvestasi::find($id);
            if (!$it) return json(404,false,'Tidak Ditemukan','Risk Profile Investasi tidak ditemukan.',null);

            $user = auth()->user();
            $roleId = (int) ($user->role_id ?? 0);
            if (!in_array($roleId,[1,2],true)) return json(403,false,'Tidak Diizinkan','Anda tidak memiliki hak untuk menyetujui.',null);

            if ($it->status !== 'submit') {
                return json(400,false,'Status Tidak Valid','Hanya data submit yang dapat disetujui.',['current_status'=>$it->status]);
            }

            $notes = (string) ($request->approval_notes ?? '');
            try { $notes = clean_string($notes); } catch (\Throwable $e) { $notes = mb_convert_encoding($notes,'UTF-8','UTF-8'); }

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

            return json(200,true,'Berhasil Disetujui','Risk Profile Investasi disetujui.',$resp);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500,false,'Gagal Menyetujui','Terjadi kesalahan sistem.',$e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['approval_notes' => 'required|string']);
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Catatan penolakan wajib diisi.',$validator->errors());

        try {
            DB::beginTransaction();

            $it = TrRiskInvestasi::find($id);
            if (!$it) return json(404,false,'Tidak Ditemukan','Risk Profile Investasi tidak ditemukan.',null);

            $user = auth()->user();
            $roleId = (int) ($user->role_id ?? 0);
            if (!in_array($roleId,[1,2],true)) return json(403,false,'Akses Ditolak','Anda tidak memiliki hak untuk menolak.',null);

            if ($it->status !== 'submit') {
                return json(400,false,'Status Tidak Valid','Hanya data submit yang dapat ditolak.',['current_status'=>$it->status]);
            }

            $notes = (string) $request->approval_notes;
            try { $notes = clean_string($notes); } catch (\Throwable $e) { $notes = mb_convert_encoding($notes,'UTF-8','UTF-8'); }

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

            return json(200,true,'Berhasil Ditolak','Risk Profile Investasi ditolak.',$resp);

        } catch (\Throwable $e) {
            DB::rollBack();
            return json(500,false,'Gagal Menolak','Terjadi kesalahan sistem.',$e->getMessage());
        }
    }
}
