<?php

namespace App\Admin\Actions\Grid;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PricePrediction;

class DeletePriceRow extends RowAction
{
    public $name = '刪除數據';

    // 解決參數不相容錯誤
    public function retrieveModel(Request $request)
    {
        $key = $request->get('_key');
        $parts = explode('|', $key);
        if (count($parts) !== 3) return null;

        return PricePrediction::where('date', $parts[0])
            ->where('crop_id', $parts[1])
            ->where('mode', $parts[2])->first();
    }

    public function dialog()
    {
        $this->confirm('您確定要永久刪除這筆價格數據嗎？');
    }

    /**
     * 【重要】移除 Model 類型限定
     */
    public function handle($model, Request $request)
    {
        if (!$model instanceof Model) {
            $key = $request->get('_key');
            $parts = explode('|', $key);
            $query = DB::table('price_prediction')->where('date', $parts[0])->where('crop_id', $parts[1])->where('mode', $parts[2]);
        } else {
            $query = DB::table('price_prediction')->where('date', $model->date)->where('crop_id', $model->crop_id)->where('mode', $model->mode);
        }

        return $query->delete() 
            ? $this->response()->success('數據已成功刪除！')->refresh() 
            : $this->response()->error('刪除失敗');
    }
}