<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'ESRS 2 General Disclosures';
$success   = '';
$error     = '';

// Create table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `esrs2_general_disclosures` (
        `id`                            VARCHAR(36)  NOT NULL,
        `company_id`                    VARCHAR(36)  NOT NULL,
        `reporting_period`              VARCHAR(7)   NOT NULL COMMENT 'YYYY-MM',
        `consolidation_scope`           TEXT         DEFAULT NULL,
        `value_chain_boundaries`        TEXT         DEFAULT NULL,
        `board_role_in_sustainability`  TEXT         DEFAULT NULL,
        `esg_integration_in_remuneration` INT        DEFAULT NULL,
        `assessment_process`            TEXT         DEFAULT NULL,
        `created_by`                    VARCHAR(36)  NOT NULL,
        `updated_by`                    VARCHAR(36)  DEFAULT NULL,
        `created_at`                    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`                    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_esrs2_company_period` (`company_id`, `reporting_period`),
        CONSTRAINT `fk_esrs2_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Period selection
$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// Load existing record for current period
$stmt = $pdo->prepare('SELECT * FROM esrs2_general_disclosures WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$existing = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $consolidationScope   = trim($_POST['consolidation_scope']           ?? '');
    $valueChain           = trim($_POST['value_chain_boundaries']        ?? '');
    $boardRole            = trim($_POST['board_role_in_sustainability']  ?? '');
    $esgRemuneration      = $_POST['esg_integration_in_remuneration']   !== '' ? (int)$_POST['esg_integration_in_remuneration'] : null;
    $assessmentProcess    = trim($_POST['assessment_process']            ?? '');

    if ($selectedPeriod === '') {
        $error = 'Reporting period is required.';
    } else {
        if ($existing) {
            // UPDATE
            $stmt = $pdo->prepare('
                UPDATE esrs2_general_disclosures
                SET consolidation_scope = :cs, value_chain_boundaries = :vcb,
                    board_role_in_sustainability = :br, esg_integration_in_remuneration = :esg,
                    assessment_process = :ap, updated_by = :uid, updated_at = NOW()
                WHERE id = :id AND company_id = :cid
            ');
            $stmt->execute([
                ':cs'  => $consolidationScope,
                ':vcb' => $valueChain,
                ':br'  => $boardRole,
                ':esg' => $esgRemuneration,
                ':ap'  => $assessmentProcess,
                ':uid' => user_id(),
                ':id'  => $existing['id'],
                ':cid' => company_id(),
            ]);
        } else {
            // INSERT
            $stmt = $pdo->prepare('
                INSERT INTO esrs2_general_disclosures
                    (id, company_id, reporting_period, consolidation_scope, value_chain_boundaries,
                     board_role_in_sustainability, esg_integration_in_remuneration, assessment_process,
                     created_by, created_at, updated_at)
                VALUES (:id, :cid, :period, :cs, :vcb, :br, :esg, :ap, :uid, NOW(), NOW())
            ');
            $stmt->execute([
                ':id'     => uuid(),
                ':cid'    => company_id(),
                ':period' => $selectedPeriod,
                ':cs'     => $consolidationScope,
                ':vcb'    => $valueChain,
                ':br'     => $boardRole,
                ':esg'    => $esgRemuneration,
                ':ap'     => $assessmentProcess,
                ':uid'    => user_id(),
            ]);
        }
        $success = 'ESRS 2 disclosures saved successfully.';

        // Reload
        $stmt = $pdo->prepare('SELECT * FROM esrs2_general_disclosures WHERE company_id = :cid AND reporting_period = :period');
        $stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
        $existing = $stmt->fetch();
    }
}

// Get all periods for this company
$stmt = $pdo->prepare('SELECT DISTINCT reporting_period FROM esrs2_general_disclosures WHERE company_id = :cid ORDER BY reporting_period DESC');
$stmt->execute([':cid' => company_id()]);
$savedPeriods = $stmt->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="space-y-6 max-w-3xl">
    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">ESRS 2 — General Disclosures</h2>
        <p class="text-gray-500 text-base mt-1">Cross-cutting governance, strategy, and materiality disclosures</p>
    </div>

    <?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-emerald-700 font-medium"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <!-- Period Selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-end gap-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Period</label>
            <input type="month" id="periodSelector" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>"
                   class="px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <button onclick="window.location.href='?period='+document.getElementById('periodSelector').value"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-5 rounded-lg text-base transition">
            Load Period
        </button>
        <?php if (!empty($savedPeriods)): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Saved Periods</label>
            <select onchange="window.location.href='?period='+this.value"
                    class="px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                <option value="">Select...</option>
                <?php foreach ($savedPeriods as $p): ?>
                <option value="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>" <?= $p === $selectedPeriod ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Disclosures for <span class="text-emerald-600"><?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?></span></h3>
            <?php if ($existing): ?>
            <span class="text-xs font-medium text-blue-700 bg-blue-50 px-2.5 py-1 rounded-full">Editing existing record</span>
            <?php else: ?>
            <span class="text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">New record</span>
            <?php endif; ?>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Consolidation Scope -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Consolidation Scope
                        <span class="text-gray-400 font-normal ml-1">— ESRS 2, GOV-1</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Describe the entities and operations included in your sustainability report boundary.</p>
                    <textarea name="consolidation_scope" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none resize-none"
                              placeholder="e.g. The report covers all wholly-owned subsidiaries and joint ventures where the company has operational control..."><?= htmlspecialchars($existing['consolidation_scope'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Value Chain Boundaries -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Value Chain Boundaries
                        <span class="text-gray-400 font-normal ml-1">— ESRS 2, SBM-1</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Describe the upstream and downstream value chain scope included in assessments.</p>
                    <textarea name="value_chain_boundaries" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none resize-none"
                              placeholder="e.g. Our value chain assessment includes Tier 1 suppliers and key downstream distributors..."><?= htmlspecialchars($existing['value_chain_boundaries'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Board Role in Sustainability -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Board Role in Sustainability
                        <span class="text-gray-400 font-normal ml-1">— ESRS 2, GOV-1</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Describe how the board oversees sustainability matters and strategy.</p>
                    <textarea name="board_role_in_sustainability" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none resize-none"
                              placeholder="e.g. The Board's Sustainability Committee meets quarterly to review ESG performance against targets..."><?= htmlspecialchars($existing['board_role_in_sustainability'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- ESG Integration in Remuneration -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ESG Integration in Remuneration (%)
                        <span class="text-gray-400 font-normal ml-1">— ESRS 2, GOV-3</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Percentage of executive compensation linked to sustainability performance (0–100).</p>
                    <input type="number" name="esg_integration_in_remuneration" min="0" max="100"
                           value="<?= htmlspecialchars($existing['esg_integration_in_remuneration'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-48 px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                           placeholder="e.g. 25">
                </div>

                <!-- Assessment Process -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Materiality Assessment Process
                        <span class="text-gray-400 font-normal ml-1">— ESRS 2, IRO-1</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Describe how impacts, risks, and opportunities were identified and assessed.</p>
                    <textarea name="assessment_process" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none resize-none"
                              placeholder="e.g. We conducted a double materiality assessment through stakeholder surveys, industry benchmarking, and expert workshops..."><?= htmlspecialchars($existing['assessment_process'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <button type="submit" name="save" value="1"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                        Save Disclosures
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
