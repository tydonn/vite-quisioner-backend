<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class MataKuliah extends Model
{
    //
    protected $connection = 'siakad';
    protected $table = 'mk';
    protected $primaryKey = 'MKID';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MKID',
        'Nama',
    ];
}
