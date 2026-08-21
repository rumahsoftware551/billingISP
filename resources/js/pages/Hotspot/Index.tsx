import React, {FormEvent} from 'react';
import {Head, Link, router, useForm} from '@inertiajs/react';
import Layout from '../../components/Layout';

type Profile = {
  id:number; name:string; code:string; price:number; validity_minutes:number;
  session_timeout_minutes:number; idle_timeout_minutes:number; simultaneous_use:number;
  activation_deadline_days:number; rate_limit:string; active:boolean; vouchers_count:number; available_count:number;
};
type Batch = {
  id:number; batch_code:string; prefix:string; quantity:number; created_at:string;
  vouchers_count:number; available_count:number; sold_count:number; profile:{id:number;name:string;code:string};
};
type Voucher = {
  id:number; username:string; status:string; sold_price?:number; sale_method?:string; sale_reference?:string;
  sold_at?:string; activated_at?:string; expires_at?:string; activation_deadline_at?:string;
  profile:{id:number;name:string;code:string;price:number}; batch:{id:number;batch_code:string};
};
type Paginated<T> = {data:T[];links:{url?:string;label:string;active:boolean}[];from?:number;to?:number;total:number};

const input='w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';
const button='rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50';

export default function HotspotIndex({profiles,batches,vouchers,stats,revenue,filters}:{profiles:Profile[];batches:Batch[];vouchers:Paginated<Voucher>;stats:any;revenue:number;filters:{status:string;q:string}}){
  const profile=useForm({name:'',code:'',price:5000,validity_minutes:1440,session_timeout_minutes:480,idle_timeout_minutes:5,simultaneous_use:1,activation_deadline_days:30,rate_limit:'2M/5M'});
  const batch=useForm({hotspot_profile_id:profiles[0]?.id||'',quantity:100,prefix:'VCR',idempotency_key:globalThis.crypto.randomUUID()});
  const search=useForm({q:filters.q||'',status:filters.status||''});
  const createProfile=(e:FormEvent)=>{e.preventDefault();profile.post('/hotspot/profiles',{preserveScroll:true,onSuccess:()=>profile.reset('name','code')});};
  const createBatch=(e:FormEvent)=>{e.preventDefault();batch.post('/hotspot/batches',{preserveScroll:true,onSuccess:()=>{batch.setData('idempotency_key',globalThis.crypto.randomUUID());}});};
  const applyFilter=(e:FormEvent)=>{e.preventDefault();router.get('/hotspot',{q:search.data.q,status:search.data.status},{preserveState:true,replace:true});};
  const metric=(value:any)=>Number(value||0).toLocaleString('id-ID');

  return <Layout>
    <Head title="Hotspot Voucher"/>
    <div className="space-y-6">
      <div><h1 className="text-2xl font-black text-slate-900">Hotspot Voucher</h1><p className="mt-1 text-sm text-slate-500">Generate, jual, aktifkan, dan rekonsiliasi voucher MikroTik melalui FreeRADIUS.</p></div>

      <section className="grid grid-cols-2 gap-3 lg:grid-cols-6">
        <Metric label="Total" value={metric(stats?.total)}/><Metric label="Tersedia" value={metric(stats?.available)}/><Metric label="Terjual" value={metric(stats?.sold)}/><Metric label="Aktif" value={metric(stats?.active)}/><Metric label="Expired" value={metric(stats?.expired)}/><Metric label="Pendapatan" value={`Rp ${metric(revenue)}`}/>
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <Card title="1. Profil Voucher" subtitle="Rate limit ditulis dari sisi MikroTik: upload/download, contoh 2M/5M.">
          <form onSubmit={createProfile} className="grid gap-3 md:grid-cols-2">
            <Field label="Nama"><input className={input} value={profile.data.name} onChange={e=>profile.setData('name',e.target.value)} placeholder="Voucher Harian 5 Mbps"/></Field>
            <Field label="Kode"><input className={input} value={profile.data.code} onChange={e=>profile.setData('code',e.target.value)} placeholder="DAY5M"/></Field>
            <Field label="Harga"><input className={input} type="number" min={0} value={profile.data.price} onChange={e=>profile.setData('price',Number(e.target.value))}/></Field>
            <Field label="Masa aktif sejak login (menit)"><input className={input} type="number" min={30} value={profile.data.validity_minutes} onChange={e=>profile.setData('validity_minutes',Number(e.target.value))}/></Field>
            <Field label="Batas satu sesi (menit)"><input className={input} type="number" min={5} value={profile.data.session_timeout_minutes} onChange={e=>profile.setData('session_timeout_minutes',Number(e.target.value))}/></Field>
            <Field label="Idle timeout (menit)"><input className={input} type="number" min={1} value={profile.data.idle_timeout_minutes} onChange={e=>profile.setData('idle_timeout_minutes',Number(e.target.value))}/></Field>
            <Field label="Login bersamaan"><input className={input} type="number" min={1} max={10} value={profile.data.simultaneous_use} onChange={e=>profile.setData('simultaneous_use',Number(e.target.value))}/></Field>
            <Field label="Batas aktivasi setelah dijual (hari)"><input className={input} type="number" min={1} max={365} value={profile.data.activation_deadline_days} onChange={e=>profile.setData('activation_deadline_days',Number(e.target.value))}/></Field>
            <Field label="Rate limit"><input className={input} value={profile.data.rate_limit} onChange={e=>profile.setData('rate_limit',e.target.value)} placeholder="2M/5M"/></Field>
            <button className={`${button} self-end`} disabled={profile.processing}>Simpan Profil</button>
          </form>
          <Errors errors={profile.errors}/>
          <div className="mt-5 space-y-2">{profiles.map(p=><div key={p.id} className="flex justify-between gap-4 rounded-xl border border-slate-200 p-3"><div><div className="font-bold text-slate-900">{p.name} <span className="text-xs text-slate-400">{p.code}</span></div><div className="text-xs text-slate-500">{p.rate_limit} · {duration(p.validity_minutes)} · Rp {metric(p.price)}</div></div><div className="text-right text-xs text-slate-500">{metric(p.vouchers_count)} voucher<br/>{metric(p.available_count)} tersedia</div></div>)}</div>
        </Card>

        <Card title="2. Generate Batch" subtitle="Maksimal 1.000 voucher per batch agar aman pada VPS RAM 4 GB.">
          <form onSubmit={createBatch} className="space-y-3">
            <Field label="Profil"><select className={input} value={batch.data.hotspot_profile_id} onChange={e=>batch.setData('hotspot_profile_id',Number(e.target.value))}><option value="">Pilih profil</option>{profiles.filter(p=>p.active).map(p=><option key={p.id} value={p.id}>{p.name} · Rp {metric(p.price)}</option>)}</select></Field>
            <div className="grid grid-cols-2 gap-3"><Field label="Jumlah"><input className={input} type="number" min={1} max={1000} value={batch.data.quantity} onChange={e=>batch.setData('quantity',Number(e.target.value))}/></Field><Field label="Prefix"><input className={input} value={batch.data.prefix} onChange={e=>batch.setData('prefix',e.target.value)} maxLength={12}/></Field></div>
            <button className={button} disabled={batch.processing||profiles.length===0}>Generate Voucher</button>
          </form>
          <Errors errors={batch.errors}/>
          <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">File CSV memuat password voucher. Simpan terbatas, jangan kirim melalui grup publik, dan hapus salinan yang tidak diperlukan.</div>
          <div className="mt-4 max-h-80 space-y-2 overflow-auto">{batches.map(b=><div key={b.id} className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3"><div><div className="font-bold text-slate-900">{b.batch_code}</div><div className="text-xs text-slate-500">{b.profile.name} · {b.available_count} tersedia · {b.sold_count} diproses</div></div><a href={`/hotspot/batches/${b.id}/export`} className="rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50">Unduh CSV</a></div>)}</div>
        </Card>
      </section>

      <Card title="3. Penjualan dan Status Voucher" subtitle="Voucher baru dapat login setelah ditandai terjual. Aktivasi dihitung dari Accounting-Start pertama.">
        <form onSubmit={applyFilter} className="mb-4 grid gap-3 md:grid-cols-[1fr_200px_auto]">
          <input className={input} value={search.data.q} onChange={e=>search.setData('q',e.target.value)} placeholder="Cari username voucher"/>
          <select className={input} value={search.data.status} onChange={e=>search.setData('status',e.target.value)}><option value="">Semua status</option>{['available','sold','active','disabled','expired'].map(s=><option key={s} value={s}>{s}</option>)}</select>
          <button className={button}>Filter</button>
        </form>
        <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="border-b text-left text-xs uppercase tracking-wide text-slate-500"><th className="p-3">Voucher</th><th className="p-3">Profil</th><th className="p-3">Status</th><th className="p-3">Penjualan</th><th className="p-3">Masa aktif</th><th className="p-3">Aksi</th></tr></thead><tbody>{vouchers.data.map(v=><tr key={v.id} className="border-b border-slate-100 align-top"><td className="p-3"><div className="font-mono font-bold text-slate-900">{v.username}</div><div className="text-xs text-slate-400">{v.batch.batch_code}</div></td><td className="p-3">{v.profile.name}<div className="text-xs text-slate-400">Rp {metric(v.profile.price)}</div></td><td className="p-3"><Status value={v.status}/></td><td className="p-3">{v.sold_at?<><div>{v.sale_method?.toUpperCase()}</div><div className="text-xs text-slate-400">{new Date(v.sold_at).toLocaleString('id-ID')}</div></>:<SaleForm voucher={v}/>}</td><td className="p-3 text-xs text-slate-500">{v.activated_at?<>Aktif {new Date(v.activated_at).toLocaleString('id-ID')}<br/>s.d. {v.expires_at?new Date(v.expires_at).toLocaleString('id-ID'):'-'}</>:v.activation_deadline_at?<>Aktivasi sebelum<br/>{new Date(v.activation_deadline_at).toLocaleString('id-ID')}</>:'Belum dijual'}</td><td className="p-3"><VoucherActions voucher={v}/></td></tr>)}</tbody></table></div>
        {vouchers.data.length===0&&<div className="py-10 text-center text-sm text-slate-500">Belum ada voucher sesuai filter.</div>}
        <div className="mt-4 flex flex-wrap gap-2">{vouchers.links.map((link,i)=><Link key={i} preserveScroll href={link.url||'#'} className={`rounded-lg border px-3 py-1.5 text-xs ${link.active?'border-blue-600 bg-blue-600 text-white':'border-slate-200 text-slate-600'} ${!link.url?'pointer-events-none opacity-40':''}`} dangerouslySetInnerHTML={{__html:link.label}}/>)}</div>
      </Card>
    </div>
  </Layout>;
}

function SaleForm({voucher}:{voucher:Voucher}){const form=useForm({method:'cash',reference:'',idempotency_key:globalThis.crypto.randomUUID()});const submit=(e:FormEvent)=>{e.preventDefault();form.post(`/hotspot/vouchers/${voucher.id}/sell`,{preserveScroll:true,onSuccess:()=>form.setData('idempotency_key',globalThis.crypto.randomUUID())});};return <form onSubmit={submit} className="min-w-40 space-y-1"><select className="w-full rounded border border-slate-300 px-2 py-1 text-xs" value={form.data.method} onChange={e=>form.setData('method',e.target.value)}><option value="cash">Cash</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select><input className="w-full rounded border border-slate-300 px-2 py-1 text-xs" value={form.data.reference} onChange={e=>form.setData('reference',e.target.value)} placeholder="Referensi (opsional)"/><button disabled={form.processing} className="w-full rounded bg-emerald-600 px-2 py-1 text-xs font-bold text-white">Tandai Terjual</button></form>}
function VoucherActions({voucher}:{voucher:Voucher}){if(['sold','active'].includes(voucher.status))return <button onClick={()=>confirm('Nonaktifkan voucher ini?')&&router.post(`/hotspot/vouchers/${voucher.id}/disable`,{},{preserveScroll:true})} className="rounded bg-rose-100 px-2 py-1 text-xs font-bold text-rose-700">Nonaktifkan</button>;if(voucher.status==='disabled')return <button onClick={()=>router.post(`/hotspot/vouchers/${voucher.id}/enable`,{},{preserveScroll:true})} className="rounded bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">Aktifkan</button>;return <span className="text-xs text-slate-400">—</span>}
function Metric({label,value}:{label:string;value:string}){return <div className="rounded-2xl border border-slate-200 bg-white p-4"><div className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</div><div className="mt-1 text-xl font-black text-slate-900">{value}</div></div>}
function Card({title,subtitle,children}:{title:string;subtitle:string;children:React.ReactNode}){return <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 className="text-lg font-black text-slate-900">{title}</h2><p className="mb-4 mt-1 text-xs text-slate-500">{subtitle}</p>{children}</section>}
function Field({label,children}:{label:string;children:React.ReactNode}){return <label className="block text-xs font-bold text-slate-600"><span className="mb-1 block">{label}</span>{children}</label>}
function Errors({errors}:{errors:Record<string,string>}){const values=Object.values(errors);return values.length?<div className="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700">{values.join(' ')}</div>:null}
function Status({value}:{value:string}){const colors:Record<string,string>={available:'bg-slate-100 text-slate-700',sold:'bg-amber-100 text-amber-800',active:'bg-emerald-100 text-emerald-800',disabled:'bg-rose-100 text-rose-800',expired:'bg-zinc-200 text-zinc-700'};return <span className={`rounded-full px-2 py-1 text-[10px] font-black uppercase ${colors[value]||colors.available}`}>{value}</span>}
function duration(minutes:number){if(minutes%1440===0)return `${minutes/1440} hari`;if(minutes%60===0)return `${minutes/60} jam`;return `${minutes} menit`}
