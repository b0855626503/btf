<?php

namespace Gametech\Member\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Core\Models\ReferProxy;
use Gametech\Game\Models\GameProxy;
use Gametech\Game\Models\GameUserFreeProxy;
use Gametech\Game\Models\GameUserProxy;
use Gametech\Member\Contracts\Member as MemberContract;
use Gametech\Payment\Models\BankPaymentProxy;
use Gametech\Payment\Models\BankProxy;
use Gametech\Payment\Models\BillFreeProxy;
use Gametech\Payment\Models\BillProxy;
use Gametech\Payment\Models\BonusSpinProxy;
use Gametech\Payment\Models\PaymentPromotionProxy;
use Gametech\Payment\Models\PaymentWaitingProxy;
use Gametech\Payment\Models\WithdrawFreeProxy;
use Gametech\Payment\Models\WithdrawProxy;
use Gametech\Payment\Models\WithdrawSeamlessFreeProxy;
use Gametech\Payment\Models\WithdrawSeamlessProxy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
//use HighIdeas\UsersOnline\Traits\UsersOnlineTrait;

class Member extends Authenticatable implements MemberContract
{
    use Notifiable , LaravelSubQueryTrait;


//    public $with = ['bank'];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

//    public function receivesBroadcastNotificationsOn() {
//        return 'member.'.$this->code;
//    }


    protected $table = 'members';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

//    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $dates = ['date_create', 'date_update'];

    protected $primaryKey = 'code';

