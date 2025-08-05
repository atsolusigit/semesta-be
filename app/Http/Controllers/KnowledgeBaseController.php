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

    // Mapping tipe dokumen
    $typeMap = [
        1 => 'NEWS',
        2 => 'PERDIR',
        3 => 'SOP',
        4 => 'SURAT KEPUTUSAN',
        5 => 'REGULASI',
    ];

    // Enkripsi creator_id dan tambahkan label type
    $data->getCollection()->transform(function ($item) use ($typeMap) {
        $item->creator_id = encrypt_decrypt_md5('enc', $item->creator_id);
        $item->type_label = $typeMap[$item->type] ?? 'TIDAK DIKETAHUI';
        return $item;
    });

    return json(200, true, 'success', 'Data berhasil diambil', $data);
}

   public function show($id)
{
    try {
        \Log::info('Starting show function for ID: ' . $id);

        $data = Knowledgebase::find($id);
        if (!$data) {
            return json(404, false, 'not_found', 'Data tidak ditemukan', null);
        }

        \Log::info('Basic data loaded successfully');

        $fieldsToCheck = ['title', 'description', 'long_description', 'img_path'];
        foreach ($fieldsToCheck as $field) {
            try {
                $value = $data->$field;
                if ($value !== null) {
                    $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                    $isValidUtf8 = mb_check_encoding($value, 'UTF-8');

                    \Log::info("Field $field check:", [
                        'value_length' => strlen($value),
                        'detected_encoding' => $encoding,
                        'is_valid_utf8' => $isValidUtf8,
                        'first_50_chars' => substr($value, 0, 50)
                    ]);

                    if (!$isValidUtf8) {
                        \Log::error("Invalid UTF-8 detected in field: $field");

                        if ($encoding) {
                            $data->$field = mb_convert_encoding($value, 'UTF-8', $encoding);
                        } else {
                            $data->$field = null;
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Error checking field $field: " . $e->getMessage());
                $data->$field = null;
            }
        }

        \Log::info('Field validation completed');

        $typeMap = [
            1 => 'NEWS',
            2 => 'PERDIR',
            3 => 'SOP',
            4 => 'SURAT KEPUTUSAN',
            5 => 'REGULASI',
        ];
        $data->type_label = $typeMap[$data->type] ?? 'TIDAK DIKETAHUI';

        \Log::info('Type label added');

        $responseData = [
            'id' => $data->id,
            'description' => $data->description,
            'long_description' => $data->long_description,
            'img_path' => $data->img_path,
            'type' => $data->type,
            'type_label' => $data->type_label,
            'creator_id' => $data->creator_id,
            'created_at' => $data->created_at,
            'updated_at' => $data->updated_at,

        ];

        \Log::info('Response data prepared:', $responseData);

        return json(200, true, 'success', 'Detail Data', $responseData);

    } catch (\Exception $e) {
        \Log::error('Show function failed at step:', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ]);

        return json(500, false, 'error', 'Gagal mengambil data: ' . $e->getMessage(), null);
    }
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

        // Tambahkan type_label
        $Base->type_label = match ($Base->type) {
            1 => 'NEWS',
            2 => 'PERDIR',
            3 => 'SOP',
            4 => 'SURAT KEPUTUSAN',
            5 => 'REGULASI',
            default => 'TIDAK DIKETAHUI',
        };

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
        // Update hanya fields yang diperlukan, tanpa processing creator
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

        // Return minimal response tanpa load creator
        $Base->type_label = match ($Base->type) {
            1 => 'NEWS',
            2 => 'PERDIR',
            3 => 'SOP',
            4 => 'SURAT KEPUTUSAN',
            5 => 'REGULASI',
            default => 'TIDAK DIKETAHUI',
        };

        // Hapus sementara semua processing creator dan enkripsi
        unset($Base->creator);

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
