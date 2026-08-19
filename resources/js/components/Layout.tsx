import React, {useMemo, useState} from 'react';
import {Head, Link, usePage} from '@inertiajs/react';
import {
  Activity,
  Boxes,
  Building2,
  ChevronDown,
  CircleDollarSign,
  ClipboardList,
  FileBarChart,
  Gauge,
  Globe2,
  Menu,
  Network,
  PanelsTopLeft,
  Search,
  Settings,
  ShieldCheck,
  TicketCheck,
  Users,
  UsersRound,
  WalletCards,
  X,
} from 'lucide-react';
import {useAccess} from '../hooks/useAccess';
import {ROLE_UI} from '../config/roleUi';
import {
  ROLE_WORKSPACE_NAVIGATION,
  ROLE_WORKSPACE_NAV_VERSION,
  type NavKey,
} from '../config/roleNavigation';

type NavItem={
  name:string;
  href:string;
  icon:any;
  permission?:string;
};

type NavGroup={
  label:string;
  items:NavItem[];
};

const NAV_CATALOG:Record<NavKey,NavItem>={
  dashboard:{name:'Dashboard',href:'/dashboard',icon:Gauge,permission:'dashboard.view'},
  customers:{name:'Pelanggan',href:'/customers',icon:Users,permission:'customers.view'},
  billing:{name:'Billing & Pembayaran',href:'/billing',icon:CircleDollarSign,permission:'billing.view'},
  manualPayments:{name:'Bukti Pembayaran',href:'/billing/manual-payments',icon:WalletCards,permission:'billing.view'},
  network:{name:'Jaringan & RADIUS',href:'/network',icon:Network,permission:'network.view'},
  sessions:{name:'Sesi Online',href:'/network/sessions',icon:Activity,permission:'network.view'},
  operations:{name:'Automation & Isolir',href:'/operations',icon:ShieldCheck,permission:'operations.view'},
  fieldOps:{name:'Teknisi, Tiket & WO',href:'/field-operations',icon:TicketCheck,permission:'field_ops.view'},
  partners:{name:'Mitra / Reseller',href:'/partners',icon:UsersRound,permission:'partners.view'},
  inventory:{name:'Inventory',href:'/inventory-management',icon:Boxes,permission:'inventory.view'},
  reports:{name:'Laporan',href:'/reports',icon:FileBarChart,permission:'reports.view'},
  integrations:{name:'Integrasi',href:'/integrations',icon:Globe2,permission:'integrations.manage'},
  settings:{name:'Pengaturan',href:'/settings',icon:Settings,permission:'settings.manage'},
  system:{name:'System',href:'/system',icon:PanelsTopLeft,permission:'system.manage'},
};

