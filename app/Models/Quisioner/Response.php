<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;

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

    // Define relationship with ResponseDetail
    public function responseDetails()
    {
        return $this->hasMany(ResponseDetail::class, 'ResponID', 'ResponID');
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class, 'DosenID', 'Login');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'MahasiswaID', 'MhswID');
    }

    public function matakuliah()
    {
        return $this->belongsTo(MataKuliah::class, 'MatakuliahID', 'MKID');
    }
}
