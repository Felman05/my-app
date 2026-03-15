import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function ProviderActivitiesIndex({ activities }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">My Activity Listings</h2>}
        >
            <Head title="My Activities" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex items-center justify-between gap-3">
                        <p className="text-sm text-slate-600">Track submitted activities and their review status.</p>
                        <Link
                            href={route('provider.activities.create')}
                            className="inline-flex rounded-full bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                        >
                            Add Activity
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Title</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Category</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Location</th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {activities.data.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-6 text-center text-slate-500">
                                            No activities yet. Submit your first listing.
                                        </td>
                                    </tr>
                                )}

                                {activities.data.map((activity) => (
                                    <tr key={activity.id}>
                                        <td className="px-4 py-3 font-medium text-slate-900">{activity.title}</td>
                                        <td className="px-4 py-3 text-slate-700">{activity.category?.name ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {activity.municipality?.name ? `${activity.municipality.name}, ` : ''}
                                            {activity.province?.name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                                {activity.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
