<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('quisioner')->statement(
            'ALTER TABLE dk_tbl_question_prodi MODIFY created_by VARCHAR(191) NULL'
        );
    }

    public function down(): void
    {
        DB::connection('quisioner')->statement(
            'ALTER TABLE dk_tbl_question_prodi MODIFY created_by BIGINT UNSIGNED NULL'
        );
    }
};
