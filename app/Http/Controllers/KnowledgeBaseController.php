<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Knowledgebase;
use App\Models\KnowledgeBaseReader;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\File;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Knowledgebase::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $data = $query->latest()->paginate(10);

        // Enkripsi creator_id saja, ID knowledge base tetap plain
        $data->getCollection()->transform(function ($item) {
            $item->creator_id = encrypt_decrypt_md5('enc', $item->creator_id);
            return $item;
        });

        return json(200, true, 'success', 'Data berhasil diambil', $data);
    }

    public function show($id)
    {
        // Langsung gunakan ID tanpa dekripsi
        $data = Knowledgebase::with('creator')->find($id);
        if (!$data) {
            return json(404, false, 'not_found', 'Data tidak ditemukan', null);
        }

        try {
            if ($data->creator) {
                // Enkripsi ID creator
                $data->creator->id = encrypt_decrypt_md5('enc', $data->creator->id);

                if ($data->creator->email) {
                    $data->creator->email = encrypt_decrypt_db('decrypt', $data->creator->email, $data->creator->id);
                }

                unset(
                    $data->creator->jtkn,
                    $data->creator->fbtk,
                    $data->creator->nip,
                    $data->creator->phone_number,
                    $data->creator->gender,
                    $data->creator->photo,
                    $data->creator->email_verified_at
                );
            }
        } catch (\Exception $e) {
            if ($data->creator) {
                $data->creator->email = null;
            }
        }

        // Enkripsi creator_id saja, ID knowledge base tetap plain
        $data->creator_id = encrypt_decrypt_md5('enc', $data->creator_id);

        return json(200, true, 'success', 'Detail Data', $data);
    }

    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            return json(403, false, 'forbidden', 'Anda tidak memiliki izin untuk membuat data', null);
        }

        $array_validation = [
            'img_path' => 'required|string|max:255',
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

            // Enkripsi creator_id saja, ID knowledge base tetap plain
            $Base->creator_id = encrypt_decrypt_md5('enc', $Base->creator_id);

            return json(200, true, 'success', 'Data berhasil disimpan', $Base);
        } catch (\Exception $e) {
            DB::rollback();
            return json(500, false, 'error', 'Gagal menyimpan data: ' . $e->getMessage(), null);
        }
    }

    public function update(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!in_array($user->role_id, [1, 2, 6, 7])) {
            return json(403, false, 'forbidden', 'Anda tidak memiliki izin untuk mengupdate data', null);
        }

        // Langsung gunakan ID tanpa dekripsi
        $Base = Knowledgebase::find($id);
        if (!$Base) {
            return json(404, false, 'not_found', 'Data tidak ditemukan', null);
        }

        $array_validation = [
            'img_path' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'long_description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:1,2,3,4,5',
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

            if ($request->has('description')) {
                $Base->description = $request->description;
            }

            if ($request->has('long_description')) {
                $Base->long_description = $request->long_description;
            }

            if ($request->has('type')) {
                $Base->type = $request->type;
            }

            $Base->save();

            DB::commit();

            $Base->load('creator');

            try {
                if ($Base->creator) {
                    // Enkripsi ID creator
                    $creatorId = $Base->creator->id;
                    $Base->creator->id = encrypt_decrypt_md5('enc', $creatorId);

                    if ($Base->creator->email) {
                        $Base->creator->email = encrypt_decrypt_db('decrypt', $Base->creator->email, $creatorId);
                    }

                    unset(
                        $Base->creator->jtkn,
                        $Base->creator->fbtk,
                        $Base->creator->nip,
                        $Base->creator->phone_number,
                        $Base->creator->gender,
                        $Base->creator->photo,
                        $Base->creator->email_verified_at
                    );
                }
            } catch (\Exception $e) {
                if ($Base->creator) {
                    $Base->creator->email = null;
                }
            }

            // Enkripsi creator_id saja, ID knowledge base tetap plain
            $Base->creator_id = encrypt_decrypt_md5('enc', $Base->creator_id);

            return json(200, true, 'success', 'Data berhasil diupdate', $Base);
        } catch (\Exception $e) {
            DB::rollback();
            return json(500, false, 'error', 'Gagal update data: ' . $e->getMessage(), null);
        }
    }

    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!in_array($user->role_id, [1, 6])) {
            return json(403, false, 'forbidden', 'Anda tidak memiliki izin untuk menghapus data', null);
        }

        // Langsung gunakan ID tanpa dekripsi
        $Base = Knowledgebase::find($id);
        if (!$Base) {
            return json(404, false, 'not_found', 'Data tidak ditemukan', null);
        }

        try {
            File::delete(public_path('storage/' . $Base->img_path));
            $Base->delete();
            return json(200, true, 'success', 'Data berhasil dihapus', null);
        } catch (\Exception $e) {
            return json(500, false, 'error', 'Gagal menghapus data: ' . $e->getMessage(), null);
        }
    }

    public function trackReader($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Langsung gunakan ID tanpa dekripsi
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

            return json(200, true, 'reader_tracked', 'Pembacaan berhasil dicatat', null);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return json(401, false, 'unauthenticated', 'Token tidak valid atau kedaluwarsa', null);
        } catch (\Exception $e) {
            return json(500, false, 'error', 'Gagal mencatat pembaca: ' . $e->getMessage(), null);
        }
    }
}
