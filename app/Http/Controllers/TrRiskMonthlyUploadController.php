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

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function show($id)
    {
        $data = TrRiskMonthlyUpload::with(['header', 'riskMonthly'])->find($id);

        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['status' => true, 'data' => $data]);
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
        return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    }

    $data = TrRiskMonthlyUpload::create([
        'header_id' => $request->header_id,
        'risk_monthly_id' => $request->risk_monthly_id,
        'filepath' => $request->filepath,
        'domain' => $request->domain,
    ]);

    return response()->json(['status' => true, 'message' => 'Data berhasil disimpan.', 'data' => $data]);
}


  public function update(Request $request, $id)
{
    $data = TrRiskMonthlyUpload::find($id);

    if (!$data) {
        return response()->json([
            'status' => false,
            'message' => 'Data tidak ditemukan.'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'header_id' => 'required|exists:tr_risk_header,id',
        'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
        'filepath' => 'required|url',
        'domain' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $data->update([
        'header_id' => $request->header_id,
        'risk_monthly_id' => $request->risk_monthly_id,
        'filepath' => $request->filepath,
        'domain' => $request->domain,
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Data berhasil diperbarui.',
        'data' => $data
    ]);
}


  public function destroy($id)
{
    $data = TrRiskMonthlyUpload::find($id);

    if (!$data) {
        return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
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
        return response()->json([
            'status' => false,
            'message' => 'Gagal menghapus file : ' . $e->getMessage()
        ], 500);
    }

    // Hapus data dari DB
    $data->delete();

    return response()->json(['status' => true, 'message' => 'Data berhasil dihapus.']);
}

}
