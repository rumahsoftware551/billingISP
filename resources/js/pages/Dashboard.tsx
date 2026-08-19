import React from 'react';
import {Head, Link} from '@inertiajs/react';
import {
  Activity,
  AlertTriangle,
  ArrowRight,
  Boxes,
  CircleDollarSign,
  FileBarChart,
  FileText,
  Network,
  Receipt,
  Settings,
  ShieldCheck,
  TicketCheck,
  UserPlus,
  Users,
  WalletCards,
  Wifi,
} from 'lucide-react';
import Layout from '../components/Layout';
import {useAccess} from '../hooks/useAccess';
import {ROLE_UI, type RoleSlug} from '../config/roleUi';

type MoneyPoint={period:string;invoiced:number;payments:number;balance:number};
type Aging={bucket:string;label:string;invoices:number;amount:number};
type Usage={id:number;service_number:string;pppoe_username:string;customer_number:string;customer_name:string;bytes:number};
type Outstanding={id:number;customer_number:string;name:string;invoices:number;outstanding:number};
type Metric={label:string;value:string;sub:string;icon:any;tone:string};
type Action={key:string;href:string;title:string;text:string;icon:any;permission?:string;system?:boolean};

export default function Dashboard({
  kpis={},
  financial=[],
  customer_growth=[],
  service_status=[],
  aging=[],
  top_outstanding=[],
  top_usage=[],
}:{
  kpis:any;
  financial:MoneyPoint[];
  customer_growth:any[];
  service_status:any[];
  aging:Aging[];
  top_outstanding:Outstanding[];
  top_usage:Usage[];
}) {
  const {can,roleSlug,roleName,systemAdmin}=useAccess();
  const profile=ROLE_UI[roleSlug];

  const metrics=metricsFor(roleSlug,kpis,can);
  const actions:Action[]=[
    {key:'customers',href:'/customers',title:'Kelola Pelanggan',text:'Tambah dan kelola data pelanggan',icon:UserPlus,permission:'customers.manage'},
    {key:'customers-view',href:'/customers',title:'Lihat Pelanggan',text:'Cari pelanggan dan status layanan',icon:Users,permission:'customers.view'},
    {key:'billing',href:'/billing',title:'Billing & Invoice',text:'Kelola invoice, pembayaran dan piutang',icon:CircleDollarSign,permission:'billing.manage'},
    {key:'billing-view',href:'/billing',title:'Status Billing',text:'Lihat invoice dan pembayaran pelanggan',icon:FileText,permission:'billing.view'},
    {key:'manual-payments',href:'/billing/manual-payments',title:'Bukti Pembayaran',text:'Review pembayaran manual dan QRIS',icon:WalletCards,permission:'billing.manage'},
    {key:'network',href:'/network',title:'Jaringan & RADIUS',text:'Router, NAS, RADIUS dan IP Pool',icon:Network,permission:'network.manage'},
    {key:'network-view',href:'/network',title:'Status Jaringan',text:'Lihat kondisi router dan RADIUS',icon:Network,permission:'network.view'},
    {key:'sessions',href:'/network/sessions',title:'Sesi Online',text:'Monitor sesi PPPoE dan accounting',icon:Activity,permission:'network.view'},
    {key:'operations',href:'/operations',title:'Automation & Isolir',text:'Overdue, isolir dan reaktivasi',icon:ShieldCheck,permission:'operations.manage'},
    {key:'field',href:'/field-operations',title:'Tiket & Work Order',text:'Kelola instalasi, gangguan dan teknisi',icon:TicketCheck,permission:'field_ops.manage'},
    {key:'field-view',href:'/field-operations',title:'Lihat Work Order',text:'Pantau tiket dan pekerjaan lapangan',icon:TicketCheck,permission:'field_ops.view'},
    {key:'inventory',href:'/inventory-management',title:'Inventory',text:'Stok, asset, serial dan stock movement',icon:Boxes,permission:'inventory.manage'},
    {key:'inventory-view',href:'/inventory-management',title:'Lihat Inventory',text:'Pantau stok dan asset',icon:Boxes,permission:'inventory.view'},
    {key:'reports',href:'/reports',title:'Laporan',text:'Buka laporan sesuai hak akses',icon:FileBarChart,permission:'reports.view'},
    {key:'settings',href:'/settings',title:'Pengaturan & User',text:'Role, permission, branding dan tenant',icon:Settings,system:true},
  ];

  const quick=actions.filter(action=>
    profile.quick.includes(action.key) &&
    (!action.permission || can(action.permission)) &&
    (!action.system || (systemAdmin && ['owner','admin'].includes(roleSlug)))
  );

  const showBilling=['owner','admin','finance'].includes(roleSlug);
  const showService=['owner','admin','cs','noc','viewer'].includes(roleSlug) && (can('customers.view')||can('network.view'));
  const showAging=['owner','admin','finance'].includes(roleSlug) && can('billing.view');
  const showUsage=['owner','admin','noc'].includes(roleSlug) && can('network.view');
  const showOutstanding=['owner','admin','finance'].includes(roleSlug) && can('billing.view');
  const workspaceOnly=['warehouse','technician'].includes(roleSlug);

  return <Layout>
    <Head title={profile.title}/>
    <div className="space-y-6">
      <header className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div className="text-[11px] font-black uppercase tracking-[.18em] text-[var(--jk-primary)]">{profile.eyebrow}</div>
          <h1 className="mt-1 text-2xl font-black tracking-tight text-slate-950">{profile.title}</h1>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">{profile.description}</p>
          <div className="mt-3 inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600">
            Role aktif: {roleName}
          </div>
        </div>
        {quick.length>0&&<Link href={quick[0].href} className="inline-flex items-center gap-2 rounded-xl bg-[var(--jk-primary)] px-4 py-2.5 text-sm font-bold text-white shadow-sm">
          {quick[0].title}<ArrowRight size={16}/>
        </Link>}
      </header>

      {metrics.length>0&&<section className={`grid gap-3 sm:grid-cols-2 ${metrics.length>=5?'xl:grid-cols-5':'xl:grid-cols-4'}`}>
        {metrics.map(metric=><MetricCard key={metric.label} {...metric}/>)}
      </section>}

      <section className={`grid gap-6 ${workspaceOnly?'xl:grid-cols-1':'xl:grid-cols-[1fr_.85fr]'}`}>
        {!workspaceOnly&&showBilling
          ? <Surface title="Billing & Collection" subtitle="Data keuangan hanya tampil untuk role yang memiliki akses billing.">
              <MoneyBars rows={financial}/>
            </Surface>
          : !workspaceOnly
            ? <RoleSummary role={roleSlug}/>
            : null}

        <Surface title="Aksi Sesuai Role" subtitle="Hanya menu yang relevan dengan pekerjaan dan permission Anda yang ditampilkan.">
          {quick.length
            ? <div className="grid gap-2 sm:grid-cols-2">{quick.map(action=><QuickAction key={action.key} {...action}/>)}</div>
            : <Empty text="Tidak ada aksi yang diizinkan untuk role ini."/>}
        </Surface>
      </section>

      {workspaceOnly&&<RoleWorkspace role={roleSlug}/>}

      {(showService||showAging||showUsage)&&<section className="grid gap-6 xl:grid-cols-3">
        {showService&&<Surface title={roleSlug==='noc'?'Status Layanan & Network':'Status Layanan'}><StatusList rows={service_status}/></Surface>}
        {showAging&&<Surface title="Umur Piutang"><AgingList rows={aging}/></Surface>}
        {showUsage&&<Surface title="Traffic Pelanggan" subtitle={`Total periode ${bytes(kpis.traffic_period_bytes)}`}><UsageList rows={top_usage}/></Surface>}
      </section>}

      {showOutstanding&&<Surface title="Pelanggan Prioritas Penagihan" subtitle="Outstanding terbesar untuk tindak lanjut Finance/Owner/Admin.">
        <OutstandingTable rows={top_outstanding}/>
      </Surface>}

      {roleSlug==='viewer'&&<div className="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        Mode Read Only aktif. Tombol mutasi, approval, create, edit dan delete tidak ditampilkan.
      </div>}
    </div>
  </Layout>;
}

