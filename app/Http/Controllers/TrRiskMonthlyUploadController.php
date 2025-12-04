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
<<<<<<< HEAD
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
=======
{
    $user = auth()->user();

    // Check authorization: roles 1,2,3,4,5 can delete
    $roleCheck = check_role($user, [1, 2, 3, 4, 5]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $data = TrRiskMonthlyUpload::find($id);

    if (!$data) {
        return json(404, false, 'Data Tidak Ditemukan', 'Data tidak ditemukan.', null);
    }

    // Role 2 dan 3 hanya bisa delete berdasarkan department_id mereka
    if (in_array($user->role_id, [2, 3])) {
        if ($data->department_id != $user->department_id) {
            return json(403, false, 'Akses Ditolak', 'Anda hanya dapat menghapus data dari department Anda sendiri.', null);
        }
    }

    $filePath = $data->filepath;

    /**
     * Jika file base64, filepath selalu diawali "data:"
     * BERARTI tidak disimpan di S3 → tidak perlu delete fisik file
     */
    if (!str_starts_with($filePath, 'data:')) {
        // file BUKAN base64 → berarti file fisik (URL atau path)
        try {
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                // ambil hanya path dari URL S3
                $parsedPath = parse_url($filePath, PHP_URL_PATH);
                $cleanPath = ltrim($parsedPath, '/');
            } else {
                $cleanPath = $filePath;
            }

            Storage::disk('s3')->delete($cleanPath);

        } catch (\Exception $e) {
            return json(500, false, 'Gagal Menghapus File', 'Gagal menghapus file: ' . $e->getMessage(), null);
        }
    }

    // Delete database record
    $data->delete();

    return json(200, true, 'Berhasil Dihapus', 'Data berhasil dihapus.', null);
}


    public function deleteTempFile(Request $request)
{
    $user = auth()->user();

    // Check authorization: roles 1,2,3,4,5 can delete temp files
    $roleCheck = check_role($user, [1, 2, 3, 4, 5]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $filename = $request->get('filename');

    if (!$filename) {
        return json(400, false, 'Nama file kosong', 'Nama file harus dikirim.', null);
    }

    \Log::info('deleteTempFile - Original: ' . $filename);

    /**
     * 1. CEK BASE64
     * Base64 selalu diawali "data:"
     */
    if (str_starts_with($filename, 'data:')) {
        \Log::info('deleteTempFile - Base64 detected, no physical delete needed.');

        return json(200, true, 'Berhasil', 'File base64 tidak perlu dihapus dari storage.', null);
    }

    /**
     * 2. FILE FISIK → PROSES DELETE S3
     */
    if (filter_var($filename, FILTER_VALIDATE_URL)) {
        $parsedPath = parse_url($filename, PHP_URL_PATH);
        $cleanPath = ltrim($parsedPath, '/');
    } else {
        $cleanPath = $filename;
    }

    \Log::info('deleteTempFile - Clean path: ' . $cleanPath);

    try {
        $disk = Storage::disk('s3');

        // cek apakah file ada
        if (!$disk->exists($cleanPath)) {
            \Log::warning('deleteTempFile - File does not exist: ' . $cleanPath);

            return json(404, false, 'Data Tidak Ditemukan', 'File tidak ditemukan.', null);
        }

        // hapus file dari S3
        $disk->delete($cleanPath);

        \Log::info('deleteTempFile - File deleted: ' . $cleanPath);

        return json(200, true, 'Berhasil', 'File berhasil dihapus.', null);

    } catch (\Exception $e) {
        \Log::error('deleteTempFile - Error: ' . $e->getMessage());
        return json(500, false, 'Gagal Menghapus File', 'Gagal menghapus file: ' . $e->getMessage(), null);
    }
}

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
}
