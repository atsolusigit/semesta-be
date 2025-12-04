<?php

namespace App\Http\Controllers;

use App\Models\MstJenisRisiko;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstJenisRisikoController extends Controller
{
 public function index(Request $request)
{
    $perPage = $request->input('per_page');

    $query = MstJenisRisiko::with(['createdBy:id,username'])
        ->orderBy('id', 'asc');

    // Search filter
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where('nama_jenis_risiko', 'like', "%{$search}%");
    }

    // Jika per_page kosong atau = "all", ambil semua data tanpa pagination
    if (empty($perPage) || $perPage === 'all') {
        $data = $query->get();

        $mappedData = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'nama_jenis_risiko' => $item->nama_jenis_risiko,
                'created_by' => $item->created_by,
                'created_by_username' => get_decrypted_username($item->createdBy),
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
            ];
        });

        return json(200, true, 'Data Ditemukan', 'Data berhasil diambil.', [
            'total' => $mappedData->count(),
            'data' => $mappedData,
        ]);
    }

    // Kalau per_page dikirim, gunakan pagination Laravel
    $data = $query->paginate($perPage);

    $mappedData = $data->getCollection()->map(function ($item) {
        return [
            'id' => $item->id,
            'nama_jenis_risiko' => $item->nama_jenis_risiko,
            'created_by' => $item->created_by,
            'created_by_username' => get_decrypted_username($item->createdBy),
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
            'nama_jenis_risiko' => 'required|string|max:855',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = MstJenisRisiko::create([
            'nama_jenis_risiko' => $request->nama_jenis_risiko,
            'created_by' => auth()->id(),
        ]);

        return json(200, true, 'Berhasil Disimpan', 'Data berhasil disimpan.', $data);
    }

    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk mengubah data', null);
        }

        $data = MstJenisRisiko::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'nama_jenis_risiko' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data->update([
            'nama_jenis_risiko' => $request->nama_jenis_risiko,
        ]);

        return json(200, true, 'Berhasil Diperbarui', 'Data berhasil diperbarui.', $data);
    }

    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $userRole = auth()->user()->role_id ?? null;
        if ($userRole !== 1) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menghapus data', null);
        }

        $data = MstJenisRisiko::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
    }
}
