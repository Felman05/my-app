import { useEffect, useMemo, useRef, useState } from 'react';
import { GoogleMap, useJsApiLoader } from '@react-google-maps/api';
import StatusChip from '@/Components/dashboard/StatusChip';

const DEFAULT_CENTER = [14.1, 121.3];
const DEFAULT_ZOOM = 9;

const STATUS_COLORS = {
    approved: '#059669',
    pending: '#d97706',
    rejected: '#dc2626',
    draft: '#475569',
};

export default function GeoActivityMap({
    title,
    subtitle,
    initialPoints = [],
    defaultStatus = 'approved',
    className = '',
}) {
    const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
    const [status, setStatus] = useState(defaultStatus);
    const [points, setPoints] = useState(initialPoints);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [activePointId, setActivePointId] = useState(null);
    const [copiedPointId, setCopiedPointId] = useState(null);
    const [isStreetViewOpen, setIsStreetViewOpen] = useState(false);
    const [mapInstance, setMapInstance] = useState(null);
    const circlesRef = useRef([]);
    const streetViewContainerRef = useRef(null);
    const streetViewInstanceRef = useRef(null);

    const { isLoaded, loadError } = useJsApiLoader({
        id: 'doon-google-map-script',
        googleMapsApiKey,
    });

    const endpoint = typeof route === 'function' ? route('api.map.points') : '/api/map/points';

    useEffect(() => {
        const controller = new AbortController();
        const params = new URLSearchParams({ status });

        setLoading(true);
        setError(null);

        fetch(`${endpoint}?${params.toString()}`, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Map request failed (${response.status})`);
                }

                return response.json();
            })
            .then((json) => {
                setPoints(Array.isArray(json?.data) ? json.data : []);
            })
            .catch((fetchError) => {
                if (fetchError.name !== 'AbortError') {
                    setError('Unable to load live map points. Showing latest cached values.');
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [endpoint, status]);

    const normalizedPoints = useMemo(() => {
        return (points ?? []).slice(0, 120).map((point) => {
            const lat = Number(point.latitude);
            const lng = Number(point.longitude);

            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                return null;
            }

            return {
                id: point.id,
                title: point.title,
                status: point.status,
                province: point.province,
                municipality: point.municipality,
                category: point.category,
                latitude: lat,
                longitude: lng,
            };
        }).filter(Boolean);
    }, [points]);

    useEffect(() => {
        if (normalizedPoints.length === 0) {
            setActivePointId(null);
            return;
        }

        if (!normalizedPoints.some((point) => point.id === activePointId)) {
            setActivePointId(normalizedPoints[0].id);
        }
    }, [normalizedPoints, activePointId]);

    const activePoint = normalizedPoints.find((point) => point.id === activePointId) || normalizedPoints[0] || null;

    const listPreview = normalizedPoints.slice(0, 5);

    const copyCoordinates = async (point) => {
        const text = `${point.latitude},${point.longitude}`;

        try {
            await navigator.clipboard.writeText(text);
            setCopiedPointId(point.id);
            setTimeout(() => setCopiedPointId(null), 1200);
        } catch {
            setError('Could not copy coordinates on this browser.');
        }
    };

    useEffect(() => {
        if (!activePoint) {
            setIsStreetViewOpen(false);
        }
    }, [activePoint]);

    useEffect(() => {
        if (!isLoaded || !mapInstance || !window.google) {
            return;
        }

        if (activePoint) {
            mapInstance.panTo({ lat: activePoint.latitude, lng: activePoint.longitude });
            if ((mapInstance.getZoom() ?? DEFAULT_ZOOM) < 11) {
                mapInstance.setZoom(11);
            }

            return;
        }

        if (normalizedPoints.length === 0) {
            mapInstance.setCenter({ lat: DEFAULT_CENTER[0], lng: DEFAULT_CENTER[1] });
            mapInstance.setZoom(DEFAULT_ZOOM);

            return;
        }

        if (normalizedPoints.length === 1) {
            mapInstance.setCenter({
                lat: normalizedPoints[0].latitude,
                lng: normalizedPoints[0].longitude,
            });
            mapInstance.setZoom(12);

            return;
        }

        const bounds = new window.google.maps.LatLngBounds();
        normalizedPoints.forEach((point) => {
            bounds.extend({ lat: point.latitude, lng: point.longitude });
        });

        mapInstance.fitBounds(bounds, 36);
    }, [isLoaded, mapInstance, normalizedPoints, activePoint]);

    useEffect(() => {
        if (!isLoaded || !mapInstance || !window.google) {
            return;
        }

        circlesRef.current.forEach((entry) => entry.circle.setMap(null));
        circlesRef.current = normalizedPoints.map((point) => {
            const color = STATUS_COLORS[point.status] || '#0ea5e9';
            const isActive = activePoint?.id === point.id;
            const circle = new window.google.maps.Circle({
                map: mapInstance,
                center: { lat: point.latitude, lng: point.longitude },
                radius: isActive ? 110 : 85,
                strokeColor: '#ffffff',
                strokeOpacity: 1,
                strokeWeight: 2,
                fillColor: color,
                fillOpacity: 0.86,
                clickable: true,
                zIndex: isActive ? 120 : 80,
            });

            circle.addListener('click', () => setActivePointId(point.id));

            return { pointId: point.id, circle };
        });

        return () => {
            circlesRef.current.forEach((entry) => entry.circle.setMap(null));
            circlesRef.current = [];
        };
    }, [isLoaded, mapInstance, normalizedPoints, activePoint]);

    useEffect(() => {
        if (!isLoaded || !window.google || !activePoint || !isStreetViewOpen || !streetViewContainerRef.current) {
            return;
        }

        if (!streetViewInstanceRef.current) {
            streetViewInstanceRef.current = new window.google.maps.StreetViewPanorama(streetViewContainerRef.current, {
                addressControl: false,
                linksControl: true,
                panControl: true,
                enableCloseButton: false,
                fullscreenControl: true,
                motionTracking: false,
            });
        }

        streetViewInstanceRef.current.setPosition({ lat: activePoint.latitude, lng: activePoint.longitude });
        streetViewInstanceRef.current.setPov({ heading: 34, pitch: 8 });
        streetViewInstanceRef.current.setVisible(true);
    }, [isLoaded, activePoint, isStreetViewOpen]);

    return (
        <article className={`panel ${className}`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 className="text-xl font-semibold tracking-tight text-slate-900">{title}</h4>
                    <p className="text-sm text-slate-600">{subtitle}</p>
                </div>
                <div className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white/80 px-3 py-1.5 text-xs font-semibold text-slate-700">
                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                    {loading ? 'Refreshing map...' : `${points?.length ?? 0} points loaded`}
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-3">
                <StatusChip active={status === 'approved'} onClick={() => setStatus('approved')} activeClassName="bg-slate-900 text-white shadow-sm">Approved</StatusChip>
                <StatusChip active={status === 'pending'} onClick={() => setStatus('pending')} activeClassName="bg-slate-900 text-white shadow-sm">Pending</StatusChip>
                <StatusChip active={status === 'rejected'} onClick={() => setStatus('rejected')} activeClassName="bg-slate-900 text-white shadow-sm">Rejected</StatusChip>
                {activePoint && (
                    <button
                        type="button"
                        onClick={() => setIsStreetViewOpen((prev) => !prev)}
                        className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                            isStreetViewOpen
                                ? 'bg-emerald-700 text-white shadow-sm'
                                : 'border border-slate-300 bg-white text-slate-700 hover:border-slate-500'
                        }`}
                    >
                        {isStreetViewOpen ? 'Hide Street View' : 'Street View'}
                    </button>
                )}
            </div>

            <div className="map-shell mt-4">
                {!googleMapsApiKey && (
                    <div className="flex h-full items-center justify-center rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                        Google Maps API key is missing. Add <code className="mx-1 rounded bg-amber-100 px-1">VITE_GOOGLE_MAPS_API_KEY</code> to your <code className="mx-1 rounded bg-amber-100 px-1">.env</code> file.
                    </div>
                )}

                {googleMapsApiKey && loadError && (
                    <div className="flex h-full items-center justify-center rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                        Failed to load Google Maps. Check API key restrictions and enabled APIs.
                    </div>
                )}

                {googleMapsApiKey && !loadError && !isLoaded && (
                    <div className="flex h-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        Loading Google Maps...
                    </div>
                )}

                {googleMapsApiKey && isLoaded && !loadError && (
                    <GoogleMap
                        mapContainerClassName="map-canvas"
                        center={{ lat: DEFAULT_CENTER[0], lng: DEFAULT_CENTER[1] }}
                        zoom={DEFAULT_ZOOM}
                        onLoad={(map) => setMapInstance(map)}
                        options={{
                            mapTypeControl: false,
                            streetViewControl: true,
                            fullscreenControl: true,
                            clickableIcons: false,
                        }}
                    />
                )}
            </div>

            {activePoint && (
                <div className="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <p className="text-sm font-semibold text-slate-900">{activePoint.title}</p>
                    <span className="text-xs text-slate-600">{activePoint.category || 'Uncategorized'} • {activePoint.municipality || activePoint.province || 'Unknown'}</span>
                    <a
                        href={`https://www.google.com/maps?q=${activePoint.latitude},${activePoint.longitude}`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-full border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:border-slate-500"
                    >
                        Open Map
                    </a>
                    <button
                        type="button"
                        onClick={() => copyCoordinates(activePoint)}
                        className="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-slate-700"
                    >
                        {copiedPointId === activePoint.id ? 'Copied' : 'Copy Coords'}
                    </button>
                    <button
                        type="button"
                        onClick={() => setIsStreetViewOpen(true)}
                        className="rounded-full border border-emerald-700 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50"
                    >
                        Street View
                    </button>
                </div>
            )}

            {googleMapsApiKey && isLoaded && !loadError && isStreetViewOpen && activePoint && (
                <div ref={streetViewContainerRef} className="streetview-shell mt-4" />
            )}

            {error && <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">{error}</p>}

            <div className="mt-4 space-y-2">
                {listPreview.length === 0 && <p className="empty-note">No map data for this status yet.</p>}
                {listPreview.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setActivePointId(item.id)}
                        className={`w-full rounded-xl border px-3 py-2 text-left transition ${
                            activePoint?.id === item.id
                                ? 'border-slate-800 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white/80 hover:border-slate-400'
                        }`}
                    >
                        <p className={`text-sm font-semibold ${activePoint?.id === item.id ? 'text-white' : 'text-slate-800'}`}>{item.title}</p>
                        <p className={`text-xs ${activePoint?.id === item.id ? 'text-slate-200' : 'text-slate-600'}`}>
                            {item.category || 'Uncategorized'} • {item.province || 'Unknown province'}
                        </p>
                    </button>
                ))}
            </div>
        </article>
    );
}

