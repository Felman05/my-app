import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyNote from '@/Components/dashboard/EmptyNote';
import DashboardQuickNav from '@/Components/dashboard/DashboardQuickNav';
import GeoActivityMap from '@/Components/dashboard/GeoActivityMap';
import MetricCard from '@/Components/dashboard/MetricCard';
import { Head, Link } from '@inertiajs/react';

export default function AdminDashboard({ stats, pendingQueue, roleMix, mapPoints }) {
    const userTotal = Math.max(1, stats?.users ?? 0);
    const providerShare = Math.round(((stats?.providers ?? 0) / userTotal) * 100);
    const pendingTotal = (pendingQueue ?? []).length;
    const roleTotal = (roleMix ?? []).reduce((sum, item) => sum + Number(item.total || 0), 0);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Admin Dashboard</h2>}
        >
            <Head title="Admin Dashboard" />

            <div className="py-10">
                <div className="list-stagger mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="hero-glass reveal rounded-3xl p-6 text-white shadow-xl">
                        <h3 className="text-3xl font-extrabold">Platform Governance</h3>
                        <p className="mt-1 text-sm text-slate-200">
                            Real-time moderation and health indicators across users, providers, and listing operations.
                        </p>
                        <div className="mt-4">
                            <Link
                                href={route('admin.moderation.activities.index')}
                                className="inline-flex rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-800 transition hover:bg-slate-100"
                            >
                                Open Moderation Queue
                            </Link>
                        </div>
                    </section>

                    <DashboardQuickNav
                        links={[
                            { label: 'Overview', href: route('admin.dashboard') },
                            { label: 'Moderation Queue', href: route('admin.moderation.activities.index') },
                            { label: 'Profile', href: route('profile.edit') },
                        ]}
                    />

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MetricCard title="Users" value={stats?.users ?? 0} />
                        <MetricCard title="Providers" value={stats?.providers ?? 0} />
                        <MetricCard title="Activities" value={stats?.activities ?? 0} />
                        <MetricCard title="Events" value={stats?.events ?? 0} />
                    </section>

                    <section className="grid gap-5 lg:grid-cols-3">
                        <GeoActivityMap
                            className="reveal reveal-delay-1 lg:col-span-2"
                            title="Platform Coverage Map"
                            subtitle="See publication distribution and moderation pressure by status."
                            initialPoints={mapPoints}
                            defaultStatus="pending"
                        />

                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Role Distribution</h4>
                            <div className="mt-3 space-y-3">
                                {(roleMix ?? []).length === 0 && <EmptyNote text="No role distribution data found." />}
                                {(roleMix ?? []).map((role) => (
                                    <div key={role.role} className="rounded-xl bg-slate-100 p-3">
                                        <p className="text-sm font-semibold text-slate-800">{role.role}</p>
                                        <p className="text-xs text-slate-600">{role.total} users</p>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </section>

                    <section className="panel reveal reveal-delay-3">
                        <h4 className="text-lg font-semibold text-slate-800">Pending Moderation Queue</h4>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-slate-600">
                                        <th className="py-2 pe-3">Item</th>
                                        <th className="py-2 pe-3">Type</th>
                                        <th className="py-2 pe-3">Owner</th>
                                        <th className="py-2 pe-3">Submitted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(pendingQueue ?? []).length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="py-4 text-slate-500">No pending items.</td>
                                        </tr>
                                    )}
                                    {(pendingQueue ?? []).map((item) => (
                                        <tr key={`${item.item_type}-${item.id}`} className="border-b border-slate-100">
                                            <td className="py-2 pe-3 font-semibold text-slate-800">{item.item_name}</td>
                                            <td className="py-2 pe-3 text-slate-600">{item.item_type}</td>
                                            <td className="py-2 pe-3 text-slate-600">{item.owner_name}</td>
                                            <td className="py-2 pe-3 text-slate-500">{item.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="grid gap-5 lg:grid-cols-2">
                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Platform Health</h4>
                            <div className="mt-3 space-y-4">
                                <div>
                                    <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                        <span className="font-semibold text-slate-800">Provider Share</span>
                                        <span>{providerShare}%</span>
                                    </div>
                                    <div className="status-bar"><span style={{ width: `${providerShare}%` }} /></div>
                                </div>
                                <div className="data-grid">
                                    <div className="stat-chip">
                                        <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Pending Queue</p>
                                        <p className="mt-1 text-lg font-bold text-slate-900">{pendingTotal}</p>
                                    </div>
                                    <div className="stat-chip">
                                        <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Role-Mapped Users</p>
                                        <p className="mt-1 text-lg font-bold text-slate-900">{roleTotal}</p>
                                    </div>
                                    <div className="stat-chip">
                                        <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Coverage Pins</p>
                                        <p className="mt-1 text-lg font-bold text-slate-900">{(mapPoints ?? []).length}</p>
                                    </div>
                                    <div className="stat-chip">
                                        <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Activity Density</p>
                                        <p className="mt-1 text-lg font-bold text-slate-900">{Math.round((stats?.activities ?? 0) / Math.max(1, stats?.providers ?? 1))} / provider</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article className="panel reveal reveal-delay-3">
                            <h4 className="text-lg font-semibold text-slate-800">Moderation Pressure</h4>
                            <div className="mt-3 space-y-3">
                                {(pendingQueue ?? []).slice(0, 5).map((item) => (
                                    <div key={`pressure-${item.id}`} className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2">
                                        <p className="text-sm font-semibold text-slate-800">{item.item_name}</p>
                                        <p className="text-xs text-slate-600">{item.owner_name} • {item.item_type}</p>
                                    </div>
                                ))}
                                {(pendingQueue ?? []).length === 0 && <EmptyNote text="No active moderation pressure currently." />}
                            </div>
                        </article>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
