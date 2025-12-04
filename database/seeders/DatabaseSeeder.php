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
<<<<<<< HEAD
        MstHeatmapDampakSeeder::class,
        MstHeatmapKemungkinanSeeder::class,
        MstHeatmapSeeder::class,
        MstHeatmapRiskRangeSeeder::class,
        MstRiskCodeSeeder::class,
        MstOptionSeeder::class,
=======
        HeatmapSeeder::class,
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
    ]);
}
}
