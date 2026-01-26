<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    //
    protected $connection = 'quisioner';
    protected $table = 'dk_tbl_question';
    protected $primaryKey = 'AspectID';

    public $timestamps = false;

    protected $fillable = [
        'CategoryID',
        'AspectText',
        'AnswerType',
        'ChoiceTypeID',
        'SortOrder',
        'IsActive',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'CategoryID', 'CategoryID');
    }

    public function choiceType()
    {
        return $this->belongsTo(ChoiceType::class, 'ChoiceTypeID', 'ChoiceTypeID');
    }
}
