<?php

namespace Gametech\Admin\Transformers;


use Gametech\Member\Contracts\Member;
use League\Fractal\TransformerAbstract;


class MemberTransformer extends TransformerAbstract
{

    protected $config;

//    protected $admin;

    public function __construct($config)
    {
        $this->config = $config;
//        $this->admin = Auth::guard('admin')->user();
    }


    public function transform(Member $model)
    {


//        dd($model->toJson(JSON_PRETTY_PRINT));

        $config = $this->config;
//        $admin = $this->admin;

        if (bouncer()->hasPermission('wallet.member.tel')) {
            $tel = $model->tel;
        } else {
            $tel = '*****';
//            $tel = StarReplacer::replace($model->tel);
        }

        if (bouncer()->hasPermission('wallet.member.password')) {
            $pass = $model->user_pass;
        } else {
            $pass = '*****';
//            $tel = StarReplacer::replace($model->tel);
        }

        if ($config->seamless === 'Y') {

            $pro = '<button class="btn btn-xs icon-only ' . ($model->promotion == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->promotion) . "'" . "," . "'promotion'" . ')">' . ($model->promotion == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';
//            $pro = '<button class="btn btn-xs icon-only ' . ($model->status_pro == 1 ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flipnum($model->status_pro) . "'" . "," . "'status_pro'" . ')">' . ($model->status_pro == 1 ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';
            $newuser = '<button class="btn btn-xs icon-only ' . ($model->status_pro == 1 ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flipnum($model->status_pro) . "'" . "," . "'status_pro'" . ')">' . ($model->status_pro == 1 ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';


        } else {


            if ($config->multigame_open === 'Y') {
                $pro = '';
//            $pro = '<button class="btn btn-xs icon-only ' . ($model->promotion == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->promotion) . "'" . "," . "'promotion'" . ')">' . ($model->promotion == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';
                $newuser = '<button class="btn btn-xs icon-only ' . ($model->status_pro == 1 ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flipnum($model->status_pro) . "'" . "," . "'status_pro'" . ')">' . ($model->status_pro == 1 ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';

            } else {

                $user = core()->getGameUser($model->code);
                $model->balance = $user['balance'];

                $pro = '<button class="btn btn-xs icon-only ' . ($model->promotion == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->promotion) . "'" . "," . "'promotion'" . ')">' . ($model->promotion == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';
//            $pro = '<button class="btn btn-xs icon-only ' . ($model->status_pro == 1 ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flipnum($model->status_pro) . "'" . "," . "'status_pro'" . ')">' . ($model->status_pro == 1 ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';
                $newuser = '<button class="btn btn-xs icon-only ' . ($model->status_pro == 1 ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flipnum($model->status_pro) . "'" . "," . "'status_pro'" . ')">' . ($model->status_pro == 1 ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>';

            }
        }

        return [
            'code' => (int)$model->code,
            'date_regis' => $model->date_create->format('d/m/Y H:i:s'),
            'firstname' => htmlentities(strip_tags($model->firstname)),
            'lastname' => htmlentities(strip_tags($model->lastname)),
            'up' => ($model->upline_code == 0 ? '' : (is_null($model->up) ? '' : $model->up->name)),
            'down' => $model->downs_count,
            'bank' => (is_null($model->bank) ? '' : core()->displayBank($model->bank->shortcode, $model->bank->filepic)),
            'acc_no' => htmlentities(strip_tags($model->acc_no)),
            'user_name' => $model->user_name,
            'tel' => htmlentities(strip_tags($tel)),
            'wallet' => (is_null($model->wallet_id) ? htmlentities(strip_tags($tel)) : htmlentities(($model->wallet_id))),
            'pass' => $pass,
            'remark' => (is_null($model->member_remark->first()) ? $model->remark : $model->member_remark->first()->remark),
            'lineid' => htmlentities(strip_tags($model->lineid)),
            'deposit' => $model->count_deposit,
            'point' => "<span class='text-primary'>" . $model->point_deposit . "</span>",
            'balance' => "<span class='text-success'>" . $model->balance . "</span>",
            'diamond' => "<span class='text-indigo'>" . $model->diamond . "</span>",
            'pro' => $pro,
            'newuser' => $newuser,
            'enable' => '<button class="btn btn-xs icon-only ' . ($model->enable == 'Y' ? 'btn-success' : 'btn-danger') . '" onclick="editdata(' . $model->code . "," . "'" . core()->flip($model->enable) . "'" . "," . "'enable'" . ')">' . ($model->enable == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>') . '</button>',
            'action' => view('admin::module.member.datatables_actions', ['code' => $model->code])->render(),
        ];
    }


}
