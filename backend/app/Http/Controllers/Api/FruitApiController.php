<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PricePrediction;
use App\Models\Crop;
use Illuminate\Http\Request;
class FruitApiController extends Controller {
public function getCrops() { return response()->json(Crop::all(['crop_id', 'crop_name'])); }
public function getPrices(Request $request) { $query = PricePrediction::with('crop'); if ($request->crop_id) { $query->where('crop_id', $request->crop_id); } if ($request->start_date) { $query->where('date', '>=', $request->start_date); } if ($request->end_date) { $query->where('date', '<=', $request->end_date); } $data = $query->orderBy('date', 'desc')->get()->map(function($item) { return ['日期' => $item->date, '作物' => $item->crop->crop_name ?? '未知', '價格(元/公斤)' => $item->price]; }); return response()->json(['status' => 'success', 'data' => $data]); } }