<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionProdiGlobalSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        for ($aspectId = 1; $aspectId <= 23; $aspectId++) {
            $rows[] = [
                'AspectID' => $aspectId,
                'ProdiID' => '999999',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('quisioner')
            ->table('dk_tbl_question_prodi')
            ->upsert($rows, ['AspectID', 'ProdiID'], ['updated_at']);
    }
}
