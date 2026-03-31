<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';

$pageTitle = 'Sites';

$stmt = $pdo->prepare('SELECT * FROM sites WHERE company_id = :company_id AND deleted_at IS NULL ORDER BY created_at DESC');
$stmt->execute([':company_id' => company_id()]);
$sites = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="space-y-5">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Sites & Facilities</h2>
            <p class="text-gray-500 text-base mt-1">Manage your company's operational sites</p>
        </div>
        <a href="/esg-report-test/sites/create.php"
           class="inline-flex items-center space-x-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-5 rounded-lg text-base transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Add New Site</span>
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-emerald-700 font-medium">Site deleted successfully.</p>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-emerald-700 font-medium">Site saved successfully.</p>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <?php if (empty($sites)): ?>
        <div class="p-12 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="text-gray-500 text-base font-medium">No sites added yet</p>
            <p class="text-gray-400 text-sm mt-1">Add your first site to start tracking emissions by location</p>
            <a href="/esg-report-test/sites/create.php" class="inline-block mt-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-5 rounded-lg text-sm transition">
                Add First Site
            </a>
        </div>
        <?php else: ?>
        <table class="w-full text-base">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left py-3 px-5 text-sm font-semibold text-gray-600">Site Name</th>
                    <th class="text-left py-3 px-5 text-sm font-semibold text-gray-600">Address</th>
                    <th class="text-left py-3 px-5 text-sm font-semibold text-gray-600">Country</th>
                    <th class="text-left py-3 px-5 text-sm font-semibold text-gray-600">Added</th>
                    <th class="text-right py-3 px-5 text-sm font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($sites as $i => $site): ?>
                <tr class="<?= $i % 2 === 1 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-emerald-50 transition-colors">
                    <td class="py-3.5 px-5 font-medium text-gray-900"><?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="py-3.5 px-5 text-gray-600 max-w-xs truncate"><?= htmlspecialchars($site['address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="py-3.5 px-5 text-gray-600"><?= htmlspecialchars($site['country'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="py-3.5 px-5 text-gray-500 text-sm"><?= date('d M Y', strtotime($site['created_at'])) ?></td>
                    <td class="py-3.5 px-5 text-right">
                        <div class="flex items-center justify-end space-x-2">
                            <a href="/esg-report-test/sites/edit.php?id=<?= urlencode($site['id']) ?>"
                               class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-md transition">
                                Edit
                            </a>
                            <form method="POST" action="/esg-report-test/sites/delete.php"
                                  onsubmit="return confirm('Delete this site? This cannot be undone.')">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($site['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit"
                                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-md transition">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <?php if (!empty($sites)): ?>
    <p class="text-sm text-gray-400 text-right"><?= count($sites) ?> site<?= count($sites) !== 1 ? 's' : '' ?> total</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
