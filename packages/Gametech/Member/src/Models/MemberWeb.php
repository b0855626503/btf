<?php

namespace Gametech\Member\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Core\Models\WebsiteProxy;
use Gametech\Payment\Models\BankPaymentProxy;
use Gametech\Payment\Models\WithdrawProxy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Gametech\Member\Contracts\MemberWeb as MemberWebContract;

class MemberWeb extends Model implements MemberWebContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'members_web';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $dates = ['date_create', 'date_update'];

    protected $primaryKey = 'code';

    protected static function booted()
    {
        static::addGlobalScope('enable', function (Builder $builder) {
            $builder->where('members_web.enable', 'Y');
        });
    }

    public function user()
    {
        return $this->belongsTo(MemberProxy::modelClass(), 'member_code');
    }

    public function me()
    {
        return $this->belongsTo(MemberProxy::modelClass(), 'member_code');
    }

    public function web(){
        return $this->belongsTo(WebsiteProxy::modelClass(),'web_code');
    }

    public function bankPayments(){
        return $this->hasMany(BankPaymentProxy::modelClass(),'tranferer','user');
    }

    public function withdraws(){
        return $this->hasMany(WithdrawProxy::modelClass(),'member_code','member_code');
    }

    public function memberBank(){
        return $this->hasMany(MemberBankProxy::modelClass(),'member_code','member_code');
    }
}
