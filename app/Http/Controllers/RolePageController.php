<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MstPage;
use App\Models\MstRole;


class RolePageController extends Controller
{
    // Tampilkan semua kombinasi halaman + role + akses
    public function index()
    {
        $data = DB::table('tr_role_page')
            ->join('mst_page', 'mst_page.id', '=', 'tr_role_page.page_id')
            ->join('mst_role', 'mst_role.id', '=', 'tr_role_page.role_id')
            ->select(
                'tr_role_page.id',
                'mst_page.name as page_name',
                'mst_role.name as role_name',
                'tr_role_page.access'
            )
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'page' => $item->page_name,
                    'role' => $item->role_name,
                    'access' => json_decode($item->access),
                ];
            });

        return response()->json($data);
    }

    // Tambah/ubah akses halaman oleh role
    public function storeAccess(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:mst_role,id',
            'page_id' => 'required|exists:mst_page,id',
            'access' => 'required|array|min:1',
            'access.*' => 'in:viewer,admin,superadmin',
        ]);

        DB::table('tr_role_page')->updateOrInsert(
            [
                'role_id' => $request->role_id,
                'page_id' => $request->page_id,
            ],
            [
                'access' => json_encode($request->access),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['status' => true, 'message' => 'Akses disimpan']);
    }

    // Tampilkan detail akses untuk satu data role-page
public function show($id)
{
    $item = DB::table('tr_role_page')
        ->join('mst_page', 'mst_page.id', '=', 'tr_role_page.page_id')
        ->join('mst_role', 'mst_role.id', '=', 'tr_role_page.role_id')
        ->select(
            'tr_role_page.id',
            'mst_page.name as page_name',
            'mst_role.name as role_name',
            'tr_role_page.access'
        )
        ->where('tr_role_page.id', $id)
        ->first();

    if (!$item) {
        return response()->json(['message' => 'Data Tidak Ditemukan'], 404);
    }

    return response()->json([
        'id' => $item->id,
        'page' => $item->page_name,
        'role' => $item->role_name,
        'access' => json_decode($item->access),
    ]);
}

// Update data akses berdasarkan ID
public function update(Request $request, $id)
{
    $request->validate([
        'access' => 'required|array|min:1',
        'access.*' => 'in:viewer,admin,superadmin',
    ]);

    $row = DB::table('tr_role_page')->where('id', $id)->first();
    if (!$row) {
        return response()->json(['message' => 'Data Tidak Ditemukan'], 404);
    }

    DB::table('tr_role_page')->where('id', $id)->update([
        'access' => json_encode($request->access),
        'updated_at' => now(),
    ]);

    return response()->json(['status' => true, 'message' => 'Access updated']);
}

    // Hapus akses
    public function destroy($id)
    {
        DB::table('tr_role_page')->where('id', $id)->delete();
        return response()->json(['status' => true, 'message' => 'Data dihapus']);
    }
}
