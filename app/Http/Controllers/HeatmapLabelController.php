<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstHeatmapDampak;
use App\Models\MstHeatmapKemungkinan;
use Illuminate\Support\Facades\Validator;

class HeatmapLabelController extends Controller
{
    public function index()
    {
        $dampak = MstHeatmapDampak::orderBy('dampak','asc')->get();
        $kemungkinan = MstHeatmapKemungkinan::orderBy('kemungkinan','asc')->get();

        return response()->json([
            'status' => true,
            'dampak' => $dampak,
            'kemungkinan' => $kemungkinan
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:dampak,kemungkinan',
            'label' => 'required|string',
            'skala' => 'required|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->type === 'dampak') {
            $data = MstHeatmapDampak::create([
                'dampak' => $request->skala,
                'label' => $request->label
            ]);
        } else {
            $data = MstHeatmapKemungkinan::create([
                'kemungkinan' => $request->skala,
                'label' => $request->label
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request, $type, $id)
    {
        $validator = Validator::make($request->all(), [
            'skala' => 'required|integer|min:1|max:5',
            'label' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($type === 'dampak') {
            $data = MstHeatmapDampak::find($id);
            if (!$data) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data dampak tidak ditemukan'
                ], 404);
            }

            $data->dampak = $request->skala;
            $data->label = $request->label;
            $data->save();

        } elseif ($type === 'kemungkinan') {
            $data = MstHeatmapKemungkinan::find($id);
            if (!$data) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data kemungkinan tidak ditemukan'
                ], 404);
            }

            $data->kemungkinan = $request->skala;
            $data->label = $request->label;
            $data->save();

        } else {
            return response()->json(['status' => false, 'message' => 'Tipe tidak valid'], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Label berhasil diperbarui',
            'data' => $data
        ]);
    }

    public function destroy($type, $id)
    {
        if ($type === 'dampak') {
            $data = MstHeatmapDampak::find($id);
            if (!$data) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data dampak tidak ditemukan'
                ], 404);
            }
        } elseif ($type === 'kemungkinan') {
            $data = MstHeatmapKemungkinan::find($id);
            if (!$data) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data kemungkinan tidak ditemukan'
                ], 404);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Tipe tidak valid'
            ], 400);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Label berhasil dihapus'
        ]);
    }
}
