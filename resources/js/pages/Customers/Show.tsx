import React from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import Layout from '../../components/Layout';

const input='w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-sky-500';
const btn='rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold hover:bg-sky-500 disabled:opacity-50';

type Plan={id:number,name:string,code:string,price:number,download_kbps:number,upload_kbps:number};
type RouterRow={id:number,name:string,host:string,status:string};
type Nas={id:number,router_id?:number,shortname:string,nasname:string};
type Pool={id:number,name:string,start_ip:string,end_ip:string};
type OnlineSession={radacctid:number,acctsessionid:string,framedipaddress?:string,nasipaddress?:string,acctstarttime?:string,acctupdatetime?:string,acctsessiontime?:number,acctinputoctets?:number,acctoutputoctets?:number,callingstationid?:string};
type Service={id:number,service_number:string,service_type:string,pppoe_username:string,status:string,billing_day:number,due_day:number,static_ip?:string,installed_at?:string,last_radius_sync_at?:string,last_coa_at?:string,last_disconnect_at?:string,notes?:string,internet_plan_id:number,router_id?:number,network_nas_id?:number,ip_pool_id?:number,plan?:Plan,router?:RouterRow,nas?:Nas,ip_pool?:Pool,status_histories?:any[],accounting_sessions?:OnlineSession[]};

type Customer={id:number,customer_number:string,name:string,customer_type:string,identity_number?:string,email?:string,phone?:string,secondary_phone?:string,status:string,notes?:string,addresses:any[],contacts:any[],services:Service[],invoices?:any[],portal_account?:{id:number,email?:string,status:string,must_change_password:boolean,last_login_at?:string,portal_enabled_at?:string}};

