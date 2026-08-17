import React, {FormEvent, useMemo} from 'react';
import {Head, router, useForm} from '@inertiajs/react';
import Layout from '../../components/Layout';

const input='mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-sky-500';
const button='rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold hover:bg-sky-500 disabled:opacity-50';
const methods=['gopay','shopeepay','other_qris','bca_va','bni_va','bri_va','permata_va','cimb_va','danamon_va','bsi_va','echannel','credit_card','alfamart','indomaret'];

export default function IntegrationsIndex(props:any){
  const p=props.payment||{}; const w=props.whatsapp||{};
  const payment=useForm({provider:p.provider||'mock',environment:p.environment||'sandbox',enabled:!!p.enabled,merchant_id:p.merchant_id||'',client_key:'',server_key:'',enabled_payments:p.enabled_payments||['gopay','shopeepay','other_qris'],expiry_minutes:p.expiry_minutes||60});
  const wa=useForm({mode:w.mode||'log',enabled:!!w.enabled,graph_version:w.graph_version||'v26.0',phone_number_id:w.phone_number_id||'',business_account_id:w.business_account_id||'',access_token:'',app_secret:'',verify_token:'',default_country_code:w.default_country_code||'62',template_language:w.template_language||'id',template_map:w.template_map||{}});
  const test=useForm({recipient:'081234567890'});
  const toggleMethod=(m:string)=>payment.setData('enabled_payments',payment.data.enabled_payments.includes(m)?payment.data.enabled_payments.filter((x:string)=>x!==m):[...payment.data.enabled_payments,m]);
  const webhookPath=useMemo(()=>`/api/whatsapp/${props.tenant?.slug||'TENANT-SLUG'}/webhook`,[props.tenant?.slug]);
  const submitPayment=(e:FormEvent)=>{e.preventDefault();payment.put('/integrations/payment',{preserveScroll:true});};
  const submitWa=(e:FormEvent)=>{e.preventDefault();wa.put('/integrations/whatsapp',{preserveScroll:true});};
  return <Layout><Head title="Integrations"/><div className="space-y-6">
    <div><h2 className="text-2xl font-black">Payment & WhatsApp Integrations</h2><p className="text-sm text-slate-500 mt-1">Midtrans Snap/QRIS dan WhatsApp Cloud API per tenant. Secret disimpan terenkripsi.</p></div>

    <div className="grid xl:grid-cols-2 gap-6">
      <Card title="Payment Gateway">
        <form onSubmit={submitPayment} className="space-y-4">
          <div className="grid md:grid-cols-3 gap-3"><Label title="Provider"><select className={input} value={payment.data.provider} onChange={e=>payment.setData('provider',e.target.value)}><option value="mock">Mock (local)</option><option value="midtrans">Midtrans Snap</option></select></Label><Label title="Environment"><select className={input} value={payment.data.environment} onChange={e=>payment.setData('environment',e.target.value)}><option value="sandbox">Sandbox</option><option value="production">Production</option></select></Label><Label title="Expiry minutes"><input className={input} type="number" min={5} max={10080} value={payment.data.expiry_minutes} onChange={e=>payment.setData('expiry_minutes',Number(e.target.value))}/></Label></div>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={payment.data.enabled} onChange={e=>payment.setData('enabled',e.target.checked)}/> Enable payment gateway</label>
          <Label title="Merchant ID"><input className={input} value={payment.data.merchant_id} onChange={e=>payment.setData('merchant_id',e.target.value)} placeholder="Optional for Snap legacy integration"/></Label>
          <div className="grid md:grid-cols-2 gap-3"><Label title={`Client Key ${p.has_client_key?'(saved)':''}`}><input type="password" className={input} value={payment.data.client_key} onChange={e=>payment.setData('client_key',e.target.value)} placeholder="Leave blank to keep existing"/></Label><Label title={`Server Key ${p.has_server_key?'(saved)':''}`}><input type="password" className={input} value={payment.data.server_key} onChange={e=>payment.setData('server_key',e.target.value)} placeholder="Leave blank to keep existing"/></Label></div>
          <div><div className="text-sm mb-2">Enabled payment methods</div><div className="grid grid-cols-2 md:grid-cols-3 gap-2">{methods.map(m=><label key={m} className="rounded border border-slate-200 px-2 py-2 text-xs flex gap-2"><input type="checkbox" checked={payment.data.enabled_payments.includes(m)} onChange={()=>toggleMethod(m)}/>{m}</label>)}</div></div>
          <button disabled={payment.processing} className={button}>Save payment configuration</button>
          <Status text={p.last_test_status} error={p.last_error}/>
        </form>
      </Card>

      <Card title="WhatsApp Cloud API">
        <form onSubmit={submitWa} className="space-y-4">
          <div className="grid md:grid-cols-3 gap-3"><Label title="Mode"><select className={input} value={wa.data.mode} onChange={e=>wa.setData('mode',e.target.value)}><option value="log">LOG (local)</option><option value="cloud">Meta Cloud</option></select></Label><Label title="Graph version"><input className={input} value={wa.data.graph_version} onChange={e=>wa.setData('graph_version',e.target.value)}/></Label><Label title="Country code"><input className={input} value={wa.data.default_country_code} onChange={e=>wa.setData('default_country_code',e.target.value)}/></Label></div><div className="grid md:grid-cols-3 gap-3"><Label title="Template language"><input className={input} value={wa.data.template_language} onChange={e=>wa.setData('template_language',e.target.value)} placeholder="id"/></Label></div>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={wa.data.enabled} onChange={e=>wa.setData('enabled',e.target.checked)}/> Enable WhatsApp notification</label>
          <div className="grid md:grid-cols-2 gap-3"><Label title="Phone Number ID"><input className={input} value={wa.data.phone_number_id} onChange={e=>wa.setData('phone_number_id',e.target.value)}/></Label><Label title="WABA ID"><input className={input} value={wa.data.business_account_id} onChange={e=>wa.setData('business_account_id',e.target.value)}/></Label></div>
          <Label title={`Access Token ${w.has_access_token?'(saved)':''}`}><input type="password" className={input} value={wa.data.access_token} onChange={e=>wa.setData('access_token',e.target.value)} placeholder="Leave blank to keep existing"/></Label>
          <div className="grid md:grid-cols-2 gap-3"><Label title={`App Secret ${w.has_app_secret?'(saved)':''}`}><input type="password" className={input} value={wa.data.app_secret} onChange={e=>wa.setData('app_secret',e.target.value)} placeholder="Used to verify webhook signature"/></Label><Label title={`Webhook Verify Token ${w.has_verify_token?'(saved)':''}`}><input type="password" className={input} value={wa.data.verify_token} onChange={e=>wa.setData('verify_token',e.target.value)} placeholder="Leave blank to keep existing"/></Label></div>
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs"><div className="text-slate-500">Webhook path</div><code className="break-all">{webhookPath}</code></div>
          <div><div className="text-sm mb-2">Approved template name mapping</div>{['billing.invoice_created','billing.overdue','billing.payment_received'].map(code=><div key={code} className="grid md:grid-cols-2 gap-2 items-center mb-2"><code className="text-xs text-slate-500">{code}</code><input className={input} value={(wa.data.template_map as any)?.[code]||''} onChange={e=>wa.setData('template_map',{...wa.data.template_map,[code]:e.target.value})} placeholder="Meta template name (optional)"/></div>)}</div>
          <button disabled={wa.processing} className={button}>Save WhatsApp configuration</button>
          <Status text={w.last_test_status} error={w.last_error}/>
        </form>
        <form className="mt-5 border-t border-slate-200 pt-4 flex gap-2" onSubmit={e=>{e.preventDefault();test.post('/integrations/whatsapp/test',{preserveScroll:true});}}><input className={input+' mt-0'} value={test.data.recipient} onChange={e=>test.setData('recipient',e.target.value)} placeholder="0812..."/><button className="rounded-lg bg-emerald-700 px-4 text-sm font-semibold">Send test</button></form>
      </Card>
    </div>

    <Card title="Recent payment gateway transactions"><div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="text-slate-500"><tr><Th>Order</Th><Th>Invoice</Th><Th>Provider</Th><Th>Amount</Th><Th>Status</Th><Th>Updated</Th></tr></thead><tbody>{(props.transactions||[]).map((t:any)=><tr key={t.id} className="border-t border-slate-200"><Td>{t.order_id}</Td><Td>{t.invoice?.invoice_number||'-'}</Td><Td>{t.provider}/{t.environment}</Td><Td>{rupiah(t.amount)}</Td><Td>{t.status}</Td><Td>{fmt(t.updated_at)}</Td></tr>)}{!(props.transactions||[]).length&&<tr><td colSpan={6} className="p-4 text-slate-500">Belum ada transaksi gateway.</td></tr>}</tbody></table></div></Card>
    <Card title="Recent WhatsApp delivery logs"><div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="text-slate-500"><tr><Th>Recipient</Th><Th>Message ID</Th><Th>Status</Th><Th>Error</Th><Th>Updated</Th></tr></thead><tbody>{(props.whatsappLogs||[]).map((x:any)=><tr key={x.id} className="border-t border-slate-200"><Td>{x.recipient}</Td><Td>{x.provider_message_id||'-'}</Td><Td>{x.status}</Td><Td>{x.last_error||'-'}</Td><Td>{fmt(x.updated_at)}</Td></tr>)}{!(props.whatsappLogs||[]).length&&<tr><td colSpan={5} className="p-4 text-slate-500">Belum ada pesan WhatsApp.</td></tr>}</tbody></table></div></Card>
  </div></Layout>
}
function Card({title,children}:{title:string,children:React.ReactNode}){return <section className="rounded-xl border border-slate-200 bg-white p-5"><h3 className="mb-4 text-lg font-bold">{title}</h3>{children}</section>}
function Label({title,children}:{title:string,children:React.ReactNode}){return <label className="block text-sm"><span className="text-slate-500">{title}</span>{children}</label>}
function Status({text,error}:{text?:string,error?:string}){return <div className="text-xs text-slate-500">{text?`Last test: ${text}`:''}{error?<span className="text-rose-400"> · {error}</span>:null}</div>}
function Th({children}:{children:React.ReactNode}){return <th className="p-2 text-left font-medium">{children}</th>}
function Td({children}:{children:React.ReactNode}){return <td className="p-2">{children}</td>}
function rupiah(v:any){return 'Rp'+Number(v||0).toLocaleString('id-ID')}
function fmt(v?:string){return v?new Date(v).toLocaleString('id-ID'):'-'}
