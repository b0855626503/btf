<?php

namespace Gametech\TelegramBot\Transformers;

use Gametech\TelegramBot\Models\TelegramCustomerMenuProxy;
use League\Fractal\TransformerAbstract;

class TelegramCustomerMenuTransformer extends TransformerAbstract
{
    public function transform(TelegramCustomerMenuProxy $model)
    {

        return [
            'id' => (int) $model->id,
            'title' => $model->title,
            'type' => $model->type,
            'value' => $model->value,
            'active' => '<button class="btn btn-xs icon-only '.($model->active == 1 ? 'btn-success' : 'btn-danger').'" onclick="editdata('.$model->id.','."'".core()->flipnum($model->active)."'".','."'active'".')">'.($model->active == 1 ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>').'</button>',
            'action' => view('telegrambot::module.telegram_customer_menu.datatables_actions', ['code' => $model->id])->render(),
        ];
    }
}
