<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class ChoiceType extends Model
{
    //
    protected $connection = 'quisioner';
    protected $table = 'dk_tbl_quisioner_choice_type';
    protected $primaryKey = 'ChoiceTypeID';

    public $timestamps = false;

    protected $fillable = [
        'TypeCode',
        'TypeName',
        'Description',
        'IsActive',
    ];
}
