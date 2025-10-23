<?php

namespace Gametech\Payment\Observers;

use App\Events\SumNewPayment;
use Gametech\Auto\Jobs\CheckPayments as CheckPaymentsJob;
use Gametech\Auto\Jobs\TopupPayments as TopupPaymentsJob;
use Gametech\Core\Models\Log;
use Gametech\LogAdmin\Http\Traits\ActivityLogger;
use Gametech\Payment\Models\BankAccount;
use Gametech\Payment\Models\BankPayment as EventData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

class BankPaymentObserver
{
    use ActivityLogger;

    /** ★ bump ถ้าเปลี่ยนสูตรนับ */
    private const COUNTER_VERSION      = 6;

    /** base keys */
    private const BANK_IN_WAITING_KEY  = 'bank_in_waiting_count';
    private const BANK_IN_WAITING_LOCK = 'lock:recount_bank_in_waiting';

    private function counterKey(): string   { return self::BANK_IN_WAITING_KEY  . ':v' . self::COUNTER_VERSION; }
    private function counterLock(): string  { return self::BANK_IN_WAITING_LOCK . ':v' . self::COUNTER_VERSION; }

    /** แปลงโมเดลเป็น array อย่างปลอดภัย */
    private function attrs($model): array
    {
        return is_array($model) ? $model : ($model->getAttributes() ?? []);
    }

    /**
     * เกณฑ์ “รอ” แบบ simplified:
     * - status = 0
     * - enable = 'Y'
     */
    private function isWaiting(array $attrs): bool
    {
        return (int)($attrs['status'] ?? -1) === 0
            && (string)($attrs['enable'] ?? 'N') === 'Y';
    }

    /** เป็นรายการของ "วันนี้" หรือไม่ (เทียบแบบ same-day ตาม timezone ของแอป) */
    private function isToday(array $attrs): bool
    {
        if (empty($attrs['date_create'])) return false;
        return Carbon::parse($attrs['date_create'])->isSameDay(today());
    }

    /** สูตรนับจริง: "รอ" + "เป็นวันนี้" */
    private function isCountableTodayWaiting(array $attrs): bool
    {
        return $this->isWaiting($attrs) && $this->isToday($attrs);
    }

    /** อ่านค่า counter ปัจจุบันจาก Redis */
    private function currentWaitingCount(): int
    {
        return (int) Cache::store('redis')->get($this->counterKey(), 0);
    }

    /** inc/dec แบบกันติดลบ */
    private function inc(): void
    {
        Cache::store('redis')->increment($this->counterKey());
    }
    private function dec(): void
    {
        $v = Cache::store('redis')->decrement($this->counterKey());
        if ($v < 0) {
            Cache::store('redis')->forever($this->counterKey(), 0);
        }
    }

    /**
     * Bootstrap ค่าตั้งต้น (COUNT จาก DB) ครั้งแรกเท่านั้น
     * ใช้สูตรเดียวกับ isCountableTodayWaiting(): status=0, enable='Y', date_create=วันนี้
     */
    private function ensureCounterBootstrapped(): void
    {
        $store = Cache::store('redis');
        if ($store->has($this->counterKey())) return;

        $lock = $store->lock($this->counterLock(), 30);
        if ($lock->get()) {
            try {
                if (!$store->has($this->counterKey())) {
                    $count = app('Gametech\Payment\Repositories\BankPaymentRepository')
                        ->where('status', 0)          // ✅ ตรงกับ isWaiting()
                        ->where('enable','Y')
                        ->whereDate('date_create', today()) // ✅ ตรงกับ isToday()
                        ->count();
                    $store->forever($this->counterKey(), $count);
                }
            } finally {
                optional($lock)->release();
            }
            return;
        }

        // มีโปรเซสอื่นกำลังบูต → รอสักครู่
        $deadline = microtime(true) + 5.0;
        while (!$store->has($this->counterKey()) && microtime(true) < $deadline) {
            usleep(50_000);
        }

        // กันกรณีบูตล่ม → set ค่าเริ่มต้น (ไม่ใช่ increment)
        if (!$store->has($this->counterKey())) {
            $count = app('Gametech\Payment\Repositories\BankPaymentRepository')
                ->where('status', 0)
                ->where('enable','Y')
                ->whereDate('date_create', today())
                ->count();
            $store->forever($this->counterKey(), $count);
        }
    }

    /** broadcast โดยใช้ค่าจาก Redis */
    private function broadcastCount(string $action, string $code): void
    {
        $this->ensureCounterBootstrapped();
        $bank_in = $this->currentWaitingCount();
        broadcast(new SumNewPayment($bank_in, $action, $code));
    }

