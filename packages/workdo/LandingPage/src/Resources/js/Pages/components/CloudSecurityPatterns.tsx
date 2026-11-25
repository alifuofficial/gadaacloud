import React from 'react';

/**
 * Architectural Cloud Topology & Node Mesh SVG Pattern for light hero & cloud sections.
 * Uses signature GadaaCloud emerald tones (#00A76F / #059669) at ultra-subtle opacity.
 */
export function CloudTopologyPattern({ className = '' }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={`pointer-events-none select-none ${className}`}
            width="100%"
            height="100%"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <pattern id="gc-cloud-topology" x="0" y="0" width="120" height="120" patternUnits="userSpaceOnUse">
                    {/* Subtle cloud contour curves */}
                    <path
                        d="M24 68c-8.8 0-16-6.7-16-15s7.2-15 16-15c1.6-9.5 9.9-16.5 19.8-16.5 9.4 0 17.3 6.3 19.4 15 6.8.7 12.1 6.4 12.1 13.4 0 7.5-6.1 13.6-13.6 13.6H24z"
                        fill="none"
                        stroke="#00A76F"
                        strokeWidth="0.8"
                        strokeOpacity="0.10"
                    />
                    {/* Network node connectors */}
                    <path
                        d="M75 50 L108 26 M75 50 L110 86 M12 18 L40 22"
                        stroke="#059669"
                        strokeWidth="0.7"
                        strokeDasharray="3 3"
                        strokeOpacity="0.14"
                    />
                    <circle cx="108" cy="26" r="2.2" fill="#00A76F" fillOpacity="0.16" />
                    <circle cx="110" cy="86" r="2" fill="#34D399" fillOpacity="0.22" />
                    <circle cx="12" cy="18" r="1.8" fill="#00A76F" fillOpacity="0.14" />
                    <circle cx="75" cy="50" r="2.5" fill="#059669" fillOpacity="0.15" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#gc-cloud-topology)" />
        </svg>
    );
}

/**
 * Cryptographic Hexagonal Shield Lattice Pattern for dark security & trust bands.
 */
export function SecurityHexPattern({ className = '' }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={`pointer-events-none select-none ${className}`}
            width="100%"
            height="100%"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <pattern id="gc-security-hex" width="56" height="96" patternUnits="userSpaceOnUse">
                    <path
                        d="M28 66L0 50L0 16L28 0L56 16L56 50L28 66ZM28 96L0 80L0 46L28 30L56 46L56 80L28 96Z"
                        fill="none"
                        stroke="#34D399"
                        strokeWidth="0.75"
                        strokeOpacity="0.09"
                    />
                    {/* Micro lock shield dot */}
                    <circle cx="28" cy="33" r="1.6" fill="#34D399" fillOpacity="0.20" />
                    <circle cx="0" cy="16" r="1.4" fill="#00A76F" fillOpacity="0.18" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#gc-security-hex)" />
        </svg>
    );
}

/**
 * Fine Emerald Circuit & Data-Node Matrix Pattern for Pricing & Feature cards.
 */
export function CircuitDotPattern({ className = '' }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={`pointer-events-none select-none ${className}`}
            width="100%"
            height="100%"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <pattern id="gc-circuit-dots" width="32" height="32" patternUnits="userSpaceOnUse">
                    <circle cx="2" cy="2" r="1.1" fill="#00A76F" fillOpacity="0.14" />
                    <path
                        d="M2 2 H18 V18"
                        fill="none"
                        stroke="#00A76F"
                        strokeWidth="0.6"
                        strokeOpacity="0.07"
                    />
                    <circle cx="18" cy="18" r="1.3" fill="#059669" fillOpacity="0.10" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#gc-circuit-dots)" />
        </svg>
    );
}

/**
 * Watermark emblem combining a Cloud silhouette + Security Shield for hero & vault panels.
 */
export function CloudSecurityWatermark({ className = '' }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 240 180"
            className={`pointer-events-none select-none ${className}`}
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            {/* Outer Cloud */}
            <path
                d="M58 142h124c22.1 0 40-17.9 40-40 0-20.5-15.4-37.4-35.3-39.7C180.4 31.8 153.5 10 121.5 10 85.4 10 56 38.4 54.6 74.2 31.4 77.7 14 97.6 14 121.5c0 11.3 4.5 20.5 14 20.5h30z"
                stroke="currentColor"
                strokeWidth="2"
                strokeDasharray="5 5"
                opacity="0.22"
            />
            {/* Inner Security Shield */}
            <path
                d="M120 52l42 16v34c0 27.6-17.9 51.6-42 60-24.1-8.4-42-32.4-42-60V68l42-16z"
                stroke="currentColor"
                strokeWidth="2.2"
                opacity="0.30"
            />
            <path
                d="M104 104l11 11 22-24"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.45"
            />
        </svg>
    );
}
