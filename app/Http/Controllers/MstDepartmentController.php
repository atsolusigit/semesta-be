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

        return json(200, 'success', 'Success', 'Daftar departemen yang tersedia.', $departments);
    }

    public function show($id)
    {
        $department = MstDepartment::select('id', 'name', 'abbreviation')->find($id);

        if (!$department) {
            return json(404, 'error', 'Not Found', 'Departemen tidak ditemukan.', null);
        }

        $safeData = collect($department)->map(function ($value) {
            return is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value;
        });

        return json(200, 'success', 'Success', 'Data department berhasil ditemukan.', $safeData);
    }

    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

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
            'created_by' => $user->id, // Simpan plain di database
        ]);

        $userIdToAssign = $request->assign_to_user_id ?? $user->id;

        // Cek apakah user sudah di-assign ke department ini
        $alreadyAssigned = UserDepartment::where('user_id', $userIdToAssign)
            ->where('department_id', $department->id)
            ->exists();

        if (!$alreadyAssigned) {
            UserDepartment::insert([
                'user_id' => $userIdToAssign,
                'department_id' => $department->id,
                'created_by' => $user->id, // Simpan plain di database
            ]);
        }

        // Enkripsi created_by untuk response JSON
        $department->created_by = encrypt_decrypt_md5('enc', $department->created_by);

        return json(200, 'success', 'Success', 'Departemen berhasil ditambahkan dan user diassign.', $department);
    }

    public function update(Request $request, $id)
{
    $user = JWTAuth::parseToken()->authenticate();

    // Menggunakan helper check_validation
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
        'updated_by' => $user->id, // Simpan plain
    ]);

    // Refresh department dari database setelah update
    $department->refresh();

    // Enkripsi created_by untuk response JSON (bukan disimpan)
    $department = MstDepartment::find($id); // Ambil ulang dari database
    $department->created_by = encrypt_decrypt_md5('enc', $department->created_by);

    return json(200, 'success', 'Success', 'Departemen berhasil diperbarui.', $department);
}

    public function destroy($id)
    {
        $department = MstDepartment::find($id);

        if (!$department) {
            return json(404, 'error', 'Not Found', 'Departemen tidak ditemukan.', null);
        }

        $department->delete();

        return json(200, 'success', 'Success', 'Departemen berhasil dihapus.', null);
    }
}
