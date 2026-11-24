import React, { useState } from 'react';
import { 
    FileText, Bot, Calendar, Coffee, CheckCircle2, ShieldCheck, 
    ArrowRight, Sparkles, Send, RefreshCw, QrCode, TrendingUp,
    Building2, DollarSign, Clock, Layers, ChevronRight
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface InteractiveStudioProps {
    colors: {
        primary: string;
        secondary: string;
        accent: string;
        dark?: string;
    };
    grad: (a?: string, b?: string) => string;
}

type StudioTab = 'eims' | 'ai' | 'payroll' | 'coffee';

export default function InteractiveStudio({ colors, grad }: InteractiveStudioProps) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState<StudioTab>('eims');
    const [isVerifying, setIsVerifying] = useState(false);
    const [aiQuery, setAiQuery] = useState('');
    const [aiMessages, setAiMessages] = useState<Array<{ sender: 'user' | 'ai'; text: string; time: string; data?: any }>>([
        {
            sender: 'user',
            text: 'በጥቅምት ወር ስንት ቫት (VAT) ተሰብስቧል? ከ CBE ስቴትመንት ጋር አዛምደው።',
            time: '10:42 AM'
        },
        {
            sender: 'ai',
            text: 'በጥቅምት 2017 ዓ.ም. በድምሩ ETB 277,560.00 ቫት ተሰብስቧል። 98.4% የሚሆኑ ግብይቶች ከ CBE እና Telebirr ስቴትመንቶች ጋር በራስ-ሰር ተዛምደዋል።',
            time: '10:42 AM',
            data: {
                collected: 'ETB 277,560.00',
                deductions: 'ETB 142,300.00',
                payable: 'ETB 135,260.00',
                cbeMatched: '98.4%'
            }
        }
    ]);
    const [calendarView, setCalendarView] = useState<'geez' | 'gregorian'>('geez');

    const handleVerifyEims = () => {
        setIsVerifying(true);
        setTimeout(() => setIsVerifying(false), 900);
    };

    const handleSendAi = (textToSend?: string) => {
        const query = textToSend || aiQuery;
        if (!query.trim()) return;
        
        const userMsg = { sender: 'user' as const, text: query, time: 'Just now' };
        setAiMessages(prev => [...prev, userMsg]);
        setAiQuery('');

        setTimeout(() => {
            const aiMsg = {
                sender: 'ai' as const,
                text: query.includes('CBE') || query.includes('ባንክ')
                    ? 'የ CBE ባንክ ስቴትመንት በ 1.2 ሰከንድ ውስጥ ተገምግሟል። 418 ግብይቶች በትክክል ተመዝግበዋል። ምንም ያልተዛመደ ቀሪ የለም።'
                    : 'Gadaa AI Copilot analyzed 1,420 transactions in 0.8s. Your projected cash flow for the next 30 days is ETB +1.84M with zero tax discrepancies.',
                time: 'Just now',
                data: {
                    status: 'Reconciled',
                    discrepancy: 'ETB 0.00',
                    accuracy: '100%'
                }
            };
            setAiMessages(prev => [...prev, aiMsg]);
        }, 600);
    };

    return (
        <div className="w-full rounded-3xl bg-white shadow-2xl border border-emerald-100/80 overflow-hidden text-left relative transition-all duration-300">
            {/* Window Chrome Top Header */}
            <div className="flex flex-wrap items-center justify-between px-4 sm:px-6 py-3.5 bg-slate-50/90 border-b border-slate-200/70 gap-2">
                <div className="flex items-center gap-2">
                    <div className="w-3 h-3 rounded-full bg-rose-400" />
                    <div className="w-3 h-3 rounded-full bg-amber-400" />
                    <div className="w-3 h-3 rounded-full bg-[#00A76F]" />
                    <div className="ml-2 px-3 py-1 rounded-full bg-white border border-slate-200 text-[11px] font-semibold text-slate-600 flex items-center gap-1.5 shadow-2xs">
                        <span className="w-2 h-2 rounded-full bg-[#00A76F] animate-pulse" />
                        <span className="hidden sm:inline">app.gadaacloud.et</span>
                        <span className="text-slate-400">/</span>
                        <span className="text-[#00A76F] font-bold">studio</span>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <span className="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-[#00A76F] border border-emerald-200 flex items-center gap-1">
                        <ShieldCheck className="h-3 w-3" /> MoR Directive 1142 Ready
                    </span>
                </div>
            </div>

            {/* Interactive Workspace Navigation Tabs */}
            <div className="flex overflow-x-auto border-b border-slate-100 bg-slate-50/40 p-1.5 gap-1.5 text-xs font-bold scrollbar-none">
                <button
                    onClick={() => setActiveTab('eims')}
                    className={`flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all whitespace-nowrap ${
                        activeTab === 'eims'
                            ? 'bg-white text-[#00A76F] shadow-sm border border-emerald-100'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
                    }`}
                >
                    <FileText className="h-3.5 w-3.5" />
                    <span>MoR EIMS Invoicing</span>
                    <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-100 text-[#00A76F]">Direct 1142</span>
                </button>

                <button
                    onClick={() => setActiveTab('ai')}
                    className={`flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all whitespace-nowrap ${
                        activeTab === 'ai'
                            ? 'bg-white text-[#00A76F] shadow-sm border border-emerald-100'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
                    }`}
                >
                    <Bot className="h-3.5 w-3.5" />
                    <span>Amharic AI Copilot</span>
                    <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-100 text-[#00A76F]">አማርኛ</span>
                </button>

                <button
                    onClick={() => setActiveTab('payroll')}
                    className={`flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all whitespace-nowrap ${
                        activeTab === 'payroll'
                            ? 'bg-white text-[#00A76F] shadow-sm border border-emerald-100'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
                    }`}
                >
                    <Calendar className="h-3.5 w-3.5" />
                    <span>Ge'ez Dual Payroll</span>
                    <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-100 text-[#00A76F]">ETB Law</span>
                </button>

                <button
                    onClick={() => setActiveTab('coffee')}
                    className={`flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all whitespace-nowrap ${
                        activeTab === 'coffee'
                            ? 'bg-white text-[#00A76F] shadow-sm border border-emerald-100'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
                    }`}
                >
                    <Coffee className="h-3.5 w-3.5" />
                    <span>Coffee Export & ECX</span>
                    <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-100 text-[#00A76F]">Djibouti Corridor</span>
                </button>
            </div>

            {/* Dynamic Interactive Tab Canvas */}
            <div className="p-4 sm:p-6 bg-white min-h-[360px] flex flex-col justify-between">
                {/* ================= TAB 1: MOR EIMS INVOICING ================= */}
                {activeTab === 'eims' && (
                    <div className="space-y-4 animate-in fade-in duration-200">
                        {/* EIMS Header Banner */}
                        <div className="flex flex-wrap items-center justify-between pb-3 border-b border-slate-100 gap-2">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h4 className="text-sm font-black text-slate-900">TAX INVOICE · የግብር ደረሰኝ</h4>
                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-[#00A76F] border border-emerald-200">
                                        IRN: 8a4f-9c02-eims-1142
                                    </span>
                                </div>
                                <p className="text-[11px] text-slate-500 font-medium">
                                    Date: <strong className="text-slate-700">15 Tikimt 2017 (25 Oct 2024)</strong> · Currency: <strong className="text-slate-700">ETB (Ethiopian Birr)</strong>
                                </p>
                            </div>

                            <button
                                onClick={handleVerifyEims}
                                disabled={isVerifying}
                                className="px-3 py-1.5 rounded-lg text-xs font-bold text-white shadow-sm flex items-center gap-1.5 transition-all hover:scale-[1.02]"
                                style={{ background: grad() }}
                            >
                                <RefreshCw className={`h-3.5 w-3.5 ${isVerifying ? 'animate-spin' : ''}`} />
                                {isVerifying ? 'Verifying with MoR...' : 'Validate Fiscal QR'}
                            </button>
                        </div>

                        {/* Customer & Tax Registry Info */}
                        <div className="grid grid-cols-2 gap-3 text-xs bg-slate-50/70 p-3 rounded-xl border border-slate-100">
                            <div>
                                <span className="text-[10px] font-bold uppercase text-slate-400 block">Buyer / ደንበኛ</span>
                                <span className="font-bold text-slate-900">Bole Luxury Towers PLC</span>
                                <span className="text-[10px] text-slate-500 block">TIN: 0049281741 · VAT: 9918273645</span>
                            </div>
                            <div className="text-right">
                                <span className="text-[10px] font-bold uppercase text-slate-400 block">Fiscal Device ID</span>
                                <span className="font-bold text-[#00A76F]">MOU-GC-EIMS-0921</span>
                                <span className="text-[10px] text-emerald-600 font-semibold block">Signed with Private Key (ECDSA)</span>
                            </div>
                        </div>

                        {/* Line Items Table */}
                        <div className="border border-slate-100 rounded-xl overflow-hidden text-xs">
                            <table className="w-full text-left">
                                <thead className="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 border-b border-slate-100">
                                    <tr>
                                        <th className="p-2 sm:px-3">Description</th>
                                        <th className="p-2 text-center">Qty</th>
                                        <th className="p-2 text-right">Unit Price</th>
                                        <th className="p-2 sm:px-3 text-right">Total (ETB)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-slate-700 font-medium">
                                    <tr>
                                        <td className="p-2 sm:px-3 font-semibold text-slate-900">GadaaCloud Enterprise ERP License</td>
                                        <td className="p-2 text-center">1</td>
                                        <td className="p-2 text-right">85,000.00</td>
                                        <td className="p-2 sm:px-3 text-right font-bold text-slate-900">85,000.00</td>
                                    </tr>
                                    <tr>
                                        <td className="p-2 sm:px-3">MoR EIMS Directive 1142 Fiscal Module</td>
                                        <td className="p-2 text-center">1</td>
                                        <td className="p-2 text-right">15,000.00</td>
                                        <td className="p-2 sm:px-3 text-right font-bold text-slate-900">15,000.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {/* Totals & Cryptographic QR Verification Footer */}
                        <div className="flex flex-wrap items-center justify-between pt-2 gap-4">
                            <div className="flex items-center gap-3 bg-emerald-50/70 p-2.5 rounded-xl border border-emerald-100">
                                <div className="w-12 h-12 bg-white rounded-lg border border-emerald-200 flex items-center justify-center text-[#00A76F] shadow-2xs">
                                    <QrCode className="h-8 w-8" />
                                </div>
                                <div className="text-[10px]">
                                    <span className="font-bold text-slate-900 block">Ministry of Revenues Hash</span>
                                    <span className="text-slate-500 font-mono text-[9px] block">sha256: 4f8a...e901</span>
                                    <span className="text-[#00A76F] font-bold flex items-center gap-1">
                                        <CheckCircle2 className="h-3 w-3" /> Live MoR Synchronized
                                    </span>
                                </div>
                            </div>

                            <div className="text-right text-xs space-y-0.5 ml-auto">
                                <div className="text-slate-500">Subtotal: <strong className="text-slate-900">ETB 100,000.00</strong></div>
                                <div className="text-slate-500">VAT (15%): <strong className="text-slate-900">ETB 15,000.00</strong></div>
                                <div className="text-sm font-black text-[#00A76F] pt-1 border-t border-slate-200">
                                    Grand Total: ETB 115,000.00
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* ================= TAB 2: AMHARIC AI COPILOT ================= */}
                {activeTab === 'ai' && (
                    <div className="space-y-4 animate-in fade-in duration-200">
                        {/* Chat Messages Log */}
                        <div className="space-y-3 max-h-[220px] overflow-y-auto pr-1">
                            {aiMessages.map((msg, i) => (
                                <div key={i} className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start gap-2.5'}`}>
                                    {msg.sender === 'ai' && (
                                        <div className="w-7 h-7 rounded-lg bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white flex-shrink-0 mt-0.5 shadow-xs">
                                            <Bot className="h-4 w-4" />
                                        </div>
                                    )}
                                    <div className={`p-3 rounded-2xl text-xs max-w-[88%] ${
                                        msg.sender === 'user'
                                            ? 'bg-emerald-50 text-slate-900 font-semibold border border-emerald-100 rounded-tr-xs'
                                            : 'bg-slate-50 text-slate-800 border border-slate-100 rounded-tl-xs space-y-2'
                                    }`}>
                                        <p className="leading-relaxed">{msg.text}</p>
                                        {msg.data && (
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5 pt-1 text-[10px]">
                                                <div className="p-1.5 bg-white rounded-md border border-slate-200">
                                                    <span className="text-slate-400 block font-bold">VAT Collected</span>
                                                    <span className="font-bold text-slate-900">{msg.data.collected}</span>
                                                </div>
                                                <div className="p-1.5 bg-white rounded-md border border-slate-200">
                                                    <span className="text-slate-400 block font-bold">Input Deduction</span>
                                                    <span className="font-bold text-slate-900">{msg.data.deductions}</span>
                                                </div>
                                                <div className="p-1.5 bg-emerald-50 rounded-md border border-emerald-200">
                                                    <span className="text-[#00A76F] block font-bold">Net Tax Due</span>
                                                    <span className="font-black text-[#00A76F]">{msg.data.payable}</span>
                                                </div>
                                                <div className="p-1.5 bg-white rounded-md border border-slate-200">
                                                    <span className="text-slate-400 block font-bold">CBE Match</span>
                                                    <span className="font-bold text-emerald-600">{msg.data.cbeMatched}</span>
                                                </div>
                                            </div>
                                        )}
                                        <span className="text-[9px] text-slate-400 block text-right">{msg.time}</span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Fast Prompt Pills */}
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            <button
                                onClick={() => handleSendAi('የ CBE እና የቴሌብር (Telebirr) ሂሳብ አዛምድ')}
                                className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-[#00A76F] transition"
                            >
                                ⚡ የ CBE እና ቴሌብር ሂሳብ አዛምድ
                            </button>
                            <button
                                onClick={() => handleSendAi('Generate 30-Day Birr Cash Flow Forecast')}
                                className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-[#00A76F] transition"
                            >
                                📈 30-Day Cash Flow Forecast
                            </button>
                            <button
                                onClick={() => handleSendAi('Audit supplier bills for duplicate TIN')}
                                className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-[#00A76F] transition"
                            >
                                🔍 Audit Duplicate TINs
                            </button>
                        </div>

                        {/* Interactive Input Bar */}
                        <div className="flex items-center gap-2 pt-1">
                            <input
                                type="text"
                                value={aiQuery}
                                onChange={(e) => setAiQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleSendAi()}
                                placeholder="በአማርኛ ወይም በ English ጥያቄዎን ይጠይቁ..."
                                className="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-[#00A76F]"
                            />
                            <button
                                onClick={() => handleSendAi()}
                                className="px-4 py-2.5 rounded-xl text-white font-bold text-xs flex items-center gap-1.5 shadow-sm"
                                style={{ background: grad() }}
                            >
                                <Send className="h-3.5 w-3.5" /> Send
                            </button>
                        </div>
                    </div>
                )}

                {/* ================= TAB 3: DUAL-CALENDAR PAYROLL ================= */}
                {activeTab === 'payroll' && (
                    <div className="space-y-4 animate-in fade-in duration-200 text-xs">
                        {/* Month & Calendar Switcher */}
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h4 className="text-sm font-bold text-slate-900">Ethiopian Labor Law Birr Payroll</h4>
                                <p className="text-[11px] text-slate-500">Tax Schedule: Proclamation No. 979/2016 (0% - 35% Brackets)</p>
                            </div>
                            <div className="flex items-center bg-slate-100 p-0.5 rounded-lg text-[11px] font-bold">
                                <button
                                    onClick={() => setCalendarView('geez')}
                                    className={`px-2.5 py-1 rounded-md transition ${calendarView === 'geez' ? 'bg-white text-[#00A76F] shadow-2xs' : 'text-slate-600'}`}
                                >
                                    ጥቅምት 2017 (Ge'ez)
                                </button>
                                <button
                                    onClick={() => setCalendarView('gregorian')}
                                    className={`px-2.5 py-1 rounded-md transition ${calendarView === 'gregorian' ? 'bg-white text-[#00A76F] shadow-2xs' : 'text-slate-600'}`}
                                >
                                    October 2024
                                </button>
                            </div>
                        </div>

                        {/* Payroll Table */}
                        <div className="border border-slate-100 rounded-xl overflow-hidden">
                            <table className="w-full text-left">
                                <thead className="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 border-b border-slate-100">
                                    <tr>
                                        <th className="p-2 sm:px-3">Employee Name</th>
                                        <th className="p-2 text-right">Gross Pay</th>
                                        <th className="p-2 text-right">Income Tax</th>
                                        <th className="p-2 text-right">Pension (7%)</th>
                                        <th className="p-2 sm:px-3 text-right">Net Birr Pay</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-medium">
                                    <tr>
                                        <td className="p-2 sm:px-3 font-semibold text-slate-900">Dawit Haile (Lead Engineer)</td>
                                        <td className="p-2 text-right">ETB 75,000</td>
                                        <td className="p-2 text-right text-rose-600">ETB 20,400</td>
                                        <td className="p-2 text-right text-slate-500">ETB 5,250</td>
                                        <td className="p-2 sm:px-3 text-right font-bold text-[#00A76F]">ETB 49,350</td>
                                    </tr>
                                    <tr>
                                        <td className="p-2 sm:px-3 font-semibold text-slate-900">Selamawit Tadesse (Finance Dir)</td>
                                        <td className="p-2 text-right">ETB 90,000</td>
                                        <td className="p-2 text-right text-rose-600">ETB 25,650</td>
                                        <td className="p-2 text-right text-slate-500">ETB 6,300</td>
                                        <td className="p-2 sm:px-3 text-right font-bold text-[#00A76F]">ETB 58,050</td>
                                    </tr>
                                    <tr>
                                        <td className="p-2 sm:px-3 font-semibold text-slate-900">Yared Bekele (Logistics)</td>
                                        <td className="p-2 text-right">ETB 55,000</td>
                                        <td className="p-2 text-right text-rose-600">ETB 13,400</td>
                                        <td className="p-2 text-right text-slate-500">ETB 3,850</td>
                                        <td className="p-2 sm:px-3 text-right font-bold text-[#00A76F]">ETB 37,750</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {/* Batch Action Banner */}
                        <div className="flex items-center justify-between p-3 rounded-xl bg-emerald-50 border border-emerald-100">
                            <div>
                                <span className="font-bold text-slate-900 block text-xs">Total Monthly Payroll: ETB 220,000.00</span>
                                <span className="text-[10px] text-slate-500">Pension Authority (18% Total) & ERCA Tax files prepared.</span>
                            </div>
                            <button
                                className="px-3.5 py-1.5 rounded-lg text-white font-bold text-xs shadow-sm flex items-center gap-1.5"
                                style={{ background: grad() }}
                            >
                                <DollarSign className="h-3.5 w-3.5" /> Disburse via CBE / Telebirr
                            </button>
                        </div>
                    </div>
                )}

                {/* ================= TAB 4: COFFEE EXPORT & ECX ================= */}
                {activeTab === 'coffee' && (
                    <div className="space-y-4 animate-in fade-in duration-200 text-xs">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h4 className="text-sm font-bold text-slate-900">Lot: SIDAMA-YIRGA-G1-2026</h4>
                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-[#00A76F]">
                                        ECX Grade 1 · Q-Score 88.5
                                    </span>
                                </div>
                                <p className="text-[11px] text-slate-500">Contract #EXP-DJIB-9921 · Destination: Hamburg, Germany</p>
                            </div>
                            <div className="text-right">
                                <span className="text-[10px] uppercase font-bold text-slate-400 block">LC Value</span>
                                <span className="font-bold text-[#00A76F] text-sm">USD 142,500</span>
                                <span className="text-[10px] text-slate-500 block">≈ ETB 17,812,500</span>
                            </div>
                        </div>

                        {/* Stepper Pipeline */}
                        <div className="grid grid-cols-4 gap-2 text-center pt-1">
                            <div className="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                <span className="w-5 h-5 rounded-full bg-[#00A76F] text-white flex items-center justify-center mx-auto text-[10px] font-bold mb-1">
                                    ✓
                                </span>
                                <span className="font-bold text-slate-900 block text-[11px]">Farm Origin</span>
                                <span className="text-[9px] text-slate-500 block">Sidama Union</span>
                            </div>
                            <div className="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                <span className="w-5 h-5 rounded-full bg-[#00A76F] text-white flex items-center justify-center mx-auto text-[10px] font-bold mb-1">
                                    ✓
                                </span>
                                <span className="font-bold text-slate-900 block text-[11px]">ECX Grading</span>
                                <span className="text-[9px] text-slate-500 block">Addis Central Lab</span>
                            </div>
                            <div className="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                <span className="w-5 h-5 rounded-full bg-[#00A76F] text-white flex items-center justify-center mx-auto text-[10px] font-bold mb-1">
                                    ✓
                                </span>
                                <span className="font-bold text-slate-900 block text-[11px]">Container Prep</span>
                                <span className="text-[9px] text-slate-500 block">Mojo Dry Port</span>
                            </div>
                            <div className="p-2.5 rounded-xl bg-white border-2 border-[#00A76F] shadow-xs">
                                <span className="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto text-[10px] font-bold mb-1 animate-pulse">
                                    ●
                                </span>
                                <span className="font-bold text-slate-900 block text-[11px]">Djibouti Corridor</span>
                                <span className="text-[9px] text-[#00A76F] font-bold block">In Transit (Train)</span>
                            </div>
                        </div>

                        {/* Quality Specs Pill */}
                        <div className="grid grid-cols-3 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-center text-[10px]">
                            <div>
                                <span className="text-slate-400 block font-bold">Moisture Content</span>
                                <span className="font-bold text-slate-900">11.2% (Passed)</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-bold">Screen Size</span>
                                <span className="font-bold text-slate-900">15+ Clean Cup</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-bold">Demurrage Clock</span>
                                <span className="font-bold text-emerald-600">6 Days Free Time</span>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
