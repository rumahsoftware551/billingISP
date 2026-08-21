import React, { type ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';

export const authInput =
    'mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100';

type AuthShellProps = {
    title: string;
    subtitle?: string;
    description?: string;
    icon?: ReactNode;
    children: ReactNode;
};

export function AuthShell({
    title,
    subtitle,
    description,
    icon,
    children,
}: AuthShellProps) {
    const page: any = usePage().props;
    const branding = page.branding || {};
    const primary = branding.primary_color || '#0f6cbd';
    const appName = branding.app_name || 'Jaringanku';

    return (
        <div
            className="min-h-screen bg-slate-50 text-slate-900"
            style={{ '--auth-primary': primary } as React.CSSProperties}
        >
            <Head title={`${title} Â· ${appName}`}>
                {branding.favicon_url && <link rel="icon" href={branding.favicon_url} />}
            </Head>

            <div className="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
                <section
                    className="hidden flex-col justify-between p-12 text-white lg:flex"
                    style={{
                        background: `linear-gradient(145deg, ${primary}, #10233f 72%)`,
                    }}
                >
                    <div>
                        {branding.logo_url ? (
                            <img
                                src={branding.logo_url}
                                alt={appName}
                                className="h-14 max-w-60 rounded-xl bg-white p-2 object-contain"
                            />
                        ) : (
                            <div className="text-3xl font-black tracking-tight">{appName}</div>
                        )}
                        <div className="mt-3 text-sm text-white/70">
                            ISP Billing & Network Management System
                        </div>
                    </div>

                    <div className="max-w-xl">
                        <div className="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10">
                            {icon || <ShieldCheck size={28} />}
                        </div>
                        <h1 className="text-4xl font-black leading-tight">
                            Operasional ISP dalam satu ruang kerja yang aman.
                        </h1>
                        <p className="mt-4 text-sm leading-7 text-white/70">
                            Akses pelanggan, billing, jaringan, mitra, inventory dan layanan sesuai role serta permission akun Anda.
                        </p>
                    </div>

                    <div className="text-xs text-white/50">
                        {branding.footer_text || 'Jaringanku ISP Management System'}
                    </div>
                </section>

                <main className="flex items-center justify-center p-5 sm:p-8 lg:p-12">
                    <div className="w-full max-w-md">
                        <div className="mb-7 lg:hidden">
                            {branding.logo_url ? (
                                <img
                                    src={branding.logo_url}
                                    alt={appName}
                                    className="h-12 max-w-56 object-contain"
                                />
                            ) : (
                                <div className="text-2xl font-black" style={{ color: primary }}>
                                    {appName}
                                </div>
                            )}
                        </div>

                        <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,.08)] sm:p-8">
                            <div className="mb-7">
                                <div
                                    className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50"
                                    style={{ color: primary }}
                                >
                                    {icon || <ShieldCheck size={24} />}
                                </div>
                                {subtitle && (
                                    <div className="mb-1 text-xs font-black uppercase tracking-[.15em] text-slate-400">
                                        {subtitle}
                                    </div>
                                )}
                                <h2 className="text-2xl font-black tracking-tight">{title}</h2>
                                {description && (
                                    <p className="mt-2 text-sm leading-6 text-slate-500">{description}</p>
                                )}
                            </div>

                            {children}

                            <Link
                                href="/access"
                                className="mt-6 flex items-center justify-center gap-2 text-sm font-bold"
                                style={{ color: primary }}
                            >
                                <ArrowLeft size={16} />
                                Pusat Akses Jaringanku
                            </Link>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}