    // ─────────────────────────────────────────────────────────────────────────────

    public function created(EventData $data)
    {
//        // งานต่อพ่วงฝั่งธนาคาร/ตรวจจับ
//        $bank = BankAccount::query()
//            ->where('enable', 'Y')
//            ->where('bank_type', 1)
//            ->where('code', $data->account_code)
//            ->first();
//
//        if ($bank) {
//            $shouldTopup =
//                ($bank->status_topup === 'Y'  && (int)$data->member_topup > 0)
//                || ($bank->status_topup !== 'Y' && (int)$data->member_topup > 0 && (int)$data->emp_topup > 0);
//
//            if ($shouldTopup) {
//                TopupPaymentsJob::dispatch($data->code)
//                    ->delay(now()->addSeconds(2))
//                    ->onQueue('topup');
//            }
//
//            $short = $bank->bank?->shortcode;
//            if ($short && (int)$data->member_topup === 0 && $data->autocheck === 'N') {
//                CheckPaymentsJob::dispatch(strtolower($short), $data)->onQueue('topup');
//            }
//        }

        // ปรับ counter หลังคอมมิต
        DB::afterCommit(function () use ($data) {
            $this->ensureCounterBootstrapped();
            $attrs = $this->attrs($data);
            if ($this->isCountableTodayWaiting($attrs)) {
                $this->inc();
            }
            $this->broadcastCount('created', $data->id);
        });
    }

    public function updated(EventData $data)
    {
        // ยิงงานเติมเมื่อระบุตัวสมาชิกได้ครั้งแรก
//        if (
//            $data->wasChanged('member_topup') &&
//            (int)$data->getOriginal('member_topup') === 0 &&
//            (int)$data->member_topup > 0
//        ) {
//            TopupPaymentsJob::dispatch($data->code)
//                ->delay(now()->addSeconds(2))
//                ->onQueue('topup');
//        }

        // LOG ผู้แก้ไข
        $userId = 0; $userName = '';
        if (Auth::guard('admin')->check()) {
            $userId   = Request::user('admin')->code;
            $userName = Request::user('admin')->user_name;
        }
        if ($userId > 0) {
            $log = new Log;
            $log->emp_code    = $userId;
            $log->mode        = 'EDIT';
            $log->menu        = 'bank_payment';
            $log->record      = $data->id;
            $log->item_before = json_encode($data->getOriginal(), JSON_UNESCAPED_UNICODE);
            $log->item        = json_encode($data->getChanges(),  JSON_UNESCAPED_UNICODE);
            $log->ip          = Request::ip();
            $log->user_create = $userName;
            $log->save();
        }

        // รัน transition เฉพาะตอน field ที่มีผลกับเกณฑ์นับเปลี่ยนจริง ๆ (status/enable)
        $dirtyKeys = array_intersect(array_keys($data->getChanges()), ['status','enable']);
        if (empty($dirtyKeys)) {
            return; // ไม่มีผลต่อ counter → จบ
        }

        // คำนวณก่อนเข้า afterCommit (กัน state เปลี่ยน)
        $before = (array) $data->getOriginal();
        $after  = (array) $data->getAttributes();

        $was = $this->isCountableTodayWaiting($before);
        $now = $this->isCountableTodayWaiting($after);

        DB::afterCommit(function () use ($was, $now, $data) {
            $this->ensureCounterBootstrapped();
            if (!$was && $now) $this->inc();
            if ($was && !$now) $this->dec();
            $this->broadcastCount('updated', $data->id);
        });
    }

    public function deleted(EventData $data)
    {
        // LOG ผู้ลบ
        $userId = 0; $userName = '';
        if (Auth::guard('admin')->check()) {
            $userId   = Request::user('admin')->code;
            $userName = Request::user('admin')->user_name;
        }
        if ($userId > 0) {
            $log = new Log;
            $log->emp_code    = $userId;
            $log->mode        = 'DEL';
            $log->menu        = 'bank_payment';
            $log->record      = $data->id;
            $log->item_before = json_encode($data->getOriginal(), JSON_UNESCAPED_UNICODE);
            $log->item        = json_encode($data->getChanges(),  JSON_UNESCAPED_UNICODE);
            $log->ip          = Request::ip();
            $log->user_create = $userName;
            $log->save();
        }

        $before = (array) $data->getOriginal();

        DB::afterCommit(function () use ($before, $data) {
            $this->ensureCounterBootstrapped();
            if ($this->isCountableTodayWaiting($before)) {
                $this->dec();
            }
            $this->broadcastCount('deleted', $data->id);
        });
    }
}
