<?php

namespace App\Http\Controllers;

use App\Models\MstApproval;
use App\Models\MstJabatan;
use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MstApprovalController extends Controller
{
    /**
     * Display a listing of approval
     */
    public function index(Request $request)
    {
        $request->validate([
            'document_id' => 'nullable|integer',
            'tahun' => 'nullable|integer',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'jabatan_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $query = MstApproval::with([
            'jabatan:id,name,nipp,department_id',
            'jabatan.department:id,name',
            'document:id,process_code,jenis_risiko,department_id,year'
        ]);

        // Role-based access control
        $user = auth()->user();
        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        if (in_array($userRole, [2, 3]) && $userDepartmentId) {
            $query->whereHas('document', function($q) use ($userDepartmentId) {
                $q->where('department_id', $userDepartmentId);
            });
        }

        // Apply filters
        if ($request->document_id) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->tahun) {
            $query->where('tahun', $request->tahun);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->jabatan_id) {
            $query->where('jabatan_id', $request->jabatan_id);
        }

        $perPage = $request->input('per_page', 15);
        $data = $query->orderBy('posisi', 'asc')->orderBy('created_at', 'desc')->paginate($perPage);

        return json(200, true, 'Berhasil', 'List data approval', $data);
    }

    /**
     * Store a new approval
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|integer|exists:tr_risk_header,id',
            'tahun' => 'required|integer|min:2020|max:2030',
            'posisi' => 'required|integer|min:1',
            'jabatan_id' => 'required|integer|exists:mst_jabatan,id',
            'status' => 'required|string|in:pending,approved,rejected',
            'tanggal' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        // Check duplicate approval
        $exist = MstApproval::where('document_id', $request->document_id)
            ->where('tahun', $request->tahun)
            ->where('posisi', $request->posisi)
            ->first();

        if ($exist) {
            return json(409, false, 'Gagal', 'Sudah ada approval untuk dokumen, tahun, dan posisi yang sama', null);
        }

        // Role-based access control
        $user = auth()->user();
        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        if (in_array($userRole, [2, 3]) && $userDepartmentId) {
            $document = TrRiskHeader::find($request->document_id);
            if (!$document || $document->department_id !== $userDepartmentId) {
                return json(404, false, 'Akses Ditolak', 'Anda tidak memiliki akses untuk membuat approval untuk dokumen ini', null);
            }
        }

        try {
            DB::beginTransaction();

            $data = MstApproval::create($request->only([
                'document_id',
                'tahun',
                'posisi',
                'jabatan_id',
                'status',
                'tanggal',
                'note'
            ]));

            $data->load(['jabatan:id,name,nipp', 'document:id,process_code,jenis_risiko']);

            DB::commit();

            return json(201, true, 'Berhasil', 'Data approval berhasil ditambahkan', $data);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat menyimpan data', null);
        }
    }

    /**
     * Display specific approval
     */
    public function show($id)
    {
        $item = MstApproval::with([
            'jabatan:id,name,nipp,department_id',
            'jabatan.department:id,name',
            'document:id,process_code,jenis_risiko,department_id,year'
        ])->find($id);

        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Data approval tidak ditemukan', null);
        }

        // Role-based access control
        $user = auth()->user();
        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        if (in_array($userRole, [2, 3]) && $userDepartmentId) {
            if (!$item->document || $item->document->department_id !== $userDepartmentId) {
                return json(404, false, 'Akses Ditolak', 'Anda tidak memiliki akses untuk melihat data ini', null);
            }
        }

        return json(200, true, 'Berhasil', 'Detail data approval', $item);
    }

    /**
     * Update approval
     */
    public function update(Request $request, $id)
    {
        $approval = MstApproval::with('document')->find($id);

        if (!$approval) {
            return json(404, false, 'Tidak Ditemukan', 'Data approval tidak ditemukan', null);
        }

        // Role-based access control
        $user = auth()->user();
        $userRole = $user->role->id ?? $user->role_id ?? 1;
        $userDepartmentId = $user->department_id ?? null;

        if (in_array($userRole, [2, 3]) && $userDepartmentId) {
            if (!$approval->document || $approval->document->department_id !== $userDepartmentId) {
                return json(404, false, 'Akses Ditolak', 'Anda tidak memiliki akses untuk mengupdate data ini', null);
            }
        }

        $validator = Validator::make($request->all(), [
            'document_id' => 'required|integer|exists:tr_risk_header,id',
            'tahun' => 'required|integer|min:2020|max:2030',
            'posisi' => 'required|integer|min:1',
            'jabatan_id' => 'required|integer|exists:mst_jabatan,id',
            'status' => 'required|string|in:pending,approved,rejected',
            'tanggal' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        // Check duplicate
        $exist = MstApproval::where('document_id', $request->document_id)
            ->where('tahun', $request->tahun)
            ->where('posisi', $request->posisi)
            ->where('id', '!=', $id)
            ->first();

        if ($exist) {
            return json(409, false, 'Gagal', 'Sudah ada approval lain untuk dokumen, tahun, dan posisi yang sama', null);
        }

        try {
            DB::beginTransaction();

            $approval->update($request->only([
                'document_id',
                'tahun',
                'posisi',
                'jabatan_id',
                'status',
                'tanggal',
                'note'
            ]));

            $approval->load([
                'jabatan:id,name,nipp',
                'document:id,process_code,jenis_risiko'
            ]);

            DB::commit();

            return json(200, true, 'Berhasil', 'Data approval berhasil diperbarui', $approval);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat mengupdate data', null);
        }
    }

    /**
     * Remove approval
     */
    public function destroy($id)
    {
        $approval = MstApproval::find($id);

        if (!$approval) {
            return json(404, false, 'Tidak Ditemukan', 'Data approval tidak ditemukan', null);
        }

        try {
            DB::beginTransaction();

            $approval->delete();

            DB::commit();

            return json(200, true, 'Berhasil', 'Data approval berhasil dihapus', null);

        } catch (\Exception $e) {
            DB::rollBack();
            return json(500, false, 'Gagal', 'Terjadi kesalahan saat menghapus data', null);
        }
    }
}
