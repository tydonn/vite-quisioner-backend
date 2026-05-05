<?php

namespace App\Models\Sia;

use Illuminate\Database\Eloquent\Model;

class TahunAkademik extends Model
{
    //
    protected $connection = 'siakad';
    protected $table = 'tahun';
    protected $primaryKey = 'TahunID';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'TahunID',
    ];
}
