<?php

namespace App\Http\Controllers;

use App\Models\MstRole;
use App\Models\MstPage;
use App\Models\RolePage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index()
    {
        $roles = MstRole::select('id', 'name')->where('id', '!=', 1)->get();
        return response()->json(['status' => true, 'data' => $roles]);
    }

    public function show($id)
    {
        if ($id == 1) {
            return response()->json(['status' => false, 'message' => 'Role not found'], 404);
        }

        $role = MstRole::with(['pages'])->find($id);
        if (!$role) return response()->json(['status' => false, 'message' => 'Role not found'], 404);

        $data = [
            'id'   => $role->id,
            'name' => $role->name,
            'pages' => $role->pages->map(function ($page) {
                return [
                    'id' => $page->id,
                    'name' => $page->name,
                    'access' => $page->pivot->access ?? '{}'
                ];
            })
        ];

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:mst_role,name',
            'level' => 'nullable|integer|min:1',
            'access_pages' => 'nullable|array',
            'access_pages.*.page_id' => 'required_with:access_pages|integer|exists:mst_page,id',
            'access_pages.*.access' => 'required_with:access_pages|array',
        ]);

        DB::beginTransaction();
        try {
            // Tentukan level
            $level = $validated['level'] ?? get_next_role_level();

            // Buat role
            $role = MstRole::create([
                'name' => $validated['name'],
                'level' => $level,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simpan access_pages kalau ada
            if (!empty($validated['access_pages'])) {
                foreach ($validated['access_pages'] as $page) {
                    RolePage::create([
                        'role_id' => $role->id,
                        'page_id' => $page['page_id'],
                        'access' => json_encode($page['access']),
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Role berhasil dibuat',
                'data' => $role->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Gagal membuat role: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                   => 'required|string|max:100|unique:mst_role,name,' . $id,
            'level'                  => 'nullable|integer|min:1', // Tambahkan validasi level
            'access_pages'           => 'nullable|array',
            'access_pages.*.page_id' => 'required_with:access_pages|integer|exists:mst_page,id',
            'access_pages.*.access'  => 'required_with:access_pages|array',
        ]);

        DB::beginTransaction();
        try {
            $role = MstRole::findOrFail($id);

            // Update data role
            $updateData = [
                'name' => $request->name,
                'updated_at' => now(),
                'updated_by' => auth()->id() ?? null,
            ];

            // Tambahkan level jika ada di request
            if ($request->has('level')) {
                $updateData['level'] = $request->level;
            }

            $role->update($updateData);

            // Update access pages
            if ($request->has('access_pages')) {
                RolePage::where('role_id', $role->id)->delete();

                foreach ($request->access_pages as $page) {
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
        if ($id == 1) {
            return response()->json([
                'status' => false,
                'message' => 'Role Super Admin tidak dapat dihapus'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $role = MstRole::findOrFail($id);

            // Cek apakah role masih digunakan user
            if ($role->users()->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Role masih digunakan oleh user, tidak dapat dihapus'
                ], 422);
            }

            // Hapus role pages dulu
            RolePage::where('role_id', $role->id)->delete();

            // Hapus role
            $role->delete();

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Role berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
