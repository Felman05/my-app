import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyNote from '@/Components/dashboard/EmptyNote';
import DashboardQuickNav from '@/Components/dashboard/DashboardQuickNav';
import GeoActivityMap from '@/Components/dashboard/GeoActivityMap';
import MetricCard from '@/Components/dashboard/MetricCard';
import { calculatePercent, getTopLocationCounts } from '@/utils/dashboardStats';
import { Head, Link } from '@inertiajs/react';

export default function LguDashboard({ stats, topSpots, mapPoints }) {
    const totalTopSpotEngagement = (topSpots ?? []).reduce((sum, item) => sum + Number(item.engagement || 0), 0);
    const topMunicipalities = getTopLocationCounts(mapPoints, 'municipality');

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800">LGU Dashboard</h2>}
        >
            <Head title="LGU Dashboard" />

            <div className="py-10">
                <div className="list-stagger mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="hero-glass reveal rounded-3xl p-6 text-white shadow-xl">
                        <h3 className="text-3xl font-extrabold">Regional Tourism Intelligence</h3>
                        <p className="mt-1 text-sm text-sky-100">
                            Evidence-based monitoring for tourism offices: trends, approvals, and engagement maps.
                        </p>
                    </section>

                    <DashboardQuickNav
                        links={[
                            { label: 'Overview', href: route('lgu.dashboard') },
                            { label: 'Profile', href: route('profile.edit') },
                        ]}
                    />

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MetricCard title="Monthly Visitor Logs" value={stats?.monthlyVisitors ?? 0} />
                        <MetricCard title="Unique Tourists" value={stats?.uniqueTourists ?? 0} />
                        <MetricCard title="Pending Approvals" value={stats?.pendingApprovals ?? 0} />
                        <MetricCard title="Feedback Count" value={stats?.feedbackCount ?? 0} />
                    </section>

                    <section className="grid gap-5 lg:grid-cols-3">
                        <GeoActivityMap
                            className="reveal reveal-delay-1 lg:col-span-2"
                            title="Province Activity Map"
                            subtitle="Monitor municipality-level listing density and moderation state."
                            initialPoints={mapPoints}
                            defaultStatus="approved"
                        />

                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Top Engaged Spots</h4>
                            <div className="mt-3 space-y-3">
                                {(topSpots ?? []).length === 0 && <EmptyNote text="No engagement trend data yet." />}
                                {(topSpots ?? []).map((spot) => (
                                    <div key={spot.title} className="rounded-xl bg-slate-100 p-3">
                                        <p className="text-sm font-semibold text-slate-800">{spot.title}</p>
                                        <p className="text-xs text-slate-600">Engagement: {spot.engagement}</p>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </section>

                    <section className="grid gap-5 lg:grid-cols-2">
                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Monitoring Lens</h4>
                            <div className="mt-3 data-grid">
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Approval Pressure</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{stats?.pendingApprovals ?? 0}</p>
                                </div>
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Engagement Volume</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{totalTopSpotEngagement}</p>
                                </div>
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Map Saturation</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{(mapPoints ?? []).length} points</p>
                                </div>
                                <div className="stat-chip">
                                    <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Visitor Diversity</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900">{stats?.uniqueTourists ?? 0}</p>
                                </div>
                            </div>
                        </article>

                        <article className="panel reveal reveal-delay-3">
                            <h4 className="text-lg font-semibold text-slate-800">Territory Concentration</h4>
                            <div className="mt-3 space-y-3">
                                {topMunicipalities.length === 0 && <EmptyNote text="No municipality concentration data yet." />}
                                {topMunicipalities.map(([name, count]) => (
                                    <div key={name}>
                                        <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                            <span className="font-semibold text-slate-800">{name}</span>
                                            <span>{count} points</span>
                                        </div>
                                        <div className="status-bar">
                                            <span style={{ width: `${calculatePercent(count, (mapPoints ?? []).length)}%` }} />
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
