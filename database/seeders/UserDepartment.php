<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\MstDepartment;

class UserDepartment extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
<<<<<<< HEAD
        $now = Carbon::now()->toDateTimeString();



        DB::table('mst_role')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name'       => $role['name'],
                    'created_by' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
=======
        // $now = Carbon::now()->toDateTimeString();



        // DB::table('mst_role')->updateOrInsert(
        //         ['id' => $role['id']],
        //         [
        //             'name'       => $role['name'],
        //             'created_by' => 1,
        //             'created_at' => $now,
        //             'updated_at' => $now,
        //         ]
        //     );
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    }
}
