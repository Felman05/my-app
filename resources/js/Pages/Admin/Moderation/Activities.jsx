import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusChip from '@/Components/dashboard/StatusChip';
import { Head, router, useForm } from '@inertiajs/react';

export default function ActivitiesModeration({ queue, filters }) {
    const { data, setData, processing, reset } = useForm({ notes: '' });
    const activeStatus = filters?.status || 'pending';
    const activeSearch = filters?.search || '';

    const approve = (id) => {
        router.post(route('admin.moderation.activities.approve', id), { notes: data.notes }, { onSuccess: () => reset() });
    };

    const reject = (id) => {
        router.post(route('admin.moderation.activities.reject', id), { notes: data.notes }, { onSuccess: () => reset() });
    };

    const switchStatus = (status) => {
        router.get(
            route('admin.moderation.activities.index'),
            { status, search: activeSearch || undefined },
            { preserveState: true, replace: true }
        );
    };

    const applySearch = (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const search = String(form.get('search') || '').trim();

        router.get(
            route('admin.moderation.activities.index'),
            { status: activeStatus, search: search || undefined },
            { preserveState: true, replace: true }
        );
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Moderation Queue</h2>}>
            <Head title="Activity Moderation" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <section className="hero-glass reveal rounded-2xl p-5 text-white shadow-xl">
                        <h3 className="text-2xl font-semibold">Activity Moderation Center</h3>
                        <p className="mt-1 text-sm text-slate-100">
                            Review listings by status, search quickly, and apply consistent moderation notes.
                        </p>
                    </section>

                    <section className="panel reveal">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusChip active={activeStatus === 'pending'} onClick={() => switchStatus('pending')}>Pending</StatusChip>
                            <StatusChip active={activeStatus === 'rejected'} onClick={() => switchStatus('rejected')}>Rejected</StatusChip>
                            <StatusChip active={activeStatus === 'approved'} onClick={() => switchStatus('approved')}>Approved</StatusChip>
                        </div>

                        <form className="mt-4 flex flex-col gap-3 sm:flex-row" onSubmit={applySearch}>
                            <input
                                type="search"
                                name="search"
                                defaultValue={activeSearch}
                                className="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                                placeholder="Search by title, provider, municipality"
                            />
                            <button
                                type="submit"
                                className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                            >
                                Apply Filters
                            </button>
                        </form>
                    </section>

                    <section className="panel reveal reveal-delay-1">
                        <label className="block text-sm font-medium text-slate-700">Moderation Notes</label>
                        <textarea
                            className="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Add note for approval/rejection logs"
                        />
                    </section>

                    <section className="panel reveal reveal-delay-2">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-slate-600">
                                        <th className="py-2 pe-3">Title</th>
                                        <th className="py-2 pe-3">Provider</th>
                                        <th className="py-2 pe-3">Municipality</th>
                                        <th className="py-2 pe-3">Status</th>
                                        <th className="py-2 pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {queue.data.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="py-8 text-center text-slate-500">
                                                No activities match the current filters.
                                            </td>
                                        </tr>
                                    )}
                                    {queue.data.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100">
                                            <td className="py-2 pe-3 font-semibold text-slate-800">{item.title}</td>
                                            <td className="py-2 pe-3 text-slate-600">{item.provider_name}</td>
                                            <td className="py-2 pe-3 text-slate-600">{item.municipality}</td>
                                            <td className="py-2 pe-3">
                                                <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold uppercase text-slate-700">
                                                    {item.status}
                                                </span>
                                            </td>
                                            <td className="py-2 pe-3">
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={processing}
                                                        onClick={() => approve(item.id)}
                                                        className="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={processing}
                                                        onClick={() => reject(item.id)}
                                                        className="rounded-full bg-rose-600 px-3 py-1 text-xs font-semibold text-white"
                                                    >
                                                        Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            {(queue.links || []).map((link, index) => (
                                <button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    disabled={!link.url || link.active}
                                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                    className={`rounded-md px-3 py-1.5 text-xs font-semibold ${
                                        link.active
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700 hover:border-slate-500'
                                    } disabled:cursor-not-allowed disabled:opacity-50`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
