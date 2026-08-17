import React from 'react';
import {useForm,usePage} from '@inertiajs/react';
import Layout from '../../components/Layout';
import {Boxes,MapPin,UserCog,UsersRound} from 'lucide-react';
import {MetricCard,PageHeader,Surface} from '../../components/Ui';
import {useAccess} from '../../hooks/useAccess';

const input='w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';
export default function Index({accounts,locations,technicians}:any){
 const p:any=usePage().props;
 const {can}=useAccess();
 const canManage=can('inventory.manage');
 const location=useForm({code:'',name:'',location_type:'warehouse',technician_id:'',address:''});
 const account=useForm({name:'',email:'',role:'warehouse_staff',inventory_location_id:'',technician_id:''});
 const activeAccounts=accounts.filter((a:any)=>a.status==='active').length;
 return <Layout><div className="space-y-6">
  <PageHeader eyebrow="Inventory" title="Inventory Portal Management" description={`${p.tenant?.name||'Tenant'} · kelola lokasi stok dan akun akses warehouse/teknisi.`}/>
  <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <MetricCard icon={MapPin} label="Lokasi Stok" value={locations.length} tone="blue"/>
    <MetricCard icon={UsersRound} label="Akun Inventory" value={accounts.length} tone="cyan"/>
    <MetricCard icon={UserCog} label="Akun Aktif" value={activeAccounts} tone="emerald"/>
    <MetricCard icon={Boxes} label="Teknisi Terhubung" value={technicians.length} tone="violet"/>
  </section>
  {canManage&&<div className="grid gap-5 lg:grid-cols-2">
    <Surface title="Tambah Lokasi" subtitle="Warehouse, stok teknisi, transit atau repair."><form className="space-y-3" onSubmit={e=>{e.preventDefault();location.post('/inventory-management/locations',{preserveScroll:true})}}>
      <input className={input} placeholder="Kode (opsional)" value={location.data.code} onChange={e=>location.setData('code',e.target.value)}/><input className={input} placeholder="Nama gudang/lokasi" value={location.data.name} onChange={e=>location.setData('name',e.target.value)}/><select className={input} value={location.data.location_type} onChange={e=>location.setData('location_type',e.target.value)}><option value="warehouse">Warehouse</option><option value="technician">Stok Teknisi</option><option value="transit">Transit</option><option value="repair">Repair</option></select>{location.data.location_type==='technician'&&<select className={input} value={location.data.technician_id} onChange={e=>location.setData('technician_id',e.target.value)}><option value="">Pilih teknisi</option>{technicians.map((t:any)=><option key={t.id} value={t.id}>{t.code} · {t.name}</option>)}</select>}<input className={input} placeholder="Alamat" value={location.data.address} onChange={e=>location.setData('address',e.target.value)}/><button disabled={location.processing} className="rounded-xl bg-[var(--jk-primary)] px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">Simpan Lokasi</button>
    </form></Surface>
    <Surface title="Tambah Akun Portal" subtitle="Buat akses khusus warehouse, teknisi atau auditor."><form className="space-y-3" onSubmit={e=>{e.preventDefault();account.post('/inventory-management/accounts',{preserveScroll:true})}}>
      <input className={input} placeholder="Nama" value={account.data.name} onChange={e=>account.setData('name',e.target.value)}/><input className={input} type="email" placeholder="Email" value={account.data.email} onChange={e=>account.setData('email',e.target.value)}/><select className={input} value={account.data.role} onChange={e=>account.setData('role',e.target.value)}><option value="warehouse_manager">Warehouse Manager</option><option value="warehouse_staff">Warehouse Staff</option><option value="technician">Technician</option><option value="auditor">Auditor</option></select><select className={input} value={account.data.inventory_location_id} onChange={e=>account.setData('inventory_location_id',e.target.value)}><option value="">Semua / tanpa default lokasi</option>{locations.map((x:any)=><option key={x.id} value={x.id}>{x.code} · {x.name}</option>)}</select><select className={input} value={account.data.technician_id} onChange={e=>account.setData('technician_id',e.target.value)}><option value="">Tidak terhubung teknisi</option>{technicians.map((t:any)=><option key={t.id} value={t.id}>{t.code} · {t.name}</option>)}</select><button disabled={account.processing} className="rounded-xl bg-[var(--jk-primary)] px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">Buat Akun</button>
    </form></Surface>
  </div>}
  <Surface title="Akun Inventory" subtitle="Hak akses portal operasional inventory per lokasi."><div className="overflow-x-auto"><table className="w-full min-w-[680px] text-sm"><thead><tr className="border-y border-slate-100 bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-400"><th className="px-3 py-3">Nama</th><th>Email</th><th>Role</th><th>Lokasi</th><th>Status</th></tr></thead><tbody>{accounts.map((a:any)=><tr key={a.id} className="border-b border-slate-100"><td className="px-3 py-3 font-semibold">{a.name}</td><td>{a.email}</td><td className="capitalize">{String(a.role).replaceAll('_',' ')}</td><td>{a.location?.name||'Semua lokasi'}</td><td><span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${a.status==='active'?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-600'}`}>{a.status}</span></td></tr>)}</tbody></table>{!accounts.length&&<div className="py-10 text-center text-sm text-slate-400">Belum ada akun inventory.</div>}</div></Surface>
 </div></Layout>;
}
