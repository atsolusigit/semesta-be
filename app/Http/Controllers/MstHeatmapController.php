<?php

namespace App\Http\Controllers;

use App\Models\MstHeatmap;
use App\Models\MstHeatmapDampak;
use App\Models\MstHeatmapKemungkinan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MstHeatmapRiskRange;

class MstHeatmapController extends Controller
{
   public function index()
{
    $data = MstHeatmap::orderBy('dampak', 'asc')
        ->orderBy('kemungkinan', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'dampak' => $item->dampak,
                'kemungkinan' => $item->kemungkinan,
                'result' => $item->result,
                'name' => $item->risk_range?->name ?? null,
                'color' => $item->risk_range?->color ?? null,
            ];
        });

    return response()->json([
        'code' => 200,
        'status' => true,
        'message' => 'List data heatmap',
        'data' => $data,
    ]);
}

    public function show($id)
{
    $item = MstHeatmap::with('riskRange')->find($id);

    if (!$item) {
        return response()->json([
            'code' => 404,
            'status' => false,
            'message' => 'Data heatmap tidak ditemukan',
        ]);
    }

    $data = [
        'dampak' => $item->dampak,
        'kemungkinan' => $item->kemungkinan,
        'result' => $item->result,
        'name' => $item->riskRange->name ?? null,
        'color' => $item->riskRange->color ?? null,
    ];

    return response()->json([
        'code' => 200,
        'status' => true,
        'message' => 'Detail data heatmap',
        'data' => $data,
    ]);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dampak' => 'required|exists:mst_heatmap_dampak,id',
            'kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'result' => 'required|numeric|min:1',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ]);
        }

        $exist = MstHeatmap::where('dampak', $request->dampak)
            ->where('kemungkinan', $request->kemungkinan)
            ->first();

        if ($exist) {
            return response()->json([
                'code' => 409,
                'status' => false,
                'message' => 'Data sudah ada dengan kombinasi dampak & kemungkinan ini',
            ]);
        }

        $data = MstHeatmap::create($request->only(['dampak', 'kemungkinan', 'result', 'name']));

        return response()->json([
            'code' => 201,
            'status' => true,
            'message' => 'Data heatmap berhasil ditambahkan',
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        $heatmap = MstHeatmap::find($id);
        if (!$heatmap) {
            return response()->json([
                'code' => 404,
                'status' => false,
                'message' => 'Data heatmap tidak ditemukan',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'dampak' => 'required|exists:mst_heatmap_dampak,id',
            'kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'result' => 'required|numeric|min:1',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 400,
                'status' => false,
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ]);
        }

        $heatmap->update($request->only(['dampak', 'kemungkinan', 'result', 'name']));

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Data heatmap berhasil diperbarui',
            'data' => $heatmap,
        ]);
    }

    public function destroy($id)
{
    $data = MstHeatmap::find($id);

    if (!$data) {
        return response()->json([
            'status' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    $data->delete();

    return response()->json([
        'status' => true,
        'message' => 'Data heatmap berhasil dihapus'
    ]);
}

}
