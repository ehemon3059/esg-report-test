<?php
session_start();

// Logged-in users go straight to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /esg-report-test/phase3/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESG Reporting Platform — Sustainability Made Simple</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes shimmer {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .animate-shimmer { animation: shimmer 2.5s infinite; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.7s ease forwards; }
        .fade-up-delay-1 { animation: fadeUp 0.7s 0.15s ease both; }
        .fade-up-delay-2 { animation: fadeUp 0.7s 0.30s ease both; }
        .fade-up-delay-3 { animation: fadeUp 0.7s 0.45s ease both; }

        .gradient-hero {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 40%, #0f766e 100%);
        }
        .card-hover {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.12);
        }
        * { transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">

<!-- ═══════════════════════════════════════════════════════════
     HEADER / NAV
═══════════════════════════════════════════════════════════ -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <span class="text-lg font-bold text-gray-900">ESG Platform</span>
        </div>
        <nav class="hidden md:flex items-center gap-6 text-base text-gray-600 font-medium">
            <a href="#phases"   class="hover:text-emerald-700 transition-colors">Features</a>
            <a href="#workflow" class="hover:text-emerald-700 transition-colors">Workflow</a>
            <a href="#standards" class="hover:text-emerald-700 transition-colors">Standards</a>
        </nav>
        <div class="flex items-center gap-3">
            <a href="/esg-report-test/auth/login.php"
               class="text-base font-medium text-gray-700 hover:text-emerald-700 transition-colors px-4 py-2">
                Sign In
            </a>
            <a href="/esg-report-test/auth/register.php"
               class="text-base font-semibold bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-2 rounded-xl hover:from-emerald-700 hover:to-teal-700 transition-all shadow-md hover:shadow-lg">
                Get Started
            </a>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════
     HERO
═══════════════════════════════════════════════════════════ -->
<section class="gradient-hero text-white py-24 md:py-32 relative overflow-hidden">
    <!-- shimmer overlay -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -inset-full h-full w-1/3 bg-white/5 skew-x-12 animate-shimmer"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="max-w-3xl fade-up">
            <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 text-sm font-medium mb-6 backdrop-blur">
                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                ESRS · EU Taxonomy · GHG Protocol Ready
            </div>
            <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-6">
                ESG Reporting<br>
                <span class="text-emerald-300">Made Simple</span>
            </h1>
            <p class="text-lg md:text-xl text-emerald-100 mb-10 leading-relaxed">
                A multi-phase platform for companies to collect sustainability data,
                calculate Scope 1 &amp; 2 emissions, complete ESRS disclosures,
                and generate a downloadable ESG PDF report.
            </p>
            <div class="flex flex-wrap gap-4">
                <a href="/esg-report-test/auth/register.php"
                   class="inline-flex items-center gap-2 bg-white text-emerald-700 font-bold text-base px-8 py-3.5 rounded-xl hover:bg-emerald-50 transition-all shadow-lg hover:shadow-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Create Free Account
                </a>
                <a href="/esg-report-test/auth/login.php"
                   class="inline-flex items-center gap-2 border border-white/40 text-white font-semibold text-base px-8 py-3.5 rounded-xl hover:bg-white/10 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign In
                </a>
            </div>
        </div>
    </div>

    <!-- Stat strip -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-20">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 fade-up-delay-1">
            <?php
            $stats = [
                ['12', 'Reporting Steps',     'from Registration to PDF'],
                ['5',  'ESRS Topics',         'E1–E5 Environmental Coverage'],
                ['4',  'Social Standards',    'S1–S4 & G1 Governance'],
                ['9+', 'Emission Factors',    'DEFRA 2024 Seeded Data'],
            ];
            foreach ($stats as $s): ?>
            <div class="bg-white/10 border border-white/20 rounded-2xl p-5 backdrop-blur text-center">
                <div class="text-3xl font-extrabold text-white mb-1"><?= $s[0] ?></div>
                <div class="text-sm font-semibold text-emerald-200"><?= $s[1] ?></div>
                <div class="text-xs text-emerald-300 mt-1"><?= $s[2] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     USER WORKFLOW
═══════════════════════════════════════════════════════════ -->
<section id="workflow" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">From Registration to PDF in 6 Phases</h2>
            <p class="text-gray-500 text-base max-w-2xl mx-auto">
                Follow a guided workflow. Each phase builds on the last — complete them in order to unlock your full ESG report.
            </p>
        </div>

        <div class="relative">
            <!-- connecting line -->
            <div class="hidden md:block absolute top-8 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-200 via-teal-300 to-emerald-200 mx-16"></div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 relative">
                <?php
                $steps = [
                    ['1','Register & Login',   'from-emerald-500 to-teal-500',   'Create your company account'],
                    ['2','Add Sites',          'from-teal-500 to-cyan-500',       'Facilities & locations'],
                    ['3','Log Emissions',      'from-cyan-500 to-blue-500',       'Fuel & energy data'],
                    ['4','Environmental',      'from-blue-500 to-indigo-500',     'ESRS E1–E5 disclosures'],
                    ['5','Social & Gov.',      'from-indigo-500 to-violet-500',   'S1–S4 & G1 data'],
                    ['6','Generate PDF',       'from-violet-500 to-purple-500',   'Download ESG report'],
                ];
                foreach ($steps as $step): ?>
                <div class="flex flex-col items-center text-center gap-3">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br <?= $step[2] ?> flex items-center justify-center shadow-lg relative z-10">
                        <span class="text-white font-extrabold text-xl"><?= $step[0] ?></span>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-gray-900"><?= $step[1] ?></div>
                        <div class="text-xs text-gray-500 mt-0.5"><?= $step[3] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     PHASE FEATURE CARDS
═══════════════════════════════════════════════════════════ -->
<section id="phases" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Everything You Need for ESG Compliance</h2>
            <p class="text-gray-500 text-base max-w-2xl mx-auto">
                Each module maps to the exact data the EU requires under ESRS, the GHG Protocol, and the EU Taxonomy Regulation.
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">

            <!-- Phase 3 - Emissions -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 card-hover">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full">Phase 3</span>
                    <span class="text-xs text-gray-400">GHG Protocol</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Emissions Data Collection</h3>
                <p class="text-base text-gray-500 mb-5">
                    Log fuel consumption (Scope 1) and energy usage (Scope 2). The platform automatically calculates tCO₂e using DEFRA 2024 emission factors with regional fallback.
                </p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>Scope 1 — Diesel, Petrol, Natural Gas, LPG</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>Scope 2 — Electricity (Location &amp; Market-Based)</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>Live emissions dashboard with period filter</li>
                </ul>
            </div>

            <!-- Phase 4 - Environmental -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 card-hover">
                <div class="w-12 h-12 rounded-xl bg-teal-50 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold bg-teal-100 text-teal-700 px-2.5 py-1 rounded-full">Phase 4</span>
                    <span class="text-xs text-gray-400">ESRS E1–E5</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Environmental Reporting</h3>
                <p class="text-base text-gray-500 mb-5">
                    ESRS 2 General Disclosures and five environmental topic accordions — covering climate, pollution, water, biodiversity, and circular economy.
                </p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-teal-500 rounded-full"></span>E1 Climate Change — policy &amp; reduction targets</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-teal-500 rounded-full"></span>E2–E5 Pollution, Water, Biodiversity, Circular</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-teal-500 rounded-full"></span>DRAFT → APPROVED status workflow</li>
                </ul>
            </div>

            <!-- Phase 5 - Social & Governance -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 card-hover">
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full">Phase 5</span>
                    <span class="text-xs text-gray-400">ESRS S1–S4 · G1</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Social &amp; Governance</h3>
                <p class="text-base text-gray-500 mb-5">
                    Capture social disclosures across own workforce, value chain, communities, and consumers — plus board governance, diversity, and anti-corruption controls.
                </p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>S1 Own Workforce — headcount, health &amp; safety</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>S2–S4 Supply chain, communities, consumers</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>G1 Board diversity &amp; ESG oversight</li>
                </ul>
            </div>

            <!-- Phase 6A - EU Taxonomy -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 card-hover">
                <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full">Phase 6A</span>
                    <span class="text-xs text-gray-400">EU Taxonomy</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">EU Taxonomy Alignment</h3>
                <p class="text-base text-gray-500 mb-5">
                    Report on taxonomy-eligible and aligned Revenue, CapEx, and OpEx percentages. Includes DNSH assessment and minimum social safeguards status.
                </p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>Revenue, CapEx &amp; OpEx KPIs</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>DNSH — Do No Significant Harm assessment</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>Minimum social safeguards status</li>
                </ul>
            </div>

            <!-- Phase 6B - Assurance -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7 card-hover">
                <div class="w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold bg-violet-100 text-violet-700 px-2.5 py-1 rounded-full">Phase 6B</span>
                    <span class="text-xs text-gray-400">ISAE 3000</span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Assurance &amp; Audit</h3>
                <p class="text-base text-gray-500 mb-5">
                    Document third-party assurance engagements. Track the four-item readiness checklist with a live progress bar before report generation.
                </p>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>Provider, level (limited/reasonable), standard</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>4-item readiness checklist with progress bar</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-violet-500 rounded-full"></span>Scope description &amp; conclusion narrative</li>
                </ul>
            </div>

            <!-- PDF Report -->
            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-2xl shadow-lg p-7 card-hover text-white">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold bg-white/20 text-white px-2.5 py-1 rounded-full">Phase 7</span>
                    <span class="text-xs text-emerald-200">TCPDF</span>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Generate ESG Report PDF</h3>
                <p class="text-base text-emerald-100 mb-5">
                    One click to compile all 6 phases into a formatted A4 PDF — company profile, emissions summary, all ESRS sections, EU Taxonomy KPIs, and assurance details.
                </p>
                <a href="/esg-report-test/auth/register.php"
                   class="inline-flex items-center gap-2 bg-white text-emerald-700 font-bold text-sm px-5 py-2.5 rounded-xl hover:bg-emerald-50 transition-all">
                    Start Now
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     STANDARDS BADGES
═══════════════════════════════════════════════════════════ -->
<section id="standards" class="py-16 bg-white border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-center text-sm font-semibold text-gray-400 uppercase tracking-widest mb-10">
            Built for these frameworks &amp; standards
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <?php
            $badges = [
                ['ESRS E1–E5',       'Climate, Pollution, Water, Biodiversity, Circular Economy', 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                ['ESRS S1–S4',       'Workforce, Value Chain, Communities, Consumers',            'bg-teal-50 text-teal-800 border-teal-200'],
                ['ESRS G1',          'Business Conduct & Governance',                             'bg-cyan-50 text-cyan-800 border-cyan-200'],
                ['GHG Protocol',     'Scope 1 & 2 Emission Accounting',                          'bg-blue-50 text-blue-800 border-blue-200'],
                ['EU Taxonomy',      'Regulation (EU) 2020/852',                                 'bg-indigo-50 text-indigo-800 border-indigo-200'],
                ['ISAE 3000',        'Third-Party Assurance Standard',                           'bg-violet-50 text-violet-800 border-violet-200'],
                ['DEFRA 2024',       'UK Emission Factor Database',                              'bg-gray-50 text-gray-800 border-gray-200'],
            ];
            foreach ($badges as $b): ?>
            <div class="border <?= $b[2] ?> rounded-xl px-5 py-3 text-center">
                <div class="text-sm font-bold"><?= $b[0] ?></div>
                <div class="text-xs mt-0.5 opacity-70"><?= $b[1] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     CTA
═══════════════════════════════════════════════════════════ -->
<section class="gradient-hero py-20">
    <div class="max-w-3xl mx-auto px-4 text-center text-white">
        <h2 class="text-3xl md:text-4xl font-extrabold mb-5">Ready to start your ESG journey?</h2>
        <p class="text-emerald-100 text-base mb-10">
            Register your company, add your sites, and begin logging emissions data today.
            Your first ESG PDF report is just 6 phases away.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="/esg-report-test/auth/register.php"
               class="inline-flex items-center gap-2 bg-white text-emerald-700 font-bold text-base px-8 py-3.5 rounded-xl hover:bg-emerald-50 transition-all shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Create Account
            </a>
            <a href="/esg-report-test/auth/login.php"
               class="inline-flex items-center gap-2 border border-white/40 text-white font-semibold text-base px-8 py-3.5 rounded-xl hover:bg-white/10 transition-all">
                Sign In →
            </a>
        </div>
        <p class="text-emerald-300 text-sm mt-6">
            Default demo login: <strong class="text-white">admin@example.com</strong> / <strong class="text-white">admin123</strong>
        </p>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════ -->
<footer class="bg-gray-900 text-gray-400 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <span class="text-sm font-semibold text-gray-300">ESG Reporting Platform</span>
        </div>
        <div class="text-xs text-gray-500 text-center">
            PHP 8 · MySQL 8 · jQuery 3 · Tailwind CSS · TCPDF &nbsp;|&nbsp;
            Emission factors: DEFRA 2024
        </div>
        <div class="flex gap-4 text-sm">
            <a href="/esg-report-test/auth/login.php"    class="hover:text-emerald-400 transition-colors">Sign In</a>
            <a href="/esg-report-test/auth/register.php" class="hover:text-emerald-400 transition-colors">Register</a>
        </div>
    </div>
</footer>

</body>
</html>
