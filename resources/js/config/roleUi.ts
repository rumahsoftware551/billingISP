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
    eyebrow: 'Executive Overview',
    title: 'Dashboard Owner',
    description: 'Ringkasan bisnis, collection, pelanggan dan kesehatan operasional ISP.',
    quick: ['customers','billing','reports','network','inventory','settings'],
  },
  admin: {
    label: 'Administrator',
    eyebrow: 'Operations Control',
    title: 'Dashboard Administrator',
    description: 'Kontrol operasional tenant, pelanggan, billing, jaringan dan layanan pendukung.',
    quick: ['customers','billing','network','operations','field','inventory','settings'],
  },
  finance: {
    label: 'Finance / Billing',
    eyebrow: 'Billing & Collection',
    title: 'Dashboard Finance',
    description: 'Fokus pada invoice, pembayaran, piutang, collection dan laporan keuangan.',
    quick: ['billing','manual-payments','reports','customers-view'],
  },
  cs: {
    label: 'Customer Service',
    eyebrow: 'Customer Operations',
    title: 'Dashboard Customer Service',
    description: 'Fokus pada pelanggan, status layanan, tiket, instalasi dan visibilitas billing dasar.',
    quick: ['customers','field','billing-view'],
  },
  noc: {
    label: 'NOC / Network',
    eyebrow: 'Network Operations Center',
    title: 'Dashboard NOC',
    description: 'Fokus pada router, RADIUS, sesi online, isolir dan kesehatan jaringan.',
    quick: ['network','sessions','operations','customers-view','field-view'],
  },
  warehouse: {
    label: 'Warehouse / Inventory',
    eyebrow: 'Inventory Operations',
    title: 'Dashboard Warehouse',
    description: 'Fokus pada stok, asset, serial perangkat, pergerakan barang dan kebutuhan teknisi.',
    quick: ['inventory','field-view'],
  },
  viewer: {
    label: 'Read Only',
    eyebrow: 'Read Only Workspace',
    title: 'Dashboard Viewer',
    description: 'Ringkasan informasi yang diizinkan tanpa aksi create, edit, delete atau approval.',
    quick: ['customers-view','billing-view','network-view','reports','inventory-view'],
  },
  technician: {
    label: 'Technician / Field Ops',
    eyebrow: 'Field Operations',
    title: 'Dashboard Teknisi',
    description: 'Fokus pada work order, instalasi, maintenance, pelanggan dan material lapangan.',
    quick: ['field','customers-view','inventory-view'],
  },
};

export function normalizeRole(value: unknown): RoleSlug {
  const raw = String(value || '').trim().toLowerCase().replaceAll(' ', '_');

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
  };

  return aliases[raw] || 'viewer';
}
