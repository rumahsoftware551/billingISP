import React, {FormEvent, useState} from 'react';
import {Head, Link, router, useForm} from '@inertiajs/react';
import {Download, Plus, Search, UserCheck, Users, Wifi, WifiOff} from 'lucide-react';
import Layout from '../../components/Layout';
import {EmptyState, MetricCard, PageHeader, Surface} from '../../components/Ui';
import {useAccess} from '../../hooks/useAccess';

type Customer = {
  id:number; customer_number:string; name:string; customer_type:string; email?:string; phone?:string;
  status:string; services_count:number; created_at:string;
};
type Pagination = {data:Customer[]; current_page:number; last_page:number; total:number; links:{url?:string|null,label:string,active:boolean}[]};
type Filters = {q?:string; status?:string};

type Stats = {customers:number; active_customers:number; services:number; active_services:number};

const field='mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-[var(--jk-primary)] focus:ring-2 focus:ring-blue-100';

export default function CustomersIndex({customers,filters={},stats}:{customers:Pagination,filters:Filters,stats:Stats}){
  const {can}=useAccess();
  const canManage=can('customers.manage');
  const canExport=can('reports.view');
  const [showForm,setShowForm]=useState(false);
  const [q,setQ]=useState(filters.q||'');
  const [status,setStatus]=useState(filters.status||'');
  const form=useForm({name:'',customer_type:'residential',identity_number:'',email:'',phone:'',secondary_phone:'',notes:'',address_line:'',village:'',district:'',city:'',province:'',postal_code:''});

  const applyFilter=(e?:FormEvent)=>{e?.preventDefault();router.get('/customers',{q,status},{preserveState:true,replace:true});};
  const submit=(e:FormEvent)=>{e.preventDefault();form.post('/customers',{preserveScroll:true,onSuccess:()=>{form.reset();setShowForm(false)}})};
  const resetFilter=()=>{setQ('');setStatus('');router.get('/customers',{}, {preserveState:true,replace:true});};

  return <Layout><Head title="Pelanggan"/><div className="space-y-6">
    <PageHeader
      eyebrow="Subscriber Management"
      title="Data Pelanggan"
      description="Kelola pelanggan, kontak, alamat, dan layanan internet dari satu area kerja. Tombol perubahan hanya muncul untuk role yang memiliki izin kelola pelanggan."
      actions={<>
        {canManage&&<button onClick={()=>setShowForm(v=>!v)} className="jk-btn primary"><Plus size={17}/> Tambah Pelanggan</button>}
        {canExport&&<a href="/reports/export/customers" className="jk-btn secondary"><Download size={16}/> Export CSV</a>}
      </>}
    />

    <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <MetricCard icon={Users} label="Total Pelanggan" value={stats.customers.toLocaleString('id-ID')} hint="Semua pelanggan tenant"/>
      <MetricCard icon={UserCheck} label="Pelanggan Aktif" value={stats.active_customers.toLocaleString('id-ID')} hint="Customer berstatus aktif" tone="emerald"/>
      <MetricCard icon={Wifi} label="Total Layanan" value={stats.services.toLocaleString('id-ID')} hint="PPPoE / service terdaftar" tone="cyan"/>
      <MetricCard icon={WifiOff} label="Layanan Aktif" value={stats.active_services.toLocaleString('id-ID')} hint="Service aktif saat ini" tone="amber"/>
    </section>

    {canManage&&showForm&&<Surface title="Tambah Pelanggan Baru" subtitle="Nomor pelanggan dibuat otomatis. Data alamat dapat dilengkapi kembali dari halaman detail pelanggan.">
      <form onSubmit={submit} className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
        <Field label="Kode pos" error={form.errors.postal_code}><input className={field} value={form.data.postal_code} onChange={e=>form.setData('postal_code',e.target.value)} placeholder="Opsional"/></Field>
        <div className="md:col-span-2 xl:col-span-4"><Field label="Catatan internal" help="Tidak ditampilkan ke pelanggan." error={form.errors.notes}><textarea className={field} rows={2} value={form.data.notes} onChange={e=>form.setData('notes',e.target.value)} placeholder="Catatan teknis, patokan rumah, informasi collector, dll."/></Field></div>
        <div className="md:col-span-2 xl:col-span-4 flex justify-end gap-2"><button type="button" onClick={()=>setShowForm(false)} className="jk-btn secondary">Batal</button><button disabled={form.processing} className="jk-btn primary disabled:opacity-50">{form.processing?'Menyimpan...':'Simpan Pelanggan'}</button></div>
      </form>
    </Surface>}

    <Surface title="Daftar Pelanggan" subtitle={`Menampilkan ${customers.data.length} data pada halaman ${customers.current_page} dari ${customers.last_page}.`}>
      <form onSubmit={applyFilter} className="mb-5 grid gap-3 md:grid-cols-[1fr_220px_auto_auto]">
        <label className="relative"><Search size={17} className="absolute left-3 top-3 text-slate-400"/><input value={q} onChange={e=>setQ(e.target.value)} className="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm outline-none focus:border-[var(--jk-primary)]" placeholder="Cari nama, nomor pelanggan, HP atau email..."/></label>
        <select value={status} onChange={e=>setStatus(e.target.value)} className="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
        <button className="jk-btn secondary">Terapkan</button>
        <button type="button" onClick={resetFilter} className="jk-btn secondary">Reset</button>
      </form>

      {customers.data.length===0?<EmptyState text="Tidak ada pelanggan yang sesuai filter."/>:<div className="overflow-x-auto rounded-xl border border-slate-200">
        <table className="min-w-[900px] w-full text-sm">
          <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">Pelanggan</th><th className="px-5 py-3">Kontak</th><th className="px-5 py-3">Jenis</th><th className="px-5 py-3">Layanan</th><th className="px-5 py-3">Status</th><th className="px-5 py-3 text-right">Aksi</th></tr></thead>
          <tbody>{customers.data.map(c=><tr key={c.id} className="border-t border-slate-100 hover:bg-slate-50/80"><td className="px-5 py-4"><div className="font-bold text-slate-900">{c.name}</div><div className="mt-0.5 text-xs font-semibold text-[var(--jk-primary)]">{c.customer_number}</div></td><td className="px-5 py-4"><div className="text-slate-700">{c.phone||'-'}</div><div className="mt-0.5 text-xs text-slate-400">{c.email||'Email belum diisi'}</div></td><td className="px-5 py-4"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{c.customer_type==='business'?'Usaha':'Rumah'}</span></td><td className="px-5 py-4"><span className="font-bold text-slate-800">{c.services_count}</span><span className="ml-1 text-xs text-slate-400">layanan</span></td><td className="px-5 py-4"><Status value={c.status}/></td><td className="px-5 py-4 text-right"><Link href={`/customers/${c.id}`} className="jk-btn secondary !px-3 !py-2 text-xs">Buka Detail</Link></td></tr>)}</tbody>
        </table>
      </div>}

      <div className="mt-4 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between"><span className="text-slate-500">Total {customers.total.toLocaleString('id-ID')} pelanggan</span>{customers.last_page>1&&<div className="flex flex-wrap gap-1">{customers.links.map((l,i)=><button key={i} disabled={!l.url} onClick={()=>l.url&&router.visit(l.url,{preserveState:true})} dangerouslySetInnerHTML={{__html:l.label}} className={`min-w-9 rounded-lg border px-2.5 py-1.5 text-xs font-semibold ${l.active?'border-[var(--jk-primary)] bg-[var(--jk-primary)] text-white':'border-slate-200 bg-white text-slate-600'} disabled:opacity-40`}/>)}</div>}</div>
    </Surface>
  </div></Layout>;
}

function Field({label,help,error,required,children}:{label:string;help?:string;error?:string;required?:boolean;children:React.ReactNode}){return <label className="block"><span className="text-xs font-bold text-slate-700">{label}{required&&<span className="ml-1 text-rose-500">*</span>}</span>{children}{help&&<span className="mt-1 block text-[11px] leading-4 text-slate-400">{help}</span>}{error&&<span className="mt-1 block text-[11px] font-semibold text-rose-600">{error}</span>}</label>}
function Status({value}:{value:string}){const active=value==='active';return <span className={`inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold ${active?'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200':'bg-slate-100 text-slate-600 ring-1 ring-slate-200'}`}>{active?'Aktif':'Nonaktif'}</span>}
