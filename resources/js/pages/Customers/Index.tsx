import React, {FormEvent, useState} from 'react';
import {Head, Link, router, useForm} from '@inertiajs/react';
import {Download, Plus, Search, UserCheck, Users, Wifi, WifiOff} from 'lucide-react';
import Layout from '../../components/Layout';

type Customer = {
  id:number; customer_number:string; name:string; customer_type:string; email?:string; phone?:string;
  status:string; services_count:number; created_at:string;
};
type Pagination = {data:Customer[]; current_page:number; last_page:number; total:number; links:{url?:string|null,label:string,active:boolean}[]};
type Filters = {q?:string; status?:string};

const field='mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

export default function CustomersIndex({customers,filters={},stats}:{customers:Pagination,filters:Filters,stats:any}){
  const [showForm,setShowForm]=useState(false);
  const [q,setQ]=useState(filters.q||'');
  const [status,setStatus]=useState(filters.status||'');
  const form=useForm({name:'',customer_type:'residential',identity_number:'',email:'',phone:'',secondary_phone:'',notes:'',address_line:'',village:'',district:'',city:'',province:'',postal_code:''});

  const applyFilter=(e?:FormEvent)=>{e?.preventDefault();router.get('/customers',{q,status},{preserveState:true,replace:true});};
  const submit=(e:FormEvent)=>{e.preventDefault();form.post('/customers',{preserveScroll:true,onSuccess:()=>{form.reset();setShowForm(false)}})};

  return <Layout><Head title="Pelanggan"/><div className="space-y-6">
    <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div><div className="text-xs font-bold uppercase tracking-[.16em] text-blue-600">Subscriber Management</div><h1 className="mt-1 text-2xl font-black tracking-tight text-slate-950">Data Pelanggan</h1><p className="mt-1 max-w-3xl text-sm text-slate-500">Kelola identitas pelanggan, kontak dan layanan. Setiap kolom input diberi label agar staf tidak perlu menebak arti field.</p></div>
      <div className="flex flex-wrap gap-2"><button onClick={()=>setShowForm(v=>!v)} className="inline-flex items-center gap-2 rounded-xl bg-[var(--jk-primary)] px-4 py-2.5 text-sm font-bold text-white shadow-sm"><Plus size={17}/> Tambah Pelanggan</button><a href="/reports/export/customers" className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><Download size={16}/> Export</a></div>
    </header>

    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Metric icon={Users} label="Total Pelanggan" value={stats.customers} hint="Semua pelanggan"/>
      <Metric icon={UserCheck} label="Pelanggan Aktif" value={stats.active_customers} hint="Status customer aktif" tone="emerald"/>
      <Metric icon={Wifi} label="Total Layanan" value={stats.services} hint="PPPoE/service terdaftar" tone="blue"/>
      <Metric icon={WifiOff} label="Layanan Aktif" value={stats.active_services} hint="Service aktif saat ini" tone="amber"/>
    </section>

    {showForm&&<section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between gap-4"><div><h2 className="font-bold text-slate-950">Tambah Pelanggan Baru</h2><p className="mt-1 text-sm text-slate-500">Nomor pelanggan dibuat otomatis. Isi data yang diketahui; alamat dapat dilengkapi lagi di detail pelanggan.</p></div><button type="button" onClick={()=>setShowForm(false)} className="text-sm font-semibold text-slate-500">Tutup</button></div>
      <form onSubmit={submit} className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Field label="Nama lengkap / nama usaha" required error={form.errors.name}><input className={field} value={form.data.name} onChange={e=>form.setData('name',e.target.value)} placeholder="Contoh: Aris Setiawan"/></Field>
        <Field label="Jenis pelanggan" required error={form.errors.customer_type}><select className={field} value={form.data.customer_type} onChange={e=>form.setData('customer_type',e.target.value)}><option value="residential">Rumah / Residential</option><option value="business">Usaha / Business</option></select></Field>
        <Field label="Nomor HP / WhatsApp" help="Gunakan nomor aktif untuk notifikasi tagihan." error={form.errors.phone}><input className={field} value={form.data.phone} onChange={e=>form.setData('phone',e.target.value)} placeholder="08xxxxxxxxxx"/></Field>
        <Field label="Nomor HP alternatif" error={form.errors.secondary_phone}><input className={field} value={form.data.secondary_phone} onChange={e=>form.setData('secondary_phone',e.target.value)} placeholder="Opsional"/></Field>
        <Field label="Email pelanggan" error={form.errors.email}><input className={field} type="email" value={form.data.email} onChange={e=>form.setData('email',e.target.value)} placeholder="nama@email.com"/></Field>
        <Field label="NIK / nomor identitas" error={form.errors.identity_number}><input className={field} value={form.data.identity_number} onChange={e=>form.setData('identity_number',e.target.value)} placeholder="Opsional"/></Field>
        <div className="md:col-span-2"><Field label="Alamat pemasangan" error={form.errors.address_line}><textarea className={field} rows={3} value={form.data.address_line} onChange={e=>form.setData('address_line',e.target.value)} placeholder="Jalan, RT/RW, nomor rumah, patokan"/></Field></div>
        <Field label="Desa / Kelurahan" error={form.errors.village}><input className={field} value={form.data.village} onChange={e=>form.setData('village',e.target.value)}/></Field>
        <Field label="Kecamatan" error={form.errors.district}><input className={field} value={form.data.district} onChange={e=>form.setData('district',e.target.value)}/></Field>
        <Field label="Kota / Kabupaten" error={form.errors.city}><input className={field} value={form.data.city} onChange={e=>form.setData('city',e.target.value)}/></Field>
        <Field label="Provinsi" error={form.errors.province}><input className={field} value={form.data.province} onChange={e=>form.setData('province',e.target.value)}/></Field>
        <div className="md:col-span-2 xl:col-span-4"><Field label="Catatan internal" help="Tidak ditampilkan ke pelanggan." error={form.errors.notes}><textarea className={field} rows={2} value={form.data.notes} onChange={e=>form.setData('notes',e.target.value)} placeholder="Catatan teknis, patokan rumah, informasi collector, dll."/></Field></div>
        <div className="md:col-span-2 xl:col-span-4 flex justify-end"><button disabled={form.processing} className="rounded-xl bg-[var(--jk-primary)] px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{form.processing?'Menyimpan...':'Simpan Pelanggan'}</button></div>
      </form>
    </section>}

    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-200 p-4 lg:p-5"><form onSubmit={applyFilter} className="grid gap-3 md:grid-cols-[1fr_220px_auto]"><label className="relative"><Search size={17} className="absolute left-3 top-3 text-slate-400"/><input value={q} onChange={e=>setQ(e.target.value)} className="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm outline-none focus:border-blue-500" placeholder="Cari nama, nomor pelanggan, HP atau email..."/></label><select value={status} onChange={e=>setStatus(e.target.value)} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select><button className="rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700">Terapkan Filter</button></form></div>
      <div className="overflow-x-auto"><table className="min-w-[900px] w-full text-sm"><thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">Pelanggan</th><th className="px-5 py-3">Kontak</th><th className="px-5 py-3">Jenis</th><th className="px-5 py-3">Layanan</th><th className="px-5 py-3">Status</th><th className="px-5 py-3 text-right">Aksi</th></tr></thead><tbody>{customers.data.map(c=><tr key={c.id} className="border-t border-slate-100 hover:bg-slate-50/80"><td className="px-5 py-4"><div className="font-bold text-slate-900">{c.name}</div><div className="mt-0.5 text-xs font-semibold text-blue-600">{c.customer_number}</div></td><td className="px-5 py-4"><div>{c.phone||'-'}</div><div className="mt-0.5 text-xs text-slate-400">{c.email||'Email belum diisi'}</div></td><td className="px-5 py-4"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{c.customer_type==='business'?'Usaha':'Rumah'}</span></td><td className="px-5 py-4"><span className="font-bold">{c.services_count}</span><span className="ml-1 text-xs text-slate-400">layanan</span></td><td className="px-5 py-4"><Status value={c.status}/></td><td className="px-5 py-4 text-right"><Link href={`/customers/${c.id}`} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:border-blue-400 hover:text-blue-700">Buka Detail</Link></td></tr>)}{customers.data.length===0&&<tr><td colSpan={6} className="p-12 text-center text-slate-400">Tidak ada pelanggan yang sesuai filter.</td></tr>}</tbody></table></div>
      <div className="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between"><span className="text-slate-500">Total {customers.total.toLocaleString('id-ID')} pelanggan</span>{customers.last_page>1&&<div className="flex flex-wrap gap-1">{customers.links.map((l,i)=><button key={i} disabled={!l.url} onClick={()=>l.url&&router.visit(l.url,{preserveState:true})} dangerouslySetInnerHTML={{__html:l.label}} className={`min-w-9 rounded-lg border px-2.5 py-1.5 text-xs ${l.active?'border-blue-600 bg-blue-600 text-white':'border-slate-200 bg-white text-slate-600'} disabled:opacity-30`}/>)}</div>}</div>
    </section>
  </div></Layout>;
}

