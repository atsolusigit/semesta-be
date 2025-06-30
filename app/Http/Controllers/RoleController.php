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
        $roles = MstRole::select('id', 'name')->get();
        return response()->json(['status' => true, 'data' => $roles]);
    }

    public function show($id)
    {
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
        'name' => 'required|string|max:100',
        'access_pages' => 'required|array',
        'access_pages.*.page_id' => 'required|integer|exists:mst_page,id',
        'access_pages.*.access' => 'required|array',
    ]);

    $role = MstRole::create(['name' => $validated['name']]);

    foreach ($validated['access_pages'] as $page) {
        DB::table('tr_role_page')->insert([
            'role_id' => $role->id,
            'page_id' => $page['page_id'],
            'access' => json_encode($page['access']),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return response()->json(['status' => true, 'data' => $role]);
}

    public function update(Request $request, $id)
{
    $request->validate([
        'name'               => 'required|string|max:100|unique:mst_role,name,' . $id,
        'access_pages'       => 'required|array',
        'access_pages.*.page_id' => 'required|integer|exists:mst_page,id',
        'access_pages.*.access'  => 'required|array',
    ]);

    DB::beginTransaction();
    try {
        $role = MstRole::findOrFail($id);
        $role->update(['name' => $request->name]);

        RolePage::where('role_id', $role->id)->delete();
        logger($request->access_pages);


        foreach ($request->access_pages as $page) {
            RolePage::create([
                'role_id' => $role->id,
                'page_id' => $page['page_id'],
                'access'  => json_encode($page['access']),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::commit();
        return response()->json(['status' => true, 'message' => 'Role berhasil diperbarui']);
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
    }
}


    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $role = MstRole::findOrFail($id);
            RolePage::where('role_id', $role->id)->delete();
            $role->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Role berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
