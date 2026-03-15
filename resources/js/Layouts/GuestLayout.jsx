import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="landing-shell relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-8">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.22),transparent_40%),radial-gradient(circle_at_bottom_right,rgba(249,115,22,0.2),transparent_40%)]" />

            <div className="relative">
                <Link href="/" className="flex flex-col items-center">
                    <div className="rounded-2xl border border-cyan-400/30 bg-slate-900/80 p-3">
                        <ApplicationLogo className="h-14 w-14 fill-current text-cyan-300" />
                    </div>
                    <p className="mt-3 text-lg font-black text-white">Doon</p>
                </Link>
            </div>

            <div className="relative mt-6 w-full overflow-hidden rounded-2xl border border-slate-700 bg-slate-900/90 px-6 py-5 shadow-2xl sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
