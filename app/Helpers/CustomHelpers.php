<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

if (!function_exists('check_validation')) {
    /**
     * Format a number as currency.
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    function check_validation($request, $array_validation)
    {
        $rules = [
            'asdp.required'=> 'The asdp header is required', // custom message
        ];

        $validator = Validator::make($request, $array_validation, $rules);

        if ($validator->fails()) {
            $json = response()->json([
                'code' => 400,
                'status' => 'error_validation',
                'message' => 'error validation. [400 - bad request]',
                'data' => $validator->messages()
            ], 200);

            return [1, $json];
        }
        else
        {
            return [0, ''];
        }
    }
}

if (!function_exists('json')) {
    /**
     * Format a number as currency.
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    function json($code, $status, $title, $message, $data)
    {
        return response()->json([
            'code' => $code,
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);
    }
}

if (!function_exists('time_elapsed_string')) {
    /**
     * Format a number as currency.
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

if (!function_exists('encrypt_decrypt_db')) {
    /**
     * Format a number as currency.
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    function encrypt_decrypt_db($type, $value, $id)
    {
        if ($type == 'enc') {
            $value = "AES_ENCRYPT('$value', concat('SM','$id'))";
        }
        else {
            $value = DB::select("SELECT AES_DECRYPT('$value', concat('SM', $id)) as result");

            if (count($value) == 0) {
                $value = '';
            }
            else {
                $value = $value[0]->result;
            }
        }

        return $value;
    }

}

if (!function_exists('encrypt_decrypt_md5')) {
    /**
     * Mengenkripsi atau mendekripsi string menggunakan AES-256-CBC dengan key berbasis MD5 hash.
     *
     * @param string $action 'enc' untuk enkripsi, 'dec' untuk dekripsi
     * @param string $string String yang ingin dienkripsi atau didekripsi
     * @param string $salt (Opsional) Salt tambahan untuk key dan IV
     * @return string
     */
    function encrypt_decrypt_md5($action, $string, $salt = '')
    {
        $output = '';
        $encrypt_method = 'AES-256-CBC';
        $secret_key = $salt . 'SEMESTA-asfyasiuyfiy238sadfh';
        $secret_iv = $salt . 'SEMESTA-asfyasiuyfiy238sadfh';

        // Generate key
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        if ($action === 'enc') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } elseif ($action === 'dec' && $string !== '') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }

        return $output;
    }
}


if (!function_exists('map_access_array')) {
    /**
     * Konversi array akses ke struktur boolean
     *
     * @param array $access
     * @return array
     */
    function map_access_array(array $access)
    {
        return [
            'view' => in_array('view', $access),
            'create' => in_array('create', $access),
            'update' => in_array('update', $access),
            'delete' => in_array('delete', $access),
        ];
    }
}

if (!function_exists('validate_monthly_data_for_finalization')) {
    /**
     * Validasi data bulanan sebelum finalisasi
     *
     * @param $monthly
     * @return array
     */
    function validate_monthly_data_for_finalization($monthly)
    {
        $requiredFields = [
            'process_code' => 'Kode Proses',
            'status_risiko' => 'Status Risiko',
            'start_date' => 'Tanggal Mulai',
            'expired_date' => 'Tanggal Berakhir',

            'target_quantitative' => 'Target Kuantitatif',
            'target_option' => 'Satuan Target',
            'target_notes' => 'Catatan Target',

            'realization_quantitative' => 'Realisasi Kuantitatif',
            'realization_option' => 'Satuan Realisasi',
            'realization_note' => 'Catatan Realisasi',

            'residual_risk_level_dampak' => 'Level Dampak Residual Risk',
            'residual_risk_level_kemungkinan' => 'Level Kemungkinan Residual Risk',

            'residual_risk_satutahun_level_dampak' => 'Level Dampak Residual Risk (1 Tahun)',
            'residual_risk_satutahun_level_kemungkinan' => 'Level Kemungkinan Residual Risk (1 Tahun)',
        ];

        $missingFields = [];
        foreach ($requiredFields as $field => $label) {
            if (is_null($monthly->$field) || $monthly->$field === '') {
                $missingFields[] = $label;
            }
        }

        if (!empty($missingFields)) {
            return [
                'valid' => false,
                'message' => 'Field berikut masih kosong: ' . implode(', ', $missingFields),
                'missing_fields' => $missingFields
            ];
        }

        // Validasi konsistensi tanggal
        $expectedStartDate = \Carbon\Carbon::create($monthly->header->year, $monthly->month, 1)->startOfMonth();
        $expectedEndDate = \Carbon\Carbon::create($monthly->header->year, $monthly->month, 1)->endOfMonth();

        $actualStartDate = \Carbon\Carbon::parse($monthly->start_date)->startOfMonth();
        $actualEndDate = \Carbon\Carbon::parse($monthly->expired_date)->endOfMonth();

        if (!$actualStartDate->isSameMonth($expectedStartDate) || !$actualEndDate->isSameMonth($expectedEndDate)) {
            return [
                'valid' => false,
                'message' => 'Tanggal tidak sesuai dengan bulan yang ditentukan',
                'missing_fields' => ['Date consistency']
            ];
        }

        return ['valid' => true, 'message' => 'Valid', 'missing_fields' => []];
    }
}


