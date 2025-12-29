<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Content $content, Request $request)
    {
        // 1. 取得篩選與排序參數
        $crop_id = $request->get('crop_id', 1);
        $start_date = $request->get('start_date', date('Y-m-d', strtotime('-3 month')));
        $end_date = $request->get('end_date', date('Y-m-d', strtotime('+7 day')));
        $year_range = $request->get('year_range', "2023;2024");
        $todayStr = "2025-12-29"; 
        
        $sort_by = $request->get('sort_by');
        $order_clause = in_array($sort_by, ['avg_press', 'avg_temp', 'avg_hum', 'avg_rain', 'avg_wind']) 
                        ? "{$sort_by} DESC" : "c.city_id ASC";

        $yearsArr = explode(';', $year_range);
        $min_y = $yearsArr[0]; $max_y = $yearsArr[1] ?? $min_y;

        // 2. 數據獲取
        $cropOptions = DB::table('crop')->pluck('crop_name', 'crop_id')->toArray();

        // 價格趨勢數據
        $priceSql = "WITH max_actual AS (SELECT crop_id, MAX(`date`) AS max_actual_date FROM price_prediction WHERE `mode` = 'actual' AND price IS NOT NULL GROUP BY crop_id), price_filtered AS (SELECT p.date, p.crop_id, 'prediction' AS mode, p.price FROM price_prediction p JOIN max_actual m ON p.crop_id = m.crop_id WHERE p.date > m.max_actual_date AND p.mode = 'prediction' UNION ALL SELECT p.date, p.crop_id, p.mode, p.price FROM price_prediction p JOIN max_actual m ON p.crop_id = m.crop_id WHERE p.date <= m.max_actual_date AND p.mode = 'actual'), volume_stats AS (SELECT `date`, crop_id, SUM(trans_volume)/1000 AS volume_ton FROM volume GROUP BY `date`, crop_id) SELECT p.date, p.mode, p.price, v.volume_ton FROM price_filtered p LEFT JOIN volume_stats v ON p.crop_id = v.crop_id AND p.date = v.date WHERE p.crop_id = ? AND p.date BETWEEN ? AND ? ORDER BY p.date ASC";
        $priceDb = DB::select($priceSql, [$crop_id, $start_date, $end_date]);
        
        $dates = []; $actualP = []; $predictP = []; $volumes = []; $totalP = 0; $countP = 0; $todayIndex = -1;
        foreach($priceDb as $idx => $r) {
            $dates[] = date('Y/m/d', strtotime($r->date)); $volumes[] = round($r->volume_ton, 2);
            if (date('Y-m-d', strtotime($r->date)) == $todayStr) $todayIndex = $idx;
            if($r->mode == 'actual') { $actualP[] = $r->price; $predictP[] = '-'; $totalP += $r->price; $countP++; } 
            else { $actualP[] = '-'; $predictP[] = $r->price; }
        }
        $avgPrice = ($countP > 0) ? round($totalP / $countP, 2) : 0;

        // 天氣指標數據
        $weatherDb = DB::select("SELECT c.city_name AS city, AVG(w.station_pressure) AS avg_press, AVG(w.air_temperature) AS avg_temp, AVG(w.relative_humidity) AS avg_hum, AVG(w.precipitation) AS avg_rain, AVG(w.wind_speed) AS avg_wind FROM weather w JOIN city c ON w.city_id = c.city_id WHERE w.date BETWEEN ? AND ? GROUP BY c.city_name, c.city_id ORDER BY {$order_clause}", [$start_date, $end_date]);
        $wCities = []; $wP = []; $wT = []; $wH = []; $wR = []; $wW = [];
        foreach($weatherDb as $r) { $wCities[] = $r->city; $wP[] = round($r->avg_press, 1); $wT[] = round($r->avg_temp, 1); $wH[] = round($r->avg_hum, 1); $wR[] = round($r->avg_rain, 2); $wW[] = round($r->avg_wind, 1); }

        // 面積與地圖連動數據
        $areaDb = DB::select("SELECT c.city_name AS city, SUM(a.planted_area) as total_p, SUM(a.harvested_area) as total_h, SUM(a.production) as total_prod FROM area_production a JOIN city c ON a.city_id = c.city_id WHERE a.crop_id = ? AND a.year BETWEEN ? AND ? GROUP BY c.city_name, c.city_id ORDER BY total_p DESC", [$crop_id, $min_y, $max_y]);
        
        $aCities = []; $p_area = []; $h_area = []; $mapHarvest = []; $mapPlant = []; $mapProd = [];
        foreach($areaDb as $r) {
            $aCities[] = $r->city; $p_area[] = round($r->total_p, 1); $h_area[] = round($r->total_h, 1);
            $mapHarvest[] = ['name' => $r->city, 'value' => round($r->total_h, 1)];
            $mapPlant[] = ['name' => $r->city, 'value' => round($r->total_p, 1)];
            $mapProd[] = ['name' => $r->city, 'value' => round($r->total_prod, 1)];
        }

        return $content
            ->title('水果價格預測系統 - 決策看板')
            ->row(function (Row $row) use ($start_date, $end_date, $crop_id, $cropOptions, $year_range) {
                // 頂部篩選器 HTML
                $row->column(12, function (Column $column) use ($start_date, $end_date, $crop_id, $cropOptions, $year_range) {
                    $html = '<div style="background:#fff; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #e0e0e0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);"><form action="" method="GET" id="main-filter" style="display:flex; align-items:center; gap:25px; flex-wrap:wrap;"><input type="hidden" name="sort_by" id="sort_field" value=""><div><label style="font-size:12px;color:#666;">日期區間</label><br><input type="date" name="start_date" value="'.$start_date.'"> ~ <input type="date" name="end_date" value="'.$end_date.'"></div><div><label style="font-size:12px;color:#666;">水果選擇</label><br><select name="crop_id" style="width:120px; height:30px;">';
                    foreach($cropOptions as $id => $name) { $sel = ($id == $crop_id) ? "selected" : ""; $html .= "<option value='$id' $sel>$name</option>"; }
                    $html .= '</select></div><div style="flex:1; max-width:250px;"><label style="font-size:12px;color:#666;">年份範圍 (Slider)</label><br><input type="text" id="year_slider" name="year_range" value="'.$year_range.'"></div><button type="submit" class="btn btn-primary" style="background:#d32f2f; margin-top:15px; border:none; height:34px; padding:0 20px;">執行連動分析</button></form></div><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ion-rangeslider/2.3.1/css/ion.rangeSlider.min.css"/><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/ion-rangeslider/2.3.1/js/ion.rangeSlider.min.js"></script><script>$("#year_slider").ionRangeSlider({ type: "double", min: 2020, max: 2024, from: '.explode(';',$year_range)[0].', to: '.(explode(';',$year_range)[1] ?? 2024).', step: 1, postfix: "年" });</script>';
                    $column->append($html);
                });
            })
            ->row(function (Row $row) {
                $row->column(7, function (Column $column) { $column->append('<div style="background:#fff; padding:20px; border-radius:12px; height:550px; border:1px solid #eee; box-shadow: 0 4px 12px rgba(0,0,0,0.05);"><h4 style="font-weight:bold;margin-bottom:15px;">量價趨勢與預測</h4><div id="trend-chart" style="height:480px;"></div></div>'); });
                // 右側地圖容器：加入熱力圖維度切換按鈕
                $row->column(5, function (Column $column) { 
                    $html = '<div style="background:#fff; padding:20px; border-radius:12px; height:550px; border:1px solid #eee; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">';
                    $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">';
                    $html .= '<h4 style="font-weight:bold; margin:0;">產地熱力分佈</h4>';
                    $html .= '<div class="btn-group btn-group-xs" role="group">';
                    $html .= '<button type="button" class="btn btn-default active" onclick="switchMapData(\'harvest\', this)">收穫</button>';
                    $html .= '<button type="button" class="btn btn-default" onclick="switchMapData(\'plant\', this)">種植</button>';
                    $html .= '<button type="button" class="btn btn-default" onclick="switchMapData(\'prod\', this)">產量</button>';
                    $html .= '</div></div>';
                    $html .= '<div id="taiwan-map" style="height:480px;"></div></div>';
                    $column->append($html);
                });
            })
            ->row(function (Row $row) {
                // 天氣指標與面積分析
                $row->column(7, function (Column $column) { $column->append('<div style="background:#fff; padding:20px; border-radius:12px; margin-top:20px; border:1px solid #eee; box-shadow: 0 4px 12px rgba(0,0,0,0.05);"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;"><h4 style="font-weight:bold; margin:0;">縣市五大氣象指標</h4><div style="font-size:11px; color:#888;">排序： <span style="cursor:pointer; color:#3c8dbc;" onclick="applySort(\'avg_press\')">氣壓</span> | <span style="cursor:pointer; color:#3c8dbc;" onclick="applySort(\'avg_temp\')">氣溫</span> | <span style="cursor:pointer; color:#3c8dbc;" onclick="applySort(\'avg_hum\')">濕度</span> | <span style="cursor:pointer; color:#3c8dbc;" onclick="applySort(\'avg_rain\')">降雨</span> | <span style="cursor:pointer; color:#3c8dbc;" onclick="applySort(\'avg_wind\')">風速</span></div></div><div id="weather-chart" style="height:550px;"></div></div>'); });
                $row->column(5, function (Column $column) { $column->append('<div style="background:#fff; padding:20px; border-radius:12px; margin-top:20px; border:1px solid #eee; box-shadow: 0 4px 12px rgba(0,0,0,0.05);"><h4 style="font-weight:bold;margin-bottom:20px;">收穫與種植面積對比 (公頃)</h4><div id="area-chart" style="height:550px;"></div></div>'); });
            })
            ->row(function (Row $row) use ($dates, $actualP, $predictP, $volumes, $avgPrice, $todayIndex, $todayStr, $wCities, $wP, $wT, $wH, $wR, $wW, $aCities, $p_area, $h_area, $mapHarvest, $mapPlant, $mapProd) {
                $row->column(12, function (Column $column) use ($dates, $actualP, $predictP, $volumes, $avgPrice, $todayIndex, $todayStr, $wCities, $wP, $wT, $wH, $wR, $wW, $aCities, $p_area, $h_area, $mapHarvest, $mapPlant, $mapProd) {
                    $column->append('
                        <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
                        <script>
                            function applySort(f) { document.getElementById("sort_field").value = f; document.getElementById("main-filter").submit(); }
                            
                            // 儲存三種地圖數據與標籤
                            const mapSource = {
                                "harvest": { name: "收穫面積(公頃)", data: '.json_encode($mapHarvest).' },
                                "plant": { name: "種植面積(公頃)", data: '.json_encode($mapPlant).' },
                                "prod": { name: "產量(公噸)", data: '.json_encode($mapProd).' }
                            };
                            let mapChart;

                            function switchMapData(type, btn) {
                                $(".btn-group .btn").removeClass("active");
                                $(btn).addClass("active");
                                const info = mapSource[type];
                                
                                // 動態計算該數據組的 Max 值
                                const values = info.data.map(d => d.value);
                                const maxVal = Math.max(...values, 10); // 最小上限為 10
                                
                                mapChart.setOption({
                                    visualMap: { max: maxVal },
                                    tooltip: { formatter: "{b}<br/>" + info.name + ": {c}" },
                                    series: [{ data: info.data }]
                                });
                            }

                            (function() {
                                // 1. 趨勢圖 (量價雙軸)
                                var trendChart = echarts.init(document.getElementById("trend-chart"));
                                trendChart.setOption({ tooltip: { trigger: "axis" }, legend: { bottom: 0 }, xAxis: { type: "category", data: '.json_encode($dates).' }, yAxis: [{ type: "value" }, { type: "value", position: "right", splitLine: {show: false} }], series: [{ name: "交易量", type: "bar", yAxisIndex: 1, data: '.json_encode($volumes).', color: "#ffe0b2" }, { name: "實際價格", type: "line", data: '.json_encode($actualP).', color: "#d32f2f", smooth: true, markLine: { symbol: ["none", "none"], data: [{yAxis: '.$avgPrice.', label:{formatter:"平均 '.$avgPrice.'", position:"start"}}, {xAxis: '.($todayIndex!=-1?$todayIndex:"null").', label:{formatter:"Today"}, lineStyle: {color: "#333", width: 2}}] } }, { name: "預測價格", type: "line", data: '.json_encode($predictP).', color: "#388e3c", lineStyle: {type: "dashed"} }] });

                                // 2. 台灣熱力地圖 (維度切換 + 5.0 鎖定倍率)
                                mapChart = echarts.init(document.getElementById("taiwan-map"));
                                fetch("/assets/json/taiwan_map.json")
                                .then(res => res.json())
                                .then(geoJson => {
                                    echarts.registerMap("taiwan", geoJson);
                                    // 初始以收穫面積計算 Max 值
                                    const initMax = Math.max(...mapSource.harvest.data.map(d => d.value), 100);
                                    mapChart.setOption({
                                        tooltip: { trigger: "item", formatter: "{b}<br/>收穫面積: {c} 公頃" },
                                        visualMap: { min: 0, max: initMax, left: "left", bottom: "5%", text: ["高", "低"], calculable: true, inRange: { color: ["#fff5f5", "#f28e8e", "#d32f2f"] } },
                                        series: [{
                                            type: "map",
                                            map: "taiwan",
                                            nameProperty: "COUNTYNAME",
                                            label: { show: true, fontSize: 8 },
                                            aspectScale: 0.85,
                                            zoom: 5.0, // 嚴格鎖定您的倍率
                                            center: [120.9738, 23.9738],
                                            data: mapSource.harvest.data
                                        }]
                                    });
                                });

                                // 3. 天氣圖 (補全降雨、風速)
                                var weatherChart = echarts.init(document.getElementById("weather-chart"));
                                weatherChart.setOption({
                                    tooltip: { trigger: "axis" },
                                    dataZoom: [{ type: "slider", yAxisIndex: [0,1,2,3,4], right: 5, startValue: 0, endValue: 9 }],
                                    grid: [{left: "10%", width: "13%"}, {left: "28%", width: "13%"}, {left: "46%", width: "13%"}, {left: "64%", width: "13%"}, {left: "82%", width: "13%"}],
                                    xAxis: [{gridIndex:0, name:"hPa", axisLabel:{rotate:90}}, {gridIndex:1, name:"°C", axisLabel:{rotate:90}}, {gridIndex:2, name:"%", axisLabel:{rotate:90}}, {gridIndex:3, name:"mm", axisLabel:{rotate:90}}, {gridIndex:4, name:"m/s", axisLabel:{rotate:90}}],
                                    yAxis: [{gridIndex:0, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{interval:0}}, {gridIndex:1, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false}}, {gridIndex:2, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false}}, {gridIndex:3, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false}}, {gridIndex:4, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false}}],
                                    series: [
                                        {name:"氣壓", type:"bar", xAxisIndex:0, yAxisIndex:0, data:'.json_encode($wP).', label:{show:true, position:"right", fontSize:8}},
                                        {name:"氣溫", type:"bar", xAxisIndex:1, yAxisIndex:1, data:'.json_encode($wT).', label:{show:true, position:"right", fontSize:8}},
                                        {name:"濕度", type:"bar", xAxisIndex:2, yAxisIndex:2, data:'.json_encode($wH).', label:{show:true, position:"right", fontSize:8}},
                                        {name:"降雨", type:"bar", xAxisIndex:3, yAxisIndex:3, data:'.json_encode($wR).', label:{show:true, position:"right", fontSize:8}},
                                        {name:"風速", type:"bar", xAxisIndex:4, yAxisIndex:4, data:'.json_encode($wW).', label:{show:true, position:"right", fontSize:8}}
                                    ]
                                });

                                // 4. 面積圖 (Bullet 對齊)
                                var areaChart = echarts.init(document.getElementById("area-chart"));
                                areaChart.setOption({ tooltip: { trigger: "axis" }, dataZoom: [{ type: "slider", yAxisIndex: 0, right: 10, startValue: 0, endValue: 9 }], grid: { left: "20%", right: "15%", containLabel: true }, xAxis: { type: "value", axisLabel: { rotate: 90 } }, yAxis: { type: "category", data: '.json_encode($aCities).', inverse: true, axisLabel: { interval: 0 } }, series: [ { name: "種植面積", type: "bar", data: '.json_encode($p_area).', color: "#e0e0e0", barWidth: 16, z: 1 }, { name: "收穫面積", type: "bar", data: '.json_encode($h_area).', color: "#f28e8e", barWidth: 8, barGap: "-75%", z: 2, label: { show: true, position: "right", fontSize: 10, color: "#d32f2f" } } ] });

                                window.addEventListener("resize", function() { trendChart.resize(); mapChart.resize(); weatherChart.resize(); areaChart.resize(); });
                            })();
                        </script>');
                });
            });
    }
}