import React, {FormEvent, useState} from 'react';
import {Head, Link, router} from '@inertiajs/react';
import Layout from '../../components/Layout';

type SessionRow = {
  radacctid:number; acctsessionid:string; acctuniqueid:string; username:string;
  nasipaddress:string; nasportid?:string; nasporttype?:string; framedipaddress?:string;
  callingstationid?:string; calledstationid?:string; acctstarttime?:string; acctupdatetime?:string;
  acctstoptime?:string; acctsessiontime?:number; acctinputoctets?:number; acctoutputoctets?:number;
  acctterminatecause?:string; class?:string; service_id:number; service_number:string;
  service_status:string; customer_id:number; customer_number:string; customer_name:string;
  plan_name?:string; plan_code?:string; network_nas_id?:number; nas_shortname?:string;
  coa_port?:number; router_name?:string; last_coa_at?:string; last_disconnect_at?:string;
};

type Page<T>={data:T[];current_page:number;last_page:number;prev_page_url?:string|null;next_page_url?:string|null;total:number};
type ActionRow={id:number;action:string;target?:string;response_code?:string;success:boolean;output?:string;created_at:string;service?:{service_number:string,pppoe_username:string};nas?:{shortname:string,nasname:string};actor?:{name:string}};

const input='w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-sky-500';

