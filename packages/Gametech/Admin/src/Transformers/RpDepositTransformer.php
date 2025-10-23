<?php

namespace Gametech\Admin\Transformers;

use Gametech\Payment\Contracts\BankPayment;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class RpDepositTransformer extends TransformerAbstract
{


    public function transform(BankPayment $model)
    {
        if($model->enable == 'Y'){
            $code = (int)$model->code;
        }else{
            if (bouncer()->hasPermission('wallet.rp_deposit.edit')) {
                $code = '<button class="btn btn-xs icon-only ' . ($model->enable == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->enable) . "'" . "," . "'enable'" . ')">' . ($model->enable == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-reply"></i>') . '</button>';
            }else{
                $code = (int)$model->code;
            }
        }

        return [
            'code' => $code,
            'bank_raw' => $model->bank_account->bank->name_th,
            'money' => $model->value,
            'bank' => (is_null($model->bank_account) ? '' : core()->displayBank($model->bank_account->bank->shortcode, $model->bank_account->bank->filepic)),
            'acc_no' => (is_null($model->bank_account) ? '' : $model->bank_account->acc_no),
            'date' => $model->bank_time->format('d/m/y H:i:s'),
            'date_create' => (is_null($model->date_create) ? '' : $model->date_create->format('d/m/y H:i:s')),
            'date_approve' => (is_null($model->date_approve) ? '' : $model->date_approve->format('d/m/y H:i:s')),
            'channel' => '<span class="text-long" data-toggle="tooltip" title="' . $model->channel . '">' . Str::limit($model->channel, 10) . '</span>',
            'detail' => $model->detail,
            'amount' => core()->textcolor(core()->currency($model->value), 'text-success'),
            'member_name' => (is_null($model->member) ? '' : $model->member->name),
            'user_name' => (is_null($model->member) ? '' : $model->member->user_name),
            'remark' => $model->remark_admin,
            'emp_name' => ($model->emp_topup === 0 ? ($model->create_by ? $model->create_by : $model->topup_by) : (is_null($model->admin) ? '' : $model->admin->user_name)),
            'ip' => ($model->emp_topup === 0 ? '127.0.0.1' : $model->ip_admin)
        ];
    }


}
