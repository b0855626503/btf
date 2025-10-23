<?php

namespace Gametech\Integrations\Providers;

use Gametech\Integrations\Contracts\{
    ProviderContract, ApproveContext, ProviderResult, BalanceContext, BalanceResult
};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class VegusProvider implements ProviderContract
{
    protected function authToken(string $agentName, int $timeout, int $retryTimes, int $retrySleepMs): ?string
    {
        $cacheKey = "provider:vegus:token:{$agentName}";
        return Cache::remember($cacheKey, 90, function() use ($agentName, $timeout, $retryTimes, $retrySleepMs) {
            $payload = [
                "username"  => config('integrations.services.vegus.username', 'vegusapi'),
                "password"  => config('integrations.services.vegus.password'),
                "agentName" => $agentName,
            ];
            $res = Http::timeout($timeout)->retry($retryTimes, $retrySleepMs)
                ->asJson()->post('https://vegusapi.asgard-serv.com/gateway/auth/signin', $payload);

            if (!$res->successful()) return null;
            $json = $res->json();
            $access = $json['result']['accessToken'] ?? null;
            $type   = $json['result']['tokenType'] ?? 'Bearer';
            return $access ? ($type.' '.$access) : null;
        });
    }

    public function approve(ApproveContext $ctx): ProviderResult
    {
        $op = strtolower($ctx->op); // 'deposit' | 'withdraw'
        if (!in_array($op, ['deposit','withdraw'], true)) {
            return new ProviderResult(false, 'invalid op');
        }

        $token = $this->authToken($ctx->website->user, $ctx->timeoutSec, $ctx->retryTimes, $ctx->retrySleepMs);
        if (!$token) return new ProviderResult(false, 'Auth Vegus ล้มเหลว');

        // old balance
        $old = $this->balance(new BalanceContext(
            username: $ctx->username,
            website:  $ctx->website,
            timeoutSec: $ctx->timeoutSec,
            retryTimes: $ctx->retryTimes,
            retrySleepMs: $ctx->retrySleepMs,
            traceId: $ctx->traceId
        ));

        $payload = [
            "username" => $ctx->username,
            "type"     => $op,          // deposit | withdraw
            "amount"   => $ctx->amount,
        ];

        $res = Http::timeout($ctx->timeoutSec)
            ->withHeaders(['Authorization' => $token])
            ->retry($ctx->retryTimes, $ctx->retrySleepMs)
            ->asJson()->post('https://vegusapi.asgard-serv.com/gateway/users/finance', $payload);

        if (!$res->successful()) {
            return new ProviderResult(false, 'เชื่อมต่อ API ไม่ได้', $old->credit, null, ['http' => $res->json()]);
        }

        $json = $res->json();
        if (($json['status'] ?? null) !== 'SUCCESS') {
            return new ProviderResult(false, 'ดำเนินการไม่สำเร็จ', $old->credit, null, $json);
        }

        // after balance
        $after = $this->balance(new BalanceContext(
            username: $ctx->username,
            website:  $ctx->website,
            timeoutSec: $ctx->timeoutSec,
            retryTimes: $ctx->retryTimes,
            retrySleepMs: $ctx->retrySleepMs,
            traceId: $ctx->traceId
        ));

        return new ProviderResult(true, 'OK', $old->credit, $after->credit, ['result' => $json]);
    }

    public function balance(BalanceContext $ctx): BalanceResult
    {
        $token = $this->authToken($ctx->website->user, $ctx->timeoutSec, $ctx->retryTimes, $ctx->retrySleepMs);
        if (!$token) return new BalanceResult(false, null, null, 'Auth Vegus ล้มเหลว');

        $url = 'https://vegusapi.asgard-serv.com/gateway/users/info?username='.urlencode($ctx->username);
        $res = Http::timeout($ctx->timeoutSec)
            ->withHeaders(['Authorization' => $token])
            ->retry($ctx->retryTimes, $ctx->retrySleepMs)
            ->asJson()->get($url);

        if (!$res->successful()) return new BalanceResult(false, null, null, 'เชื่อมต่อ API ไม่ได้');
        $json = $res->json();
        $credit = $json['result']['credit'] ?? null;

        return new BalanceResult(true, is_numeric($credit) ? (float)$credit : null, null, 'OK', $json);
    }
}
