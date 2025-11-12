<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MstEmailDomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domains = [
            ['domain' => 'kbn.co.id', 'status' => 1],
            ['domain' => 'gmail.com', 'status' => 1],
        ];

        foreach ($domains as $domain) {
            DB::table('mst_email_domains')->insert([
                'domain' => $domain['domain'],
                'status' => $domain['status'],
                'created_by' => 1, // Assuming admin user has id 1
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