export default function Layout({children}:{children:React.ReactNode}) {
  const {props,url}:any=usePage();
  const {can,platformAdmin,roleSlug,roleName}=useAccess();
  const [mobile,setMobile]=useState(false);
  const [search,setSearch]=useState('');
  const branding=props.branding||{};
  const profile=ROLE_UI[roleSlug];

  const groups:NavGroup[]=ROLE_WORKSPACE_NAVIGATION[roleSlug]
    .map(group=>({
      label:group.label,
      items:group.items
        .map(key=>NAV_CATALOG[key])
        .filter(Boolean)
        .filter(item=>can(item.permission)),
    }))
    .filter(group=>group.items.length>0);

  if(platformAdmin){
    groups.push({
      label:'Platform SaaS',
      items:[
        {name:'Platform SaaS',href:'/platform',icon:Building2},
        {name:'Release Audit',href:'/platform/release',icon:ClipboardList},
      ],
    });
  }

  const nav=groups.flatMap(group=>group.items);

  const active=(href:string)=>{
    if(href==='/dashboard') return url==='/'||url==='/dashboard'||url.startsWith('/dashboard/');
    if(href==='/network') return url==='/network';
    return url===href||url.startsWith(href+'/');
  };

  const results=useMemo(
    ()=>search.trim()
      ? nav.filter(n=>n.name.toLowerCase().includes(search.toLowerCase()))
      : [],
    [search,nav],
  );

  const primary=branding.primary_color||'#0f6cbd';
  const initials=String(props.auth?.user?.name||props.auth?.user?.email||'U')
    .split(/\s+/)
    .slice(0,2)
    .map((x:string)=>x.charAt(0))
    .join('')
    .toUpperCase();

  const readOnly=roleSlug==='viewer';

  return <div
    className="jk-shell min-h-screen"
    data-role-workspace={roleSlug}
    data-role-nav-version={ROLE_WORKSPACE_NAV_VERSION}
    style={{'--jk-primary':primary} as React.CSSProperties}
  >
    <Head>
      <title>{branding.app_name||'Jaringanku'}</title>
      {branding.favicon_url&&<link rel="icon" href={branding.favicon_url}/>}
    </Head>

    <aside className={`jk-sidebar ${mobile?'translate-x-0':'-translate-x-full'} md:translate-x-0`}>
      <div className="flex h-18 items-center gap-3 border-b border-white/10 px-5">
        {branding.logo_url
          ? <img src={branding.logo_url} className="h-9 w-9 rounded-lg bg-white p-1 object-contain"/>
          : <div className="grid h-9 w-9 place-items-center rounded-lg bg-white/10 font-black">J</div>}
        <div className="min-w-0">
          <div className="truncate font-extrabold tracking-tight text-white">{branding.app_name||'Jaringanku'}</div>
          <div className="truncate text-[10px] uppercase tracking-[.16em] text-slate-400">ISP Management</div>
        </div>
        <button
          aria-label="Tutup menu"
          className="ml-auto rounded-lg p-2 text-slate-300 hover:bg-white/10 md:hidden"
          onClick={()=>setMobile(false)}
        >
          <X size={19}/>
        </button>
      </div>

      <div className="mx-3 mt-4 rounded-2xl border border-white/10 bg-white/[.06] p-3.5 shadow-inner">
        <div className="flex items-center justify-between gap-2">
          <div className="text-[9px] font-black uppercase tracking-[.16em] text-sky-300">Workspace Aktif</div>
          <span className="rounded-full border border-white/10 bg-white/10 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-white">
            {roleSlug}
          </span>
        </div>
        <div className="mt-2 text-sm font-black text-white">{roleName}</div>
        <div className="mt-1 text-[11px] leading-5 text-slate-400">{profile.eyebrow}</div>
        {readOnly&&<div className="mt-2 rounded-lg border border-amber-400/20 bg-amber-300/10 px-2.5 py-2 text-[10px] font-bold text-amber-200">
          MODE READ ONLY — tidak ada aksi perubahan data
        </div>}
      </div>

      <nav className="jk-nav px-3 py-4">
        {groups.map(group=><div key={group.label} className="mb-4 last:mb-0">
          <div className="jk-nav-section">{group.label}</div>
          <div className="space-y-1">
            {group.items.map(item=>{
              const I=item.icon;
              return <Link
                key={item.href}
                href={item.href}
                onClick={()=>setMobile(false)}
                className={`jk-nav-link ${active(item.href)?'active':''}`}
              >
                <I size={18}/>
                <span>{item.name}</span>
              </Link>;
            })}
          </div>
        </div>)}
      </nav>

      <div className="mt-auto border-t border-white/10 p-4">
        <div className="rounded-xl bg-white/5 p-3">
          <div className="text-[10px] uppercase tracking-wider text-slate-400">ISP / Tenant</div>
          <div className="mt-1 truncate text-sm font-semibold text-white">{props.tenant?.name||'-'}</div>
          {props.subscription?.plan&&<div className="mt-2 text-xs text-slate-400">
            {props.subscription.plan.name} · {props.subscription.status}
          </div>}
        </div>
        <div className="mt-3 text-[10px] text-slate-500">
          v{props.release?.version||'1.3.0-dev'} · {props.release?.channel||'development'}
        </div>
      </div>
    </aside>

    {mobile&&<button
      aria-label="Tutup menu"
      className="fixed inset-0 z-30 bg-slate-950/45 backdrop-blur-[1px] md:hidden"
      onClick={()=>setMobile(false)}
    />}

    <div className="jk-main md:ml-72">
      <header className="jk-topbar">
        <div className="flex min-w-0 items-center gap-3">
          <button
            aria-label="Buka menu"
            className="rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm md:hidden"
            onClick={()=>setMobile(true)}
          >
            <Menu size={19}/>
          </button>

          <div className="hidden min-w-0 sm:block">
            <div className="truncate text-sm font-black text-slate-900">{profile.title}</div>
            <div className="mt-0.5 flex items-center gap-2">
              <span className="truncate text-[11px] text-slate-500">{props.tenant?.name||branding.company_name||'Jaringanku'}</span>
              <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-slate-600">
                {roleName}
              </span>
            </div>
          </div>
        </div>

        <div className="relative mx-auto hidden w-full max-w-xl lg:block">
          <Search size={16} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
          <input
            value={search}
            onChange={e=>setSearch(e.target.value)}
            className="jk-search pl-10"
            placeholder={`Cari menu ${roleName}...`}
          />
          {results.length>0&&<div className="absolute top-12 z-50 w-full rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl">
            {results.map(r=><Link
              href={r.href}
              key={r.href}
              onClick={()=>setSearch('')}
              className="block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
              {r.name}
            </Link>)}
          </div>}
        </div>

        <div className="flex items-center gap-2">
          {roleSlug==='owner'&&<Link
            href="/access"
            className="hidden rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 sm:inline-flex"
          >
            Akses Portal
          </Link>}

          <div className="group relative">
            <button className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-1.5 pr-3 text-sm shadow-sm hover:bg-slate-50">
              <span className="grid h-8 w-8 place-items-center rounded-lg bg-slate-900 text-[11px] font-black text-white">
                {initials}
              </span>
              <span className="hidden max-w-36 truncate font-semibold sm:block">
                {props.auth?.user?.name||props.auth?.user?.email}
              </span>
              <ChevronDown size={14} className="text-slate-400"/>
            </button>

            <div className="invisible absolute right-0 z-40 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-2 opacity-0 shadow-2xl transition group-hover:visible group-hover:opacity-100">
              <div className="px-3 py-2">
                <div className="text-xs font-bold text-slate-900">{props.auth?.user?.name}</div>
                <div className="mt-0.5 truncate text-[11px] text-slate-500">{props.auth?.user?.email}</div>
                <div className="mt-2 inline-flex rounded-full bg-blue-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-blue-700">
                  {roleName}
                </div>
              </div>
              <div className="my-1 border-t border-slate-100"/>
              <Link
                method="post"
                as="button"
                href="/logout"
                className="w-full rounded-xl px-3 py-2 text-left text-sm font-semibold text-rose-600 hover:bg-rose-50"
              >
                Keluar
              </Link>
            </div>
          </div>
        </div>
      </header>

      <main className="jk-content">
        {props.flash?.success&&<div className="jk-alert success">{props.flash.success}</div>}
        {props.flash?.error&&<div className="jk-alert error">{props.flash.error}</div>}
        {children}
      </main>
    </div>
  </div>;
}
