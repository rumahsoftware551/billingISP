import React, {FormEvent, useState} from 'react';
import {Head, Link, router, useForm} from '@inertiajs/react';
import Layout from '../../components/Layout';

type Invoice={id:number,invoice_number:string,period_start:string,period_end:string,issued_at:string,due_at:string,total:number,paid_amount:number,balance_due:number,status:string,customer?:{id:number,customer_number:string,name:string,phone?:string},service?:{id:number,service_number:string,pppoe_username:string,plan?:{name:string,code:string}}};
type Payment={id:number,payment_number:string,amount:number,method:string,reference?:string,paid_at:string,customer?:{customer_number:string,name:string},allocations?:{invoice?:{invoice_number:string}}[]};
type Run={id:number,run_key:string,period_start:string,status:string,eligible_count:number,created_count:number,skipped_count:number,error_count:number,started_at?:string,finished_at?:string};
type Page<T>={data:T[];current_page:number,last_page:number,prev_page_url?:string|null,next_page_url?:string|null,total:number};

const input='w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-sky-500';

export default function BillingIndex({invoices,payments,runs,filters,defaultPeriod,stats}:{invoices:Page<Invoice>,payments:Payment[],runs:Run[],filters:{status?:string,q?:string},defaultPeriod:string,stats:any}){
  const [q,setQ]=useState(filters.q||'');
  const [status,setStatus]=useState(filters.status||'');
  const run=useForm({period:defaultPeriod});
  const search=(e:FormEvent)=>{e.preventDefault();router.get('/billing',{q,status},{preserveState:true,replace:true});};

  return <Layout>
    <Head title="Billing & Payments"/>
    <div className="space-y-6">
      <div className="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div><h2 className="text-2xl font-black">Billing & Payments</h2><p className="text-sm text-slate-400 mt-1">Invoice bulanan idempoten, outstanding, pembayaran parsial, dan riwayat billing run.</p></div>
        <form onSubmit={e=>{e.preventDefault();run.post('/billing/run',{preserveScroll:true});}} className="flex gap-2 items-end">
          <label className="text-xs text-slate-400">Periode<input type="month" className={`${input} mt-1`} value={run.data.period} onChange={e=>run.setData('period',e.target.value)}/></label>
          <button disabled={run.processing} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold hover:bg-sky-500 disabled:opacity-50">Generate Invoice</button>
        </form>
      </div>

      <section className="grid grid-cols-2 xl:grid-cols-6 gap-3">
        <Metric title="Invoices" value={stats.invoice_count}/><Metric title="Open" value={stats.unpaid_count}/><Metric title="Overdue" value={stats.overdue_count}/><Metric title="Outstanding" value={rupiah(stats.outstanding)}/><Metric title="Paid Bulan Ini" value={rupiah(stats.paid_this_month)}/><Metric title="Invoiced Bulan Ini" value={rupiah(stats.invoiced_this_month)}/>
      </section>

      <section className="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
        <form onSubmit={search} className="grid md:grid-cols-[1fr_220px_auto_auto] gap-3">
          <input className={input} value={q} onChange={e=>setQ(e.target.value)} placeholder="Cari invoice, pelanggan, service, PPPoE..."/>
          <select className={input} value={status} onChange={e=>setStatus(e.target.value)}><option value="">Semua status</option>{['unpaid','partial','overdue','paid','void'].map(s=><option key={s} value={s}>{s}</option>)}</select>
          <button className="rounded-lg bg-slate-800 px-4 py-2 text-sm hover:bg-slate-700">Filter</button>
          <button type="button" onClick={()=>{setQ('');setStatus('');router.get('/billing')}} className="rounded-lg border border-slate-700 px-4 py-2 text-sm">Reset</button>
        </form>
      </section>

      <section className="rounded-xl border border-slate-800 bg-slate-900/50 overflow-hidden">
        <div className="p-5 border-b border-slate-800 flex items-center justify-between"><div><h3 className="font-bold text-lg">Invoices</h3><p className="text-xs text-slate-500">Klik invoice untuk detail dan posting pembayaran.</p></div><span className="text-xs text-slate-500">{invoices.total} total</span></div>
        <div className="overflow-x-auto"><table className="min-w-[1080px] w-full text-sm"><thead className="bg-slate-950/70 text-xs uppercase text-slate-500"><tr><Th>Invoice</Th><Th>Customer / Service</Th><Th>Period</Th><Th>Due</Th><Th>Total</Th><Th>Paid</Th><Th>Balance</Th><Th>Status</Th></tr></thead><tbody>
          {invoices.data.map(i=><tr key={i.id} className="border-t border-slate-800 hover:bg-slate-800/30"><Td><Link href={`/billing/invoices/${i.id}`} className="font-semibold text-sky-300 hover:text-sky-200">{i.invoice_number}</Link><div className="text-[11px] text-slate-500">issued {date(i.issued_at)}</div></Td><Td><Link href={`/customers/${i.customer?.id}`} className="font-medium hover:text-sky-300">{i.customer?.customer_number} · {i.customer?.name}</Link><div className="text-xs text-slate-500">{i.service?.service_number} · {i.service?.pppoe_username} · {i.service?.plan?.name||'-'}</div></Td><Td>{date(i.period_start)} — {date(i.period_end)}</Td><Td>{date(i.due_at)}</Td><Td>{rupiah(i.total)}</Td><Td>{rupiah(i.paid_amount)}</Td><Td className="font-semibold">{rupiah(i.balance_due)}</Td><Td><Status value={i.status}/></Td></tr>)}
          {invoices.data.length===0&&<tr><td colSpan={8} className="p-8 text-center text-slate-500">Belum ada invoice. Pilih periode lalu klik Generate Invoice.</td></tr>}
        </tbody></table></div>
        {(invoices.prev_page_url||invoices.next_page_url)&&<div className="p-4 border-t border-slate-800 flex justify-between text-sm"><button disabled={!invoices.prev_page_url} onClick={()=>invoices.prev_page_url&&router.visit(invoices.prev_page_url,{preserveState:true})} className="disabled:opacity-30">← Sebelumnya</button><span className="text-slate-500">Page {invoices.current_page}/{invoices.last_page}</span><button disabled={!invoices.next_page_url} onClick={()=>invoices.next_page_url&&router.visit(invoices.next_page_url,{preserveState:true})} className="disabled:opacity-30">Berikutnya →</button></div>}
      </section>

      <section className="grid xl:grid-cols-2 gap-6">
        <Card title="Pembayaran Terbaru"><div className="space-y-2 max-h-[520px] overflow-auto">{payments.map(p=><div key={p.id} className="rounded-lg border border-slate-800 p-3"><div className="flex justify-between gap-4"><div><div className="font-semibold text-emerald-300">{p.payment_number}</div><div className="text-xs text-slate-400">{p.customer?.customer_number} · {p.customer?.name}</div><div className="text-[11px] text-slate-500">{p.method}{p.reference?` · ${p.reference}`:''} · {dateTime(p.paid_at)}</div></div><div className="text-right"><div className="font-bold">{rupiah(p.amount)}</div><div className="text-[11px] text-slate-500">{p.allocations?.map(a=>a.invoice?.invoice_number).filter(Boolean).join(', ')}</div></div></div></div>)}{payments.length===0&&<div className="text-sm text-slate-500">Belum ada pembayaran.</div>}</div></Card>
        <Card title="Billing Runs"><div className="space-y-2 max-h-[520px] overflow-auto">{runs.map(r=><details key={r.id} className="rounded-lg border border-slate-800 p-3"><summary className="cursor-pointer"><span className="font-semibold">{r.run_key}</span><span className={`ml-2 rounded px-2 py-0.5 text-[10px] ${r.error_count?'bg-amber-950 text-amber-200':'bg-emerald-950 text-emerald-200'}`}>{r.status}</span><span className="float-right text-xs text-slate-500">{r.created_count} baru</span></summary><div className="mt-2 text-xs text-slate-400">Eligible {r.eligible_count} · Created {r.created_count} · Existing {r.skipped_count} · Errors {r.error_count}</div></details>)}{runs.length===0&&<div className="text-sm text-slate-500">Belum ada billing run.</div>}</div></Card>
      </section>
    </div>
  </Layout>;
}

