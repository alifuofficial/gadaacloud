import React, { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Sparkles, Bot, X, Send, ShieldCheck, ArrowRight, RefreshCw, MessageSquare, Terminal } from 'lucide-react';

interface ChatMessage {
    id: string;
    sender: 'user' | 'copilot';
    text: string;
    timestamp: string;
    confidence?: number;
}

export default function CopilotFloatingChat() {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const [isOpen, setIsOpen] = useState(false);
    const [prompt, setPrompt] = useState('');
    const [loading, setLoading] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([
        {
            id: '1',
            sender: 'copilot',
            text: `👋 Hello ${auth?.user?.name || 'User'}! I am **GadaaCloud Copilot**.\n\nI analyze your ERP data in real-time while strictly enforcing your active Spatie permissions. How can I assist you with financial forecasting, Ethiopian tax engine calculations, or payroll today?`,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            confidence: 0.99,
        },
    ]);

    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        if (isOpen) {
            scrollToBottom();
        }
    }, [messages, isOpen]);

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
            const response = await axios.post(route('settings.copilot.query'), {
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

    const quickPrompts = [
        'Predict next 6-month cashflow',
        'Calculate Ethiopian employment tax',
        'Sales & Receivables summary',
        'Staff Payroll breakdown',
    ];

    return (
        <div className="fixed bottom-6 right-6 z-50 font-sans">
            {/* Floating Action Trigger Button */}
            {!isOpen && (
                <button
                    onClick={() => setIsOpen(true)}
                    className="group relative flex items-center gap-3 bg-gradient-to-r from-purple-700 via-indigo-700 to-slate-900 text-white p-3.5 pr-5 rounded-full shadow-2xl hover:shadow-purple-500/25 transition-all hover:scale-105 border border-purple-400/30"
                >
                    <div className="relative">
                        <div className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center backdrop-blur-md">
                            <Bot className="w-6 h-6 text-purple-200 group-hover:rotate-12 transition-transform" />
                        </div>
                        <span className="absolute -top-1 -right-1 flex h-3 w-3">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                    </div>
                    <div className="text-left">
                        <div className="text-xs font-bold tracking-wide text-white flex items-center gap-1">
                            GadaaCloud Copilot
                            <Sparkles className="w-3 h-3 text-purple-300 animate-pulse" />
                        </div>
                        <div className="text-[10px] text-purple-200/80 font-mono">Ask ERP AI Assistant</div>
                    </div>
                </button>
            )}

            {/* Chatbot Terminal Modal Window */}
            {isOpen && (
                <div className="w-[380px] sm:w-[440px] h-[600px] bg-slate-950/95 border border-purple-500/30 rounded-2xl shadow-2xl backdrop-blur-xl flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-5 duration-300">
                    {/* Header */}
                    <div className="p-4 bg-gradient-to-r from-purple-950 via-slate-900 to-indigo-950 border-b border-purple-500/20 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="p-2 rounded-xl bg-purple-500/20 border border-purple-400/30">
                                <Bot className="w-5 h-5 text-purple-300" />
                            </div>
                            <div>
                                <div className="text-sm font-bold text-white flex items-center gap-2">
                                    GadaaCloud Copilot
                                    <Badge className="bg-emerald-500/20 text-emerald-300 border-emerald-500/30 text-[9px] px-1.5 py-0.5">
                                        <ShieldCheck className="w-3 h-3 mr-1" />
                                        Permission-Aware
                                    </Badge>
                                </div>
                                <div className="text-[10px] text-slate-400 flex items-center gap-1 font-mono">
                                    <span className="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Google Gemini 1.5 Flash Active
                                </div>
                            </div>
                        </div>
                        <button
                            onClick={() => setIsOpen(false)}
                            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    {/* Messages Body */}
                    <div className="flex-1 p-4 overflow-y-auto space-y-4 text-xs">
                        {messages.map((msg) => (
                            <div
                                key={msg.id}
                                className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[85%] rounded-2xl p-3.5 space-y-1.5 ${
                                        msg.sender === 'user'
                                            ? 'bg-purple-600 text-white rounded-br-none'
                                            : 'bg-slate-900 border border-slate-800 text-slate-200 rounded-bl-none shadow-sm'
                                    }`}
                                >
                                    <div className="whitespace-pre-wrap leading-relaxed">
                                        {msg.text.split('\n').map((line, i) => (
                                            <p key={i} className={line.startsWith('•') || line.startsWith('  -') ? 'my-0.5 font-sans' : 'my-1'}>
                                                {line}
                                            </p>
                                        ))}
                                    </div>
                                    <div className="flex items-center justify-between text-[10px] opacity-75 pt-1 font-mono">
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
                                <div className="bg-slate-900 border border-slate-800 text-slate-400 rounded-2xl rounded-bl-none p-3 flex items-center gap-2">
                                    <RefreshCw className="w-3.5 h-3.5 animate-spin text-purple-400" />
                                    <span>Analyzing ERP ledger &amp; permissions...</span>
                                </div>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {/* Quick Prompts */}
                    <div className="px-4 py-2 bg-slate-900/60 border-t border-slate-800/80 flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                        {quickPrompts.map((qp, idx) => (
                            <button
                                key={idx}
                                onClick={() => handleSend(qp)}
                                className="whitespace-nowrap text-[10px] bg-slate-800 hover:bg-purple-900/50 text-slate-300 hover:text-purple-200 px-2.5 py-1 rounded-full border border-slate-700 hover:border-purple-500/40 transition-all flex items-center gap-1"
                            >
                                <Sparkles className="w-2.5 h-2.5 text-purple-400" />
                                {qp}
                            </button>
                        ))}
                    </div>

                    {/* Input Footer */}
                    <div className="p-3 bg-slate-950 border-t border-slate-800/80 flex items-center gap-2">
                        <Input
                            value={prompt}
                            onChange={(e) => setPrompt(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                            placeholder="Ask Copilot about sales, taxes, cashflow..."
                            className="bg-slate-900 border-slate-800 text-slate-100 placeholder:text-slate-500 text-xs focus:ring-purple-500/50"
                        />
                        <Button
                            onClick={() => handleSend()}
                            disabled={loading || !prompt.trim()}
                            size="icon"
                            className="bg-purple-600 hover:bg-purple-700 text-white shrink-0"
                        >
                            <Send className="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
