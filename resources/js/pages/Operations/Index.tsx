import React from 'react';
import {Head, Link, router, useForm} from '@inertiajs/react';
import Layout from '../../components/Layout';

const input='w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500';

export default function Operations({policy,stats,suspensions,runs,events}:{policy:any,stats:any,suspensions:any[],runs:any[],events:any[]}){
  const form=useForm({
    grace_days:Number(policy.grace_days||0),
    auto_suspend:Boolean(policy.auto_suspend),
    auto_reactivate:Boolean(policy.auto_reactivate),
    disconnect_on_suspend:Boolean(policy.disconnect_on_suspend),
  });

  return <Layout>
    <Head title="Operations Automation"/>
    <div className="space-y-6">
      <div className="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div><h2 className="text-2xl font-black">Operations & Billing Automation</h2><p className="text-sm text-slate-500 mt-1">Auto isolir tagihan, disconnect PPPoE, dan reaktivasi otomatis setelah pembayaran.</p></div>
        <button onClick={()=>router.post('/operations/run',{}, {preserveScroll:true})} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold hover:bg-sky-500">Jalankan Automation Sekarang</button>
      </div>

      <section className="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <Metric title="Service Suspended" value={stats.suspended_services}/>
        <Metric title="Billing Suspension Aktif" value={stats.billing_suspensions}/>
        <Metric title="Run Hari Ini" value={stats.runs_today}/>
        <Metric title="Error Hari Ini" value={stats.errors_today}/>
      </section>

      <section className="grid xl:grid-cols-[420px_1fr] gap-6">
        <Card title="Automation Policy">
          <form className="space-y-4" onSubmit={e=>{e.preventDefault();form.put('/operations/policy',{preserveScroll:true});}}>
            <div><label className="text-xs text-slate-500">Grace period setelah due date (hari)</label><input type="number" min={0} max={30} className={`${input} mt-1`} value={form.data.grace_days} onChange={e=>form.setData('grace_days',Number(e.target.value))}/>{form.errors.grace_days&&<div className="text-xs text-rose-700 mt-1">{form.errors.grace_days}</div>}</div>
            <Toggle label="Auto suspend layanan menunggak" checked={form.data.auto_suspend} onChange={v=>form.setData('auto_suspend',v)}/>
            <Toggle label="Auto reactivate setelah tagihan blocking clear" checked={form.data.auto_reactivate} onChange={v=>form.setData('auto_reactivate',v)}/>
            <Toggle label="Disconnect session aktif saat suspend" checked={form.data.disconnect_on_suspend} onChange={v=>form.setData('disconnect_on_suspend',v)}/>
            <button disabled={form.processing} className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold hover:bg-emerald-600 disabled:opacity-50">Simpan Policy</button>
          </form>
          <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-500">Suspensi manual/operasional tidak pernah diaktifkan kembali oleh automation billing. Hanya suspensi dengan source <b className="text-slate-700">billing_automation</b> yang dapat direaktivasi otomatis.</div>
        </Card>

        <Card title="Suspension History">
          <div className="overflow-x-auto"><table className="min-w-[900px] w-full text-sm"><thead className="text-xs uppercase text-slate-500"><tr><Th>Customer / Service</Th><Th>Source</Th><Th>Invoice</Th><Th>Suspended</Th><Th>Resolved</Th><Th>Status</Th></tr></thead><tbody>
            {suspensions.map(s=><tr key={s.id} className="border-t border-slate-200"><Td><Link href={`/customers/${s.service?.customer_id}`} className="font-semibold hover:text-blue-700">{s.service?.customer?.customer_number} · {s.service?.customer?.name}</Link><div className="text-xs text-slate-500">{s.service?.service_number} · {s.service?.pppoe_username}</div></Td><Td><span className="rounded bg-slate-800 px-2 py-1 text-[10px] uppercase">{s.source}</span></Td><Td>{s.invoice?<Link href={`/billing/invoices/${s.invoice.id}`} className="text-blue-700">{s.invoice.invoice_number}</Link>:'-'}<div className="text-xs text-slate-500">{s.invoice?`${rupiah(s.invoice.balance_due)} · due ${date(s.invoice.due_at)}`:''}</div></Td><Td>{dateTime(s.suspended_at)}</Td><Td>{s.resolved_at?dateTime(s.resolved_at):'-'}{s.resolved_by_payment&&<div className="text-xs text-emerald-400">{s.resolved_by_payment.payment_number}</div>}</Td><Td><Badge value={s.status}/></Td></tr>)}
            {suspensions.length===0&&<tr><td colSpan={6} className="p-8 text-center text-slate-500">Belum ada suspension automation.</td></tr>}
          </tbody></table></div>
        </Card>
      </section>

      <section className="grid xl:grid-cols-2 gap-6">
        <Card title="Automation Runs"><div className="space-y-2 max-h-[580px] overflow-auto">{runs.map(r=><details key={r.id} className="rounded-lg border border-slate-200 p-3"><summary className="cursor-pointer"><span className="font-semibold">{r.run_key}</span><span className="ml-2 text-[10px] uppercase rounded bg-slate-800 px-2 py-0.5">{r.source}</span><span className="float-right"><Badge value={r.status}/></span></summary><div className="mt-2 text-xs text-slate-500">Scanned {r.scanned_count} · Suspended {r.suspended_count} · Reactivated {r.reactivated_count} · Enforced {r.enforced_count} · Skipped {r.skipped_count} · Errors {r.error_count}</div><div className="mt-1 text-[11px] text-slate-600">{dateTime(r.started_at)}{r.finished_at?` → ${dateTime(r.finished_at)}`:''}</div></details>)}{runs.length===0&&<div className="text-sm text-slate-500">Belum ada automation run.</div>}</div></Card>
        <Card title="Automation Events"><div className="space-y-2 max-h-[580px] overflow-auto">{events.map(e=><div key={e.id} className="rounded-lg border border-slate-200 p-3"><div className="flex justify-between gap-3"><div><div className={e.success?'font-semibold text-emerald-700':'font-semibold text-rose-700'}>{e.event}</div><div className="text-xs text-slate-500">{e.service?.customer?.customer_number} · {e.service?.service_number} · {e.service?.pppoe_username}</div><div className="text-xs text-slate-500 mt-1">{e.message}</div><div className="text-[11px] text-slate-600 mt-1">{dateTime(e.created_at)}{e.invoice?` · ${e.invoice.invoice_number}`:''}{e.payment?` · ${e.payment.payment_number}`:''}</div></div><span className={`h-fit rounded px-2 py-1 text-[10px] ${e.success?'bg-emerald-50 text-emerald-200':'bg-rose-50 text-rose-200'}`}>{e.success?'OK':'ERROR'}</span></div></div>)}{events.length===0&&<div className="text-sm text-slate-500">Belum ada event.</div>}</div></Card>
      </section>
    </div>
  </Layout>;
}

