<?php

namespace Gametech\Admin\Transformers;


use Gametech\Game\Contracts\Game;
use Gametech\Game\Contracts\GameSeamless;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Fractal\TransformerAbstract;

class GameSeamlessTransformer extends TransformerAbstract
{


    public function transform(GameSeamless $model)
    {




        return [
            'code' => (int)$model->code,
            'name' => $model->name,
            'game_type' => $model->game_type,
//            'user_demo' => $model->user_demo,
//            'user_demofree' => $model->user_demofree,
//            'demo' => 'Real : ' . $model->user_demo . '<br> Free : ' . $model->user_demofree,
//            'account' => 'Real : ' . number_format($normal) . '<br> Free : ' . number_format($free),
//            'status' => $game_status,
//            'auto_open' => '<button type="button" class="btn ' . ($model->auto_open == 'Y' ? 'btn-success' : 'btn-danger') . ' btn-xs icon-only" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->auto_open) . "'" . "," . "'auto_open'" . ')">' . ($model->auto_open == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>',
            'status_open' => '<button type="button" class="btn ' . ($model->status_open == 'Y' ? 'btn-success' : 'btn-danger') . ' btn-xs icon-only" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->status_open) . "'" . "," . "'status_open'" . ')">' . ($model->status_open == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>',
//            'batch_game' => ($model->batch_game == 'Y' ? 'เพิ่มที่เมนู Batch User' : 'สมัครได้ทันที'),
            'enable' => '<button type="button" class="btn ' . ($model->enable == 'Y' ? 'btn-success' : 'btn-danger') . ' btn-xs icon-only" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->enable) . "'" . "," . "'enable'" . ')">' . ($model->enable == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>',
            'filepic' => '<img src="' . Storage::url('game_img/' . $model->filepic) . '" class="rounded" style="width:50px;height:50px;">',
            'icon' => '<img src="' . Storage::url('icon_img/' . $model->icon) . '" class="rounded" style="width:50px;height:50px;">',
            'action' => view('admin::module.game_seamless.datatables_actions', ['code' => $model->code])->render(),
        ];
    }


}
