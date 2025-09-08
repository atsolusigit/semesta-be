<?php

namespace App\Http\Controllers;

use App\Models\MstOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstOptionController extends Controller
{
    public function index(Request $request)
    {
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

        $data = $query->get();

        return json(200, true, 'Data Ditemukan', 'Data berhasil diambil.', $data);
    }

    public function store(Request $request)
    {
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
        $data = MstOption::find($id);
        if (!$data) {
            return json(404, true, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
    }
}
