<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\MstPageSeeder;
use Database\Seeders\MstDivisionSeeder;
use Database\Seeders\MstRoleSeeder;
use Database\Seeders\MstDepartmentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
         $this->call([
        HeatmapSeeder::class,
    ]);
}
}
