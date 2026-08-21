import React from 'react';
import {Head, Link, router} from '@inertiajs/react';
import Layout from '../../components/Layout';
export default function MockGateway({transaction}:{transaction:any}){
  return <Layout><Head title="Mock QRIS Payment"/><div className="max-w-xl mx-auto rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-5">
    <div><div className="text-xs uppercase tracking-wider text-amber-300">LOCAL TEST ONLY</div><h2 className="text-2xl font-black mt-1">Mock QRIS Payment</h2><p className="text-sm text-slate-400">Simulasi provider untuk acceptance test Phase 09. Tidak melakukan transaksi uang nyata.</p></div>
    <div className="rounded-xl bg-white text-slate-900 p-8 text-center"><div className="mx-auto grid h-44 w-44 grid-cols-9 gap-1 bg-slate-900 p-3">{Array.from({length:81}).map((_,i)=><div key={i} className={(i*7+i%5)%3===0?'bg-white':'bg-slate-900'}/>)}</div><div className="mt-4 font-bold">SCAN QRIS MOCK</div></div>
    <div className="space-y-2 text-sm"><Row label="Order ID" value={transaction.order_id}/><Row label="Invoice" value={transaction.invoice?.invoice_number}/><Row label="Customer" value={transaction.invoice?.customer?.name}/><Row label="Amount" value={'Rp'+Number(transaction.amount).toLocaleString('id-ID')}/><Row label="Status" value={transaction.status}/></div>
    {transaction.status==='pending'?<button onClick={()=>router.post(`/billing/gateway-transactions/${transaction.id}/mock-settle`)} className="w-full rounded-lg bg-emerald-600 py-3 font-bold hover:bg-emerald-500">Simulate Payment Success</button>:<div className="rounded-lg border border-emerald-800 bg-emerald-950/40 p-3 text-emerald-200">Transaction already {transaction.status}.</div>}
    <Link href={`/billing/invoices/${transaction.invoice_id}`} className="block text-center text-sm text-sky-400">← Back to invoice</Link>
  </div></Layout>
}
function Row({label,value}:{label:string,value:any}){return <div className="flex justify-between gap-4 border-b border-slate-800 pb-2"><span className="text-slate-500">{label}</span><span className="font-medium text-right">{String(value||'-')}</span></div>}
