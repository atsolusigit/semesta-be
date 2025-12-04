<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MstHeatmap;
use App\Models\TrRiskMonthlyUpload;
use App\Models\MstRole;
use App\Models\RoleApprovalFlow;

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
                'message' => 'validasi gagal',
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

if (!function_exists('clean_monthly_data')) {
    /**
     * Membersihkan data bulanan dari field tidak relevan dan null
     *
     * @param array $monthlyData - Single monthly data array
     * @return array
     */
    function clean_monthly_data(array $monthlyData)
    {
        return collect($monthlyData)
            ->except(['target_option_position', 'realization_option_position'])
            ->filter(fn($value) => !is_null($value))
            ->toArray();
    }
}

if (!function_exists('generate_risk_monthly_dates')) {
    /**
     * Generate start_date dan expired_date untuk risk monthly berdasarkan year dan month
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    function generate_risk_monthly_dates($year, $month)
    {
        return [
            'start_date' => Carbon::create($year, $month, 1)->startOfMonth()->format('Y-m-d'),
            'expired_date' => Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d')
        ];
    }
}

if (!function_exists('validate_risk_monthly_dates')) {
    /**
     * Validasi tanggal untuk risk monthly
     *
     * @param object $request
     * @param int $year
     * @param int $month
     * @return array
     */
    function validate_risk_monthly_dates($request, $year, $month)
    {
        if (!$request->start_date && !$request->expired_date) {
            return ['valid' => true, 'message' => 'Valid - dates will be auto-generated'];
        }

        $expectedStartDate = Carbon::create($year, $month, 1)->startOfMonth();
        $expectedEndDate = Carbon::create($year, $month, 1)->endOfMonth();

        if ($request->start_date) {
            $inputStartDate = Carbon::parse($request->start_date)->startOfMonth();
            if (!$inputStartDate->isSameMonth($expectedStartDate)) {
                return [
                    'valid' => false,
                    'message' => 'Start date harus dalam bulan ' . $expectedStartDate->format('F Y')
                ];
            }
        }

        if ($request->expired_date) {
            $inputEndDate = Carbon::parse($request->expired_date)->endOfMonth();
            if (!$inputEndDate->isSameMonth($expectedEndDate)) {
                return [
                    'valid' => false,
                    'message' => 'Expired date harus dalam bulan ' . $expectedEndDate->format('F Y')
                ];
            }
        }

        return ['valid' => true, 'message' => 'Valid'];
    }
}

if (!function_exists('should_process_yearly_residual_risk')) {
    /**
     * Cek apakah yearly residual risk fields harus diproses
     *
     * @param object $request
     * @return bool
     */
    function should_process_yearly_residual_risk($request)
    {
        return !empty($request->residual_risk_satutahun_level_dampak) &&
               !empty($request->residual_risk_satutahun_level_kemungkinan);
    }
}

if (!function_exists('process_yearly_residual_risk')) {
    /**
     * Proses yearly residual risk calculation
     *
     * @param object $request
     * @return array|null
     */
    function process_yearly_residual_risk($request)
    {
        $residualRiskSatutahunHeatmap = MstHeatmap::with('riskRange')
            ->where('dampak', $request->residual_risk_satutahun_level_dampak)
            ->where('kemungkinan', $request->residual_risk_satutahun_level_kemungkinan)
            ->first();

        if ($residualRiskSatutahunHeatmap) {
            return [
                'residual_risk_satutahun_level_dampak' => $request->residual_risk_satutahun_level_dampak,
                'residual_risk_satutahun_level_kemungkinan' => $request->residual_risk_satutahun_level_kemungkinan,
                'residual_risk_satutahun_posisi_risiko' => $residualRiskSatutahunHeatmap->result,
                'residual_risk_satutahun_level_risiko' => $residualRiskSatutahunHeatmap->riskRange->name ?? null,
            ];
        }

        return null;
    }
}

