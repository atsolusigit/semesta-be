<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\RencanaInvestasi;
use App\Models\TrRiskInvestasi;

class RencanaInvestasiController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = (int) $request->input('per_page', 10);
        $sortBy    = $request->input('sortBy');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'tahun'         => 'year',
            'nilai'         => 'nilai_rkap',
            'nilai_erkap'   => 'nilai_rkap',
            'nilai_revisi'  => 'nilai_revisi',
            // opsional: aktifkan sorting kolom baru jika dibutuhkan
            // 'nilai_realisasi'       => 'nilai_realisasi',
            // 'nilai_budget_transfer' => 'nilai_budget_transfer',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'id';

        $query = RencanaInvestasi::with([
            'createdBy:id,username',
            'updatedBy:id,username',
            'riskInvestasi:id,erkap_id,status',
        ])
        ->when($request->filled('tahun'), fn($q) => $q->where('year', (int)$request->tahun))
        ->when($request->filled('jenis_investasi'), fn($q) => $q->where('jenis_investasi','like','%'.$request->jenis_investasi.'%'))
        ->when($request->filled('department_name'), fn($q) => $q->where('department_name','like','%'.$request->department_name.'%'))
        ->when($request->filled('search'), function ($q) use ($request) {
            $s = $request->search;
            $q->where(function($qq) use ($s) {
                $qq->where('nama_investasi','like',"%$s%")
                   ->orWhere('department_name','like',"%$s%")
                   ->orWhere('kategori_investasi','like',"%$s%")
                   ->orWhere('jenis_investasi','like',"%$s%");
            });
        })
        ->orderBy($sortColumn, $sortOrder);

        $data = $query->paginate($perPage);

        if (empty($data->items())) {
            return json(404, false, 'Tidak Ada Data', 'Data rencana investasi tidak ditemukan.', null);
        }

        $resData = collect($data->items())->map(function ($it) {
            return [
                'id'                 => $it->id,
                'erkap_id'           => $it->erkap_id,
                'department_name'    => $it->department_name,
                'nama_investasi'     => $it->nama_investasi,
                'kategori_investasi' => $it->kategori_investasi,
                'jenis_investasi'    => $it->jenis_investasi,
                'year'               => $it->year,
                'nilai_rkap'         => $it->nilai_rkap,
                'nilai_revisi'       => $it->nilai_revisi,
                // ➕ kolom tambahan
                'nilai_budget_transfer' => $it->nilai_budget_transfer,
                'nilai_realisasi'       => $it->nilai_realisasi,
                'target_timeline'       => $it->target_timeline,
                'realisasi_timeline'    => $it->realisasi_timeline,
                'ld_inherent'           => $it->ld_inherent,
                'dampak_inherent'       => $it->dampak_inherent,
                'ld_current'            => $it->ld_current,
                'lk_current'            => $it->lk_current,
                'level_current'         => $it->level_current,
                'dampak_current'        => $it->dampak_current,
                'level_residual'        => $it->level_residual,
                'dampak_residual'       => $it->dampak_residual,
                // —
                'keterangan'         => $it->keterangan,
                'status'             => $it->status,
                'has_risk_profile'   => (bool) $it->riskInvestasi,
                'created_at'         => optional($it->created_at)->toISOString(),
                'updated_at'         => optional($it->updated_at)->toISOString(),
                'created_by'         => $it->created_by,
                'created_by_name'    => get_decrypted_name($it->createdBy),
                'updated_by'         => $it->updated_by,
                'updated_by_name'    => $it->updatedBy ? get_decrypted_name($it->updatedBy) : null,
            ];
        });

        $cleanData = clean_recursive([
            'current_page' => $data->currentPage(),
            'per_page'     => $data->perPage(),
            'total'        => $data->total(),
            'last_page'    => $data->lastPage(),
            'from'         => $data->firstItem(),
            'to'           => $data->lastItem(),
            'data'         => $resData,
        ]);

        return json(200, true, 'Data Ditemukan', 'Data rencana investasi berhasil diambil.', $cleanData);
    }

    public function store(Request $request)
    {
        $result = check_role(auth()->user(), [3]);
        if ($result !== true) return $result;

        $validator = Validator::make($request->all(), [
            'erkap_id'         => 'required|integer',
            'department_name'  => 'required|string',
            'nama_investasi'   => 'required|string',
            'kategori_investasi'=> 'required|string',
            'jenis_investasi'  => 'required|string',
            'year'             => 'required|numeric',
            'nilai_rkap'       => 'nullable|numeric',
            'nilai_revisi'     => 'nullable|numeric',
            'keterangan'       => 'required|string',
            'status'           => 'required|string',

            // ➕ kolom tambahan
            'nilai_budget_transfer' => 'nullable|integer',
            'nilai_realisasi'       => 'nullable|integer',
            'target_timeline'       => 'nullable|string',
            'realisasi_timeline'    => 'nullable|string',
            'ld_inherent'           => 'nullable|integer',
            'dampak_inherent'       => 'nullable|string',
            'ld_current'            => 'nullable|integer',
            'lk_current'            => 'nullable|integer',
            'level_current'         => 'nullable|integer',
            'dampak_current'        => 'nullable|string',
            'level_residual'        => 'nullable|string',
            'dampak_residual'       => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $exists = RencanaInvestasi::where('erkap_id', $request->erkap_id)->exists();
        if ($exists) return json(403, false, 'Sudah Ada', 'Data rencana investasi sudah ada', null);

        try {
            DB::beginTransaction();

            $currentUser = auth()->user();

            $data = $request->only([
                'erkap_id','department_name','nama_investasi','kategori_investasi','jenis_investasi',
                'year','nilai_rkap','nilai_revisi','keterangan','status',
                // ➕ kolom tambahan
                'nilai_budget_transfer','nilai_realisasi','target_timeline','realisasi_timeline',
                'ld_inherent','dampak_inherent','ld_current','lk_current','level_current',
                'dampak_current','level_residual','dampak_residual',
            ]);
            $data['created_by']    = auth()->id();
            $data['unit_kerja_id'] = $currentUser->role_id == 1
                                    ? $request->input('unit_kerja_id')
                                    : $currentUser->department_id;

            $item = RencanaInvestasi::create($data);

            DB::commit();

            $item->load('createdBy:id,username');

            $resp = [
                'id'                 => $item->id,
                'erkap_id'           => $item->erkap_id,
                'nama_investasi'     => clean_string($item->nama_investasi),
                'kategori_investasi' => clean_string($item->kategori_investasi),
                'jenis_investasi'    => clean_string($item->jenis_investasi),
                'nilai_rkap'         => $item->nilai_rkap,
                'nilai_revisi'       => $item->nilai_revisi,
                'department_name'    => $item->department_name,
                'status'             => $item->status,
                'year'               => $item->year,

                // ➕ kolom tambahan (kembalikan juga)
                'nilai_budget_transfer' => $item->nilai_budget_transfer,
                'nilai_realisasi'       => $item->nilai_realisasi,
                'target_timeline'       => $item->target_timeline,
                'realisasi_timeline'    => $item->realisasi_timeline,
                'ld_inherent'           => $item->ld_inherent,
                'dampak_inherent'       => clean_string($item->dampak_inherent),
                'ld_current'            => $item->ld_current,
                'lk_current'            => $item->lk_current,
                'level_current'         => $item->level_current,
                'dampak_current'        => clean_string($item->dampak_current),
                'level_residual'        => clean_string($item->level_residual),
                'dampak_residual'       => clean_string($item->dampak_residual),

                'created_at'         => $item->created_at,
                'created_by'         => $item->created_by,
                'created_by_name'    => get_decrypted_name($item->createdBy),
            ];

            return json(200, true, 'Berhasil Disimpan', 'Rencana investasi berhasil disimpan.', $resp);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $result = check_role(auth()->user(), [1,2,3]);
        if ($result !== true) return $result;

        $item = RencanaInvestasi::find($id);
        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Rencana investasi tidak ditemukan.', null);
        }

        $locked = TrRiskInvestasi::where('erkap_id', $item->erkap_id)->exists();
        if ($locked) {
            return json(403, false, 'Terkunci', 'Risk Profile Investasi sudah dibuat. Rencana Investasi tidak dapat diupdate.', null);
        }

        $validator = Validator::make($request->all(), [
            'department_name'     => 'nullable|string',
            'nama_investasi'      => 'nullable|string',
            'kategori_investasi'  => 'nullable|string',
            'jenis_investasi'     => 'nullable|string',
            'year'                => 'nullable|numeric',
            'nilai_rkap'          => 'nullable|numeric',
            'nilai_revisi'        => 'nullable|numeric',
            'keterangan'          => 'nullable|string',
            'status'              => 'nullable|string',

            // ➕ kolom tambahan
            'nilai_budget_transfer' => 'nullable|integer',
            'nilai_realisasi'       => 'nullable|integer',
            'target_timeline'       => 'nullable|string',
            'realisasi_timeline'    => 'nullable|string',
            'ld_inherent'           => 'nullable|integer',
            'dampak_inherent'       => 'nullable|string',
            'ld_current'            => 'nullable|integer',
            'lk_current'            => 'nullable|integer',
            'level_current'         => 'nullable|integer',
            'dampak_current'        => 'nullable|string',
            'level_residual'        => 'nullable|string',
            'dampak_residual'       => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        try {
            DB::beginTransaction();

            $payload = $request->only([
                'department_name','nama_investasi','kategori_investasi','jenis_investasi',
                'year','nilai_rkap','nilai_revisi','keterangan','status',
                // ➕ kolom tambahan
                'nilai_budget_transfer','nilai_realisasi','target_timeline','realisasi_timeline',
                'ld_inherent','dampak_inherent','ld_current','lk_current','level_current',
                'dampak_current','level_residual','dampak_residual',
            ]);

            if (!empty($payload)) {
                $payload['updated_by'] = auth()->id();
                $item->update($payload);
            }

            DB::commit();

            return json(200, true, 'Berhasil Diperbarui', 'Rencana investasi berhasil diupdate.', $item->only([
                'id','erkap_id','nama_investasi','kategori_investasi','jenis_investasi','year',
                'nilai_rkap','nilai_revisi','status',
                // ➕ kembalikan ringkas kolom tambahan
                'nilai_budget_transfer','nilai_realisasi','target_timeline','realisasi_timeline',
                'ld_inherent','ld_current','lk_current','level_current','level_residual',
            ]));

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }
}
