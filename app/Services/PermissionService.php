<?php

namespace App\Services;

use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function context(?int $userId): array
    {
        if (!$userId || !app()->bound(CurrentTenant::class)) return ['role'=>null,'permissions'=>[]];
        $tenantId = app(CurrentTenant::class)->id();
        $membership = DB::table('tenant_memberships')
            ->leftJoin('roles','roles.id','=','tenant_memberships.role_id')
            ->where('tenant_memberships.tenant_id',$tenantId)
            ->where('tenant_memberships.user_id',$userId)
            ->where('tenant_memberships.status','active')
            ->select('tenant_memberships.role_id','roles.slug','roles.name')
            ->first();
        if (!$membership) return ['role'=>null,'permissions'=>[]];
        if (in_array($membership->slug, ['owner','admin'], true)) return ['role'=>['id'=>$membership->role_id,'slug'=>$membership->slug,'name'=>$membership->name],'permissions'=>['*']];
        $permissions = DB::table('permission_role')
            ->join('permissions','permissions.id','=','permission_role.permission_id')
            ->where('permission_role.role_id',$membership->role_id)
            ->orderBy('permissions.slug')->pluck('permissions.slug')->all();
        return ['role'=>['id'=>$membership->role_id,'slug'=>$membership->slug,'name'=>$membership->name],'permissions'=>$permissions];
    }

    public function allows(?int $userId, string $permission): bool
    {
        $ctx = $this->context($userId);
        return in_array('*',$ctx['permissions'],true) || in_array($permission,$ctx['permissions'],true);
    }
}
