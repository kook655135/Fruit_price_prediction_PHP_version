<?php

namespace App\Admin\Actions\Grid;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PricePrediction;

class UpdatePriceRow extends RowAction
{
    public $name = '修改價格';

    // 解決與父類別 retrieveModel 參數不相容的致命錯誤
    public function retrieveModel(Request $request)
    {
        $key = $request->get('_key'); // 獲取我們偽造的 "date|crop_id|mode"
        $parts = explode('|', $key);
        if (count($parts) !== 3) return null;

        return PricePrediction::where('date', $parts[0])
            ->where('crop_id', $parts[1])
            ->where('mode', $parts[2])->first();
    }

    public function form()
    {
        // 防禦性讀取：避免 row 為 false 時炸裂
        $price = is_object($this->row) ? ($this->row->price ?? 0) : 0;
        $this->text('price', '新的價格(元/公斤)')->default($price)->rules('required|numeric');
    }

    /**
     * 【重要】移除 $model 的 Model 類型限定，防止 Request 遞補導致的崩潰
     */
    public function handle($model, Request $request)
    {
        $newPrice = $request->get('price');

        // 如果 $model 不是物件，手動解析 _key 進行定位
        if (!$model instanceof Model) {
            $key = $request->get('_key');
            $parts = explode('|', $key);
            $query = DB::table('price_prediction')->where('date', $parts[0])->where('crop_id', $parts[1])->where('mode', $parts[2]);
        } else {
            $query = DB::table('price_prediction')->where('date', $model->date)->where('crop_id', $model->crop_id)->where('mode', $model->mode);
        }

        $updated = $query->update(['price' => $newPrice]);

        return $updated 
            ? $this->response()->success('價格更新成功！')->refresh() 
            : $this->response()->warning('數值未變動或更新失敗。');
    }
}