<?php

namespace App\Http\Controllers;

use App\Models\MstRiskCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstRiskCodeController extends Controller
{
    // Ambil semua data jenis risiko
   public function index(Request $request)
{
    $query = MstRiskCode::query()->orderBy('id', 'asc');

    // Search filter (code dan name)
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });
    }

    $data = $query->get();

    return json(200, true, 'Data ditemukan', 'Data jenis risiko berhasil diambil.', $data);
}

    // Tambah data jenis risiko
    public function store(Request $request)
    {
        // Check authorization: only role 1 and 2 can store
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menambah data', null);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:mst_risk_code,code',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = MstRiskCode::create([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return json(200, true, 'Berhasil Ditambahkan', 'Jenis risiko berhasil ditambahkan.', $data);
    }

    // Detail satu jenis risiko
    public function show($id)
    {
        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data jenis risiko tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail jenis risiko berhasil diambil.', $data);
    }

    // Update jenis risiko
    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk mengubah data', null);
        }

        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:mst_risk_code,code,' . $id,
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data->update([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return json(200, true, 'Berhasil Diperbarui', 'Jenis risiko berhasil diperbarui.', $data);
    }

    // Hapus jenis risiko
    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $userRole = auth()->user()->role_id ?? null;
        if ($userRole !== 1) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menghapus data', null);
        }

        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Jenis risiko berhasil dihapus.', null);
    }
}
