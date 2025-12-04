<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\SentMessage;
use App\Mail\RecommendationNotif;
use App\Models\RecommendationInvestasiNotif;
<<<<<<< HEAD
=======
use App\Models\MstEmailRiskOwner;
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae

class RecommendationNotifController extends Controller
{

    public function show($id)
    {
        $rekomendasi = RecommendationInvestasiNotif::where('erkap_id', $id)->get();

        if (!$rekomendasi) {
            return json(404, false, 'Tidak Ditemukan', 'Data tidak ditemukan.', null);
        }

        return json(200, true, 'Detail Ditemukan', 'Detail data berhasil diambil.', $rekomendasi);
    }

    public function sendRecommendationEmails(Request $request) {

<<<<<<< HEAD
        $result = check_role(auth()->user(), [1,2]);
        if ($result !== true) return $result;

=======
        $result = check_role(auth()->user(), [1,2,4,5]);
        if ($result !== true) return $result;

        $user = auth()->user();
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        $validator = Validator::make($request->all(), [
            'erkap_id' =>'required|integer',
            'nama_investasi' => 'required|string',
            'kategori_investasi' => 'required|string',
            'tahun'=> 'required|numeric',
            'rekomendasi' => 'required|string',
            'risk_owner' => 'required|string',
<<<<<<< HEAD
        ]);
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

=======
            'risk_owner_id' => 'required|numeric',
        ]);
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

        $email_user = MstEmailRiskOwner::where('unit_kerja_id', $request->risk_owner_id)->value('unit_kerja_email');
        $email_str = $email_user;
        $email_user = explode(",", $email_user);
        if (empty($email_user)) {
             return json(404, false, 'Email Tidak Ditemukan', 'Email '.$request->risk_owner.' tidak ditemukan, harap tambahkan di master email.', null);
        }

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        $jmlNotif = RecommendationInvestasiNotif::where('erkap_id', $request->erkap_id)->count();
        $jmlNotif +=1;

        $myRequest = $request->only([
                'erkap_id',
                'nama_investasi',
                'kategori_investasi',
                'tahun',
                'rekomendasi',
                'risk_owner'
            ]);

        $data = (object) $myRequest;
        $data->count_notif = $jmlNotif;
        try {

            DB::beginTransaction();

<<<<<<< HEAD
            $currentUser = get_decrypted_name(auth()->user());

            $myRequest['created_by'] = auth()->id();
            $myRequest['kirim_ke'] = 'atsolusigit@gmail.com';
            $myRequest['dikirim_oleh'] = $currentUser;
            $myRequest['status'] = 'Terkirim';

=======
            $currentUser = get_decrypted_name($user->id);

            $myRequest['created_by'] = $user->id;
            $myRequest['kirim_ke'] = $email_str;
            $myRequest['dikirim_oleh'] = $currentUser;
            $myRequest['status'] = 'Terkirim';
      
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
            $item = RecommendationInvestasiNotif::create($myRequest);
            
            $responseData = [
                'Erkap_id' => $data->erkap_id,
                'nama_investasi' => $data->nama_investasi,
                'kategori_investasi' => $data->kategori_investasi,
                'total' => $data->tahun,
                'rekomendasi' => $data->rekomendasi,
                'status' => $myRequest['status'] ,
            ];
            
<<<<<<< HEAD
            if(Mail::to(['atsolusigit@gmail.com', 'ramdhaniteddy21@gmail.com'])
            ->cc(['aryoaditya2000@gmail.com'])
            ->send(new RecommendationNotif(
                    $data
                    // $data->erkap_id,
                    // $data->nama_investasi,
                    // $data->kategori_investasi,
                    // $data->tahun,
                    // $data->rekomendasi,
                    // $data->risk_owner,
                    // $data->count_notif,
=======
            $cc = '';
            if(Mail::to($email_user)
            ->when($cc, fn($mail) => $mail->cc($cc))
            ->send(new RecommendationNotif(
                    $data
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
            )) instanceof SentMessage){
        
                DB::commit();
                return json(200, true, 'Email Terkirim', 'Email berhasil dikirim.', $responseData);

            } else {
                $list_email='';
                foreach(Mail::failures as $email_address) {
                    $list_email .= $email_address .", ";
                }
                return json(500, true, 'Email Gagal Terkirim', 'Email gagal dikirim.', $list_email);
            }

            
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            \Log::error('Mail sending failed for order ' . $data->erkap_id . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send email for order ' . $data->erkap_id], 500);
        }
    }
}
