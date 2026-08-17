import React from 'react';
import {Head, Link, router as inertiaRouter, useForm, usePage} from '@inertiajs/react';
import {
  Activity,
  Cable,
  CircleGauge,
  Database,
  Network,
  Router as RouterIcon,
  ServerCog,
  ShieldCheck,
  Wifi,
} from 'lucide-react';
import Layout from '../../components/Layout';
import {EmptyState, MetricCard, PageHeader, Surface} from '../../components/Ui';
import {useAccess} from '../../hooks/useAccess';

type RouterRow = {id:number;name:string;host:string;rest_port:number;api_username:string;verify_tls:boolean;status:string;routeros_version?:string;board_name?:string;last_seen_at?:string;last_error?:string};
type NasRow = {id:number;router_id?:number;nasname:string;shortname:string;type:string;coa_port:number;active:boolean;description?:string;router?:{id:number;name:string}};
type PlanRow = {id:number;name:string;code:string;price:number;download_kbps:number;upload_kbps:number;acct_interim_interval:number;active:boolean;radius_attributes?:Record<string,string>};
type PoolRow = {id:number;name:string;start_ip:string;end_ip:string;gateway?:string;active:boolean};

const input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-50';
const primaryBtn = 'rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const secondaryBtn = 'rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-blue-300 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-40';
const dangerBtn = 'rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100';

