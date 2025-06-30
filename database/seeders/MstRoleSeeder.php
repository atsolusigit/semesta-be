<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MstRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now()->toDateTimeString();

        $roles = [
            ['id' => 1, 'name' => 'Super Admin'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'User'],
        ];

        foreach ($roles as $role) {
            DB::table('mst_role')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name'       => $role['name'],
                    'created_by' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
