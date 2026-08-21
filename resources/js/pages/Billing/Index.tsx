import React, {FormEvent, useState} from 'react';
import {Head, Link, router, useForm} from '@inertiajs/react';
import {BadgeDollarSign, CalendarRange, Clock3, FileText, PlayCircle, ReceiptText, RefreshCw, Search, TriangleAlert, WalletCards} from 'lucide-react';
import Layout from '../../components/Layout';
import {EmptyState, MetricCard, PageHeader, Surface} from '../../components/Ui';
import {useAccess} from '../../hooks/useAccess';

type Invoice={id:number,invoice_number:string,period_start:string,period_end:string,issued_at:string,due_at:string,total:number,paid_amount:number,balance_due:number,status:string,customer?:{id:number,customer_number:string,name:string,phone?:string},service?:{id:number,service_number:string,pppoe_username:string,plan?:{name:string,code:string}}};
type Payment={id:number,payment_number:string,amount:number,method:string,reference?:string,paid_at:string,customer?:{customer_number:string,name:string},allocations?:{invoice?:{invoice_number:string}}[]};
type Run={id:number,run_key:string,period_start:string,status:string,eligible_count:number,created_count:number,skipped_count:number,error_count:number,started_at?:string,finished_at?:string};
type Page<T>={data:T[];current_page:number,last_page:number,prev_page_url?:string|null,next_page_url?:string|null,total:number};

const input='w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-[var(--jk-primary)] focus:ring-2 focus:ring-blue-100';

