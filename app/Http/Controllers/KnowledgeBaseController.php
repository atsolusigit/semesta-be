<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Knowledgebase;
use App\Models\KnowledgeBaseReader;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;


class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Knowledgebase::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $data = $query->latest()->paginate(10);
        return json(200, true, 'success', 'Data berhasil diambil', $data);
    }

    public function show($id)
    {
// ganti knoledgebase dengan knowledgebase
        $data = Knowledgebase::with('creator')->find($id);
        if (!$data) {
            return json(404, 'false', 'not_found', 'Data tidak ditemukan', null);
        }

        try {
            if ($data->creator && $data->creator->email) {
                $data->creator->email = encrypt_decrypt_db('decrypt', $data->creator->email, $data->creator->id);
            }
        } catch (\Exception $e) {
            $data->creator->email = null;
        }

        return json(200, 'true', 'success', 'Detail Data', $data);
    }

    public function store(Request $request)
{
    $user = JWTAuth::parseToken()->authenticate();
    if (!in_array($user->role_id, [1, 2, 6, 7])) {
        return json(403, false, 'forbidden', 'Anda tidak memiliki izin untuk membuat data', null);
    }

    $array_validation = [
        'img_path' => 'required|string|max:255', // sekarang hanya link, bukan file
        'description' => 'nullable|string|max:500',
        'long_description' => 'nullable|string|max:1000',
        'type' => 'required|in:1,2,3,4,5',
    ];

    if (check_validation($request->all(), $array_validation)[0] !== 0) {
        return check_validation($request->all(), $array_validation)[1];
    }

    DB::beginTransaction();

    try {
        $Base = Knowledgebase::create([
            'creator_id' => $user->id,
            'img_path' => $request->img_path,
            'description' => $request->description,
            'long_description' => $request->long_description,
            'type' => $request->type,
        ]);

        DB::commit();

        return json(200, 'true', 'success', 'Data berhasil disimpan', $Base);
    } catch (\Exception $e) {
        DB::rollback();
        return json(500, 'false', 'error', 'Gagal menyimpan data: ' . $e->getMessage(), null);
    }
}

    public function update(Request $request, $id)
{
    $user = JWTAuth::parseToken()->authenticate();
    if (!in_array($user->role_id, [1, 2, 6, 7])) {
        return json(403, false, 'forbidden', 'Anda tidak memiliki izin untuk mengupdate data', null);
    }

    $Base = Knowledgebase::find($id);
    if (!$Base) {
        return json(404, 'false', 'not_found', 'Data tidak ditemukan', null);
    }

    $array_validation = [
        'img_path' => 'nullable|string|max:255', // string link
        'description' => 'nullable|string|max:500',
        'long_description' => 'nullable|string|max:1000',
        'type' => 'required|in:1,2,3,4,5',
    ];

    $validate = check_validation($request->all(), $array_validation);
    if ($validate[0] !== 0) {
        return $validate[1];
    }

    DB::beginTransaction();

    try {
        if ($request->filled('img_path')) {
            $Base->img_path = $request->img_path;
        }

        $Base->description = $request->description;
        $Base->long_description = $request->long_description;
        $Base->type = $request->type;
        $Base->save();

        DB::commit();

        $Base->load('creator');

        try {
            if ($Base->creator && $Base->creator->email) {
                $Base->creator->email = encrypt_decrypt_db('decrypt', $Base->creator->email, $Base->creator->id);
            }
        } catch (\Exception $e) {
            $Base->creator->email = null;
        }

        return json(200, 'true', 'success', 'Data berhasil diupdate', $Base);
    } catch (\Exception $e) {
        DB::rollback();
        return json(500, 'false', 'error', 'Gagal update data: ' . $e->getMessage(), null);
    }
}


    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!in_array($user->role_id, [1, 6])) {
            return json(403, false, 'forbidden', 'Anda tidak memiliki izin untuk menghapus data', null);
        }

        $Base = Knowledgebase::find($id);
        if (!$Base) {
            return json(404, 'false', 'not_found', 'Data tidak ditemukan', null);
        }

        try {
            File::delete(public_path('storage/' . $Base->img_path)); // Hapus gambar terkait
            $Base->delete();
            return json(200, 'true', 'success', 'Data berhasil dihapus', null);
        } catch (\Exception $e) {
            return json(500, 'false', 'error', 'Gagal menghapus data: ' . $e->getMessage(), null);
        }
    }

public function trackReader($id)
{
    try {
        $user = JWTAuth::parseToken()->authenticate();

        $knowledge = Knowledgebase::find($id);
        if (!$knowledge) {
            return json(404, false, 'not_found', 'Knowledge Base tidak ditemukan', null);
        }

        $existing = KnowledgeBaseReader::where('user_id', $user->id)
            ->where('id_knowledge', $id)
            ->first();

        if ($existing) {
            return json(200, true, 'reader_exists', 'User sudah membaca knowledge ini', null);
        }

        KnowledgeBaseReader::create([
            'user_id' => $user->id,
            'id_knowledge' => $id
        ]);

        return json(201, true, 'reader_tracked', 'Pembacaan berhasil dicatat', null);

    } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        return json(401, false, 'unauthenticated', 'Token tidak valid atau kedaluwarsa', null);
    } catch (\Exception $e) {
        return json(500, false, 'error', 'Gagal mencatat pembaca: ' . $e->getMessage(), null);
    }
}

}
