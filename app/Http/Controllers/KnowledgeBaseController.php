<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Knowledgebase;
use App\Models\KnowledgebaseReader;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\Models\KnowledgeUpload;

class KnowledgeBaseController extends Controller
{
   public function index(Request $request)
{
    $query = Knowledgebase::with(['creator', 'updater', 'uploads']);

    if ($request->has('type')) {
        $query->where('type', $request->type);
    }

    $data = $query->latest()->get();

    $typeMap = [
        1 => 'NEWS',
        2 => 'PERDIR',
        3 => 'SOP',
        4 => 'SURAT KEPUTUSAN',
        5 => 'REGULASI',
    ];

    $data->transform(function ($item) use ($typeMap) {

        // ambil semua file img dan doc (bisa multiple) dengan nama file
        $imgs = $item->uploads->where('type', 'img_path')->map(function($upload) {
            return [
                'id' => $upload->id,
                'filename' => $upload->filename,
                'path' => $upload->path
            ];
        })->values()->toArray();

        $docs = $item->uploads->where('type', 'doc_path')->map(function($upload) {
            return [
                'id' => $upload->id,
                'filename' => $upload->filename,
                'path' => $upload->path
            ];
        })->values()->toArray();

        // kembalikan sebagai array (kosong jika tidak ada)
        $item->img_path = $imgs;
        $item->doc_path = $docs;

        // creator_id
        $item->creator_id = $item->creator_id
            ? encrypt_decrypt_md5('enc', $item->creator_id)
            : null;

        // label
        $item->type_label = $typeMap[$item->type] ?? 'TIDAK DIKETAHUI';

        $item->created_by_name = get_decrypted_name($item->creator ?? null);
        $item->updated_by_name = get_decrypted_name($item->updater ?? null);

        unset($item->creator, $item->updater, $item->uploads);

        return clean_recursive($item);
    });

    return json(200, true, 'success', 'Data berhasil diambil', [
        'per_load' => $request->input('per_load', 6),
        'data' => $data
    ]);
}

   public function show($id)
{
    $data = Knowledgebase::with(['creator','updater','uploads'])->find($id);
    if (!$data) {
        return json(404, false, 'not_found', 'Data tidak ditemukan', null);
    }

    // ambil semua file img dan doc (bisa multiple) dengan nama file
    $imgs = $data->uploads->where('type', 'img_path')->map(function($upload) {
        return [
            'id' => $upload->id,
            'filename' => $upload->filename,
            'path' => $upload->path
        ];
    })->values()->toArray();

    $docs = $data->uploads->where('type', 'doc_path')->map(function($upload) {
        return [
            'id' => $upload->id,
            'filename' => $upload->filename,
            'path' => $upload->path
        ];
    })->values()->toArray();

    $responseData = [
        'id' => $data->id,
        'title' => $data->title,
        'description' => $data->description,
        'long_description' => $data->long_description,

        // kembalikan sebagai array (kosong jika tidak ada)
        'img_path' => $imgs,
        'doc_path' => $docs,

        'type' => $data->type,
        'type_label' => match ($data->type) {
            1 => 'NEWS',
            2 => 'PERDIR',
            3 => 'SOP',
            4 => 'SURAT KEPUTUSAN',
            5 => 'REGULASI',
            default => 'TIDAK DIKETAHUI'
        },

        'creator_id' => $data->creator_id
            ? encrypt_decrypt_md5('enc', $data->creator_id)
            : null,

        'created_by_name' => get_decrypted_username($data->creator),
        'updated_by_name' => get_decrypted_username($data->updater),

        'created_at' => $data->created_at,
        'updated_at' => $data->updated_at,
    ];

    return json(200, true, 'success', 'Detail Data', $responseData);
}


    public function store(Request $request)
{
    $result = check_role(auth()->user(), [1, 2, 3]);
    if ($result !== true) {
        return $result;
    }

    $user = auth()->user();

    $array_validation = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'long_description' => 'nullable|string',
        'type' => 'required|in:1,2,3,4,5',
    ];

    $validate = check_validation($request->all(), $array_validation);
    if ($validate[0] !== 0) return $validate[1];

    DB::beginTransaction();