function metricsFor(role:RoleSlug,k:any,can:(p?:string)=>boolean):Metric[] {
  const common={
    customers:{label:'Total Pelanggan',value:num(k.customers),sub:`+${num(k.new_customers_month)} bulan ini`,icon:Users,tone:'blue'},
    active:{label:'Layanan Aktif',value:num(k.active_services),sub:`${num(k.suspended_services)} suspended`,icon:Wifi,tone:'emerald'},
    online:{label:'Pelanggan Online',value:num(k.online_sessions),sub:`${num(k.routers_down)} router tidak online`,icon:Activity,tone:'cyan'},
    revenue:{label:'Pendapatan Bulan Ini',value:rupiah(k.revenue_month),sub:`Invoice ${rupiah(k.invoiced_month)}`,icon:CircleDollarSign,tone:'amber'},
    outstanding:{label:'Outstanding',value:rupiah(k.outstanding),sub:`Collection ${pct(k.collection_rate)}%`,icon:AlertTriangle,tone:'rose'},
    invoices:{label:'Invoice Bulan Ini',value:rupiah(k.invoiced_month),sub:'Nilai invoice terbit',icon:Receipt,tone:'violet'},
    suspended:{label:'Layanan Suspended',value:num(k.suspended_services),sub:'Perlu tindak lanjut sesuai prosedur',icon:ShieldCheck,tone:'rose'},
    routers:{label:'Router Bermasalah',value:num(k.routers_down),sub:'Router tidak online',icon:Network,tone:'rose'},
    traffic:{label:'Traffic Periode',value:bytes(k.traffic_period_bytes),sub:'Dari data RADIUS accounting',icon:Activity,tone:'cyan'},
  };

  switch(role){
    case 'owner': return [common.customers,common.active,common.online,common.revenue,common.outstanding];
    case 'admin': return [common.customers,common.active,common.online,common.revenue,common.suspended];
    case 'finance': return [common.revenue,common.invoices,common.outstanding,{label:'Collection Rate',value:`${pct(k.collection_rate)}%`,sub:'Rasio pembayaran terhadap invoice',icon:CircleDollarSign,tone:'emerald'}];
    case 'cs': return [common.customers,common.active,common.suspended,{label:'Pelanggan Baru',value:num(k.new_customers_month),sub:'Bulan berjalan',icon:UserPlus,tone:'blue'}];
    case 'noc': return [common.online,common.routers,common.active,common.suspended,common.traffic];
    case 'viewer': {
      const rows=[common.customers,common.active];
      if(can('network.view')) rows.push(common.online);
      if(can('billing.view')) rows.push(common.outstanding);
      return rows;
    }
    default: return [];
  }
}

