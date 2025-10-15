<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstPage;
use App\Models\MstRole;
use Illuminate\Support\Facades\DB;
class PageController extends Controller
{
    // Get semua halaman
    public function index()
    {
        $pages = MstPage::select('id', 'name', 'head_url', 'status')->get();
        return response()->json([
            'status' => true,
            'data' => $pages
        ]);
    }

    // Simpan halaman baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:mst_page,name',
            'head_url' => 'required|string|max:255',
            'is_web' => 'nullable|boolean',
            'is_mobile' => 'nullable|boolean',
            'status' => 'nullable|in:0,1',
            'created_by' => 'required|exists:users,id',
        ]);

        $page = MstPage::create([
            'name' => $request->name,
            'head_url' => $request->head_url,
            'is_web' => $request->is_web ?? 1,
            'is_mobile' => $request->is_mobile ?? 0,
            'status' => $request->status ?? 1,
            'created_by' => $request->created_by,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Page created successfully',
            'data' => $page
        ]);
    }

    public function storeWithRoles(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:100|unique:mst_page,name',
        'head_url' => 'required|string|max:255',
        'created_by' => 'required|exists:users,id',
        'access_roles' => 'required|array|min:1',
        'access_roles.*.role_id' => 'required|exists:mst_role,id',
        'access_roles.*.access' => 'required|array|min:1'
    ]);

    \DB::beginTransaction();
    try {
        // Simpan Page ke mst_page
        $page = MstPage::create([
            'name' => $request->name,
            'head_url' => $request->head_url,
            'is_web' => 1,
            'is_mobile' => 0,
            'status' => 1,
            'created_by' => $request->created_by,
        ]);

        // Simpan role access ke tr_role_page
        foreach ($request->access_roles as $roleAccess) {
            \DB::table('tr_role_page')->insert([
                'role_id' => $roleAccess['role_id'],
                'page_id' => $page->id,
                'access' => json_encode(map_access_array($roleAccess['access'])), // ✅ perbaikan di sini
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        \DB::commit();
        return response()->json(['status' => true, 'data' => $page]);

    } catch (\Exception $e) {
        \DB::rollBack();
        return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
    }
}

}