    protected $fillable = [
        'name', 'tel', 'password','session_id','refers','date_regis','lineid','userid','user_create','user_update',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function memberweb()
    {
        return $this->hasOne(MemberWeb::class, 'member_code')->where('enable','Y');
    }

    public function memberbank()
    {
        return $this->hasOne(MemberBank::class, 'member_code');
    }

    protected static function booted()
    {
        static::addGlobalScope('code', function (Builder $builder) {
            $builder->where('members.code', '>', 0);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('members.enable','Y');
    }

    public function scopeInactive($query)
    {
        return $query->where('members.enable','N');
    }

    public function scopeConfirm($query)
    {
        return $query->where('members.confirm','Y');
    }

    public function scopeWaiting($query)
    {
        return $query->where('members.confirm','N');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankProxy::modelClass(), 'bank_code');
    }

    public function referCode(): BelongsTo
    {
        return $this->belongsTo(ReferProxy::modelClass(), 'refers');
    }

    public function up(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upline_code');
    }

    public function downs(): HasMany
    {
        return $this->hasMany(MemberProxy::modelClass(), 'upline_code');
    }

    public function down()
    {
        return $this->hasMany(MemberProxy::modelClass(), 'upline_code');
    }

    public function memberIc(): HasMany
    {
        return $this->hasMany(MemberIcProxy::modelClass(), 'member_code');
    }

    public function memberCash(): HasMany
    {
        return $this->hasMany(MemberCashbackProxy::modelClass(), 'downline_code');
    }

    public function parentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function games(): HasMany
    {
        return $this->hasMany(GameProxy::modelClass());
    }


    public function gamesUser(): HasMany
    {
        return $this->hasMany(GameUserProxy::modelClass(), 'member_code');
    }

    public function gamesUserFree(): HasMany
    {
        return $this->hasMany(GameUserFreeProxy::modelClass(), 'member_code');
    }

    public function gameUser(): HasOne
    {
        return $this->hasOne(GameUserProxy::modelClass(), 'member_code');
    }

    public function gameUserFree(): HasOne
    {
        return $this->hasOne(GameUserFreeProxy::modelClass(), 'member_code');
    }


    public function bankPayments(): HasMany
    {
        return $this->hasMany(BankPaymentProxy::modelClass(), 'member_topup','code');
    }

    public function bank_payments()
    {
        return $this->bankPayments()->where('status',1)->where('enable','Y');
    }

    public function topupSum()
    {
        return $this->hasMany(BankPaymentProxy::modelClass(), 'member_topup','code')->where('status',1)->where('enable','Y')->sum('value');
    }

    public function last_payment()
    {
        return $this->hasOne(BankPaymentProxy::modelClass(), 'member_topup','code')->where('enable','Y')->orderByDesc('date_topup');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(BillProxy::modelClass(),'member_code');
    }

    public function is_pro(): HasMany
    {
        return $this->bills()->where('enable','Y')->where('pro_code','<>',0);
    }

    public function billsFree(): HasMany
    {
        return $this->hasMany(BillFreeProxy::modelClass(),'member_code');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MemberLogProxy::modelClass(), 'member_code');
    }

    public function withdraw(): HasMany
    {
        return $this->hasMany(WithdrawProxy::modelClass(), 'member_code');
    }

    public function withdrawFree(): HasMany
    {
        return $this->hasMany(WithdrawFreeProxy::modelClass(), 'member_code');
    }

    public function withdrawSeamless(): HasMany
    {
        return $this->hasMany(WithdrawSeamlessProxy::modelClass(), 'member_code');
    }

    public function withdrawSeamlessFree(): HasMany
    {
        return $this->hasMany(WithdrawSeamlessFreeProxy::modelClass(), 'member_code');
    }

    public function paymentsPromotion(): HasMany
    {
        return $this->hasMany(PaymentPromotionProxy::modelClass(), 'member_code');
    }

    public function paymentPromotion(): HasOne
    {
        return $this->hasOne(PaymentPromotionProxy::modelClass(), 'member_code');
    }

    public function paymentWaiting(): HasMany
    {
        return $this->hasMany(PaymentWaitingProxy::modelClass());
    }

    public function memberFreeCredit(): HasMany
    {
        return $this->hasMany(MemberFreeCreditProxy::modelClass(),'member_code');
    }

    public function bonus_spin(): HasMany
    {
        return $this->hasMany(BonusSpinProxy::modelClass(), 'member_code');
    }

    public function memberTran(): HasMany
    {
        return $this->hasMany(MemberCreditLogProxy::modelClass(),'member_code');
    }

    public function wallet_transaction(): MorphTo
    {
        return $this->morphTo();
    }

    public function bill()
    {
        return $this->hasOne(BillProxy::modelClass(),'member_code');
    }

    public function member_cashback()
    {
        return $this->hasOne(MemberCashbackProxy::modelClass(),'downline_code','member_code');
    }

    public function member_ic()
    {
        return $this->hasOne(MemberIcProxy::modelClass(),'downline_code','member_code');
    }

    public function upline()
    {
        return $this->hasOne(MemberProxy::modelClass(),'code','upline_code');
//        return $this->belongsTo(self::class, 'upline_code','code');
    }


    public function memberReward(): HasMany
    {
        return $this->hasMany(MemberRewardLogProxy::modelClass(),'member_code');
    }

    public function member_remark()
    {
        return $this->hasMany(MemberRemarkProxy::modelClass(),'member_code');
    }

    public function memberCreditFree(): HasMany
    {
        return $this->hasMany(MemberCreditFreeLogProxy::modelClass(),'member_code');
    }


    public function receivesBroadcastNotificationsOn() { return env('APP_NAME').'_members.'.$this->code; }

    public function payment_first()
    {
        return $this->hasOne(BankPaymentProxy::modelClass(), 'member_topup', 'code')->where('enable', 'Y')->where('status', 1)->oldest();
    }

    public function payment()
    {
        return $this->hasMany(BankPaymentProxy::modelClass(), 'member_topup', 'code')->where('enable', 'Y')->where('status', 1);
    }

    public function payout()
    {
        return $this->hasMany(WithdrawSeamlessProxy::modelClass(), 'member_code', 'code')->where('enable', 'Y')->where('status', 1);
    }

}


