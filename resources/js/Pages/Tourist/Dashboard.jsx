import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyNote from '@/Components/dashboard/EmptyNote';
import DashboardQuickNav from '@/Components/dashboard/DashboardQuickNav';
import GeoActivityMap from '@/Components/dashboard/GeoActivityMap';
import MetricCard from '@/Components/dashboard/MetricCard';
import { calculatePercent, getTopLocationCounts } from '@/utils/dashboardStats';
import { Head, Link, usePage } from '@inertiajs/react';

export default function TouristDashboard({ stats, recommendations, weather, recentLogs, mapPoints }) {
    const { auth } = usePage().props;
    const topProvinces = getTopLocationCounts(mapPoints, 'province');

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Tourist Dashboard</h2>}
        >
            <Head title="Tourist Dashboard" />

            <div className="py-10">
                <div className="list-stagger mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="hero-glass reveal rounded-3xl p-6 text-white shadow-xl">
                        <p className="text-sm uppercase tracking-[0.16em] text-cyan-100">Smart Travel Command</p>
                        <h3 className="mt-2 text-3xl font-extrabold">Welcome, {auth.user.name}</h3>
                        <p className="mt-2 max-w-3xl text-sm text-cyan-50">
                            Track what matters in one place: recommendations, weather snapshots, and real-time destination
                            activity across CALABARZON.
                        </p>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <Link
                                href={route('tourist.activities.index')}
                                className="inline-flex rounded-full bg-white px-4 py-2 text-sm font-semibold text-teal-700 transition hover:scale-[1.02] hover:bg-teal-50"
                            >
                                Explore Activities
                            </Link>
                        </div>
                    </section>

                    <DashboardQuickNav
                        links={[
                            { label: 'Overview', href: route('tourist.dashboard') },
                            { label: 'Activities', href: route('tourist.activities.index') },
                            { label: 'Profile', href: route('profile.edit') },
                        ]}
                    />

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MetricCard title="Saved Activities" value={stats?.savedActivities ?? 0} />
                        <MetricCard title="Itineraries" value={stats?.itineraries ?? 0} />
                        <MetricCard title="Recommendations" value={stats?.recommendations ?? 0} />
                        <MetricCard title="Recent Actions" value={stats?.recentActions ?? 0} />
                    </section>

                    <section className="grid gap-5 lg:grid-cols-3">
                        <GeoActivityMap
                            className="reveal reveal-delay-1 lg:col-span-2"
                            title="CALABARZON Live Map"
                            subtitle="Map points are fetched from your live activity API."
                            initialPoints={mapPoints}
                            defaultStatus="approved"
                        />

                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Weather Feed</h4>
                            <div className="mt-3 space-y-3">
                                {(weather ?? []).length === 0 && <EmptyNote text="No weather cache available yet." />}
                                {(weather ?? []).map((item) => (
                                    <div key={`${item.municipality}-${item.fetched_at}`} className="rounded-xl bg-slate-100 p-3">
                                        <p className="text-sm font-semibold text-slate-800">{item.municipality}</p>
                                        <p className="text-sm text-slate-600">
                                            {item.temperature ?? '-'} C • {item.weather_condition ?? 'Unknown'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </section>

                    <section className="grid gap-5 lg:grid-cols-2">
                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Recommendation Engine Output</h4>
                            <div className="mt-4 space-y-3">
                                {(recommendations ?? []).length === 0 && <EmptyNote text="No recommendations generated yet." />}
                                {(recommendations ?? []).map((item) => (
                                    <div key={`${item.id}-${item.created_at}`} className="rounded-xl border border-slate-200 p-3">
                                        <p className="font-semibold text-slate-800">{item.title}</p>
                                        <p className="text-sm text-slate-600">{item.category}</p>
                                        <p className="mt-1 text-xs font-semibold text-teal-700">{item.reason ?? 'Profile match'}</p>
                                    </div>
                                ))}
                            </div>
                        </article>

                        <article className="panel reveal reveal-delay-3">
                            <h4 className="text-lg font-semibold text-slate-800">Recent Activity Timeline</h4>
                            <div className="mt-4 space-y-3">
                                {(recentLogs ?? []).length === 0 && <EmptyNote text="No interactions recorded yet." />}
                                {(recentLogs ?? []).map((log) => (
                                    <div key={`${log.activity_title}-${log.created_at}`} className="flex items-start gap-3 rounded-xl bg-slate-100 p-3">
                                        <span className="rounded-md bg-teal-700 px-2 py-1 text-xs font-semibold uppercase text-white">
                                            {log.action}
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold text-slate-800">{log.activity_title}</p>
                                            <p className="text-xs text-slate-500">{log.created_at}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </section>

                    <section className="grid gap-5 lg:grid-cols-2">
                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Travel Signals</h4>
                            <div className="mt-3 data-grid">
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Recommendation Rate</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{(stats?.recommendations ?? 0).toLocaleString()} total</p>
                                </div>
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Discovery Pulse</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{(mapPoints ?? []).length} geo points</p>
                                </div>
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Interaction Tempo</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{stats?.recentActions ?? 0} recent logs</p>
                                </div>
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Planning Depth</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{stats?.itineraries ?? 0} itineraries</p>
                                </div>
                            </div>
                        </article>

                        <article className="panel reveal reveal-delay-3">
                            <h4 className="text-lg font-semibold text-slate-800">Province Activity Snapshot</h4>
                            <div className="mt-3 space-y-3">
                                {topProvinces.length === 0 && <EmptyNote text="No province activity data available yet." />}
                                {topProvinces.map(([province, total]) => (
                                    <div key={province}>
                                        <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                            <span className="font-semibold text-slate-800">{province}</span>
                                            <span>{total} points</span>
                                        </div>
                                        <div className="status-bar">
                                            <span style={{ width: `${calculatePercent(total, (mapPoints ?? []).length)}%` }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
