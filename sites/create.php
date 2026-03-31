<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Add New Site';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = trim($_POST['country'] ?? '');

    if ($name === '') {
        $error = 'Site name is required.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO sites (id, company_id, name, address, country, created_by, created_at, updated_at)
            VALUES (:id, :company_id, :name, :address, :country, :created_by, NOW(), NOW())
        ');
        $stmt->execute([
            ':id'         => uuid(),
            ':company_id' => company_id(),
            ':name'       => $name,
            ':address'    => $address,
            ':country'    => $country,
            ':created_by' => user_id(),
        ]);
        header('Location: /esg-report-test/sites/index.php?saved=1');
        exit;
    }
}

require_once '../includes/header.php';
?>

<div class="max-w-xl mx-auto">
    <div class="mb-6 flex items-center space-x-3">
        <a href="/esg-report-test/sites/index.php" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Add New Site</h2>
            <p class="text-gray-500 text-base mt-0.5">Register a new operational facility</p>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" class="space-y-5">
            <!-- Site Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Site Name <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" required autofocus
                       value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                       placeholder="e.g. Headquarters, Factory A, Warehouse B">
            </div>

            <!-- Address -->
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea id="address" name="address" rows="3"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition resize-none"
                          placeholder="Full street address..."><?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Country -->
            <div>
                <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <input type="text" id="country" name="country"
                       value="<?= htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                       placeholder="e.g. MY, UK, US">
            </div>

            <div class="flex items-center space-x-3 pt-2">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg text-base transition">
                    Add Site
                </button>
                <a href="/esg-report-test/sites/index.php"
                   class="text-gray-500 hover:text-gray-700 font-medium py-2.5 px-4 rounded-lg text-base transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
