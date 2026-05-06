<?php

namespace App\Models\Siakad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Siakad\Prodi;

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
        'MKKode',
        'Nama',
        'ProdiID',
    ];

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'ProdiID', 'ProdiID');
    }
}
