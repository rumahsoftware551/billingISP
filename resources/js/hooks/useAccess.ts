import {usePage} from '@inertiajs/react';
import {normalizeRole, ROLE_UI} from '../config/roleUi';

export function useAccess() {
  const props:any = usePage().props;
  const permissions:string[] = props.access?.permissions || [];
  const platformAdmin = Boolean(props.auth?.user?.is_platform_admin);
  const systemAdmin = Boolean(props.auth?.system_admin);
  const role = props.access?.role || null;
  const roleSlug = normalizeRole(role?.slug || role?.name || role);
  const roleName = role?.name || ROLE_UI[roleSlug].label;

  const can = (permission?:string) =>
    !permission || platformAdmin || permissions.includes('*') || permissions.includes(permission);

  const canAny = (...wanted:string[]) =>
    platformAdmin || permissions.includes('*') || wanted.some(permission=>permissions.includes(permission));

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
