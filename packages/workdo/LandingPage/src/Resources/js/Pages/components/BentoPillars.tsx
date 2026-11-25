import React from 'react';
import { 
    ShieldCheck, Calendar, CloudLightning, DollarSign, 
    Check, ArrowRight, QrCode, LockKeyhole, Cpu, CloudCog, FileLock2, DatabaseZap
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CloudTopologyPattern, CircuitDotPattern, CloudSecurityWatermark } from './CloudSecurityPatterns';

interface BentoPillarsProps {
    colors: {
        primary: string;
        secondary: string;
        accent: string;
    };
    grad: (a?: string, b?: string) => string;
}

export default function BentoPillars({ colors, grad }: BentoPillarsProps) {
    const { t } = useTranslation();

    return (
        <section id="pillars" className="py-10 sm:py-14 bg-white relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="max-w-3xl mx-auto text-center mb-7 space-y-2.5">
                    <span className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                        <CloudCog className="h-3.5 w-3.5" /> Secured Cloud Architecture
                    </span>
                    <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                        Built for Ethiopian enterprise realities.
                    </h2>
                    <p className="text-sm sm:text-base text-slate-600 leading-relaxed">
                        Four secured cloud pillars engineered for Ethiopia's fiscal, calendar, and connectivity ecosystem.
                    </p>
                </div>

                {/* Modern Bento Grid Layout */}
                <div className="grid grid-cols-1 md:grid-cols-12 gap-5">
                    {/* BENTO 1: MoR EIMS DIRECTIVE 1142 (Wide - 7 Cols) */}
                    <div className="md:col-span-7 bg-gradient-to-br from-emerald-50/90 via-emerald-50/30 to-white rounded-3xl p-6 border border-emerald-100/90 shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col justify-between group relative overflow-hidden">
                        <CircuitDotPattern className="absolute inset-0 opacity-60" />
                        <div className="space-y-3 relative z-10">
                            <div className="flex items-center justify-between">
                                <div className="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                    <FileLock2 className="h-5 w-5" />
                                </div>
                                <span className="text-[11px] font-bold px-3 py-1 rounded-full bg-emerald-100/80 text-[#00A76F] border border-emerald-200 flex items-center gap-1">
                                    <LockKeyhole className="h-3 w-3" /> MoR Directive No. 1142/2026
                                </span>
                            </div>

                            <div className="space-y-1.5">
                                <h3 className="text-xl font-black text-slate-900 tracking-tight">
                                    Certified MoR EIMS Electronic Invoicing
                                </h3>
                                <p className="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-xl">
                                    Issue legally binding electronic invoices with automated cryptographic QR hashes, IRN tracking, and encrypted Ministry of Revenues cloud transmission.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                                <div className="flex items-start gap-2 text-xs text-slate-700">
                                    <div className="w-4 h-4 rounded-full bg-emerald-100 text-[#00A76F] flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <Check className="h-3 w-3 stroke-[3]" />
                                    </div>
                                    <span>ECDSA SHA-256 digital signature stamp</span>
                                </div>
                                <div className="flex items-start gap-2 text-xs text-slate-700">
                                    <div className="w-4 h-4 rounded-full bg-emerald-100 text-[#00A76F] flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <Check className="h-3 w-3 stroke-[3]" />
                                    </div>
                                    <span>Offline fiscal queueing with auto-push</span>
                                </div>
                            </div>
                        </div>

                        {/* Interactive In-Card Mock */}
                        <div className="mt-4 p-3.5 rounded-2xl bg-white border border-emerald-100/80 shadow-xs flex items-center justify-between relative z-10">
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center text-[#00A76F]">
                                    <QrCode className="h-5 w-5" />
                                </div>
                                <div>
                                    <span className="text-xs font-bold text-slate-900 block">EIMS Digital Signature Token</span>
                                    <span className="text-[10px] text-slate-500 font-mono">Status: Verified by MoR Gateway (200 OK)</span>
                                </div>
                            </div>
                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-[#00A76F]">
                                100% Validated
                            </span>
                        </div>
                    </div>

                    {/* BENTO 2: DUAL CALENDAR ENGINE (5 Cols) */}
                    <div className="md:col-span-5 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col justify-between group relative overflow-hidden">
                        <div className="space-y-3 relative z-10">
                            <div className="flex items-center justify-between">
                                <div className="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                    <Calendar className="h-5 w-5" />
                                </div>
                                <span className="text-[11px] font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
                                    Ge'ez + Gregorian
                                </span>
                            </div>

                            <div className="space-y-1.5">
                                <h3 className="text-xl font-black text-slate-900 tracking-tight">
                                    Native Dual Calendar Engine
                                </h3>
                                <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                    Work simultaneously in Gregorian and Ethiopian Ge'ez (`Kenat`) calendars. Auto-computes the Ethiopian fiscal year (Hamle 1 – Sene 30).
                                </p>
                            </div>

                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-500">Ethiopian Calendar:</span>
                                    <span className="font-bold text-[#00A76F]">ጥቅምት 15, 2017 ዓ.ም.</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-500">Gregorian Equivalent:</span>
                                    <span className="font-bold text-slate-900">October 25, 2024</span>
                                </div>
                            </div>
                        </div>

                        <div className="pt-3 border-t border-slate-100 flex items-center gap-2 text-xs font-bold text-[#00A76F]">
                            <span>Seamless across accounting & payroll</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </div>
                    </div>

                    {/* BENTO 3: OFFLINE-FIRST REGIONAL CLOUD SYNC (5 Cols) */}
                    <div className="md:col-span-5 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col justify-between group relative overflow-hidden">
                        <CloudTopologyPattern className="absolute inset-0 opacity-70" />
                        <CloudSecurityWatermark className="absolute -right-6 -bottom-4 w-44 h-36 text-[#00A76F] opacity-40" />
                        <div className="space-y-3 relative z-10">
                            <div className="flex items-center justify-between">
                                <div className="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                    <CloudLightning className="h-5 w-5" />
                                </div>
                                <span className="text-[11px] font-bold px-3 py-1 rounded-full bg-emerald-50 text-[#00A76F] border border-emerald-100">
                                    Encrypted Edge Cloud
                                </span>
                            </div>

                            <div className="space-y-1.5">
                                <h3 className="text-xl font-black text-slate-900 tracking-tight">
                                    Offline-First Cloud Edge Sync
                                </h3>
                                <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                    Internet cuts never halt sales. POS registers and depot scanners buffer records in encrypted local storage and sync to cloud automatically.
                                </p>
                            </div>
                        </div>

                        <div className="p-3 rounded-2xl bg-emerald-50/85 backdrop-blur-xs border border-emerald-100 text-xs font-semibold text-[#00A76F] flex items-center gap-2 mt-4 relative z-10">
                            <span className="w-2 h-2 rounded-full bg-[#00A76F] animate-pulse" />
                            <span>Zero lost sales across regional branches</span>
                        </div>
                    </div>

                    {/* BENTO 4: CBE & TELEBIRR RECONCILIATION (7 Cols) */}
                    <div className="md:col-span-7 bg-gradient-to-br from-emerald-50/90 via-emerald-50/30 to-white rounded-3xl p-6 border border-emerald-100/90 shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col justify-between group relative overflow-hidden">
                        <div className="space-y-3 relative z-10">
                            <div className="flex items-center justify-between">
                                <div className="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                    <DatabaseZap className="h-5 w-5" />
                                </div>
                                <span className="text-[11px] font-bold px-3 py-1 rounded-full bg-emerald-100/80 text-[#00A76F] border border-emerald-200 flex items-center gap-1">
                                    <ShieldCheck className="h-3 w-3" /> Secured Bank & Telebirr Sync
                                </span>
                            </div>

                            <div className="space-y-1.5">
                                <h3 className="text-xl font-black text-slate-900 tracking-tight">
                                    Automated CBE & Telebirr Clearing
                                </h3>
                                <p className="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-xl">
                                    Ingest Commercial Bank of Ethiopia (CBE) statements and Telebirr merchant files over encrypted channels with automated 1-click ledger matching.
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-3 pt-1">
                                <div className="p-2.5 rounded-xl bg-white border border-emerald-100 shadow-2xs text-xs">
                                    <span className="text-slate-400 block font-bold text-[10px]">CBE Bank File</span>
                                    <span className="font-bold text-slate-900">418 of 418 Matched</span>
                                </div>
                                <div className="p-2.5 rounded-xl bg-white border border-emerald-100 shadow-2xs text-xs">
                                    <span className="text-slate-400 block font-bold text-[10px]">Telebirr QR</span>
                                    <span className="font-bold text-[#00A76F]">Instant Settlement</span>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 pt-3 border-t border-emerald-100 flex items-center justify-between text-xs font-semibold text-slate-600 relative z-10">
                            <span>Supported: CBE, Telebirr, Awash, Dashen, Co-op</span>
                            <span className="text-[#00A76F] font-bold">TLS 1.3 Verified</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
