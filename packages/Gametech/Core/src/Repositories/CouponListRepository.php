<?php

namespace Gametech\Core\Repositories;

use App\Libraries\CouponGenerator;
use Gametech\Auto\Jobs\BatchCoupon;
use Gametech\Core\Eloquent\Repository;
use Gametech\Core\Models\CouponListProxy;
use Illuminate\Container\Container as App;

class CouponListRepository extends Repository
{

    private $generator;

    public function __construct(
        CouponGenerator $generator,
        App $app

    )
    {
        $this->generator = $generator;
        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Core\Contracts\CouponList';
    }

    public function genCouponList($code)
    {

        $datenow = now()->toDateTimeString();

        $main = app('Gametech\Core\Repositories\CouponRepository')->findOneWhere(['enable' => 'Y', 'gen' => 'N', 'code' => $code]);
        if (isset($main)) {
            if ($main['same_coupon'] === 'Y') {
                $coupon = $this->generate(1);
                $items = [];
                for ($i = 1; $i <= $main['amount']; $i++) {
                    $items[] = [
                        'coupon_code' => $main['code'],
                        'name' => $coupon[0],
                        'cashback' => $main['cashback'],
                        'amount' => 1,
                        'value' => $main['value'],
                        'turnpro' => $main['turnpro'],
                        'amount_limit' => $main['amount_limit'],
                        'money' => $main['money'],
                        'enable' => $main['enable'],
                        'date_start' => $main['date_start'],
                        'date_stop' => $main['date_stop'],
                        'date_expire' => $main['date_expire'],
                        'user_create' => 'SYSTEM',
                        'user_update' => 'SYSTEM',
                        'date_create' => $datenow,
                        'date_update' => $datenow,
                    ];
                }

            } else {
                $coupons = $this->generate($main['amount']);
                $items = [];
                foreach ($coupons as $coupon) {
                    $items[] = [
                        'coupon_code' => $main['code'],
                        'name' => $coupon,
                        'cashback' => $main['cashback'],
                        'amount' => 1,
                        'value' => $main['value'],
                        'turnpro' => $main['turnpro'],
                        'amount_limit' => $main['amount_limit'],
                        'money' => $main['money'],
                        'enable' => $main['enable'],
                        'date_start' => $main['date_start'],
                        'date_stop' => $main['date_stop'],
                        'date_expire' => $main['date_expire'],
                        'user_create' => 'SYSTEM',
                        'user_update' => 'SYSTEM',
                        'date_create' => $datenow,
                        'date_update' => $datenow,
                    ];
                }

            }

            BatchCoupon::dispatch($items);

            return true;
        } else {
            return false;
        }
    }

    public function genCode($same)
    {

    }

    public function generate(int $amount = 1): array
    {
        $codes = [];

        for ($i = 1; $i <= $amount; $i++) {
            $codes[] = $this->getUniqueCoupon();
        }

        return $codes;
    }

    protected function getUniqueCoupon(): string
    {
        $coupon = $this->generator->generateUnique();

        while (CouponListProxy::whereCode($coupon)->count() > 0) {
            $coupon = $this->generator->generateUnique();
        }

        return $coupon;
    }
}
