import React,{FormEvent,useState} from 'react';
import {Head,Link,router} from '@inertiajs/react';
import {Activity,AlertTriangle,ArrowDownToLine,ArrowUpFromLine,History,Network,Radio,RefreshCw,Search,ShieldCheck,Wifi} from 'lucide-react';
import Layout from '../../components/Layout';
import {EmptyState,MetricCard,PageHeader,Surface} from '../../components/Ui';
import {useAccess} from '../../hooks/useAccess';

type Page<T>={data:T[];current_page:number;last_page:number;prev_page_url?:string|null;next_page_url?:string|null;total:number};
type SessionRow={radacctid:number;acctsessionid:string;username:string;nasipaddress?:string;acctstarttime?:string;acctupdatetime?:string;acctstoptime?:string;acctsessiontime?:number;acctinputoctets?:number;acctoutputoctets?:number;callingstationid?:string;acctterminatecause?:string;framedipaddress?:string;service_id:number;service_number:string;service_status:string;customer_id:number;customer_number:string;customer_name:string;plan_name?:string;plan_code?:string;network_nas_id?:number;nas_shortname?:string;router_name?:string};
type ActionRow={id:number;action:string;target?:string;response_code?:string;success:boolean;output?:string;created_at:string;service?:{service_number:string;pppoe_username:string};nas?:{shortname:string;nasname:string};actor?:{name:string}};

const input='w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-50';

