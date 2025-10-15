<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MstDivisionSeeder extends Seeder
{
    // public function run(): void
    // {
    //     $now = Carbon::now()->toDateTimeString();

    //     $divisions = [
    //         ['id' => 1, 'name' => 'Penyewaan Lahan dan Bangunan'],
    //         ['id' => 2, 'name' => 'Logistik'],
    //         ['id' => 3, 'name' => 'Pergudangan'],
    //         ['id' => 4, 'name' => 'Depo Peti kemas'],
    //         ['id' => 5, 'name' => 'Hukum'],
    //         ['id' => 6, 'name' => 'Backdoor'],
    //     ];

    //     foreach ($divisions as $d) {
    //         DB::table('mst_division')->updateOrInsert(
    //             ['id' => $d['id']],
    //             [
    //                 'name'       => $d['name'],
    //                 'created_by' => 1,
    //                 'created_at' => $now,
    //                 'updated_at' => $now,
    //             ]
    //         );
    //     }
    // }
}
