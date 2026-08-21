import React from 'react';
import {Link} from '@inertiajs/react';
import {ArrowRight} from 'lucide-react';

type IconType = React.ComponentType<{size?:number; className?:string}>;

export function PageHeader({eyebrow,title,description,actions}:{eyebrow?:string;title:string;description?:string;actions?:React.ReactNode}) {
  return <header className="jk-page-header">
    <div className="min-w-0">
      {eyebrow&&<div className="jk-eyebrow">{eyebrow}</div>}
      <h1 className="jk-page-title">{title}</h1>
      {description&&<p className="jk-page-description">{description}</p>}
    </div>
    {actions&&<div className="flex flex-wrap gap-2">{actions}</div>}
  </header>;
}

export function Surface({title,subtitle,children,className=''}:{title?:string;subtitle?:string;children:React.ReactNode;className?:string}) {
  return <section className={`jk-surface ${className}`}>
    {(title||subtitle)&&<div className="jk-surface-head">
      {title&&<h2 className="jk-surface-title">{title}</h2>}
      {subtitle&&<p className="jk-surface-subtitle">{subtitle}</p>}
    </div>}
    <div className="jk-surface-body">{children}</div>
  </section>;
}

export function MetricCard({icon:Icon,label,value,hint,tone='blue'}:{icon:IconType;label:string;value:React.ReactNode;hint?:React.ReactNode;tone?:'blue'|'emerald'|'cyan'|'amber'|'rose'|'violet'}) {
  return <div className="jk-metric">
    <div className="min-w-0">
      <div className="jk-metric-label">{label}</div>
      <div className="jk-metric-value">{value}</div>
      {hint&&<div className="jk-metric-hint">{hint}</div>}
    </div>
    <div className={`jk-metric-icon ${tone}`}><Icon size={19}/></div>
  </div>;
}

export function QuickAction({href,title,description,icon:Icon}:{href:string;title:string;description:string;icon?:IconType}) {
  return <Link href={href} className="jk-quick-action">
    <div className="flex min-w-0 items-start gap-3">
      {Icon&&<div className="jk-quick-icon"><Icon size={17}/></div>}
      <div className="min-w-0">
        <div className="font-bold text-slate-800">{title}</div>
        <div className="mt-1 text-xs leading-5 text-slate-500">{description}</div>
      </div>
    </div>
    <ArrowRight size={15} className="mt-1 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[var(--jk-primary)]"/>
  </Link>;
}

export function EmptyState({text}:{text:string}) {
  return <div className="jk-empty">{text}</div>;
}
