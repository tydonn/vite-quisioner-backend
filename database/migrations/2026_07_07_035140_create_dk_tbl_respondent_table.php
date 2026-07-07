<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('quisioner')->create('dk_tbl_respondent', function (Blueprint $table) {
            $table->increments('RespondentID', 50)->unique();
            $table->string('RespondentName', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('quisioner')->dropIfExists('dk_tbl_respondent');
    }
};
