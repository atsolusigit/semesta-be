<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\RencanaInvestasi;

class ErkapSyncService
{
    public function fetchAndSync(int $tahun, int $bulan, int $week): int
    {
        $base = rtrim(config('services.erkap.base_url', env('ERKAP_BASE_URL', '')), '/');
        $auth = config('services.erkap.basic_auth', env('ERKAP_BASIC_AUTH', ''));

        if (empty($base) || empty($auth)) {
            throw new \RuntimeException('ERKAP_BASE_URL or ERKAP_BASIC_AUTH is not set.');
        }

        $resp = Http::retry(3, 250)
            ->timeout(20)
            ->withHeaders(['Authorization' => $auth])
            ->get("$base/api/semesta/capex-monitor", [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'week'  => $week,
            ])
            ->throw()
            ->json();

        if (!is_array($resp) || ($resp['success'] ?? false) !== true) {
            return 0;
        }

        $count = 0;

        foreach ($resp['result'] ?? [] as $item) {
            DB::transaction(function () use ($item, &$count) {
                RencanaInvestasi::updateOrCreate(
                    ['erkap_id' => $item['capex_id'], 'year' => $item['tahun']],
                    [
                        'department_name'       => $item['unit_kerja_nama'] ?? null,
                        'nama_investasi'        => $item['nama_pekerjaan'] ?? null,
                        'kategori_investasi'    => $item['kategori_nama'] ?? null,
                        'jenis_investasi'       => $item['jenis_investasi'] ?? null,
                        'unit_kerja_id'         => $item['unit_kerja_id'] ?? null,
                        'nilai_rkap'            => $item['nilai_rkap'] ?? null,
                        'nilai_revisi'          => $item['nilai_revisi'] ?? null,
                        'nilai_budget_transfer' => $item['nilai_transfer'] ?? null,
                        'nilai_realisasi'       => $item['nilai_realisasi_keuangan'] ?? null,
                        'keterangan'            => $item['keterangan_revisi'] ?? '',

                        // Risk ringkas (ambil yang pertama jika ada)
                        'dampak_inherent' => data_get($item, 'list_risk.0.risk.0.risk_awal_dampak'),
                        'ld_inherent'     => data_get($item, 'list_risk.0.risk.0.risk_awal_exp_kode'),
                        'dampak_current'  => data_get($item, 'list_risk.0.risk.0.risk_akhir_dampak'),
                        'ld_current'      => data_get($item, 'list_risk.0.risk.0.risk_akhir_exp_kode'),

                        'updated_at' => now(),
                    ]
                );

                $count++;
            });
        }

        return $count;
    }
}
