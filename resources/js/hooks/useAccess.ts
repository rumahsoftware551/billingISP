import {usePage} from '@inertiajs/react';
import {normalizeRole, ROLE_UI, type RoleSlug} from '../config/roleUi';

function inferredRole(
  permissions: string[],
  platformAdmin: boolean,
  systemAdmin: boolean,
): RoleSlug {
  if (platformAdmin) return 'owner';
  if (permissions.includes('*')) return systemAdmin ? 'admin' : 'viewer';
  if (permissions.includes('inventory.manage')) return 'warehouse';
  if (permissions.includes('network.manage') || permissions.includes('operations.manage')) return 'noc';
  if (
    permissions.includes('field_ops.manage') &&
    permissions.includes('customers.manage') &&
    !permissions.includes('billing.manage')
  ) return 'cs';
  if (
    permissions.includes('billing.manage') &&
    !permissions.includes('network.manage') &&
    !permissions.includes('inventory.manage')
  ) return 'finance';
  return 'viewer';
}

export function useAccess() {
  const props:any = usePage().props;
  const permissions:string[] = Array.isArray(props.access?.permissions)
    ? props.access.permissions
    : [];

  const platformAdmin = Boolean(props.auth?.user?.is_platform_admin);
  const systemAdmin = Boolean(props.auth?.system_admin);
  const role = props.access?.role || null;

  const explicitValue =
    role && typeof role === 'object'
      ? (role.slug || role.name || null)
      : role;

  let roleSlug:RoleSlug = explicitValue
    ? normalizeRole(explicitValue)
    : inferredRole(permissions, platformAdmin, systemAdmin);

  const inferred = inferredRole(permissions, platformAdmin, systemAdmin);

  if (platformAdmin && roleSlug === 'viewer') {
    roleSlug = 'owner';
  } else if (systemAdmin && roleSlug === 'viewer') {
    roleSlug = inferred === 'viewer' ? 'admin' : inferred;
  } else if (roleSlug === 'viewer' && inferred !== 'viewer') {
    roleSlug = inferred;
  }

  const roleName =
    (role && typeof role === 'object' ? role.name : null) ||
    ROLE_UI[roleSlug].label;

  const can = (permission?:string) =>
    !permission ||
    platformAdmin ||
    permissions.includes('*') ||
    permissions.includes(permission);

  const canAny = (...wanted:string[]) =>
    platformAdmin ||
    permissions.includes('*') ||
    wanted.some(p=>permissions.includes(p));

  return {
    can,
    canAny,
    permissions,
    role,
    roleSlug,
    roleName,
    platformAdmin,
    systemAdmin,
  };
}