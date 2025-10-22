<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\RencanaInvestasi;
use App\Models\RencanaInvestasiDetail;

class RencanaInvestasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 10);

            $query = RencanaInvestasi::with([
                'createdBy:id,username,id',
                'updatedBy:id,username,id',
            ])
            ->when($request->department_name, function ($query) use ($request) {
                $query->where('department_name', 'like', '%' . $request->department_name . '%');
            })
            ->when($request->nama_investasi, function ($query) use ($request) {
                $query->where('nama_investasi', 'like', '%' . $request->nama_investasi . '%');
            })
            ->when($request->jenis_investasi, function ($query) use ($request) {
                $query->where('jenis_investasi', 'like', '%' . $request->jenis_investasi . '%');
            })
            ->when($request->tahun, function ($query) use ($request) {
                $query->where('year', $request->tahun);
            })
            ->orderBy('id', 'desc');

            $data = $query->paginate($perPage);

            if (empty($data->items())) {
                return json(404, false, 'Data Tidak Ditemukan', 'Data rencana investasi tidak ditemukan.', null);
            }

            $resData = collect($data->items())->map(function ($investasi) {
                return [
                    'id' => $investasi->id,
                    'erkap_id' => $investasi->erkap_id,
                    'department_name' => $investasi->department_name,
                    'nama_investasi' => $investasi->nama_investasi,
                    'kategori_investasi' => $investasi->kategori_investasi,
                    'jenis_investasi' => $investasi->jenis_investasi,
                    'year' => $investasi->year,
                    'nilai_rkap' => $investasi->nilai_rkap,
                    'nilai_revisi' => $investasi->nilai_revisi,
                    'keterangan' => $investasi->keterangan,
                    'status' => $investasi->status,
                    'created_at' => optional($investasi->created_at)->toISOString(),
                    'updated_at' => optional($investasi->updated_at)->toISOString(),
                    'created_by' => $investasi->created_by ?? null,
                    'created_by_name' => get_decrypted_name($investasi->createdBy),
                    'updated_by' => $investasi->updated_by ?? null,
                    'updated_by_name' => get_decrypted_name($investasi->updatedBy),
                ];
            });

            $cleanData = clean_recursive([
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                // FIX: firstItem/lastItem
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'data' => $resData,
            ]);

            return json(200, true, 'Data Ditemukan', 'Data rencana investasi berhasil diambil.', $cleanData);
        } catch (\Throwable $e) {
            \Log::error('RencanaInvestasi@index error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $result = check_role(auth()->user(), [3]);
        if ($result !== true) {
            return $result;
        }

        $currentUser = auth()->user();

        $RInvestasi = RencanaInvestasi::with([
            'createdBy:id,username,id',
            'updatedBy:id,username,id',
        ])->where('erkap_id', '=', $request->erkap_id)->get()->all();

        if (!empty($RInvestasi)) {
            $resData = [];
            foreach ($RInvestasi as $investasi) {
                $resData = [
                    'id' => $investasi['id'],
                    'erkap_id' => $investasi['erkap_id'],
                    'department_name' => $investasi['department_name'],
                    'nama_investasi' => $investasi['nama_investasi'],
                    'kategori_investasi' => $investasi['kategori_investasi'],
                    'jenis_investasi' => $investasi['jenis_investasi'],
                    'year' => $investasi['year'],
                    'nilai_rkap' => $investasi['nilai_rkap'],
                    'nilai_revisi' => $investasi['nilai_revisi'],
                    'keterangan' => $investasi['keterangan'],
                    'status' => $investasi['status'],
                    'created_at' => $investasi['created_at'] ? $investasi['created_at']->toISOString() : null,
                    'updated_at' => $investasi['updated_at'] ? $investasi['updated_at']->toISOString() : null,
                    'created_by' => $investasi['created_by'] ?? null,
                    'created_by_name' => get_decrypted_name($investasi['createdBy']),
                    'updated_by' => $investasi['updated_by'] ?? null,
                    'updated_by_name' => get_decrypted_name($investasi['updatedBy']),
                ];
            }
            return json(403, false, 'Data Rencana Investasi', 'Data rencana investasi sudah ada', $resData);
        }

        $allowedFields = [
            'erkap_id',
            'department_name',
            'nama_investasi',
            'kategori_investasi',
            'jenis_investasi',
            'year',
            'nilai_rkap',
            'nilai_revisi',
            'keterangan',
            'status'
        ];

        $validator = Validator::make($request->all(), [
            'erkap_id' =>'required|string',
            'department_name' => 'required|string',
            'nama_investasi' => 'required|string',
            'kategori_investasi' => 'required|string',
            'jenis_investasi' => 'required|string',
            'year'=> 'required|numeric',
            'nilai_rkap' => 'nullable|numeric',
            'nilai_revisi' => 'nullable|numeric',
            'keterangan' => 'required|string',
            'status' => 'required|string',

            'detail.peristiwa_risiko' => 'nullable|array',
            'detail.penyebab_risiko' => 'nullable|array',
            'detail.kontrol_internal_eksternal' => 'nullable|array',
            'detail.mitigasi_inherent' => 'nullable|array',
            'detail.mitigasi_residual' => 'nullable|array',
            'detail.inherent_dampak' => 'nullable|integer',
            'detail.inherent_kemungkinan' => 'nullable|integer',
            'detail.inherent_eksposur_level' => 'nullable|string',
            'detail.inherent_eksposur_kode' => 'nullable|string',
            'detail.inherent_risiko' => 'nullable|string',
            'detail.residual_dampak' => 'nullable|integer',
            'detail.residual_kemungkinan' => 'nullable|integer',
            'detail.residual_eksposur_level' => 'nullable|string',
            'detail.residual_eksposur_kode' => 'nullable|string',
            'detail.residual_risiko' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        try {
            DB::beginTransaction();

            $data = [];
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $data[$field] = $request->input($field);
                }
            }

            $data['created_by'] = auth()->id();
            $data['updated_at'] = null;

            if ($currentUser->role_id == 1) {
                $data['unit_kerja_id'] = $request->input('unit_kerja_id');
            } else {
                $data['unit_kerja_id'] = $currentUser->department_id;
            }
        
            $rInvest = RencanaInvestasi::create($data);

            // if ($request->has('detail') && is_array($request->detail)) {
            //     $detail = $request->detail;
            //     $detail['rencana_investasi_id'] = $rInvest->id;
            //     $detail['created_by'] = auth()->id();
            //     RencanaInvestasiDetail::create($detail);
            // }

            DB::commit();
           
            $rInvest->load([
                'createdBy:id,username',
            ]);

            $createdByName = 'Unknown User';
            try {
                $createdByName = get_decrypted_name($rInvest->createdBy);
            } catch (\Throwable $e) {
                \Log::warning("Error handling createdBy: {$e->getMessage()}");
            }

            $responseData = [
                'id' => $rInvest->id,
                'nama_investasi' => clean_string($rInvest->nama_investasi),
                'kategori_investasi' => clean_string($rInvest->kategori_investasi),
                'jenis_investasi' => clean_string($rInvest->jenis_investasi),
                'nilai_rkap' => clean_string($rInvest->nilai_rkap),
                'nilai_revisi' => clean_string($rInvest->nilai_revisi),
                'department_name' => $rInvest->unit_kerja_id,
                'status' => $rInvest->status,
                'year' => $rInvest->year,
                'created_at' => $rInvest->created_at,
                'created_by' => $rInvest->created_by,
                'created_by_name' => $createdByName
            ];
            
            $message = 'Rencana investasi header berhasil disimpan dengan status approved';
            return json(200, true, 'Berhasil Disimpan', $message, $responseData);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }


    public function show($id)
    {
        $item = RencanaInvestasi::with([
            'detail',
            'createdBy:id,username',
            'updatedBy:id,username'
        ])->find($id);

        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Data rencana investasi tidak ditemukan', null);
        }

        $d = $item->detail;

        $data = [
            'id' => $item->id,
            'erkap_id' => $item->erkap_id,
            'risk_owner' => $item->department_name,
            'tahun' => $item->year,
            'nama_pekerjaan' => $item->nama_investasi,
            'jenis_investasi' => $item->jenis_investasi,
            'nilai_investasi' => $item->nilai_rkap,
            'kategori_risiko' => $item->kategori_investasi,
            'sasaran' => $item->sasaran ?? null,

            // arrays (detail)
            'peristiwa_risiko' => $d ? $d->peristiwa_risiko : [],
            'penyebab_risiko' => $d ? $d->penyebab_risiko : [],
            'kontrol_internal_eksternal' => $d ? $d->kontrol_internal_eksternal : [],
            'mitigasi_inherent' => $d ? $d->mitigasi_inherent : [],
            'mitigasi_residual' => $d ? $d->mitigasi_residual : [],

            // inherent
            'inherent_dampak' => $d ? $d->inherent_dampak : null,
            'inherent_kemungkinan' => $d ? $d->inherent_kemungkinan : null,
            'inherent_eksposur_level' => $d ? $d->inherent_eksposur_level : null,
            'inherent_eksposur_kode' => $d ? $d->inherent_eksposur_kode : null,
            'inherent_risiko' => $d ? $d->inherent_risiko : null,

            // residual
            'residual_dampak' => $d ? $d->residual_dampak : null,
            'residual_kemungkinan' => $d ? $d->residual_kemungkinan : null,
            'residual_eksposur_level' => $d ? $d->residual_eksposur_level : null,
            'residual_eksposur_kode' => $d ? $d->residual_eksposur_kode : null,
            'residual_risiko' => $d ? $d->residual_risiko : null,

            // status + notes
            'status' => $item->status,
            'catatan_svp_unit' => $item->catatan_svp_unit ?? null,
            'catatan_svp_menrisk' => $item->catatan_svp_menrisk ?? null,

            'created_at' => optional($item->created_at)->toISOString(),
            'updated_at' => optional($item->updated_at)->toISOString(),
        ];

        return json(200, true, 'Berhasil', 'Detail rencana investasi', $data);
    }

    public function update(Request $request, $id)
    {
        $result = check_role(auth()->user(), [1, 2, 3]);
        if ($result !== true) {
            return $result;
        }

        $allowedHeader = [
            'erkap_id',
            'department_name',
            'nama_investasi',
            'kategori_investasi',
            'jenis_investasi',
            'year',
            'nilai_rkap',
            'nilai_revisi',
            'keterangan',
            'status',
            'sasaran',
            'catatan_svp_unit',
            'catatan_svp_menrisk',
        ];

        $validator = Validator::make($request->all(), [
            'erkap_id' =>'sometimes|string',
            'department_name' => 'sometimes|string',
            'nama_investasi' => 'sometimes|string',
            'kategori_investasi' => 'sometimes|string',
            'jenis_investasi' => 'sometimes|string',
            'year'=> 'sometimes|numeric',
            'nilai_rkap' => 'sometimes|numeric|nullable',
            'nilai_revisi' => 'sometimes|numeric|nullable',
            'keterangan' => 'sometimes|string|nullable',
            'status' => 'sometimes|string',
            'sasaran' => 'sometimes|string|nullable',
            'catatan_svp_unit' => 'sometimes|string|nullable',
            'catatan_svp_menrisk' => 'sometimes|string|nullable',

            // detail payload
            'detail.peristiwa_risiko' => 'sometimes|array|nullable',
            'detail.penyebab_risiko' => 'sometimes|array|nullable',
            'detail.kontrol_internal_eksternal' => 'sometimes|array|nullable',
            'detail.mitigasi_inherent' => 'sometimes|array|nullable',
            'detail.mitigasi_residual' => 'sometimes|array|nullable',
            'detail.inherent_dampak' => 'sometimes|integer|nullable',
            'detail.inherent_kemungkinan' => 'sometimes|integer|nullable',
            'detail.inherent_eksposur_level' => 'sometimes|string|nullable',
            'detail.inherent_eksposur_kode' => 'sometimes|string|nullable',
            'detail.inherent_risiko' => 'sometimes|string|nullable',
            'detail.residual_dampak' => 'sometimes|integer|nullable',
            'detail.residual_kemungkinan' => 'sometimes|integer|nullable',
            'detail.residual_eksposur_level' => 'sometimes|string|nullable',
            'detail.residual_eksposur_kode' => 'sometimes|string|nullable',
            'detail.residual_risiko' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        try {
            DB::beginTransaction();

            $header = RencanaInvestasi::find($id);
            if (!$header) {
                return json(404, false, 'Tidak Ditemukan', 'Data rencana investasi tidak ditemukan', null);
            }

            // header update
            $data = [];
            foreach ($allowedHeader as $field) {
                if ($request->has($field)) {
                    $data[$field] = $request->input($field);
                }
            }
            if (!empty($data)) {
                $data['updated_by'] = auth()->id();
                $header->update($data);
            }

            if ($request->has('detail') && is_array($request->detail)) {
                $detailPayload = $request->detail;
                $detail = RencanaInvestasiDetail::firstOrNew(['rencana_investasi_id' => $header->id]);
                $detail->fill($detailPayload);
                if (!$detail->exists) {
                    $detail->created_by = auth()->id();
                }
                $detail->updated_by = auth()->id();
                $detail->rencana_investasi_id = $header->id;
                $detail->save();
            }

            DB::commit();

            $header->load(['detail', 'createdBy:id,username', 'updatedBy:id,username']);

            return json(200, true, 'Berhasil', 'Rencana investasi berhasil diperbarui.', $header);

        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('RencanaInvestasi@update error', [
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ]);
            return json(500, false, 'Gagal Memperbarui', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }

    public function destroy($id)
    {
       
    }

    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        try {
            DB::beginTransaction();

            $item = RencanaInvestasi::find($id);
            if (!$item) {
                return json(404, false, 'Data Tidak Ditemukan', 'Rencana investasi tidak ditemukan.', null);
            }

            $currentUser = auth()->user();
            $roleId = (int) ($currentUser->role_id ?? 0);

            // Hanya role 1 & 2 yang boleh approve
            if (!in_array($roleId, [1, 2], true)) {
                return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki hak untuk menyetujui data ini.', null);
            }

            // Role 2 hanya boleh approve data departemen sendiri
            if ($roleId === 2 && (int) $item->unit_kerja_id !== (int) ($currentUser->department_id ?? 0)) {
                return json(403, false, 'Tidak Diizinkan', 'Anda hanya dapat menyetujui data dari departemen Anda sendiri.', null);
            }

            // Hanya bisa approve ketika status submit (sesuaikan jika flow Anda berbeda)
            if ($item->status !== 'submit') {
                return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status submit yang dapat disetujui.', [
                    'current_status' => $item->status
                ]);
            }

            $notes = (string) ($request->approval_notes ?? '');
            try {
                $notes = clean_string($notes); 
            } catch (\Throwable $e) {
                $notes = mb_convert_encoding($notes, 'UTF-8', 'UTF-8');
            }

            $updatePayload = ['status' => 'approved'];

            if (\Illuminate\Support\Facades\Schema::hasColumn('rencana_investasi', 'catatan_svp_unit')) {
                $updatePayload['catatan_svp_unit'] = $notes;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('rencana_investasi', 'approved_by')) {
                $updatePayload['approved_by'] = $currentUser->id;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('rencana_investasi', 'approved_at')) {
                $updatePayload['approved_at'] = now();
            }

            $item->update($updatePayload);

            DB::commit();

            $item->loadMissing('approvedBy:id,username,name');

            $approvedByName = null;
            try {
                if (!empty($item->approved_by) && $item->approvedBy) {
                    $approvedByName = get_decrypted_name($item->approvedBy);
                    if (empty($approvedByName)) {
                        $approvedByName = $item->approvedBy->name ?? $item->approvedBy->username ?? null;
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning("Error resolving approvedBy name: {$e->getMessage()}");
            }

            $resp = [
                'id'             => $item->id,
                'status'         => $item->status,
                'approval_notes' => isset($item->catatan_svp_unit) ? clean_string($item->catatan_svp_unit) : null,
                'approved_by'    => $item->approved_by ?? null,
                'approved_by_name' => $approvedByName,
                'approved_at'    => isset($item->approved_at) ? optional($item->approved_at)->toISOString() : null,
            ];

            return json(200, true, 'Berhasil Disetujui', 'Rencana investasi telah disetujui.', clean_recursive($resp));

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('RencanaInvestasi@approve error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return json(500, false, 'Gagal Menyetujui', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }



    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'approval_notes' => 'required|string'
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Catatan penolakan wajib diisi.', $validator->errors());
        }

        try {
            DB::beginTransaction();

            $item = RencanaInvestasi::find($id);
            if (!$item) {
                return json(404, false, 'Data Tidak Ditemukan', 'Rencana investasi tidak ditemukan.', null);
            }

            $currentUser = auth()->user();
            $roleId = (int) ($currentUser->role_id ?? 0);

            if (!in_array($roleId, [1, 2], true)) {
                return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki hak untuk menolak data ini.', null);
            }

            if ($roleId === 2 && (int) $item->unit_kerja_id !== (int) ($currentUser->department_id ?? 0)) {
                return json(403, false, 'Akses Ditolak', 'Anda hanya dapat menolak data dari departemen Anda sendiri.', null);
            }

            if ($item->status !== 'submit') {
                return json(400, false, 'Status Tidak Valid', 'Hanya data dengan status submit yang dapat ditolak.', [
                    'current_status' => $item->status
                ]);
            }

            $notes = (string) ($request->approval_notes ?? '');
            try { $notes = clean_string($notes); } catch (\Throwable $e) { $notes = mb_convert_encoding($notes, 'UTF-8', 'UTF-8'); }

            $payload = ['status' => 'rejected'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('rencana_investasi', 'catatan_svp_unit')) {
                $payload['catatan_svp_unit'] = $notes;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('rencana_investasi', 'approved_by')) {
                $payload['approved_by'] = $currentUser->id;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('rencana_investasi', 'approved_at')) {
                $payload['approved_at'] = now();
            }

            $item->update($payload);

            DB::commit();

            $item->loadMissing('approvedBy:id,username,name');

            $approvedByName = null;
            try {
                if (!empty($item->approved_by) && $item->approvedBy) {
                    $approvedByName = get_decrypted_name($item->approvedBy) ?: ($item->approvedBy->name ?? $item->approvedBy->username ?? null);
                }
            } catch (\Throwable $e) {
                \Log::warning("Error resolving approvedBy name: {$e->getMessage()}");
            }

            $resp = [
                'id' => $item->id,
                'status' => $item->status,
                'rejection_notes' => isset($item->catatan_svp_unit) ? clean_string($item->catatan_svp_unit) : null,
                'rejected_by' => $item->approved_by ?? null,
                'rejected_by_name' => $approvedByName,
                'rejected_at' => isset($item->approved_at) ? optional($item->approved_at)->toISOString() : null,
            ];

            return json(200, true, 'Berhasil Ditolak', 'Rencana investasi berhasil ditolak.', clean_recursive($resp));

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('RencanaInvestasi@reject error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return json(500, false, 'Gagal Menolak', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }






}
