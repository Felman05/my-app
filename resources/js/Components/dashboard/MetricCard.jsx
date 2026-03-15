export default function MetricCard({ title, value }) {
    return (
        <article className="metric-card reveal reveal-delay-1">
            <p className="text-sm text-slate-600">{title}</p>
            <p className="mt-2 text-2xl font-black text-slate-800">{value}</p>
        </article>
    );
}