export default function SessionsIndex({sessions,recent,actions,stats,filters}:{sessions:Page<SessionRow>;recent:SessionRow[];actions:ActionRow[];stats:any;filters:{q?:string}}){
  const {can}=useAccess();
  const canManage=can('network.manage');
  const [q,setQ]=useState(filters.q||'');
  const submit=(e:FormEvent)=>{e.preventDefault();router.get('/network/sessions',q?{q}:{},{preserveState:true,replace:true});};
  const disconnect=(s:SessionRow)=>{if(!canManage)return;if(confirm(`Disconnect session ${s.username} sekarang?`))router.post(`/network/sessions/${s.radacctid}/disconnect`,{}, {preserveScroll:true});};
  const coa=(s:SessionRow)=>{if(!canManage)return;if(confirm(`Kirim CoA rate-limit paket ${s.plan_name||''} ke ${s.username}?`))router.post(`/network/sessions/${s.radacctid}/coa`,{}, {preserveScroll:true});};

  return <Layout>
    <Head title="Online Sessions & Accounting"/>
    <div className="space-y-6">
      <PageHeader eyebrow="NOC / RADIUS" title="Online Sessions & Accounting" description="Pantau session PPPoE berdasarkan radacct, traffic realtime, stale accounting, serta audit CoA/Disconnect per tenant." actions={<Link href="/network" className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-700"><Network size={16}/> Network Core</Link>}/>

      <section className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <MetricCard icon={Wifi} label="Online Sessions" value={stats.online} hint={`${sessions.total} session pada filter saat ini`} tone="emerald"/>
        <MetricCard icon={AlertTriangle} label="Stale >15m" value={stats.stale} hint="Perlu cek interim-update / konektivitas NAS" tone={Number(stats.stale)>0?'amber':'emerald'}/>
        <MetricCard icon={ArrowDownToLine} label="Acct Input" value={bytes(stats.input_octets)} hint="Akumulasi input session online" tone="cyan"/>
        <MetricCard icon={ArrowUpFromLine} label="Acct Output" value={bytes(stats.output_octets)} hint="Akumulasi output session online" tone="violet"/>
      </section>

      <Surface title="Cari Session" subtitle="Cari berdasarkan PPPoE username, IP, MAC, service number, customer number, atau nama pelanggan.">
        <form onSubmit={submit} className="flex flex-col gap-3 md:flex-row">
          <div className="relative flex-1"><Search size={16} className="pointer-events-none absolute left-3 top-3 text-slate-400"/><input className={`${input} pl-9`} value={q} onChange={e=>setQ(e.target.value)} placeholder="Cari session..."/></div>
          <button className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"><Search size={15}/> Cari</button>
          {filters.q&&<button type="button" onClick={()=>{setQ('');router.get('/network/sessions')}} className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:border-blue-300"><RefreshCw size={15}/> Reset</button>}
        </form>
      </Surface>

      <Surface title="Session Online" subtitle="Accounting-Start / Interim-Update yang belum memiliki Accounting-Stop." className="overflow-hidden">
        <div className="mb-4 flex items-center justify-between"><span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><Activity size={13}/>{sessions.total} online</span>{!canManage&&<span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Read-only mode</span>}</div>
        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="min-w-[1180px] w-full text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><Th>User / Customer</Th><Th>IP & NAS</Th><Th>Plan</Th><Th>Start / Update</Th><Th>Traffic</Th><Th>Session</Th>{canManage&&<Th>Aksi</Th>}</tr></thead>
            <tbody className="bg-white">{sessions.data.map(s=><tr key={s.radacctid} className="border-t border-slate-100 align-top hover:bg-slate-50/60">
              <Td><div className="font-bold text-blue-700">{s.username}</div><Link href={`/customers/${s.customer_id}`} className="text-xs font-medium text-slate-700 hover:text-blue-700">{s.customer_number} · {s.customer_name}</Link><div className="text-[11px] text-slate-400">{s.service_number} · {s.service_status}</div></Td>
              <Td><div className="font-mono text-xs font-semibold text-slate-700">{s.framedipaddress||'-'}</div><div className="mt-1 text-xs text-slate-500">NAS {s.nas_shortname||s.nasipaddress||'-'}</div><div className="text-[11px] text-slate-400">Router {s.router_name||'-'} · MAC {s.callingstationid||'-'}</div></Td>
              <Td><div className="font-semibold text-slate-700">{s.plan_name||'-'}</div><div className="text-xs text-slate-400">{s.plan_code||''}</div></Td>
              <Td><div className="text-xs text-slate-700">{dt(s.acctstarttime)}</div><div className={`mt-1 inline-flex items-center gap-1 text-xs ${isStale(s)?'font-semibold text-amber-700':'text-slate-400'}`}>{isStale(s)&&<AlertTriangle size={11}/>}update {dt(s.acctupdatetime)}</div></Td>
              <Td><div className="text-xs text-slate-700">In {bytes(s.acctinputoctets)}</div><div className="text-xs text-slate-700">Out {bytes(s.acctoutputoctets)}</div></Td>
              <Td><div className="font-semibold text-slate-700">{duration(s.acctsessiontime,s.acctstarttime)}</div><div className="mt-1 max-w-36 truncate font-mono text-[10px] text-slate-400" title={s.acctsessionid}>{s.acctsessionid}</div></Td>
              {canManage&&<Td><div className="flex min-w-32 flex-col gap-2"><button disabled={!s.network_nas_id} onClick={()=>coa(s)} className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-40">CoA Paket</button><button disabled={!s.network_nas_id} onClick={()=>disconnect(s)} className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-40">Disconnect</button>{!s.network_nas_id&&<span className="text-[10px] text-amber-600">Assign NAS pada service</span>}</div></Td>}
            </tr>)}{sessions.data.length===0&&<tr><td colSpan={canManage?7:6}><EmptyState text="Tidak ada session PPPoE online untuk filter ini."/></td></tr>}</tbody>
          </table>
        </div>
        {(sessions.prev_page_url||sessions.next_page_url)&&<div className="mt-4 flex items-center justify-between text-sm"><button disabled={!sessions.prev_page_url} onClick={()=>sessions.prev_page_url&&router.visit(sessions.prev_page_url,{preserveState:true})} className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-600 disabled:opacity-30">← Sebelumnya</button><span className="text-xs text-slate-500">Page {sessions.current_page}/{sessions.last_page}</span><button disabled={!sessions.next_page_url} onClick={()=>sessions.next_page_url&&router.visit(sessions.next_page_url,{preserveState:true})} className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-600 disabled:opacity-30">Berikutnya →</button></div>}
      </Surface>

      <section className="grid gap-6 xl:grid-cols-2">
        <Surface title="Recent Accounting Stop" subtitle="Session terakhir yang sudah menerima Accounting-Stop.">
          <div className="max-h-[520px] space-y-3 overflow-auto pr-1">{recent.map(s=><div key={s.radacctid} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex justify-between gap-3"><div><div className="flex items-center gap-2 font-bold text-slate-800"><History size={14} className="text-slate-400"/>{s.username}<span className="text-xs font-medium text-slate-400">{s.service_number}</span></div><div className="mt-1 text-xs text-slate-500">{s.customer_name} · {s.framedipaddress||'-'} · {s.nas_shortname||s.nasipaddress}</div></div><div className="text-right text-xs"><div className="font-semibold text-slate-700">{duration(s.acctsessiontime,s.acctstarttime)}</div><div className="text-slate-400">{dt(s.acctstoptime)}</div></div></div><div className="mt-2 text-[11px] text-slate-400">Terminate: {s.acctterminatecause||'-'} · In {bytes(s.acctinputoctets)} · Out {bytes(s.acctoutputoctets)}</div></div>)}{recent.length===0&&<EmptyState text="Belum ada Accounting-Stop."/>}</div>
        </Surface>

        <Surface title="CoA / Disconnect Audit" subtitle="Audit tindakan NOC ke session dan NAS.">
          <div className="max-h-[520px] space-y-3 overflow-auto pr-1">{actions.map(a=><details key={a.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><summary className="cursor-pointer list-none"><div className="flex items-start justify-between gap-3"><div><span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${a.success?'bg-emerald-50 text-emerald-700':'bg-rose-50 text-rose-700'}`}>{a.action} · {a.response_code||'no-response'}</span><div className="mt-1 text-sm font-semibold text-slate-700">{a.service?.pppoe_username||'-'}</div></div><span className="text-xs text-slate-400">{dt(a.created_at)}</span></div></summary><div className="mt-3 flex items-center gap-2 text-xs text-slate-500"><Radio size={12}/> NAS {a.nas?.shortname||a.target||'-'} · actor {a.actor?.name||'system'}</div>{a.output&&<pre className="mt-3 max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-950 p-3 text-[10px] leading-5 text-slate-300">{a.output}</pre>}</details>)}{actions.length===0&&<EmptyState text="Belum ada aksi CoA/Disconnect."/>}</div>
        </Surface>
      </section>

      <Surface>
        <div className="flex gap-3"><div className="rounded-xl bg-blue-50 p-2 text-blue-700"><ShieldCheck size={18}/></div><div><div className="font-bold text-slate-800">MikroTik RADIUS Operations</div><div className="mt-1 space-y-1 font-mono text-xs text-slate-500"><div>/ppp aaa set use-radius=yes accounting=yes interim-update=0s</div><div>/radius incoming set accept=yes port=3799</div></div><p className="mt-2 text-xs leading-5 text-slate-500">CoA dapat memperbarui rate-limit session aktif. Perubahan IP, pool, atau route sebaiknya diikuti Disconnect agar PPPoE melakukan autentikasi ulang dengan atribut terbaru.</p></div></div>
      </Surface>
    </div>
  </Layout>;
}

function Th({children}:{children:React.ReactNode}){return <th className="px-4 py-3 text-left font-semibold">{children}</th>}
function Td({children}:{children:React.ReactNode}){return <td className="px-4 py-3">{children}</td>}
function dt(v?:string){return v?new Date(v).toLocaleString('id-ID'):'-'}
function bytes(v?:number){const n=Number(v||0);if(n<1024)return `${n} B`;const units=['KB','MB','GB','TB'];let x=n/1024,i=0;while(x>=1024&&i<units.length-1){x/=1024;i++;}return `${x.toFixed(x>=100?0:x>=10?1:2)} ${units[i]}`}
function duration(seconds?:number,start?:string){let n=Number(seconds||0);if(!n&&start)n=Math.max(0,Math.floor((Date.now()-new Date(start).getTime())/1000));const d=Math.floor(n/86400),h=Math.floor((n%86400)/3600),m=Math.floor((n%3600)/60);return `${d?d+'d ':''}${h}h ${m}m`}
function isStale(s:SessionRow){const t=s.acctupdatetime||s.acctstarttime;return !!t&&(Date.now()-new Date(t).getTime()>15*60*1000)}
