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
    
            $path = Storage::disk('s3')->put('semesta',$request->file('file'),[
                'ACL' => 'public-read',
                'visibility' => 'public',
            ]);
    
            $url = Storage::disk('s3')->url($path);
    
            return json(200, 'true', 'success', 'upload successfully.', $url);
        }
    }

    public function multipleUpload(Request $request)
    {
        //check validation
        $array_validation = [
            'file' => 'required|array',
            'file.*' => 'file|mimes:jpeg,jpg,png,gif|max:5120', // max 5MB per file
        ];

        if (check_validation($request->all(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }
        //check validation

        if ($request->hasFile('file')) {
            $uploadedUrls = [];
            foreach ($request->file('file') as $file) {
                $name_file = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        
                $path = Storage::disk('s3')->put('semesta',$file,[
                    'ACL' => 'public-read',
                    'visibility' => 'public',
                ]);
        
                $url = Storage::disk('s3')->url($path);

                array_push($uploadedUrls, $url);
            }
    
            return json(200, 'true', 'success', 'upload successfully.', $uploadedUrls);
        }
    }
}
