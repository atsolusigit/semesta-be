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
        // Check authorization: only role 1 and 2 can store
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

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

        return json(200, true, 'Berhasil Disimpan', 'Data berhasil disimpan.', $data);
    }

    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

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
        // Check authorization: only role 1 can delete
        $user = auth()->user();
        $roleCheck = check_role($user, 1);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

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

    public function deleteTempFile(Request $request)
    {
        // Check authorization: only role 1 and 2 can delete temp files
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        // dd(Storage::disk('s3')->files('semesta'));

        $filename = $request->get('filename');

        if (!$filename) {
            return json(400, false, 'Nama file kosong', 'Nama file harus dikirim.', null);
        }

        // GUNAKAN PARSING YANG SAMA PERSIS SEPERTI destroy()
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            $parsedPath = parse_url($filename, PHP_URL_PATH);
            $cleanPath = ltrim($parsedPath, '/');
        } else {
            $cleanPath = $filename;
        }

        \Log::info('deleteTempFile - Original: ' . $filename);
        \Log::info('deleteTempFile - Clean path: ' . $cleanPath);

        // CEK APAKAH FILE ADA SEBELUM DIHAPUS
        try {
            $disk = Storage::disk('s3');

            if (!$disk->exists($cleanPath)) {
                \Log::warning('File not exists: ' . $cleanPath);

                // Debug: Cari file di directory yang sama
                $directory = dirname($cleanPath);
                if ($directory === '.') $directory = '';

                $filesInDir = $disk->files($directory);
                \Log::info('Files in directory: ' . json_encode(array_slice($filesInDir, 0, 5)));

                return json(404, false, 'Data Tidak Ditemukan',
                           'File tidak ditemukan: ' . $cleanPath,
                           ['files_in_directory' => array_slice($filesInDir, 0, 10)]);
            }

            // HAPUS FILE (SAMA SEPERTI destroy())
            $disk->delete($cleanPath);
            \Log::info('File deleted successfully: ' . $cleanPath);

            return json(200, true, 'Berhasil', 'File berhasil dihapus.', null);

        } catch (\Exception $e) {
            \Log::error('Error: ' . $e->getMessage());
            return json(500, false, 'Gagal Menghapus File', 'Gagal menghapus file: ' . $e->getMessage(), null);
        }
    }
}
