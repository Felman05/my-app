import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function TouristActivitiesIndex({ activities }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Explore Activities</h2>}
        >
            <Head title="Explore Activities" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-5">
                        <p className="text-sm text-slate-600">
                            Browse approved activities from local providers across CALABARZON.
                        </p>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {activities.data.length === 0 && (
                            <div className="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                                No approved activities yet. Check back soon.
                            </div>
                        )}

                        {activities.data.map((activity) => (
                            <article key={activity.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="text-xs font-semibold uppercase tracking-wide text-teal-700">
                                    {activity.category?.name ?? 'Uncategorized'}
                                </p>
                                <h3 className="mt-2 text-lg font-semibold text-slate-900">{activity.title}</h3>
                                <p className="mt-2 line-clamp-3 text-sm text-slate-600">{activity.description || 'No description yet.'}</p>
                                <p className="mt-4 text-xs text-slate-500">
                                    {activity.municipality?.name ? `${activity.municipality.name}, ` : ''}
                                    {activity.province?.name ?? 'Unknown location'}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
