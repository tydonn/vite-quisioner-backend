<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class Respondent extends Model
{
    //
    protected $connection = 'quisioner';
    protected $table = 'dk_tbl_respondent';
    protected $primaryKey = 'RespondentID';

    public $timestamps = true;

    protected $fillable = [
        'RespondentName',
        'LevelID'
    ];
}