function RoleSummary({role}:{role:RoleSlug}) {
  const copy:Record<string,{title:string;text:string;icon:any}>={
    cs:{title:'Customer Service Workspace',text:'Gunakan data pelanggan, layanan dan tiket untuk menangani kebutuhan pelanggan tanpa membuka konfigurasi network.',icon:Users},
    noc:{title:'Network Operations Workspace',text:'Pantau RADIUS, sesi, isolir dan kesehatan jaringan. Nilai keuangan operasional tidak menjadi fokus dashboard NOC.',icon:Network},
    viewer:{title:'Read Only Workspace',text:'Gunakan menu yang tersedia untuk membaca data. Aksi mutasi sengaja disembunyikan.',icon:FileText},
  };
  const row=copy[role]||copy.viewer;
  const I=row.icon;
  return <Surface title={row.title}><div className="flex items-start gap-4 rounded-xl bg-slate-50 p-4"><div className="rounded-xl bg-white p-3 text-[var(--jk-primary)] shadow-sm"><I size={22}/></div><p className="text-sm leading-6 text-slate-600">{row.text}</p></div></Surface>;
}

function RoleWorkspace({role}:{role:RoleSlug}) {
  const warehouse=role==='warehouse';
  return <section className="grid gap-4 md:grid-cols-3">
    <WorkspaceCard icon={warehouse?Boxes:TicketCheck} title={warehouse?'Stok & Asset':'Work Order'} text={warehouse?'Kelola stok, serial, perangkat dan stock movement.':'Lihat pekerjaan instalasi dan maintenance yang ditugaskan.'}/>
    <WorkspaceCard icon={warehouse?TicketCheck:Users} title={warehouse?'Kebutuhan Teknisi':'Pelanggan Lapangan'} text={warehouse?'Pantau kebutuhan material yang berkaitan dengan pekerjaan teknisi.':'Akses informasi pelanggan yang diperlukan untuk pekerjaan lapangan.'}/>
    <WorkspaceCard icon={ShieldCheck} title="Least Privilege" text="Dashboard ini sengaja tidak menampilkan billing, RADIUS atau pengaturan yang tidak diperlukan role Anda."/>
  </section>;
}

function WorkspaceCard({icon:Icon,title,text}:{icon:any;title:string;text:string}){
  return <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="mb-4 inline-flex rounded-xl bg-slate-100 p-3 text-slate-700"><Icon size={22}/></div><h3 className="font-black text-slate-900">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-500">{text}</p></div>;
}

function MetricCard({icon:Icon,label,value,sub,tone}:{icon:any;label:string;value:string;sub:string;tone:string}){
  const tones:any={blue:'bg-blue-50 text-blue-600',emerald:'bg-emerald-50 text-emerald-600',cyan:'bg-cyan-50 text-cyan-600',amber:'bg-amber-50 text-amber-600',rose:'bg-rose-50 text-rose-600',violet:'bg-violet-50 text-violet-600'};
  return <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between gap-3"><div><div className="text-[10px] font-black uppercase tracking-[.12em] text-slate-400">{label}</div><div className="mt-2 break-words text-xl font-black text-slate-950">{value}</div><div className="mt-1 text-xs leading-5 text-slate-400">{sub}</div></div><div className={`rounded-xl p-2.5 ${tones[tone]||tones.blue}`}><Icon size={18}/></div></div></div>;
}

