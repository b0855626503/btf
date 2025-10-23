<?php

namespace Gametech\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * แหล่งตัดสินสิทธิ์แบบเบา: ไม่พึ่ง acls/role_acl (เพราะไม่มีตารางจริง)
 * ลำดับการตัดสิน:
 *  1) Super Admin (level>=9 && superadmin==='Y') → allow all
 *  2) Per-user overrides (permissions_override JSON) → deny > allow
 *  3) Role.permission_type === 'all' → allow all
 *  4) Fallback: roles.permissions (JSON array)
 */
class AclAuthorizer
{
    public function __construct(private ?string $userTable = null) {}

    /**
     * ตรวจสิทธิแบบนุ่มนวล (true/false)
     */
    public function can(object $user, string $permissionKey): bool
    {
        // 0) super admin bypass (ทั้งคู่ต้องจริง)
        $level      = (int) data_get($user, 'level', 0);
        $superadmin = (string) data_get($user, 'superadmin', 'N');
        if ($level >= 9 && $superadmin === 'Y') {
            return true;
        }

        // 1) per-user overrides (deny > allow)
        $over = $this->getUserOverrides($user);
        if (in_array($permissionKey, $over['deny'], true)) {
            return false;
        }
        if (in_array($permissionKey, $over['allow'], true)) {
            return true;
        }

        // 2) อ่าน role_id ตาม config (คีย์ PK เป็น code, เก็บเป็นสตริงได้)
        $roleIdCol = config('access.columns.employees.role_id', 'role_id');
        $roleId    = (string) (data_get($user, $roleIdCol) ?? data_get($user, 'role_id') ?? '');

        if ($roleId === '') {
            return false;
        }

        // 2.1 ลองอ่านจาก relation ที่โหลดมาแล้วก่อน (ต้องเป็น 'role' ไม่ใช่ 'roles')
        $rolePermType = strtolower((string) data_get($user, 'role.permission_type', ''));

        // 2.2 ถ้ายังว่าง → โหลดจาก DB (cache)
        if ($rolePermType === '') {
            $roleRow     = $this->getRoleRow($roleId);
            $rolePermType = strtolower((string) data_get($roleRow, 'permission_type', ''));
            $rolePermsJson = data_get($roleRow, 'permissions');
        } else {
            $roleRow = null; // ไม่จำเป็นต้องโหลดซ้ำ
            $rolePermsJson = data_get($user, 'role.permissions');
        }

        // 3) role = all → ผ่านทั้งหมด
        if ($rolePermType === 'all') {
            return true;
        }

        // 4) fallback: permissions (JSON array) ในตาราง roles
        $perms = $this->normalizeJsonPermissions($rolePermsJson);
        return in_array($permissionKey, $perms, true);
    }

    /**
     * เช็คแบบ “ต้องผ่าน” ไม่งั้นโยน exception
     */
    public function must(object $user, string $permissionKey): void
    {
        if (!$this->can($user, $permissionKey)) {
            throw new \RuntimeException("Forbidden: missing permission [{$permissionKey}]");
        }
    }

    /**
     * อ่าน overrides (allow/deny) จาก user->permissions_override
     * รองรับทั้ง string(JSON) / array / null
     */
    private function getUserOverrides(object $user): array
    {
        $raw = data_get($user, 'permissions_override');

        if (is_string($raw)) {
            $json = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $json = $raw;
        } else {
            $json = [];
        }

        $allow = array_values(array_unique(array_filter((array) data_get($json, 'allow', []))));
        $deny  = array_values(array_unique(array_filter((array) data_get($json, 'deny',  []))));

        return ['allow' => $allow, 'deny' => $deny];
    }

    /**
     * โหลด role แถวเดียวจาก DB (ตาม role code) แล้ว cache ตาม access.cache_ttl
     */
    private function getRoleRow(string $roleCode): ?object
    {
        $tRoles    = config('access.tables.roles', 'roles');
        $cRoleId   = config('access.columns.roles.id', 'code');
        $ttl       = (int) config('access.cache_ttl', 120);

        $cacheKey = "acl:role:row:{$roleCode}";

        return Cache::remember($cacheKey, $ttl, function () use ($tRoles, $cRoleId, $roleCode) {
            return DB::table($tRoles)->where($cRoleId, $roleCode)->first();
        });
    }

    /**
     * แปลงค่าจาก roles.permissions (ที่อาจเป็น string JSON / array / null) → array ของ permission keys
     */
    private function normalizeJsonPermissions(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_filter($decoded, fn ($v) => is_string($v) && $v !== '')));
            }
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_unique(array_filter($raw, fn ($v) => is_string($v) && $v !== '')));
        }

        return [];
    }
}
