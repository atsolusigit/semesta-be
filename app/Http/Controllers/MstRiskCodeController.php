<?php

namespace App\Http\Controllers;

use App\Models\MstRiskCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstRiskCodeController extends Controller
{
    // Ambil semua data jenis risiko
    public function index()
    {
        $data = MstRiskCode::orderBy('id', 'asc')->get();
        return json(200, true, 'Data ditemukan', 'Data jenis risiko berhasil diambil.', $data);
    }

    // Tambah data jenis risiko
    public function store(Request $request)
    {
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

        return json(201, true, 'Berhasil Ditambahkan', 'Jenis risiko berhasil ditambahkan.', $data);
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
        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Jenis risiko berhasil dihapus.', null);
    }
}
