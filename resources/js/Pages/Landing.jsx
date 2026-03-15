import { Head, Link } from '@inertiajs/react';

const PROVINCE_STYLES = {
    batangas: { emoji: '🌋', bg: 'bg-purple-100 border-purple-300', icon: 'text-purple-600' },
    laguna: { emoji: '💧', bg: 'bg-blue-100 border-blue-300', icon: 'text-blue-600' },
    cavite: { emoji: '🏰', bg: 'bg-pink-100 border-pink-300', icon: 'text-pink-600' },
    rizal: { emoji: '⛰', bg: 'bg-amber-100 border-amber-300', icon: 'text-amber-700' },
    quezon: { emoji: '🌿', bg: 'bg-green-100 border-green-300', icon: 'text-green-600' },
    default: { emoji: '📍', bg: 'bg-slate-100 border-slate-300', icon: 'text-slate-600' },
};

const CATEGORY_STYLES = {
    Attractions: { emoji: '⭐', bg: 'bg-yellow-100 border-yellow-300', icon: 'text-yellow-600' },
    Adventure: { emoji: '🎒', bg: 'bg-blue-100 border-blue-300', icon: 'text-blue-600' },
    'Food & Dining': { emoji: '🍽', bg: 'bg-orange-100 border-orange-300', icon: 'text-orange-600' },
    Accommodation: { emoji: '🏛', bg: 'bg-purple-100 border-purple-300', icon: 'text-purple-600' },
    Shopping: { emoji: '❤️', bg: 'bg-rose-100 border-rose-300', icon: 'text-rose-600' },
    Transportation: { emoji: '🚌', bg: 'bg-cyan-100 border-cyan-300', icon: 'text-cyan-600' },
    default: { emoji: '📍', bg: 'bg-slate-100 border-slate-300', icon: 'text-slate-600' },
};

const ROLE_META = {
    Tourist: {
        icon: '🧳',
        desc: 'Discover activities, plan trips, and get personalized recommendations.',
        accent: 'text-emerald-700',
    },
    Provider: {
        icon: '🏪',
        desc: 'Publish listings, manage bookings, and improve performance visibility.',
        accent: 'text-amber-700',
    },
    'LGU Manager': {
        icon: '🏛',
        desc: 'Monitor regional tourism indicators and listing moderation workflows.',
        accent: 'text-sky-700',
    },
    Admin: {
        icon: '⚙',
        desc: 'Oversee platform governance, users, and system health operations.',
        accent: 'text-violet-700',
    },
};


