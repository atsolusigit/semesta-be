<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\TrRiskInvestasi;

class ErkapRiskProfileService
{
    public function fetchAndSync(int $tahun): int
    {
        $base = rtrim(config('services.erkap.base_url', env('ERKAP_BASE_URL', '')), '/');
        $auth = config('services.erkap.basic_auth', env('ERKAP_BASIC_AUTH', ''));

        $resp = Http::retry(3, 300)->timeout(25)
            ->withHeaders(['Authorization' => $auth])
            ->get("$base/api/semesta/capex-risk-profile", ['tahun' => $tahun])
            ->throw()
            ->json();

        if (!is_array($resp) || ($resp['success'] ?? false) !== true) {
            return 0;
        }

        $count = 0;

        foreach ($resp['result'] ?? [] as $row) {
            DB::transaction(function () use ($row, $tahun, &$count) {

                $erkapId  = (int) ($row['cpx_kegiatan_id'] ?? 0);
                if (!$erkapId) return;

                $listRisk = $row['list_risk'] ?? [];
                $first    = is_array($listRisk) && count($listRisk) ? $listRisk[0] : null;

                $payload = [
                    'erkap_id'           => $erkapId,
                    'tahun'              => $tahun,
                    'unit_kerja_id'      => $row['unit_kerja_id'] ?? null,
                    'unit_kerja_nama'    => $row['unit_kerja_nama'] ?? null,
                    'nilai'              => $row['nilai'] ?? null,
                    'with_sub_pekerjaan' => (bool) ($row['with_sub_pekerjaan'] ?? null),

                    'kategori_risiko'    => $first['risk_kategori'] ?? null,
                    'risk_kategori_id'   => $first['risk_kategori_id'] ?? null,
                    'sasaran'            => $first['risk_sasaran'] ?? null,
                    'peristiwa_risiko'   => $first['risk_peristiwa'] ?? null,
                    'penyebab_risiko'    => $first['risk_sebab'] ?? null,    

                    'dampak_risiko_awal' => $first['risk_awal_dampak'] ?? null,
                    'kemungkinan_awal'   => $first['risk_awal_kemungkinan'] ?? null,
                    'eksposure_level_awal'=> $first['risk_awal_exp_level'] ?? null,
                    'eksposure_ltmh_awal' => $first['risk_awal_exp_kode'] ?? null, 

                    'dampak_risiko_akhir' => $first['risk_akhir_dampak'] ?? null,
                    'kemungkinan_akhir'   => $first['risk_akhir_kemungkinan'] ?? null,
                    'eksposure_level_akhir'=> $first['risk_akhir_exp_level'] ?? null,
                    'eksposure_ltmh_akhir' => $first['risk_akhir_exp_kode'] ?? null,

                    'eksposure_kode_awal'  => $first['risk_awal_exp_kode']  ?? null,
                    'eksposure_color_awal' => $first['risk_awal_exp_color'] ?? null,
                    'eksposure_kode_akhir' => $first['risk_akhir_exp_kode'] ?? null,
                    'eksposure_color_akhir'=> $first['risk_akhir_exp_color']?? null,

                    'capex_sub_id'       => $first['capex_sub_id'] ?? null,
                    'nama_sub_pekerjaan' => $first['nama_sub_pekerjaan'] ?? null,

                    'erkap_list_risk_json'=> $listRisk ?: null,
                    'synced_at'            => now(),
                ];

                $record = TrRiskInvestasi::query()
                    ->where('erkap_id', $erkapId)
                    ->where('tahun', $tahun)
                    ->first();

                if ($record) {
                    $record->fill($payload);
                    $record->save();
                } else {
                    TrRiskInvestasi::create($payload);
                }

                $count++;
            });
        }

        return $count;
    }
}
