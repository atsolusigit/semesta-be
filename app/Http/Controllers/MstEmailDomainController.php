<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstEmailDomain;
use Illuminate\Support\Facades\Validator;

class MstEmailDomainController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page');

        $query = MstEmailDomain::select('id', 'domain', 'status', 'created_at', 'created_by')
            ->with('createdBy:id,name,email');

        // Check user authorization
        $user = auth()->user();
        if ($user) {
            // Only role 1 & 2 can see all email domains
            if (!in_array($user->role->id, [1, 2])) {
                return json(403, false, 'Forbidden', 'Anda tidak memiliki akses ke data ini.', []);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('domain', 'like', "%{$search}%");
        }

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // If per_page is empty or "all", get all data
        if (empty($perPage) || $perPage === 'all') {
            $data = $query->orderBy('id')->get();

            $mappedData = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'domain' => $item->domain,
                    'status' => $item->status,
                    'status_label' => $item->status_label,
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                    'created_by' => $item->created_by,
                    'created_by_name' => $item->createdBy ? get_decrypted_name($item->createdBy) : null,
                    'created_by_email' => $item->createdBy ? get_decrypted_email($item->createdBy) : null,
                ];
            });

            return json(200, true, 'Success', 'Daftar email domain yang tersedia.', [
                'total' => $mappedData->count(),
                'data' => $mappedData,
            ]);
        }

        // With pagination
        $data = $query->orderBy('id')->paginate($perPage);

        $mappedData = $data->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'domain' => $item->domain,
                'status' => $item->status,
                'status_label' => $item->status_label,
                'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                'created_by' => $item->created_by,
                'created_by_name' => $item->createdBy ? get_decrypted_name($item->createdBy) : null,
                'created_by_email' => $item->createdBy ? get_decrypted_email($item->createdBy) : null,
            ];
        });

        $responseData = [
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'last_page' => $data->lastPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'data' => $mappedData,
        ];

        if ($mappedData->isEmpty()) {
            return json(404, false, 'Not Found', 'Data email domain tidak ditemukan.', []);
        }

        return json(200, true, 'Success', 'Daftar email domain yang tersedia.', $responseData);
    }

    public function show($id)
    {
        // Check authorization
        $result = check_role(auth()->user(), [1, 2]);
        if ($result !== true) {
            return $result;
        }

        $emailDomain = MstEmailDomain::select('id', 'domain', 'status', 'created_at', 'updated_at', 'created_by', 'updated_by')
            ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
            ->find($id);

        if (!$emailDomain) {
            return json(404, 'error', 'Not Found', 'Email domain tidak ditemukan.', null);
        }

        $safeData = [
            'id' => $emailDomain->id,
            'domain' => $emailDomain->domain,
            'status' => $emailDomain->status,
            'status_label' => $emailDomain->status_label,
            'created_at' => $emailDomain->created_at ? $emailDomain->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $emailDomain->updated_at ? $emailDomain->updated_at->format('Y-m-d H:i:s') : null,
            'created_by' => $emailDomain->created_by,
            'created_by_name' => $emailDomain->createdBy ? get_decrypted_name($emailDomain->createdBy) : null,
            'created_by_email' => $emailDomain->createdBy ? get_decrypted_email($emailDomain->createdBy) : null,
            'updated_by' => $emailDomain->updated_by,
            'updated_by_name' => $emailDomain->updatedBy ? get_decrypted_name($emailDomain->updatedBy) : null,
            'updated_by_email' => $emailDomain->updatedBy ? get_decrypted_email($emailDomain->updatedBy) : null,
        ];

        return json(200, 'success', 'Success', 'Data email domain berhasil ditemukan.', $safeData);
    }

    public function store(Request $request)
    {
        // Check authorization: only role 1 and 2 can store
        $result = check_role(auth()->user(), [1, 2]);
        if ($result !== true) {
            return $result;
        }

        $user = auth()->user();

        // Custom validation untuk status
        $statusValue = $request->status;
        $validStatuses = ['aktif', 'tidak aktif', 'active', 'inactive', 'nonaktif', 'non aktif', '1', '0', 'true', 'false', true, false, 1, 0];

        if (!in_array(strtolower(trim($statusValue)), array_map('strtolower', $validStatuses)) && !is_bool($statusValue) && !is_numeric($statusValue)) {
            return response()->json([
                'code' => 400,
                'status' => 'error_validation',
                'message' => 'Status tidak valid.',
                'data' => [
                    'status' => ['Status harus berupa: aktif, tidak aktif, 1, 0, true, atau false']
                ]
            ], 200);
        }

        $validation = check_validation($request->all(), [
            'domain' => 'required|string|max:100|unique:mst_email_domains,domain',
            'status' => 'required',
        ]);

        if ($validation[0] == 1) {
            return $validation[1];
        }

        $emailDomain = MstEmailDomain::create([
            'domain' => strtolower(trim($request->domain)),
            'status' => $request->status, // Model akan otomatis convert
            'created_by' => $user->id,
        ]);

        return json(200, 'success', 'Success', 'Email domain berhasil ditambahkan.', [
            'id' => $emailDomain->id,
            'domain' => $emailDomain->domain,
            'status' => $emailDomain->status,
            'status_label' => $emailDomain->status_label,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Check authorization: only role 1 and 2 can update
        $result = check_role(auth()->user(), [1, 2]);
        if ($result !== true) {
            return $result;
        }

        $user = auth()->user();

        // Custom validation untuk status
        $statusValue = $request->status;
        $validStatuses = ['aktif', 'tidak aktif', 'active', 'inactive', 'nonaktif', 'non aktif', '1', '0', 'true', 'false', true, false, 1, 0];

        if (!in_array(strtolower(trim($statusValue)), array_map('strtolower', $validStatuses)) && !is_bool($statusValue) && !is_numeric($statusValue)) {
            return response()->json([
                'code' => 400,
                'status' => 'error_validation',
                'message' => 'Status tidak valid.',
                'data' => [
                    'status' => ['Status harus berupa: aktif, tidak aktif, 1, 0, true, atau false']
                ]
            ], 200);
        }

        $validation = check_validation($request->all(), [
            'domain' => 'required|string|max:100|unique:mst_email_domains,domain,' . $id,
            'status' => 'required',
        ]);

        if ($validation[0] == 1) {
            return $validation[1];
        }

        $emailDomain = MstEmailDomain::find($id);

        if (!$emailDomain) {
            return json(404, 'error', 'Not Found', 'Email domain tidak ditemukan.', null);
        }

        $emailDomain->update([
            'domain' => strtolower(trim($request->domain)),
            'status' => $request->status, // Model akan otomatis convert
            'updated_by' => $user->id,
        ]);

        $emailDomain->refresh();

        return json(200, 'success', 'Success', 'Email domain berhasil diperbarui.', [
            'id' => $emailDomain->id,
            'domain' => $emailDomain->domain,
            'status' => $emailDomain->status,
            'status_label' => $emailDomain->status_label,
        ]);
    }

    public function destroy($id)
{
    $result = check_role(auth()->user(), [1]);
    if ($result !== true) {
        return $result;
    }

    $emailDomain = MstEmailDomain::find($id);

    if (!$emailDomain) {
        return json(404, 'error', 'Not Found', 'Email domain tidak ditemukan.', null);
    }

    $domain = $emailDomain->domain;
    $emailDomain->delete(); // Hapus permanen

    return json(200, 'success', 'Success', 'Email domain "' . $domain . '" berhasil dihapus permanen.', null);
}

    /**
     * Get active email domains only (for dropdown/select)
     */
   public function getActiveDomains()
    {
        $domains = MstEmailDomain::active()
            ->select('id', 'domain')
            ->orderBy('domain', 'desc')
            ->get();

        return json(200, true, 'Success', 'Daftar email domain yang aktif.', [
            'data' => $domains
        ]);
    }
}
