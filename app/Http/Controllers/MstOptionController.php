<?php

namespace App\Http\Controllers;

use App\Models\MstOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstOptionController extends Controller
{
    public function index()
{
    $data = MstOption::orderBy('id', 'asc')->get();

    return response()->json([
        'status' => true,
        'message' => 'Data berhasil diambil.',
        'data' => $data
    ]);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'required|in:Depan,Belakang',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'data' => $validator->errors()
            ], 400);
        }

        $data = MstOption::create($request->only('name', 'position'));

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil disimpan.',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $data = MstOption::find($id);
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Detail data berhasil diambil.',
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = MstOption::find($id);
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'required|in:Depan,Belakang',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal.',
                'data' => $validator->errors()
            ], 400);
        }

        $data->update($request->only('name', 'position'));

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil diperbarui.',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        $data = MstOption::find($id);
        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.'
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil dihapus.'
        ]);
    }
}
