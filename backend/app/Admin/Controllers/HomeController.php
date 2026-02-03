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
        // 設定時區為 CST
        date_default_timezone_set('Asia/Taipei');

        // 1. 預設值與參數處理
        $cropOptions = DB::table('crop')->pluck('crop_name', 'crop_id')->toArray();
        $defaultOrangeId = array_search('柳橙', $cropOptions) ?: 1; 

        $crop_id = $request->get('crop_id', $defaultOrangeId);
        $start_date = $request->get('start_date', date('Y-m-d', strtotime('-3 month')));
        $end_date = $request->get('end_date', date('Y-m-d', strtotime('+7 day')));
        $year_range = $request->get('year_range', "2024;2024");
        
        $todayRaw = date('Y-m-d'); 
        $todayDisplay = date('Y/m/d'); 
        
        $selected_cities_str = $request->get('selected_cities', ''); 
        $selected_cities_arr = array_filter(explode(',', $selected_cities_str));

        $sort_by = $request->get('sort_by');
        $order_clause = in_array($sort_by, ['avg_press', 'avg_temp', 'avg_hum', 'avg_rain', 'avg_wind']) 
                        ? "{$sort_by} DESC" : "c.city_id ASC";

        $yearsArr = explode(';', $year_range);
        $min_y = $yearsArr[0]; $max_y = $yearsArr[1] ?? $min_y;

        // 2. CTE 數據查詢
        $priceSql = "
            WITH max_actual AS (
                SELECT crop_id, MAX(`date`) AS max_actual_date FROM price_prediction WHERE `mode` = 'actual' AND price IS NOT NULL GROUP BY crop_id
            ),
            price_filtered AS (
                SELECT p.date, p.crop_id, 'prediction' AS mode, p.price FROM price_prediction p JOIN max_actual m ON p.crop_id = m.crop_id WHERE p.date > m.max_actual_date AND p.mode = 'prediction'
                UNION ALL 
                SELECT p.date, p.crop_id, p.mode, p.price FROM price_prediction p JOIN max_actual m ON p.crop_id = m.crop_id WHERE p.date <= m.max_actual_date AND p.mode = 'actual'
            ),
            volume_stats AS (
                SELECT `date`, crop_id, SUM(trans_volume)/1000 AS volume_ton FROM volume GROUP BY `date`, crop_id
            )
            SELECT p.date, p.mode, p.price, v.volume_ton FROM price_filtered p LEFT JOIN volume_stats v ON p.crop_id = v.crop_id AND p.date = v.date WHERE p.crop_id = ? AND p.date BETWEEN ? AND ? ORDER BY p.date ASC
        ";
        
        $priceDb = DB::select($priceSql, [$crop_id, $start_date, $end_date]);
        $dates = []; $actualP = []; $predictP = []; $volumes = []; $totalP = 0; $countP = 0; $todayIndex = -1; $priceForKpi = [];

        foreach($priceDb as $idx => $r) {
            $dates[] = date('Y/m/d', strtotime($r->date)); 
            $volumes[] = round((float)$r->volume_ton, 2);
            if (date('Y-m-d', strtotime($r->date)) == $todayRaw) $todayIndex = $idx;
            if($r->mode == 'actual') { 
                $actualP[] = (float)$r->price; $predictP[] = null; 
                if($r->price > 0) { $totalP += $r->price; $countP++; $priceForKpi[] = $r->price; }
            } else { 
                $actualP[] = null; $predictP[] = (float)$r->price; 
            }
        }
        
        $avgPrice = ($countP > 0) ? round($totalP / $countP, 1) : 0;
        $maxPrice = !empty($priceForKpi) ? max($priceForKpi) : 0;
        $minPrice = !empty($priceForKpi) ? min($priceForKpi) : 0;
        $totalVol = array_sum($volumes);

        // 3. 年度平均量價 (給地圖懸浮標籤)
        $yearAvgPriceDb = DB::select("SELECT AVG(price) as avg_p FROM price_prediction WHERE crop_id = ? AND YEAR(`date`) BETWEEN ? AND ? AND `mode` = 'actual' AND price > 0", [$crop_id, $min_y, $max_y]);
        $yearAvgPrice = !empty($yearAvgPriceDb) ? round($yearAvgPriceDb[0]->avg_p, 1) : 0;
        $yearAvgVolDb = DB::select("SELECT AVG(daily_vol) as avg_v FROM (SELECT SUM(trans_volume)/1000 as daily_vol FROM volume WHERE crop_id = ? AND YEAR(`date`) BETWEEN ? AND ? GROUP BY `date`) t", [$crop_id, $min_y, $max_y]);
        $yearAvgVol = !empty($yearAvgVolDb) ? round($yearAvgVolDb[0]->avg_v, 2) : 0;

        $cityWhere = ""; $cityParams = [];
        if (!empty($selected_cities_arr)) {
            $placeholders = implode(',', array_fill(0, count($selected_cities_arr), '?'));
            $cityWhere = " AND c.city_name IN ($placeholders) ";
            $cityParams = array_values($selected_cities_arr);
        }

        $weatherDb = DB::select("SELECT c.city_name AS city, AVG(w.station_pressure) AS avg_press, AVG(w.air_temperature) AS avg_temp, AVG(w.relative_humidity) AS avg_hum, AVG(w.precipitation) AS avg_rain, AVG(w.wind_speed) AS avg_wind FROM weather w JOIN city c ON w.city_id = c.city_id WHERE w.date BETWEEN ? AND ? $cityWhere GROUP BY c.city_name, c.city_id ORDER BY {$order_clause}", array_merge([$start_date, $end_date], $cityParams));
        $wCities = []; $wP = []; $wT = []; $wH = []; $wR = []; $wW = [];
        foreach($weatherDb as $r) { $wCities[] = $r->city; $wP[] = round($r->avg_press, 1); $wT[] = round($r->avg_temp, 1); $wH[] = round($r->avg_hum, 1); $wR[] = round($r->avg_rain, 2); $wW[] = round($r->avg_wind, 1); }

        $allAreaDb = DB::select("SELECT c.city_name AS city, SUM(a.planted_area) as total_p, SUM(a.harvested_area) as total_h, SUM(a.production) as total_prod FROM area_production a JOIN city c ON a.city_id = c.city_id WHERE a.crop_id = ? AND a.year BETWEEN ? AND ? GROUP BY c.city_name", [$crop_id, $min_y, $max_y]);
        $mapHarvest = []; $mapPlant = []; $mapProd = [];
        foreach($allAreaDb as $r) {
            $commonMapData = ['name' => $r->city, 'p_area' => round($r->total_p, 1), 'h_area' => round($r->total_h, 1), 'prod' => round($r->total_prod, 1), 'year_avg_vol' => $yearAvgVol, 'year_avg_price' => $yearAvgPrice];
            $mapHarvest[] = array_merge($commonMapData, ['value' => $commonMapData['h_area']]);
            $mapPlant[] = array_merge($commonMapData, ['value' => $commonMapData['p_area']]);
            $mapProd[] = array_merge($commonMapData, ['value' => $commonMapData['prod']]);
        }

        $areaDb = DB::select("SELECT c.city_name AS city, SUM(a.planted_area) as total_p, SUM(a.harvested_area) as total_h FROM area_production a JOIN city c ON a.city_id = c.city_id WHERE a.crop_id = ? AND a.year BETWEEN ? AND ? $cityWhere GROUP BY c.city_name ORDER BY total_p DESC", array_merge([$crop_id, $min_y, $max_y], $cityParams));
        $aCities = []; $p_area = []; $h_area = [];
        foreach($areaDb as $r) { $aCities[] = $r->city; $p_area[] = round($r->total_p, 1); $h_area[] = round($r->total_h, 1); }

        return $content
            ->title('水果價格預測系統')
            ->row(function (Row $row) use ($start_date, $end_date, $crop_id, $cropOptions, $year_range, $selected_cities_str, $avgPrice, $maxPrice, $minPrice, $totalVol, $request) {
                $row->column(12, function (Column $column) use ($start_date, $end_date, $crop_id, $cropOptions, $year_range, $selected_cities_str, $avgPrice, $maxPrice, $minPrice, $totalVol, $request) {
                    $html = '
                    <style>
                        .content { padding-top: 1px !important; }
                        .breadcrumb { display: none !important; }
                        .kpi-box { background:#fff; padding:6px 15px; border-radius:8px; border:1px solid #e0e0e0; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
                        .kpi-val { font-size: 17px; font-weight: bold; line-height: 1.1; margin-top: 2px; }
                        .kpi-title { font-size: 9px; color: #888; }
                        .chart-card { background:#fff; padding:10px; border-radius:8px; border:1px solid #eee; }
                        .btn-maintenance { font-size: 13px !important; padding: 6px 18px !important; font-weight: bold !important; box-shadow: 0 2px 4px rgba(60,141,188,0.3); }
                        .crop-checkbox-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; padding: 15px; }
                    </style>
                    <div class="kpi-box">
                        <form action="" method="GET" id="main-filter" style="display:flex; align-items:center; gap:15px; flex:0 0 auto;">
                            <input type="hidden" name="selected_cities" id="selected_cities_input" value="'.$selected_cities_str.'">
                            <div><span class="kpi-title">日期範圍</span><br><div style="display:flex; align-items:center; gap:5px;"><input type="date" name="start_date" value="'.$start_date.'" onchange="this.form.submit()" style="border:1px solid #ddd; padding:0 3px; border-radius:4px; font-size:11px; height:24px;"><span style="font-size:12px; color:#999;">~</span><input type="date" name="end_date" value="'.$end_date.'" onchange="this.form.submit()" style="border:1px solid #ddd; padding:0 3px; border-radius:4px; font-size:11px; height:24px;"></div></div>
                            <div><span class="kpi-title">水果選擇</span><br><select name="crop_id" onchange="this.form.submit()" style="height:24px; border:1px solid #ddd; font-size:11px;">';
                            foreach($cropOptions as $id => $name) { $sel = ($id == $crop_id) ? "selected" : ""; $html .= "<option value='$id' $sel>$name</option>"; }
                    $html .= '  </select></div>
                        </form>
                        <div style="display:flex; align-items:center; gap:25px; flex:1; justify-content:center; border-left:1px solid #eee; border-right:1px solid #eee; margin:0 15px; padding:0 15px;">
                            <div style="text-align:center;"><span class="kpi-title">平均價</span><div class="kpi-val" style="color:#d32f2f;">$'.number_format($avgPrice, 1).'</div></div>
                            <div style="text-align:center;"><span class="kpi-title">最高價</span><div class="kpi-val" style="color:#b71c1c;">$'.number_format($maxPrice, 1).'</div></div>
                            <div style="text-align:center;"><span class="kpi-title">最低價</span><div class="kpi-val" style="color:#2e7d32;">$'.number_format($minPrice, 1).'</div></div>
                            <div style="text-align:center;"><span class="kpi-title">總量(公噸)</span><div class="kpi-val" style="color:#333;">'.number_format($totalVol, 0).'</div></div>
                        </div>
                        <div style="display:flex; align-items:center; gap:15px; flex:0 0 auto;">
                            <div style="width:130px;"><span class="kpi-title">年份範圍</span><input type="text" id="year_slider" name="year_range" value="'.$year_range.'"></div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <a href="/" class="btn btn-sm btn-default" style="font-size:10px;">重置</a>
                                <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#exportModal" style="font-size:11px;"><i class="fa fa-download"></i> 報表輸出</button>
                                <a href="/data-management" class="btn btn-primary btn-maintenance"><i class="fa fa-edit"></i> 數據維護</a>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <form action="export-report" method="GET" target="_blank">
                                <div class="modal-content">
                                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">選擇導出作物 (可多選)</h4></div>
                                    <div class="modal-body">
                                        <input type="hidden" name="start_date" value="'.$start_date.'"><input type="hidden" name="end_date" value="'.$end_date.'"><input type="hidden" name="year_range" value="'.$year_range.'"><input type="hidden" name="selected_cities" value="'.$selected_cities_str.'">
                                        <div style="margin-bottom:10px; padding-left:15px;"><label><input type="checkbox" id="selectAllCrops"> 全選所有作物</label></div>
                                        <div class="crop-checkbox-grid">';
                                        foreach($cropOptions as $id => $name) { $html .= '<label style="font-weight:normal;"><input type="checkbox" name="crop_ids[]" value="'.$id.'" class="crop-item"> '.$name.'</label>'; }
                    $html .= '          </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                                        <button type="submit" class="btn btn-primary" onclick="$(\'#exportModal\').modal(\'hide\')">開始導出報表</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ion-rangeslider/2.3.1/css/ion.rangeSlider.min.css"/>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/ion-rangeslider/2.3.1/js/ion.rangeSlider.min.js"></script>
                    <script>
                        $("#year_slider").ionRangeSlider({ type: "double", min: 2020, max: 2024, from: '.explode(';',$year_range)[0].', to: '.(explode(';',$year_range)[1] ?? 2024).', step: 1, postfix: "年", prettify: function(n){return n;}, onFinish: function(data){ $("#main-filter").submit(); } });
                        $("#selectAllCrops").click(function(){ $(".crop-item").prop("checked", this.checked); });
                    </script>';
                    $column->append($html);
                });
            })
            ->row(function (Row $row) {
                $row->column(7, function (Column $column) { $column->append('<div class="chart-card"><h5 style="margin:0 0 5px 0;font-weight:bold;font-size:13px;">價格趨勢</h5><div id="trend-chart" style="height:260px;"></div></div>'); });
                $row->column(5, function (Column $column) { 
                    $html = '<div class="chart-card"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><h5 style="margin:0;font-weight:bold;font-size:13px;">地圖</h5>';
                    $html .= '<div class="btn-group btn-group-xs"><button type="button" class="btn btn-default active" onclick="switchMapData(\'harvest\', this)" style="font-size:10px; padding:1px 5px;">收穫</button><button type="button" class="btn btn-default" onclick="switchMapData(\'plant\', this)" style="font-size:10px; padding:1px 5px;">種植</button><button type="button" class="btn btn-default" onclick="switchMapData(\'prod\', this)" style="font-size:10px; padding:1px 5px;">產量</button></div></div>';
                    $html .= '<div id="taiwan-map" style="height:250px;"></div></div>';
                    $column->append($html);
                });
            })
            ->row(function (Row $row) {
                $row->column(7, function (Column $column) { $column->append('<div class="chart-card" style="margin-top:8px;"><h5 style="margin:0 0 5px 0;font-weight:bold;font-size:13px;">天氣</h5><div id="weather-chart" style="height:280px;"></div></div>'); });
                $row->column(5, function (Column $column) { $column->append('<div class="chart-card" style="margin-top:8px;"><h5 style="margin:0 0 5px 0;font-weight:bold;font-size:13px;">面積(公頃)</h5><div id="area-chart" style="height:280px;"></div></div>'); });
            })
            ->row(function (Row $row) use ($dates, $actualP, $predictP, $volumes, $avgPrice, $todayIndex, $todayDisplay, $wCities, $wP, $wT, $wH, $wR, $wW, $aCities, $p_area, $h_area, $mapHarvest, $mapPlant, $mapProd, $selected_cities_str) {
                $row->column(12, function (Column $column) use ($dates, $actualP, $predictP, $volumes, $avgPrice, $todayIndex, $todayDisplay, $wCities, $wP, $wT, $wH, $wR, $wW, $aCities, $p_area, $h_area, $mapHarvest, $mapPlant, $mapProd, $selected_cities_str) {
                    $column->append('
                        <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
                        <script>
                            function updateSelectionAndSubmit(name) {
                                let input = document.getElementById("selected_cities_input");
                                let list = input.value ? input.value.split(",") : [];
                                let idx = list.indexOf(name);
                                if (idx > -1) { list.splice(idx, 1); } else { list.push(name); }
                                input.value = list.join(",");
                                document.getElementById("main-filter").submit();
                            }
                            const mapSource = {"harvest": { name: "收穫", data: '.json_encode($mapHarvest).' },"plant": { name: "種植", data: '.json_encode($mapPlant).' },"prod": { name: "產量", data: '.json_encode($mapProd).' }};
                            let mapChart;
                            function switchMapData(type, btn) {
                                $(".btn-group .btn").removeClass("active"); $(btn).addClass("active");
                                const info = mapSource[type]; const maxVal = Math.max(...info.data.map(d => d.value), 10);
                                mapChart.setOption({ visualMap: { max: maxVal, text: [Math.round(maxVal), "0"] }, series: [{ data: info.data }] });
                            }
                            (function() {
                                var trendChart = echarts.init(document.getElementById("trend-chart"));
                                
                                // 如果沒有資料，顯示提示訊息；否則正常顯示圖表
                                if ('.json_encode($dates).'.length === 0) {
                                    trendChart.setOption({
                                        title: {
                                            text: "所選區間無交易資料",
                                            left: "center",
                                            top: "center",
                                            textStyle: { color: "#999", fontSize: 14, fontWeight: "normal" }
                                        }
                                    });
                                } else {
                                    var markLineData = [];
                                    if ('.$avgPrice.' > 0) markLineData.push({yAxis: '.$avgPrice.', label:{formatter: "平均 $'.$avgPrice.'", position: "middle", fontSize:8}});
                                    if ('.($todayIndex != -1 ? 'true' : 'false').') markLineData.push({xAxis: '.$todayIndex.', label:{formatter: "Today ('.$todayDisplay.')", position: "end", fontSize:8}, lineStyle: {color: "#d32f2f", type: "dashed"}});

                                    trendChart.setOption({ 
                                        tooltip: { trigger: "axis" }, 
                                        legend: { bottom: 0, itemWidth: 10, textStyle: {fontSize: 9} }, 
                                        grid: { top: 35, bottom: 45, left: 35, right: 35 }, 
                                        xAxis: { type: "category", data: '.json_encode($dates).', axisLabel: { fontSize: 8 } }, 
                                        yAxis: [{ type: "value", name: "成交價(元/公斤)", nameTextStyle: {fontSize: 9}, axisLabel: {fontSize: 8} }, { type: "value", name: "交易量(公噸)", nameTextStyle: {fontSize: 9}, position: "right", splitLine: {show: false}, axisLabel: {fontSize: 8} }], 
                                        series: [
                                            { name: "交易量", type: "bar", yAxisIndex: 1, data: '.json_encode($volumes).', color: "#ffe0b2" }, 
                                            { name: "實際價格", type: "line", data: '.json_encode($actualP).', color: "#d32f2f", smooth: true, connectNulls: true, markLine: { symbol: ["none", "none"], data: markLineData } }, 
                                            { name: "預測價格", type: "line", data: '.json_encode($predictP).', color: "#388e3c", smooth: true, connectNulls: true, lineStyle: {type: "dashed"} }
                                        ] 
                                    });
                                }

                                mapChart = echarts.init(document.getElementById("taiwan-map"));
                                fetch("/assets/json/taiwan_map.json").then(res => res.json()).then(geoJson => {
                                    echarts.registerMap("taiwan", geoJson); const mData = mapSource.harvest.data; const maxVal = Math.max(...mData.map(d => d.value), 10);
                                    mapChart.setOption({ tooltip: { trigger: "item", backgroundColor: "rgba(255, 255, 255, 0.9)", formatter: function(p) { if(!p.data) return p.name; return `<div style="font-weight:bold; border-bottom:1px solid #eee; margin-bottom:5px;">縣市：<span style="float:right;">${p.data.name}</span></div><div>種植(公頃)：<span style="float:right; font-weight:bold;">${p.data.p_area}</span></div><div>收穫(公頃)：<span style="float:right; font-weight:bold;">${p.data.h_area}</span></div><div>產量(公噸)：<span style="float:right; font-weight:bold;">${p.data.prod}</span></div><div style="margin-top:5px; color:#888; font-size:10px;">年度平均：</div><div>交易量(公噸)：<span style="float:right; font-weight:bold;">${p.data.year_avg_vol}</span></div><div style="color:#d32f2f;">平均價：<span style="float:right; font-weight:bold;">$${p.data.year_avg_price}</span></div>`; } }, visualMap: { min: 0, max: maxVal, left: "5%", bottom: "2%", itemHeight: 80, text: [Math.round(maxVal), "0"], textStyle: {fontSize: 9}, calculable: true, inRange: { color: ["#fff5f5", "#f28e8e", "#d32f2f"] } }, series: [{ type: "map", map: "taiwan", selectedMode: "multiple", nameProperty: "COUNTYNAME", label: { show: true, fontSize: 6 }, zoom: 5, center: [120.9738, 23.9738], data: mData }] });
                                    const preSelected = "'.$selected_cities_str.'".split(",").filter(x => x); preSelected.forEach(name => mapChart.dispatchAction({ type: "mapSelect", name: name }));
                                    mapChart.on("click", function(params) { if (params.componentType === "series") updateSelectionAndSubmit(params.name); });
                                });
                                var weatherChart = echarts.init(document.getElementById("weather-chart"));
                                weatherChart.setOption({ tooltip: { trigger: "axis" }, legend: { data: ["氣壓", "氣溫", "濕度", "降雨", "風速"], top: -5, textStyle: {fontSize: 8}, itemWidth: 8 }, dataZoom: [{ type: "slider", yAxisIndex: [0,1,2,3,4], right: 0, startValue: 0, endValue: 9, width: 12 }], grid: [{left: "13%", width: "11%"}, {left: "29%", width: "11%"}, {left: "45%", width: "11%"}, {left: "61%", width: "11%"}, {left: "77%", width: "11%"}], xAxis: [{gridIndex:0, name:"hPa", axisLabel:{rotate:90, fontSize:8}}, {gridIndex:1, name:"°C", axisLabel:{rotate:90, fontSize:8}}, {gridIndex:2, name:"%", axisLabel:{rotate:90, fontSize:8}}, {gridIndex:3, name:"mm", axisLabel:{rotate:90, fontSize:8}}, {gridIndex:4, name:"m/s", axisLabel:{rotate:90, fontSize:8}}], yAxis: [{gridIndex:0, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{fontSize:8, interval:0}}, {gridIndex:1, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false, interval:0}}, {gridIndex:2, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false, interval:0}}, {gridIndex:3, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false, interval:0}}, {gridIndex:4, type:"category", data:'.json_encode($wCities).', inverse:true, axisLabel:{show:false, interval:0}}], series: [{name:"氣壓", type:"bar", xAxisIndex:0, yAxisIndex:0, itemStyle:{color:"#5470c6"}, data:'.json_encode($wP).', label:{show:true, position:"right", fontSize:7}}, {name:"氣溫", type:"bar", xAxisIndex:1, yAxisIndex:1, itemStyle:{color:"#91cc75"}, data:'.json_encode($wT).', label:{show:true, position:"right", fontSize:7}}, {name:"濕度", type:"bar", xAxisIndex:2, yAxisIndex:2, itemStyle:{color:"#fac858"}, data:'.json_encode($wH).', label:{show:true, position:"right", fontSize:7}}, {name:"降雨", type:"bar", xAxisIndex:3, yAxisIndex:3, itemStyle:{color:"#ee6666"}, data:'.json_encode($wR).', label:{show:true, position:"right", fontSize:7}}, {name:"風速", type:"bar", xAxisIndex:4, yAxisIndex:4, itemStyle:{color:"#73c0de"}, data:'.json_encode($wW).', label:{show:true, position:"right", fontSize:7}}] });
                                var areaChart = echarts.init(document.getElementById("area-chart"));
                                areaChart.setOption({ tooltip: { trigger: "axis" }, legend: { data: ["種植面積", "收穫面積"], top: 0, itemWidth: 10, textStyle: {fontSize: 9} }, dataZoom: [{ type: "slider", yAxisIndex: 0, right: 0, startValue: 0, endValue: 9, width: 12 }], grid: { left: "16%", right: "12%", top: 25, bottom: 40 }, xAxis: { type: "value", axisLabel:{rotate:90, fontSize:8} }, yAxis: { type: "category", data: '.json_encode($aCities).', inverse: true, axisLabel:{fontSize:8, interval:0} }, series: [{ name: "種植面積", type: "bar", data: '.json_encode($p_area).', color: "#e0e0e0", barWidth: 12, z: 1 }, { name: "收穫面積", type: "bar", data: '.json_encode($h_area).', color: "#f28e8e", barWidth: 6, barGap: "-75%", z: 2, label:{show:true, position:"right", fontSize:8} }] });
                                window.addEventListener("resize", function() { trendChart.resize(); mapChart.resize(); weatherChart.resize(); areaChart.resize(); });
                            })();
                        </script>');
                });
            });
    }

