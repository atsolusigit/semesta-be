<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstEmailRiskOwner;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\RencanaInvestasi;
<<<<<<< HEAD
=======
use Illuminate\Support\Facades\Http;
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae

class MstEmailRiskOwnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
<<<<<<< HEAD
    public function index(Request $request)
   {
        $perPage = $request->input('per_page', 10);

        $query = MstEmailRiskOwner::query()->orderBy('id', 'asc');
=======
     public function index(Request $request)
   {
        $perPage = $request->input('per_page', 10);

        $query = MstEmailRiskOwner::query()
        ->with('department')            
        ->orderBy('id', 'asc');
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('unit_kerja_nama', 'like', "%{$search}%")
                ->orWhere('unit_kerja_email', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan risk_owner
        if ($request->filled('unit_kerja_nama')) {
            $query->where('unit_kerja_nama', $request->kategori);
        }

        // Pagination
        $data = $query->paginate($perPage);

        $resData = $data->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'unit_kerja_id' => $item->unit_kerja_id,
                'unit_kerja_email' => $item->unit_kerja_email,
                'unit_kerja_nama' => $item->unit_kerja_nama,
<<<<<<< HEAD
=======

                'department' => $item->department ? [
                    'id'            => $item->department->id,
                    'name'          => $item->department->name,
                    'abbreviation'  => $item->department->abbreviation,
                ] : null,

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
            ];
        });

        $responseData = [
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'data' => $resData,
        ];

        return json(200, true, 'Data Ditemukan', 'Data berhasil diambil.', $responseData);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
<<<<<<< HEAD
        // Check authorization: only role 1 and 2 can store
=======
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menambah data', null);
        }

        $exists = MstEmailRiskOwner::where('unit_kerja_id', $request->unit_kerja_id)->exists();
<<<<<<< HEAD

=======
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        if ($exists) {
            return json(500, false, 'Unit Kerja Exist', 'Unit Kerja '.$request->unit_kerja_id.' Exists', null);
        }

<<<<<<< HEAD
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'unit_kerja_nama' => 'required|string',
            'unit_kerja_email' => 'required|string',
            'unit_kerja_id' => 'required|integer',
=======
        $validator = Validator::make($request->all(), [
            'unit_kerja_nama'   => 'required|string',
            'unit_kerja_email'  => 'required|string',
            'unit_kerja_id'     => 'required|integer',
            'department_id'     => 'nullable|integer|exists:mst_department,id',
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = MstEmailRiskOwner::create([
<<<<<<< HEAD
            'unit_kerja_nama' => $request->unit_kerja_nama,
            'unit_kerja_email' => $request->unit_kerja_email,
            'unit_kerja_id' => $request->unit_kerja_id,
            'created_by' => $user->id,
=======
            'unit_kerja_nama'  => $request->unit_kerja_nama,
            'unit_kerja_email' => $request->unit_kerja_email,
            'unit_kerja_id'    => $request->unit_kerja_id,
            'department_id'    => $request->department_id,
            'created_by'       => auth()->id(),
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        ]);

        return json(200, true, 'Berhasil Disimpan', 'Data berhasil disimpan.', $data);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
<<<<<<< HEAD
        $data = MstEmailRiskOwner::find($id);
=======
        $data = MstEmailRiskOwner::with('department')->find($id);
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail data berhasil diambil.', $data);
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
<<<<<<< HEAD
     public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
=======
    public function update(Request $request, $id)
    {
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk mengubah data', null);
        }

        $data = MstEmailRiskOwner::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
<<<<<<< HEAD
            'unit_kerja_nama' => 'required|string',
            'unit_kerja_email' => 'required|string',
            'unit_kerja_id' => 'nullable|integer',
=======
            'unit_kerja_nama'   => 'required|string',
            'unit_kerja_email'  => 'required|string',
            'unit_kerja_id'     => 'nullable|integer',
            'department_id'     => 'nullable|integer|exists:mst_department,id',
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

<<<<<<< HEAD
        $data->update($request->only('unit_kerja_id', 'unit_kerja_nama','unit_kerja_email'));
=======
        $data->update(
            $request->only('unit_kerja_id', 'unit_kerja_nama', 'unit_kerja_email', 'department_id')
        );
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae

        return json(200, true, 'Berhasil Diperbarui', 'Data berhasil diperbarui.', $data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $userRole = auth()->user()->role_id ?? null;
        if ($userRole !== 1) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menghapus data', null);
        }

        $data = MstEmailRiskOwner::where('unit_kerja_id', $id)->exists();
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $emailExists = RencanaInvestasi::where('unit_kerja_id', $id)->exists();

        if ($emailExists) {
            return json(500, false, 'Unit Kerja Exists', 'Unit kerja exists di rencana investasi.', null);
        }

        MstEmailRiskOwner::where('unit_kerja_id', $id)->delete();


        return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
    }

    /**
     * Update the specified resource in storage.
     */
     public function sync(Request $request)
    {
        // Check authorization: only role 1 and 2 can update
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk mengubah data', null);
        }
        $dataUnitKerja = (array) $request->input('UnitKerjaErkap');
        $user = auth()->user();

        if(!empty($dataUnitKerja)){

            try {
                DB::beginTransaction();

                    foreach($dataUnitKerja as $item){
        
                       $resUpdate = MstEmailRiskOwner::updateOrCreate(
                            ['unit_kerja_id' => $item['unit_kerja_id']], // Match condition
                            [ 
                                'unit_kerja_nama' => $item['unit_kerja_nama'],
                                'created_by' =>  $user->id,
                            ]
                        );
                    }
                    
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                return json(500, false, 'Gagal Disimpan', 'Terjadi kesalahan sistem.', $th->getMessage());
            }
            
        }

        return json(200, true, 'Berhasil di syncronize', 'Data berhasil di syncronize.', null);
    }
}
