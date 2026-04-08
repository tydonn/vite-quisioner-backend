<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class ResponseDetail extends Model
{
    //
    protected $connection = 'quisioner';
    protected $table = 'dk_tbl_response_detail';
    protected $primaryKey = 'DetailID';

    public $timestamps = false;

    protected $fillable = [
        'ResponID',
        'AspectID',
        'ChoiceID',
        'AnswerText',
        'AnswerNumber',
    ];

    protected $appends = [
        'choice_value',
        'choice_label',
    ];

    public function response()
    {
        return $this->belongsTo(Response::class, 'ResponID', 'ResponID');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'AspectID', 'AspectID');
    }

    public function choice()
    {
        return $this->belongsTo(Choice::class, 'ChoiceID', 'ChoiceID');
    }

    public function getChoiceValueAttribute()
    {
        if (!$this->relationLoaded('choice')) {
            return null;
        }

        return $this->choice?->ChoiceValue;
    }

    public function getChoiceLabelAttribute()
    {
        if (!$this->relationLoaded('choice')) {
            return null;
        }

        return $this->choice?->ChoiceLabel;
    }
}