function Surface({title,subtitle,children}:{title:string;subtitle?:string;children:React.ReactNode}){
  return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="border-b border-slate-100 px-5 py-4"><h2 className="font-black text-slate-950">{title}</h2>{subtitle&&<p className="mt-1 text-xs leading-5 text-slate-400">{subtitle}</p>}</div><div className="p-5">{children}</div></section>;
}

function QuickAction({href,title,text,icon:Icon}:{href:string;title:string;text:string;icon:any}){
  return <Link href={href} className="group flex items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-[var(--jk-primary)] hover:bg-slate-50"><div className="rounded-lg bg-slate-100 p-2 text-slate-700 group-hover:text-[var(--jk-primary)]"><Icon size={17}/></div><div className="min-w-0"><div className="text-sm font-black text-slate-800">{title}</div><div className="mt-1 text-xs leading-5 text-slate-400">{text}</div></div></Link>;
}

function MoneyBars({rows}:{rows:MoneyPoint[]}){
  if(!rows.length)return <Empty text="Belum ada data billing." />;
  const max=Math.max(1,...rows.flatMap(r=>[Number(r.invoiced||0),Number(r.payments||0)]));
  return <div className="space-y-4">{rows.map(r=><div key={r.period}><div className="mb-1 flex justify-between gap-3 text-xs"><b>{periodLabel(r.period)}</b><span className="text-slate-400">{rupiah(r.payments)} / {rupiah(r.invoiced)}</span></div><div className="space-y-1"><div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-blue-500" style={{width:`${Math.max(2,Number(r.invoiced||0)/max*100)}%`}}/></div><div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-emerald-500" style={{width:`${Math.max(2,Number(r.payments||0)/max*100)}%`}}/></div></div></div>)}</div>;
}

function StatusList({rows}:{rows:any[]}){
  return <div className="space-y-2">{rows.map((r:any)=><div key={r.status} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5"><span className="text-sm font-semibold">{statusLabel(r.status)}</span><b>{num(r.total)}</b></div>)}{!rows.length&&<Empty text="Belum ada data status layanan."/>}</div>;
}

function AgingList({rows}:{rows:Aging[]}){
  return <div className="space-y-2">{rows.map(a=><div key={a.bucket} className="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3"><div><div className="text-sm font-semibold">{a.label}</div><div className="text-xs text-slate-400">{a.invoices} invoice</div></div><div className="text-sm font-black">{rupiah(a.amount)}</div></div>)}{!rows.length&&<Empty text="Belum ada aging outstanding."/>}</div>;
}

function UsageList({rows}:{rows:Usage[]}){
  return <div className="space-y-2">{rows.slice(0,6).map(x=><div key={x.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3"><div className="min-w-0"><div className="truncate text-sm font-semibold">{x.customer_name}</div><div className="truncate text-xs text-slate-400">{x.customer_number} · {x.pppoe_username}</div></div><div className="whitespace-nowrap text-sm font-bold">{bytes(x.bytes)}</div></div>)}{!rows.length&&<Empty text="Belum ada data RADIUS accounting."/>}</div>;
}

function OutstandingTable({rows}:{rows:Outstanding[]}){
  return <div className="overflow-x-auto"><table className="min-w-[680px] w-full text-sm"><thead className="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3">Pelanggan</th><th className="px-4 py-3">Invoice</th><th className="px-4 py-3">Outstanding</th></tr></thead><tbody>{rows.map(x=><tr key={x.id} className="border-t border-slate-100"><td className="px-4 py-3"><b>{x.name}</b><div className="text-xs text-slate-400">{x.customer_number}</div></td><td className="px-4 py-3">{x.invoices}</td><td className="px-4 py-3 font-black">{rupiah(x.outstanding)}</td></tr>)}</tbody></table>{!rows.length&&<Empty text="Tidak ada pelanggan prioritas penagihan."/>}</div>;
}

function Empty({text}:{text:string}){return <div className="py-8 text-center text-sm text-slate-400">{text}</div>}
function rupiah(v:any){return 'Rp '+Number(v||0).toLocaleString('id-ID')}
function num(v:any){return Number(v||0).toLocaleString('id-ID')}
function pct(v:any){return Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:1})}
function bytes(v:any){let n=Number(v||0);for(const u of ['B','KB','MB','GB','TB','PB']){if(n<1024||u==='PB')return `${n.toLocaleString('id-ID',{maximumFractionDigits:n<10?2:1})} ${u}`;n/=1024}return '-'}
function periodLabel(v:string){const [y,m]=v.split('-').map(Number);return new Date(y,m-1,1).toLocaleDateString('id-ID',{month:'short',year:'numeric'})}
function statusLabel(v:string){const x=String(v||'-').replaceAll('_',' ');return x.charAt(0).toUpperCase()+x.slice(1)}
