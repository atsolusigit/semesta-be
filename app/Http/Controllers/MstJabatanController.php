<?php

namespace App\Http\Controllers;

use App\Models\MstJabatan;
use App\Models\MstDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MstJabatanController extends Controller
{
<<<<<<< HEAD
    /**
=======
  /**
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
 * Display a listing of jabatan
 */
public function index(Request $request)
{
    $request->validate([
        'department_id' => 'nullable|integer',
        'search' => 'nullable|string|max:255',
<<<<<<< HEAD
        'per_page' => 'nullable|integer|min:1|max:100'
    ]);

    $perPage = $request->input('per_page', 10);
=======
        'per_page' => 'nullable' // biarkan FE bebas atur, tidak wajib integer
    ]);

    $perPage = $request->input('per_page');
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae

    $query = MstJabatan::with('department:id,name')->orderBy('name', 'asc');

    // Role-based access control
    $user = auth()->user();
    $userRole = $user->role->id ?? $user->role_id ?? 1;
    $userDepartmentId = $user->department_id ?? null;

    // Role 1 dapat melihat semua data, role 2,3,4,5 hanya departemen mereka
    if (in_array($userRole, [2, 3, 4, 5]) && $userDepartmentId) {
        $query->where('department_id', $userDepartmentId);
    }

    // Apply filters
    if ($request->department_id) {
        $query->where('department_id', $request->department_id);
    }

    if ($request->search) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('nipp', 'like', '%' . $search . '%');
        });
    }

<<<<<<< HEAD
    // Pagination
=======
    // Jika per_page kosong atau 'all', ambil semua data tanpa pagination
    if (empty($perPage) || $perPage === 'all') {
        $data = $query->get();

        $mappedData = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'nipp' => $item->nipp,
                'department_id' => $item->department_id,
                'department_name' => $item->department?->name,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
            ];
        });

        return json(200, true, 'Berhasil', 'List data jabatan', [
            'total' => $mappedData->count(),
            'data' => $mappedData,
        ]);
    }

    // Kalau per_page dikirim, gunakan pagination
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    $data = $query->paginate($perPage);

    // Mapping data
    $mappedData = $data->getCollection()->map(function ($item) {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'nipp' => $item->nipp,
            'department_id' => $item->department_id,
            'department_name' => $item->department?->name,
            'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
            'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
        ];
    });

    // Prepare response dengan pagination
    $responseData = [
        'current_page' => $data->currentPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
        'last_page' => $data->lastPage(),
        'from' => $data->firstItem(),
        'to' => $data->lastItem(),
        'data' => $mappedData,
    ];

    return json(200, true, 'Berhasil', 'List data jabatan', $responseData);
}

    /**
     * Store a new jabatan
     */
    public function store(Request $request)
    {
        // Role-based access control - hanya role 1 dan 2 yang bisa store
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'nipp' => 'required|string|max:50|unique:mst_jabatan,nipp',
            'department_id' => 'required|integer|exists:mst_department,id',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        // Role 2 hanya bisa membuat jabatan untuk departemen mereka sendiri
        if ($userRole == 2 && $userDepartmentId) {
            if ($request->department_id !== $userDepartmentId) {
                return json(403, false, 'Akses Ditolak', 'Anda hanya dapat membuat jabatan untuk departemen Anda sendiri', null);
            }
        }

        // Check duplicate name dalam department yang sama
        $exist = MstJabatan::where('name', $request->name)
            ->where('department_id', $request->department_id)
            ->first();

        if ($exist) {
            return json(409, false, 'Gagal', 'Nama jabatan sudah ada dalam departemen ini', null);
        }

        try {
            DB::beginTransaction();

            $data = MstJabatan::create($request->only([
                'name',
                'nipp',
                'department_id'
            ]));

            // $data->load('department:id,name'); // Comment dulu sampai relationship dibuat

            DB::commit();

            return json(201, true, 'Berhasil', 'Data jabatan berhasil ditambahkan', $data);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat menyimpan data', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display specific jabatan
     */
    public function show($id)
    {
        $item = MstJabatan::with('department:id,name')->find($id);

        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Data jabatan tidak ditemukan', null);
        }

        // Role-based access control
        $user = auth()->user();
        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        // Role 1 dapat melihat semua data, role 2,3,4,5 hanya departemen mereka
        if (in_array($userRole, [2, 3, 4, 5]) && $userDepartmentId) {
            if ($item->department_id !== $userDepartmentId) {
                return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki akses untuk melihat data ini', null);
            }
        }

        return json(200, true, 'Berhasil', 'Detail data jabatan', $item);
    }

    /**
     * Update jabatan
     */
    public function update(Request $request, $id)
    {
        // Role-based access control - hanya role 1 dan 2 yang bisa update
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        $jabatan = MstJabatan::find($id);

        if (!$jabatan) {
            return json(404, false, 'Tidak Ditemukan', 'Data jabatan tidak ditemukan', null);
        }

        // Role 2 hanya bisa update jabatan dalam departemen mereka
        if ($userRole == 2 && $userDepartmentId) {
            if ($jabatan->department_id !== $userDepartmentId) {
                return json(403, false, 'Akses Ditolak', 'Anda tidak memiliki akses untuk mengupdate data ini', null);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'nipp' => 'required|string|max:50|unique:mst_jabatan,nipp,' . $id,
            'department_id' => 'required|integer|exists:mst_department,id',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        // Role 2 hanya bisa mengupdate ke departemen mereka sendiri
        if ($userRole == 2 && $userDepartmentId) {
            if ($request->department_id !== $userDepartmentId) {
                return json(403, false, 'Akses Ditolak', 'Anda hanya dapat mengupdate jabatan dalam departemen Anda sendiri', null);
            }
        }

        // Check duplicate name dalam department yang sama
        $exist = MstJabatan::where('name', $request->name)
            ->where('department_id', $request->department_id)
            ->where('id', '!=', $id)
            ->first();

        if ($exist) {
            return json(409, false, 'Gagal', 'Nama jabatan sudah ada dalam departemen ini', null);
        }

        try {
            DB::beginTransaction();

            $jabatan->update($request->only([
                'name',
                'nipp',
                'department_id'
            ]));

            $jabatan->load('department:id,name');

            DB::commit();

            return json(200, true, 'Berhasil', 'Data jabatan berhasil diperbarui', $jabatan);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat mengupdate data', null);
        }
    }

    /**
     * Remove jabatan
     */
    public function destroy($id)
    {
        // Role-based access control - hanya role 1 yang bisa delete
        $user = auth()->user();
        $roleCheck = check_role($user, 1);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $jabatan = MstJabatan::find($id);

        if (!$jabatan) {
            return json(404, false, 'Tidak Ditemukan', 'Data jabatan tidak ditemukan', null);
        }

        try {
            DB::beginTransaction();

            $jabatan->delete();

            DB::commit();

            return json(200, true, 'Berhasil', 'Data jabatan berhasil dihapus', null);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat menghapus data', null);
        }
    }
}