export default function Landing({
    landingStats = {},
    landingProvinces = [],
    landingFeatured = null,
    landingCategories = [],
    landingRoles = [],
}) {
    const provinces = (landingProvinces ?? []).map((province) => ({
        name: province.name,
        style: PROVINCE_STYLES[(province.name || '').toLowerCase()] ?? PROVINCE_STYLES.default,
        count: `${Number(province.count || 0).toLocaleString()} spots`,
    }));

    const featureCards = (landingCategories ?? []).map((category) => ({
        title: category.name,
        desc: `${Number(category.count || 0).toLocaleString()} activities currently listed in this category.`,
        style: CATEGORY_STYLES[category.name] ?? CATEGORY_STYLES.default,
    }));

    const roles = (landingRoles ?? []).map((role) => ({
        name: role.name,
        count: Number(role.count || 0),
        ...(ROLE_META[role.name] ?? {
            icon: '👤',
            desc: 'Access a tailored dashboard experience.',
            accent: 'text-slate-700',
        }),
    }));

    return (
        <>
            <Head title="Doon | Smart CALABARZON Travel" />

            <div className="landing-shell min-h-screen text-slate-800">
                <div className="mx-auto max-w-7xl px-6 py-6 lg:px-8">
                    <header className="reveal flex items-center justify-between rounded-2xl border border-white/70 bg-white/75 px-5 py-4 backdrop-blur">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-400 text-lg text-white shadow-md shadow-emerald-200">
                                📍
                            </div>
                            <div>
                                <p className="text-2xl font-black tracking-tight text-slate-900">Doon</p>
                                <p className="text-[11px] uppercase tracking-[0.16em] text-slate-500">Smart Regional Tourism</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Link href={route('login')} className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700">
                                Login
                            </Link>
                            <Link href={route('register')} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-500">
                                Get Started
                            </Link>
                        </div>
                    </header>

                    <main className="grid items-center gap-10 py-12 lg:grid-cols-[1.1fr_0.9fr]">
                        <section className="reveal space-y-6">
                            <p className="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">
                                CALABARZON Tourism Intelligence
                            </p>
                            <h1 className="text-4xl font-black leading-tight text-slate-900 sm:text-5xl">
                                Discover the best of CALABARZON with a smarter tourism platform.
                            </h1>
                            <p className="max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg">
                                One destination hub for travelers, local businesses, and tourism offices. Plan better trips,
                                track activity trends, and coordinate regional growth through one clean experience.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Link href={route('register')} className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-500">
                                    Start Exploring
                                </Link>
                                <Link href={route('login')} className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:border-slate-400">
                                    I Have An Account
                                </Link>
                            </div>
                            <div className="data-grid pt-2">
                                <article className="metric-card">
                                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Active Tourists</p>
                                    <p className="mt-2 text-2xl font-black text-slate-900">{Number(landingStats.activeTourists || 0).toLocaleString()}</p>
                                </article>
                                <article className="metric-card">
                                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Regional Listings</p>
                                    <p className="mt-2 text-2xl font-black text-slate-900">{Number(landingStats.regionalListings || 0).toLocaleString()}</p>
                                </article>
                                <article className="metric-card">
                                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Upcoming Events</p>
                                    <p className="mt-2 text-2xl font-black text-slate-900">{Number(landingStats.upcomingEvents || 0).toLocaleString()}</p>
                                </article>
                                <article className="metric-card">
                                    <p className="text-xs uppercase tracking-[0.14em] text-slate-500">Featured Spots</p>
                                    <p className="mt-2 text-2xl font-black text-slate-900">{Number(landingStats.featuredSpots || 0).toLocaleString()}</p>
                                </article>
                            </div>
                        </section>

                        <section className="reveal reveal-delay-1">
                            <div className="glow-card p-5">
                                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Featured Destination</p>
                                <h2 className="mt-2 text-2xl font-black text-slate-900">{landingFeatured?.title ?? 'No featured destination yet'}</h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    {(landingFeatured?.municipality || landingFeatured?.province)
                                        ? `${landingFeatured?.municipality ?? ''}${landingFeatured?.municipality && landingFeatured?.province ? ', ' : ''}${landingFeatured?.province ?? ''}`
                                        : 'Destination data will appear here after activities are published.'}
                                </p>
                                <div className="mt-4 rounded-xl bg-gradient-to-br from-emerald-100 to-teal-100 p-6 text-center text-6xl">🌋</div>
                                <div className="mt-4 flex items-center justify-between text-sm">
                                    <span className="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">Live</span>
                                    <span className="font-semibold text-slate-700">
                                        {landingFeatured?.avg_rating ? `${landingFeatured.avg_rating} rating` : 'No rating yet'}
                                    </span>
                                </div>
                            </div>
                        </section>
                    </main>

                    <section className="reveal reveal-delay-1 rounded-2xl border border-slate-200 bg-white/85 p-5">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {provinces.map((province) => (
                                <article key={province.name} className="rounded-xl border-2 bg-white p-4 shadow-md transition hover:shadow-lg">
                                    <div className={`mb-2 inline-flex h-16 w-16 items-center justify-center rounded-lg border ${province.style.bg} text-3xl`}>
                                        {province.style.emoji}
                                    </div>
                                    <p className="mt-2 font-bold text-slate-900">{province.name}</p>
                                    <p className={`mt-1 text-xs font-semibold ${province.style.icon}`}>{province.count}</p>
                                </article>
                            ))}
                            {provinces.length === 0 && (
                                <article className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:col-span-2 lg:col-span-5">
                                    <p className="text-sm text-slate-600">No province records found yet.</p>
                                </article>
                            )}
                        </div>
                    </section>

                    <section className="py-12">
                        <div className="mb-7 text-center">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Platform Features</p>
                            <h2 className="mt-2 text-4xl font-black text-slate-900">Top Activity Categories</h2>
                            <p className="mx-auto mt-2 max-w-2xl text-slate-600">Categories below are generated directly from your current activity records.</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {featureCards.map((feature) => (
                                <article key={feature.title} className="glow-card card-lift rounded-xl border-2 p-5 transition hover:shadow-lg">
                                    <div className={`mb-3 inline-flex h-14 w-14 items-center justify-center rounded-lg border ${feature.style.bg} text-2xl`}>
                                        {feature.style.emoji}
                                    </div>
                                    <h3 className="text-lg font-extrabold text-slate-900">{feature.title}</h3>
                                    <p className="mt-1 text-sm leading-relaxed text-slate-600">{feature.desc}</p>
                                </article>
                            ))}
                            {featureCards.length === 0 && (
                                <article className="glow-card p-5 md:col-span-2 xl:col-span-3">
                                    <p className="text-sm text-slate-600">No category distribution available yet.</p>
                                </article>
                            )}
                        </div>
                    </section>

                    <section className="pb-10">
                        <div className="mb-6 text-center">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Choose Your Role</p>
                            <h2 className="mt-2 text-4xl font-black text-slate-900">Built for Everyone</h2>
                        </div>
                        <div className="list-stagger grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {roles.map((role) => (
                                <article key={role.name} className="glow-card p-4">
                                    <p className="text-3xl">{role.icon}</p>
                                    <p className={`mt-2 text-base font-bold ${role.accent}`}>{role.name}</p>
                                    <p className="mt-1 text-sm text-slate-600">{role.desc}</p>
                                    <p className="mt-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{role.count.toLocaleString()} accounts</p>
                                    <Link href={route('login')} className="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700">
                                        Sign In As {role.name}
                                    </Link>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="reveal rounded-3xl bg-gradient-to-r from-emerald-600 to-emerald-500 px-6 py-12 text-center text-white shadow-xl shadow-emerald-200">
                        <h2 className="text-4xl font-black">Start Your CALABARZON Journey</h2>
                        <p className="mx-auto mt-2 max-w-2xl text-emerald-50">Join tourists, providers, and LGUs already using Doon to build better travel outcomes across the region.</p>
                        <Link href={route('register')} className="mt-6 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-bold text-emerald-700 hover:bg-emerald-50">
                            Get Started Free
                        </Link>
                    </section>
                </div>
            </div>
        </>
    );
}
