<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstDepartment;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\UserDepartment;
use App\Models\User;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Arr;

class MstDepartmentController extends Controller
{
    public function index(Request $request)
{
    $perPage = $request->input('per_page');

    $query = MstDepartment::select('id', 'name', 'abbreviation', 'created_at', 'created_by')
        ->with('createdBy:id,name,email');

    $user = auth()->user();

    if ($user) {
        // Check authorization: only role 1, 2, 4, 5, and 6 can access all departments
        if (!in_array($user->role->id, [1, 2, 4, 5, 6])) {
            $query->where('id', $user->department_id);
        }
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('abbreviation', 'like', "%{$search}%");
        });
    }

    // Jika per_page kosong atau = "all", ambil semua data
    if (empty($perPage) || $perPage === 'all') {
        $data = $query->orderBy('id')->get();

        $mappedData = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'abbreviation' => $item->abbreviation,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'created_by' => $item->created_by,
                'created_by_name' => $item->createdBy ? get_decrypted_name($item->createdBy) : null,
                'created_by_email' => $item->createdBy ? get_decrypted_email($item->createdBy) : null,
            ];
        });

        return json(200, true, 'Success', 'Daftar departemen yang tersedia.', [
            'total' => $mappedData->count(),
            'data' => $mappedData,
        ]);
    }

    // Kalau per_page dikirim, tetap gunakan pagination Laravel
    $data = $query->orderBy('id')->paginate($perPage);

    $mappedData = $data->getCollection()->map(function ($item) {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'abbreviation' => $item->abbreviation,
            'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
            'created_by' => $item->created_by,
            'created_by_name' => $item->createdBy ? get_decrypted_name($item->createdBy) : null,
            'created_by_email' => $item->createdBy ? get_decrypted_email($item->createdBy) : null,
        ];
    });

    $responseData = [
        'current_page' => $data->currentPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
        'last_page' => $data->lastPage(),
        'from' => $data->firstItem(),
        'to' => $data->lastItem(),
        'data' => $mappedData,
    ];

    if ($mappedData->isEmpty()) {
        return json(404, false, 'Not Found', 'Data departemen tidak ditemukan.', []);
    }

    return json(200, true, 'Success', 'Daftar departemen yang tersedia.', $responseData);
}

public function show($id)
{
    $user = auth()->user();

    if ($user) {
        // Check authoriztion: only role 1, 2, 4, 5, and 6 can access other departments
        if (!in_array($user->role->id, [1, 2, 4, 5, 6]) && $user->department_id != $id) {
            return json(404, false, 'Not Found', 'Departemen tidak ditemukan.', null);
        }
    }

    $department = MstDepartment::select('id', 'name', 'abbreviation', 'created_at', 'created_by')
        ->with('createdBy:id,name,email')
        ->find($id);

    if (!$department) {
        return json(404, 'error', 'Not Found', 'Departemen tidak ditemukan.', null);
    }

    $safeData = [
        'id' => $department->id,
        'name' => is_string($department->name) ? mb_convert_encoding($department->name, 'UTF-8', 'UTF-8') : $department->name,
        'abbreviation' => is_string($department->abbreviation) ? mb_convert_encoding($department->abbreviation, 'UTF-8', 'UTF-8') : $department->abbreviation,
        'created_at' => $department->created_at ? $department->created_at->format('Y-m-d') : null,
        'created_by' => $department->created_by,
        'created_by_name' => $department->createdBy ? get_decrypted_name($department->createdBy) : null,
        'created_by_email' => $department->createdBy ? get_decrypted_email($department->createdBy) : null,
    ];

    return json(200, 'success', 'Success', 'Data department berhasil ditemukan.', $safeData);
}

    public function store(Request $request)
    {
        // Check authorization: only role 1 and 2 can store
        $result = check_role(auth()->user(), [1, 2]);
        if ($result !== true) {
            return $result;
        }

        $user = auth()->user();

        // Menggunakan helper check_validation
        $validation = check_validation($request->all(), [
            'name' => 'required|string|max:100|unique:mst_department,name',
            'abbreviation' => 'nullable|string|max:10',
            'assign_to_user_id' => 'nullable|exists:users,id'
        ]);

        if ($validation[0] == 1) {
            return $validation[1];
        }

        $department = MstDepartment::create([
            'name' => $request->name,
            'abbreviation' => $request->abbreviation,
            'created_by' => $user->id,
        ]);

        $userIdToAssign = $request->assign_to_user_id ?? $user->id;

        $alreadyAssigned = UserDepartment::where('user_id', $userIdToAssign)
            ->where('department_id', $department->id)
            ->exists();

        if (!$alreadyAssigned) {
            UserDepartment::insert([
                'user_id' => $userIdToAssign,
                'department_id' => $department->id,
                'created_by' => $user->id,
            ]);
        }

        $department->created_by = encrypt_decrypt_md5('enc', $department->created_by);

        return json(200, 'success', 'Success', 'Departemen berhasil ditambahkan dan user diassign.', $department);
    }

    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $result = check_role(auth()->user(), [1, 2]);
        if ($result !== true) {
            return $result;
        }

        $user = auth()->user();

        $validation = check_validation($request->all(), [
            'name' => 'required|string|max:100|unique:mst_department,name,' . $id,
            'abbreviation' => 'nullable|string|max:10',
        ]);

        if ($validation[0] == 1) {
            return $validation[1];
        }

        $department = MstDepartment::find($id);

        if (!$department) {
            return json(404, 'error', 'Not Found', 'Departemen tidak ditemukan.', null);
        }

        $department->update([
            'name' => $request->name,
            'abbreviation' => $request->abbreviation,
            'updated_by' => $user->id,
        ]);

        $department->refresh();
        $department = MstDepartment::find($id);
        $department->created_by = encrypt_decrypt_md5('enc', $department->created_by);

        return json(200, 'success', 'Success', 'Departemen berhasil diperbarui.', $department);
    }

    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $result = check_role(auth()->user(), [1]);
        if ($result !== true) {
            return $result;
        }

        $department = MstDepartment::find($id);

        if (!$department) {
            return json(404, 'error', 'Not Found', 'Departemen tidak ditemukan.', null);
        }

        $department->delete();

        return json(200, 'success', 'Success', 'Departemen berhasil dihapus.', null);
    }
}
