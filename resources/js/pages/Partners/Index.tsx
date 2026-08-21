import React from 'react';
import Layout from '../../components/Layout';
import {router,useForm} from '@inertiajs/react';
import {Banknote,Handshake,UsersRound,WalletCards} from 'lucide-react';
import {MetricCard,PageHeader,Surface} from '../../components/Ui';
import {useAccess} from '../../hooks/useAccess';

const money=(n:number)=>'Rp'+new Intl.NumberFormat('id-ID').format(n||0);
const input='w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';
const button='rounded-xl bg-[var(--jk-primary)] px-3 py-2 text-xs font-bold text-white disabled:opacity-50';

function PartnerActions({p,customers}:any){
  const a=useForm({name:'Owner Mitra',email:'',role:'owner',password:''});
  const r=useForm({name:'Komisi Pembayaran 10%',type:'payment_percent',value:1000,active:true});
  const c=useForm({customer_id:customers[0]?.id||''});
  return <div className="mt-4 grid gap-3 xl:grid-cols-3">
    <form onSubmit={e=>{e.preventDefault();a.post(`/partners/${p.id}/accounts`,{preserveScroll:true})}} className="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2">
      <div className="text-xs font-black uppercase tracking-wide text-slate-500">Akun Portal</div>
      <input placeholder="Nama akun" value={a.data.name} onChange={e=>a.setData('name',e.target.value)} className={input}/>
      <input placeholder="Email" type="email" value={a.data.email} onChange={e=>a.setData('email',e.target.value)} className={input}/>
      <input placeholder="Password sementara" type="password" value={a.data.password} onChange={e=>a.setData('password',e.target.value)} className={input}/>
      <button disabled={a.processing} className={button}>Tambah Akun</button>
    </form>
    <form onSubmit={e=>{e.preventDefault();r.post(`/partners/${p.id}/commission-rules`,{preserveScroll:true})}} className="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2">
      <div className="text-xs font-black uppercase tracking-wide text-slate-500">Aturan Komisi</div>
      <select value={r.data.type} onChange={e=>r.setData('type',e.target.value)} className={input}><option value="payment_percent">% Pembayaran (basis point)</option><option value="payment_fixed">Nominal / pembayaran</option><option value="activation_fixed">Aktivasi</option><option value="active_customer_fixed">Pelanggan aktif</option></select>
      <input type="number" value={r.data.value} onChange={e=>r.setData('value',Number(e.target.value))} className={input}/>
      <button disabled={r.processing} className={button}>Tambah Komisi</button>
    </form>
    <form onSubmit={e=>{e.preventDefault();c.post(`/partners/${p.id}/customers`,{preserveScroll:true})}} className="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2">
      <div className="text-xs font-black uppercase tracking-wide text-slate-500">Assign Pelanggan</div>
      <select value={c.data.customer_id} onChange={e=>c.setData('customer_id',e.target.value)} className={input}><option value="">Pilih pelanggan</option>{customers.map((x:any)=><option key={x.id} value={x.id}>{x.customer_number} · {x.name}</option>)}</select>
      <button disabled={c.processing||!c.data.customer_id} className={button}>Assign Customer</button>
    </form>
  </div>;
}

export default function Index({partners,unassignedCustomers,withdrawals}:any){
  const {can}=useAccess();
  const canManage=can('partners.manage');
  const f=useForm({name:'',email:'',phone:'',area_name:'',address:'',bank_name:'',bank_account:'',bank_holder:''});
  const available=partners.reduce((sum:number,p:any)=>sum+Number(p.commission_available||0),0);
  const customers=partners.reduce((sum:number,p:any)=>sum+Number(p.customers_count||0),0);
  const requested=withdrawals.filter((w:any)=>w.status==='requested').length;
  return <Layout><div className="space-y-6">
    <PageHeader eyebrow="Bisnis" title="Mitra & Reseller" description="Kelola akun mitra, area, pelanggan, skema komisi dan withdrawal dengan kontrol akses yang jelas."/>
    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <MetricCard icon={Handshake} label="Total Mitra" value={partners.length} tone="blue"/>
      <MetricCard icon={UsersRound} label="Pelanggan Mitra" value={customers} tone="cyan"/>
      <MetricCard icon={WalletCards} label="Komisi Tersedia" value={money(available)} tone="emerald"/>
      <MetricCard icon={Banknote} label="Withdrawal Menunggu" value={requested} tone="amber"/>
    </section>
    <div className={`grid gap-5 ${canManage?'xl:grid-cols-[360px_1fr]':''}`}>
      {canManage&&<Surface title="Mitra Baru" subtitle="Buat entitas reseller/mitra di bawah tenant ISP."><form onSubmit={e=>{e.preventDefault();f.post('/partners',{preserveScroll:true,onSuccess:()=>f.reset()})}} className="space-y-3">
        {Object.keys(f.data).map(k=><label key={k} className="block text-xs font-bold uppercase tracking-wide text-slate-500">{k.replaceAll('_',' ')}<input value={(f.data as any)[k]} onChange={e=>f.setData(k as any,e.target.value)} className={`${input} mt-1.5`}/></label>)}
        <button disabled={f.processing} className="w-full rounded-xl bg-[var(--jk-primary)] p-3 text-sm font-bold text-white disabled:opacity-50">Buat Mitra</button>
      </form></Surface>}
      <Surface title="Daftar Mitra" subtitle={`${partners.length} mitra terdaftar pada tenant ini.`}>
        <div className="divide-y divide-slate-100">{partners.map((p:any)=><div key={p.id} className="py-5 first:pt-0 last:pb-0"><div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between"><div><div className="font-black text-slate-900">{p.code} · {p.name}</div><div className="mt-1 text-xs text-slate-500">{p.area_name||'Area belum diatur'} · {p.customers_count} pelanggan · {p.accounts_count} akun</div></div><div className="md:text-right"><div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Komisi tersedia</div><div className="font-black text-emerald-700">{money(p.commission_available)}</div></div></div>{canManage&&<PartnerActions p={p} customers={unassignedCustomers}/>}</div>)}{!partners.length&&<div className="py-10 text-center text-sm text-slate-400">Belum ada mitra.</div>}</div>
      </Surface>
    </div>
    <Surface title="Withdrawal Mitra" subtitle="Approval pencairan komisi mitra dan jejak status pembayaran.">
      <div className="divide-y divide-slate-100">{withdrawals.map((w:any)=><div key={w.id} className="flex flex-col gap-3 py-3 first:pt-0 md:flex-row md:items-center md:justify-between"><div><div className="font-semibold text-slate-800">{w.withdrawal_number} · {w.partner.name}</div><div className="mt-1 text-xs text-slate-500">{money(w.amount)} · <span className="uppercase">{w.status}</span></div></div>{canManage&&<div className="flex gap-2">{(w.status==='requested'?['approved','rejected']:w.status==='approved'?['paid','rejected']:[]).map((s:string)=><button key={s} onClick={()=>router.put(`/partner-withdrawals/${w.id}`,{status:s},{preserveScroll:true})} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold capitalize text-slate-700 hover:bg-slate-50">{s}</button>)}</div>}</div>)}{!withdrawals.length&&<div className="py-8 text-center text-sm text-slate-400">Belum ada withdrawal.</div>}</div>
    </Surface>
  </div></Layout>;
}
