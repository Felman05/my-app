export default function ApplicationLogo(props) {
    return (
        <svg {...props} viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="doonGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#0f766e" />
                    <stop offset="100%" stopColor="#0891b2" />
                </linearGradient>
            </defs>
            <circle cx="80" cy="80" r="72" fill="url(#doonGradient)" />
            <path
                d="M48 42h32c22 0 38 15 38 38s-16 38-38 38H48V42zm28 60c14 0 24-9 24-22s-10-22-24-22h-8v44h8z"
                fill="#e2f7f5"
            />
            <circle cx="112" cy="48" r="10" fill="#fbbf24" />
            <path d="M112 34l4 10 10 4-10 4-4 10-4-10-10-4 10-4 4-10z" fill="#fef3c7" />
        </svg>
    );
}
