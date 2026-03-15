import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: 'tourist',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <div className="mb-6">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-orange-300">Create Account</p>
                <h1 className="mt-1 text-2xl font-bold text-white">Join Doon</h1>
                <p className="mt-1 text-sm text-slate-300">
                    Sign up as a tourist, provider, LGU manager, or admin account.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="name" className="block text-sm font-medium text-slate-200">
                        Full Name
                    </label>

                    <input
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        autoComplete="name"
                        autoFocus
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />

                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-slate-200">
                        Email Address
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <label htmlFor="role" className="block text-sm font-medium text-slate-200">
                        Role
                    </label>

                    <select
                        id="role"
                        name="role"
                        value={data.role}
                        className="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        onChange={(e) => setData('role', e.target.value)}
                        required
                    >
                        <option value="tourist">Tourist / End User</option>
                        <option value="provider">Business Owner / Activity Provider</option>
                        <option value="lgu">LGU / Regional Manager</option>
                        <option value="admin">Platform Admin</option>
                    </select>

                    <InputError message={errors.role} className="mt-2" />
                </div>

                <div>
                    <label htmlFor="password" className="block text-sm font-medium text-slate-200">
                        Password
                    </label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-slate-200">
                        Confirm Password
                    </label>

                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full rounded-xl border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="flex items-center justify-between gap-3 pt-2">
                    <Link href={route('login')} className="text-sm text-slate-300 underline transition hover:text-white">
                        Already registered?
                    </Link>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Create Account
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
