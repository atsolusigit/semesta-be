<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\RencanaInvestasi;

class RencanaInvestasiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
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

        // Pagination, ambil data per halaman
        $data = $query->paginate($perPage);

        if (empty($data->investasis())) {
            return json(404, false, 'Data Tidak Dinvestasiukan', 'Data rencana investasi tidak dinvestasiukan.', null);
        }

        $resData = collect($data->investasis())->map(function ($investasi) {

             return [
                'id' => $investasi->id,
                'erkap_id' => $investasi->erkap_id,
                'department_name' => $investasi->department->name ?? '',
                'nama_investasi' => $investasi->nama_investasi,
                'kategori_investasi' => $investasi->kategori_investasi,
                'jenis_investasi' => $investasi->jenis_investasi,
                'year' => $investasi->year,
                'nilai_rkap' => $investasi->nilai_rkap,
                'nilai_revisi' => $investasi->nilai_revisi,
                'keterangan' => $investasi->keterangan,
                'status' => $investasi->status,
                'created_at' => $investasi->created_at ? $investasi->created_at->toISOString() : null,
                'updated_at' => $investasi->updated_at ? $investasi->updated_at->toISOString() : null,
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
            'from' => $data->firstinvestasi(),
            'to' => $data->lastinvestasi(),
            'data' => $resData,
        ]);
        return json(200, true, 'Data Dinvestasiukan', 'Data rencana investasi berhasil diambil.',$cleanData);

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
        ]);

         if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

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
            $data['updated_at'] = null;

            // Superadmin (role 1) boleh pilih departemen dari request
            if ($currentUser->role_id == 1) {
                $data['unit_kerja_id'] = $request->input('unit_kerja_id');
            } else {
                // Role lain (2, 3, dst) selalu pakai department_id user
                $data['unit_kerja_id'] = $currentUser->department_id;
            }
        
           $rInvest = RencanaInvestasi::create($data);

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
            return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
