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


        return [
            'code' => (int)$model->code,
            'date_regis' => $model->date_create->format('d/m/Y'),
            'name' => $model->name,
            'tel' => $model->tel,
            'email' => $model->email,
            'refers' => $model->referCode->name,
            'user_create' => $model->user_create,

            'action' => view('admin::module.member.datatables_actions', ['code' => $model->code])->render(),
        ];
    }


}