public function exportReport(Request $request)
    {
        date_default_timezone_set('Asia/Taipei'); // 設定為 CST 時區

        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $year_range = $request->get('year_range');
        $yearsArr = explode(';', $year_range);
        $min_y = $yearsArr[0]; $max_y = $yearsArr[1] ?? $min_y;
        
        $crop_ids = $request->get('crop_ids', []); 
        $selected_cities_str = $request->get('selected_cities', ''); 
        $selected_cities_arr = array_filter(explode(',', $selected_cities_str));

        $cropWhere = ""; $cropParams = [];
        if (!empty($crop_ids)) {
            $placeholders = implode(',', array_fill(0, count($crop_ids), '?'));
            $cropWhere = " AND cp.crop_id IN ($placeholders) ";
            $cropParams = $crop_ids;
        }

        $cityWhere = ""; $cityParams = [];
        if (!empty($selected_cities_arr)) {
            $placeholders = implode(',', array_fill(0, count($selected_cities_arr), '?'));
            $cityWhere = " AND c.city_name IN ($placeholders) ";
            $cityParams = $selected_cities_arr;
        }

        // 1. 價格趨勢 SQL：透過 AS 加入單位 (元/公斤)
        $priceSql = "
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
        $dataPrice = DB::select($priceSql, array_merge([$start_date, $end_date], $cropParams));

        // 2. 天氣數據 SQL：透過 AS 加入五大氣象指標單位
        $weatherSql = "
            SELECT 
                w.date AS `日期`, 
                c.city_name AS `縣市`, 
                w.station_pressure AS `氣壓(hPa)`, 
                w.air_temperature AS `氣溫(°C)`, 
                w.relative_humidity AS `濕度(%)`, 
                w.precipitation AS `降雨量(mm)`, 
                w.wind_speed AS `風速(m/s)` 
            FROM weather w 
            JOIN city c ON w.city_id = c.city_id 
            WHERE w.date BETWEEN ? AND ? $cityWhere 
            ORDER BY w.date DESC
        ";
        $dataWeather = DB::select($weatherSql, array_merge([$start_date, $end_date], $cityParams));

        // 3. 面積數據 SQL：透過 AS 加入 (公頃) 與 (公噸)
        $areaSql = "
            SELECT 
                a.year AS `年份`, 
                cp.crop_name AS `作物`, 
                c.city_name AS `縣市`, 
                a.planted_area AS `種植面積(公頃)`, 
                a.harvested_area AS `收穫面積(公頃)`, 
                a.production AS `產量(公噸)` 
            FROM area_production a 
            JOIN city c ON a.city_id = c.city_id 
            JOIN crop cp ON a.crop_id = cp.crop_id 
            WHERE a.year BETWEEN ? AND ? $cropWhere $cityWhere 
            ORDER BY a.year DESC
        ";
        $dataArea = DB::select($areaSql, array_merge([$min_y, $max_y], $cropParams, $cityParams));

        $filename = "水果預測系統報表_" . date('Ymd_His') . ".xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: public"); header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        echo "\xEF\xBB\xBF"; 
        
        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
        
        // --- 第一個分頁：價格趨勢 ---
        $output .= "<Worksheet ss:Name=\"價格趨勢\"><Table>";
        if (!empty($dataPrice)) {
            $columns = array_keys((array)$dataPrice[0]); // 動態取得帶有單位的欄位名稱
            $output .= "<Row>"; foreach($columns as $col) { $output .= "<Cell><Data ss:Type=\"String\">$col</Data></Cell>"; } $output .= "</Row>";
            foreach($dataPrice as $r) {
                $output .= "<Row>"; foreach($columns as $col) { $val = $r->$col; $type = is_numeric($val) ? "Number" : "String"; $output .= "<Cell><Data ss:Type=\"$type\">$val</Data></Cell>"; } $output .= "</Row>";
            }
        }
        $output .= "</Table></Worksheet>";

        // --- 第二個分頁：天氣數據 ---
        $output .= "<Worksheet ss:Name=\"天氣數據\"><Table>";
        if (!empty($dataWeather)) {
            $columns = array_keys((array)$dataWeather[0]);
            $output .= "<Row>"; foreach($columns as $col) { $output .= "<Cell><Data ss:Type=\"String\">$col</Data></Cell>"; } $output .= "</Row>";
            foreach($dataWeather as $r) {
                $output .= "<Row>"; foreach($columns as $col) { $val = $r->$col; $type = is_numeric($val) ? "Number" : "String"; $output .= "<Cell><Data ss:Type=\"$type\">$val</Data></Cell>"; } $output .= "</Row>";
            }
        }
        $output .= "</Table></Worksheet>";

        // --- 第三個分頁：面積數據 ---
        $output .= "<Worksheet ss:Name=\"面積數據\"><Table>";
        if (!empty($dataArea)) {
            $columns = array_keys((array)$dataArea[0]);
            $output .= "<Row>"; foreach($columns as $col) { $output .= "<Cell><Data ss:Type=\"String\">$col</Data></Cell>"; } $output .= "</Row>";
            foreach($dataArea as $r) {
                $output .= "<Row>"; foreach($columns as $col) { $val = $r->$col; $type = is_numeric($val) ? "Number" : "String"; $output .= "<Cell><Data ss:Type=\"$type\">$val</Data></Cell>"; } $output .= "</Row>";
            }
        }
        $output .= "</Table></Worksheet>";

        $output .= "</Workbook>";
        echo $output;
        exit;
    }
}