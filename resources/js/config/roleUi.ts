export type RoleSlug =
  | 'owner'
  | 'admin'
  | 'finance'
  | 'cs'
  | 'noc'
  | 'warehouse'
  | 'viewer'
  | 'technician';

export type RoleUiProfile = {
  label: string;
  eyebrow: string;
  title: string;
  description: string;
  quick: string[];
};

export const ROLE_UI: Record<RoleSlug, RoleUiProfile> = {
  owner: {
    label: 'Owner',
    eyebrow: 'Executive & Governance Workspace',
    title: 'Dashboard Owner',
    description: 'Ringkasan bisnis, pelanggan, billing, jaringan, inventory dan governance ISP.',
    quick: ['customers','billing','reports','network','inventory','settings'],
  },
  admin: {
    label: 'Administrator',
    eyebrow: 'ISP Operations Workspace',
    title: 'Dashboard Administrator',
    description: 'Kontrol operasional tenant, pelanggan, billing, jaringan, ticket dan inventory.',
    quick: ['customers','billing','network','operations','field','inventory','settings'],
  },
  finance: {
    label: 'Finance / Billing',
    eyebrow: 'Billing & Collection Workspace',
    title: 'Dashboard Finance',
    description: 'Fokus invoice, pembayaran, piutang, collection dan laporan keuangan.',
    quick: ['billing','manual-payments','reports','customers-view'],
  },
  cs: {
    label: 'Customer Service',
    eyebrow: 'Customer Operations Workspace',
    title: 'Dashboard Customer Service',
    description: 'Fokus pelanggan, status layanan, ticket, instalasi dan informasi billing.',
    quick: ['customers','field','billing-view'],
  },
  noc: {
    label: 'NOC / Network',
    eyebrow: 'Network Operations Center',
    title: 'Dashboard NOC',
    description: 'Fokus router, RADIUS, sesi online, isolir dan kesehatan jaringan.',
    quick: ['network','sessions','operations','customers-view','field-view'],
  },
  warehouse: {
    label: 'Warehouse / Inventory',
    eyebrow: 'Inventory Operations Workspace',
    title: 'Dashboard Warehouse',
    description: 'Fokus stok, asset, serial perangkat dan kebutuhan teknisi.',
    quick: ['inventory','field-view'],
  },
  viewer: {
    label: 'Read Only',
    eyebrow: 'Read Only Workspace',
    title: 'Dashboard Viewer',
    description: 'Ringkasan informasi yang diizinkan tanpa aksi perubahan data.',
    quick: ['customers-view','billing-view','network-view','reports','inventory-view'],
  },
  technician: {
    label: 'Technician / Field Ops',
    eyebrow: 'Field Operations Workspace',
    title: 'Dashboard Teknisi',
    description: 'Fokus work order, instalasi, maintenance dan material lapangan.',
    quick: ['field','customers-view','inventory-view'],
  },
};

function token(value: unknown): string {
  return String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

export function normalizeRole(value: unknown): RoleSlug {
  const raw = token(value);

  const aliases: Record<string, RoleSlug> = {
    owner: 'owner',
    administrator: 'admin',
    admin: 'admin',
    finance: 'finance',
    billing: 'finance',
    finance_billing: 'finance',
    cs: 'cs',
    customer_service: 'cs',
    noc: 'noc',
    network: 'noc',
    noc_network: 'noc',
    warehouse: 'warehouse',
    inventory: 'warehouse',
    warehouse_inventory: 'warehouse',
    viewer: 'viewer',
    read_only: 'viewer',
    auditor: 'viewer',
    technician: 'technician',
    field_ops: 'technician',
    technician_field_ops: 'technician',
  };

  return aliases[raw] || 'viewer';
}