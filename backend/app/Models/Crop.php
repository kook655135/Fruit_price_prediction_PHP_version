<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    protected $table = 'crop';
    protected $primaryKey = 'crop_id'; // 您的 PK 是 crop_id
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // 沒有時間戳記

    protected $fillable = ['crop_id', 'crop_name'];
}