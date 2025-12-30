<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricePrediction extends Model
{
    // 1. 指定資料表名稱
    protected $table = 'price_prediction';

    // 2. 告知系統此表沒有自動遞增的 ID
    public $incrementing = false;

    // 3. 告知系統此表沒有時間戳記欄位 (created_at, updated_at)
    public $timestamps = false;

    /**
     * 4. 定義「虛擬 ID」屬性
     * 這會將三個欄位拼接成字串，對應 Controller 裡的 explode('|', $id)
     */
    protected $appends = ['id'];

    public function getIdAttribute()
    {
        return "{$this->date}|{$this->crop_id}|{$this->mode}";
    }

    // 5. 允許批量賦值的欄位
    protected $fillable = ['crop_id', 'date', 'mode', 'price'];

    /**
     * 6. 定義與 Crop 表的關聯
     * 讓 Controller 的 $grid->column('crop.crop_name') 可以運作
     */
    public function crop()
    {
        return $this->belongsTo(Crop::class, 'crop_id', 'crop_id');
    }
}