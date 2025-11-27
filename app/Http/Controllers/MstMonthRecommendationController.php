<?php

namespace App\Http\Controllers;

use App\Models\MstMonthRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MstMonthRecommendationController extends Controller
{
    public function index(Request $request)
    {
        // Check authorization: only role 1, 2, 4 and 5 can access
        $user = auth()->user();
        $roleCheck = check_role($user, [1, 2, 4, 5]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $query = MstMonthRecommendation::with([
            'createdBy:id,username',
            'updatedBy:id,username'
        ]);

        if ($request->search) {
            $search = $request->search;
            $query->where('name', 'like', '%' . $search . '%');
        }

        $data = $query->orderBy('id', 'asc')->get();

        // Mapping data seperti cara di TrRiskHeader
        $mappedData = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'required' => $item->required,
                'month_required' => $item->required ? 'Rekomendasi' : 'Tidak Rekomendasi',
                'created_by' => $item->created_by ?? null,
                'created_by_name' => get_decrypted_name($item->createdBy),
                'updated_by' => $item->updated_by ?? null,
                'updated_by_name' => get_decrypted_name($item->updatedBy),
                'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
                'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,
            ];
        });

        return json(200, true, 'Berhasil', 'List data month recommendation', $mappedData);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        //Check authorization: only role 1, 2, 4 and 5 can store
        $roleCheck = check_role($user, [1, 2, 4, 5]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $validation = check_validation($request->all(), [
            'name' => 'required|string|max:255',
            'required' => 'nullable|string|in:Rekomendasi,Tidak Rekomendasi'
        ]);

        if ($validation[0] == 1) {
            return $validation[1];
        }

        // Konversi string ke boolean
        $requiredValue = ($request->required === 'Rekomendasi') ? 1 : 0;

        $monthRecommendation = MstMonthRecommendation::create([
            'name' => $request->name,
            'required' => $requiredValue,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        return json(200, true, 'Berhasil', 'Month recommendation berhasil ditambahkan', $monthRecommendation);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        // Check authorization: only role 1, 2, 4 and 5 can update
        $roleCheck = check_role($user, [1, 2, 4, 5]);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $monthRecommendation = MstMonthRecommendation::find($id);
        if (!$monthRecommendation) {
            return json(404, false, 'Data Tidak Ditemukan', 'Month recommendation tidak ditemukan.', null);
        }

        $validation = check_validation($request->all(), [
            'name' => 'required|string|max:255',
            'required' => 'nullable|string|in:Rekomendasi,Tidak Rekomendasi'
        ]);

        if ($validation[0] == 1) {
            return $validation[1];
        }

        // Konversi string ke boolean
        $requiredValue = ($request->required === 'Rekomendasi') ? 1 : 0;

        $monthRecommendation->update([
            'name' => $request->name,
            'required' => $requiredValue,
            'updated_by' => $user->id
        ]);

        return json(200, true, 'Berhasil', 'Data Rekomendasi Bulan berhasil diupdate', $monthRecommendation);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        // Check authorization: only role 1 can delete
        $roleCheck = check_role($user, 1);

        if ($roleCheck !== true) {
            return $roleCheck;
        }

        $monthRecommendation = MstMonthRecommendation::find($id);
        if (!$monthRecommendation) {
            return json(404, false, 'Data Tidak Ditemukan', 'Data Rekomendasi tidak ditemukan.', null);
        }

        $monthRecommendation->delete();

        return json(200, true, 'Berhasil', 'Data Rekomendasi berhasil dihapus', null);
    }
}
