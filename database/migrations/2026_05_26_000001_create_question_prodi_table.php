<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('quisioner')->create('dk_tbl_question_prodi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('AspectID');
            $table->string('ProdiID', 20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['AspectID', 'ProdiID'], 'uq_question_prodi');
            $table->index('ProdiID');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::connection('quisioner')->dropIfExists('dk_tbl_question_prodi');
    }
};
