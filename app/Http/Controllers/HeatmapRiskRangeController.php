<?php

namespace App\Http\Controllers;

use App\Models\MstHeatmapRiskRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class HeatmapRiskRangeController extends Controller
{
    public function index()
{
    try {
        $data = MstHeatmapRiskRange::orderBy('start', 'asc')->get();
        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Gagal mengambil data.',
        ], 500);
    }
}


    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:100',
        'start' => 'required|integer',
        'end' => 'required|integer',
        'color' => 'required|string|max:7',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal.',
            'errors' => $validator->errors()
        ], 400);
    }

    // Validasi: end tidak boleh lebih kecil dari start
    if ($request->end < $request->start) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal.',
            'errors' => [
                'end' => ['End harus lebih besar atau sama dengan Start.']
            ]
        ], 400);
    }

    try {
        $range = new MstHeatmapRiskRange();
        $range->name = $request->name;
        $range->start = $request->start;
        $range->end = $request->end;
        $range->color = $request->color;
        $range->save();

        return response()->json([
            'status' => true,
            'message' => 'Risk range berhasil dibuat.'
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Gagal menyimpan risk range.',
        ], 500);
    }
}


   public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:100',
        'start' => 'required|integer',
        'end' => 'required|integer',
        'color' => 'required|string|max:7',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal.',
            'errors' => $validator->errors()
        ], 400);
    }

    if ($request->end < $request->start) {
        return response()->json([
            'status' => false,
            'message' => 'Validasi gagal.',
            'errors' => [
                'end' => ['End harus lebih besar atau sama dengan Start.']
            ]
        ], 400);
    }

    try {
        $range = MstHeatmapRiskRange::findOrFail($id);
        $range->name = $request->name;
        $range->start = $request->start;
        $range->end = $request->end;
        $range->color = $request->color;
        $range->save();

        return response()->json([
            'status' => true,
            'message' => 'Risk range berhasil diperbarui.'
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Gagal memperbarui risk range.',
        ], 500);
    }
}


    public function destroy($id)
    {
        try {
            $range = MstHeatmapRiskRange::findOrFail($id);
            $range->delete();

            return response()->json([
                'status' => true,
                'message' => 'Risk range berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal menghapus, data tidak tersedia.',
            ], 500);
        }
    }
}
