<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class Choice extends Model
{
    //
    protected $connection = 'quisioner';
    protected $table = 'dk_tbl_choice';
    protected $primaryKey = 'ChoiceID';

    public $timestamps = false;

    protected $fillable = [
        'AspectID',
        'ChoiceLabel',
        'ChoiceValue',
        'SortOrder',
        'IsActive',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class, 'AspectID', 'AspectID');
    }
}
