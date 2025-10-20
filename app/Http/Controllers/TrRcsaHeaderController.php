<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\TrRcsaHeader;
use App\Models\TrRcsaResidual;
use App\Models\TrRcsaRencanaRisikoList;
use PhpParser\Node\Stmt\TryCatch;
use App\Models\MstJabatan;

class TrRcsaHeaderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $perPage = $request->input('per_page', 10);

        $query = TrRcsaHeader::with([
            'rcsaResidual',
            'rcsaRisikoList',
            'createdBy:id,username,id',
            'updatedBy:id,username,id',
            'department:id,name',
        ])
        ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            $query->where('unit_kerja_id', $user->department_id);
        })
        ->whereHas('department', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
        })
        ->orWhere('peristiwa_risiko', 'like', "%{$request->search}%")
        ->orWhere('status', 'like', "%{$request->search}%")
        ->orderBy('id', 'desc');

        // Pagination, ambil data per halaman
        $data = $query->paginate($perPage);

        if (empty($data->items())) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data rcsa header tidak ditemukan.', null);
        }
        
        $resData = collect($data->items())->map(function ($item) {

            $rcsaResidual = $item->rcsaResidual->map(function ($residual) {
                return [
                    'id' => $residual->id,
                    'kuartal' => $residual->kuartal,
                    'residual_eksposur_risiko_kualitatif' => $residual->residual_eksposur_risiko_kualitatif,
                    'residual_eksposur_risiko_kuantitatif' => $residual->residual_eksposur_risiko_kuantitatif,
                    'residual_level_risiko' => $residual->residual_level_risiko,
                    'residual_nilai_dampak' => $residual->residual_nilai_dampak,
                    'residual_nilai_probabilitas' => $residual->residual_nilai_probabilitas,
                    'residual_skala_dampak' => $residual->residual_skala_dampak,
                    'residual_skala_probabilitas' => $residual->residual_skala_probabilitas,
                    'residual_skala_risiko' => $residual->residual_skala_risiko,
                ];
            });

            $rcsaRencanaRisiko = $item->rcsaRisikoList->map(function ($rrList) {
                return [
                    'id' => $rrList->id,
                    'jenis_rencana_perlakuan_risiko' => $rrList->jenis_rencana_perlakuan_risiko,
                ];
            });

             return [
                'id' => $item->id,
                'department_id' => $item->unit_kerja_id,
                'department_name' => $item->department->name ?? '',
                'risk_status' => $item->status,
                'peristiwa_risiko' => $item->peristiwa_risiko,
                'penyebab_risiko' => $item->penyebab_risiko,
                'deskripsi_dampak' => $item->deskripsi_dampak,
                'inherent_skala_dampak' => $item->inherent_skala_dampak,
                'inherent_skala_probabilitas' => $item->inherent_skala_probabilitas,
                'inherent_skala_risiko' => $item->inherent_skala_risiko,
                'inherent_level_risiko' => $item->inherent_level_risiko,
                'pilihan_sasaran' => $item->pilihan_sasaran,
                'hasil_yang_diharapkan_perusahaan' => $item->hasil_yang_diharapkan_perusahaan,
                'nilai_risiko_yang_akan_timbul' => $item->nilai_risiko_yang_akan_timbul,
                'nilai_limit_risiko' => $item->nilai_limit_risiko,
                'data_residual' => $rcsaResidual,
                'data_risiko_list' => $rcsaRencanaRisiko,
                'year' => $item->year,
                'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
                'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,
                'created_by' => $item->created_by ?? null,
                'created_by_name' => get_decrypted_name($item->createdBy),
                'updated_by' => $item->updated_by ?? null,
                'updated_by_name' => get_decrypted_name($item->updatedBy),
             ];    
        });

        $cleanData = clean_recursive([
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'data' => $resData,
        ]);
        return json(200, true, 'Data Ditemukan', 'Data rcsa header berhasil diambil.',$cleanData);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $result = check_role(auth()->user(), [1, 2, 3]);
        if ($result !== true) {
            return $result;
        }
        
        $allowedFields = [
            'asumsi_perhitungan_dampak',
            'deskripsi_dampak',
            'biaya_perlakuan_risiko',
            'deskripsi_peristiwa_risiko',
            'hasil_yang_diharapkan_perusahaan',
            'inherent_eksposur_risiko_kualitatif',
            'inherent_eksposur_risiko_kuantitatif',
            'inherent_level_risiko',
            'inherent_nilai_dampak',
            'existing_control',
            'inherent_nilai_probabilitas',
            'inherent_skala_dampak',
            'inherent_skala_probabilitas',
            'inherent_skala_risiko',
            'jenis_existing_control',
            'jenis_program_dalam_rkap',
            'kategori_dampak',
            'kategori_risiko_bumn',
            'kategori_risiko_t2_t3_kbumn',
            'kategori_threshold_kri_aman',
            'kategori_threshold_kri_bahaya',
            'kategori_threshold_kri_hati_hati',
            'keputusan_penetapan',
            'key_risk_indicators',
            'kode_bumn',
            'nama_bumn',
            'nilai_limit_risiko',
            'nilai_risiko_yang_akan_timbul',
            'opsi_perlakuan_risiko',
            'output_perlakuan_risiko',
            'penilaian_efektivitas_kontrol',
            'penyebab_risiko',
            'peristiwa_risiko',
            'perkiraan_waktu_terpapar_risiko',
            'pilihan_sasaran',
            'pilihan_strategi',
            'rencana_perlakuan_risiko',
            'sasaran_kbumn',
            'timeline_bulan_akhir',
            'timeline_bulan_awal',
            'unit_satuan_kri',
            'unit_kerja_id',
            'year'
        ];

         $validator = Validator::make($request->all(), [
            'asumsi_perhitungan_dampak' =>'required|string',
            'deskripsi_dampak' => 'required|string',
            'biaya_perlakuan_risiko' => 'required|numeric',
            'deskripsi_peristiwa_risiko' => 'required|string',
            'existing_control' => 'nullable|string',
            'hasil_yang_diharapkan_perusahaan' => 'required|string',
            'kategori_dampak'=> 'required|numeric',
            'inherent_eksposur_risiko_kualitatif' => 'required_if:kategori_dampak,1',
            'inherent_eksposur_risiko_kuantitatif' => 'required|numeric',
            'inherent_level_risiko' => 'required|string',
            'inherent_nilai_dampak' => 'required|numeric',
            'inherent_nilai_probabilitas' => 'required|numeric',
            'inherent_skala_dampak' => 'required|numeric',
            'inherent_skala_probabilitas' => 'required|numeric',
            'inherent_skala_risiko' => 'required|numeric',
            'jenis_existing_control'=> 'required|string',
            'jenis_program_dalam_rkap' => 'required|string',
            'kategori_risiko_bumn' => 'required|string',
            'kategori_risiko_t2_t3_kbumn' => 'required|string',
            'kategori_threshold_kri_aman' => 'required|string',
            'kategori_threshold_kri_bahaya' => 'required|string',
            'kategori_threshold_kri_hati_hati' => 'required|string',

            'keputusan_penetapan' => 'required|numeric',
            'key_risk_indicators' => 'required|string',
            'kode_bumn' => 'required|string',
            'nama_bumn' => 'required|string',
            'nilai_limit_risiko' => 'required|string',
            'nilai_risiko_yang_akan_timbul' => 'required|string',


            'opsi_perlakuan_risiko' => 'required|string',
            'output_perlakuan_risiko' => 'required|string',
            'penilaian_efektivitas_kontrol' => 'required|string',
            'penyebab_risiko' => 'required|string',
            'peristiwa_risiko' => 'required|string',
            'perkiraan_waktu_terpapar_risiko' => 'required|string',
            'pic' => 'nullable|string',
            'pilihan_sasaran' => 'required|string',
            'pilihan_strategi'=> 'required|string',
            'rencana_perlakuan_risiko' => 'required|string',
            'sasaran_kbumn' => 'required|string',
            'timeline_bulan_akhir' => 'required|date',
            // 'timeline_bulan_awal' => 'required|date',
            // 'unit_satuan_kri' => 'required|string',
            'unit_kerja_id' => 'required|numeric',
            'year'=> 'required|numeric',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = [];
        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        $dataResidual = (array) $request->input('dataResidual');
        $dataRisikoList = (array) $request->input('dataRisikoList');
        
        try {
            DB::beginTransaction();

            // HANYA AMBIL DATA YANG DIIZINKAN
            $data = [];
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $data[$field] = $request->input($field);
                }
            }

            $data['created_by'] = auth()->id();
            $data['created_by_role'] = auth()->user()->role_id;

            $currentUser = auth()->user();

                    // Superadmin (role 1) boleh pilih departemen dari request
            if ($currentUser->role_id == 1) {
                $data['unit_kerja_id'] = $request->input('unit_kerja_id');
            } else {
                // Role lain (2, 3, dst) selalu pakai department_id user
                $data['unit_kerja_id'] = $currentUser->department_id;
            }

            /* PIC */
            if(!empty($request->input('pic'))){
                $data['pic'] = $request->input('pic');
            }
            /* END PIC */
            $data['status'] = 'draft';
             
        
           $rcsaHeader = TrRcsaHeader::create($data);

           DB::commit();
           
            $rcsaHeader->load([
                'department:id,name',
                'createdBy:id,username',
            ]);
            $rcsa_id = ['rcsa_id' => $rcsaHeader->id];
            $createdByName = 'Unknown User';
            try {
                $createdByName = get_decrypted_name($rcsaHeader->createdBy);
            } catch (\Throwable $e) {
                \Log::warning("Error handling createdBy: {$e->getMessage()}");
            }

            /**************RESIDUAL************/
            if(!empty($dataResidual)){
                foreach($dataResidual as $item){
                $mergedResidual[] = array_merge($item, $rcsa_id);
                }

                TrRcsaResidual::insert($mergedResidual);
            }
            /**********END RESIDUAL************/

            /**************RISIKO LIST************/
            if(!empty($dataRisikoList)){
                foreach($dataRisikoList as $itemList){
                    $mergedRList[] = array_merge($itemList, $rcsa_id);
                }

                TrRcsaRencanaRisikoList::insert($mergedRList);
            }
            /**********END RISIKO LIST************/

             $responseData = [
                'id' => $rcsaHeader->id,
                'pilihan_sasaran' => clean_string($rcsaHeader->pilihan_sasaran),
                'pilihan_strategi' => clean_string($rcsaHeader->pilihan_strategi),
                'asumsi_perhitungan_dampak' => clean_string($rcsaHeader->asumsi_perhitungan_dampak),
                'deskripsi_dampak' => clean_string($rcsaHeader->deskripsi_dampak),
                'biaya_perlakuan_risiko' => clean_string($rcsaHeader->biaya_perlakuan_risiko),
                'deskripsi_peristiwa_risiko' => clean_string($rcsaHeader->deskripsi_peristiwa_risiko),
                'existing_control' => clean_string($rcsaHeader->existing_control),
                'inherent_nilai_probabilitas' => clean_string($rcsaHeader->inherent_nilai_probabilitas),
                'inherent_skala_dampak' => clean_string($rcsaHeader->inherent_skala_dampak),
                'inherent_skala_probabilitas' => clean_string($rcsaHeader->inherent_skala_probabilitas),
                'inherent_skala_risiko' => clean_string($rcsaHeader->inherent_skala_risiko),
                'jenis_existing_control' => clean_string($rcsaHeader->jenis_existing_control),
                'department_id' => $rcsaHeader->unit_kerja_id,
                'status' => $rcsaHeader->status,
                'year' => $rcsaHeader->year,
                'updated_at' => $rcsaHeader->updated_at,
                'created_at' => $rcsaHeader->created_at,
                'created_by' => $rcsaHeader->created_by
                // 'created_by_name' => $createdByName,
            ];
            
            $message = 'RCSA header berhasil disimpan dengan status draft dan menunggu persetujuan.';

            return json(200, true, 'Berhasil Disimpan', $message, $responseData);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = TrRcsaHeader::with([
            'rcsaResidual',
            'rcsaRisikoList',
            'createdBy:id,username,id',
            'updatedBy:id,username,id',
            'department:id,name',
        ])
        ->find($id);

         if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data rcsa header tidak ditemukan.', null);
        }
        $item = $data;

        if (!empty($item->rcsaResidual)) {
            $rcsaResidual = $item->rcsaResidual->map(function ($residual) {
                return [
                    'id' => $residual->id,
                    'kuartal' => $residual->kuartal,
                    'residual_eksposur_risiko_kualitatif' => $residual->residual_eksposur_risiko_kualitatif,
                    'residual_eksposur_risiko_kuantitatif' => $residual->residual_eksposur_risiko_kuantitatif,
                    'residual_level_risiko' => $residual->residual_level_risiko,
                    'residual_nilai_dampak' => $residual->residual_nilai_dampak,
                    'residual_nilai_probabilitas' => $residual->residual_nilai_probabilitas,
                    'residual_skala_dampak' => $residual->residual_skala_dampak,
                    'residual_skala_probabilitas' => $residual->residual_skala_probabilitas,
                    'residual_skala_risiko' => $residual->residual_skala_risiko,
                ];
            });
        }
            
        if (!empty($item->rcsaResidual)) {
            $rcsaRencanaRisiko = $item->rcsaRisikoList->map(function ($rrList) {
                return [
                    'id' => $rrList->id,
                    'jenis_rencana_perlakuan_risiko' => $rrList->jenis_rencana_perlakuan_risiko,
                ];
            });
        }
            
        $resData = 
             [
                'id' => $item->id,
                'unit_kerja_id' => $item->unit_kerja_id,
                'status' => $item->status,
                'pilihan_sasaran' => $item->pilihan_sasaran,
                'pilihan_strategi' => $item->pilihan_strategi,
                'hasil_yang_diharapkan_perusahaan' => $item->hasil_yang_diharapkan_perusahaan,
                'nilai_risiko_yang_akan_timbul' => $item->nilai_risiko_yang_akan_timbul,
                'nilai_limit_risiko' => $item->nilai_limit_risiko,
                'keputusan_penetapan' => $item->keputusan_penetapan,
                'perkiraan_waktu_terpapar_risiko' => $item->perkiraan_waktu_terpapar_risiko,
                'deskripsi_dampak' => $item->deskripsi_dampak,
                'kategori_dampak' => $item->kategori_dampak,
                'penilaian_efektivitas_kontrol' => $item->penilaian_efektivitas_kontrol,
                'jenis_existing_control' => $item->jenis_existing_control,
                'existing_control' => $item->existing_control,
                'kategori_threshold_kri_bahaya' => $item->kategori_threshold_kri_bahaya,
                'kategori_threshold_kri_hati_hati' => $item->kategori_threshold_kri_hati_hati,
                'kategori_threshold_kri_aman' => $item->kategori_threshold_kri_aman,
                'key_risk_indicators' => $item->key_risk_indicators,
                'unit_satuan_kri' => $item->unit_satuan_kri,
                'penyebab_risiko' => $item->penyebab_risiko,
                'deskripsi_peristiwa_risiko' => $item->deskripsi_peristiwa_risiko,
                'peristiwa_risiko' => $item->peristiwa_risiko,
                'kategori_risiko_t2_t3_kbumn' => $item->kategori_risiko_t2_t3_kbumn,
                'kategori_risiko_bumn' => $item->kategori_risiko_bumn,
                'nama_bumn' => $item->nama_bumn,
                'kode_bumn' => $item->kode_bumn,
                'sasaran_kbumn' => $item->sasaran_kbumn,
                'opsi_perlakuan_risiko' => $item->opsi_perlakuan_risiko,
                'rencana_perlakuan_risiko' => $item->rencana_perlakuan_risiko,
                'output_perlakuan_risiko' => $item->output_perlakuan_risiko,
                'biaya_perlakuan_risiko' => $item->biaya_perlakuan_risiko,
                'jenis_program_dalam_rkap' => $item->jenis_program_dalam_rkap,
                'pic' => $item->pic,
                'timeline_bulan_akhir' => $item->timeline_bulan_akhir,
                'timeline_bulan_awal' => $item->timeline_bulan_awal,
                'asumsi_perhitungan_dampak' => $item->asumsi_perhitungan_dampak,
                'inherent_level_risiko' => $item->inherent_level_risiko,
                'inherent_skala_risiko' => $item->inherent_skala_risiko,
                'inherent_eksposur_risiko_kualitatif' => $item->inherent_eksposur_risiko_kualitatif,
                'inherent_eksposur_risiko_kuantitatif' => $item->inherent_eksposur_risiko_kuantitatif,
                'inherent_nilai_probabilitas' => $item->inherent_nilai_probabilitas,
                'inherent_skala_probabilitas' => $item->inherent_skala_probabilitas,
                'inherent_nilai_dampak' => $item->inherent_nilai_dampak,
                'inherent_skala_dampak' => $item->inherent_skala_dampak,
                'year' => $item->year,
                'created_by' => $item->created_by,
                'updated_by' => $item->updated_by,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'dataResidual' => $rcsaResidual,
                'dataRisikoList' => $rcsaRencanaRisiko,       
            ];
         $resData = clean_recursive($resData);

        return json(200, true, 'Data Ditemukan', 'Data rcsa header berhasil diambil.', $resData);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $currentUser = auth()->user();

        // Validasi role: hanya role 1, 2, 3 yang diizinkan
        $roleCheck = check_role($currentUser, [1, 2, 3]);
        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $RcsaHeader = TrRcsaHeader::when(in_array($currentUser->role_id, [2, 3]), function ($query) use ($currentUser) {
        // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
        $query->where('unit_kerja_id', $currentUser->department_id);
        })->find($id);

        if (!$RcsaHeader) {
            return json(404, false, 'Data Tidak Ditemukan', 'RCSA tidak ditemukan.', null);
        }

        if ($RcsaHeader->status === 'approved') {
            return json(403, false, 'Akses Ditolak', 'Semua data sudah terisi dan di-approve, tidak bisa dirubah lagi.', null);
        }

        if (in_array($currentUser->role_id, [2, 3])) {
            if ($request->has('unit_kerja_id') && $request->input('unit_kerja_id') != $currentUser->department_id) {
                return json(403, false, 'Akses Ditolak', 'Anda tidak dapat mengubah department_id ke department lain.', null);
            }
        }

        $residual = TrRcsaResidual::where('rcsa_id', $id)->get();
        
        if (empty($residual)) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data Residual tidak ditemukan.', null);
        }

        $RisikoList = TrRcsaRencanaRisikoList::where('rcsa_id', $id)->get();
        if (!$RisikoList) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data Rencana Risiko List tidak ditemukan.', null);
        }
            

        return $this->RcsaUpdate($request, $RcsaHeader, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $rcsaHeader = TrRcsaHeader::find($id);

            if (!$rcsaHeader) {
                return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
            }

            $currentUser = auth()->user();
            $currentUserRole = $currentUser->role_id;

            // VALIDASI HAK AKSES DELETE
            // Hanya Superadmin (role 1) yang bisa delete
            if ($currentUserRole !== 1 ) {
                return json(404, false, 'Akses Ditolak', 'Hanya Superadmin dan User biasa yang dapat menghapus data RCSA.', null);
            }

             // OPTIONAL: Cek apakah sudah approved dan complete
            if ($rcsaHeader->status === 'approved') {
                return json(400, false, 'Tidak Dapat Dihapus', 'Rcsa sudah approved dan lengkap tidak dapat dihapus.', null);
            }

            /********* HAPUS RCSA Residual **********/
            TrRcsaResidual::where('rcsa_id', $rcsaHeader->id)->delete();

            /********* HAPUS RCSA Risiko List **********/
            TrRcsaRencanaRisikoList::where('rcsa_id', $rcsaHeader->id)->delete();

             $deletedData = [
                'id' => $rcsaHeader->id,
                'pilihan_sasaran' => $rcsaHeader->pilihan_sasaran,
                'pilihan_strategi' => $rcsaHeader->pilihan_strategi,
                'peristiwa_risiko' => $rcsaHeader->peristiwa_risiko,
                'penyebab_risiko' => $rcsaHeader->penyebab_risiko,
                'unit_kerja_id' => $rcsaHeader->unit_kerja_id,
                'status' => $rcsaHeader->status,
                'created_by' => $rcsaHeader->created_by,
                'created_at' => $rcsaHeader->created_at,
                'deleted_by' => $currentUser->id,
                'deleted_at' => now()
            ];

            \Log::info('Risk Header Deleted', [
                'deleted_data' => $deletedData,
                'deleted_by_user' => $currentUser->id,
                'deleted_by_username' => $currentUser->username ?? 'Unknown'
            ]);

            DB::commit();

            return json(200, true, 'Berhasil Dihapus', 'RCSA berhasil dihapus dari sistem.', [
                'deleted_id' => $deletedData['id'],
                'deleted_peristiwa_risiko' => clean_string($deletedData['peristiwa_risiko']),
                'deleted_pilihan_sasaran' => $deletedData['pilihan_sasaran'],
                'deleted_at' => $deletedData['deleted_at'],
                'deleted_by' => $deletedData['deleted_by']
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();

            \Log::error('Error deleting RCSA', [
                'rcsa_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return json(500, false, 'Gagal Menghapus', 'Terjadi kesalahan sistem saat menghapus data.', $e->getMessage());
        }

    }

     public function sasaran(Request $request)
    {
        $user = auth()->user();

        $perPage = $request->input('per_page', 10);

        $query = TrRcsaHeader::with([
            'rcsaResidual',
            'rcsaRisikoList',
            'createdBy:id,username,id',
            'updatedBy:id,username,id',
            'department:id,name',
        ])
        ->when(in_array($user->role_id, [2, 3]), function ($query) use ($user) {
            $query->where('unit_kerja_id', $user->department_id);
        })
        ->when($request->pilihan_sasaran, function ($query) use ($request) {
            $query->where('pilihan_sasaran', 'like', '%' . $request->pilihan_sasaran . '%');
        })
        ->when($request->tahun, function ($query) use ($request) {
            $query->where('year', $request->tahun);
        })
        ->where('status', 'approved')
        ->orderBy('id', 'desc');

        $query->count();
        // Pagination, ambil data per halaman
        $data = $query->paginate($perPage);

        if ($query->count() < 1) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data rcsa sasaran tidak ditemukan.', null);
        }
        
        $resData = collect($data->items())->map(function ($item) {
             return [
                'id' => $item->id,
                'department_id' => $item->unit_kerja_id,
                'department_name' => $item->department->name ?? '',
                'status' => $item->status,
                'peristiwa_risiko' => $item->peristiwa_risiko,
                'penyebab_risiko' => $item->penyebab_risiko,
                'deskripsi_dampak' => $item->deskripsi_dampak,
                'pilihan_sasaran' => $item->pilihan_sasaran,
                'year' => $item->year,
                'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
                'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,
                'created_by' => $item->created_by ?? null,
                'created_by_name' => get_decrypted_name($item->createdBy),
                'updated_by' => $item->updated_by ?? null,
                'updated_by_name' => get_decrypted_name($item->updatedBy),
             ];    
        });

        $cleanData = clean_recursive([
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'data' => $resData,
        ]);
        return json(200, true, 'Data Ditemukan', 'Data rcsa sasaran berhasil diambil.',$cleanData);
    }

    private function RcsaUpdate(Request $request, $rcsaHeader, $rcsa_id)
    {
         
        $allowedFields = [
            'asumsi_perhitungan_dampak',
            'deskripsi_dampak',
            'biaya_perlakuan_risiko',
            'deskripsi_peristiwa_risiko',
            'hasil_yang_diharapkan_perusahaan',
            'inherent_eksposur_risiko_kualitatif',
            'inherent_eksposur_risiko_kuantitatif',
            'inherent_level_risiko',
            'inherent_nilai_dampak',
            'existing_control',
            'inherent_nilai_probabilitas',
            'inherent_skala_dampak',
            'inherent_skala_probabilitas',
            'inherent_skala_risiko',
            'jenis_existing_control',
            'jenis_program_dalam_rkap',
            'kategori_dampak',
            'kategori_risiko_bumn',
            'kategori_risiko_t2_t3_kbumn',
            'kategori_threshold_kri_aman',
            'kategori_threshold_kri_bahaya',
            'kategori_threshold_kri_hati_hati',
            'keputusan_penetapan',
            'key_risk_indicators',
            'kode_bumn',
            'nama_bumn',
            'nilai_limit_risiko',
            'nilai_risiko_yang_akan_timbul',
            'opsi_perlakuan_risiko',
            'output_perlakuan_risiko',
            'penilaian_efektivitas_kontrol',
            'penyebab_risiko',
            'peristiwa_risiko',
            'perkiraan_waktu_terpapar_risiko',
            'pilihan_sasaran',
            'pilihan_strategi',
            'rencana_perlakuan_risiko',
            'sasaran_kbumn',
            'timeline_bulan_akhir',
            'timeline_bulan_awal',
            'unit_satuan_kri',
            'unit_kerja_id',
            'year'
        ];

         $validator = Validator::make($request->all(), [
            'asumsi_perhitungan_dampak' =>'required|string',
            'deskripsi_dampak' => 'required|string',
            'biaya_perlakuan_risiko' => 'required|numeric',
            'deskripsi_peristiwa_risiko' => 'required|string',
            'existing_control' => 'nullable|string',
            'hasil_yang_diharapkan_perusahaan' => 'required|string',
            'kategori_dampak'=> 'required|numeric',
            'inherent_eksposur_risiko_kualitatif' => 'required_if:kategori_dampak,1',
            'inherent_eksposur_risiko_kuantitatif' => 'required|numeric',
            'inherent_level_risiko' => 'required|string',
            'inherent_nilai_dampak' => 'required|numeric',
            'inherent_nilai_probabilitas' => 'required|numeric',
            'inherent_skala_dampak' => 'required|numeric',
            'inherent_skala_probabilitas' => 'required|numeric',
            'inherent_skala_risiko' => 'required|numeric',
            'jenis_existing_control'=> 'required|string',
            'jenis_program_dalam_rkap' => 'required|string',
            'kategori_risiko_bumn' => 'required|string',
            'kategori_risiko_t2_t3_kbumn' => 'required|string',
            'kategori_threshold_kri_aman' => 'required|string',
            'kategori_threshold_kri_bahaya' => 'required|string',
            'kategori_threshold_kri_hati_hati' => 'required|string',

            'keputusan_penetapan' => 'required|numeric',
            'key_risk_indicators' => 'required|string',
            'kode_bumn' => 'required|string',
            'nama_bumn' => 'required|string',
            'nilai_limit_risiko' => 'required|string',
            'nilai_risiko_yang_akan_timbul' => 'required|string',

            'opsi_perlakuan_risiko' => 'required|string',
            'output_perlakuan_risiko' => 'required|string',
            'penilaian_efektivitas_kontrol' => 'required|string',
            'penyebab_risiko' => 'required|string',
            'peristiwa_risiko' => 'required|string',
            'perkiraan_waktu_terpapar_risiko' => 'required|string',
            'pic' => 'nullable|string',
            'pilihan_sasaran' => 'required|string',
            'pilihan_strategi'=> 'required|string',
            'rencana_perlakuan_risiko' => 'required|string',
            'sasaran_kbumn' => 'required|string',
            'timeline_bulan_akhir' => 'required|date',
            // 'timeline_bulan_awal' => 'required|date',
            // 'unit_satuan_kri' => 'required|string',
            'unit_kerja_id' => 'required|numeric',
            'year'=> 'required|numeric',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $currentUser = auth()->user();
        $dataResidual = (array) $request->input('dataResidual');
        $dataRisikoList = (array) $request->input('dataRisikoList');
        // dd($dataResidual);
        try {
            DB::beginTransaction();

            //Residual
            foreach ($dataResidual as $itemRes) {
                // dd($itemRes['residual_eksposur_risiko_kualitatif']);
                TrRcsaResidual::where('id',$itemRes['id'])
                ->where('rcsa_id', $itemRes['rcsa_id'])
                ->update([
                    'kuartal' => $itemRes['kuartal'],
                    'residual_skala_dampak' => $itemRes['residual_skala_dampak'],
                    'residual_nilai_dampak' => $itemRes['residual_nilai_dampak'],
                    'residual_skala_probabilitas' => $itemRes['residual_skala_probabilitas'],
                    'residual_nilai_probabilitas' => $itemRes['residual_nilai_probabilitas'],
                    'residual_eksposur_risiko_kuantitatif' => $itemRes['residual_eksposur_risiko_kuantitatif'],
                    'residual_eksposur_risiko_kualitatif' => $itemRes['residual_eksposur_risiko_kualitatif'],
                    'residual_skala_risiko' => $itemRes['residual_skala_risiko'],
                    'residual_level_risiko' => $itemRes['residual_level_risiko']
                ]);
            }

             /********* HAPUS RCSA Risiko List **********/
            TrRcsaRencanaRisikoList::where('rcsa_id', $rcsa_id)->delete();

            //Insert kembali RisikoList
            $concat_rcsaid = ['rcsa_id' => $rcsa_id];
            if(!empty($dataRisikoList)){
                 $mergedRList = array();
                foreach($dataRisikoList as $itemList){
                    $mergedRList[] = array_merge($itemList, $concat_rcsaid);
                }
                TrRcsaRencanaRisikoList::insert($mergedRList);
            }

            // foreach ($dataRisikoList as $itemReq) {
            //     TrRcsaRencanaRisikoList::where('id',$itemReq['id'])
            //     ->where('rcsa_id', $itemReq['rcsa_id'])
            //     ->update([
            //         'jenis_rencana_perlakuan_risiko' => $itemReq['jenis_rencana_perlakuan_risiko']
            //     ]);
            // }

            // HANYA AMBIL DATA YANG DIIZINKAN
            $updateData = [];
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            $updateData['updated_by'] = auth()->id();

            // Superadmin (role 1) boleh ubah departemen dari request
            if ($currentUser->role_id == 1) {
                if ($request->has('unit_kerja_id')) {
                    $updateData['unit_kerja_id'] = $request->input('unit_kerja_id');
                }
            } else {
                // Role lain (2, 3) selalu pakai department_id user
                $updateData['unit_kerja_id'] = $currentUser->department_id;
            }

            /* PIC */
            if(!empty($request->input('pic'))){
                $updateData['pic'] = $request->input('pic');
            }
            /* END PIC */
        
           $rcsaHeader->update($updateData);
           
           DB::commit();
           $rcsaHeader->refresh();
           $rcsaHeader->load([
                'department:id,name',
                'createdBy:id,username',
            ]);

           $createdByName = 'Unknown User';
            try {
                $createdByName = get_decrypted_name($rcsaHeader->createdBy);
            } catch (\Throwable $e) {
                \Log::warning("Error handling createdBy: {$e->getMessage()}");
            }
            
            $responseData = [
                'id' => $rcsaHeader->id,
                'pilihan_sasaran' => clean_string($rcsaHeader->pilihan_sasaran),
                'pilihan_strategi' => clean_string($rcsaHeader->pilihan_strategi),
                'asumsi_perhitungan_dampak' => clean_string($rcsaHeader->asumsi_perhitungan_dampak),
                'deskripsi_dampak' => clean_string($rcsaHeader->deskripsi_dampak),
                'biaya_perlakuan_risiko' => clean_string($rcsaHeader->biaya_perlakuan_risiko),
                'deskripsi_peristiwa_risiko' => clean_string($rcsaHeader->deskripsi_peristiwa_risiko),
                'existing_control' => clean_string($rcsaHeader->existing_control),
                'inherent_nilai_probabilitas' => clean_string($rcsaHeader->inherent_nilai_probabilitas),
                'inherent_skala_dampak' => clean_string($rcsaHeader->inherent_skala_dampak),
                'inherent_skala_probabilitas' => clean_string($rcsaHeader->inherent_skala_probabilitas),
                'inherent_skala_risiko' => clean_string($rcsaHeader->inherent_skala_risiko),
                'jenis_existing_control' => clean_string($rcsaHeader->jenis_existing_control),
                'department_id' => $rcsaHeader->unit_kerja_id,
                'status' => $rcsaHeader->status,
                'year' => $rcsaHeader->year,
                'updated_at' => $rcsaHeader->updated_at,
                'created_at' => $rcsaHeader->created_at,
                'created_by' => $rcsaHeader->created_by,
                'created_by_name' => $createdByName
            ];

            return json(200, true, 'Berhasil Diupdate', 'Data draft berhasil diupdate. Silakan submit data ketika sudah siap untuk proses approval.', $responseData);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

    public function submit(Request $request, $id)
    {
        $currentUser = auth()->user();

        $roleCheck = check_role($currentUser, [1, 2, 3]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $rcsaHeader = TrRcsaHeader::when(in_array($currentUser->role_id, [2, 3]), function ($query) use ($currentUser) {
            // Jika role_id = 2 atau 3, batasi department yang terlihat sesuai department user
            $query->where('unit_kerja_id', $currentUser->department_id);
        })->find($id);

        if (!$rcsaHeader) {
            return json(404, false, 'Data Tidak Ditemukan', 'Risk header tidak ditemukan.', null);
        }

        if ($rcsaHeader->status !== 'draft') {
            return json(403, false, 'Akses Ditolak', 'Hanya data dengan status draft yang dapat disubmit.', null);
        }

        try {
            DB::beginTransaction();

            $rcsaHeader->update([
                'status' => 'submit',
                'submitted_at' => now(),
                'submitted_by' => auth()->id()
            ]);

            $jabatanId = null;

            if ($currentUser->jabatan_id) {
                $jabatanId = $currentUser->jabatan_id;
            } else {
                $jabatan = MstJabatan::where('department_id', $rcsaHeader->unit_kerja_id)->first();
                $jabatanId = $jabatan ? $jabatan->id : null;
            }

            $existingApproval = \App\Models\MstApproval::where('document_id', $rcsaHeader->id)->first();

            if ($existingApproval) {
                $existingApproval->update([
                    'tahun' => $rcsaHeader->year,
                    'jabatan_id' => $jabatanId,
                    'status' => 'pending',
                    'tanggal' => null,
                    'note' => null
                ]);
            } else {
                // Buat approval entry baru
                \App\Models\MstApproval::create([
                    'document_id' => $rcsaHeader->id,
                    'tahun' => $rcsaHeader->year,
                    'posisi' => 1,
                    'jabatan_id' => $jabatanId,
                    'status' => 'pending',
                    'tanggal' => null,
                    'note' => null
                ]);
            }

            DB::commit();

            $rcsaHeader->refresh();
            $responseData = [
                'id' => $rcsaHeader->id,
                'pilihan_sasaran' => clean_string($rcsaHeader->pilihan_sasaran),
                'pilihan_strategi' => clean_string($rcsaHeader->pilihan_strategi),
                'asumsi_perhitungan_dampak' => clean_string($rcsaHeader->asumsi_perhitungan_dampak),
                'deskripsi_dampak' => clean_string($rcsaHeader->deskripsi_dampak),
                'biaya_perlakuan_risiko' => clean_string($rcsaHeader->biaya_perlakuan_risiko),
                'deskripsi_peristiwa_risiko' => clean_string($rcsaHeader->deskripsi_peristiwa_risiko),
                'existing_control' => clean_string($rcsaHeader->existing_control),
                'inherent_nilai_probabilitas' => clean_string($rcsaHeader->inherent_nilai_probabilitas),
                'inherent_skala_dampak' => clean_string($rcsaHeader->inherent_skala_dampak),
                'inherent_skala_probabilitas' => clean_string($rcsaHeader->inherent_skala_probabilitas),
                'inherent_skala_risiko' => clean_string($rcsaHeader->inherent_skala_risiko),
                'jenis_existing_control' => clean_string($rcsaHeader->jenis_existing_control),
                'department_id' => $rcsaHeader->unit_kerja_id,
                'status' => $rcsaHeader->status,
                'year' => $rcsaHeader->year,
                'updated_at' => $rcsaHeader->updated_at,
                'created_at' => $rcsaHeader->created_at,
                'created_by' => $rcsaHeader->created_by
                // 'created_by_name' => $createdByName,
            ];

            return json(200, true, 'Berhasil Submit', 'Data berhasil disubmit untuk proses persetujuan. Status berubah menjadi submit dan data tidak dapat diedit hingga ada keputusan persetujuan.', $responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal Submit', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }



    public function updateIsMainRisk(Request $request, $id)
    {
        $result = check_role(auth()->user(), [1, 2, 3]);
        if ($result !== true) {
            return $result;
        }

        $validator = Validator::make($request->all(), [
            'isMainRisk' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Field isMainRisk wajib boolean.', $validator->errors());
        }

        try {
            DB::beginTransaction();

            $rcsa = TrRcsaHeader::find($id);
            if (!$rcsa) {
                return json(404, false, 'Data Tidak Ditemukan', 'RCSA header tidak ditemukan.', null);
            }

            $user = auth()->user();
            if (in_array((int)$user->role_id, [2, 3]) && (int)$rcsa->unit_kerja_id !== (int)($user->department_id ?? 0)) {
                return json(403, false, 'Akses Ditolak', 'Anda hanya dapat mengubah data departemen Anda sendiri.', null);
            }

            $rcsa->isMainRisk = (bool)$request->input('isMainRisk');
            if (\Illuminate\Support\Facades\Schema::hasColumn('tr_rcsa_header', 'updated_by')) {
                $rcsa->updated_by = $user->id;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('tr_rcsa_header', 'updated_at')) {
                $rcsa->updated_at = now();
            }
            $rcsa->save();

            DB::commit();

            return json(200, true, 'Berhasil', 'isMainRisk berhasil diperbarui.', [
                'id' => $rcsa->id,
                'isMainRisk' => (bool)$rcsa->isMainRisk,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('updateIsMainRisk failed', [
                'rcsa_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return json(500, false, 'Gagal', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }


    
}
