import {usePage} from '@inertiajs/react';

export function useAccess() {
  const props:any = usePage().props;
  const permissions:string[] = props.access?.permissions || [];
  const platformAdmin = Boolean(props.auth?.user?.is_platform_admin);
  const systemAdmin = Boolean(props.auth?.system_admin);

  const can = (permission?:string) =>
    !permission || platformAdmin || permissions.includes('*') || permissions.includes(permission);

  return {
    can,
    permissions,
    role: props.access?.role || null,
    platformAdmin,
    systemAdmin,
  };
}