if (!function_exists('get_follow_up_info')) {
    /**
     * Mendapatkan informasi tindak lanjut berdasarkan data bulanan
     *
     * @param $header
     * @param $monthlyData
     * @return array
     */
    function get_follow_up_info($header, $monthlyData)
    {
        $currentYear = \Carbon\Carbon::now()->year;
        $currentMonth = \Carbon\Carbon::now()->month;

        $decemberData = $monthlyData->where('month', 12)->first();
        $isFollowUpRequired = false;
        $message = '';
        $followUpDetails = [];

        if ($header->year < $currentYear) {
            // $decemberData is array, so use array access
            if ($decemberData && $decemberData['status_risiko'] == 'open' && $decemberData['is_finalize']) {
                $isFollowUpRequired = true;
                $message = "Risiko di bulan Desember {$header->year} masih open dan sudah difinalisasi. Ini menjadi tindak lanjut di tahun {$currentYear}.";
                $followUpDetails = [
                    'follow_up_year' => $currentYear,
                    'original_year' => $header->year,
                    'december_status' => 'open_finalized'
                ];
            } elseif ($decemberData && $decemberData['status_risiko'] == 'close') {
                $message = "Semua risiko sudah close di tahun {$header->year}.";
            } else {
                $message = "Data Desember {$header->year} belum difinalisasi atau tidak ada data.";
            }
        } elseif ($header->year == $currentYear) {
            if ($currentMonth == 12) {
                if ($decemberData && $decemberData['status_risiko'] == 'open') {
                    $isFollowUpRequired = true;
                    $message = "Perhatian: Risiko di bulan Desember masih open. Ini akan menjadi tindak lanjut di tahun " . ($currentYear + 1) . ".";
                    $followUpDetails = [
                        'follow_up_year' => $currentYear + 1,
                        'original_year' => $header->year,
                        'december_status' => 'open_current'
                    ];
                } else {
                    $message = "Semua risiko sudah close untuk tahun ini.";
                }
            } else {
                $message = "Tahun risk masih berjalan. Evaluasi follow-up akan dilakukan di akhir tahun.";
            }
        } else {
            $message = "Tahun risk belum dimulai.";
        }

        return [
            'is_follow_up_required' => $isFollowUpRequired,
            'header_year' => $header->year,
            'current_year' => $currentYear,
            'current_month' => $currentMonth,
            'message' => $message,
            'december_data' => $decemberData,
            'follow_up_details' => $followUpDetails
        ];
    }
}

if (!function_exists('check_if_follow_up_required')) {
    /**
     * Cek apakah tindak lanjut diperlukan berdasarkan data bulanan
     *
     * @param $header
     * @param $monthlyData
     * @return bool
     */
    function check_if_follow_up_required($header, $monthlyData)
    {
        $currentYear = \Carbon\Carbon::now()->year;
        $decemberData = $monthlyData->where('month', 12)->first();

        return $header->year < $currentYear &&
               $decemberData &&
               $decemberData->status_risiko == 'open' &&
               $decemberData->is_finalize;
    }


    if (!function_exists('generate_monthly_data')) {
    /**
     * Generate monthly data for a risk header
     *
     * @param $riskHeader
     */
    function generate_monthly_data($riskHeader) {
        $year = $riskHeader->year;
        $currentMonth = \Carbon\Carbon::now()->month;
        $currentYear = \Carbon\Carbon::now()->year;

        for ($month = 1; $month <= 12; $month++) {
            $status = ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) ? 'close' : 'open';
            $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

            \App\Models\TrRiskMonthly::create([
                'header_id' => $riskHeader->id,
                'risk_code' => $riskHeader->risk_code,
                'process_code' => $riskHeader->process_code,
                'month' => $month,
                'status_risiko' => $status,
                'start_date' => $startDate->toDateString(),
                'expired_date' => $endDate->toDateString(),
                'rr_level_dampak' => $riskHeader->residual_target_level_dampak,
                'rr_level_kemungkinan' => $riskHeader->residual_target_level_kemungkinan,
                'rr_posisi_risiko' => $riskHeader->residual_target_posisi_risiko,
                'rr_level_risiko' => $riskHeader->residual_target_level_risiko,
                'is_finalize' => false,
            ]);
        }
    }
}

}

