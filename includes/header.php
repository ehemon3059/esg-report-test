<?php
// header.php - reusable HTML header and navigation
// Note: session_start() is NOT called here — auth.php already handles it
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

function isActive(string $path): string {
    global $currentPath;
    return (strpos($currentPath, $path) !== false) ? 'bg-emerald-700 text-white' : 'text-emerald-100 hover:bg-emerald-700 hover:text-white';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'ESG Reporting Platform', ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { font-size: 1rem; }
        .nav-link { display: block; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; transition: background-color 0.15s; }
        .accordion-content { display: none; }
        .accordion-toggle:checked + label + .accordion-content { display: block; }
        .accordion-toggle:checked + label .accordion-icon { transform: rotate(180deg); }
        .accordion-icon { transition: transform 0.2s; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="flex min-h-screen">
    <!-- Sidebar Navigation -->
    <nav class="w-64 bg-emerald-800 text-white flex-shrink-0 flex flex-col min-h-screen">
        <!-- Logo / Brand -->
        <div class="px-6 py-5 border-b border-emerald-700">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 bg-emerald-500 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <span class="text-white font-bold text-base block">ESG Platform</span>
                    <span class="text-emerald-300 text-xs">Reporting Suite</span>
                </div>
            </div>
        </div>

        <!-- User Info -->
        <?php if (!empty($_SESSION['user_id'])): ?>
        <div class="px-4 py-3 bg-emerald-900 border-b border-emerald-700">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 bg-emerald-500 rounded-full flex items-center justify-center text-xs font-bold">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-white text-xs font-medium truncate"><?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-emerald-300 text-xs capitalize"><?= htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation Links -->
        <div class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

            <!-- Dashboard -->
            <a href="<?= BASE_URL ?>/phase3/dashboard.php" class="nav-link <?= isActive('/phase3/dashboard') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/></svg>
                    <span>Dashboard</span>
                </span>
            </a>

            <!-- Sites -->
            <a href="<?= BASE_URL ?>/sites/index.php" class="nav-link <?= isActive('/sites/') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Sites</span>
                </span>
            </a>

            <!-- Emissions Section Label -->
            <div class="pt-3 pb-1">
                <p class="text-emerald-400 text-xs font-semibold uppercase tracking-wider px-2">Emissions</p>
            </div>

            <!-- Fuel Consumption -->
            <a href="<?= BASE_URL ?>/phase3/fuel.php" class="nav-link <?= isActive('/phase3/fuel') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    <span>Fuel Consumption</span>
                </span>
            </a>

            <!-- Energy Consumption -->
            <a href="<?= BASE_URL ?>/phase3/energy.php" class="nav-link <?= isActive('/phase3/energy') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Energy Consumption</span>
                </span>
            </a>

            <!-- Scope 3 Emissions -->
            <a href="<?= BASE_URL ?>/phase3/scope3.php" class="nav-link <?= isActive('/phase3/scope3') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                    <span>Scope 3 Emissions</span>
                </span>
            </a>

            <!-- Emission Factors -->
            <a href="<?= BASE_URL ?>/phase3/emission-factors.php" class="nav-link <?= isActive('/phase3/emission-factors') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Emission Factors</span>
                </span>
            </a>

            <!-- Reporting Section Label -->
            <div class="pt-3 pb-1">
                <p class="text-emerald-400 text-xs font-semibold uppercase tracking-wider px-2">ESRS Disclosures</p>
            </div>

            <!-- ESRS 2 -->
            <a href="<?= BASE_URL ?>/phase4/esrs2.php" class="nav-link <?= isActive('/phase4/esrs2') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>ESRS 2 Disclosures</span>
                </span>
            </a>

            <!-- Environmental -->
            <a href="<?= BASE_URL ?>/phase4/environmental.php" class="nav-link <?= isActive('/phase4/environmental') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    <span>Environmental</span>
                </span>
            </a>

            <!-- Social -->
            <a href="<?= BASE_URL ?>/phase5/social.php" class="nav-link <?= isActive('/phase5/social') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Social</span>
                </span>
            </a>

            <!-- Governance -->
            <a href="<?= BASE_URL ?>/phase5/governance.php" class="nav-link <?= isActive('/phase5/governance') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Governance</span>
                </span>
            </a>

            <!-- Compliance Section Label -->
            <div class="pt-3 pb-1">
                <p class="text-emerald-400 text-xs font-semibold uppercase tracking-wider px-2">Compliance</p>
            </div>

            <!-- EU Taxonomy -->
            <a href="<?= BASE_URL ?>/phase6/taxonomy.php" class="nav-link <?= isActive('/phase6/taxonomy') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                    <span>EU Taxonomy</span>
                </span>
            </a>

            <!-- Assurance -->
            <a href="<?= BASE_URL ?>/phase6/assurance.php" class="nav-link <?= isActive('/phase6/assurance') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Assurance</span>
                </span>
            </a>

            <!-- Report Section Label -->
            <div class="pt-3 pb-1">
                <p class="text-emerald-400 text-xs font-semibold uppercase tracking-wider px-2">Reports</p>
            </div>

            <!-- Generate Report -->
            <a href="<?= BASE_URL ?>/report/generate.php" class="nav-link <?= isActive('/report/generate') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Generate Report</span>
                </span>
            </a>

            <!-- Company Profile -->
            <a href="<?= BASE_URL ?>/company/create.php" class="nav-link <?= isActive('/company/') ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Company Profile</span>
                </span>
            </a>
        </div>

        <!-- Logout -->
        <div class="px-3 py-4 border-t border-emerald-700">
            <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link text-emerald-200 hover:bg-red-600 hover:text-white">
                <span class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>Logout</span>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto">
        <!-- Top Bar -->
        <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($pageTitle ?? 'ESG Reporting Platform', ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="text-sm text-gray-500">
                <?= date('l, F j, Y') ?>
            </div>
        </div>
        <div class="p-6">