function Toggle({label,checked,onChange}:{label:string,checked:boolean,onChange:(v:boolean)=>void}){return <label className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 p-3"><span className="text-sm">{label}</span><input type="checkbox" checked={checked} onChange={e=>onChange(e.target.checked)} className="h-5 w-5 accent-sky-500"/></label>}
function Card({title,children}:{title:string,children:React.ReactNode}){return <section className="rounded-xl border border-slate-200 bg-white p-5"><h3 className="font-bold text-lg mb-4">{title}</h3>{children}</section>}
function Metric({title,value}:{title:string,value:any}){return <div className="rounded-xl border border-slate-200 bg-white p-4"><div className="text-[10px] uppercase tracking-wide text-slate-500">{title}</div><div className="text-xl font-black mt-1">{String(value)}</div></div>}
function Th({children}:{children:React.ReactNode}){return <th className="text-left px-3 py-2 font-semibold">{children}</th>}
function Td({children}:{children:React.ReactNode}){return <td className="px-3 py-3 align-top">{children}</td>}
function Badge({value}:{value:string}){const ok=['completed','resolved','active'].includes(value);const warn=['running','completed_with_errors'].includes(value);const cls=ok?'bg-emerald-50 text-emerald-200':warn?'bg-amber-50 text-amber-700':value==='failed'?'bg-rose-50 text-rose-200':'bg-slate-800 text-slate-700';return <span className={`rounded px-2 py-1 text-[10px] uppercase ${cls}`}>{value}</span>}
function rupiah(v:any){return 'Rp'+Number(v||0).toLocaleString('id-ID')}
function date(v?:string){return v?new Date(v+'T00:00:00').toLocaleDateString('id-ID'):'-'}
function dateTime(v?:string){return v?new Date(v).toLocaleString('id-ID'):'-'}
