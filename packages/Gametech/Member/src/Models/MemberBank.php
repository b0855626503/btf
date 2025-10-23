<?php

namespace Gametech\Member\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Payment\Models\BankProxy;
use Illuminate\Database\Eloquent\Model;
use Gametech\Member\Contracts\MemberBank as MemberBankContract;

class MemberBank extends Model implements MemberBankContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'members_bank';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $dates = ['date_create', 'date_update'];

    protected $primaryKey = 'code';


//    public function bank()
//    {
//        return $this->hasOne(BankProxy::modelClass(), 'code','bank_code');
//    }

    public function bank()
    {
        return $this->belongsTo(BankProxy::modelClass(),'bank_code');
    }

    public function user()
    {
        return $this->belongsTo(MemberProxy::modelClass(), 'member_code');
    }
}
