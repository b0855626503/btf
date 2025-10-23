<?php

namespace Gametech\Payment\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Admin\Models\AdminProxy;
use Gametech\Game\Models\GameUserProxy;
use Gametech\Member\Models\MemberBankProxy;
use Gametech\Member\Models\MemberProxy;
use Gametech\Member\Models\MemberRemarkProxy;
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Payment\Contracts\Withdraw as WithdrawContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Withdraw extends Model implements WithdrawContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'deposits';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';


    protected $dates = ['date_create', 'date_update'];

    protected $primaryKey = 'code';

    protected $fillable = [
        'member_code',
        'webcode',
        'transection_type',
        'member_user',
        'bankm',
        'amount',
        'date_record',
        'timedept',
        'ip',
        'user_create',
        'date_create',
        'txid',
        'papaya_ref',
        'enable',
        'userid',
        'web_code',
        'credit_1',
        'status_withdraw',
        'ck_withdraw',
        'ck_user',
        'oldcredit',
        'aftercredit',
        'ck_date',
    ];


    public function scopeActive($query)
    {
        return $query->where('enable', 'Y');
    }

    public function scopeInactive($query)
    {
        return $query->where('enable', 'N');
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 0);
    }

    public function scopeComplete($query)
    {
        return $query->where('status', 1);
    }

    public function member()
    {
        return $this->belongsTo(MemberProxy::modelClass(), 'member_code');
    }

    public function memberWeb()
    {
        return $this->belongsTo(MemberWebProxy::modelClass(), 'member_user','user');
    }

    public function website(){
        return $this->belongsTo(WebsiteProxy::modelClass(),'webcode');
    }

    public function memberBank(){
        return $this->belongsTo(MemberBankProxy::modelClass(),'bankm');
    }




}
