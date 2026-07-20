import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface FAQProps {
    settings?: any;
}

const DEFAULT_ITEMS = [
    { question: 'Does it support Ethiopian Birr (ETB)?',          answer: 'Yes — accounting, invoices, payslips and reports run in ETB by default. Multi-currency support lets exporters work in USD/EUR while keeping base books in Birr.' },
    { question: 'Can I issue VAT and TIN compliant invoices?',  answer: 'Absolutely. VAT rates, TIN fields and Ethiopian tax-authority compliant invoice formats are pre-configured out of the box.' },
    { question: 'What AI capabilities does GadaaCloud have?',  answer: 'GadaaCloud features a next-gen AI Copilot. It automatically parses vendor bills and receipts (supporting optical reading), auto-categorizes accounting transactions, generates predictive cash flow charts, and helps draft business reports in English & Amharic.' },
    { question: 'Does it support Real Estate and Property Management?',  answer: 'Yes. GadaaCloud has a built-in property management module that tracks multi-tenant lease agreements, automates monthly Birr invoices, manages security deposits, and handles maintenance service requests.' },
    { question: 'How does it handle Coffee Agribusiness and exporting?',  answer: 'We have pre-built coffee tracking sheets to follow coffee bags from dry mills to Addis Ababa stores and down to Djibouti. Trace lot numbers, monitor ECX auction grades, and manage export shipping contracts.' },
    { question: 'Can I track multi-warehouse inventory levels?',  answer: 'Yes. The system tracks real-time inventory balances across multiple depot locations, supports barcode scanning, generates stock transfer logs, and calculates weighted-average valuations.' },
    { question: 'Is the interface available in Amharic and Afaan Oromo?',         answer: 'Yes. Users can toggle the entire platform dashboard into Amharic, English, or Afaan Oromo instantly based on their personal preference.' }
];

export default function FAQ({ settings }: FAQProps) {
    const { t } = useTranslation();
    const sectionData = settings?.config_sections?.sections?.faq || {};
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const title = sectionData.title || 'Frequently Asked Questions';
    const subtitle = sectionData.subtitle || 'Everything Ethiopian businesses want to know about GadaaCloud.';
    const items = sectionData.items?.length > 0 ? sectionData.items : DEFAULT_ITEMS;
    const [openIndex, setOpenIndex] = useState<number>(0);

    return (
        <section id="faq" className="bg-white dark:bg-gray-950 py-24">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center mb-16">
                    <span
                        className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase mb-4"
                        style={{ backgroundColor: `${colors.primary}15`, color: colors.primary }}
                    >
                        {t('FAQ')}
                    </span>
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6 leading-tight tracking-tight">
                        {title}
                    </h2>
                    <p className="text-lg text-gray-650 dark:text-gray-400 leading-relaxed">{subtitle}</p>
                </div>

                <div className="space-y-4">
                    {items.map((item: any, index: number) => {
                        const isOpen = openIndex === index;
                        return (
                            <div
                                key={index}
                                className={`rounded-2xl border transition-all duration-300 overflow-hidden ${
                                    isOpen
                                        ? 'border-transparent dark:border-transparent bg-white dark:bg-gray-900 shadow-xl dark:shadow-black/20'
                                        : 'border-gray-150 dark:border-gray-850 bg-white dark:bg-gray-900 hover:border-gray-200 dark:hover:border-gray-700'
                                 }`}
                                style={isOpen ? { boxShadow: `0 10px 30px -10px ${colors.primary}30` } : undefined}
                            >
                                <button
                                    onClick={() => setOpenIndex(isOpen ? -1 : index)}
                                    className="w-full px-6 md:px-8 py-5 md:py-6 flex items-center justify-between text-left gap-4"
                                >
                                    <span className="text-lg font-bold text-gray-905 dark:text-white">{item.question}</span>
                                    <span
                                        className="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center transition-all duration-300"
                                        style={{
                                            backgroundColor: isOpen ? colors.primary : `${colors.primary}10`,
                                            color: isOpen ? '#fff' : colors.primary
                                        }}
                                    >
                                        <ChevronDown className={`h-5 w-5 transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`} />
                                    </span>
                                </button>
                                <div
                                    className={`grid transition-all duration-300 ease-in-out ${
                                        isOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'
                                    }`}
                                >
                                    <div className="overflow-hidden">
                                        <p className="px-6 md:px-8 pb-6 md:pb-7 text-gray-600 dark:text-gray-400 leading-relaxed text-sm">
                                            {item.answer}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}