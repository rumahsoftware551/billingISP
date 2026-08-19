import type {RoleSlug} from './roleUi';

export type NavKey =
  | 'dashboard'
  | 'customers'
  | 'billing'
  | 'manualPayments'
  | 'network'
  | 'sessions'
  | 'operations'
  | 'fieldOps'
  | 'partners'
  | 'inventory'
  | 'reports'
  | 'integrations'
  | 'settings'
  | 'system';

export type RoleNavGroup = {
  label: string;
  items: NavKey[];
};

export const ROLE_WORKSPACE_NAVIGATION: Record<RoleSlug, RoleNavGroup[]> = {
  owner: [
    {label:'Ringkasan',items:['dashboard']},
    {label:'Bisnis & Pelanggan',items:['customers','billing','manualPayments','reports']},
    {label:'Operasional ISP',items:['network','sessions','operations','fieldOps']},
    {label:'Ekspansi & Asset',items:['partners','inventory']},
    {label:'Governance',items:['integrations','settings','system']},
  ],

  admin: [
    {label:'Operasional Utama',items:['dashboard','customers']},
    {label:'Billing',items:['billing','manualPayments','reports']},
    {label:'Network & Layanan',items:['network','sessions','operations','fieldOps']},
    {label:'Bisnis & Asset',items:['partners','inventory']},
    {label:'Administrasi',items:['integrations','settings']},
  ],

  finance: [
    {label:'Finance Workspace',items:['dashboard']},
    {label:'Billing & Collection',items:['billing','manualPayments','reports']},
    {label:'Referensi',items:['customers']},
  ],

  cs: [
    {label:'Customer Service',items:['dashboard','customers']},
    {label:'Ticket & Instalasi',items:['fieldOps']},
    {label:'Informasi Tagihan',items:['billing']},
  ],

  noc: [
    {label:'Network Operations Center',items:['dashboard','network','sessions']},
    {label:'Operasional Jaringan',items:['operations','fieldOps']},
    {label:'Referensi',items:['customers','reports']},
  ],

  warehouse: [
    {label:'Inventory Workspace',items:['dashboard','inventory']},
    {label:'Kebutuhan Lapangan',items:['fieldOps']},
  ],

  viewer: [
    {label:'Read Only Workspace',items:['dashboard','reports']},
    {label:'Monitoring Bisnis',items:['customers','billing','partners','inventory']},
    {label:'Monitoring Operasional',items:['network','sessions','operations','fieldOps']},
  ],

  technician: [
    {label:'Field Operations',items:['dashboard','fieldOps']},
    {label:'Referensi',items:['customers','inventory']},
  ],
};

export const ROLE_WORKSPACE_NAV_VERSION = 'RC7_EXPLICIT_ROLE_WORKSPACE_V1';
