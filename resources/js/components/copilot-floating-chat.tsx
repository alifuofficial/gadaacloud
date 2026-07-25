import React, { Component, ReactNode, useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Sparkles, Bot, X, Send, ShieldCheck, RefreshCw, Trash2, Brain } from 'lucide-react';

interface ChatMessage {
    id: string;
    sender: 'user' | 'copilot';
    text: string;
    timestamp: string;
    confidence?: number;
}

class CopilotErrorBoundary extends Component<{ children: ReactNode }, { hasError: boolean }> {
    constructor(props: { children: ReactNode }) {
        super(props);
        this.state = { hasError: false };
    }
    static getDerivedStateFromError() {
        return { hasError: true };
    }
    componentDidCatch(error: any, errorInfo: any) {
        console.error("CopilotFloatingChat Error Boundary caught an error:", error, errorInfo);
    }
    render() {
        if (this.state.hasError) {
            return null;
        }
        return this.props.children;
    }
}

function CopilotFloatingChatInner() {
    const { t } = useTranslation();
    const pageObj = usePage() as any;
    const auth = pageObj?.props?.auth || {};
    const isSuperadmin = auth?.user?.type === 'superadmin';

    const [isOpen, setIsOpen] = useState(false);
    const [prompt, setPrompt] = useState('');
    const [loading, setLoading] = useState(false);
    const [memoryCount, setMemoryCount] = useState(0);

    const initialGreeting: ChatMessage = {
        id: '1',
        sender: 'copilot',
        text: isSuperadmin
            ? `👑 **Superadmin ERP Telemetry Online**\n\nHello Super Admin! I analyze platform tenant health, expiring company plans, helpdesk bottlenecks, and system performance. Ask me: *"What problems are companies facing?"*`
            : `👋 Hello ${auth?.user?.name || 'User'}! I am **GadaaCloud Copilot**.\n\nI analyze your ERP data in real-time while strictly enforcing your active Spatie permissions. How can I assist you with financial forecasting, Ethiopian tax calculations, or payroll today?`,
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        confidence: 0.99,
    };

    const [messages, setMessages] = useState<ChatMessage[]>([initialGreeting]);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    // Load persistent memories from backend on mount
    useEffect(() => {
        const fetchMemories = async () => {
            try {
                let memUrl = '/settings/copilot/memories';
                try {
                    if (typeof route === 'function') {
                        memUrl = route('settings.copilot.memories');
                    }
                } catch (e) {}

                const res = await axios.get(memUrl);
                if (res.data?.memories && res.data.memories.length > 0) {
                    const loadedMsgs: ChatMessage[] = res.data.memories.map((m: any) => ({
                        id: m.id.toString(),
                        sender: m.role === 'user' ? 'user' : 'copilot',
                        text: m.content,
                        timestamp: new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                        confidence: 0.98,
                    }));
                    setMessages([initialGreeting, ...loadedMsgs]);
                    setMemoryCount(loadedMsgs.length);
                }
            } catch (e) {}
        };
        fetchMemories();
    }, []);

    useEffect(() => {
        if (isOpen) {
            scrollToBottom();
        }
    }, [messages, isOpen]);

    const handleClearMemory = async () => {
        try {
            let clearUrl = '/settings/copilot/memories/clear';
            try {
                if (typeof route === 'function') {
                    clearUrl = route('settings.copilot.memories.clear');
                }
            } catch (e) {}

            await axios.post(clearUrl);
            setMessages([initialGreeting]);
            setMemoryCount(0);
        } catch (e) {}
    };

    const handleSend = async (customPrompt?: string) => {
        const queryText = customPrompt || prompt;
        if (!queryText.trim() || loading) return;

        const userMsg: ChatMessage = {
            id: Date.now().toString(),
            sender: 'user',
            text: queryText,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        };

        setMessages((prev) => [...prev, userMsg]);
        if (!customPrompt) setPrompt('');
        setLoading(true);

        try {
            let queryUrl = '/settings/copilot/query';
            try {
                if (typeof route === 'function') {
                    queryUrl = route('settings.copilot.query');
                }
            } catch (e) {}

            const response = await axios.post(queryUrl, {
                prompt: queryText,
            });

            const botMsg: ChatMessage = {
                id: (Date.now() + 1).toString(),
                sender: 'copilot',
                text: response.data.reply || 'Analysis complete.',
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                confidence: response.data.confidence || 0.98,
            };

            setMessages((prev) => [...prev, botMsg]);
            setMemoryCount((prev) => prev + 2);
        } catch (error) {
            const errorMsg: ChatMessage = {
                id: (Date.now() + 1).toString(),
                sender: 'copilot',
                text: '⚠️ Unable to process query. Please check network connection or permissions.',
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            };
            setMessages((prev) => [...prev, errorMsg]);
        } finally {
            setLoading(false);
        }
    };

    const quickPrompts = isSuperadmin
        ? [
              'What problems are companies facing?',
              'Which company plans are expiring?',
              'Support Ticket Escalations',
              'Platform Storage & Health',
          ]
        : [
              'Predict next 6-month cashflow',
              'Calculate Ethiopian employment tax',
              'Sales & Receivables summary',
              'Staff Payroll breakdown',
          ];

    return (
        <div className="fixed bottom-6 right-6 z-[9999] font-sans">
            {/* Floating Action Trigger Button */}
            {!isOpen && (
                <button
                    onClick={() => setIsOpen(true)}
                    className="group relative flex items-center gap-3 bg-gradient-to-r from-purple-700 via-indigo-700 to-slate-900 text-white p-3 pr-4 rounded-full shadow-2xl hover:shadow-purple-500/25 transition-all hover:scale-105 border border-purple-400/30"
                >
                    <div className="relative">
                        <div className="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center backdrop-blur-md">
                            <Bot className="w-5 h-5 text-purple-200 group-hover:rotate-12 transition-transform" />
                        </div>
                        <span className="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                    </div>
                    <div className="text-left">
                        <div className="text-xs font-bold tracking-wide text-white flex items-center gap-1">
                            {isSuperadmin ? 'Superadmin Copilot' : 'GadaaCloud Copilot'}
                            <Sparkles className="w-3 h-3 text-purple-300 animate-pulse" />
                        </div>
                        <div className="text-[9px] text-purple-200/80 font-mono flex items-center gap-1">
                            <Brain className="w-2.5 h-2.5 text-emerald-400" />
                            Memory Active
                        </div>
                    </div>
                </button>
            )}

            {/* Chatbot Terminal Modal Window */}
            {isOpen && (
                <div className="fixed bottom-20 right-6 z-[9999] w-[340px] sm:w-[390px] h-[520px] max-h-[82vh] bg-slate-950/95 border border-purple-500/30 rounded-2xl shadow-2xl backdrop-blur-xl flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-300">
                    {/* Header */}
                    <div className="p-3.5 bg-gradient-to-r from-purple-950 via-slate-900 to-indigo-950 border-b border-purple-500/20 flex items-center justify-between">
                        <div className="flex items-center gap-2.5">
                            <div className="p-1.5 rounded-xl bg-purple-500/20 border border-purple-400/30">
                                <Bot className="w-4 h-4 text-purple-300" />
                            </div>
                            <div>
                                <div className="text-xs font-bold text-white flex items-center gap-1.5">
                                    {isSuperadmin ? 'Superadmin AI Copilot' : 'GadaaCloud Copilot'}
                                    <Badge className="bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[8px] px-1 py-0.2">
                                        <Brain className="w-2.5 h-2.5 mr-0.5" />
                                        Memory
                                    </Badge>
                                </div>
                                <div className="text-[9px] text-slate-400 flex items-center gap-1 font-mono">
                                    <span className="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Google Gemini 1.5 Active
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            {memoryCount > 0 && (
                                <button
                                    onClick={handleClearMemory}
                                    title="Wipe Conversation Memory"
                                    className="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors text-[10px] flex items-center gap-1"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                </button>
                            )}
                            <button
                                onClick={() => setIsOpen(false)}
                                className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Messages Body */}
                    <div className="flex-1 p-3 overflow-y-auto space-y-3 text-xs">
                        {messages.map((msg) => (
                            <div
                                key={msg.id}
                                className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[88%] rounded-2xl p-3 space-y-1 ${
                                        msg.sender === 'user'
                                            ? 'bg-purple-600 text-white rounded-br-none'
                                            : 'bg-slate-900 border border-slate-800 text-slate-200 rounded-bl-none shadow-sm'
                                    }`}
                                >
                                    <div className="whitespace-pre-wrap leading-relaxed text-[11px]">
                                        {msg.text.split('\n').map((line, i) => (
                                            <p key={i} className={line.startsWith('•') || line.startsWith('  -') ? 'my-0.5 font-sans' : 'my-1'}>
                                                {line}
                                            </p>
                                        ))}
                                    </div>
                                    <div className="flex items-center justify-between text-[9px] opacity-75 pt-1 font-mono">
                                        <span>{msg.timestamp}</span>
                                        {msg.confidence && (
                                            <span className="text-emerald-400 font-semibold">{Math.round(msg.confidence * 100)}% Confidence</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                        {loading && (
                            <div className="flex justify-start">
                                <div className="bg-slate-900 border border-slate-800 text-slate-400 rounded-2xl rounded-bl-none p-2.5 flex items-center gap-2 text-[11px]">
                                    <RefreshCw className="w-3 h-3 animate-spin text-purple-400" />
                                    <span>Analyzing platform telemetry &amp; memory...</span>
                                </div>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {/* Quick Prompts */}
                    <div className="px-3 py-1.5 bg-slate-900/60 border-t border-slate-800/80 flex items-center gap-1 overflow-x-auto no-scrollbar">
                        {quickPrompts.map((qp, idx) => (
                            <button
                                key={idx}
                                onClick={() => handleSend(qp)}
                                className="whitespace-nowrap text-[9px] bg-slate-800 hover:bg-purple-900/50 text-slate-300 hover:text-purple-200 px-2 py-0.5 rounded-full border border-slate-700 hover:border-purple-500/40 transition-all flex items-center gap-1"
                            >
                                <Sparkles className="w-2.5 h-2.5 text-purple-400" />
                                {qp}
                            </button>
                        ))}
                    </div>

                    {/* Input Footer */}
                    <div className="p-2.5 bg-slate-950 border-t border-slate-800/80 flex items-center gap-2">
                        <Input
                            value={prompt}
                            onChange={(e) => setPrompt(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                            placeholder={isSuperadmin ? "Ask about company issues, tickets, fixes..." : "Ask Copilot about sales, taxes, cashflow..."}
                            className="bg-slate-900 border-slate-800 text-slate-100 placeholder:text-slate-500 text-xs focus:ring-purple-500/50 h-9"
                        />
                        <Button
                            onClick={() => handleSend()}
                            disabled={loading || !prompt.trim()}
                            size="icon"
                            className="bg-purple-600 hover:bg-purple-700 text-white shrink-0 h-9 w-9"
                        >
                            <Send className="w-3.5 h-3.5" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function CopilotFloatingChat() {
    return (
        <CopilotErrorBoundary>
            <CopilotFloatingChatInner />
        </CopilotErrorBoundary>
    );
}
