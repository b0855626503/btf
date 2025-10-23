<?php

namespace Gametech\Admin\Transformers;



use Gametech\Game\Contracts\GameUser;
use League\Fractal\TransformerAbstract;

class GameUserTransformer extends TransformerAbstract
{


    public function transform(GameUser $model)
    {

        if($model->pro_code > 0){
            $pro = '<button class="btn btn-xs icon-only btn-default" onclick="resetpro(' . $model->code . ')"><i class="fa-solid fa-person-praying"></i></button>';
        }else{
            $pro = '';
        }

        return [
            'code' => (int)$model->code,
            'user_name' => $model->user_name,
            'turnpro' => $model->turnpro,
            'amount_balance' => $model->amount_balance,
            'withdraw_limit_rate' => $model->withdraw_limit_rate,
            'withdraw_limit_amount' => $model->withdraw_limit_amount,
            'reset' => $pro,
            'action' => view('admin::module.game_user.datatables_actions', ['code' => $model->code])->render(),
        ];
    }


}
