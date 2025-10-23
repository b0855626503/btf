<?php

namespace Gametech\Integrations\Providers;

use Gametech\Integrations\Contracts\{
    ProviderContract, ApproveContext, ProviderResult, BalanceContext, BalanceResult
};
use Illuminate\Support\Facades\Http;

final class LsmProvider implements ProviderContract
{
    protected function sign(array $param, string $secret): string
    {
        $postString = http_build_query($param, '', '&');
        return base64_encode(hash_hmac('SHA1', $postString, $secret, true));
    }

    public function approve(ApproveContext $ctx): ProviderResult
    {
        $op = strtolower($ctx->op);
        if (!in_array($op, ['deposit','withdraw'], true)) {
            return new ProviderResult(false, 'invalid op');
        }

        $time    = (string) round(microtime(true) * 1000);
        $transID = strtoupper(($op === 'deposit' ? 'DEP' : 'WDR')).$time;

        $param = [
            "appid"         => $ctx->website->user,
            "credit"        => $ctx->amount,
            "transactionId" => $transID,
            "username"      => $ctx->username,
        ];
        $param['signature'] = $this->sign($param, $ctx->website->appID);

        $endpoint = $op === 'deposit'
            ? 'https://apiv2.lsmapi.net/api/member/balance/deposit'
            : 'https://apiv2.lsmapi.net/api/member/balance/withdraw';

        $res = Http::timeout($ctx->timeoutSec)->retry($ctx->retryTimes, $ctx->retrySleepMs)
            ->asJson()->post($endpoint, $param);

        if (!$res->successful()) return new ProviderResult(false, 'เชื่อมต่อ API ไม่ได้');

        $json = $res->json();
        if (($json['status'] ?? null) !== 'success') {
            return new ProviderResult(false, 'ดำเนินการไม่สำเร็จ', null, null, $json);
        }

        $afterCredit = data_get($json, 'data.credit');

        $old = null;
        if (is_numeric($afterCredit)) {
            $after = (float)$afterCredit;
            $old   = $op === 'deposit' ? ($after - $ctx->amount) : ($after + $ctx->amount);
        }

        return new ProviderResult(true, 'OK', $old, $afterCredit !== null ? (float)$afterCredit : null, $json);
    }

    public function balance(BalanceContext $ctx): BalanceResult
    {
        return new BalanceResult(false, null, null, 'ไม่รองรับการดึงยอดจาก LSM');
    }
}
