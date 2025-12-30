<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FruitDataController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Taipei');
    }

    /**
     * 讀取價格列表
     */
    public function getPriceIndex(Request $request)
    {
        $start_date = $request->get('start_date', date('Y-m-d', strtotime('-3 month')));
        $end_date = $request->get('end_date', date('Y-m-d', strtotime('+7 day')));
        $crop_names = $request->get('crop_names', []);

        $cropWhere = ""; $params = [$start_date, $end_date];

        if (!empty($crop_names)) {
            $placeholders = implode(',', array_fill(0, count($crop_names), '?'));
            $cropWhere = " AND cp.crop_name IN ($placeholders) ";
            $params = array_merge($params, $crop_names);
        }

        $sql = "
            WITH max_actual AS (SELECT crop_id, MAX(`date`) AS max_actual_date FROM price_prediction WHERE `mode` = 'actual' AND price IS NOT NULL GROUP BY crop_id),
            price_filtered AS (SELECT p.date, p.crop_id, 'prediction' AS mode, p.price FROM price_prediction p JOIN max_actual m ON p.crop_id = m.crop_id WHERE p.date > m.max_actual_date AND p.mode = 'prediction' UNION ALL SELECT p.date, p.crop_id, p.mode, p.price FROM price_prediction p JOIN max_actual m ON p.crop_id = m.crop_id WHERE p.date <= m.max_actual_date AND p.mode = 'actual')
            SELECT 
                p.date AS `日期`, 
                cp.crop_name AS `作物`, 
                p.mode AS `數據模式`, 
                p.price AS `價格(元/公斤)` 
            FROM price_filtered p 
            JOIN crop cp ON p.crop_id = cp.crop_id 
            WHERE p.date BETWEEN ? AND ? $cropWhere 
            ORDER BY p.date DESC
        ";

        $data = DB::select($sql, $params);
        
        return response()->json([
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'count' => count($data),
            'data' => $data
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 新增數據
     */
    public function storePrice(Request $request)
    {
        $cropId = DB::table('crop')->where('crop_name', $request->crop_name)->value('crop_id');
        if (!$cropId) return response()->json(['status' => 'error', 'message' => '找不到作物'], 404, [], JSON_UNESCAPED_UNICODE);

        $inserted = DB::table('price_prediction')->insert([
            'date'    => $request->date,
            'crop_id' => $cropId,
            'price'   => $request->price,
            'mode'    => $request->mode,
        ]);

        return response()->json(['status' => 'success', 'message' => '數據新增成功'], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 更新數據 (使用複合鍵定位)
     */
    public function updatePrice(Request $request)
    {
        $cropId = DB::table('crop')->where('crop_name', $request->crop_name)->value('crop_id');
        
        $updated = DB::table('price_prediction')
            ->where('date', $request->date)
            ->where('crop_id', $cropId)
            ->where('mode', $request->mode)
            ->update(['price' => $request->price]);

        return response()->json([
            'status' => $updated ? 'success' : 'fail',
            'message' => $updated ? '更新成功' : '找不到資料或價格未變動'
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 刪除數據 (使用複合鍵定位)
     */
    public function deletePrice(Request $request)
    {
        $cropId = DB::table('crop')->where('crop_name', $request->crop_name)->value('crop_id');
        
        $deleted = DB::table('price_prediction')
            ->where('date', $request->date)
            ->where('crop_id', $cropId)
            ->where('mode', $request->mode)
            ->delete();

        return response()->json([
            'status' => $deleted ? 'success' : 'fail',
            'message' => $deleted ? '數據已刪除' : '找不到對應資料'
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}