<?php

namespace App\Http\Controllers;

use App\Models\MstRiskCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MstRiskCodeController extends Controller
{
   public function index(Request $request)
{
    $perPage = $request->input('per_page');

    $query = MstRiskCode::query()->orderBy('id', 'asc');

    // Search filter (code dan name)
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });
    }

    // Jika per_page kosong atau 'all', ambil semua data tanpa pagination
    if (empty($perPage) || $perPage === 'all') {
        $data = $query->get();

        $mappedData = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
            ];
        });

        return json(200, true, 'Data ditemukan', 'Data jenis risiko berhasil diambil.', [
            'total' => $mappedData->count(),
            'data' => $mappedData,
        ]);
    }

    // Pagination
    $data = $query->paginate((int) $perPage);

    // Mapping data
    $mappedData = $data->getCollection()->map(function ($item) {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
            'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
        ];
    });

    // Prepare response dengan pagination
    $responseData = [
        'current_page' => $data->currentPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
        'last_page' => $data->lastPage(),
        'from' => $data->firstItem(),
        'to' => $data->lastItem(),
        'data' => $mappedData,
    ];

    return json(200, true, 'Data ditemukan', 'Data jenis risiko berhasil diambil.', $responseData);
}

    // Tambah data jenis risiko
    public function store(Request $request)
    {
        // Check authorization: only role 1 and 2 can store
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:mst_risk_code,code',
            'name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data = MstRiskCode::create([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return json(200, true, 'Berhasil Ditambahkan', 'Jenis risiko berhasil ditambahkan.', $data);
    }

    // Detail satu jenis risiko
    public function show($id)
    {
        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data jenis risiko tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail jenis risiko berhasil diambil.', $data);
    }

    // Update jenis risiko
    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:mst_risk_code,code,' . $id,
            'name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $data->update([
            'code' => $request->code,
            'name' => $request->name,
        ]);

        return json(200, true, 'Berhasil Diperbarui', 'Jenis risiko berhasil diperbarui.', $data);
    }

    // Hapus jenis risiko
    public function destroy($id)
    {
        // Check authorization: only role 1 can delete
        $user = auth()->user();
        $roleCheck = check_role($user, 1);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $data = MstRiskCode::find($id);
        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        $data->delete();

        return json(200, true, 'Berhasil Dihapus', 'Jenis risiko berhasil dihapus.', null);
    }
}
