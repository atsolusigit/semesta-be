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
