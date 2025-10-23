<?php

namespace Gametech\Integrations\Contracts;

interface ProviderContract
{
    /** ดำเนินการฝาก/ถอน ตาม $ctx->op */
    public function approve(ApproveContext $ctx): ProviderResult;

    /** ดึงยอดคงเหลือผู้ใช้ (หรือ agent) */
    public function balance(BalanceContext $ctx): BalanceResult;
}
