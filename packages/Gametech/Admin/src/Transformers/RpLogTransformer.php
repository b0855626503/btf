<?php

namespace Gametech\Admin\Transformers;

use Gametech\Core\Contracts\Log;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class RpLogTransformer extends TransformerAbstract
{


    public function transform(Log $model)
    {
        if($model->menu == 'members'){
            $user_name = (is_null($model->user) ? '' : $model->user->user_name);
        }else{
            $user_name = $model->record;
        }


        return [
            'code' => (int)$model->code,
            'mode' => $model->mode,
            'menu' => $model->menu,
            'emp' => (is_null($model->admin) ? '-' : $model->admin->user_name),
            'record' => $model->record,
            'user_name' => $user_name,
            'item_before' => $model->item_before,
            'item' => $model->item,
            'ip' => $model->ip,
            'date_create' => core()->formatDate($model->date_create,'Y-m-d H:i:s')
        ];
    }


}