    try {
        $Base = Knowledgebase::create([
            'creator_id' => $user->id,
            'created_by' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'long_description' => $request->long_description,
            'type' => $request->type,
        ]);

       // AUTO-LINK file upload milik user yg belum memiliki knowledge_id
        KnowledgeUpload::whereNull('knowledge_id')
            ->where('created_by', $user->id)
            ->update(['knowledge_id' => $Base->id]);

        DB::commit();

        $Base->creator_id = encrypt_decrypt_md5('enc', $Base->creator_id);

        $Base->type_label = match ($Base->type) {
            1 => 'NEWS',
            2 => 'PERDIR',
            3 => 'SOP',
            4 => 'SURAT KEPUTUSAN',
            5 => 'REGULASI',
            default => 'TIDAK DIKETAHUI',
        };

        $Base->created_by_name = get_decrypted_username($user);

        return json(200, true, 'success', 'Data berhasil disimpan', $Base);

    } catch (\Exception $e) {
        DB::rollback();
        return json(500, false, 'error', 'Gagal menyimpan data: ' . $e->getMessage(), null);
    }
}

   public function update(Request $request, $id)
{
    $result = check_role(auth()->user(), [1, 2, 3]);
    if ($result !== true) {
        return $result;
    }

    $user = auth()->user();

    $Base = Knowledgebase::find($id);
    if (!$Base) {
        return json(404, false, 'not_found', 'Data tidak ditemukan', null);
    }

    $array_validation = [
        'title' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'long_description' => 'nullable|string',
        'type' => 'nullable|in:1,2,3,4,5',
        'upload_ids' => 'nullable|array',
        'upload_ids.*' => 'integer|exists:knowledge_uploads,id',
    ];

    $validate = check_validation($request->all(), $array_validation);
    if ($validate[0] !== 0) return $validate[1];

    DB::beginTransaction();

    try {

        if ($request->filled('title')) $Base->title = $request->title;
        if ($request->has('description')) $Base->description = $request->description;
        if ($request->has('long_description')) $Base->long_description = $request->long_description;
        if ($request->has('type')) $Base->type = $request->type;

        $Base->updated_by = $user->id;
        $Base->save();

        // AUTO LINK UPLOAD BARU
        KnowledgeUpload::whereNull('knowledge_id')
            ->where('created_by', $user->id)
            ->update(['knowledge_id' => $Base->id]);

        // LINK FILE BERDASARKAN upload_ids (optional)
        if ($request->filled('upload_ids')) {
            KnowledgeUpload::whereIn('id', $request->upload_ids)
                ->where(function ($q) use ($Base) {
                    $q->whereNull('knowledge_id')
                      ->orWhere('knowledge_id', $Base->id);
                })
                ->update(['knowledge_id' => $Base->id]);
        }

        DB::commit();

        $Base->load(['creator', 'updater']);

        $Base->creator_id = encrypt_decrypt_md5('enc', $Base->creator_id);

        $Base->type_label = match ($Base->type) {
            1 => 'NEWS',
            2 => 'PERDIR',
            3 => 'SOP',
            4 => 'SURAT KEPUTUSAN',
            5 => 'REGULASI',
            default => 'TIDAK DIKETAHUI',
        };

        $Base->created_by_name = get_decrypted_username($Base->creator);
        $Base->updated_by_name = get_decrypted_username($Base->updater);

        unset($Base->creator, $Base->updater);

        return json(200, true, 'success', 'Data berhasil diupdate', $Base);

    } catch (\Exception $e) {
        DB::rollback();
        return json(500, false, 'error', 'Gagal update data: ' . $e->getMessage(), null);
    }
}

    public function destroy($id)
{
    // ❗ Hanya role id = 1 yang boleh menghapus
    if (auth()->user()->role_id != 1) {
        return json(403, false, 'forbidden', 'Anda tidak memiliki akses untuk menghapus data', null);
    }

    $Base = Knowledgebase::find($id);
    if (!$Base) {
        return json(404, false, 'not_found', 'Data tidak ditemukan', null);
    }

    DB::beginTransaction();

    try {
        // === HAPUS FILE DARI KNOWLEDGE_UPLOADS (base64 di database) ===
        $uploads = KnowledgeUpload::where('knowledge_id', $Base->id)->get();

        foreach ($uploads as $upload) {
            // Karena path berisi base64, tidak perlu hapus file fisik
            // Langsung hapus record dari database
            $upload->delete();
        }

        // === HAPUS KNOWLEDGEBASE ===
        $Base->delete();

        DB::commit();

        return json(200, true, 'success', 'Data berhasil dihapus', null);

    } catch (\Exception $e) {
        DB::rollback();
        return json(500, false, 'error', 'Gagal menghapus data: ' . $e->getMessage(), null);
    }
}

    public function trackReader($id)
    {
        try {
            if (!auth()->check()) {
                return json(401, false, 'unauthenticated', 'User tidak terautentikasi', null);
            }

            $user = auth()->user();

            $knowledge = Knowledgebase::find($id);
            if (!$knowledge) {
                return json(404, false, 'not_found', 'Knowledge base tidak ditemukan', null);
            }

            $existing = KnowledgeBaseReader::where('user_id', $user->id)
                ->where('id_knowledge', $id)
                ->first();

            if ($existing) {
                return json(200, true, 'reader_exists', 'User sudah membaca knowledge ini', [
                    'knowledge_title' => $knowledge->title,
                    'read_at' => $existing->created_at->timezone('Asia/Jakarta')->toDateTimeString(),
                ]);
            }

            $reader = KnowledgeBaseReader::create([
                'user_id' => $user->id,
                'id_knowledge' => $id
            ]);

            return json(200, true, 'reader_tracked', 'Pembacaan berhasil dicatat', [
                'knowledge_title' => $knowledge->title,
                'read_at' => $reader->created_at->timezone('Asia/Jakarta')->toDateTimeString(),
            ]);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return json(401, false, 'unauthenticated', 'Token tidak valid atau kedaluwarsa', null);
        } catch (\Exception $e) {
            return json(500, false, 'error', 'Gagal mencatat pembaca: ' . $e->getMessage(), null);
        }
    }

    public function uploadFile(Request $request)
    {
        $result = check_role(auth()->user(), [1, 2, 3]);
        if ($result !== true) {
            return $result;
        }

        $type = $request->query('type');

        if (!in_array($type, ['img_path', 'doc_path'])) {
            return json(400, false, 'invalid_type', 'Parameter type harus img_path atau doc_path', null);
        }

        $rules = [
            'file' => 'required',
            'file.*' => ($type === 'img_path')
                ? 'file|mimes:jpg,jpeg,png,webp|max:20480'
                : 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:51200',
        ];

        $validate = check_validation($request->all(), $rules);
        if ($validate[0] !== 0) return $validate[1];

        try {

            $files = $request->file('file');

            // Paksa selalu array meskipun hanya 1 file
            if (!is_array($files)) {
                $files = [$files];
            }

            $uploaded = [];

            foreach ($files as $file) {

                $originalName = $file->getClientOriginalName();
                $mime = $file->getMimeType();
                $fileContent = file_get_contents($file->getRealPath());
                $base64 = "data:$mime;base64," . base64_encode($fileContent);

                $upload = KnowledgeUpload::create([
                    'knowledge_id' => null,
                    'type' => $type,
                    'path' => $base64,
                    'filename' => $originalName,
                    'created_by' => auth()->id(),
                ]);

                $uploaded[] = [
                    'upload_id' => $upload->id,
                    'filename' => $originalName,
                    'base64' => $base64
                ];
            }

            return json(200, true, 'success', 'File berhasil diupload', [
                'uploads' => $uploaded
            ]);

        } catch (\Exception $e) {
            return json(500, false, 'error', 'Gagal upload file: ' . $e->getMessage(), null);
        }
    }

    public function deleteFile($id, Request $request)
    {
        $result = check_role(auth()->user(), [1, 2, 3]);
        if ($result !== true) {
            return $result;
        }

        $upload = KnowledgeUpload::find($id);
        if (!$upload) {
            return json(404, false, 'not_found', 'File tidak ditemukan', null);
        }

        try {
            // Karena path berisi base64, tidak perlu hapus file fisik
            // Langsung hapus record dari database
            $upload->delete();

            return json(200, true, 'success', 'File berhasil dihapus', null);

        } catch (\Exception $e) {
            return json(500, false, 'error', 'Gagal menghapus file: ' . $e->getMessage(), null);
        }
    }

}
