<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FruitPrice extends Model
{
    // 因為這是一個邏輯 Model，不一定要對應單一資料表，但我們可以封裝查詢
    public static function getFruitPrice($cropId, $startDate, $endDate)
    {
        $sql = "
            WITH 
            max_actual AS (
                SELECT
                    crop_id,
                    MAX(`date`) AS max_actual_date
                FROM price_prediction
                WHERE `mode` = 'actual' AND price IS NOT NULL
                GROUP BY crop_id
            ),
            price_filtered AS (
                SELECT p.date, p.crop_id, 'predict' AS mode, p.price
                FROM price_prediction p
                JOIN max_actual m ON p.crop_id = m.crop_id
                WHERE p.date > m.max_actual_date AND p.mode = 'prediction'
                UNION ALL
                SELECT p.date, p.crop_id, p.mode, p.price
                FROM price_prediction p
                JOIN max_actual m ON p.crop_id = m.crop_id
                WHERE p.date <= m.max_actual_date AND p.mode = 'actual'
            ),
            volume_stats AS (
                SELECT
                    `date`,
                    crop_id,
                    SUM(trans_volume)/1000 AS volume_ton
                FROM volume
                GROUP BY `date`, crop_id
            )
            SELECT
                cr.crop_name AS fruit_name,
                p.date AS trade_date,
                p.mode AS price_mode,
                p.price AS avg_price,
                v.volume_ton AS volume_ton
            FROM
                price_filtered p
            JOIN crop cr ON p.crop_id = cr.crop_id
            LEFT JOIN volume_stats v ON p.crop_id = v.crop_id AND p.date = v.date
            WHERE p.crop_id = ? 
              AND p.date BETWEEN ? AND ?
            ORDER BY p.date ASC
        ";

        return DB::select($sql, [$cropId, $startDate, $endDate]);
    }
}