<?php

namespace App\Http\Controllers;

use App\Models\MstRiskCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstRiskCodeController extends Controller
{
    //  Ambil semua data jenis risiko
    public function index()
    {
        $data = MstRiskCode::orderBy('id', 'asc')->get();
        return response()->json([
            'status' => true,
            'message' => 'Data jenis risiko berhasil diambil.',
            'data' => $data
        ]);
    }

    //  Tambah data jenis risiko
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:mst_risk_code,code',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'data' => $validator->errors(),
            ], 400);
        }

        $data = MstRiskCode::create([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Jenis risiko berhasil ditambahkan.',
            'data' => $data
        ]);
    }

    //  Detail satu jenis risiko
    public function show($id)
    {
        $data = MstRiskCode::find($id);
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data jenis risiko tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Detail jenis risiko berhasil diambil.',
            'data' => $data
        ]);
    }

    //  Update jenis risiko
    public function update(Request $request, $id)
    {
        $data = MstRiskCode::find($id);
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:mst_risk_code,code,' . $id,
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'data' => $validator->errors(),
            ], 400);
        }

        $data->update([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Jenis risiko berhasil diperbarui.',
            'data' => $data
        ]);
    }

    //  Hapus jenis risiko
    public function destroy($id)
    {
        $data = MstRiskCode::find($id);
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Jenis risiko berhasil dihapus.'
        ]);
    }
}