<<<<<<< HEAD
if (!function_exists('process_risk_monthly_file_uploads')) {
    /**
     * Proses file uploads untuk risk monthly
     *
     * @param array $uploadedFiles
     * @param object $monthly
     * @return void
     */
=======
if (!function_exists('process_lost_event_file_uploads')) {

    /**
     * Simpan file yang sudah diupload ke tabel lost_event_uploads
     *
     * @param array $uploadedFiles
     * @param object $lostEvent
     * @return void
     */
    function process_lost_event_file_uploads($uploadedFiles, $lostEvent)
    {
        if (!is_array($uploadedFiles)) {
            return;
        }

        foreach ($uploadedFiles as $file) {

            // Pastikan format benar
            if (
                !isset($file['filepath']) ||
                empty($file['filepath'])
            ) {
                continue;
            }

            \App\Models\LostEventUpload::create([
                'lost_event_id' => $lostEvent->id,
                'filepath' => $file['filepath'],
                'domain' => $file['domain'] ?? 'Lost_Event_Report_SEMUA_DEPARTMENT',
                'is_confirmed' => 1,
            ]);
        }
    }
}


if (!function_exists('process_risk_monthly_file_uploads')) {

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    function process_risk_monthly_file_uploads($uploadedFiles, $monthly)
    {
        if (!is_array($uploadedFiles)) {
            return;
        }

        foreach ($uploadedFiles as $file) {
<<<<<<< HEAD
=======

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
            if (!isset($file['filepath']) || empty($file['filepath'])) {
                continue;
            }

            TrRiskMonthlyUpload::create([
<<<<<<< HEAD
                'header_id' => $monthly->header_id,
                'risk_monthly_id' => $monthly->id,
                'filepath' => $file['filepath'],
                'domain' => $file['domain'] ?? basename($file['filepath']),
                'is_confirmed' => true,
=======
                'header_id'        => $monthly->header_id,
                'risk_monthly_id'  => $monthly->id,
                'filepath'         => $file['filepath'],  // base64 tersimpan
                'domain'           => $file['domain'] ?? 'dokumen',
                'is_confirmed'     => true,
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
            ]);
        }
    }
}

if (!function_exists('process_risk_monthly_entry_file_uploads')) {
    /**
     * Proses file uploads untuk risk monthly entry
     *
     * @param array $uploadedFiles
     * @param object $entry
     * @return void
     */
    function process_risk_monthly_entry_file_uploads($uploadedFiles, $entry)
    {
        if (!is_array($uploadedFiles)) {
            return;
        }

        foreach ($uploadedFiles as $file) {
            if (!isset($file['filepath']) || empty($file['filepath'])) {
                continue;
            }

                \App\Models\TrRiskMonthlyUpload::create([
                'header_id' => $entry->header_id,
                'risk_monthly_id' => $entry->monthly_id,
                'risk_monthly_entry_id' => $entry->id,
                'filepath' => $file['filepath'],
                'domain' => $file['domain'] ?? basename($file['filepath']),
                'is_confirmed' => true,
            ]);
        }
    }
}


if (!function_exists('validate_bulk_quantitative_data')) {
    /**
     * Validasi data kuantitatif untuk bulk update
     *
     * @param array $monthlyData
     * @param bool $hasMonthField
     * @param int $headerId
     * @return array
     */
    function validate_bulk_quantitative_data($monthlyData, $hasMonthField, $headerId)
    {
        foreach ($monthlyData as $index => $monthData) {
            // Cek target_quantitative null atau kosong
            if (array_key_exists('target_quantitative', $monthData) && is_null($monthData['target_quantitative'])) {
                return [
                    'valid' => false,
<<<<<<< HEAD
                    'title' => 'Target Quantitative Tidak Boleh Kosong',
                    'message' => "Data pada index {$index} memiliki kuantitatif target yang kosong. Mohon isi dengan nilai yang valid.",
=======
                    'title' => 'Data Tidak Boleh Kosong',
                    'message' => "Ada data yang masih kosong. Mohon mengisi data dengan benar.",
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
                    'data' => [
                        'header_id' => $headerId,
                        'invalid_index' => $index,
                        'month' => $hasMonthField ? ($monthData['month'] ?? 'unknown') : ($index + 1),
                        'error_field' => 'target_quantitative',
                        'current_value' => $monthData['target_quantitative']
                    ]
                ];
            }

            // Validasi target_quantitative harus berupa string atau numeric
            if (isset($monthData['target_quantitative']) && !is_null($monthData['target_quantitative'])) {
                if (!is_string($monthData['target_quantitative']) && !is_numeric($monthData['target_quantitative'])) {
                    return [
                        'valid' => false,
                        'title' => 'Target Quantitative Tidak Valid',
                        'message' => "Data pada index {$index} memiliki target_quantitative yang tidak valid. Harus berupa string atau angka.",
                        'data' => [
                            'header_id' => $headerId,
                            'invalid_index' => $index,
                            'month' => $hasMonthField ? ($monthData['month'] ?? 'unknown') : ($index + 1),
                            'error_field' => 'target_quantitative',
                            'current_value' => $monthData['target_quantitative']
                        ]
                    ];
                }

                // Cek jika string kosong
                if (is_string($monthData['target_quantitative']) && trim($monthData['target_quantitative']) === '') {
                    return [
                        'valid' => false,
                        'title' => 'Target Quantitative Tidak Boleh Kosong',
                        'message' => "Data pada index {$index} memiliki target_quantitative yang kosong.",
                        'data' => [
                            'header_id' => $headerId,
                            'invalid_index' => $index,
                            'month' => $hasMonthField ? ($monthData['month'] ?? 'unknown') : ($index + 1),
                            'error_field' => 'target_quantitative',
                            'current_value' => $monthData['target_quantitative']
                        ]
                    ];
                }
            }
        }

        return ['valid' => true];
    }
}

if (!function_exists('process_bulk_monthly_data')) {
    /**
     * Proses data bulanan untuk bulk update
     *
     * @param array $monthlyData
     * @param bool $hasMonthField
     * @return array
     */
    function process_bulk_monthly_data($monthlyData, $hasMonthField)
    {
        $processedMonthlyData = [];

        if ($hasMonthField) {
            $processedMonthlyData = $monthlyData;
        } else {
            foreach ($monthlyData as $index => $monthData) {
                $processedMonthlyData[] = array_merge(['month' => $index + 1], $monthData);
            }
        }

        $requestedMonths = collect($processedMonthlyData)->pluck('month')->toArray();

        return [
            'monthly_data' => $processedMonthlyData,
            'requested_months' => $requestedMonths,
            'mode' => $hasMonthField ? 'explicit_month' : 'sequential_month'
        ];
    }
}

if (!function_exists('validate_bulk_monthly_constraints')) {
    /**
     * Validasi constraints untuk bulk monthly update
     *
     * @param array $processedMonthlyData
     * @param object $existingMonthly
     * @param bool $requireAllMonths
     * @param int $headerId
<<<<<<< HEAD
     * @return array
     */
    function validate_bulk_monthly_constraints($processedMonthlyData, $existingMonthly, $requireAllMonths, $headerId)
=======
     * @param bool $bypassFinalization - parameter baru untuk bypass pengecekan finalisasi
     * @return array
     */
    function validate_bulk_monthly_constraints($processedMonthlyData, $existingMonthly, $requireAllMonths, $headerId, $bypassFinalization = false)
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    {
        $requestedMonths = collect($processedMonthlyData)->pluck('month')->toArray();
        $warnings = [];

<<<<<<< HEAD
        // Check finalization
        $finalizedMonths = [];
        foreach ($processedMonthlyData as $monthData) {
            $month = $monthData['month'];
            if (isset($existingMonthly[$month]) && $existingMonthly[$month]->is_finalize) {
                $finalizedMonths[] = $month;
            }
        }

        if (!empty($finalizedMonths)) {
            return [
                'valid' => false,
                'title' => 'Data Sudah Difinalisasi',
                'message' => 'Bulan berikut sudah difinalisasi dan tidak bisa diubah: ' . implode(', ', $finalizedMonths),
                'data' => [
                    'header_id' => $headerId,
                    'finalized_months' => $finalizedMonths
                ]
            ];
=======
        // Check finalization - bisa di-bypass oleh role tertentu
        if (!$bypassFinalization) {
            $finalizedMonths = [];
            foreach ($processedMonthlyData as $monthData) {
                $month = $monthData['month'];
                if (isset($existingMonthly[$month]) && $existingMonthly[$month]->is_finalize) {
                    $finalizedMonths[] = $month;
                }
            }

            if (!empty($finalizedMonths)) {
                return [
                    'valid' => false,
                    'title' => 'Data Sudah Difinalisasi',
                    'message' => 'Bulan berikut sudah difinalisasi dan tidak bisa diubah: ' . implode(', ', $finalizedMonths),
                    'data' => [
                        'header_id' => $headerId,
                        'finalized_months' => $finalizedMonths
                    ]
                ];
            }
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        }

        // Check for duplicate months
        if (count($requestedMonths) !== count(array_unique($requestedMonths))) {
            return [
                'valid' => false,
                'title' => 'Duplikasi Bulan',
                'message' => 'Terdapat duplikasi bulan dalam data yang dikirim.',
                'data' => [
                    'header_id' => $headerId,
                    'requested_months' => $requestedMonths
                ]
            ];
        }

        // Validate completeness if required
        if ($requireAllMonths === true) {
            $allMonths = range(1, 12);
            $missingMonths = array_diff($allMonths, $requestedMonths);

            if (!empty($missingMonths)) {
                return [
                    'valid' => false,
                    'title' => 'Data Tidak Lengkap',
                    'message' => 'Semua bulan (1-12) harus diisi. Bulan yang belum diisi: ' . implode(', ', $missingMonths),
                    'data' => [
                        'header_id' => $headerId,
                        'missing_months' => $missingMonths,
                        'provided_months' => $requestedMonths
                    ]
                ];
            }
        }

        // Generate warnings for incomplete data
        if (count($requestedMonths) < 12 && $requireAllMonths !== true) {
            $allMonths = range(1, 12);
            $missingMonths = array_diff($allMonths, $requestedMonths);
            $warnings[] = 'Peringatan: Hanya ' . count($requestedMonths) . ' dari 12 bulan yang akan diupdate. Bulan yang tidak diupdate: ' . implode(', ', $missingMonths);
        }

        return [
            'valid' => true,
            'warnings' => $warnings
        ];
    }
}

if (!function_exists('validate_header_year')) {
    /**
     * Validasi tahun header
     *
     * @param int $year
     * @return bool
     */
    function validate_header_year($year)
    {
        return $year && $year >= 1900 && $year <= 2100;
    }
}

if (!function_exists('execute_bulk_quantitative_update')) {
    /**
     * Eksekusi bulk update kuantitatif
     *
     * @param array $processedMonthlyData
     * @param object $existingMonthly
     * @param object $header
     * @param string $updateMode
     * @param int $userId
     * @return array
     */
    function execute_bulk_quantitative_update($processedMonthlyData, $existingMonthly, $header, $updateMode, $userId)
    {
        $updatedData = [];
        $createdData = [];

        foreach ($processedMonthlyData as $monthData) {
            $month = $monthData['month'];

            if (isset($existingMonthly[$month])) {
                // Update existing data
                $monthly = $existingMonthly[$month];

                if ($updateMode === 'selective') {
                    $updateData = [];

                    if (array_key_exists('target_quantitative', $monthData)) {
                        $updateData['target_quantitative'] = $monthData['target_quantitative'];
                    }

                    if (array_key_exists('target_notes', $monthData)) {
                        $updateData['target_notes'] = $monthData['target_notes'];
                    }

                    // PERBAIKAN: Tambahkan handling untuk target_kualitatif
                    if (array_key_exists('target_kualitatif', $monthData)) {
                        $updateData['target_kualitatif'] = $monthData['target_kualitatif'];
                    }

                    if (!empty($updateData)) {
                        $updateData['updated_by'] = $userId;
                        $monthly->update($updateData);
                    }
                } else {
                    // Complete mode: update all available fields
                    $updateFields = [
                        'target_quantitative' => $monthData['target_quantitative'],
                        'target_notes' => $monthData['target_notes'] ?? null,
                        'updated_by' => $userId,
                    ];

                    // PERBAIKAN: Tambahkan target_kualitatif jika ada
                    if (array_key_exists('target_kualitatif', $monthData)) {
                        $updateFields['target_kualitatif'] = $monthData['target_kualitatif'];
                    }

                    $monthly->update($updateFields);
                }

                $monthly->load('header');
                $result = clean_monthly_data($monthly->toArray());
                $updatedData[] = $result;

            } else {
                // Create new data with auto-generated dates
                $autoGeneratedDates = generate_risk_monthly_dates($header->year, $month);

                $createData = [
                    'header_id' => $header->id,
                    'month' => $month,
                    'target_quantitative' => $monthData['target_quantitative'],
                    'target_notes' => $monthData['target_notes'] ?? null,
                    'is_finalize' => false,
                    'status_risiko' => 'open',
                    'start_date' => $autoGeneratedDates['start_date'],
                    'expired_date' => $autoGeneratedDates['expired_date'],
                    'created_by' => $userId,
                ];

                // PERBAIKAN: Tambahkan target_kualitatif jika ada
                if (array_key_exists('target_kualitatif', $monthData)) {
                    $createData['target_kualitatif'] = $monthData['target_kualitatif'];
                }

                $monthly = \App\Models\TrRiskMonthly::create($createData);
                $monthly->load('header');
                $result = clean_monthly_data($monthly->toArray());
                $createdData[] = $result;
            }
        }

        return [
            'header_id' => $header->id,
            'updated_count' => count($updatedData),
            'created_count' => count($createdData),
            'updated_data' => $updatedData,
            'created_data' => $createdData,
            'total_processed' => count($updatedData) + count($createdData),
            'update_mode' => $updateMode
        ];
    }
}

if (!function_exists('get_color_by_position')) {
    /**
     * Get heatmap color by risk position.
     *
     * @param int|null $position
     * @return string|null
     */
    function get_color_by_position($position)
    {
        if (!$position) {
            return null;
        }

        $riskRange = \App\Models\MstHeatmapRiskRange::where('start', '<=', $position)
            ->where('end', '>=', $position)
            ->first();

        return $riskRange ? $riskRange->color : null;
    }
}

if (!function_exists('clean_string')) {
    /**
     * Bersihkan string dari karakter tidak valid agar aman di JSON dan encoding UTF-8.
     *
     * @param mixed $string
     * @return mixed
     */
    function clean_string($string)
    {
        if (!is_string($string)) return $string;

        // Hapus karakter kontrol yang tidak diinginkan
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);

        // Pastikan encoding UTF-8 valid
        if (!mb_check_encoding($string, 'UTF-8')) {
            $detected = mb_detect_encoding($string, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($detected !== false) {
                $string = mb_convert_encoding($string, 'UTF-8', $detected);
            } else {
                // Remove byte sequences yang invalid
                $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);
            }
        }

        return trim($string);
    }
}

if (!function_exists('clean_recursive')) {
    /**
     * Bersihkan array/object secara rekursif menggunakan clean_string.
     *
     * @param mixed $data
     * @return mixed
     */
    function clean_recursive($data)
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanedKey = is_string($key) ? clean_string($key) : $key;
                $cleaned[$cleanedKey] = clean_recursive($value);
            }
            return $cleaned;
        }

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return clean_recursive($data->toArray());
            }

            $cleaned = new \stdClass();
            foreach (get_object_vars($data) as $key => $value) {
                $cleanedKey = clean_string($key);
                $cleaned->$cleanedKey = clean_recursive($value);
            }
            return $cleaned;
        }

        if (is_string($data)) {
            return clean_string($data);
        }

        return $data;
    }
}

