<?php

namespace App\Http\Controllers;

use App\Models\MstRole;
use App\Models\MstPage;
use App\Models\RolePage;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
  public function index()
{
    $roles = MstRole::with('permissions:id,name')
        ->where('id', '!=', 1) // sembunyikan Super Admin
        ->get(['id','name']);

    return response()->json([
        'status' => true,
        'data' => $roles
    ]);
}
   public function show($id)
{
    // blokir super admin
    if ($id == 1) {
        return response()->json([
            'status' => false,
            'message' => 'Role not found'
        ], 404);
    }

    $role = MstRole::with(['pages','permissions:id,name'])->find($id);
    if (!$role) {
        return response()->json([
            'status' => false,
            'message' => 'Role not found'
        ], 404);
    }

    $data = [
        'id'   => $role->id,
        'name' => $role->name,
        'permissions' => $role->permissions->map(function ($perm) {
            return [
                'id'   => $perm->id,
                'name' => $perm->name
            ];
        })
    ];

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}


   public function store(Request $request)
{
    $user = auth()->user();
    $roleCheck = check_role($user, 1);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $validated = $request->validate([
        'name'       => 'required|string|max:100|unique:mst_role,name',
        'level'      => 'nullable|integer|min:1',
        'permisions' => 'nullable|array',
        'permisions.*' => 'integer|exists:permissions,id',
    ]);

    DB::beginTransaction();
    try {
        $level = $validated['level'] ?? get_next_role_level();

        $role = MstRole::create([
            'name'       => $validated['name'],
            'level'      => $level,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // simpan hanya permisions yang dikirim
        if (!empty($validated['permisions'])) {
            foreach ($validated['permisions'] as $permId) {
                RolePermission::create([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,

                ]);
            }
        }

        DB::commit();
        return response()->json([
            'status'  => true,
            'message' => 'Role berhasil dibuat',
            'data'    => $role->fresh()
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status'  => false,
            'message' => 'Gagal membuat role: ' . $e->getMessage()
        ], 500);
    }
}

public function update(Request $request, $id)
{
    $user = auth()->user();
    $roleCheck = check_role($user, 1);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $validated = $request->validate([
        'name'                   => 'required|string|max:100|unique:mst_role,name,' . $id,
        'level'                  => 'nullable|integer|min:1',
        'access_pages'           => 'nullable|array',
        'access_pages.*.page_id' => 'required_with:access_pages|integer|exists:mst_page,id',
        'access_pages.*.access'  => 'required_with:access_pages|array',
        'permisions'             => 'nullable|array',
        'permisions.*'           => 'integer|exists:permissions,id'
    ]);

    DB::beginTransaction();
    try {
        $role = MstRole::findOrFail($id);

        $updateData = [
            'name'       => $validated['name'],
            'updated_at' => now(),
            'updated_by' => auth()->id() ?? null,
        ];

        if ($request->has('level')) {
            $updateData['level'] = $validated['level'];
        }

        $role->update($updateData);

        if ($request->has('access_pages')) {
            RolePage::where('role_id', $role->id)->delete();

            foreach ($validated['access_pages'] as $page) {
                RolePage::create([
                    'role_id'    => $role->id,
                    'page_id'    => $page['page_id'],
                    'access'     => json_encode($page['access']),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Sinkronkan permissions sesuai request
        RolePermission::where('role_id', $role->id)->delete();
        if (!empty($validated['permisions'])) {
            foreach ($validated['permisions'] as $permId) {
                RolePermission::create([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,

                ]);
            }
        }

        DB::commit();

        return response()->json([
            'status'  => true,
            'message' => 'Role berhasil diperbarui',
            'data'    => $role->fresh()->only(['id', 'name', 'level', 'created_by', 'created_at', 'updated_at'])
        ]);
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'status'  => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
    public function destroy($id)
    {
        $user = auth()->user();
        $roleCheck = check_role($user, 1);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

    if ($id == 1) {
        return response()->json([
            'status' => false,
            'message' => 'Role Super Admin tidak dapat dihapus'
        ], 404);
    }

        DB::beginTransaction();
        try {
            $role = MstRole::findOrFail($id);

            if ($role->users()->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Role masih digunakan oleh user, tidak dapat dihapus'
                ], 404);
            }

            RolePage::where('role_id', $role->id)->delete();
            RolePermission::where('role_id', $role->id)->delete();

            $role->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Role berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
