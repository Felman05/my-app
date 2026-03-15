import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ProviderActivitiesCreate({ categories, provinces, municipalities }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        category_id: '',
        province_id: '',
        municipality_id: '',
        address: '',
        latitude: '',
        longitude: '',
        price: '',
        starts_at: '',
        ends_at: '',
        is_featured: false,
    });

    const filteredMunicipalities = municipalities.filter(
        (municipality) => String(municipality.province_id) === String(data.province_id),
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('provider.activities.store'));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Submit Activity Listing</h2>}
        >
            <Head title="Submit Activity" />

            <div className="py-10">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div>
                            <label className="block text-sm font-medium text-slate-700">Title</label>
                            <input
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                required
                            />
                            <InputError message={errors.title} className="mt-2" />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700">Description</label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                rows={4}
                            />
                            <InputError message={errors.description} className="mt-2" />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Category</label>
                                <select
                                    value={data.category_id}
                                    onChange={(e) => setData('category_id', e.target.value)}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                    required
                                >
                                    <option value="">Select category</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category_id} className="mt-2" />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700">Province</label>
                                <select
                                    value={data.province_id}
                                    onChange={(e) => {
                                        setData('province_id', e.target.value);
                                        setData('municipality_id', '');
                                    }}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                    required
                                >
                                    <option value="">Select province</option>
                                    {provinces.map((province) => (
                                        <option key={province.id} value={province.id}>
                                            {province.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.province_id} className="mt-2" />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Municipality</label>
                                <select
                                    value={data.municipality_id}
                                    onChange={(e) => setData('municipality_id', e.target.value)}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                >
                                    <option value="">Select municipality</option>
                                    {filteredMunicipalities.map((municipality) => (
                                        <option key={municipality.id} value={municipality.id}>
                                            {municipality.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.municipality_id} className="mt-2" />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700">Address</label>
                                <input
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                />
                                <InputError message={errors.address} className="mt-2" />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Price (PHP)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                />
                                <InputError message={errors.price} className="mt-2" />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700">Latitude</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={data.latitude}
                                        onChange={(e) => setData('latitude', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                    />
                                    <InputError message={errors.latitude} className="mt-2" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700">Longitude</label>
                                    <input
                                        type="number"
                                        step="0.0000001"
                                        value={data.longitude}
                                        onChange={(e) => setData('longitude', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                    />
                                    <InputError message={errors.longitude} className="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Starts At</label>
                                <input
                                    type="datetime-local"
                                    value={data.starts_at}
                                    onChange={(e) => setData('starts_at', e.target.value)}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                />
                                <InputError message={errors.starts_at} className="mt-2" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700">Ends At</label>
                                <input
                                    type="datetime-local"
                                    value={data.ends_at}
                                    onChange={(e) => setData('ends_at', e.target.value)}
                                    className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"
                                />
                                <InputError message={errors.ends_at} className="mt-2" />
                            </div>
                        </div>

                        <label className="inline-flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={data.is_featured}
                                onChange={(e) => setData('is_featured', e.target.checked)}
                                className="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                            />
                            <span className="text-sm text-slate-700">Request featured placement</span>
                        </label>

                        <div className="pt-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex rounded-full bg-teal-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Submit for Approval
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
