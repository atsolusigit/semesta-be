<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function singleUpload(Request $request)
    {
        //check validation
        $array_validation = [
            'file' => 'required|file|max:5120'
        ];

        if (check_validation($request->all(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }
        //check validation

        if ($request->hasFile('file')) {
            $name_file = time() . '_' . Str::slug(pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $request->file('file')->getClientOriginalExtension();

            $path = Storage::disk('s3')->putFileAs('semesta', $request->file('file'), $name_file, [
                'ACL' => 'public-read',
                'visibility' => 'public',
            ]);

            $url = Storage::disk('s3')->url($path);

            return json(200, true, 'Berhasil Upload', 'File berhasil diupload.', [
                'filepath' => $url,
                'domain' => $request->file('file')->getClientOriginalName(),
                'filename' => basename($url),
            ]);
        }

        return json(400, false, 'Tidak ada file', 'Mohon unggah file.', null);
    }

    public function multipleUpload(Request $request)
    {
        //check validation
        $array_validation = [
            'file' => 'required|array|min:1',
            'file.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:5120', // max 5MB per file
        ];

        if (check_validation($request->all(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }
        //check validation

        if ($request->hasFile('file')) {
            $uploadedFiles = [];
            foreach ($request->file('file') as $index => $file) {
                $name_file = time() . '_' . $index . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();

                $path = Storage::disk('s3')->putFileAs('semesta', $file, $name_file, [
                    'ACL' => 'public-read',
                    'visibility' => 'public',
                ]);

                $url = Storage::disk('s3')->url($path);

                $uploadedFiles[] = [
                    'filepath' => $url,
                    'domain' => $file->getClientOriginalName(),
                    'filename' => basename($url),
                ];
            }

            return json(200, true, 'Berhasil Upload', 'Semua file berhasil diupload.', $uploadedFiles);
        }
        return json(400, false, 'Tidak ada file', 'Mohon unggah minimal 1 file.', null);
    }
}
