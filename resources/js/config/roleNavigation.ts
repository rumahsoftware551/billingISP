import type {RoleSlug} from './roleUi';

const ROLE_ROUTE_PREFIXES: Record<RoleSlug, string[]> = {
  owner: ['*'],
  admin: ['/', '/dashboard', '/customers', '/billing', '/network', '/operations', '/field-operations', '/partners', '/inventory-management', '/reports', '/integrations', '/settings'],
  finance: ['/', '/dashboard', '/customers', '/billing', '/reports'],
  cs: ['/', '/dashboard', '/customers', '/billing', '/field-operations'],
  noc: ['/', '/dashboard', '/customers', '/network', '/operations', '/field-operations', '/reports'],
  warehouse: ['/', '/dashboard', '/inventory-management', '/field-operations'],
  viewer: ['/', '/dashboard', '/customers', '/billing', '/network', '/operations', '/field-operations', '/partners', '/inventory-management', '/reports'],
  technician: ['/', '/dashboard', '/customers', '/field-operations', '/inventory-management'],
};

export const ROLE_NAVIGATION_MATRIX_VERSION = 'RC6_ROLE_NAV_V1';

export function isRoleMenuAllowed(role: RoleSlug, href?: string): boolean {
  if (!href) return false;
  const allowed = ROLE_ROUTE_PREFIXES[role] || ROLE_ROUTE_PREFIXES.viewer;
  if (allowed.includes('*')) return true;

  const path = href.split('?')[0].replace(/\/+$/, '') || '/';

  return allowed.some((prefix) => {
    const normalized = prefix.replace(/\/+$/, '') || '/';
    if (normalized === '/') return path === '/' || path === '/dashboard';
    return path === normalized || path.startsWith(`${normalized}/`);
  });
}