export default function CustomerShow({customer,plans,routers,nas,pools,serviceStatuses}:{customer:Customer,plans:Plan[],routers:RouterRow[],nas:Nas[],pools:Pool[],serviceStatuses:string[]}){
  const edit=useForm({name:customer.name,customer_type:customer.customer_type,identity_number:customer.identity_number||'',email:customer.email||'',phone:customer.phone||'',secondary_phone:customer.secondary_phone||'',status:customer.status,notes:customer.notes||''});
  const address=useForm({label:'Instalasi',address_line:'',village:'',district:'',city:'',province:'',postal_code:'',latitude:'',longitude:'',is_primary:customer.addresses.length===0});
  const contact=useForm({label:'Utama',type:'whatsapp',value:'',is_primary:customer.contacts.length===0});
  const service=useForm({internet_plan_id:plans[0]?.id||'',router_id:'',network_nas_id:'',ip_pool_id:'',pppoe_username:'',pppoe_password:'',status:'pending_installation',billing_day:1,due_day:10,static_ip:'',notes:'',status_reason:''});
  const portal=useForm({email:customer.portal_account?.email||customer.email||'',password:''});
  const page:any=usePage().props;

  return <Layout>
    <Head title={`${customer.customer_number} - ${customer.name}`}/>
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div><Link href="/customers" className="text-xs text-sky-400 hover:text-sky-300">← Kembali ke Customers</Link><h2 className="text-2xl font-black mt-1">{customer.name}</h2><div className="flex items-center gap-2 mt-1"><span className="text-sm text-sky-400 font-semibold">{customer.customer_number}</span><Status value={customer.status}/><span className="text-xs uppercase text-slate-500">{customer.customer_type}</span></div></div>
        <button onClick={()=>{if(confirm('Hapus pelanggan ini? Pelanggan dengan layanan tidak dapat dihapus.'))router.delete(`/customers/${customer.id}`)}} className="rounded-lg bg-rose-950 border border-rose-900 px-3 py-2 text-xs text-rose-200">Hapus Pelanggan</button>
      </div>

      <section className="grid xl:grid-cols-3 gap-6">
        <Card title="Profil Pelanggan" className="xl:col-span-2">
          <form className="grid md:grid-cols-2 gap-3" onSubmit={e=>{e.preventDefault();edit.put(`/customers/${customer.id}`,{preserveScroll:true});}}>
            <input className={input} value={edit.data.name} onChange={e=>edit.setData('name',e.target.value)} placeholder="Nama"/>
            <select className={input} value={edit.data.customer_type} onChange={e=>edit.setData('customer_type',e.target.value)}><option value="residential">Residential</option><option value="business">Business</option></select>
            <input className={input} value={edit.data.identity_number} onChange={e=>edit.setData('identity_number',e.target.value)} placeholder="NIK / identitas"/>
            <input className={input} type="email" value={edit.data.email} onChange={e=>edit.setData('email',e.target.value)} placeholder="Email"/>
            <input className={input} value={edit.data.phone} onChange={e=>edit.setData('phone',e.target.value)} placeholder="No. HP"/>
            <input className={input} value={edit.data.secondary_phone} onChange={e=>edit.setData('secondary_phone',e.target.value)} placeholder="HP alternatif"/>
            <select className={input} value={edit.data.status} onChange={e=>edit.setData('status',e.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select>
            <textarea className={input} value={edit.data.notes} onChange={e=>edit.setData('notes',e.target.value)} placeholder="Catatan" rows={2}/>
            <button className={`${btn} md:col-span-2`} disabled={edit.processing}>Simpan Profil</button>
          </form>
        </Card>
        <Card title="Ringkasan">
          <div className="space-y-3 text-sm"><Row label="Nomor" value={customer.customer_number}/><Row label="Layanan" value={customer.services.length}/><Row label="Aktif" value={customer.services.filter(s=>s.status==='active').length}/><Row label="Alamat" value={customer.addresses.length}/><Row label="Kontak" value={customer.contacts.length}/></div>
        </Card>
      </section>

      <Card title="Customer Portal">
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="lg:col-span-2">
            {customer.portal_account?<div className="space-y-2 text-sm"><Row label="Status" value={customer.portal_account.status}/><Row label="Email login" value={customer.portal_account.email||'-'}/><Row label="Harus ganti password" value={customer.portal_account.must_change_password?'Ya':'Tidak'}/><Row label="Login terakhir" value={customer.portal_account.last_login_at?new Date(customer.portal_account.last_login_at).toLocaleString('id-ID'):'-'}/><div className="pt-2 text-xs text-slate-500">Portal: <span className="text-sky-400">/portal/{(page.tenant?.slug||'tenant')}/login</span></div></div>:<div className="text-sm text-slate-500">Portal pelanggan belum diaktifkan.</div>}
            {page.flash?.generated_portal_password&&<div className="mt-4 rounded-lg border border-amber-800 bg-amber-950/40 p-3"><div className="text-xs text-amber-300">Password sementara — tampil sekali</div><div className="mt-1 break-all font-mono font-bold text-amber-100">{page.flash.generated_portal_password}</div></div>}
          </div>
          <form className="space-y-2" onSubmit={e=>{e.preventDefault();portal.post(`/customers/${customer.id}/portal-account`,{preserveScroll:true,onSuccess:()=>portal.reset('password')});}}>
            <input className={input} type="email" value={portal.data.email} onChange={e=>portal.setData('email',e.target.value)} placeholder="Email portal"/>
            <input className={input} type="password" value={portal.data.password} onChange={e=>portal.setData('password',e.target.value)} placeholder="Password (kosong = generate)"/>
            <button className={`${btn} w-full`} disabled={portal.processing}>{customer.portal_account?'Aktifkan / Reset Akun':'Aktifkan Portal'}</button>
            {customer.portal_account&&<><button type="button" onClick={()=>router.post(`/customers/${customer.id}/portal-account/reset-password`,{}, {preserveScroll:true})} className="w-full rounded-lg bg-indigo-800 px-3 py-2 text-xs">Generate Password Baru</button><button type="button" onClick={()=>router.put(`/customers/${customer.id}/portal-account/status`,{status:customer.portal_account?.status==='active'?'disabled':'active'},{preserveScroll:true})} className="w-full rounded-lg bg-slate-800 px-3 py-2 text-xs">{customer.portal_account.status==='active'?'Nonaktifkan Portal':'Aktifkan Portal'}</button></>}
          </form>
        </div>
      </Card>

      <section className="grid xl:grid-cols-2 gap-6">
        <Card title="Alamat">
          <form className="space-y-3 mb-5" onSubmit={e=>{e.preventDefault();address.post(`/customers/${customer.id}/addresses`,{preserveScroll:true,onSuccess:()=>address.reset('address_line','village','district','city','province','postal_code','latitude','longitude')});}}>
            <div className="grid grid-cols-2 gap-3"><input className={input} value={address.data.label} onChange={e=>address.setData('label',e.target.value)} placeholder="Label"/><label className="flex items-center gap-2 text-sm px-1"><input type="checkbox" checked={address.data.is_primary} onChange={e=>address.setData('is_primary',e.target.checked)}/> Alamat utama</label></div>
            <textarea className={input} value={address.data.address_line} onChange={e=>address.setData('address_line',e.target.value)} placeholder="Alamat lengkap *" rows={3}/>
            <div className="grid grid-cols-2 gap-3"><input className={input} value={address.data.village} onChange={e=>address.setData('village',e.target.value)} placeholder="Kelurahan"/><input className={input} value={address.data.district} onChange={e=>address.setData('district',e.target.value)} placeholder="Kecamatan"/><input className={input} value={address.data.city} onChange={e=>address.setData('city',e.target.value)} placeholder="Kota/Kab."/><input className={input} value={address.data.province} onChange={e=>address.setData('province',e.target.value)} placeholder="Provinsi"/></div>
            <div className="grid grid-cols-3 gap-3"><input className={input} value={address.data.postal_code} onChange={e=>address.setData('postal_code',e.target.value)} placeholder="Kode pos"/><input className={input} value={address.data.latitude} onChange={e=>address.setData('latitude',e.target.value)} placeholder="Latitude"/><input className={input} value={address.data.longitude} onChange={e=>address.setData('longitude',e.target.value)} placeholder="Longitude"/></div>
            <button className={btn} disabled={address.processing}>Tambah Alamat</button>
          </form>
          <div className="space-y-2">{customer.addresses.map(a=><div key={a.id} className="rounded-lg border border-slate-800 p-3 flex justify-between gap-3"><div><div className="font-semibold">{a.label} {a.is_primary&&<span className="text-[10px] bg-sky-900 text-sky-200 rounded px-2 py-0.5">PRIMARY</span>}</div><div className="text-xs text-slate-400 mt-1">{a.address_line}</div><div className="text-xs text-slate-500">{[a.village,a.district,a.city,a.province,a.postal_code].filter(Boolean).join(', ')}</div></div><button onClick={()=>confirm('Hapus alamat?')&&router.delete(`/customers/${customer.id}/addresses/${a.id}`,{preserveScroll:true})} className="text-xs text-rose-300 h-fit">Hapus</button></div>)}</div>
        </Card>

        <Card title="Kontak Tambahan">
          <form className="grid grid-cols-2 gap-3 mb-5" onSubmit={e=>{e.preventDefault();contact.post(`/customers/${customer.id}/contacts`,{preserveScroll:true,onSuccess:()=>contact.reset('value')});}}>
            <input className={input} value={contact.data.label} onChange={e=>contact.setData('label',e.target.value)} placeholder="Label"/>
            <select className={input} value={contact.data.type} onChange={e=>contact.setData('type',e.target.value)}><option value="whatsapp">WhatsApp</option><option value="phone">Phone</option><option value="email">Email</option><option value="other">Other</option></select>
            <input className={`${input} col-span-2`} value={contact.data.value} onChange={e=>contact.setData('value',e.target.value)} placeholder="Nomor / email *"/>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={contact.data.is_primary} onChange={e=>contact.setData('is_primary',e.target.checked)}/> Kontak utama</label>
            <button className={btn} disabled={contact.processing}>Tambah Kontak</button>
          </form>
          <div className="space-y-2">{customer.contacts.map(c=><div key={c.id} className="rounded-lg border border-slate-800 p-3 flex justify-between"><div><div className="font-semibold">{c.label} {c.is_primary&&<span className="text-[10px] bg-sky-900 text-sky-200 rounded px-2 py-0.5">PRIMARY</span>}</div><div className="text-xs text-slate-400">{c.type} · {c.value}</div></div><button onClick={()=>confirm('Hapus kontak?')&&router.delete(`/customers/${customer.id}/contacts/${c.id}`,{preserveScroll:true})} className="text-xs text-rose-300">Hapus</button></div>)}</div>
        </Card>
      </section>

      <Card title="Tambah Layanan Internet / PPPoE">
        <form className="grid md:grid-cols-2 xl:grid-cols-4 gap-3" onSubmit={e=>{e.preventDefault();service.post(`/customers/${customer.id}/services`,{preserveScroll:true,onSuccess:()=>service.reset('pppoe_username','pppoe_password','static_ip','notes')});}}>
          <select className={input} value={service.data.internet_plan_id} onChange={e=>service.setData('internet_plan_id',Number(e.target.value))}><option value="">Pilih paket *</option>{plans.map(p=><option key={p.id} value={p.id}>{p.name} · Rp {Number(p.price).toLocaleString('id-ID')}</option>)}</select>
          <select className={input} value={service.data.router_id} onChange={e=>service.setData('router_id',e.target.value)}><option value="">Router belum ditentukan</option>{routers.map(r=><option key={r.id} value={r.id}>{r.name} ({r.host})</option>)}</select>
          <select className={input} value={service.data.network_nas_id} onChange={e=>service.setData('network_nas_id',e.target.value)}><option value="">NAS belum ditentukan</option>{nas.map(n=><option key={n.id} value={n.id}>{n.shortname} · {n.nasname}</option>)}</select>
          <select className={input} value={service.data.ip_pool_id} onChange={e=>service.setData('ip_pool_id',e.target.value)}><option value="">Tanpa IP Pool</option>{pools.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}</select>
          <input className={input} value={service.data.pppoe_username} onChange={e=>service.setData('pppoe_username',e.target.value)} placeholder="PPPoE username *"/>
          <input className={input} type="password" value={service.data.pppoe_password} onChange={e=>service.setData('pppoe_password',e.target.value)} placeholder="PPPoE password *"/>
          <select className={input} value={service.data.status} onChange={e=>service.setData('status',e.target.value)}>{serviceStatuses.map(s=><option key={s} value={s}>{s}</option>)}</select>
          <input className={input} value={service.data.static_ip} onChange={e=>service.setData('static_ip',e.target.value)} placeholder="Static IP (opsional)"/>
          <input className={input} type="number" min={1} max={28} value={service.data.billing_day} onChange={e=>service.setData('billing_day',Number(e.target.value))} placeholder="Billing day"/>
          <input className={input} type="number" min={1} max={28} value={service.data.due_day} onChange={e=>service.setData('due_day',Number(e.target.value))} placeholder="Due day"/>
          <textarea className={`${input} xl:col-span-2`} value={service.data.notes} onChange={e=>service.setData('notes',e.target.value)} placeholder="Catatan layanan" rows={2}/>
          {Object.keys(service.errors).length>0&&<div className="xl:col-span-4 rounded-lg bg-rose-950/60 border border-rose-900 p-3 text-xs text-rose-200">{Object.values(service.errors).join(' · ')}</div>}
          <button className={`${btn} xl:col-span-4`} disabled={service.processing}>Buat Layanan & Sync RADIUS</button>
        </form>
      </Card>

      {customer.invoices&&customer.invoices.length>0&&<Card title="Invoice Terbaru"><div className="space-y-2">{customer.invoices.map((inv:any)=><Link key={inv.id} href={`/billing/invoices/${inv.id}`} className="flex flex-col md:flex-row md:items-center justify-between gap-2 rounded-lg border border-slate-800 p-3 hover:border-sky-800"><div><div className="font-semibold text-sky-300">{inv.invoice_number}</div><div className="text-xs text-slate-500">{inv.service?.service_number||'-'} · {inv.service?.pppoe_username||'-'} · due {new Date(inv.due_at+'T00:00:00').toLocaleDateString('id-ID')}</div></div><div className="text-right"><div className="font-bold">Rp{Number(inv.balance_due||0).toLocaleString('id-ID')}</div><div className="text-[10px] uppercase text-slate-500">{inv.status}</div></div></Link>)}</div><Link href="/billing" className="inline-block mt-3 text-xs text-sky-400">Buka Billing →</Link></Card>}

      <section className="space-y-4">
        <h3 className="text-xl font-black">Layanan Pelanggan</h3>
        {customer.services.map(s=><ServiceCard key={s.id} customerId={customer.id} service={s} plans={plans} routers={routers} nas={nas} pools={pools} statuses={serviceStatuses}/>) }
        {customer.services.length===0&&<div className="rounded-xl border border-dashed border-slate-700 p-8 text-center text-slate-500">Belum ada layanan internet.</div>}
      </section>
    </div>
  </Layout>;
}

function ServiceCard({customerId,service,plans,routers,nas,pools,statuses}:{customerId:number,service:Service,plans:Plan[],routers:RouterRow[],nas:Nas[],pools:Pool[],statuses:string[]}){
  const form=useForm({internet_plan_id:service.internet_plan_id,router_id:service.router_id||'',network_nas_id:service.network_nas_id||'',ip_pool_id:service.ip_pool_id||'',pppoe_username:service.pppoe_username,pppoe_password:'',status:service.status,billing_day:service.billing_day,due_day:service.due_day,static_ip:service.static_ip||'',notes:service.notes||'',status_reason:''});
  return <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
    <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-4">
      <div><div className="flex items-center flex-wrap gap-2"><h4 className="font-bold text-lg">{service.service_number}</h4><Status value={service.status}/><span className="rounded bg-slate-800 px-2 py-1 text-[10px] uppercase">{service.service_type}</span></div><div className="text-sm text-sky-400 mt-1">{service.pppoe_username}</div><div className="text-xs text-slate-500 mt-1">{service.plan?.name||'-'} · {service.router?.name||'router -'} · {service.nas?.shortname||'NAS -'} · Pool {service.ip_pool?.name||'-'}</div><div className="text-xs text-slate-500">Last RADIUS sync: {service.last_radius_sync_at?new Date(service.last_radius_sync_at).toLocaleString('id-ID'):'belum'}</div></div>
      <div className="flex gap-2"><button onClick={()=>router.post(`/customers/${customerId}/services/${service.id}/radius-sync`,{}, {preserveScroll:true})} className="rounded bg-emerald-800 px-3 py-2 text-xs">Sync RADIUS</button><button onClick={()=>confirm('Terminasi dan hapus layanan ini?')&&router.delete(`/customers/${customerId}/services/${service.id}`,{preserveScroll:true})} className="rounded bg-rose-950 border border-rose-900 px-3 py-2 text-xs text-rose-200">Terminasi</button></div>
    </div>
    {service.accounting_sessions&&service.accounting_sessions.length>0&&<div className="mb-4 rounded-lg border border-emerald-900 bg-emerald-950/20 p-3"><div className="text-xs font-bold text-emerald-300 mb-2">ONLINE SESSION ({service.accounting_sessions.length})</div><div className="space-y-2">{service.accounting_sessions.map(a=><div key={a.radacctid} className="flex flex-col lg:flex-row lg:items-center justify-between gap-2 rounded bg-slate-950/70 p-2"><div className="text-xs"><span className="text-sky-300">{a.framedipaddress||'-'}</span> · NAS {a.nasipaddress||'-'} · MAC {a.callingstationid||'-'}<div className="text-slate-500">Session {a.acctsessionid} · start {a.acctstarttime?new Date(a.acctstarttime).toLocaleString('id-ID'):'-'}</div></div><div className="flex gap-2"><button onClick={()=>router.post(`/network/sessions/${a.radacctid}/coa`,{}, {preserveScroll:true})} className="rounded bg-indigo-800 px-3 py-1 text-[11px]">CoA Paket</button><button onClick={()=>confirm('Disconnect session ini?')&&router.post(`/network/sessions/${a.radacctid}/disconnect`,{}, {preserveScroll:true})} className="rounded bg-rose-900 px-3 py-1 text-[11px]">Disconnect</button></div></div>)}</div></div>}
    <form className="grid md:grid-cols-2 xl:grid-cols-4 gap-3" onSubmit={e=>{e.preventDefault();form.put(`/customers/${customerId}/services/${service.id}`,{preserveScroll:true,onSuccess:()=>form.reset('pppoe_password','status_reason')});}}>
      <select className={input} value={form.data.internet_plan_id} onChange={e=>form.setData('internet_plan_id',Number(e.target.value))}>{plans.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}</select>
      <select className={input} value={form.data.router_id} onChange={e=>form.setData('router_id',e.target.value)}><option value="">Router -</option>{routers.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select>
      <select className={input} value={form.data.network_nas_id} onChange={e=>form.setData('network_nas_id',e.target.value)}><option value="">NAS -</option>{nas.map(n=><option key={n.id} value={n.id}>{n.shortname}</option>)}</select>
      <select className={input} value={form.data.ip_pool_id} onChange={e=>form.setData('ip_pool_id',e.target.value)}><option value="">Pool -</option>{pools.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}</select>
      <input className={input} value={form.data.pppoe_username} onChange={e=>form.setData('pppoe_username',e.target.value)} placeholder="PPPoE username"/>
      <input className={input} type="password" value={form.data.pppoe_password} onChange={e=>form.setData('pppoe_password',e.target.value)} placeholder="Password baru (kosong = tetap)"/>
      <select className={input} value={form.data.status} onChange={e=>form.setData('status',e.target.value)}>{statuses.map(s=><option key={s} value={s}>{s}</option>)}</select>
      <input className={input} value={form.data.static_ip} onChange={e=>form.setData('static_ip',e.target.value)} placeholder="Static IP"/>
      <input className={input} type="number" min={1} max={28} value={form.data.billing_day} onChange={e=>form.setData('billing_day',Number(e.target.value))}/>
      <input className={input} type="number" min={1} max={28} value={form.data.due_day} onChange={e=>form.setData('due_day',Number(e.target.value))}/>
      <input className={`${input} xl:col-span-2`} value={form.data.status_reason} onChange={e=>form.setData('status_reason',e.target.value)} placeholder="Alasan perubahan status (opsional)"/>
      <textarea className={`${input} xl:col-span-3`} value={form.data.notes} onChange={e=>form.setData('notes',e.target.value)} placeholder="Catatan" rows={2}/>
      <button className={btn} disabled={form.processing}>Simpan & Sync</button>
    </form>
    {service.status_histories&&service.status_histories.length>0&&<details className="mt-4"><summary className="cursor-pointer text-xs text-slate-400">Riwayat status ({service.status_histories.length})</summary><div className="mt-2 space-y-1">{service.status_histories.map(h=><div key={h.id} className="text-xs text-slate-500 border-l border-slate-700 pl-3"><span className="text-slate-300">{h.from_status||'NEW'} → {h.to_status}</span> · {h.reason||'-'} · {h.actor?.name||'system'} · {new Date(h.created_at).toLocaleString('id-ID')}</div>)}</div></details>}
  </div>
}
function Card({title,children,className=''}:{title:string,children:React.ReactNode,className?:string}){return <section className={`rounded-xl border border-slate-800 bg-slate-900/50 p-5 ${className}`}><h3 className="text-lg font-bold mb-4">{title}</h3>{children}</section>}
function Row({label,value}:{label:string,value:any}){return <div className="flex justify-between border-b border-slate-800 pb-2"><span className="text-slate-500">{label}</span><span className="font-semibold">{String(value)}</span></div>}
function Status({value}:{value:string}){const cls=value==='active'?'bg-emerald-900 text-emerald-200':value==='suspended'?'bg-amber-900 text-amber-200':value==='terminated'?'bg-rose-950 text-rose-200':value==='pending_installation'?'bg-sky-950 text-sky-200':'bg-slate-800 text-slate-300';return <span className={`rounded px-2 py-1 text-[10px] uppercase ${cls}`}>{value}</span>}
