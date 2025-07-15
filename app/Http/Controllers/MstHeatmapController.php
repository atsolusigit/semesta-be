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

        return json(200, true, 'Berhasil', 'List data heatmap', $data);
    }

    public function show($id)
    {
        $item = MstHeatmap::with('riskRange')->find($id);

        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Data heatmap tidak ditemukan');
        }

        $data = [
            'dampak' => $item->dampak,
            'kemungkinan' => $item->kemungkinan,
            'result' => $item->result,
            'name' => $item->riskRange->name ?? null,
            'color' => $item->riskRange->color ?? null,
        ];

        return json(200, true, 'Berhasil', 'Detail data heatmap', $data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dampak' => 'required|exists:mst_heatmap_dampak,id',
            'kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'result' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        $exist = MstHeatmap::where('dampak', $request->dampak)
            ->where('kemungkinan', $request->kemungkinan)
            ->first();

        if ($exist) {
            return json(409, false, 'Gagal', 'Data sudah ada dengan kombinasi dampak & kemungkinan ini');
        }

        $data = MstHeatmap::create($request->only(['dampak', 'kemungkinan', 'result', 'name']));

        return json(200, true, 'Berhasil', 'Data heatmap berhasil ditambahkan', $data);
    }

    public function update(Request $request, $id)
    {
        $heatmap = MstHeatmap::find($id);
        if (!$heatmap) {
            return json(404, false, 'Tidak Ditemukan', 'Data heatmap tidak ditemukan');
        }

        $validator = Validator::make($request->all(), [
            'dampak' => 'required|exists:mst_heatmap_dampak,id',
            'kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'result' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        $heatmap->update($request->only(['dampak', 'kemungkinan', 'result', 'name']));

        return json(200, true, 'Berhasil', 'Data heatmap berhasil diperbarui', $heatmap);
    }

    public function destroy($id)
    {
        $data = MstHeatmap::find($id);

        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data heatmap tidak ditemukan');
        }

        $data->delete();

        return json(200, true, 'Berhasil', 'Data heatmap berhasil dihapus');
    }
}
