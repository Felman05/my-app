import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const user = page.props?.auth?.user;
    const role = user?.role;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const desktopLinks = buildNavLinks(role);
    const roleTheme = getRoleTheme(role);

    if (!user) {
        return (
            <div className="flex min-h-screen items-center justify-center px-4">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <h2 className="text-2xl font-semibold text-slate-900">Session Expired</h2>
                    <p className="mt-2 text-sm text-slate-600">Please sign in again to continue.</p>
                    <Link
                        href={route('login')}
                        className="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                    >
                        Go To Login
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-[#f7faf9] text-slate-800">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 border-r border-slate-200 bg-white lg:flex lg:flex-col">
                <div className="border-b border-slate-100 p-5">
                    <Link href={route('dashboard')} className="flex items-center gap-3">
                        <div className="rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-400 p-2 shadow-md shadow-emerald-200">
                            <ApplicationLogo className="h-7 w-7 fill-current text-white" />
                        </div>
                        <div>
                            <p className="text-lg font-black tracking-tight text-slate-900">Doon</p>
                            <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500">Smart Tourism</p>
                        </div>
                    </Link>

                    <div className="mt-5 rounded-xl border border-slate-200 bg-emerald-50 p-3">
                        <p className="text-sm font-semibold text-slate-900">{user.name}</p>
                        <p className="text-[11px] uppercase tracking-[0.12em]" style={{ color: roleTheme.color }}>{roleTheme.label}</p>
                    </div>
                </div>

                <nav className="flex-1 space-y-1 p-3 list-stagger">
                    {desktopLinks.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={`nav-item flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition ${
                                item.active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                            }`}
                            style={item.active ? { boxShadow: `inset 2px 0 0 ${roleTheme.color}` } : undefined}
                        >
                            <span className="text-base">{item.icon}</span>
                            <span>{item.name}</span>
                        </Link>
                    ))}
                </nav>

                <div className="border-t border-slate-100 p-3">
                    <Link href={route('profile.edit')} className="mb-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        <span>👤</span>
                        <span>Profile</span>
                    </Link>
                    <Link method="post" href={route('logout')} as="button" className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-rose-500 hover:bg-rose-50 hover:text-rose-600">
                        <span>↩</span>
                        <span>Log Out</span>
                    </Link>
                </div>
            </aside>

            <div className="lg:ml-64">
                <nav className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div className="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <button
                                onClick={() => setShowingNavigationDropdown((previousState) => !previousState)}
                                className="inline-flex items-center justify-center rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus:outline-none lg:hidden"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path className={showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <div>{header || <h2 className="text-lg font-semibold text-slate-900">Dashboard</h2>}</div>
                        </div>

                        <div className="hidden items-center gap-3 sm:flex">
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <span className="inline-flex rounded-md">
                                        <button type="button" className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 focus:outline-none">
                                            {user.name}
                                            <svg className="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </Dropdown.Trigger>

                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                    <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </div>

                    <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' border-t border-slate-200 bg-white lg:hidden'}>
                        <div className="space-y-1 p-2">
                            {desktopLinks.map((item) => (
                                <ResponsiveNavLink key={item.name} href={item.href} active={item.active} className="border-l-0 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900">
                                    {item.icon} {item.name}
                                </ResponsiveNavLink>
                            ))}
                            <ResponsiveNavLink href={route('profile.edit')} className="border-l-0 rounded-md text-slate-700 hover:bg-slate-100 hover:text-slate-900">👤 Profile</ResponsiveNavLink>
                            <ResponsiveNavLink method="post" href={route('logout')} as="button" className="border-l-0 rounded-md text-rose-500 hover:bg-rose-50 hover:text-rose-600">↩ Log Out</ResponsiveNavLink>
                        </div>
                    </div>
                </nav>

                <main className="page-enter">{children}</main>
            </div>
        </div>
    );
}

function buildNavLinks(role) {
    const links = [
        {
            name: 'Home',
            href: route('dashboard'),
            active: route().current('dashboard'),
            icon: '🏠',
        },
    ];

    if (role === 'tourist') {
        links.push(
            {
                name: 'Overview',
                href: route('tourist.dashboard'),
                active: route().current('tourist.dashboard'),
                icon: '🧳',
            },
            {
                name: 'Activities',
                href: route('tourist.activities.index'),
                active: route().current('tourist.activities.index'),
                icon: '🗺️',
            },
        );
    }

    if (role === 'provider') {
        links.push(
            {
                name: 'Overview',
                href: route('provider.dashboard'),
                active: route().current('provider.dashboard'),
                icon: '🏪',
            },
            {
                name: 'My Listings',
                href: route('provider.activities.index'),
                active: route().current('provider.activities.index'),
                icon: '📋',
            },
            {
                name: 'Submit Listing',
                href: route('provider.activities.create'),
                active: route().current('provider.activities.create'),
                icon: '➕',
            },
        );
    }

    if (role === 'lgu') {
        links.push({
            name: 'Overview',
            href: route('lgu.dashboard'),
            active: route().current('lgu.dashboard'),
            icon: '🏛️',
        });
    }

    if (role === 'admin') {
        links.push(
            {
                name: 'Overview',
                href: route('admin.dashboard'),
                active: route().current('admin.dashboard'),
                icon: '⚙️',
            },
            {
                name: 'Moderation',
                href: route('admin.moderation.activities.index'),
                active: route().current('admin.moderation.activities.index'),
                icon: '✅',
            },
        );
    }

    return links;
}

function getRoleTheme(role) {
    const themes = {
        tourist: { color: '#0ABFA3', label: 'Tourist', icon: '🧳' },
        provider: { color: '#FFB347', label: 'Provider', icon: '🏪' },
        lgu: { color: '#64FFDA', label: 'LGU Manager', icon: '🏛️' },
        admin: { color: '#FF6B8A', label: 'Admin', icon: '⚙️' },
    };

    return themes[role] ?? { color: '#0ABFA3', label: 'User', icon: '👤' };
}
