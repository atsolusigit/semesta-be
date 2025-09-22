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

        return json(200, true, 'Berhasil', 'Data mitigation monthly berhasil diambil.', $data);
    }

    public function store(Request $request)
    {
        // Check authorization: only role 1 and 2 can store
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menambah data', null);
        }

        $validator = Validator::make($request->all(), [
            'header_id' => 'required|exists:tr_risk_header,id',
            'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
            'detail_id' => 'nullable|integer',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = TrMitigationMonthly::create([
            'header_id' => $request->header_id,
            'detail_id' => $request->detail_id,
            'notes' => $request->notes,
            'risk_monthly_id' => $request->risk_monthly_id,
            'timestamp' => now()
        ]);

        return json(200, true, 'Berhasil', 'Data mitigation monthly berhasil disimpan.', $data);
    }

    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $userRole = auth()->user()->role_id ?? null;
        if (!in_array($userRole, [1, 2])) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk mengubah data', null);
        }

        $data = TrMitigationMonthly::find($id);

        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'header_id' => 'required|exists:tr_risk_header,id',
            'risk_monthly_id' => 'required|exists:tr_risk_monthly,id',
            'detail_id' => 'nullable|integer',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data->update([
            'header_id' => $request->header_id,
            'detail_id' => $request->detail_id,
            'notes' => $request->notes,
            'risk_monthly_id' => $request->risk_monthly_id,
            'timestamp' => now()
        ]);

        return json(200, true, 'Berhasil', 'Data mitigation monthly berhasil diperbarui.', $data);
    }

    public function show($id)
    {
        $data = TrMitigationMonthly::with(['riskHeader', 'riskMonthly'])->find($id);

        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Berhasil', 'Detail mitigation monthly berhasil diambil.', $data);
    }

    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $userRole = auth()->user()->role_id ?? null;
        if ($userRole !== 1) {
            return json(403, false, 'Tidak Diizinkan', 'Anda tidak memiliki akses untuk menghapus data', null);
        }

        $data = TrMitigationMonthly::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();
        return json(200, true, 'Berhasil', 'Data mitigation monthly berhasil dihapus.', null);
    }
}
