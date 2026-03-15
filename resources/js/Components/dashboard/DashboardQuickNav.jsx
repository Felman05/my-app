import { Link } from '@inertiajs/react';

export default function DashboardQuickNav({ links }) {
    return (
        <section className="panel reveal reveal-delay-1">
            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Quick Navigation</p>
            <div className="mt-3 flex flex-wrap gap-2">
                {links.map((link) => (
                    <Link
                        key={link.href}
                        href={link.href}
                        className="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-500"
                    >
                        {link.label}
                    </Link>
                ))}
            </div>
        </section>
    );
}
