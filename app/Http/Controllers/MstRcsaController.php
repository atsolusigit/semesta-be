<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstRcsa;
use Illuminate\Support\Facades\Validator;

class MstRcsaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        $query = MstRcsa::query()->orderBy('id', 'asc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                ->orWhere('kategori', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan kategori
        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Pagination
        $data = $query->paginate($perPage);

        $resData = $data->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'kategori' => $item->kategori,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
            ];
        });

        $responseData = [
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'data' => $resData,
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
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = MstRcsa::create($request->only('nama', 'kategori'));

        return json(200, true, 'Berhasil Disimpan', 'Data berhasil disimpan.', $data);
    }

    public function show($id)
    {
        $data = MstRcsa::find($id);
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

        $data = MstRcsa::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data->update($request->only('nama', 'kategori'));

        return json(200, true, 'Berhasil Diperbarui', 'Data berhasil diperbarui.', $data);
    }

    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $userRole = auth()->user()->role_id ?? null;
        if ($userRole !== 1) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menghapus data', null);
        }

        $data = MstRcsa::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
    }
}
