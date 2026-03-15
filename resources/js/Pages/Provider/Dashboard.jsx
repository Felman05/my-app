import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyNote from '@/Components/dashboard/EmptyNote';
import DashboardQuickNav from '@/Components/dashboard/DashboardQuickNav';
import GeoActivityMap from '@/Components/dashboard/GeoActivityMap';
import MetricCard from '@/Components/dashboard/MetricCard';
import { calculatePercent, getTopLocationCounts } from '@/utils/dashboardStats';
import { Head, Link } from '@inertiajs/react';

export default function ProviderDashboard({ stats, submissions, upcomingEvents, mapPoints }) {
    const total = Math.max(1, stats?.totalListings ?? 0);
    const approvedPct = Math.round(((stats?.approvedListings ?? 0) / total) * 100);
    const pendingPct = Math.round(((stats?.pendingListings ?? 0) / total) * 100);

    const topCategories = getTopLocationCounts(submissions, 'category', 4, 'Uncategorized');

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Provider Dashboard</h2>}
        >
            <Head title="Provider Dashboard" />

            <div className="py-10">
                <div className="list-stagger mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="hero-glass reveal rounded-3xl p-6 text-white shadow-xl">
                        <h3 className="text-3xl font-extrabold">Provider Operations</h3>
                        <p className="mt-1 text-sm text-emerald-100">
                            Live listing performance, submission tracking, and event pipeline.
                        </p>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <Link
                                href={route('provider.activities.create')}
                                className="inline-flex rounded-full bg-white px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:scale-[1.02] hover:bg-emerald-50"
                            >
                                Submit New Activity
                            </Link>
                            <Link
                                href={route('provider.activities.index')}
                                className="inline-flex rounded-full border border-emerald-200/70 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                            >
                                Manage Listings
                            </Link>
                        </div>
                    </section>

                    <DashboardQuickNav
                        links={[
                            { label: 'Overview', href: route('provider.dashboard') },
                            { label: 'My Listings', href: route('provider.activities.index') },
                            { label: 'Submit Listing', href: route('provider.activities.create') },
                            { label: 'Profile', href: route('profile.edit') },
                        ]}
                    />

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <MetricCard title="Total Listings" value={stats?.totalListings ?? 0} />
                        <MetricCard title="Approved" value={stats?.approvedListings ?? 0} />
                        <MetricCard title="Pending" value={stats?.pendingListings ?? 0} />
                        <MetricCard title="Views" value={stats?.totalViews ?? 0} />
                    </section>

                    <section className="grid gap-5 lg:grid-cols-3">
                        <GeoActivityMap
                            className="reveal reveal-delay-1 lg:col-span-2"
                            title="Listing Coverage Map"
                            subtitle="Track approved, pending, and rejected map visibility in real time."
                            initialPoints={mapPoints}
                            defaultStatus="approved"
                        />

                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Upcoming Events</h4>
                            <div className="mt-3 space-y-3">
                                {(upcomingEvents ?? []).length === 0 && <EmptyNote text="No upcoming events in pipeline." />}
                                {(upcomingEvents ?? []).map((eventItem) => (
                                    <div key={eventItem.id} className="rounded-xl bg-slate-100 p-3">
                                        <p className="text-sm font-semibold text-slate-800">{eventItem.name}</p>
                                        <p className="text-xs text-slate-600">{eventItem.status} • {eventItem.start_datetime}</p>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </section>

                    <section className="panel reveal reveal-delay-3">
                        <h4 className="text-lg font-semibold text-slate-800">Recent Submission Status</h4>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-slate-600">
                                        <th className="py-2 pe-3">Listing</th>
                                        <th className="py-2 pe-3">Category</th>
                                        <th className="py-2 pe-3">Municipality</th>
                                        <th className="py-2 pe-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(submissions ?? []).length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="py-4 text-slate-500">No submissions found.</td>
                                        </tr>
                                    )}
                                    {(submissions ?? []).map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="py-2 pe-3 font-semibold text-slate-800">{item.title}</td>
                                            <td className="py-2 pe-3 text-slate-600">{item.category || '-'}</td>
                                            <td className="py-2 pe-3 text-slate-600">{item.municipality || '-'}</td>
                                            <td className="py-2 pe-3">
                                                <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold uppercase text-slate-700">
                                                    {item.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="grid gap-5 lg:grid-cols-2">
                        <article className="panel reveal reveal-delay-2">
                            <h4 className="text-lg font-semibold text-slate-800">Operations Health</h4>
                            <div className="mt-3 space-y-4">
                                <div>
                                    <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                        <span className="font-semibold text-slate-800">Approved Listings</span>
                                        <span>{approvedPct}%</span>
                                    </div>
                                    <div className="status-bar"><span style={{ width: `${approvedPct}%` }} /></div>
                                </div>
                                <div>
                                    <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                        <span className="font-semibold text-slate-800">Pending Listings</span>
                                        <span>{pendingPct}%</span>
                                    </div>
                                    <div className="status-bar"><span style={{ width: `${pendingPct}%` }} /></div>
                                </div>
                                <div className="data-grid">
                                    <div className="stat-chip">
                                        <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Views / Listing</p>
                                        <p className="mt-1 text-lg font-bold text-slate-900">{Math.round((stats?.totalViews ?? 0) / total)}</p>
                                    </div>
                                    <div className="stat-chip">
                                        <p className="text-xs uppercase tracking-[0.12em] text-slate-500">Live Map Pins</p>
                                        <p className="mt-1 text-lg font-bold text-slate-900">{(mapPoints ?? []).length}</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article className="panel reveal reveal-delay-3">
                            <h4 className="text-lg font-semibold text-slate-800">Category Mix</h4>
                            <div className="mt-3 space-y-3">
                                {topCategories.length === 0 && <EmptyNote text="No category distribution available yet." />}
                                {topCategories.map(([category, count]) => (
                                    <div key={category}>
                                        <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                            <span className="font-semibold text-slate-800">{category}</span>
                                            <span>{count} listings</span>
                                        </div>
                                        <div className="status-bar">
                                            <span style={{ width: `${calculatePercent(count, (submissions ?? []).length)}%` }} />
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
