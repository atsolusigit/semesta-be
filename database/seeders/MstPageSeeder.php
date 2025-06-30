<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MstPageSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now()->toDateTimeString();

        $pages = [
            ['id' => 1, 'name' => 'App Setting',         'head_url' => '/app-setting'],
            ['id' => 2, 'name' => 'Reporting (Lihat)',  'head_url' => '/reporting/lihat'],
            ['id' => 3, 'name' => 'Reporting (Buat)',   'head_url' => '/reporting/buat'],
            ['id' => 4, 'name' => 'Reporting (Editor)', 'head_url' => '/reporting/editor'],
            ['id' => 5, 'name' => 'User Access',        'head_url' => '/user-access'],
            ['id' => 6, 'name' => 'Backdoor',           'head_url' => '/backdoor'],
        ];

        foreach ($pages as $p) {
            DB::table('mst_page')->updateOrInsert(
                ['id' => $p['id']],
                [
                    'name'       => $p['name'],
                    'head_url'   => $p['head_url'],
                    'is_web'     => 1,
                    'is_mobile'  => in_array($p['id'], [2,3]) ? 1 : 0,
                    'status'     => 1,
                    'created_by' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