export default function SessionsIndex({sessions,recent,actions,stats,filters}:{sessions:Page<SessionRow>,recent:SessionRow[],actions:ActionRow[],stats:any,filters:{q?:string}}){
  const [q,setQ]=useState(filters.q||'');
  const submit=(e:FormEvent)=>{e.preventDefault();router.get('/network/sessions',q?{q}:{},{preserveState:true,replace:true});};
  const disconnect=(s:SessionRow)=>{if(confirm(`Disconnect session ${s.username} sekarang?`))router.post(`/network/sessions/${s.radacctid}/disconnect`,{}, {preserveScroll:true});};
  const coa=(s:SessionRow)=>{if(confirm(`Kirim CoA rate-limit paket ${s.plan_name||''} ke ${s.username}?`))router.post(`/network/sessions/${s.radacctid}/coa`,{}, {preserveScroll:true});};

  return <Layout>
    <Head title="Online Sessions & Accounting"/>
    <div className="space-y-6">
      <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div><h2 className="text-2xl font-black">Online Sessions & RADIUS Accounting</h2><p className="text-sm text-slate-400 mt-1">Data live berasal dari <code>radacct</code>. CoA dan Disconnect dikirim langsung ke NAS yang memegang session.</p></div>
        <Link href="/network" className="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm hover:border-sky-700">Network Core</Link>
      </div>

      <section className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <Metric title="Online" value={stats.online}/><Metric title="Stale >15m" value={stats.stale}/><Metric title="Acct Input" value={bytes(stats.input_octets)}/><Metric title="Acct Output" value={bytes(stats.output_octets)}/>
      </section>

      <section className="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
        <form onSubmit={submit} className="flex flex-col md:flex-row gap-3">
          <input className={input} value={q} onChange={e=>setQ(e.target.value)} placeholder="Cari PPPoE, IP, MAC, service number, customer..."/>
          <button className="rounded-lg bg-sky-600 px-5 py-2 text-sm font-semibold hover:bg-sky-500">Cari</button>
          {filters.q&&<button type="button" onClick={()=>{setQ('');router.get('/network/sessions')}} className="rounded-lg bg-slate-800 px-4 py-2 text-sm">Reset</button>}
        </form>
      </section>

      <section className="rounded-xl border border-slate-800 bg-slate-900/50 overflow-hidden">
        <div className="p-5 border-b border-slate-800 flex items-center justify-between"><div><h3 className="font-bold text-lg">Session Online</h3><p className="text-xs text-slate-500">Accounting-Start / Interim-Update tanpa Accounting-Stop.</p></div><span className="text-xs rounded bg-emerald-950 text-emerald-200 px-2 py-1">{sessions.total} online</span></div>
        <div className="overflow-x-auto">
          <table className="min-w-[1180px] w-full text-sm">
            <thead className="bg-slate-950/70 text-xs uppercase text-slate-500"><tr><Th>User / Customer</Th><Th>IP & NAS</Th><Th>Plan</Th><Th>Start / Update</Th><Th>Traffic</Th><Th>Session</Th><Th>Aksi</Th></tr></thead>
            <tbody>{sessions.data.map(s=><tr key={s.radacctid} className="border-t border-slate-800 align-top">
              <Td><div className="font-semibold text-sky-300">{s.username}</div><Link href={`/customers/${s.customer_id}`} className="text-xs hover:text-sky-300">{s.customer_number} · {s.customer_name}</Link><div className="text-[11px] text-slate-500">{s.service_number} · {s.service_status}</div></Td>
              <Td><div>{s.framedipaddress||'-'}</div><div className="text-xs text-slate-500">NAS {s.nas_shortname||s.nasipaddress||'-'}</div><div className="text-[11px] text-slate-600">MAC {s.callingstationid||'-'}</div></Td>
              <Td><div>{s.plan_name||'-'}</div><div className="text-xs text-slate-500">{s.plan_code||''}</div></Td>
              <Td><div>{dt(s.acctstarttime)}</div><div className={`text-xs ${isStale(s)?'text-amber-300':'text-slate-500'}`}>update {dt(s.acctupdatetime)}</div></Td>
              <Td><div className="text-xs">In {bytes(s.acctinputoctets)}</div><div className="text-xs">Out {bytes(s.acctoutputoctets)}</div></Td>
              <Td><div>{duration(s.acctsessiontime,s.acctstarttime)}</div><div className="text-[11px] text-slate-500 font-mono">{s.acctsessionid}</div></Td>
              <Td><div className="flex flex-col gap-2 min-w-32"><button disabled={!s.network_nas_id} onClick={()=>coa(s)} className="rounded bg-indigo-800 px-3 py-1.5 text-xs disabled:opacity-40">CoA Paket</button><button disabled={!s.network_nas_id} onClick={()=>disconnect(s)} className="rounded bg-rose-900 px-3 py-1.5 text-xs disabled:opacity-40">Disconnect</button>{!s.network_nas_id&&<span className="text-[10px] text-amber-400">Assign NAS pada service</span>}</div></Td>
            </tr>)}{sessions.data.length===0&&<tr><td colSpan={7} className="p-8 text-center text-slate-500">Belum ada session PPPoE online.</td></tr>}</tbody>
          </table>
        </div>
        {(sessions.prev_page_url||sessions.next_page_url)&&<div className="p-4 border-t border-slate-800 flex justify-between text-sm"><button disabled={!sessions.prev_page_url} onClick={()=>sessions.prev_page_url&&router.visit(sessions.prev_page_url,{preserveState:true})} className="disabled:opacity-30">← Sebelumnya</button><span className="text-slate-500">Page {sessions.current_page}/{sessions.last_page}</span><button disabled={!sessions.next_page_url} onClick={()=>sessions.next_page_url&&router.visit(sessions.next_page_url,{preserveState:true})} className="disabled:opacity-30">Berikutnya →</button></div>}
      </section>

      <section className="grid xl:grid-cols-2 gap-6">
        <Card title="Recent Accounting Stop">
          <div className="space-y-2 max-h-[520px] overflow-auto">{recent.map(s=><div key={s.radacctid} className="rounded-lg border border-slate-800 p-3"><div className="flex justify-between gap-3"><div><div className="font-semibold">{s.username} <span className="text-xs text-slate-500">{s.service_number}</span></div><div className="text-xs text-slate-400">{s.customer_name} · {s.framedipaddress||'-'} · {s.nas_shortname||s.nasipaddress}</div></div><div className="text-right text-xs"><div>{duration(s.acctsessiontime,s.acctstarttime)}</div><div className="text-slate-500">{dt(s.acctstoptime)}</div></div></div><div className="mt-2 text-[11px] text-slate-500">Terminate: {s.acctterminatecause||'-'} · In {bytes(s.acctinputoctets)} · Out {bytes(s.acctoutputoctets)}</div></div>)}{recent.length===0&&<div className="text-sm text-slate-500">Belum ada Accounting-Stop.</div>}</div>
        </Card>
        <Card title="CoA / Disconnect Audit">
          <div className="space-y-2 max-h-[520px] overflow-auto">{actions.map(a=><details key={a.id} className="rounded-lg border border-slate-800 p-3"><summary className="cursor-pointer"><span className={`rounded px-2 py-0.5 text-[10px] uppercase ${a.success?'bg-emerald-950 text-emerald-200':'bg-rose-950 text-rose-200'}`}>{a.action} {a.response_code||'no-response'}</span><span className="ml-2 text-sm">{a.service?.pppoe_username||'-'}</span><span className="float-right text-xs text-slate-500">{dt(a.created_at)}</span></summary><div className="mt-2 text-xs text-slate-400">NAS {a.nas?.shortname||a.target||'-'} · actor {a.actor?.name||'system'}</div>{a.output&&<pre className="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded bg-black/30 p-2 text-[10px] text-slate-400">{a.output}</pre>}</details>)}{actions.length===0&&<div className="text-sm text-slate-500">Belum ada aksi CoA/Disconnect.</div>}</div>
        </Card>
      </section>

      <section className="rounded-xl border border-sky-950 bg-sky-950/20 p-5 text-sm text-slate-300">
        <div className="font-bold text-sky-300">MikroTik untuk Phase 04</div><div className="mt-2 font-mono text-xs">/ppp aaa set use-radius=yes accounting=yes interim-update=0s</div><div className="font-mono text-xs">/radius incoming set accept=yes port=3799</div><p className="mt-2 text-xs text-slate-500">CoA dapat mengubah rate-limit session aktif. Untuk perubahan IP/pool/route, lakukan Disconnect agar PPPoE login kembali dengan atribut baru.</p>
      </section>
    </div>
  </Layout>;
}

