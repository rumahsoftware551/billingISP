import React from 'react';
import { Head, Link } from '@inertiajs/react';
import PortalLayout from '../../components/PortalLayout';

type Service={id:number,service_number:string,service_type:string,pppoe_username:string,status:string,installed_at?:string,plan?:{name:string,price:number,download_kbps:number,upload_kbps:number}};
type Invoice={id:number,invoice_number:string,issued_at:string,due_at:string,total:number,balance_due:number,status:string,service?:{service_number:string,pppoe_username:string}};
type Payment={id:number,payment_number:string,amount:number,method:string,paid_at:string,status:string,allocations?:Array<{invoice?:{invoice_number:string}}>};

const rupiah=(v:number)=>`Rp${Number(v||0).toLocaleString('id-ID')}`;
const date=(v?:string)=>v?new Date(v).toLocaleDateString('id-ID'):'-';

export default function PortalDashboard({portalTenantSlug,customer,services,invoices,payments,summary}:{portalTenantSlug:string,customer:any,services:Service[],invoices:Invoice[],payments:Payment[],summary:any}){
  return <PortalLayout tenantSlug={portalTenantSlug}>
    <Head title="Portal Pelanggan"/>
    <div className="space-y-7">
      <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Metric label="Layanan Aktif" value={summary.active_services}/>
        <Metric label="Tagihan Terbuka" value={summary.open_invoices}/>
        <Metric label="Total Outstanding" value={rupiah(summary.outstanding)} accent={summary.outstanding>0}/>
        <Metric label="Pembayaran Terakhir" value={summary.last_payment?date(summary.last_payment):'-'}/>
      </section>

      <section className="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <div className="mb-4 flex items-center justify-between"><div><h2 className="text-lg font-bold">Layanan Internet</h2><p className="text-xs text-slate-500">Status layanan dan paket pelanggan.</p></div></div>
        <div className="grid gap-3 lg:grid-cols-2">
          {services.map(s=><div key={s.id} className="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
            <div className="flex items-start justify-between gap-3"><div><div className="font-bold">{s.service_number}</div><div className="mt-1 text-sm text-sky-400">{s.pppoe_username}</div></div><Status value={s.status}/></div>
            <div className="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-400"><div>Paket<div className="mt-1 font-semibold text-slate-200">{s.plan?.name||'-'}</div></div><div>Kecepatan<div className="mt-1 font-semibold text-slate-200">{s.plan?`${Math.round(s.plan.download_kbps/1000)} / ${Math.round(s.plan.upload_kbps/1000)} Mbps`:'-'}</div></div><div>Harga<div className="mt-1 font-semibold text-slate-200">{rupiah(s.plan?.price||0)}</div></div><div>Terpasang<div className="mt-1 font-semibold text-slate-200">{date(s.installed_at)}</div></div></div>
          </div>)}
          {services.length===0&&<div className="text-sm text-slate-500">Belum ada layanan.</div>}
        </div>
      </section>

      <section className="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <h2 className="text-lg font-bold">Tagihan</h2><p className="mb-4 text-xs text-slate-500">Klik invoice untuk melihat detail dan opsi pembayaran.</p>
        <div className="space-y-2">
          {invoices.map(inv=><Link key={inv.id} href={`/portal/${portalTenantSlug}/invoices/${inv.id}`} className="flex flex-col gap-2 rounded-xl border border-slate-800 p-4 hover:border-sky-800 md:flex-row md:items-center md:justify-between">
            <div><div className="font-semibold text-sky-300">{inv.invoice_number}</div><div className="text-xs text-slate-500">{inv.service?.service_number||'-'} · terbit {date(inv.issued_at)} · jatuh tempo {date(inv.due_at)}</div></div>
            <div className="md:text-right"><div className="font-bold">{rupiah(inv.balance_due)}</div><Status value={inv.status}/></div>
          </Link>)}
          {invoices.length===0&&<div className="text-sm text-slate-500">Belum ada invoice.</div>}
        </div>
      </section>

      <section className="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <h2 className="text-lg font-bold">Riwayat Pembayaran</h2>
        <div className="mt-4 overflow-x-auto"><table className="w-full text-sm"><thead className="text-left text-xs text-slate-500"><tr><th className="pb-2">Pembayaran</th><th>Tanggal</th><th>Metode</th><th>Jumlah</th><th></th></tr></thead><tbody>{payments.map(p=><tr key={p.id} className="border-t border-slate-800"><td className="py-3 font-semibold">{p.payment_number}</td><td>{date(p.paid_at)}</td><td className="uppercase">{p.method}</td><td>{rupiah(p.amount)}</td><td className="text-right"><a href={`/portal/${portalTenantSlug}/receipts/${p.id}/download`} className="text-sky-400 hover:underline">Receipt PDF</a></td></tr>)}</tbody></table>{payments.length===0&&<div className="py-4 text-sm text-slate-500">Belum ada pembayaran.</div>}</div>
      </section>
    </div>
  </PortalLayout>;
}
function Metric({label,value,accent=false}:{label:string,value:any,accent?:boolean}){return <div className={`rounded-xl border p-4 ${accent?'border-amber-900 bg-amber-950/30':'border-slate-800 bg-slate-900/50'}`}><div className="text-xs text-slate-500">{label}</div><div className="mt-2 text-xl font-black">{value}</div></div>}
function Status({value}:{value:string}){const v=(value||'').toLowerCase();const cls=['active','paid','posted'].includes(v)?'bg-emerald-950 text-emerald-200 border-emerald-900':['overdue','suspended','partial'].includes(v)?'bg-amber-950 text-amber-200 border-amber-900':['failed','void','terminated','cancelled'].includes(v)?'bg-rose-950 text-rose-200 border-rose-900':'bg-slate-800 text-slate-300 border-slate-700';return <span className={`inline-block rounded border px-2 py-1 text-[10px] uppercase ${cls}`}>{value}</span>}
