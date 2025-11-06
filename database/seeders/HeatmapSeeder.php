<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HeatmapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Seed mst_heatmap_dampak
        $dampakData = [
            [
                'id' => 1,
                'dampak' => 1,
                'label' => 'Sangat Rendah',
                'created_at' => '2025-07-17 04:32:41',
                'updated_at' => '2025-08-19 03:33:08'
            ],
            [
                'id' => 2,
                'dampak' => 2,
                'label' => 'Rendah',
                'created_at' => '2025-07-17 04:33:01',
                'updated_at' => '2025-08-19 03:33:14'
            ],
            [
                'id' => 3,
                'dampak' => 3,
                'label' => 'Sedang',
                'created_at' => '2025-07-17 04:33:13',
                'updated_at' => '2025-09-04 07:36:07'
            ],
            [
                'id' => 4,
                'dampak' => 4,
                'label' => 'Tinggi',
                'created_at' => '2025-08-12 02:47:24',
                'updated_at' => '2025-09-04 07:36:12'
            ],
            [
                'id' => 5,
                'dampak' => 5,
                'label' => 'Sangat Tinggi',
                'created_at' => '2025-08-12 02:47:11',
                'updated_at' => '2025-08-19 03:33:01'
            ],
        ];

        DB::table('mst_heatmap_dampak')->insert($dampakData);

        // Seed mst_heatmap_kemungkinan
        $kemungkinanData = [
            [
                'id' => 1,
                'kemungkinan' => 1,
                'label' => 'Sangat Jarang Terjadi',
                'created_at' => '2025-07-17 04:33:30',
                'updated_at' => '2025-07-17 06:45:36'
            ],
            [
                'id' => 2,
                'kemungkinan' => 2,
                'label' => 'Jarang Terjadi',
                'created_at' => '2025-07-17 04:33:37',
                'updated_at' => '2025-07-17 04:33:37'
            ],
            [
                'id' => 3,
                'kemungkinan' => 3,
                'label' => 'Bisa Terjadi',
                'created_at' => '2025-07-17 04:33:50',
                'updated_at' => '2025-07-17 04:33:50'
            ],
            [
                'id' => 4,
                'kemungkinan' => 4,
                'label' => 'Sangat mungkin Terjadi',
                'created_at' => '2025-07-17 04:34:01',
                'updated_at' => '2025-07-17 04:34:01'
            ],
            [
                'id' => 5,
                'kemungkinan' => 5,
                'label' => 'Hampir Pasti Terjadi',
                'created_at' => '2025-08-12 02:50:29',
                'updated_at' => '2025-10-22 10:03:03'
            ],
        ];

        DB::table('mst_heatmap_kemungkinan')->insert($kemungkinanData);

        // Seed mst_heatmap
        $heatmapData = [
            ['id' => 1, 'dampak' => 1, 'kemungkinan' => 1, 'result' => 1, 'created_at' => '2025-08-12 13:40:54', 'updated_at' => null],
            ['id' => 2, 'dampak' => 1, 'kemungkinan' => 2, 'result' => 2, 'created_at' => '2025-08-12 13:40:53', 'updated_at' => null],
            ['id' => 3, 'dampak' => 1, 'kemungkinan' => 3, 'result' => 3, 'created_at' => '2025-08-12 13:40:51', 'updated_at' => null],
            ['id' => 4, 'dampak' => 1, 'kemungkinan' => 4, 'result' => 4, 'created_at' => '2025-08-12 13:40:47', 'updated_at' => null],
            ['id' => 5, 'dampak' => 1, 'kemungkinan' => 5, 'result' => 7, 'created_at' => '2025-08-12 13:42:02', 'updated_at' => null],
            ['id' => 6, 'dampak' => 2, 'kemungkinan' => 1, 'result' => 5, 'created_at' => '2025-08-12 13:42:00', 'updated_at' => null],
            ['id' => 7, 'dampak' => 2, 'kemungkinan' => 2, 'result' => 6, 'created_at' => '2025-08-12 13:42:30', 'updated_at' => null],
            ['id' => 8, 'dampak' => 2, 'kemungkinan' => 3, 'result' => 8, 'created_at' => '2025-08-12 13:42:52', 'updated_at' => null],
            ['id' => 9, 'dampak' => 2, 'kemungkinan' => 4, 'result' => 9, 'created_at' => '2025-08-12 13:43:20', 'updated_at' => null],
            ['id' => 10, 'dampak' => 2, 'kemungkinan' => 5, 'result' => 12, 'created_at' => '2025-08-12 13:43:44', 'updated_at' => null],
            ['id' => 11, 'dampak' => 3, 'kemungkinan' => 1, 'result' => 10, 'created_at' => '2025-08-12 13:44:04', 'updated_at' => null],
            ['id' => 12, 'dampak' => 3, 'kemungkinan' => 2, 'result' => 11, 'created_at' => '2025-08-12 13:44:18', 'updated_at' => null],
            ['id' => 13, 'dampak' => 3, 'kemungkinan' => 3, 'result' => 13, 'created_at' => '2025-08-12 13:44:34', 'updated_at' => null],
            ['id' => 14, 'dampak' => 3, 'kemungkinan' => 4, 'result' => 14, 'created_at' => '2025-08-12 13:44:55', 'updated_at' => null],
            ['id' => 15, 'dampak' => 3, 'kemungkinan' => 5, 'result' => 17, 'created_at' => '2025-08-12 13:45:18', 'updated_at' => null],
            ['id' => 16, 'dampak' => 4, 'kemungkinan' => 1, 'result' => 15, 'created_at' => '2025-08-12 13:45:39', 'updated_at' => null],
            ['id' => 17, 'dampak' => 4, 'kemungkinan' => 2, 'result' => 16, 'created_at' => '2025-08-12 13:46:07', 'updated_at' => null],
            ['id' => 18, 'dampak' => 4, 'kemungkinan' => 3, 'result' => 18, 'created_at' => '2025-08-12 13:46:33', 'updated_at' => null],
            ['id' => 19, 'dampak' => 4, 'kemungkinan' => 4, 'result' => 19, 'created_at' => '2025-08-12 13:46:58', 'updated_at' => null],
            ['id' => 20, 'dampak' => 4, 'kemungkinan' => 5, 'result' => 22, 'created_at' => '2025-08-12 13:47:14', 'updated_at' => null],
            ['id' => 21, 'dampak' => 5, 'kemungkinan' => 1, 'result' => 20, 'created_at' => '2025-08-12 13:47:32', 'updated_at' => null],
            ['id' => 22, 'dampak' => 5, 'kemungkinan' => 2, 'result' => 21, 'created_at' => '2025-08-12 13:47:48', 'updated_at' => null],
            ['id' => 23, 'dampak' => 5, 'kemungkinan' => 3, 'result' => 23, 'created_at' => '2025-08-12 13:48:04', 'updated_at' => null],
            ['id' => 24, 'dampak' => 5, 'kemungkinan' => 4, 'result' => 24, 'created_at' => '2025-08-12 13:48:20', 'updated_at' => null],
            ['id' => 27, 'dampak' => 5, 'kemungkinan' => 5, 'result' => 25, 'created_at' => '2025-10-31 06:30:02', 'updated_at' => '2025-10-31 06:30:02'],
        ];

        DB::table('mst_heatmap')->insert($heatmapData);

        $this->command->info('Heatmap data seeded successfully!');
    }
}
