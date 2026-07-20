<?php

namespace Workdo\LandingPage\Database\Seeders;

use Illuminate\Database\Seeder;
use Workdo\LandingPage\Models\LandingPageSetting;
use Illuminate\Support\Facades\Log;

class LandingPageSettingSeeder extends Seeder
{
    public function run()
    {
        if (LandingPageSetting::exists()) {
            return;
        }

        try {
            LandingPageSetting::create($this->getDefaultSettings());
        } catch (\Exception $e) {
            Log::error('Failed to seed landing page settings: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getDefaultSettings(): array
    {
        return [
            'company_name'    => 'GadaaCloud ERP',
            'contact_email'   => 'hello@gadaacloud.et',
            'contact_phone'   => '+251 11 553 0000',
            'contact_address' => 'Bole Road, Addis Ababa, Ethiopia',
            'config_sections' => $this->getDefaultConfigSections()
        ];
    }

    private function getDefaultConfigSections(): array
    {
        return [
            'sections'            => $this->getDefaultSections(),
            'section_visibility'  => $this->getDefaultVisibility(),
            'section_order'       => $this->getDefaultOrder(),
            'colors'              => $this->getDefaultColors()
        ];
    }

    private function getDefaultSections(): array
    {
        return [
            'hero' => [
                'variant'                => 'hero1',
                'title'                  => 'Transform Your Ethiopian Business with GadaaCloud',
                'subtitle'               => 'The all-in-one ERP, Accounting, CRM, POS, HRM & Project Management platform built for Ethiopia — run operations in Birr, Amharic and on cloud across Addis Ababa and every region.',
                'primary_button_text'    => 'Start Free Trial',
                'primary_button_link'    => route('register'),
                'secondary_button_text'  => 'Request a Demo',
                'secondary_button_link'  => route('login'),
                'highlight_text'         => 'Ethiopian Business',
                'image'                  => ''
            ],
            'header' => [
                'variant'              => 'header1',
                'company_name'         => 'GadaaCloud ERP',
                'cta_text'             => 'Get Started',
                'enable_pricing_link'  => true,
                'navigation_items' => [
                    ['text' => 'Home',       'href' => route('landing.page')],
                    ['text' => 'Features',   'href' => '#features'],
                    ['text' => 'Industries', 'href' => '#industries'],
                    ['text' => 'Pricing',    'href' => route('pricing.page')]
                ]
            ],
            'stats' => [
                'variant' => 'stats2',
                'stats'   => [
                    ['label' => 'Ethiopian Businesses Empowered', 'value' => '5,000+'],
                    ['label' => 'Regions Covered in Ethiopia',    'value' => '11'],
                    ['label' => 'Uptime Guarantee',                'value' => '99.9%'],
                    ['label' => 'Local Support in Amharic',        'value' => '24/7']
                ]
            ],
            'features' => [
                'variant'  => 'features3',
                'title'    => 'Powerful Features Built for Ethiopian Enterprises',
                'subtitle' => 'Everything your business needs — in Birr, in Amharic, aligned with VAT, TIN and Ethiopian labour law.',
                'features' => $this->getDefaultFeatures()
            ],
            'modules' => [
                'variant'  => 'modules5',
                'title'    => 'Complete Business Solutions for Ethiopia',
                'subtitle' => 'From your Addis Ababa HQ to branches across all regions — manage everything from one integrated platform.',
                'modules'  => [
                    [
                        'key'         => 'accounting',
                        'label'       => 'Accounting (ETB)',
                        'title'       => 'Accounting in Birr with VAT & TIN',
                        'description' => 'Issue ETB invoices that satisfy the Ethiopian tax authority, track VAT, reconcile CBE, Dashen, Awash and Telebirr transactions, and produce board-ready financial reports in minutes.',
                        'image'       => 'packages/workdo/LandingPage/src/Resources/assets/img/accounting.png'
                    ],
                    [
                        'key'         => 'pos',
                        'label'       => 'Retail POS',
                        'title'       => 'Point of Sale for Ethiopian Retailers',
                        'description' => 'Serve customers in seconds at your shop or café. Accept cash, CBE/Telebirr, print Amharic receipts — works offline and syncs when online.',
                        'image'       => 'packages/workdo/LandingPage/src/Resources/assets/img/pos.png'
                    ],
                    [
                        'key'         => 'crm',
                        'label'       => 'CRM & Sales',
                        'title'       => 'Customer Relationship Management',
                        'description' => 'Build relationships with buyers across East Africa and worldwide. Track leads, deals and customer history with full Amharic support.',
                        'image'       => 'packages/workdo/LandingPage/src/Resources/assets/img/crm.png'
                    ],
                    [
                        'key'         => 'hrm',
                        'label'       => 'HR & Payroll',
                        'title'       => 'HRM & Birr Payroll',
                        'description' => 'Manage employees, attendance, leave and payroll with payslips in Ethiopian Birr — aligned with local labour-law requirements.',
                        'image'       => 'packages/workdo/LandingPage/src/Resources/assets/img/hrm.png'
                    ],
                    [
                        'key'         => 'taskly',
                        'label'       => 'Project Management',
                        'title'       => 'Projects & Task Management',
                        'description' => 'Deliver client projects on time with Kanban boards, Gantt charts and team collaboration — perfect for Ethiopian service firms and consultancies.',
                        'image'       => 'packages/workdo/LandingPage/src/Resources/assets/img/project.png'
                    ]
                ]
            ],
            'benefits' => [
                'variant'  => 'benefits2',
                'title'    => 'Built for Ethiopian Business — Why Choose Us?',
                'benefits' => [
                    ['title' => 'Local Compliance Built-In',       'description' => 'VAT, TIN and Ethiopian labour-law requirements are pre-configured. Issue ETB invoices that satisfy the tax authority, generate audit-ready financials and run payroll aligned with local regulations.'],
                    ['title' => 'Amharic & Multilingual Interface', 'description' => 'Work in Amharic, English or Afaan Oromo — your team switches languages instantly. Reports, dashboards and customer-facing receipts support Amharic text.'],
                    ['title' => 'Accounting in Ethiopian Birr',      'description' => 'Run your books entirely in ETB with multi-currency support for exporters. Reconcile CBE, Dashen, Awash and Telebirr transactions and produce board-ready financial statements.'],
                    ['title' => 'Coffee, Retail & Agribusiness Ready', 'description' => 'From specialty coffee exporters in Yirgacheffe to retail chains in Addis Ababa and agribusinesses in the regions — purpose-built workflows handle lots, inventory and supply contracts.'],
                    ['title' => 'Reliable Cloud Across All Regions', 'description' => 'Access your ERP from Addis Ababa, Dire Dawa, Mekelle, Bahir Dar, Hawassa, Jimma, Gondar, Adama and beyond. 99.9% uptime with offline POS keep business running on patchy connectivity.'],
                    ['title' => 'Affordable for Ethiopian SMEs',     'description' => 'Flexible subscription pricing in Birr scales with your team size. No heavy upfront investment — small businesses, cooperatives and growing enterprises get enterprise tools at SME-friendly rates.']
                ]
            ],
            'gallery' => [
                'variant'  => 'gallery2',
                'title'    => 'See GadaaCloud in Action',
                'subtitle' => 'Real screenshots from Ethiopian businesses using GadaaCloud across Addis Ababa and the regions.',
                'images'   => [
                    'packages/workdo/LandingPage/src/Resources/assets/img/gallery1.jpeg',
                    'packages/workdo/LandingPage/src/Resources/assets/img/gallery2.jpeg',
                    'packages/workdo/LandingPage/src/Resources/assets/img/gallery3.jpeg',
                    'packages/workdo/LandingPage/src/Resources/assets/img/gallery4.jpeg',
                    'packages/workdo/LandingPage/src/Resources/assets/img/gallery5.jpeg',
                    'packages/workdo/LandingPage/src/Resources/assets/img/gallery6.jpeg'
                ]
            ],
            'cta' => [
                'variant'            => 'cta4',
                'title'              => 'Ready to Transform Your Ethiopian Business?',
                'subtitle'           => 'Join thousands of Ethiopian enterprises already using GadaaCloud to run their operations in Birr, Amharic and on cloud.',
                'primary_button'     => 'Start Free Trial',
                'primary_button_link'   => '',
                'secondary_button'   => 'Contact Sales',
                'secondary_button_link' => ''
            ],
            'industries' => [
                'title'    => 'Built for Every Ethiopian Industry',
                'subtitle' => 'Purpose-built workflows for the sectors that drive Ethiopia\'s economy.',
                'items'    => [
                    ['title' => 'Coffee & Agribusiness', 'description' => 'Track coffee lots from Yirgacheffe to Djibouti port — manage ECX inventory, export orders and supplier contracts.', 'icon' => 'Coffee'],
                    ['title' => 'Retail & eCommerce',     'description' => 'Run shops, cafés and online stores with offline-ready POS and multi-branch inventory sync.',                 'icon' => 'ShoppingBag'],
                    ['title' => 'Manufacturing',           'description' => 'Plan production, track raw materials and manage work orders across your factory floor.',                  'icon' => 'Factory'],
                    ['title' => 'Services & Consulting',   'description' => 'Deliver client projects on time with time-tracking, billing and Kanban workflows.',                          'icon' => 'Briefcase'],
                    ['title' => 'Import / Export Trading',  'description' => 'Manage LC documents, shipments and stock across warehouses — with multi-currency accounting in ETB & USD.', 'icon' => 'Ship'],
                    ['title' => 'NGOs & Cooperatives',      'description' => 'Track grants, donor funds and member contributions with audit-ready reporting in Birr.',                  'icon' => 'HeartHandshake']
                ]
            ],
            'testimonials' => [
                'title'    => 'Trusted by Ethiopian Businesses',
                'subtitle' => 'Real stories from real enterprises running on GadaaCloud.',
                'items'    => [
                    ['quote' => 'GadaaCloud lets us reconcile CBE and Telebirr transactions in minutes instead of days. Our monthly close is finally on time.', 'author' => 'Selam Bekele',   'role' => 'CFO',          'company' => 'Bole Coffee Exporters'],
                    ['quote' => 'The offline POS kept our shops running during power cuts. Receipts print in Amharic and sync automatically — game changer.',       'author' => 'Dawit Tesfaye',   'role' => 'Owner',        'company' => 'Merkato Retail Group'],
                    ['quote' => 'Birr payroll aligned with Ethiopian labour law saves us days every month. Employees finally trust their payslips.',                 'author' => 'Hanna Girmachew', 'role' => 'HR Director', 'company' => 'Habesha Garments'],
                    ['quote' => 'We track coffee lots from farm to port in one system. ECX reporting has never been this easy for our export team.',                'author' => 'Yonas Abebe',     'role' => 'GM',           'company' => 'Sidama Coffee Union']
                ]
            ],
            'faq' => [
                'title'    => 'Frequently Asked Questions',
                'subtitle' => 'Everything Ethiopian businesses want to know about GadaaCloud.',
                'items'    => [
                    ['question' => 'Does it support Ethiopian Birr (ETB)?',           'answer' => 'Yes — accounting, invoices, payslips and reports run in ETB by default. Multi-currency support lets exporters work in USD/EUR while keeping base books in Birr.'],
                    ['question' => 'Can I issue VAT and TIN compliant invoices?',     'answer' => 'Absolutely. VAT rates, TIN fields and Ethiopian tax-authority compliant invoice formats are pre-configured out of the box.'],
                    ['question' => 'Is the interface available in Amharic?',           'answer' => 'Yes. GadaaCloud ships with Amharic, English and Afaan Oromo interfaces — your team can switch languages instantly per user.'],
                    ['question' => 'Does it work offline in the regions?',             'answer' => 'Our POS works offline and auto-syncs once connectivity returns. The cloud dashboard works on any browser across all regions of Ethiopia.'],
                    ['question' => 'How much does it cost for a small business?',       'answer' => 'Flexible monthly plans in Birr start small and scale with your team. Contact sales for a tailored quote — no heavy upfront investment required.'],
                    ['question' => 'Can I migrate my existing data?',                  'answer' => 'Yes. We provide CSV/Excel import for customers, products, chart-of-accounts and opening balances, plus assisted migration for larger setups.']
                ]
            ],
            'pricing' => [
                'title'                    => 'Simple pricing that scales with your Ethiopian business',
                'subtitle'                 => 'Billed in Birr with VAT included — no hidden fees, no long-term contracts. Start with 14 days free.',
                'default_subscription_type' => 'pre-package',
                'default_price_type'        => 'monthly',
                'show_pre_package'          => true,
                'show_monthly_yearly_toggle' => true,
                'empty_message'             => 'No plans available yet. Contact us at hello@gadaacloud.et for tailored Ethiopian SME rates.'
            ],
            'footer' => [
                'variant'                  => 'footer1',
                'description'              => 'The all-in-one ERP, Accounting, CRM, POS & HRM platform built for Ethiopian businesses — run operations in Birr, Amharic and on cloud.',
                'email'                    => 'hello@gadaacloud.et',
                'phone'                    => '+251 11 553 0000',
                'newsletter_title'         => 'Join the Ethiopian Business Community',
                'newsletter_description'   => 'Get product updates, tax tips and growth stories for Ethiopian SMEs — straight to your inbox.',
                'newsletter_button_text'   => 'Subscribe',
                'copyright_text'            => '',
                'navigation_sections' => [
                    [
                        'title' => 'Product',
                        'links' => [
                            ['text' => 'Features',   'href' => '#features'],
                            ['text' => 'Industries', 'href' => '#industries'],
                            ['text' => 'Pricing',    'href' => route('pricing.page')],
                            ['text' => 'FAQ',        'href' => '#faq']
                        ]
                    ],
                    [
                        'title' => 'Company',
                        'links' => [
                            ['text' => 'About',   'href' => '#about'],
                            ['text' => 'Contact', 'href' => 'mailto:hello@gadaacloud.et'],
                            ['text' => 'Support', 'href' => '#support']
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getDefaultFeatures(): array
    {
        return [
            ['title' => 'Accounting in Birr',     'description' => 'Full financial management in ETB with VAT & TIN-ready invoicing for the Ethiopian tax authority.', 'icon' => 'Calculator'],
            ['title' => 'Retail POS',             'description' => 'Lightning-fast point-of-sale for shops, cafés & boutiques across Ethiopia.',                  'icon' => 'Store'],
            ['title' => 'Coffee & Agribusiness',  'description' => 'Track coffee lots, inventory & export orders from farm to port.',                             'icon' => 'Coffee'],
            ['title' => 'CRM & Sales',            'description' => 'Manage leads and customers across East Africa from one place.',                              'icon' => 'Users'],
            ['title' => 'HRM & Payroll',          'description' => 'Employee records, attendance & payroll — Birr payslips included.',                            'icon' => 'UserCheck'],
            ['title' => 'Projects & Tasks',       'description' => 'Deliver client projects on time with Kanban and Gantt views.',                               'icon' => 'FolderOpen']
        ];
    }

    private function getDefaultVisibility(): array
    {
        return [
            'header'       => true,
            'hero'         => true,
            'stats'        => true,
            'features'     => true,
            'modules'      => true,
            'industries'   => true,
            'benefits'     => true,
            'testimonials' => true,
            'gallery'      => true,
            'faq'          => true,
            'cta'          => true,
            'footer'       => true,
            'pricing'      => true
        ];
    }

    private function getDefaultOrder(): array
    {
        return ['header', 'hero', 'stats', 'features', 'modules', 'industries', 'benefits', 'testimonials', 'gallery', 'faq', 'cta', 'footer'];
    }

    private function getDefaultColors(): array
    {
        return [
            'primary'   => '#078930',
            'secondary' => '#054a2b',
            'accent'    => '#fcdd09'
        ];
    }
}