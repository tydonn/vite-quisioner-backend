<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class QuestionProdi extends Model
{
    protected $connection = 'quisioner';

    protected $table = 'dk_tbl_question_prodi';

    protected $fillable = [
        'AspectID',
        'ProdiID',
        'created_by',
    ];
}
