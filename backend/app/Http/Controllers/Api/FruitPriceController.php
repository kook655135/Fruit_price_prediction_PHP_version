<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FruitPrice; // 引入剛寫好的 Model
use Illuminate\Http\Request;

class FruitPriceController extends Controller
{
    public function getFruitPrice(Request $request)
    {
        // 1. 取得並處理參數
        $cropId = $request->query('crop_id', 'A1');
        $startDate = $request->query('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->query('end_date', now()->addDays(7)->toDateString());

        // 2. 呼叫 Model 取得數據
        $data = FruitPrice::getFruitPrice($cropId, $startDate, $endDate);

        // 3. 回傳 JSON
        return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE);
    }
}