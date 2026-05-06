<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class Mahasiswa extends Model
{
    //
    protected $connection = 'siakad';
    protected $table = 'mhsw';
    protected $primaryKey = 'MhswID';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MhswID',
        'Nama',
    ];
}
