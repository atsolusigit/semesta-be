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
    // Check authorization: only role 1 and 2 can store
    $result = check_role(auth()->user(), [1, 2, 4, 5]);
    if ($result !== true) {
        return $result;
    }

    $validator = Validator::make($request->all(), [
        'type' => 'required|in:dampak,kemungkinan',
        'label' => 'required|string',
        'skala' => 'required|integer|min:1|max:5'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input skala hanya 1 - 5', $validator->errors());
    }

    if ($request->type === 'dampak') {
        // Cek apakah skala sudah ada
        $exists = MstHeatmapDampak::where('dampak', $request->skala)->exists();
        if ($exists) {
            return json(400, false, 'Validasi Gagal', 'Skala dengan type dampak ' . $request->skala . ' sudah ada. Gunakan skala yang berbeda.', null);
        }

        $data = MstHeatmapDampak::create([
            'dampak' => $request->skala,
            'label' => $request->label
        ]);
    } else {
        // Cek apakah skala sudah ada
        $exists = MstHeatmapKemungkinan::where('kemungkinan', $request->skala)->exists();
        if ($exists) {
            return json(400, false, 'Validasi Gagal', 'Skala dengan type kemungkinan ' . $request->skala . ' sudah ada. Gunakan skala yang berbeda.', null);
        }

        $data = MstHeatmapKemungkinan::create([
            'kemungkinan' => $request->skala,
            'label' => $request->label
        ]);
    }

    return json(200, true, 'Berhasil', 'Label berhasil disimpan', $data);
}

public function update(Request $request, $type, $id)
{
    // Check authorization: only role 1 and 2 can update
    $result = check_role(auth()->user(), [1, 2, 4, 5]);
    if ($result !== true) {
        return $result;
    }

    $validator = Validator::make($request->all(), [
        'skala' => 'required|integer|min:1|max:5',
        'label' => 'required|string'
    ]);

    if ($validator->fails()) {
        return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input skala hanya 1 - 5', $validator->errors());
    }

    if ($type === 'dampak') {
        $data = MstHeatmapDampak::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data dampak tidak ditemukan', null);
        }

        // Cek apakah skala sudah digunakan oleh data lain
        $exists = MstHeatmapDampak::where('dampak', $request->skala)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return json(400, false, 'Validasi Gagal', 'Skala dampak ' . $request->skala . ' sudah digunakan oleh data lain.', null);
        }

        $data->dampak = $request->skala;
        $data->label = $request->label;
        $data->save();

    } elseif ($type === 'kemungkinan') {
        $data = MstHeatmapKemungkinan::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data kemungkinan tidak ditemukan', null);
        }

        // Cek apakah skala sudah digunakan oleh data lain
        $exists = MstHeatmapKemungkinan::where('kemungkinan', $request->skala)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return json(400, false, 'Validasi Gagal', 'Skala kemungkinan ' . $request->skala . ' sudah digunakan oleh data lain.', null);
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
        // Check authorization: only role 1 can delete
       $result = check_role(auth()->user(), 1);
    if ($result !== true) {
        return $result; // otomatis balikin JSON 403 kalau bukan role 1
    }
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