function Field({label,required,help,error,children}:{label:string;required?:boolean;help?:string;error?:string;children:React.ReactNode}){return <label className="block text-sm font-semibold text-slate-700"><span>{label}{required&&<span className="ml-1 text-rose-500">*</span>}</span>{children}{help&&<span className="mt-1 block text-xs font-normal text-slate-400">{help}</span>}{error&&<span className="mt-1 block text-xs font-normal text-rose-600">{error}</span>}</label>}
function Metric({icon:Icon,label,value,hint,tone='slate'}:any){const tones:any={slate:'bg-slate-50 text-slate-600',emerald:'bg-emerald-50 text-emerald-600',blue:'bg-blue-50 text-blue-600',amber:'bg-amber-50 text-amber-600'};return <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between"><div><div className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</div><div className="mt-1 text-2xl font-black text-slate-950">{Number(value||0).toLocaleString('id-ID')}</div><div className="mt-1 text-xs text-slate-400">{hint}</div></div><div className={`rounded-xl p-2.5 ${tones[tone]}`}><Icon size={20}/></div></div></div>}
function Status({value}:{value:string}){return <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ${value==='active'?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-600'}`}><span className={`h-1.5 w-1.5 rounded-full ${value==='active'?'bg-emerald-500':'bg-slate-400'}`}/>{value==='active'?'Aktif':'Nonaktif'}</span>}
