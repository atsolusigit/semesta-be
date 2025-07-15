<?php

namespace App\Http\Controllers;

use App\Models\TrRiskMonthlyUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TrRiskMonthlyUploadController extends Controller
{
    public function index()
    {
        $data = TrRiskMonthlyUpload::with(['header', 'riskMonthly'])
            ->orderBy('id', 'asc')
            ->get();

        return json(200, true, 'Data Ditemukan', 'Berhasil ambil data.', $data);
    }

    public function show($id)
    {
        $data = TrRiskMonthlyUpload::with(['header', 'riskMonthly'])->find($id);

        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail data berhasil diambil.', $data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'header_id' => 'required|exists:tr_risk_header,id',
            'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
            'filepath' => 'required|url', // URL dari UploadController
            'domain' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return json(422, false, 'Validasi Gagal', 'Validasi input gagal.', $validator->errors());
        }

        $data = TrRiskMonthlyUpload::create([
            'header_id' => $request->header_id,
            'risk_monthly_id' => $request->risk_monthly_id,
            'filepath' => $request->filepath,
            'domain' => $request->domain,
        ]);

        return json(201, true, 'Berhasil Disimpan', 'Data berhasil disimpan.', $data);
    }

    public function update(Request $request, $id)
    {
        $data = TrRiskMonthlyUpload::find($id);

        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'header_id' => 'required|exists:tr_risk_header,id',
            'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
            'filepath' => 'required|url',
            'domain' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return json(422, false, 'Validasi Gagal', 'Validasi input gagal.', $validator->errors());
        }

        $data->update([
            'header_id' => $request->header_id,
            'risk_monthly_id' => $request->risk_monthly_id,
            'filepath' => $request->filepath,
            'domain' => $request->domain,
        ]);

        return json(200, true, 'Berhasil Diperbarui', 'Data berhasil diperbarui.', $data);
    }

    public function destroy($id)
    {
        $data = TrRiskMonthlyUpload::find($id);

        if (!$data) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $filePath = $data->filepath;

        if (filter_var($filePath, FILTER_VALIDATE_URL)) {
            $parsedPath = parse_url($filePath, PHP_URL_PATH);
            $cleanPath = ltrim($parsedPath, '/');
        } else {
            $cleanPath = $filePath;
        }

        // Hapus dari S3
        try {
            Storage::disk('s3')->delete($cleanPath);
        } catch (\Exception $e) {
            return json(500, false, 'Gagal Menghapus File', 'Gagal menghapus file: ' . $e->getMessage(), null);
        }

        // Hapus data dari DB
        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
    }
}
