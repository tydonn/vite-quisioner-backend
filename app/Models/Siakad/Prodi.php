<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;

class Prodi extends Model
{
    //
    protected $connection = 'siakad';
    protected $table = 'prodi';
    protected $primaryKey = 'ProdiID';

    public $timestamps = false;

    protected $fillable = [
        'ProdiID',
        'Nama',
    ];
}