if (!function_exists('get_decrypted_username')) {
    /**
     * Ambil dan dekripsi username dari objek user, fallback ke 'Unknown User' jika gagal.
     *
     * @param object|null $userObject
     * @return string
     */
    function get_decrypted_username($userObject)
    {
        if (!$userObject || !isset($userObject->username) || !isset($userObject->id)) {
            return 'Unknown User';
        }

        try {
            $decryptedRaw = encrypt_decrypt_db('dec', $userObject->username, $userObject->id);
            if (is_string($decryptedRaw) && !empty(trim($decryptedRaw))) {
                $cleaned = clean_string($decryptedRaw);
                if (!empty($cleaned)) {
                    return $cleaned;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("Error decrypt username for user ID {$userObject->id}: " . $e->getMessage());
        }

        return 'Unknown User';
    }
}

if (!function_exists('get_decrypted_name')) {
    function get_decrypted_name($userObject)
    {
        if (!$userObject || empty($userObject->id)) {
            return 'User Tidak diketahui';
        }

        try {
            $row = \DB::select("
                SELECT CAST(AES_DECRYPT(name, CONCAT('SM', ?)) AS CHAR) as result
                FROM users WHERE id = ? LIMIT 1
            ", [$userObject->id, $userObject->id]);

            if ($row && !empty($row[0]->result)) {
                return clean_string($row[0]->result);
            }
        } catch (\Throwable $e) {
            \Log::warning("Error decrypt name for user ID {$userObject->id}: " . $e->getMessage());
        }

        return 'User Tidak diketahui';
    }
}

<<<<<<< HEAD
=======
if (!function_exists('get_decrypted_email')) {
    function get_decrypted_email($userObject)
    {
        if (!$userObject || empty($userObject->id)) {
            return 'Email Tidak diketahui';
        }

        try {
            $row = \DB::select("
                SELECT CAST(AES_DECRYPT(email, CONCAT('SM', ?)) AS CHAR) as result
                FROM users WHERE id = ? LIMIT 1
            ", [$userObject->id, $userObject->id]);

            if ($row && !empty($row[0]->result)) {
                return clean_string($row[0]->result);
            }
        } catch (\Throwable $e) {
            \Log::warning("Error decrypt email for user ID {$userObject->id}: " . $e->getMessage());
        }

        return 'Email Tidak diketahui';
    }
}

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
if (!function_exists('get_month_name')) {
    /**
     * Ambil nama bulan dalam bahasa Indonesia berdasarkan nomor bulan.
     *
     * @param int $month Nomor bulan (1-12)
     * @return string Nama bulan atau string kosong jika tidak valid
     */
    function get_month_name($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$month] ?? '';
    }
}

if (!function_exists('initialize_risk_matrix')) {
    /**
     * Inisialisasi matrix 5x5 dengan nilai 0
     *
     * @return array Matrix 5x5 untuk heatmap risiko
     */
    function initialize_risk_matrix()
    {
        $matrix = [];
        for ($likelihood = 1; $likelihood <= 5; $likelihood++) {
            for ($impact = 1; $impact <= 5; $impact++) {
                $matrix[$likelihood][$impact] = 0;
            }
        }
        return $matrix;
    }
}

if (!function_exists('initialize_risk_summary')) {
    /**
     * Inisialisasi summary dengan kategori default
     *
     * @return array Summary kategori risiko dengan nilai 0
     */
    function initialize_risk_summary()
    {
        return [
            'Low' => 0,
            'Low to Moderate' => 0,
            'Moderate' => 0,
            'Moderate to High' => 0,
            'High' => 0
        ];
    }
}

if (!function_exists('format_matrix_for_response')) {
    /**
     * Format matrix untuk response yang mudah dikonsumsi frontend
     *
     * @param array $matrix Matrix 5x5 hasil perhitungan
     * @return array Formatted matrix untuk response
     */
    function format_matrix_for_response($matrix)
    {
        $formatted = [];
        foreach ($matrix as $likelihood => $impacts) {
            foreach ($impacts as $impact => $count) {
                if ($count > 0) {
                    $formatted[] = [
                        'likelihood' => $likelihood,
                        'impact' => $impact,
                        'count' => $count,
                        'position' => "{$likelihood}_{$impact}",
                        'score' => $likelihood * $impact,
                        'category' => get_risk_category_by_score($likelihood * $impact)
                    ];
                }
            }
        }
        return $formatted;
    }
}

if (!function_exists('get_risk_category_by_score')) {
    /**
     * Mendapatkan kategori risiko berdasarkan score
     *
     * @param int $score Score risiko (1-25)
     * @return string Kategori risiko
     */
    function get_risk_category_by_score($score)
    {
        if ($score >= 1 && $score <= 5) return 'Low';
        if ($score >= 6 && $score <= 10) return 'Low to Moderate';
        if ($score >= 11 && $score <= 15) return 'Moderate';
        if ($score >= 16 && $score <= 20) return 'Moderate to High';
        if ($score >= 21 && $score <= 25) return 'High';

        return 'Unknown';
    }

}
}

if (!function_exists('get_month_name')) {
    /**
     * Ambil nama bulan dalam bahasa Indonesia berdasarkan nomor bulan.
     *
     * @param int $month Nomor bulan (1-12)
     * @return string Nama bulan atau string kosong jika tidak valid
     */
    function get_month_name($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$month] ?? '';
    }

}

if (!function_exists('format_target_quantitative')) {
    /**
     * Format target quantitative value yang bisa berupa numeric atau string.
     * Jika numeric, akan diformat dengan number_format.
     * Jika string, akan dikembalikan sebagai string yang sudah di-clean.
     *
     * @param mixed $value Nilai target quantitative (numeric atau string)
     * @return string|null
     */
    function format_target_quantitative($value)
    {
        // Jika null atau empty, return null
        if (is_null($value) || $value === '') {
            return null;
        }

        // Cek apakah value adalah numeric (bisa berupa string angka)
        if (is_numeric($value)) {
            return number_format((float)$value, 0, ',', '.');
        }

        // Jika bukan numeric, kembalikan sebagai string yang sudah di-clean
        return clean_string($value);
    }
}

if (!function_exists('get_next_role_level')) {
    function get_next_role_level()
    {
        $maxLevel = MstRole::max('level') ?? 0;
        return $maxLevel + 1;
    }
}

if (!function_exists('can_user_approve_simple')) {
    function can_user_approve_simple($userId, $departmentId)
    {
        try {
            $user = \App\Models\User::find($userId);

            if (!$user) {
                return false;
            }

            // PERBAIKAN: Hanya Superadmin (1) yang selalu bisa approve
            if ($user->role_id == 1) {
                return true;
            }

            // PERBAIKAN: Role 2 (Admin) dan Role 3 hanya bisa approve dari department yang sama
            if (($user->role_id == 2 || $user->role_id == 3) && $user->department_id == $departmentId) {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            \Log::error('Error in can_user_approve_simple: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_approver_jabatan_simple')) {
    function get_approver_jabatan_simple($departmentId, $userId = null)
    {
        try {
            // Jika user_id diberikan, gunakan jabatan user tersebut
            if ($userId) {
                $user = \App\Models\User::find($userId);
                if ($user && $user->jabatan_id) {
                    return $user->jabatan_id;
                }
            }

            // Jika tidak ada jabatan user, cari jabatan di department
            $jabatan = \App\Models\MstJabatan::where('department_id', $departmentId)->first();

            return $jabatan ? $jabatan->id : null;

        } catch (\Exception $e) {
            \Log::error('Error in get_approver_jabatan_simple: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('get_approval_status_simple')) {
    function get_approval_status_simple($documentId)
    {
        try {
            $approval = \App\Models\MstApproval::where('document_id', $documentId)->first();

            if (!$approval) {
                return 'not_found';
            }

            return $approval->status; // 'pending', 'approved', 'rejected'

        } catch (\Exception $e) {
            \Log::error('Error in get_approval_status_simple: ' . $e->getMessage());
            return 'error';
        }
    }
}

if (!function_exists('has_permission')) {
    /**
     * Cek apakah user punya permission tertentu
     *
     * @param \App\Models\User $user
     * @param string $permissionName
     * @return bool
     */
    function has_permission($user, $permissionName)
    {
        if (!$user) return false;

        // Ambil semua role user
        $roles = $user->roles; // pastikan model User punya relasi roles()
        foreach ($roles as $role) {
            // Ambil semua permissions role
            foreach ($role->permissions as $perm) {
                if (strtolower($perm->name) === strtolower($permissionName)) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('check_role')) {
    /**
     * Cek apakah user punya role tertentu,
     * jika tidak langsung return JSON response standar.
     *
     * @param \App\Models\User|null $user
     * @param array|int $allowedRoles
     * @return bool|\Illuminate\Http\JsonResponse
     */
    function check_role($user, $allowedRoles)
    {
        if (!$user) {
            return response()->json([
                'status'  => false,
                'code'    => 401,
                'message' => 'Unauthorized',
                'detail'  => 'User tidak terautentikasi',
                'data'    => null
            ], 401);
        }

        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        if (!in_array($user->role_id, $allowedRoles)) {
            return response()->json([
                'status'  => false,
                'code'    => 403,
                'message' => 'Tidak Diizinkan',
                'detail'  => 'Anda tidak memiliki akses',
                'data'    => null
            ], 403);
        }

        return true;
    }
}
<<<<<<< HEAD
=======

if (!function_exists('detect_storage_disk')) {
    /**
     * Detect storage disk based on file URL
     *
     * @param string $filepath
     * @return string
     */
    function detect_storage_disk($filepath)
    {
        // DigitalOcean Spaces
        if (str_contains($filepath, 'digitaloceanspaces.com')) {
            return 'do_spaces';
        }

        // AWS S3
        if (str_contains($filepath, 's3.amazonaws.com') || str_contains($filepath, '.s3.')) {
            return 's3';
        }

        // Google Cloud Storage
        if (str_contains($filepath, 'storage.googleapis.com') || str_contains($filepath, 'storage.cloud.google.com')) {
            return 'gcs';
        }

        // Local storage
        if (str_contains($filepath, '/storage/') || !str_contains($filepath, 'http')) {
            return 'public';
        }

        // Default fallback
        return config('filesystems.default', 'public');
    }
}

if (!function_exists('extract_storage_path')) {
    /**
     * Extract relative storage path from full URL
     *
     * @param string $filepath
     * @param string|null $disk
     * @return string
     */
    function extract_storage_path($filepath, $disk = null)
    {
        // Auto-detect disk if not provided
        if ($disk === null) {
            $disk = detect_storage_disk($filepath);
        }

        // Jika sudah relative path (tidak ada http/https), return as is
        if (!str_contains($filepath, 'http://') && !str_contains($filepath, 'https://')) {
            return $filepath;
        }

        switch ($disk) {
            case 'do_spaces':
                // Extract path dari DigitalOcean Spaces URL
                // Format: https://fortisid.sgp1.digitaloceanspaces.com/semesta/filename.pdf
                $pattern = '/https?:\/\/[^\/]+\/([^?]+)/';
                if (preg_match($pattern, $filepath, $matches)) {
                    return $matches[1];
                }
                break;

            case 's3':
                // Extract path dari AWS S3 URL
                $parsed = parse_url($filepath);
                return ltrim($parsed['path'] ?? '', '/');

            case 'gcs':
                // Extract path dari Google Cloud Storage URL
                $parsed = parse_url($filepath);
                $path = ltrim($parsed['path'] ?? '', '/');
                $parts = explode('/', $path, 2);
                return $parts[1] ?? $path;

            case 'public':
                // Extract path dari local storage URL
                return str_replace([
                    config('app.url') . '/storage/',
                    url('/storage/'),
                    '/storage/'
                ], '', $filepath);

            default:
                // Fallback: try to extract anything after last domain/bucket part
                $parsed = parse_url($filepath);
                return ltrim($parsed['path'] ?? $filepath, '/');
        }

        // Fallback: return original if extraction failed
        return $filepath;
    }
}

if (!function_exists('delete_file_from_storage')) {
    /**
     * Delete file from storage safely (supports multiple storage providers)
     *
     * @param string $filepath
     * @return bool
     */
    function delete_file_from_storage($filepath)
    {
        try {
            $disk = detect_storage_disk($filepath);
            $path = extract_storage_path($filepath, $disk);

            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->delete($path);
            }

            // File not exists, consider as success
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file from storage: ' . $e->getMessage(), [
                'filepath' => $filepath
            ]);
            return false;
        }
    }
}
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
