<?php

namespace App\Http\Controllers;

use App\Models\MstOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstOptionController extends Controller
{
   public function index(Request $request)
{
    $perPage = $request->input('per_page');

    $query = MstOption::query()->orderBy('id', 'asc');

    // Search filter
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('position', 'like', "%{$search}%")
              ->orWhere('type', 'like', "%{$search}%");
        });
    }

    // Filter berdasarkan type
    if ($request->filled('type')) {
        $query->where('type', $request->type);
    }

    // Jika per_page kosong atau 'all', ambil semua data tanpa pagination
    if (empty($perPage) || $perPage === 'all') {
        $data = $query->get();

        $mappedData = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'position' => $item->position,
                'type' => $item->type,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
            ];
        });

        return json(200, true, 'Data Ditemukan', 'Data berhasil diambil.', [
            'total' => $mappedData->count(),
            'data' => $mappedData,
        ]);
    }

    // Pagination
    $data = $query->paginate((int) $perPage);

    // Mapping data
    $mappedData = $data->getCollection()->map(function ($item) {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'position' => $item->position,
            'type' => $item->type,
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

    return json(200, true, 'Data Ditemukan', 'Data berhasil diambil.', $responseData);
}

    public function store(Request $request)
    {
        // Check authorization: only role 1 and 2 can store
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menambah data', null);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'required|in:Depan,Belakang',
            'type' => 'required|in:kuantitatif,kualitatif',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = MstOption::create($request->only('name', 'position', 'type'));

        return json(200, true, 'Berhasil Disimpan', 'Data berhasil disimpan.', $data);
    }

    public function show($id)
    {
        $data = MstOption::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail data berhasil diambil.', $data);
    }

    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk mengubah data', null);
        }

        $data = MstOption::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'required|in:Depan,Belakang',
            'type' => 'required|in:kuantitatif,kualitatif',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data->update($request->only('name', 'position', 'type'));

        return json(200, true, 'Berhasil Diperbarui', 'Data berhasil diperbarui.', $data);
    }

    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $userRole = auth()->user()->role_id ?? null;
        if ($userRole !== 1) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menghapus data', null);
        }

        $data = MstOption::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
    }
}
