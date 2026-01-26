<?php

namespace App\Models\Quisioner;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //
    protected $connection = 'quisioner'; // 🔑 PENTING
    protected $table = 'dk_tbl_category';

    public $timestamps = false; // DB lama biasanya tidak pakai timestamps

    protected $guarded = [];
}
