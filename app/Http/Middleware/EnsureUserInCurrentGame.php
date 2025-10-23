<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log; // เพิ่ม Log

class EnsureUserInCurrentGame
{
    public function handle(Request $request, Closure $next)
    {
        $userId = $request->input('username');
        $gameId = null;
        $productId = null;

        // LOG: request inbound
        Log::info('[EnsureUserInCurrentGame] Incoming request', [
            'userId' => $userId,
            'input' => $request->all()
        ]);

        // ดึง gameId จากหลายกรณี
        if ($request->has('gameCode')) {
            $gameId = $request->input('gameCode');
        } elseif ($request->has('txns') && is_array($request->input('txns')) && count($request->input('txns'))) {
            $gameId = $request->input('txns')[0]['gameCode'] ?? null;
        }

        if ($request->has('productId')) {
            $productId = $request->input('productId');
        }

        // LOG: extracted values
        Log::info('[EnsureUserInCurrentGame] Extracted', [
            'userId' => $userId,
            'gameId' => $gameId,
            'productId' => $productId
        ]);

        if (! $gameId || ! $productId) {
            Log::warning('[EnsureUserInCurrentGame] Missing gameId or productId', [
                'userId' => $userId,
                'gameId' => $gameId,
                'productId' => $productId
            ]);
            return response()->json([
                'id' => $request->input('id'),
                'statusCode' => 30002,
                'productId' => $request->input('productId'),
                'timestampMillis' => round(microtime(true) * 1000),
                'balance' => 0,
            ]);
        }

        $session = Redis::get("user_game_status:{$userId}");
        if (! $session) {
            Log::warning('[EnsureUserInCurrentGame] Session not found', [
                'userId' => $userId,
                'key' => "user_game_status:{$userId}"
            ]);
            return response()->json([
                'id' => $request->input('id'),
                'statusCode' => 30001,
                'productId' => $request->input('productId'),
                'timestampMillis' => round(microtime(true) * 1000),
                'balance' => 0,
            ]);
        }
        $session = json_decode($session, true);

        // LOG: session compare
        Log::info('[EnsureUserInCurrentGame] Compare session', [
            'userId' => $userId,
            'currentSession' => $session,
            'requestGameId' => $gameId,
            'requestProductId' => $productId
        ]);

        if (($session['gameCode'] ?? null) !== $gameId || ($session['productId'] ?? null) !== $productId) {
            Log::warning('[EnsureUserInCurrentGame] Session mismatch', [
                'userId' => $userId,
                'session_gameCode' => $session['gameCode'] ?? null,
                'session_productId' => $session['productId'] ?? null,
                'request_gameId' => $gameId,
                'request_productId' => $productId
            ]);
            return response()->json([
                'id' => $request->input('id'),
                'statusCode' => 30001,
                'productId' => $request->input('productId'),
                'timestampMillis' => round(microtime(true) * 1000),
                'balance' => 0,
            ]);
        }

        // LOG: session valid, refreshing TTL
        Log::info('[EnsureUserInCurrentGame] Session valid, refreshing TTL', [
            'userId' => $userId,
            'gameId' => $gameId,
            'productId' => $productId
        ]);

        Redis::setex("user_game_status:{$userId}", 600, json_encode([
            'gameCode' => $gameId,
            'productId' => $productId,
            'last_active_at' => now()->toDateTimeString(),
        ]));

        return $next($request);
    }
}