export default function BillingIndex({invoices,payments,runs,filters,defaultPeriod,stats}:{invoices:Page<Invoice>,payments:Payment[],runs:Run[],filters:{status?:string,q?:string},defaultPeriod:string,stats:any}){
  const {can}=useAccess();
  const canManage=can('billing.manage');
  const [q,setQ]=useState(filters.q||'');
  const [status,setStatus]=useState(filters.status||'');
  const run=useForm({period:defaultPeriod});
  const search=(e:FormEvent)=>{e.preventDefault();router.get('/billing',{q,status},{preserveState:true,replace:true});};

  return <Layout>
    <Head title="Billing & Payments"/>
    <div className="space-y-6">
      <PageHeader
        eyebrow="Finance Operations"
        title="Billing & Payments"
        description="Pantau invoice, outstanding, penerimaan pembayaran, dan billing run dari satu workspace yang konsisten."
        actions={canManage?<form onSubmit={e=>{e.preventDefault();run.post('/billing/run',{preserveScroll:true});}} className="flex flex-wrap items-end gap-2"><label className="text-xs font-bold text-slate-500">Periode<input type="month" className={`${input} mt-1 min-w-44`} value={run.data.period} onChange={e=>run.setData('period',e.target.value)}/></label><button disabled={run.processing} className="jk-btn primary"><PlayCircle size={16}/>{run.processing?'Memproses...':'Generate Invoice'}</button></form>:undefined}
      />

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <MetricCard icon={ReceiptText} label="Invoices" value={stats.invoice_count} hint="Total invoice terfilter" tone="blue"/>
        <MetricCard icon={Clock3} label="Open" value={stats.unpaid_count} hint="Belum lunas" tone="cyan"/>
        <MetricCard icon={TriangleAlert} label="Overdue" value={stats.overdue_count} hint="Perlu ditindaklanjuti" tone="rose"/>
        <MetricCard icon={WalletCards} label="Outstanding" value={rupiah(stats.outstanding)} hint="Saldo piutang" tone="amber"/>
        <MetricCard icon={BadgeDollarSign} label="Paid Bulan Ini" value={rupiah(stats.paid_this_month)} hint="Pembayaran diterima" tone="emerald"/>
        <MetricCard icon={CalendarRange} label="Invoiced Bulan Ini" value={rupiah(stats.invoiced_this_month)} hint="Nilai invoice diterbitkan" tone="violet"/>
      </section>

      <Surface title="Filter Invoice" subtitle="Cari berdasarkan invoice, pelanggan, service number, atau username PPPoE.">
        <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_220px_auto_auto]">
          <div className="relative"><Search size={16} className="absolute left-3 top-3.5 text-slate-400"/><input className={`${input} pl-9`} value={q} onChange={e=>setQ(e.target.value)} placeholder="Cari invoice, pelanggan, service, PPPoE..."/></div>
          <select className={input} value={status} onChange={e=>setStatus(e.target.value)}><option value="">Semua status</option>{['unpaid','partial','overdue','paid','void'].map(s=><option key={s} value={s}>{labelStatus(s)}</option>)}</select>
          <button className="jk-btn primary"><Search size={15}/>Filter</button>
          <button type="button" onClick={()=>{setQ('');setStatus('');router.get('/billing')}} className="jk-btn secondary"><RefreshCw size={15}/>Reset</button>
        </form>
      </Surface>

      <section className="jk-surface">
        <div className="jk-surface-head flex items-center justify-between gap-4"><div><h2 className="jk-surface-title">Daftar Invoice</h2><p className="jk-surface-subtitle">Buka invoice untuk rincian, payment gateway, dan posting pembayaran sesuai hak akses.</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">{invoices.total} total</span></div>
        <div className="overflow-x-auto"><table className="min-w-[1080px] w-full text-sm"><thead className="bg-slate-50 text-left text-[11px] font-extrabold uppercase tracking-wide text-slate-500"><tr><Th>Invoice</Th><Th>Customer / Service</Th><Th>Periode</Th><Th>Jatuh Tempo</Th><Th>Total</Th><Th>Terbayar</Th><Th>Sisa</Th><Th>Status</Th></tr></thead><tbody>
          {invoices.data.map(i=><tr key={i.id} className="border-t border-slate-100 align-top transition hover:bg-slate-50/80"><Td><Link href={`/billing/invoices/${i.id}`} className="font-extrabold text-[var(--jk-primary)] hover:underline">{i.invoice_number}</Link><div className="mt-1 text-[11px] text-slate-400">Terbit {date(i.issued_at)}</div></Td><Td><Link href={`/customers/${i.customer?.id}`} className="font-bold text-slate-800 hover:text-[var(--jk-primary)]">{i.customer?.customer_number} · {i.customer?.name}</Link><div className="mt-1 text-xs text-slate-500">{[i.service?.service_number,i.service?.pppoe_username,i.service?.plan?.name].filter(Boolean).join(' · ')||'-'}</div></Td><Td>{date(i.period_start)} — {date(i.period_end)}</Td><Td><span className={i.status==='overdue'?'font-bold text-rose-700':''}>{date(i.due_at)}</span></Td><Td className="font-semibold">{rupiah(i.total)}</Td><Td className="text-emerald-700">{rupiah(i.paid_amount)}</Td><Td className="font-extrabold text-slate-900">{rupiah(i.balance_due)}</Td><Td><Status value={i.status}/></Td></tr>)}
          {invoices.data.length===0&&<tr><td colSpan={8}><EmptyState text="Belum ada invoice pada filter ini."/></td></tr>}
        </tbody></table></div>
        {(invoices.prev_page_url||invoices.next_page_url)&&<div className="flex items-center justify-between border-t border-slate-100 p-4 text-sm"><button disabled={!invoices.prev_page_url} onClick={()=>invoices.prev_page_url&&router.visit(invoices.prev_page_url,{preserveState:true})} className="jk-btn secondary disabled:opacity-40">← Sebelumnya</button><span className="text-xs font-semibold text-slate-500">Halaman {invoices.current_page}/{invoices.last_page}</span><button disabled={!invoices.next_page_url} onClick={()=>invoices.next_page_url&&router.visit(invoices.next_page_url,{preserveState:true})} className="jk-btn secondary disabled:opacity-40">Berikutnya →</button></div>}
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <Surface title="Pembayaran Terbaru" subtitle="Penerimaan yang sudah diposting ke invoice."><div className="max-h-[520px] space-y-2 overflow-auto">{payments.map(p=><div key={p.id} className="rounded-xl border border-slate-200 p-3"><div className="flex justify-between gap-4"><div><div className="font-extrabold text-emerald-700">{p.payment_number}</div><div className="mt-1 text-xs font-medium text-slate-600">{p.customer?.customer_number} · {p.customer?.name}</div><div className="mt-1 text-[11px] text-slate-400">{p.method}{p.reference?` · ${p.reference}`:''} · {dateTime(p.paid_at)}</div></div><div className="text-right"><div className="font-black text-slate-900">{rupiah(p.amount)}</div><div className="mt-1 text-[11px] text-slate-400">{p.allocations?.map(a=>a.invoice?.invoice_number).filter(Boolean).join(', ')}</div></div></div></div>)}{payments.length===0&&<EmptyState text="Belum ada pembayaran."/>}</div></Surface>
        <Surface title="Billing Runs" subtitle="Riwayat proses generate invoice bulanan."><div className="max-h-[520px] space-y-2 overflow-auto">{runs.map(r=><details key={r.id} className="rounded-xl border border-slate-200 p-3"><summary className="cursor-pointer list-none"><span className="font-extrabold text-slate-800">{r.run_key}</span><span className={`ml-2 rounded-full px-2 py-1 text-[10px] font-extrabold uppercase ${r.error_count?'bg-amber-50 text-amber-700':'bg-emerald-50 text-emerald-700'}`}>{r.status}</span><span className="float-right text-xs font-semibold text-slate-400">{r.created_count} baru</span></summary><div className="mt-3 text-xs text-slate-500">Eligible {r.eligible_count} · Created {r.created_count} · Existing {r.skipped_count} · Errors {r.error_count}</div></details>)}{runs.length===0&&<EmptyState text="Belum ada billing run."/>}</div></Surface>
      </section>
    </div>
  </Layout>;
}

function Th({children}:{children:React.ReactNode}){return <th className="px-4 py-3 font-extrabold">{children}</th>}
function Td({children,className=''}:{children:React.ReactNode,className?:string}){return <td className={`px-4 py-3 text-slate-600 ${className}`}>{children}</td>}
function Status({value}:{value:string}){const cls=value==='paid'?'bg-emerald-50 text-emerald-700 ring-emerald-200':value==='overdue'?'bg-rose-50 text-rose-700 ring-rose-200':value==='partial'?'bg-amber-50 text-amber-700 ring-amber-200':value==='void'?'bg-slate-100 text-slate-600 ring-slate-200':'bg-blue-50 text-blue-700 ring-blue-200';return <span className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1 ${cls}`}>{labelStatus(value)}</span>}
function labelStatus(v:string){return ({unpaid:'Unpaid',partial:'Partial',overdue:'Overdue',paid:'Paid',void:'Void'} as Record<string,string>)[v]||v}
function rupiah(v:any){return 'Rp '+Number(v||0).toLocaleString('id-ID')}
function date(v?:string){return v?new Date(v+'T00:00:00').toLocaleDateString('id-ID'):'-'}
function dateTime(v?:string){return v?new Date(v).toLocaleString('id-ID'):'-'}
