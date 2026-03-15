export default function StatusChip({
    active,
    onClick,
    children,
    activeClassName = 'bg-slate-900 text-white',
    inactiveClassName = 'border border-slate-300 bg-white text-slate-700 hover:border-slate-500',
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${active ? activeClassName : inactiveClassName}`}
        >
            {children}
        </button>
    );
}
