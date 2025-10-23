<?php

namespace Gametech\Auto\Jobs;


use Gametech\Core\Models\CouponList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;


class BatchCoupon implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $tries = 1;

    public $maxExceptions = 3;

    public $retryAfter = 3;

    protected $items;



    public function __construct($items)
    {
        $this->items = $items;

    }


    public function handle(): bool
    {
        $items = collect($this->items)->toArray();


        return CouponList::insert($items);

    }
}
