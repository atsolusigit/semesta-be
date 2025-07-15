<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TrMitigationMonthly;
use Illuminate\Support\Facades\Validator;

class TrMitigationMonthlyController extends Controller
{
   public function index()
{
    $data = TrMitigationMonthly::with(['riskHeader', 'riskMonthly'])
        ->orderBy('id', 'asc')
        ->get();

    return response()->json(['status' => true, 'data' => $data]);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'header_id' => 'required|exists:tr_risk_header,id',
            'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
            'detail_id' => 'nullable|integer',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validasi gagal.', 'data' => $validator->errors()]);
        }

        $data = TrMitigationMonthly::create([
            'header_id' => $request->header_id,
            'detail_id' => $request->detail_id,
            'notes' => $request->notes,
            'risk_monthly_id' => $request->risk_monthly_id,
            'timestamp' => now()
        ]);

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function update(Request $request, $id)
{
    $data = TrMitigationMonthly::find($id);

    if (!$data) {
        return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
    }

    $validator = Validator::make($request->all(), [
        'header_id' => 'required|exists:tr_risk_header,id',
        'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
        'detail_id' => 'nullable|integer',
        'notes' => 'nullable|string'
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'message' => 'Validasi gagal.', 'data' => $validator->errors()]);
    }

    $data->update([
        'header_id' => $request->header_id,
        'detail_id' => $request->detail_id,
        'notes' => $request->notes,
        'risk_monthly_id' => $request->risk_monthly_id,
        'timestamp' => now()
    ]);

    return response()->json(['status' => true, 'message' => 'Data berhasil diperbarui.', 'data' => $data]);
}

    public function show($id)
{
    $data = TrMitigationMonthly::with(['riskHeader', 'riskMonthly'])->find($id);

    if (!$data) {
        return response()->json([
            'status' => false,
            'message' => 'Data tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}

    public function destroy($id)
    {
        $data = TrMitigationMonthly::find($id);
        if (!$data) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $data->delete();
        return response()->json(['status' => true, 'message' => 'Data berhasil dihapus.']);
    }
}
