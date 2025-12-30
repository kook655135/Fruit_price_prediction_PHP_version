<?php

namespace App\Admin\Controllers;

use App\Models\PricePrediction;
use App\Models\Crop;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;
use App\Admin\Actions\Grid\UpdatePriceRow;
use App\Admin\Actions\Grid\DeletePriceRow;

class PricePredictionController extends AdminController
{
    protected $title = '價格數據維護';

    protected function grid()
    {
        $grid = new Grid(new PricePrediction());
        $grid->model()->orderBy('date', 'desc');

        $grid->column('date', '日期')->sortable();
        $grid->column('crop.crop_name', '作物名稱');

        // --- 核心修正：模式欄位 ---
        $grid->column('mode', '模式')->display(function ($mode) {
            $map = [
                'actual' => '實際值',
                'prediction' => '預測值'
            ];
            $text = $map[$mode] ?? $mode;

            // 判斷當前請求是否為匯出 (Export)
            // 如果 URL 帶有 _export_= 選項，則只回傳純文字
            if (request()->has('_export_')) {
                return $text;
            }

            // 否則，在網頁上顯示帶有標籤顏色的 HTML
            $style = ($mode === 'actual') ? 'info' : 'success';
            return "<span class='label label-{$style}'>{$text}</span>";
        });

        $grid->column('price', '價格(元/公斤)')->sortable();

        // --- 核心修正：篩選器設定 ---
        $grid->filter(function($filter){

            // 1. 禁用預設的 ID 篩選框
            $filter->disableIdFilter();

            // 2. 加入日期範圍篩選 (讓使用者選一段時間)
            $filter->between('date', '日期')->date();

            // 3. 加入水果下拉選單 (精準篩選特定作物)
            $filter->equal('crop_id', '選擇水果')->select(
                \App\Models\Crop::pluck('crop_name', 'crop_id')
            );

            // 4. 加入模式篩選 (選實際值或預測值)
            $filter->equal('mode', '模式')->select([
                'actual' => '實際值',
                'prediction' => '預測值'
            ]);
        });

        $grid->actions(function ($actions) {
            $actions->disableView();
            $actions->disableEdit();
            $actions->disableDelete();
            $actions->add(new UpdatePriceRow()); 
            $actions->add(new DeletePriceRow());
        });

        return $grid;
    }

    /**
     * 【修正】手動處理「查看」頁面，並禁用右上角會報錯的按鈕
     */
    protected function detail($id)
    {
        $model = $this->resolveModel($id);
        $show = new Show($model);

        // --- 核心修正：拔掉右上角的 Edit 與 Delete 按鈕 ---
        $show->panel()->tools(function ($tools) {
            $tools->disableEdit();   // 禁用詳情頁的「編輯」按鈕，防止 ID 報錯
            $tools->disableDelete(); // 禁用詳情頁的「刪除」按鈕
            // 如果你想留著「列表」按鈕可以不寫 disableList()
        });
        // ---------------------------------------------

        $show->field('date', '日期');
        $show->field('crop.crop_name', '作物名稱');
        $show->field('mode', '模式')->using(['actual' => '實際值', 'prediction' => '預測值']);
        $show->field('price', '價格(元/公斤)');

        return $show;
    }

    /**
     * 手動處理「編輯」頁面，攔截 Unknown column id 錯誤
     */
    public function edit($id, Content $content)
    {
        $model = $this->resolveModel($id);

        return $content
            ->title($this->title())
            ->description($this->description['edit'] ?? trans('admin.edit'))
            ->body($this->form()->edit($model));
    }

    /**
     * 手動處理「更新」邏輯
     */
    public function update($id)
    {
        $model = $this->resolveModel($id);
        return $this->form()->update($id, $model->getAttributes());
    }

    /**
     * 輔助方法：將虛擬 ID 拆解並找出資料物件
     */
    private function resolveModel($id)
    {
        $parts = explode('|', $id);
        if (count($parts) !== 3) {
            return PricePrediction::where('date', $id)->firstOrFail();
        }

        return PricePrediction::where('date', $parts[0])
            ->where('crop_id', $parts[1])
            ->where('mode', $parts[2])
            ->firstOrFail();
    }

    protected function form()
    {
        $form = new Form(new PricePrediction());

        $form->date('date', '日期')->required();
        $form->select('crop_id', '水果')->options(Crop::pluck('crop_name', 'crop_id'))->required();
        $form->select('mode', '模式')->options(['actual' => '實際值', 'prediction' => '預測值'])->required();
        $form->decimal('price', '價格')->required();

        $form->footer(function ($footer) {
            $footer->disableEditingCheck(); // 禁用新增成功後的「繼續編輯」勾選框
        });

        $form->saving(function (Form $form) {
            $exists = DB::table('price_prediction')
                ->where('date', $form->date)
                ->where('crop_id', $form->crop_id)
                ->where('mode', $form->mode)
                ->exists();

            if ($exists && $form->isCreating()) {
                $error = new \Illuminate\Support\MessageBag([
                    'title'   => '重複輸入',
                    'message' => '該資料組合已存在，請直接修改現有數據。',
                ]);
                return back()->with(compact('error'));
            }
        });

        return $form;
    }
}