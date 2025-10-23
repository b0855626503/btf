<?php

namespace Gametech\Integrations\Providers;

use Gametech\Integrations\Contracts\{
    ProviderContract, ApproveContext, ProviderResult, BalanceContext, BalanceResult
};
use Illuminate\Support\Facades\Http;

final class IgoalProvider implements ProviderContract
{
    public function approve(ApproveContext $ctx): ProviderResult
    {
        $op = strtolower($ctx->op); // 'deposit' | 'withdraw'
        if (!in_array($op, ['deposit','withdraw'], true)) {
            return new ProviderResult(false, 'invalid op');
        }

        // เส้นตามที่ระบุ: deposit.php / withdraw.php
        $endpoint = $op === 'deposit'
            ? 'https://bot.ipzeroline.com/igoal/deposit.php'
            : 'https://bot.ipzeroline.com/igoal/withdraw.php';

        $payload = [
            'amount' => $ctx->amount,
            'user'   => $ctx->username,
            'aguser' => $ctx->website->user,
            'agpass' => $ctx->website->pass,
            'scode'  => $ctx->website->scode,
        ];

        $res = Http::timeout($ctx->timeoutSec)
            ->retry($ctx->retryTimes, $ctx->retrySleepMs)
            ->asJson()->post($endpoint, $payload);

        if (!$res->successful()) return new ProviderResult(false, 'เชื่อมต่อ API ไม่ได้');

        $json = $res->json();
        $ok   = ($json['success'] ?? '') === '200';

        return new ProviderResult(
            success: $ok,
            msg: $ok ? 'OK' : 'ดำเนินการไม่สำเร็จ',
            old_credit: $json['oldcredit'] ?? null,
            after_credit: $json['credit'] ?? null,
            raw: $json
        );
    }

    public function balance(BalanceContext $ctx): BalanceResult
    {
        // ยังไม่มี public balance endpoint → แจ้งไม่รองรับ
        return new BalanceResult(false, null, null, 'ไม่รองรับการดึงยอดจาก Igoal');
    }
}
