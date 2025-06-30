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
        $query = MstDepartment::query()->select('id', 'name', 'abbreviation');

        if ($request->has('search')) {
        $query->where('name', 'like', '%' . $request->search . '%');
    }

        $departments = $query->orderBy('id')->get();

        return response()->json([
        'status' => true,
        'message' => 'Daftar departemen yang tersedia.',
        'data' => $departments,
    ]);
}

  public function show($id)
{
    $department = MstDepartment::select('id', 'name', 'abbreviation')->findOrFail($id);

    // Paksa semua string ke UTF-8 aman
    $safeData = collect($department)->map(function ($value) {
        return is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value;
    });

    return response()->json([
        'status' => true,
        'message' => 'Data department berhasil ditemukan.',
        'data' => $safeData
    ], 200, [], JSON_UNESCAPED_UNICODE);
}

    public function store(Request $request)
{
    $user = JWTAuth::parseToken()->authenticate();

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:100|unique:mst_department,name',
        'abbreviation' => 'nullable|string|max:10',
        'assign_to_user_id' => 'nullable|exists:users,id'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    // Buat departemen
    $department = MstDepartment::create([
        'name' => $request->name,
        'abbreviation' => $request->abbreviation,
        'created_by' => $user->id,
    ]);

    // Assign user (jika tidak ada, default user login)
    $userIdToAssign = $request->assign_to_user_id ?? $user->id;

    // Cek apakah sudah pernah assign
    $alreadyAssigned = UserDepartment::where('user_id', $userIdToAssign)
        ->where('department_id', $department->id)
        ->exists();

    if (!$alreadyAssigned) {
        UserDepartment::create([
            'user_id' => $userIdToAssign,
            'department_id' => $department->id,
            'created_by' => $user->id,
        ]);
    }

    return response()->json([
        'status' => true,
        'message' => 'Departemen berhasil ditambahkan dan user diassign.',
        'data' => $department
    ]);
}

public function update(Request $request, $id)
{
    $user = JWTAuth::parseToken()->authenticate();

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:100|unique:mst_department,name,' . $id,
        'abbreviation' => 'nullable|string|max:10',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal',
            'errors' => $validator->errors()
        ], 422);
    }

    $department = MstDepartment::find($id);

    if (!$department) {
        return response()->json([
            'status' => false,
            'message' => 'Departemen tidak ditemukan'
        ], 404);
    }

    $department->update([
        'name' => $request->name,
        'abbreviation' => $request->abbreviation,
        'updated_by' => $user->id
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Departemen berhasil diperbarui',
        'data' => $department
    ]);
}
    public function destroy($id)
    {
        $department = MstDepartment::findOrFail($id);
        $department->delete();

        return response()->json([
            'status' => true,
            'message' => 'Departemen berhasil dihapus.'
        ]);
    }
}
