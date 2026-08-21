import React from 'react';
import PartnerLayout from '../../components/PartnerLayout';
import {Link,usePage} from '@inertiajs/react';
import {CircleDollarSign,ReceiptText,ShieldAlert,Users,Wifi,WalletCards} from 'lucide-react';
const money=(n:number)=>'Rp'+new Intl.NumberFormat('id-ID').format(n||0);
export default function Dashboard({stats,recentCustomers,recentCommissions}:any){
 const p:any=usePage().props; const slug=p.tenant.slug;
 const cards=[
  ['Pelanggan Mitra',stats.customers,Users,'text-blue-700 bg-blue-50'],
  ['Layanan Aktif',stats.active_services,Wifi,'text-emerald-700 bg-emerald-50'],
  ['Layanan Isolir',stats.suspended_services,ShieldAlert,'text-rose-700 bg-rose-50'],
  ['Tagihan Belum Lunas',money(stats.outstanding),ReceiptText,'text-amber-700 bg-amber-50'],
  ['Pembayaran Bulan Ini',money(stats.payments_month),CircleDollarSign,'text-cyan-700 bg-cyan-50'],
  ['Komisi Tersedia',money(stats.commission_available),WalletCards,'text-violet-700 bg-violet-50'],
 ];
 return <PartnerLayout tenantSlug={slug}>
  <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"><div><h1 className="text-2xl font-black">Dashboard Mitra</h1><p className="mt-1 text-sm text-slate-500">Ringkasan pelanggan, pembayaran, isolir dan komisi yang menjadi tanggung jawab mitra.</p></div><div className="flex gap-2"><Link href={`/mitra/${slug}/customers`} className="rounded-xl bg-[#0f6cbd] px-4 py-2 text-sm font-bold text-white">Kelola Pelanggan</Link><Link href={`/mitra/${slug}/tickets`} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold">Buat Request</Link></div></div>
  <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{cards.map(([label,value,Icon,tone]:any)=><div key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start justify-between gap-4"><div><div className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</div><div className="mt-2 text-2xl font-black">{value}</div></div><div className={`rounded-xl p-2.5 ${tone}`}><Icon size={20}/></div></div></div>)}</div>
  <div className="mt-6 grid gap-5 lg:grid-cols-2"><section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="font-bold">Pelanggan Terbaru</h2><Link href={`/mitra/${slug}/customers`} className="text-xs font-semibold text-[#0f6cbd]">Lihat semua</Link></div><div className="mt-3 divide-y divide-slate-100">{recentCustomers.map((c:any)=><div key={c.id} className="flex items-center justify-between gap-3 py-3"><div><div className="font-semibold">{c.name}</div><div className="text-xs text-slate-500">{c.customer_number} · {c.phone||'-'}</div></div><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">{c.status}</span></div>)}{!recentCustomers.length&&<div className="py-8 text-center text-sm text-slate-400">Belum ada pelanggan.</div>}</div></section>
  <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><h2 className="font-bold">Komisi Terbaru</h2><Link href={`/mitra/${slug}/commissions`} className="text-xs font-semibold text-[#0f6cbd]">Lihat ledger</Link></div><div className="mt-3 divide-y divide-slate-100">{recentCommissions.map((c:any)=><div key={c.id} className="flex items-center justify-between gap-3 py-3"><div><div className="font-semibold capitalize">{String(c.entry_type).replaceAll('_',' ')}</div><div className="text-xs text-slate-500">{c.earned_at?new Date(c.earned_at).toLocaleDateString('id-ID'):'-'} · {c.status}</div></div><div className="font-bold text-emerald-700">{money(c.amount)}</div></div>)}{!recentCommissions.length&&<div className="py-8 text-center text-sm text-slate-400">Belum ada komisi.</div>}</div></section></div>
 </PartnerLayout>;
}
