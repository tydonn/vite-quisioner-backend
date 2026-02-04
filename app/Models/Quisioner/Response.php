<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    //
    protected $connection = 'quisioner';
    protected $table = 'dk_tbl_response';
    protected $primaryKey = 'ResponID';

    public $timestamps = false;

    protected $fillable = [
        'MahasiswaID',
        'DosenID',
        'MatakuliahID',
        'TahunAkademik',
        'Semester',
    ];
}
