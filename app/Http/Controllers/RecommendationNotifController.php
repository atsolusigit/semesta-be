<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\SentMessage;
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
            
            $myRequest['created_by'] = auth()->id();
            $myRequest['kirim_ke'] = 'deo.mirabian@gmail.com';
            $myRequest['dikirim_oleh'] = get_decrypted_name(auth()->id());

            $item = RecommendationInvestasiNotif::create($myRequest);
            $responseData = [
                'Erkap_id' => $data->erkap_id,
                'nama_investasi' => $data->nama_investasi,
                'total' => $data->tahun,
                'rekomendasi' => $data->rekomendasi,
            ];

            if(Mail::to('deo.mirabian@gmail.com', 'ramdhaniteddy21@gmail.com')
            ->send(new RecommendationNotif(
                    $data->erkap_id,
                    $data->nama_investasi,
                    $data->tahun,
                    $data->rekomendasi,
            )) instanceof SentMessage){

                DB::commit();
                return json(200, true, 'Email Terkirim', 'Email berhasil dikirim.', $responseData);

            } else {
                foreach(Mail::failures as $email_address) {
                    echo " - $email_address <br />";
                }
                return json(500, true, 'Email Gagal Terkirim', 'Email gagal dikirim.', $responseData);
            }

            
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            \Log::error('Mail sending failed for order ' . $data->erkap_id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send email for order ' . $data->erkap_id], 500);
        }

    }
}