function Card({title,children}:{title:string,children:React.ReactNode}){return <section className="rounded-xl border border-slate-800 bg-slate-900/50 p-5"><h3 className="font-bold text-lg mb-4">{title}</h3>{children}</section>}
function Metric({title,value}:{title:string,value:any}){return <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-4"><div className="text-[10px] uppercase tracking-wide text-slate-500">{title}</div><div className="text-xl font-black mt-1 break-words">{String(value)}</div></div>}
function Th({children}:{children:React.ReactNode}){return <th className="text-left px-4 py-3 font-semibold">{children}</th>}
function Td({children,className=''}:{children:React.ReactNode,className?:string}){return <td className={`px-4 py-3 ${className}`}>{children}</td>}
function Status({value}:{value:string}){const cls=value==='paid'?'bg-emerald-950 text-emerald-200':value==='overdue'?'bg-rose-950 text-rose-200':value==='partial'?'bg-amber-950 text-amber-200':value==='void'?'bg-slate-800 text-slate-400':'bg-sky-950 text-sky-200';return <span className={`rounded px-2 py-1 text-[10px] uppercase ${cls}`}>{value}</span>}
function rupiah(v:any){return 'Rp'+Number(v||0).toLocaleString('id-ID')}
function date(v?:string){return v?new Date(v+'T00:00:00').toLocaleDateString('id-ID'):'-'}
function dateTime(v?:string){return v?new Date(v).toLocaleString('id-ID'):'-'}
