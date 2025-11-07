<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\RencanaInvestasiTimelineYear;

class ErkapTimelineService
{
    public function fetchYear(int $erkapId, int $year): RencanaInvestasiTimelineYear
    {
        $base = rtrim(config('services.erkap.base_url', env('ERKAP_BASE_URL', '')), '/');
        $auth = config('services.erkap.basic_auth', env('ERKAP_BASIC_AUTH', ''));

        $resp = Http::retry(3, 300)->timeout(25)
            ->withHeaders(['Authorization' => $auth])
            ->get("$base/api/semesta/capex-timeline", [
                'tahun'    => $year,
                'capex_id' => $erkapId,
            ])
            ->throw()
            ->json();

        $result = $resp['result'][0] ?? null;
        $timeline = $result['timeline'] ?? [];
        $hash = hash('sha256', json_encode($timeline, JSON_UNESCAPED_UNICODE));

        return RencanaInvestasiTimelineYear::updateOrCreate(
            ['erkap_id' => $erkapId, 'year' => $year],
            [
                'timeline_json' => $timeline,
                'source_hash'   => $hash,
                'synced_at'     => now(),
            ]
        );
    }
}
