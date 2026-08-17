import React from 'react';
import {Head, Link} from '@inertiajs/react';
import {Activity, AlertTriangle, ArrowRight, Boxes, CircleDollarSign, FileText, Network, Settings, ShieldCheck, UserPlus, Users, UsersRound, WalletCards, Wifi} from 'lucide-react';
import Layout from '../components/Layout';
import {EmptyState, MetricCard, PageHeader, QuickAction, Surface} from '../components/Ui';
import {useAccess} from '../hooks/useAccess';

type MoneyPoint={period:string;invoiced:number;payments:number;balance:number};
type Aging={bucket:string;label:string;invoices:number;amount:number};
type Usage={id:number;service_number:string;pppoe_username:string;customer_number:string;customer_name:string;bytes:number};
type Outstanding={id:number;customer_number:string;name:string;invoices:number;outstanding:number};

export default function Dashboard({kpis={},financial=[],service_status=[],aging=[],top_outstanding=[],top_usage=[]}:{kpis:any;financial:MoneyPoint[];customer_growth:any[];service_status:any[];aging:Aging[];top_outstanding:Outstanding[];top_usage:Usage[]}){
  const {can,systemAdmin}=useAccess();
  const quick=[
    {href:'/billing/manual-payments',title:'Review Bukti Bayar',description:'Approve/reject transfer & QRIS manual',icon:WalletCards,permission:'billing.view'},
    {href:'/operations',title:'Automation & Isolir',description:'Cek overdue, isolir dan reaktivasi',icon:ShieldCheck,permission:'operations.view'},
    {href:'/partners',title:'Mitra / Reseller',description:'Kelola mitra, komisi dan pelanggan',icon:UsersRound,permission:'partners.view'},
    {href:'/inventory-management',title:'Inventory',description:'Gudang, asset dan stok teknisi',icon:Boxes,permission:'inventory.view'},
    {href:'/network',title:'Jaringan & RADIUS',description:'Router, NAS, RADIUS dan sesi jaringan',icon:Network,permission:'network.view'},
    {href:'/settings',title:'Branding & User',description:'Logo, QRIS, role dan permission',icon:Settings,system:true},
  ].filter(item=>item.system?systemAdmin:can(item.permission));

  return <Layout><Head title="Dashboard"/><div className="space-y-6">
    <PageHeader eyebrow="Ringkasan Operasional" title="Dashboard ISP" description="Angka penting billing, pelanggan dan jaringan dalam satu layar agar tim cepat menentukan tindakan." actions={<>
      {can('customers.manage')&&<Link href="/customers" className="jk-btn primary"><UserPlus size={16}/> Tambah Pelanggan</Link>}
      {can('billing.view')&&<Link href="/billing" className="jk-btn secondary"><FileText size={16}/> Buka Billing</Link>}
    </>}/>

    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <MetricCard icon={Users} label="Total Pelanggan" value={num(kpis.customers)} hint={`+${num(kpis.new_customers_month)} bulan ini`} tone="blue"/>
      <MetricCard icon={Wifi} label="Layanan Aktif" value={num(kpis.active_services)} hint={`${num(kpis.suspended_services)} terisolir/suspended`} tone="emerald"/>
      <MetricCard icon={Activity} label="Pelanggan Online" value={num(kpis.online_sessions)} hint={`${num(kpis.routers_down)} router tidak online`} tone="cyan"/>
      <MetricCard icon={CircleDollarSign} label="Pendapatan Bulan Ini" value={rupiah(kpis.revenue_month)} hint={`Invoice ${rupiah(kpis.invoiced_month)}`} tone="amber"/>
      <MetricCard icon={AlertTriangle} label="Outstanding" value={rupiah(kpis.outstanding)} hint={`Collection ${Number(kpis.collection_rate||0).toLocaleString('id-ID')}%`} tone="rose"/>
    </section>

    <section className="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
      <Surface title="Billing vs Pembayaran — 6 Bulan" subtitle="Bandingkan nilai invoice terbit dengan pembayaran yang sudah benar-benar terposting."><MoneyBars rows={financial}/></Surface>
      <Surface title="Aksi Cepat" subtitle="Hanya menampilkan menu yang memang dapat diakses oleh akun ini."><div className="grid gap-2 sm:grid-cols-2">{quick.map(item=><QuickAction key={item.href} href={item.href} title={item.title} description={item.description} icon={item.icon}/>)}{quick.length===0&&<EmptyState text="Tidak ada aksi cepat untuk role ini."/>}</div></Surface>
    </section>

    <section className="grid gap-6 xl:grid-cols-3">
      <Surface title="Status Layanan"><StatusList rows={service_status}/></Surface>
      <Surface title="Umur Piutang"><div className="space-y-2">{aging.map(a=><div key={a.bucket} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-3"><div><div className="text-sm font-semibold">{a.label}</div><div className="text-xs text-slate-400">{a.invoices} invoice</div></div><div className="text-sm font-black">{rupiah(a.amount)}</div></div>)}{!aging.length&&<EmptyState text="Belum ada aging outstanding."/>}</div></Surface>
      <Surface title="Traffic Pelanggan" subtitle={`Total periode ${bytes(kpis.traffic_period_bytes)}`}><div className="space-y-2">{top_usage.slice(0,6).map(x=><div key={x.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3"><div className="min-w-0"><div className="truncate text-sm font-semibold">{x.customer_name}</div><div className="truncate text-xs text-slate-400">{x.customer_number} · {x.pppoe_username}</div></div><div className="whitespace-nowrap text-sm font-bold">{bytes(x.bytes)}</div></div>)}{!top_usage.length&&<EmptyState text="Belum ada data RADIUS accounting pada periode ini."/>}</div></Surface>
    </section>

    <Surface title="Pelanggan Prioritas Penagihan" subtitle="Outstanding terbesar yang sebaiknya ditindaklanjuti lebih dulu."><div className="overflow-x-auto"><table className="min-w-[720px] w-full text-sm"><thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3">Pelanggan</th><th className="px-4 py-3">Invoice Belum Lunas</th><th className="px-4 py-3">Outstanding</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody>{top_outstanding.map(x=><tr key={x.id} className="border-t border-slate-100 hover:bg-slate-50/70"><td className="px-4 py-3"><div className="font-semibold">{x.name}</div><div className="text-xs text-[var(--jk-primary)]">{x.customer_number}</div></td><td className="px-4 py-3">{x.invoices}</td><td className="px-4 py-3 font-black">{rupiah(x.outstanding)}</td><td className="px-4 py-3 text-right"><Link href={`/customers/${x.id}`} className="inline-flex items-center gap-1 text-xs font-bold text-[var(--jk-primary)]">Detail <ArrowRight size={13}/></Link></td></tr>)}{!top_outstanding.length&&<tr><td colSpan={4}><EmptyState text="Tidak ada outstanding."/></td></tr>}</tbody></table></div></Surface>
  </div></Layout>;
}

function MoneyBars({rows}:{rows:MoneyPoint[]}){const max=Math.max(1,...rows.flatMap(r=>[r.invoiced,r.payments]));return <div className="space-y-4">{rows.map(r=><div key={r.period}><div className="mb-1.5 flex justify-between gap-3 text-xs"><span className="font-semibold">{periodLabel(r.period)}</span><span className="text-slate-400">Bayar {rupiah(r.payments)} / Invoice {rupiah(r.invoiced)}</span></div><div className="relative h-3 overflow-hidden rounded-full bg-slate-100"><div className="absolute inset-y-0 left-0 rounded-full bg-blue-200" style={{width:`${r.invoiced/max*100}%`}}/><div className="absolute inset-y-0 left-0 rounded-full bg-emerald-500" style={{width:`${r.payments/max*100}%`}}/></div></div>)}{!rows.length&&<EmptyState text="Belum ada data billing."/>}<div className="flex gap-4 text-[11px] text-slate-400"><span><i className="mr-1 inline-block h-2 w-2 rounded-full bg-blue-200"/>Invoice</span><span><i className="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500"/>Pembayaran</span></div></div>}
function StatusList({rows}:{rows:any[]}){const total=Math.max(1,rows.reduce((a,r)=>a+Number(r.total||0),0));return <div className="space-y-3">{rows.map(r=><div key={r.status}><div className="mb-1 flex justify-between text-sm"><span className="font-medium">{statusLabel(r.status)}</span><span className="font-bold">{r.total}</span></div><div className="h-2 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-[var(--jk-primary)]" style={{width:`${Number(r.total)/total*100}%`}}/></div></div>)}{!rows.length&&<EmptyState text="Belum ada layanan."/>}</div>}
function rupiah(v:any){return 'Rp'+Number(v||0).toLocaleString('id-ID')}
function num(v:any){return Number(v||0).toLocaleString('id-ID')}
function bytes(v:any){let n=Number(v||0);for(const u of ['B','KB','MB','GB','TB','PB']){if(n<1024||u==='PB')return `${n.toLocaleString('id-ID',{maximumFractionDigits:n<10?2:1})} ${u}`;n/=1024}return '-'}
function periodLabel(v:string){const [y,m]=v.split('-').map(Number);return new Date(y,m-1,1).toLocaleDateString('id-ID',{month:'short',year:'numeric'})}
function statusLabel(v:string){const x=v.replaceAll('_',' ');return x.charAt(0).toUpperCase()+x.slice(1)}
