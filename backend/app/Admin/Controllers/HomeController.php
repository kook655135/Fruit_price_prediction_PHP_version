<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->title('Dashboard')
            ->description('水果價格預測系統')
            ->row(function (Row $row) {
                $row->column(12, function (Column $column) {
                    $column->append('<h3>歡迎使用後台管理系統</h3>');
                });
            });
    }
}