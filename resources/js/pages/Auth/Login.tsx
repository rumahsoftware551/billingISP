import React,{FormEvent} from 'react';
import {useForm,usePage} from '@inertiajs/react';
import {ArrowRight,LockKeyhole,Network} from 'lucide-react';
import {AuthShell,authInput} from '../../components/AuthShell';

export default function Login(){
 const page:any=usePage().props;const b=page.branding||{};const f=useForm({email:'',password:''});
 const submit=(e:FormEvent)=>{e.preventDefault();f.post('/login')};
 return <AuthShell title="Masuk ke Admin ISP" description="Gunakan akun administrator atau user internal sesuai role yang dibuat melalui User Management." icon={<Network size={28}/>}>
  <form onSubmit={submit} className="space-y-4">
    <label className="block text-sm font-semibold text-slate-700">Email<input autoFocus autoComplete="username" type="email" value={f.data.email} onChange={e=>f.setData('email',e.target.value)} placeholder="nama@ispanda.com" className={authInput}/></label>
    {f.errors.email&&<div className="rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{f.errors.email}</div>}
    <label className="block text-sm font-semibold text-slate-700">Password<div className="relative"><LockKeyhole className="absolute left-3 top-[1.15rem] text-slate-400" size={18}/><input autoComplete="current-password" type="password" value={f.data.password} onChange={e=>f.setData('password',e.target.value)} className={`${authInput} pl-10`}/></div></label>
    <button disabled={f.processing} className="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 font-bold text-white transition hover:brightness-95 disabled:opacity-50" style={{background:b.primary_color||'#0f6cbd'}}>Masuk <ArrowRight size={18}/></button>
  </form>
 </AuthShell>;
}
