<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecommendationNotif;
use App\Models\RecommendationInvestasiNotif;

class RecommendationNotifController extends Controller
{
    public function sendRecommendationEmails(Request $request) {

        $result = check_role(auth()->user(), [1,2]);
        if ($result !== true) return $result;

        $validator = Validator::make($request->all(), [
            'erkap_id' =>'required|integer',
            'nama_investasi' => 'required|string',
            'tahun'=> 'required|numeric',
            'rekomendasi' => 'required|string',
        ]);
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

        $myRequest = $request->only([
                'erkap_id',
                'nama_investasi',
                'tahun',
                'rekomendasi'
            ]);
        $data = (object) $myRequest;
    
        try {

            DB::beginTransaction();

            $currentUser = auth()->user();

            $data->created_by = auth()->id();
            $data->kirim_ke = 'deo.mirabian@gmail.com';
            $data->dikirim_oleh = get_decrypted_name($data->created_by);

            $item = RecommendationInvestasiNotif::create($data);

            DB::commit();

            Mail::to('deo.mirabian@gmail.com')
            // ->cc(['cc1@mail.com', 'cc2@mail.com'])
            // ->bcc(['bcc1@mail.com', 'bcc2@mail.com']) 
            ->send(new RecommendationNotif(
                    $data->erkap_id,
                    $data->nama_investasi,
                    $data->tahun,
                    $data->rekomendasi,
                ));
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            \Log::error('Mail sending failed for order ' . $data->erkapID . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send email for order ' . $data->erkapID], 500);
        }

    }
}