function Card({title,children}:{title:string,children:React.ReactNode}){return <section className="rounded-xl border border-slate-800 bg-slate-900/50 p-5"><h3 className="font-bold text-lg mb-4">{title}</h3>{children}</section>}
function Metric({title,value}:{title:string,value:any}){return <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-4"><div className="text-xs uppercase tracking-wide text-slate-500">{title}</div><div className="text-2xl font-black mt-1">{String(value)}</div></div>}
function Th({children}:{children:React.ReactNode}){return <th className="text-left px-4 py-3 font-semibold">{children}</th>}
function Td({children}:{children:React.ReactNode}){return <td className="px-4 py-3">{children}</td>}
function dt(v?:string){return v?new Date(v).toLocaleString('id-ID'):'-'}
function bytes(v?:number){const n=Number(v||0);if(n<1024)return `${n} B`;const units=['KB','MB','GB','TB'];let x=n/1024,i=0;while(x>=1024&&i<units.length-1){x/=1024;i++;}return `${x.toFixed(x>=100?0:x>=10?1:2)} ${units[i]}`}
function duration(seconds?:number,start?:string){let n=Number(seconds||0);if(!n&&start)n=Math.max(0,Math.floor((Date.now()-new Date(start).getTime())/1000));const d=Math.floor(n/86400),h=Math.floor((n%86400)/3600),m=Math.floor((n%3600)/60);return `${d?d+'d ':''}${h}h ${m}m`}
function isStale(s:SessionRow){const t=s.acctupdatetime||s.acctstarttime;return !!t&&(Date.now()-new Date(t).getTime()>15*60*1000)}
