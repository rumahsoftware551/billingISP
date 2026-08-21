import React, {useMemo, useState} from 'react';
import {Head, Link, usePage} from '@inertiajs/react';
import {Activity, Boxes, Building2, ChevronDown, CircleDollarSign, ClipboardList, CreditCard, FileBarChart, Gauge, Globe2, Menu, Network, PackageSearch, PanelsTopLeft, Settings, ShieldCheck, TicketCheck, Users, UsersRound, WalletCards, X} from 'lucide-react';

type NavItem={name:string,href:string,icon:any,permission?:string,section?:string};

export default function Layout({children}:{children:React.ReactNode}) {
  const {props,url}:any=usePage();
  const [mobile,setMobile]=useState(false);
  const [search,setSearch]=useState('');
  const branding=props.branding||{};
  const permissions:string[]=props.access?.permissions||[];
  const can=(permission?:string)=>!permission||permissions.includes('*')||permissions.includes(permission)||props.auth?.user?.is_platform_admin;
  const nav:NavItem[]=[
    {name:'Dashboard',href:'/dashboard',icon:Gauge,permission:'dashboard.view',section:'Operasional'},
    {name:'Pelanggan',href:'/customers',icon:Users,permission:'customers.view',section:'Operasional'},
    {name:'Billing & Pembayaran',href:'/billing',icon:CircleDollarSign,permission:'billing.view',section:'Keuangan'},
    {name:'Bukti Pembayaran',href:'/billing/manual-payments',icon:WalletCards,permission:'billing.view',section:'Keuangan'},
    {name:'Laporan',href:'/reports',icon:FileBarChart,permission:'reports.view',section:'Keuangan'},
    {name:'Jaringan & RADIUS',href:'/network',icon:Network,permission:'network.view',section:'NOC & Jaringan'},
    {name:'Sesi Online',href:'/network/sessions',icon:Activity,permission:'network.view',section:'NOC & Jaringan'},
    {name:'Automation & Isolir',href:'/operations',icon:ShieldCheck,permission:'operations.view',section:'NOC & Jaringan'},
    {name:'Teknisi, Tiket & WO',href:'/field-operations',icon:TicketCheck,permission:'field_ops.view',section:'NOC & Jaringan'},
    {name:'Mitra / Reseller',href:'/partners',icon:UsersRound,permission:'partners.view',section:'Pendukung'},
    {name:'Inventory',href:'/inventory-management',icon:Boxes,permission:'inventory.view',section:'Pendukung'},
  ].filter(x=>can(x.permission));
  if(props.auth?.system_admin) nav.push({name:'Integrasi',href:'/integrations',icon:Globe2});
  if(props.auth?.system_admin) nav.push({name:'Pengaturan',href:'/settings',icon:Settings});
  if(props.auth?.system_admin) nav.push({name:'System',href:'/system',icon:PanelsTopLeft});
  if(props.auth?.user?.is_platform_admin){nav.push({name:'Platform SaaS',href:'/platform',icon:Building2});nav.push({name:'Release Audit',href:'/platform/release',icon:ClipboardList});}

  const active=(href:string)=>href==='/network'?url==='/network':(url===href||url.startsWith(href+'/'));
  const results=useMemo(()=>search.trim()?nav.filter(n=>n.name.toLowerCase().includes(search.toLowerCase())):[],[search,nav]);
  const primary=branding.primary_color||'#0f6cbd';
  return <div className="jk-shell min-h-screen" style={{'--jk-primary':primary} as React.CSSProperties}>
    <Head><title>{branding.app_name||'Jaringanku'}</title>{branding.favicon_url&&<link rel="icon" href={branding.favicon_url}/>}</Head>
    <aside className={`jk-sidebar ${mobile?'translate-x-0':'-translate-x-full'} md:translate-x-0`}>
      <div className="flex h-18 items-center gap-3 border-b border-white/10 px-5">
        {branding.logo_url?<img src={branding.logo_url} className="h-9 w-9 rounded-lg object-contain bg-white p-1"/>:<div className="grid h-9 w-9 place-items-center rounded-lg bg-white/10 font-black">J</div>}
        <div className="min-w-0"><div className="truncate font-extrabold tracking-tight text-white">{branding.app_name||'Jaringanku'}</div><div className="truncate text-[10px] uppercase tracking-[.16em] text-slate-400">ISP Management</div></div>
        <button className="ml-auto md:hidden" onClick={()=>setMobile(false)}><X size={20}/></button>
      </div>
      <nav className="jk-nav px-3 py-4">{nav.map((item,index)=>{const I=item.icon;const showSection=index===0||nav[index-1].section!==item.section;return <React.Fragment key={item.href}>{showSection&&<div className="px-3 pb-2 pt-4 text-[10px] font-bold uppercase tracking-[.14em] text-slate-500">{item.section}</div>}<Link href={item.href} onClick={()=>setMobile(false)} className={`jk-nav-link ${active(item.href)?'active':''}`}><I size={18}/><span>{item.name}</span></Link></React.Fragment>})}</nav>
      <div className="mt-auto border-t border-white/10 p-4">
        <div className="rounded-xl bg-white/5 p-3"><div className="text-[10px] uppercase tracking-wider text-slate-400">ISP / Tenant</div><div className="mt-1 truncate text-sm font-semibold text-white">{props.tenant?.name||'-'}</div>{props.subscription?.plan&&<div className="mt-2 text-xs text-slate-400">{props.subscription.plan.name} · {props.subscription.status}</div>}</div>
        <div className="mt-3 text-[10px] text-slate-500">v{props.release?.version||'1.2.0-dev'} · {props.release?.channel||'development'}</div>
      </div>
    </aside>
    {mobile&&<button aria-label="Close menu" className="fixed inset-0 z-30 bg-slate-950/40 md:hidden" onClick={()=>setMobile(false)}/>} 
    <div className="jk-main md:ml-72">
      <header className="jk-topbar">
        <div className="flex items-center gap-3 min-w-0"><button className="rounded-lg border border-slate-200 p-2 md:hidden" onClick={()=>setMobile(true)}><Menu size={20}/></button><div className="hidden sm:block"><div className="text-sm font-semibold text-slate-900">{props.tenant?.name||branding.company_name||'Jaringanku'}</div><div className="text-xs text-slate-500">{props.access?.role?.name||'Administrator'}</div></div></div>
        <div className="relative mx-auto hidden w-full max-w-xl lg:block"><input value={search} onChange={e=>setSearch(e.target.value)} className="jk-search" placeholder="Cari menu: pelanggan, billing, jaringan, inventory..."/>{results.length>0&&<div className="absolute top-12 z-50 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-xl">{results.map(r=><Link href={r.href} key={r.href} onClick={()=>setSearch('')} className="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">{r.name}</Link>)}</div>}</div>
        <div className="flex items-center gap-2"><Link href="/access" className="hidden sm:inline-flex rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Akses Portal</Link><div className="group relative"><button className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><span className="max-w-36 truncate">{props.auth?.user?.name||props.auth?.user?.email}</span><ChevronDown size={14}/></button><div className="invisible absolute right-0 z-40 mt-1 w-52 rounded-xl border border-slate-200 bg-white p-2 opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100"><div className="px-3 py-2 text-xs text-slate-500">{props.auth?.user?.email}</div><Link method="post" as="button" href="/logout" className="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50">Keluar</Link></div></div></div>
      </header>
      <main className="jk-content">
        {props.flash?.success&&<div className="jk-alert success">{props.flash.success}</div>}
        {props.flash?.error&&<div className="jk-alert error">{props.flash.error}</div>}
        {children}
      </main>
    </div>
  </div>;
}
