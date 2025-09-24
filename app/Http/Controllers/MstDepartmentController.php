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
        $query = MstDepartment::select('id', 'name', 'abbreviation');

        $user = auth()->user();

        if ($user) {
            // Hanya role selain 1 & 2 yang dibatasi ke departemennya sendiri
            if (!in_array($user->role->id, [1, 2])) {
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

        $departments = $query->orderBy('id')->get();

        if ($departments->isEmpty()) {
            return json(404, false, 'Not Found', 'Data departemen tidak ditemukan.', []);
        }

        return json(200, true, 'Success', 'Daftar departemen yang tersedia.', $departments);
    }

    public function show($id)
    {
        $user = auth()->user();

        if ($user) {
            // Hanya role selain 1 & 2 yang dibatasi
            if (!in_array($user->role->id, [1, 2]) && $user->department_id != $id) {
                return json(404, false, 'Not Found', 'Departemen tidak ditemukan.', null);
            }
        }

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
