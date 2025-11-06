<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MstDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // $now = Carbon::now()->toDateTimeString();

        // $departments = [
        //     ['id' => 1, 'name' => 'Pemasaran dan Pengembangan','abbreviation' => ''],
        //     ['id' => 2, 'name' => 'Teknologi Informasi', 'abbreviation' => 'TI'],
        // ];

        // foreach ($departments as $dept) {
        //     DB::table('mst_department')->updateOrInsert(
        //         ['id' => $dept['id']],
        //         [
        //             'name'       => $dept['name'],
        //             'abbreviation' => $dept['abbreviation'],
        //             'created_by' => 1,
        //             'created_at' => $now,
        //             'updated_at' => $now,
        //         ]
        //     );
        // }
    }
}