export default function NetworkIndex({routers,nas,plans,pools,radius}:{routers:RouterRow[];nas:NasRow[];plans:PlanRow[];pools:PoolRow[];radius:any}) {
  const page:any = usePage().props;
  const {can} = useAccess();
  const canManage = can('network.manage');

  const routerForm = useForm({name:'',host:'',rest_port:443,api_username:'api-jaringanku',api_password:'',verify_tls:false});
  const nasForm = useForm({router_id:'',nasname:'',shortname:'',type:'mikrotik',secret:'',coa_port:3799,description:''});
  const planForm = useForm({name:'',code:'',price:250000,download_kbps:20000,upload_kbps:10000,acct_interim_interval:300});
  const poolForm = useForm({name:'',start_ip:'10.10.10.2',end_ip:'10.10.10.254',gateway:'10.10.10.1'});
  const radiusForm = useForm({username:'phase2-test',password:''});

  const onlineRouters = routers.filter(r=>r.status==='online').length;
  const activeNas = nas.filter(n=>n.active).length;
  const activePlans = plans.filter(p=>p.active).length;

  const del = (url:string) => {
    if (!canManage) return;
    if (confirm('Hapus data ini? Tindakan ini tidak dapat dibatalkan.')) inertiaRouter.delete(url,{preserveScroll:true});
  };

  return <Layout>
    <Head title="Network Core"/>
    <div className="space-y-6">
      <PageHeader
        eyebrow="Network Operations"
        title="Network, RADIUS & MikroTik"
        description="Kelola router, NAS, profil internet, IP pool, dan validasi autentikasi RADIUS dari satu pusat operasi."
        actions={<Link href="/network/sessions" className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700"><Activity size={16}/> Online Sessions</Link>}
      />

      <section className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <MetricCard icon={RouterIcon} label="Router Online" value={`${onlineRouters}/${routers.length}`} hint={routers.length-onlineRouters>0?`${routers.length-onlineRouters} perlu perhatian`:'Semua router terhubung'} tone={onlineRouters===routers.length?'emerald':'amber'}/>
        <MetricCard icon={ServerCog} label="NAS Aktif" value={`${activeNas}/${nas.length}`} hint="RADIUS clients tenant" tone="cyan"/>
        <MetricCard icon={ShieldCheck} label="RADIUS SQL" value={radius.test_ready?'Ready':'Not Ready'} hint="Authentication readiness" tone={radius.test_ready?'emerald':'rose'}/>
        <MetricCard icon={CircleGauge} label="Internet Plan" value={activePlans} hint={`${plans.length} total profil`} tone="violet"/>
      </section>

      <Surface title="FreeRADIUS Authentication" subtitle="Status dan uji Access-Request ke FreeRADIUS. Uji autentikasi hanya tersedia untuk user dengan hak kelola network.">
        <div className="mb-4 flex flex-wrap items-center gap-2 text-xs">
          <span className={`rounded-full px-2.5 py-1 font-semibold ${radius.test_ready?'bg-emerald-50 text-emerald-700':'bg-rose-50 text-rose-700'}`}>{radius.test_ready?'SQL test account ready':'SQL test account belum siap'}</span>
          <span className="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">UDP 1812 / 1813</span>
        </div>
        {canManage ? <form className="grid gap-3 md:grid-cols-3" onSubmit={e=>{e.preventDefault();radiusForm.post('/network/radius/test',{preserveScroll:true});}}>
          <input className={input} value={radiusForm.data.username} onChange={e=>radiusForm.setData('username',e.target.value)} placeholder="Username RADIUS"/>
          <input className={input} type="password" value={radiusForm.data.password} onChange={e=>radiusForm.setData('password',e.target.value)} placeholder="Password test (tidak disimpan)" autoComplete="off"/>
          <button className={primaryBtn} disabled={radiusForm.processing||!radiusForm.data.password}>Test Access-Request</button>
        </form> : <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Mode read-only. Anda dapat memantau status RADIUS, tetapi tidak dapat mengirim Access-Request.</div>}
        {page.flash?.radius_test?.output&&<pre className="mt-4 max-h-52 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-200 whitespace-pre-wrap">{page.flash.radius_test.output}</pre>}
      </Surface>

      <section className="grid gap-6 xl:grid-cols-2">
        <Surface title="Routers / MikroTik REST" subtitle="Inventaris dan health status RouterOS yang terhubung ke tenant.">
          {canManage&&<form className="mb-5 grid gap-3 md:grid-cols-2" onSubmit={e=>{e.preventDefault();routerForm.post('/network/routers',{preserveScroll:true,onSuccess:()=>routerForm.reset('name','host','api_password')});}}>
            <input className={input} value={routerForm.data.name} onChange={e=>routerForm.setData('name',e.target.value)} placeholder="Nama router"/>
            <input className={input} value={routerForm.data.host} onChange={e=>routerForm.setData('host',e.target.value)} placeholder="IP / hostname"/>
            <input className={input} type="number" value={routerForm.data.rest_port} onChange={e=>routerForm.setData('rest_port',Number(e.target.value))} placeholder="REST port"/>
            <input className={input} value={routerForm.data.api_username} onChange={e=>routerForm.setData('api_username',e.target.value)} placeholder="Username RouterOS"/>
            <input className={input} type="password" value={routerForm.data.api_password} onChange={e=>routerForm.setData('api_password',e.target.value)} placeholder="Password RouterOS" autoComplete="new-password"/>
            <label className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600"><input type="checkbox" checked={routerForm.data.verify_tls} onChange={e=>routerForm.setData('verify_tls',e.target.checked)}/> Verify TLS certificate</label>
            <button className={`${primaryBtn} md:col-span-2`} disabled={routerForm.processing}>Tambah Router</button>
          </form>}
          <div className="space-y-3">{routers.map(r=><div key={r.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2"><span className="font-bold text-slate-800">{r.name}</span><Status value={r.status}/></div>
                <div className="mt-1 text-xs text-slate-500">{r.host}:{r.rest_port} · {r.board_name||'board unknown'} · RouterOS {r.routeros_version||'-'}</div>
                <div className="mt-1 text-[11px] text-slate-400">Last seen: {formatDate(r.last_seen_at)}</div>
                {r.last_error&&<div className="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{r.last_error}</div>}
              </div>
              {canManage&&<div className="flex shrink-0 gap-2"><button onClick={()=>inertiaRouter.post(`/network/routers/${r.id}/test`,{}, {preserveScroll:true})} className={secondaryBtn}>Test</button><button onClick={()=>del(`/network/routers/${r.id}`)} className={dangerBtn}>Hapus</button></div>}
            </div>
          </div>)}{routers.length===0&&<EmptyState text="Belum ada router MikroTik yang didaftarkan."/>}</div>
        </Surface>

        <Surface title="NAS / RADIUS Clients" subtitle="Daftar NAS yang digunakan untuk authentication, accounting, dan CoA.">
          {canManage&&<form className="mb-5 grid gap-3 md:grid-cols-2" onSubmit={e=>{e.preventDefault();nasForm.post('/network/nas',{preserveScroll:true,onSuccess:()=>nasForm.reset('nasname','shortname','secret','description')});}}>
            <select className={input} value={nasForm.data.router_id} onChange={e=>nasForm.setData('router_id',e.target.value)}><option value="">Tanpa link router</option>{routers.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select>
            <input className={input} value={nasForm.data.nasname} onChange={e=>nasForm.setData('nasname',e.target.value)} placeholder="NAS IP, contoh 192.168.1.1"/>
            <input className={input} value={nasForm.data.shortname} onChange={e=>nasForm.setData('shortname',e.target.value)} placeholder="Shortname"/>
            <input className={input} type="password" value={nasForm.data.secret} onChange={e=>nasForm.setData('secret',e.target.value)} placeholder="RADIUS shared secret" autoComplete="new-password"/>
            <input className={input} type="number" value={nasForm.data.coa_port} onChange={e=>nasForm.setData('coa_port',Number(e.target.value))} placeholder="CoA port"/>
            <input className={input} value={nasForm.data.description} onChange={e=>nasForm.setData('description',e.target.value)} placeholder="Deskripsi"/>
            <button className={`${primaryBtn} md:col-span-2`} disabled={nasForm.processing}>Tambah & Sync NAS</button>
          </form>}
          <div className="space-y-3">{nas.map(n=><div key={n.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><div className="flex items-center gap-2 font-bold text-slate-800"><Database size={15} className="text-slate-400"/>{n.shortname}<span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${n.active?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-500'}`}>{n.active?'ACTIVE':'INACTIVE'}</span></div><div className="mt-1 text-xs text-slate-500">{n.nasname} · {n.type} · CoA {n.coa_port} · {n.router?.name||'no router'}</div></div>{canManage&&<div className="flex gap-2"><button onClick={()=>inertiaRouter.post(`/network/nas/${n.id}/sync`,{}, {preserveScroll:true})} className={secondaryBtn}>Sync</button><button onClick={()=>del(`/network/nas/${n.id}`)} className={dangerBtn}>Hapus</button></div>}</div>
          </div>)}{nas.length===0&&<EmptyState text="Belum ada NAS/RADIUS client untuk tenant ini."/>}</div>
          <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800">FreeRADIUS SQL clients dibaca saat service startup. Setelah NAS baru disinkronkan, restart service RADIUS sesuai prosedur deployment. MikroTik CoA/Disconnect Jaringanku menggunakan port 3799.</div>
        </Surface>
      </section>

      <section className="grid gap-6 xl:grid-cols-2">
        <Surface title="Internet Plans" subtitle="Profil layanan dan parameter bandwidth yang diterjemahkan ke atribut RADIUS.">
          {canManage&&<form className="mb-5 grid gap-3 md:grid-cols-2" onSubmit={e=>{e.preventDefault();planForm.post('/network/plans',{preserveScroll:true,onSuccess:()=>planForm.reset('name','code')});}}>
            <input className={input} value={planForm.data.name} onChange={e=>planForm.setData('name',e.target.value)} placeholder="Nama paket"/>
            <input className={input} value={planForm.data.code} onChange={e=>planForm.setData('code',e.target.value)} placeholder="Kode, contoh HOME20"/>
            <input className={input} type="number" value={planForm.data.price} onChange={e=>planForm.setData('price',Number(e.target.value))} placeholder="Harga"/>
            <input className={input} type="number" value={planForm.data.download_kbps} onChange={e=>planForm.setData('download_kbps',Number(e.target.value))} placeholder="Download kbps"/>
            <input className={input} type="number" value={planForm.data.upload_kbps} onChange={e=>planForm.setData('upload_kbps',Number(e.target.value))} placeholder="Upload kbps"/>
            <input className={input} type="number" min={60} max={3600} value={planForm.data.acct_interim_interval} onChange={e=>planForm.setData('acct_interim_interval',Number(e.target.value))} placeholder="Interim accounting (detik)"/>
            <button className={primaryBtn} disabled={planForm.processing}>Tambah Paket</button>
          </form>}
          <div className="space-y-3">{plans.map(p=><div key={p.id} className="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row"><div><div className="flex flex-wrap items-center gap-2"><span className="font-bold text-slate-800">{p.name}</span><span className="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">{p.code}</span></div><div className="mt-1 text-xs text-slate-500">{p.upload_kbps.toLocaleString('id-ID')} kbps ↑ / {p.download_kbps.toLocaleString('id-ID')} kbps ↓ · {rupiah(p.price)} · interim {p.acct_interim_interval}s</div></div>{canManage&&<button onClick={()=>del(`/network/plans/${p.id}`)} className={`${dangerBtn} h-fit`}>Hapus</button>}</div>)}{plans.length===0&&<EmptyState text="Belum ada internet plan."/>}</div>
        </Surface>

        <Surface title="IP Pools" subtitle="Pool alamat IP untuk provisioning layanan pelanggan.">
          {canManage&&<form className="mb-5 grid gap-3 md:grid-cols-2" onSubmit={e=>{e.preventDefault();poolForm.post('/network/pools',{preserveScroll:true,onSuccess:()=>poolForm.reset('name')});}}>
            <input className={input} value={poolForm.data.name} onChange={e=>poolForm.setData('name',e.target.value)} placeholder="Nama pool"/>
            <input className={input} value={poolForm.data.gateway} onChange={e=>poolForm.setData('gateway',e.target.value)} placeholder="Gateway"/>
            <input className={input} value={poolForm.data.start_ip} onChange={e=>poolForm.setData('start_ip',e.target.value)} placeholder="Start IP"/>
            <input className={input} value={poolForm.data.end_ip} onChange={e=>poolForm.setData('end_ip',e.target.value)} placeholder="End IP"/>
            <button className={`${primaryBtn} md:col-span-2`} disabled={poolForm.processing}>Tambah Pool</button>
          </form>}
          <div className="space-y-3">{pools.map(p=><div key={p.id} className="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row"><div><div className="flex items-center gap-2 font-bold text-slate-800"><Cable size={15} className="text-slate-400"/>{p.name}</div><div className="mt-1 font-mono text-xs text-slate-500">{p.start_ip} — {p.end_ip} · GW {p.gateway||'-'}</div></div>{canManage&&<button onClick={()=>del(`/network/pools/${p.id}`)} className={`${dangerBtn} h-fit`}>Hapus</button>}</div>)}{pools.length===0&&<EmptyState text="Belum ada IP pool."/>}</div>
        </Surface>
      </section>

      <Surface>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div className="flex items-start gap-3"><div className="rounded-xl bg-blue-50 p-2 text-blue-700"><Wifi size={18}/></div><div><div className="font-bold text-slate-800">Live RADIUS Accounting</div><div className="mt-1 text-xs text-slate-500">Pantau PPPoE online, framed IP, traffic, stale accounting, CoA, dan Disconnect.</div></div></div><Link href="/network/sessions" className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"><Network size={16}/> Buka Online Sessions</Link></div>
      </Surface>
    </div>
  </Layout>;
}

function Status({value}:{value:string}) {
  const normalized=(value||'unknown').toLowerCase();
  const cls=normalized==='online'?'bg-emerald-50 text-emerald-700 ring-emerald-200':normalized==='offline'?'bg-rose-50 text-rose-700 ring-rose-200':'bg-slate-100 text-slate-600 ring-slate-200';
  return <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ${cls}`}>{normalized}</span>;
}
function rupiah(v:number){return new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0));}
function formatDate(v?:string){return v?new Date(v).toLocaleString('id-ID'):'belum pernah';}
