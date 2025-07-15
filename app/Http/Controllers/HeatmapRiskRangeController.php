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
            return json(200, true, 'Berhasil', 'Data risk range berhasil diambil', $data);
        } catch (Exception $e) {
            return json(500, false, 'Gagal', 'Gagal mengambil data risk range', null);
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
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        if ($request->end < $request->start) {
            return json(400, false, 'Validasi Gagal', 'End harus lebih besar atau sama dengan Start', [
                'end' => ['End harus lebih besar atau sama dengan Start.']
            ]);
        }

        try {
            $range = new MstHeatmapRiskRange();
            $range->name = $request->name;
            $range->start = $request->start;
            $range->end = $request->end;
            $range->color = $request->color;
            $range->save();

            return json(200, true, 'Berhasil', 'Risk range berhasil dibuat', $range);
        } catch (Exception $e) {
            return json(500, false, 'Gagal', 'Gagal menyimpan risk range', null);
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
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        if ($request->end < $request->start) {
            return json(400, false, 'Validasi Gagal', 'End harus lebih besar atau sama dengan Start', [
                'end' => ['End harus lebih besar atau sama dengan Start.']
            ]);
        }

        try {
            $range = MstHeatmapRiskRange::findOrFail($id);
            $range->name = $request->name;
            $range->start = $request->start;
            $range->end = $request->end;
            $range->color = $request->color;
            $range->save();

            return json(200, true, 'Berhasil', 'Risk range berhasil diperbarui', $range);
        } catch (Exception $e) {
            return json(500, false, 'Gagal', 'Gagal memperbarui risk range', null);
        }
    }

    public function destroy($id)
    {
        try {
            $range = MstHeatmapRiskRange::findOrFail($id);
            $range->delete();

            return json(200, true, 'Berhasil', 'Risk range berhasil dihapus', null);
        } catch (Exception $e) {
            return json(500, false, 'Gagal', 'Gagal menghapus, data tidak tersedia', null);
        }
    }
}
