<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class Dosen extends Model
{
    //
    protected $connection = 'siakad';
    protected $table = 'dosen';
    protected $primaryKey = 'Login';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'Login',
        'Nama',
    ];
}
