<?php

namespace Gametech\Integrations\Providers;

use Gametech\Integrations\Contracts\{
    ProviderContract, ApproveContext, ProviderResult, BalanceContext, BalanceResult
};
use Illuminate\Support\Facades\Http;

final class KrakenProvider implements ProviderContract
{
    public function approve(ApproveContext $ctx): ProviderResult
    {
        $op = strtolower($ctx->op);
        if (!in_array($op, ['deposit','withdraw'], true)) {
            return new ProviderResult(false, 'invalid op');
        }

        // ฝาก = add, ถอน = del (ตามที่ระบุ)
        $endpoint = $op === 'deposit'
            ? 'https://kraken.mrwed.cloud/partner/user/credit/add'
            : 'https://kraken.mrwed.cloud/partner/user/credit/del';

        $param = [
            'credit'        => $ctx->amount,
            'username'      => $ctx->username,
            'agentUsername' => $ctx->website->user,
            'agentPassword' => $ctx->website->pass,
        ];

        $res = Http::timeout($ctx->timeoutSec)->withHeaders([
            'x-api-key' => config('integrations.services.kraken.key'),
        ])->retry($ctx->retryTimes, $ctx->retrySleepMs)->asJson()->post($endpoint, $param);

        if (!$res->successful()) return new ProviderResult(false, 'เชื่อมต่อ API ไม่ได้');

        $json = $res->json();
        $ok   = ($json['status'] ?? null) === 'success';

        return new ProviderResult(
            success: $ok,
            msg: $ok ? 'OK' : 'ดำเนินการไม่สำเร็จ',
            old_credit: $json['old_credit'] ?? null,
            after_credit: $json['current_credit'] ?? null,
            raw: $json
        );
    }

    public function balance(BalanceContext $ctx): BalanceResult
    {
        // ยังไม่รองรับ balance ของสมาชิก
        return new BalanceResult(false, null, null, 'ไม่รองรับการดึงยอดจาก Kraken');
    }
}
