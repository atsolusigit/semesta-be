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

        return json(200, true, 'Berhasil', 'Data label berhasil diambil', [
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
            return json(422, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
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

        return json(200, true, 'Berhasil', 'Label berhasil disimpan', $data);
    }

    public function update(Request $request, $type, $id)
    {
        $validator = Validator::make($request->all(), [
            'skala' => 'required|integer|min:1|max:5',
            'label' => 'required|string'
        ]);

        if ($validator->fails()) {
            return json(422, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        if ($type === 'dampak') {
            $data = MstHeatmapDampak::find($id);
            if (!$data) {
                return json(404, false, 'Tidak Ditemukan', 'Data dampak tidak ditemukan', null);
            }

            $data->dampak = $request->skala;
            $data->label = $request->label;
            $data->save();

        } elseif ($type === 'kemungkinan') {
            $data = MstHeatmapKemungkinan::find($id);
            if (!$data) {
                return json(404, false, 'Tidak Ditemukan', 'Data kemungkinan tidak ditemukan', null);
            }

            $data->kemungkinan = $request->skala;
            $data->label = $request->label;
            $data->save();

        } else {
            return json(400, false, 'Tipe Tidak Valid', 'Tipe hanya boleh dampak atau kemungkinan', null);
        }

        return json(200, true, 'Berhasil', 'Label berhasil diperbarui', $data);
    }

    public function destroy($type, $id)
    {
        if ($type === 'dampak') {
            $data = MstHeatmapDampak::find($id);
            if (!$data) {
                return json(404, false, 'Tidak Ditemukan', 'Data dampak tidak ditemukan', null);
            }
        } elseif ($type === 'kemungkinan') {
            $data = MstHeatmapKemungkinan::find($id);
            if (!$data) {
                return json(404, false, 'Tidak Ditemukan', 'Data kemungkinan tidak ditemukan', null);
            }
        } else {
            return json(400, false, 'Tipe Tidak Valid', 'Tipe hanya boleh dampak atau kemungkinan', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil', 'Label berhasil dihapus', null);
    }
}
