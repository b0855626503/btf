<?php

namespace Gametech\Admin\Transformers;


use Carbon\Carbon;
use Gametech\Member\Contracts\MemberCreditLog;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class SeamlessTransformer extends TransformerAbstract
{

    protected $no;

    public function __construct($no = 1)
    {
        $this->no = $no;

    }

    public function transform(array $model)
    {

//        dd($model);
        return [
            'code' => ++$this->no,
            'username' => $model['username'],
            'betStatus' => $model['betStatus'],
            'gameName' => $model['gameName'],
            'stake' => $model['stake'],
            'payoutStatus' => $model['payoutStatus'],
            'payout' => $model['payout'],
            'betId' => $model['betId'],
            'roundId' => $model['roundId'],
            'updatedDate' => Carbon::parse($model['updatedDate'])->setTimezone('Asia/Bangkok')->format('Y-m-d H:i:s'),
        ];
    }


}
