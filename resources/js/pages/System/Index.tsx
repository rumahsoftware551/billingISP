import React, { FormEvent, useMemo } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import Layout from '../../components/Layout';

const badge = (ok:boolean) => ok
  ? 'border-emerald-800 bg-emerald-950/60 text-emerald-200'
  : 'border-rose-800 bg-rose-950/60 text-rose-200';

function Card({title, children}:{title:string;children:React.ReactNode}) {
  return <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
    <h2 className="font-semibold text-slate-100 mb-4">{title}</h2>{children}
  </section>;
}

export default function SystemIndex(props:any) {
  const health = props.health || {checks:{}, status:'unknown'};
  const notification = useForm({channel:'log', recipient:'admin@jaringanku.local'});
  const webhook = useForm({
    name:'', url:'', secret:'', events:'system.test', enabled:true,
    timeout_seconds:10, max_attempts:3,
  });

  const eventArray = useMemo(() => webhook.data.events.split(',').map((v:string)=>v.trim()).filter(Boolean), [webhook.data.events]);

  const submitNotification=(e:FormEvent)=>{e.preventDefault();notification.post('/system/notifications/test');};
  const submitWebhook=(e:FormEvent)=>{
    e.preventDefault();
    router.post('/system/webhooks', {...webhook.data, events:eventArray}, {
      preserveScroll:true,
      onSuccess:()=>webhook.reset('name','url','secret'),
    });
  };

  return <Layout>
    <Head title="System & Production Readiness" />
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">System & Production Readiness</h1>
        <p className="text-sm text-slate-400 mt-1">Health, security, notification outbox, webhook delivery, dan audit operasional.</p>
      </div>

      {props.flash?.generated_webhook_secret && <div className="rounded-xl border border-amber-700 bg-amber-950/50 p-4">
        <div className="font-semibold text-amber-200">Simpan webhook secret ini sekarang</div>
        <div className="text-xs text-amber-300/80 mt-1">Secret hanya ditampilkan satu kali dan setelah ini tetap tersimpan terenkripsi.</div>
        <code className="mt-3 block break-all rounded bg-slate-950 p-3 text-sm select-all">{props.flash.generated_webhook_secret}</code>
      </div>}

      <div className="grid md:grid-cols-4 gap-3">
        <div className={`rounded-xl border p-4 ${health.status==='healthy'?'border-emerald-800 bg-emerald-950/40':'border-amber-800 bg-amber-950/40'}`}>
          <div className="text-xs uppercase text-slate-400">Overall</div>
          <div className="text-xl font-bold mt-1 uppercase">{health.status}</div>
          <div className="text-xs text-slate-400 mt-1">{health.checked_at}</div>
        </div>
        {Object.entries(health.checks || {}).slice(0,3).map(([key,value]:any)=><div key={key} className={`rounded-xl border p-4 ${badge(!!value.ok)}`}>
          <div className="text-xs uppercase opacity-70">{key}</div>
          <div className="font-semibold mt-1">{value.ok?'OK':'CHECK'}</div>
          <div className="text-xs mt-1 opacity-80 break-words">{value.message}</div>
        </div>)}
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        <Card title="Health detail">
          <div className="space-y-2">
            {Object.entries(health.checks || {}).map(([key,value]:any)=><div key={key} className="flex items-start justify-between gap-4 border-b border-slate-800 pb-2">
              <div><div className="font-medium capitalize">{key.replace('_',' ')}</div><div className="text-xs text-slate-400">{value.message}</div></div>
              <div className={value.ok?'text-emerald-400':'text-rose-400'}>{value.ok?'PASS':'WARN'}</div>
            </div>)}
          </div>
        </Card>

        <Card title="Production flags">
          <dl className="space-y-2 text-sm">
            {Object.entries(props.production || {}).map(([key,value]:any)=><div key={key} className="flex justify-between gap-4 border-b border-slate-800 pb-2">
              <dt className="text-slate-400">{key}</dt><dd className="font-mono">{String(value)}</dd>
            </div>)}
          </dl>
        </Card>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        <Card title="Notification engine test">
          <form onSubmit={submitNotification} className="space-y-3">
            <label className="block text-sm">Channel<select className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={notification.data.channel} onChange={e=>notification.setData('channel',e.target.value)}><option value="log">Log</option><option value="email">Email</option></select></label>
            <label className="block text-sm">Recipient<input className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={notification.data.recipient} onChange={e=>notification.setData('recipient',e.target.value)} /></label>
            <button disabled={notification.processing} className="rounded-lg bg-sky-600 hover:bg-sky-500 px-4 py-2 disabled:opacity-50">Queue test notification</button>
          </form>
          <div className="mt-4 text-xs text-slate-400">Channel log/email tetap tersedia. WhatsApp Cloud API dan payment notification dikonfigurasi melalui menu Integrations pada Phase 09.</div>
        </Card>

        <Card title="Tambah webhook endpoint">
          <form onSubmit={submitWebhook} className="space-y-3">
            <div className="grid md:grid-cols-2 gap-3">
              <label className="block text-sm">Name<input className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={webhook.data.name} onChange={e=>webhook.setData('name',e.target.value)} /></label>
              <label className="block text-sm">Secret <span className="text-slate-500">(kosong = generate)</span><input type="password" className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={webhook.data.secret} onChange={e=>webhook.setData('secret',e.target.value)} /></label>
            </div>
            <label className="block text-sm">HTTPS/HTTP URL<input className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={webhook.data.url} onChange={e=>webhook.setData('url',e.target.value)} placeholder="https://example.com/webhooks/jaringanku" /></label>
            <label className="block text-sm">Events, comma separated<input className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={webhook.data.events} onChange={e=>webhook.setData('events',e.target.value)} placeholder="system.test,billing.payment.posted" /></label>
            <div className="grid grid-cols-2 gap-3">
              <label className="block text-sm">Timeout<input type="number" min={1} max={30} className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={webhook.data.timeout_seconds} onChange={e=>webhook.setData('timeout_seconds',Number(e.target.value))} /></label>
              <label className="block text-sm">Max attempts<input type="number" min={1} max={5} className="mt-1 w-full rounded-lg bg-slate-950 border border-slate-700 p-2" value={webhook.data.max_attempts} onChange={e=>webhook.setData('max_attempts',Number(e.target.value))} /></label>
            </div>
            <button className="rounded-lg bg-sky-600 hover:bg-sky-500 px-4 py-2">Create webhook</button>
          </form>
        </Card>
      </div>

      <Card title="Webhook endpoints">
        <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="text-slate-400"><tr><th className="text-left p-2">Name</th><th className="text-left p-2">URL</th><th className="text-left p-2">Events</th><th className="text-left p-2">Status</th><th className="text-right p-2">Action</th></tr></thead><tbody>
          {(props.webhooks || []).map((w:any)=><tr key={w.id} className="border-t border-slate-800"><td className="p-2 font-medium">{w.name}</td><td className="p-2 max-w-sm truncate">{w.url}</td><td className="p-2 text-xs">{(w.events || []).join(', ')}</td><td className="p-2">{w.enabled?'enabled':'disabled'}</td><td className="p-2 text-right space-x-2"><button onClick={()=>router.post(`/system/webhooks/${w.id}/test`,{}, {preserveScroll:true})} className="px-2 py-1 rounded bg-slate-700">Test</button><button onClick={()=>confirm('Hapus webhook?')&&router.delete(`/system/webhooks/${w.id}`,{preserveScroll:true})} className="px-2 py-1 rounded bg-rose-900">Delete</button></td></tr>)}
          {(props.webhooks || []).length===0&&<tr><td colSpan={5} className="p-4 text-slate-500">Belum ada webhook endpoint.</td></tr>}
        </tbody></table></div>
      </Card>

      <div className="grid lg:grid-cols-2 gap-6">
        <Card title="Recent notification outbox">
          <div className="space-y-2 max-h-80 overflow-auto">{(props.notifications || []).map((n:any)=><div key={n.id} className="border-b border-slate-800 pb-2"><div className="flex justify-between"><span>{n.channel} → {n.recipient}</span><span className="text-xs uppercase">{n.status}</span></div><div className="text-xs text-slate-500">#{n.id} attempts={n.attempts} {n.last_error||''}</div></div>)}{(props.notifications||[]).length===0&&<div className="text-slate-500">Belum ada notification.</div>}</div>
        </Card>
        <Card title="Recent webhook deliveries">
          <div className="space-y-2 max-h-80 overflow-auto">{(props.deliveries || []).map((d:any)=><div key={d.id} className="border-b border-slate-800 pb-2"><div className="flex justify-between"><span>{d.event} → {d.endpoint?.name}</span><span className="text-xs uppercase">{d.status}</span></div><div className="text-xs text-slate-500">HTTP {d.response_code||'-'} attempts={d.attempts} {d.last_error||''}</div></div>)}{(props.deliveries||[]).length===0&&<div className="text-slate-500">Belum ada delivery.</div>}</div>
        </Card>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        <Card title="Security events">
          <div className="space-y-2 max-h-80 overflow-auto">{(props.securityEvents || []).map((e:any)=><div key={e.id} className="border-b border-slate-800 pb-2"><div className="flex justify-between"><span>{e.type}</span><span className={e.severity==='warning'?'text-amber-400':'text-slate-400'}>{e.severity}</span></div><div className="text-xs text-slate-500">{e.ip_address||'-'} · {e.created_at}</div></div>)}</div>
        </Card>
        <Card title="Audit log">
          <div className="space-y-2 max-h-80 overflow-auto">{(props.auditLogs || []).map((a:any)=><div key={a.id} className="border-b border-slate-800 pb-2"><div className="flex justify-between gap-3"><span>{a.event}</span><span className="text-xs text-slate-500">{a.source}</span></div><div className="text-xs text-slate-500">{a.user?.email||'system'} · {a.created_at} · request {a.request_id||'-'}</div></div>)}</div>
        </Card>
      </div>
    </div>
  </Layout>;
}
