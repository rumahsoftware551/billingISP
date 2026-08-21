import React from 'react';
import { Head, router as inertiaRouter, useForm, usePage } from '@inertiajs/react';
import Layout from '../../components/Layout';

type RouterRow = {id:number,name:string,host:string,rest_port:number,api_username:string,verify_tls:boolean,status:string,routeros_version?:string,board_name?:string,last_seen_at?:string,last_error?:string};
type NasRow = {id:number,router_id?:number,nasname:string,shortname:string,type:string,coa_port:number,active:boolean,description?:string,router?:{id:number,name:string}};
type PlanRow = {id:number,name:string,code:string,price:number,download_kbps:number,upload_kbps:number,acct_interim_interval:number,active:boolean,radius_attributes?:Record<string,string>};
type PoolRow = {id:number,name:string,start_ip:string,end_ip:string,gateway?:string,active:boolean};

const input = 'w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-sky-500';
const btn = 'rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold hover:bg-sky-500 disabled:opacity-50';

export default function NetworkIndex({routers,nas,plans,pools,radius}:{routers:RouterRow[],nas:NasRow[],plans:PlanRow[],pools:PoolRow[],radius:any}) {
  const page:any = usePage().props;
  const routerForm = useForm({name:'',host:'',rest_port:443,api_username:'api-jaringanku',api_password:'',verify_tls:false});
  const nasForm = useForm({router_id:'',nasname:'',shortname:'',type:'mikrotik',secret:'',coa_port:3799,description:''});
  const planForm = useForm({name:'',code:'',price:250000,download_kbps:20000,upload_kbps:10000,acct_interim_interval:300});
  const poolForm = useForm({name:'',start_ip:'10.10.10.2',end_ip:'10.10.10.254',gateway:'10.10.10.1'});
  const radiusForm = useForm({username:'phase2-test',password:'Phase2Test123!'});

  const del = (url:string) => { if (confirm('Hapus data ini?')) inertiaRouter.delete(url, {preserveScroll:true}); };

  return <Layout>
    <Head title="Network Core"/>
    <div className="space-y-6">
      <section className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <Metric title="Router" value={routers.length}/><Metric title="NAS" value={nas.length}/><Metric title="RADIUS SQL" value={radius.test_ready?'Ready':'Not ready'}/><Metric title="Plans" value={plans.length}/>
      </section>

      <section className="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
        <div className="flex items-center justify-between gap-4 mb-4"><div><h2 className="text-lg font-bold">FreeRADIUS Test</h2><p className="text-xs text-slate-400">Tes SQL authentication dari container Laravel ke FreeRADIUS.</p></div><span className="text-xs rounded bg-slate-800 px-2 py-1">UDP 1812/1813</span></div>
        <form className="grid md:grid-cols-3 gap-3" onSubmit={e=>{e.preventDefault();radiusForm.post('/network/radius/test',{preserveScroll:true});}}>
          <input className={input} value={radiusForm.data.username} onChange={e=>radiusForm.setData('username',e.target.value)} placeholder="Username"/>
          <input className={input} type="password" value={radiusForm.data.password} onChange={e=>radiusForm.setData('password',e.target.value)} placeholder="Password"/>
          <button className={btn} disabled={radiusForm.processing}>Test Access-Request</button>
        </form>
        {page.flash?.radius_test?.output && <pre className="mt-4 max-h-52 overflow-auto rounded-lg bg-black/40 p-3 text-xs text-slate-300 whitespace-pre-wrap">{page.flash.radius_test.output}</pre>}
      </section>

      <section className="grid xl:grid-cols-2 gap-6">
        <Card title="Routers / MikroTik REST">
          <form className="grid md:grid-cols-2 gap-3 mb-5" onSubmit={e=>{e.preventDefault();routerForm.post('/network/routers',{preserveScroll:true,onSuccess:()=>routerForm.reset('name','host','api_password')});}}>
            <input className={input} value={routerForm.data.name} onChange={e=>routerForm.setData('name',e.target.value)} placeholder="Nama Router"/>
            <input className={input} value={routerForm.data.host} onChange={e=>routerForm.setData('host',e.target.value)} placeholder="IP / hostname"/>
            <input className={input} type="number" value={routerForm.data.rest_port} onChange={e=>routerForm.setData('rest_port',Number(e.target.value))} placeholder="REST port"/>
            <input className={input} value={routerForm.data.api_username} onChange={e=>routerForm.setData('api_username',e.target.value)} placeholder="Username RouterOS"/>
            <input className={input} type="password" value={routerForm.data.api_password} onChange={e=>routerForm.setData('api_password',e.target.value)} placeholder="Password"/>
            <label className="flex items-center gap-2 text-sm px-1"><input type="checkbox" checked={routerForm.data.verify_tls} onChange={e=>routerForm.setData('verify_tls',e.target.checked)}/> Verify TLS certificate</label>
            <button className={`${btn} md:col-span-2`} disabled={routerForm.processing}>Tambah Router</button>
          </form>
          <div className="space-y-2">{routers.map(r=><div key={r.id} className="rounded-lg border border-slate-800 p-3 flex items-center justify-between gap-3"><div><div className="font-semibold">{r.name} <Status value={r.status}/></div><div className="text-xs text-slate-400">{r.host}:{r.rest_port} · {r.board_name||'-'} · ROS {r.routeros_version||'-'}</div>{r.last_error && <div className="text-xs text-rose-300 mt-1 line-clamp-2">{r.last_error}</div>}</div><div className="flex gap-2"><button onClick={()=>inertiaRouter.post(`/network/routers/${r.id}/test`,{}, {preserveScroll:true})} className="rounded bg-emerald-700 px-3 py-1 text-xs">Test</button><button onClick={()=>del(`/network/routers/${r.id}`)} className="rounded bg-rose-900 px-3 py-1 text-xs">Hapus</button></div></div>)}</div>
        </Card>

        <Card title="NAS / RADIUS Clients">
          <form className="grid md:grid-cols-2 gap-3 mb-5" onSubmit={e=>{e.preventDefault();nasForm.post('/network/nas',{preserveScroll:true,onSuccess:()=>nasForm.reset('nasname','shortname','secret','description')});}}>
            <select className={input} value={nasForm.data.router_id} onChange={e=>nasForm.setData('router_id',e.target.value)}><option value="">Tanpa link router</option>{routers.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select>
            <input className={input} value={nasForm.data.nasname} onChange={e=>nasForm.setData('nasname',e.target.value)} placeholder="NAS IP, contoh 192.168.1.1"/>
            <input className={input} value={nasForm.data.shortname} onChange={e=>nasForm.setData('shortname',e.target.value)} placeholder="Shortname"/>
            <input className={input} type="password" value={nasForm.data.secret} onChange={e=>nasForm.setData('secret',e.target.value)} placeholder="RADIUS shared secret"/>
            <input className={input} type="number" value={nasForm.data.coa_port} onChange={e=>nasForm.setData('coa_port',Number(e.target.value))}/>
            <input className={input} value={nasForm.data.description} onChange={e=>nasForm.setData('description',e.target.value)} placeholder="Deskripsi"/>
            <button className={`${btn} md:col-span-2`} disabled={nasForm.processing}>Tambah & Sync NAS</button>
          </form>
          <div className="space-y-2">{nas.map(n=><div key={n.id} className="rounded-lg border border-slate-800 p-3 flex items-center justify-between gap-3"><div><div className="font-semibold">{n.shortname}</div><div className="text-xs text-slate-400">{n.nasname} · {n.type} · CoA {n.coa_port} · {n.router?.name||'no router'}</div></div><div className="flex gap-2"><button onClick={()=>inertiaRouter.post(`/network/nas/${n.id}/sync`,{}, {preserveScroll:true})} className="rounded bg-slate-700 px-3 py-1 text-xs">Sync</button><button onClick={()=>del(`/network/nas/${n.id}`)} className="rounded bg-rose-900 px-3 py-1 text-xs">Hapus</button></div></div>)}</div>
          <p className="mt-3 text-xs text-amber-300">FreeRADIUS 3.2 membaca SQL clients saat startup. Setelah NAS baru disync, jalankan <code>docker compose restart radius</code>. Untuk MikroTik CoA/Disconnect dengan port Jaringanku 3799: <code>/radius incoming set accept=yes port=3799</code>.</p>
        </Card>
      </section>

      <section className="grid xl:grid-cols-2 gap-6">
        <Card title="Internet Plans">
          <form className="grid md:grid-cols-2 gap-3 mb-5" onSubmit={e=>{e.preventDefault();planForm.post('/network/plans',{preserveScroll:true,onSuccess:()=>planForm.reset('name','code')});}}>
            <input className={input} value={planForm.data.name} onChange={e=>planForm.setData('name',e.target.value)} placeholder="Nama paket"/>
            <input className={input} value={planForm.data.code} onChange={e=>planForm.setData('code',e.target.value)} placeholder="Kode, contoh HOME20"/>
            <input className={input} type="number" value={planForm.data.price} onChange={e=>planForm.setData('price',Number(e.target.value))} placeholder="Harga"/>
            <input className={input} type="number" value={planForm.data.download_kbps} onChange={e=>planForm.setData('download_kbps',Number(e.target.value))} placeholder="Download kbps"/>
            <input className={input} type="number" value={planForm.data.upload_kbps} onChange={e=>planForm.setData('upload_kbps',Number(e.target.value))} placeholder="Upload kbps"/>
            <input className={input} type="number" min={60} max={3600} value={planForm.data.acct_interim_interval} onChange={e=>planForm.setData('acct_interim_interval',Number(e.target.value))} placeholder="Interim accounting (detik)"/>
            <button className={btn} disabled={planForm.processing}>Tambah Paket</button>
          </form>
          <div className="space-y-2">{plans.map(p=><div key={p.id} className="rounded-lg border border-slate-800 p-3 flex justify-between gap-3"><div><div className="font-semibold">{p.name} <span className="text-xs text-slate-500">{p.code}</span></div><div className="text-xs text-slate-400">{p.upload_kbps}k ↑ / {p.download_kbps}k ↓ · Rp {Number(p.price).toLocaleString('id-ID')} · acct {p.acct_interim_interval}s</div></div><button onClick={()=>del(`/network/plans/${p.id}`)} className="rounded bg-rose-900 px-3 py-1 text-xs h-fit">Hapus</button></div>)}</div>
        </Card>

        <Card title="IP Pools">
          <form className="grid md:grid-cols-2 gap-3 mb-5" onSubmit={e=>{e.preventDefault();poolForm.post('/network/pools',{preserveScroll:true,onSuccess:()=>poolForm.reset('name')});}}>
            <input className={input} value={poolForm.data.name} onChange={e=>poolForm.setData('name',e.target.value)} placeholder="Nama pool"/>
            <input className={input} value={poolForm.data.gateway} onChange={e=>poolForm.setData('gateway',e.target.value)} placeholder="Gateway"/>
            <input className={input} value={poolForm.data.start_ip} onChange={e=>poolForm.setData('start_ip',e.target.value)} placeholder="Start IP"/>
            <input className={input} value={poolForm.data.end_ip} onChange={e=>poolForm.setData('end_ip',e.target.value)} placeholder="End IP"/>
            <button className={`${btn} md:col-span-2`} disabled={poolForm.processing}>Tambah Pool</button>
          </form>
          <div className="space-y-2">{pools.map(p=><div key={p.id} className="rounded-lg border border-slate-800 p-3 flex justify-between"><div><div className="font-semibold">{p.name}</div><div className="text-xs text-slate-400">{p.start_ip} - {p.end_ip} · GW {p.gateway||'-'}</div></div><button onClick={()=>del(`/network/pools/${p.id}`)} className="rounded bg-rose-900 px-3 py-1 text-xs h-fit">Hapus</button></div>)}</div>
        </Card>
      </section>
    </div>
  </Layout>;
}

function Card({title,children}:{title:string,children:React.ReactNode}) { return <section className="rounded-xl border border-slate-800 bg-slate-900/50 p-5"><h2 className="text-lg font-bold mb-4">{title}</h2>{children}</section>; }
function Metric({title,value}:{title:string,value:any}) { return <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-4"><div className="text-xs uppercase tracking-wide text-slate-500">{title}</div><div className="text-2xl font-bold mt-1">{value}</div></div>; }
function Status({value}:{value:string}) { const cls=value==='online'?'bg-emerald-900 text-emerald-200':value==='offline'?'bg-rose-900 text-rose-200':'bg-slate-800 text-slate-300'; return <span className={`ml-2 rounded px-2 py-0.5 text-[10px] uppercase ${cls}`}>{value}</span>; }
