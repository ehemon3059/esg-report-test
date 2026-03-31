<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Company Profile';
$success   = '';
$error     = '';

// Load company
$stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => company_id()]);
$company = $stmt->fetch();

if (!$company) {
    $error = 'Company record not found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $company) {
    $name    = trim($_POST['name']    ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $country  = trim($_POST['country']  ?? '');
    $regnum   = trim($_POST['registration_number'] ?? '');
    $website  = trim($_POST['website'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Company name is required.';
    } else {
        $stmt = $pdo->prepare('
            UPDATE companies
            SET name = :name, industry = :industry, country_of_registration = :country,
                registration_number = :reg, website = :website, description = :desc,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ');
        $stmt->execute([
            ':name'    => $name,
            ':industry' => $industry,
            ':country' => $country,
            ':reg'     => $regnum,
            ':website' => $website,
            ':desc'    => $desc,
            ':id'      => company_id(),
        ]);
        $success = 'Company profile updated successfully.';

        // Reload
        $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => company_id()]);
        $company = $stmt->fetch();
    }
}

require_once '../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Company Profile</h2>
        <p class="text-gray-500 text-base mt-1">Manage your organisation's profile information</p>
    </div>

    <?php if ($success): ?>
    <div class="mb-5 bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-emerald-700 font-medium"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <?php if ($company): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="POST" class="space-y-5">
            <!-- Company Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" required
                       value="<?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition">
            </div>

            <!-- Industry -->
            <div>
                <label for="industry" class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
                <select id="industry" name="industry"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition bg-white">
                    <option value="">Select industry...</option>
                    <?php
                    $industries = ['Manufacturing','Retail & E-Commerce','Financial Services','Energy & Utilities','Transportation & Logistics','Healthcare & Pharmaceuticals','Technology & Software','Construction & Real Estate','Agriculture & Food','Mining & Resources','Professional Services','Other'];
                    foreach ($industries as $ind):
                        $sel = ($company['industry'] ?? '') === $ind ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($ind, ENT_QUOTES, 'UTF-8') ?>" <?= $sel ?>><?= htmlspecialchars($ind, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Country -->
            <div>
                <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country of Registration</label>
                <input type="text" id="country" name="country"
                       value="<?= htmlspecialchars($company['country_of_registration'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                       placeholder="e.g. MY, UK, US">
            </div>

            <!-- Registration Number -->
            <div>
                <label for="registration_number" class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                <input type="text" id="registration_number" name="registration_number"
                       value="<?= htmlspecialchars($company['registration_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                       placeholder="e.g. 1234567-A">
            </div>

            <!-- Website -->
            <div>
                <label for="website" class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                <input type="url" id="website" name="website"
                       value="<?= htmlspecialchars($company['website'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                       placeholder="https://www.company.com">
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition resize-none"
                          placeholder="Brief description of your company..."><?= htmlspecialchars($company['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg text-base transition duration-150">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
