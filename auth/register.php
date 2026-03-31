<?php
session_start();
require_once '../includes/helpers.php';
require_once '../config/db.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /esg-report-test/phase3/dashboard.php');
    exit;
}

$errors   = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $company  = trim($_POST['company']  ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $country  = trim($_POST['country']  ?? '');

    $formData = compact('name', 'email', 'company', 'industry', 'country');

    // Validate
    if ($name === '')    $errors[] = 'Full name is required.';
    if ($email === '')   $errors[] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($password === '') $errors[] = 'Password is required.';
    elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($company === '') $errors[] = 'Company name is required.';
    if ($industry === '') $errors[] = 'Industry is required.';
    if ($country === '')  $errors[] = 'Country is required.';

    // Check email uniqueness
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email address is already registered.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $companyId = uuid();
            $userId    = uuid();

            // Insert company
            $stmt = $pdo->prepare('
                INSERT INTO companies (id, name, industry, country_of_registration, created_at, updated_at)
                VALUES (:id, :name, :industry, :country, NOW(), NOW())
            ');
            $stmt->execute([
                ':id'       => $companyId,
                ':name'     => $company,
                ':industry' => $industry,
                ':country'  => $country,
            ]);

            // Insert user
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('
                INSERT INTO users (id, company_id, name, email, password, role, created_at, updated_at)
                VALUES (:id, :company_id, :name, :email, :password, :role, NOW(), NOW())
            ');
            $stmt->execute([
                ':id'         => $userId,
                ':company_id' => $companyId,
                ':name'       => $name,
                ':email'      => $email,
                ':password'   => $hashed,
                ':role'       => 'admin',
            ]);

            $pdo->commit();
            header('Location: /esg-report-test/auth/login.php?registered=1');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ESG Reporting Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-teal-100 min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-lg w-full">
        <!-- Logo / Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-600 rounded-2xl mb-4">
                <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">ESG Reporting Platform</h1>
            <p class="text-gray-500 mt-1 text-base">Create your account to get started</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Create Account</h2>

            <?php if (!empty($errors)): ?>
            <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start space-x-2">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <ul class="text-sm text-red-700 space-y-1">
                        <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <!-- Full Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($formData['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="John Smith">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="john@company.com">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" required minlength="8"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="Minimum 8 characters">
                    <p class="text-xs text-gray-400 mt-1">At least 8 characters required</p>
                </div>

                <!-- Divider -->
                <div class="border-t border-gray-100 pt-2">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-3">Company Information</p>
                </div>

                <!-- Company Name -->
                <div>
                    <label for="company" class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
                    <input type="text" id="company" name="company" required
                           value="<?= htmlspecialchars($formData['company'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="Acme Corp Ltd">
                </div>

                <!-- Industry -->
                <div>
                    <label for="industry" class="block text-sm font-medium text-gray-700 mb-1">Industry <span class="text-red-500">*</span></label>
                    <select id="industry" name="industry" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition bg-white">
                        <option value="">Select industry...</option>
                        <?php
                        $industries = ['Manufacturing','Retail & E-Commerce','Financial Services','Energy & Utilities','Transportation & Logistics','Healthcare & Pharmaceuticals','Technology & Software','Construction & Real Estate','Agriculture & Food','Mining & Resources','Professional Services','Other'];
                        foreach ($industries as $ind):
                            $sel = ($formData['industry'] ?? '') === $ind ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($ind, ENT_QUOTES, 'UTF-8') ?>" <?= $sel ?>><?= htmlspecialchars($ind, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Country -->
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country of Registration <span class="text-red-500">*</span></label>
                    <input type="text" id="country" name="country" required
                           value="<?= htmlspecialchars($formData['country'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="e.g. MY, UK, US">
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-lg text-base transition duration-150 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Create Account
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-5">
                Already have an account?
                <a href="/esg-report-test/auth/login.php" class="text-emerald-600 hover:text-emerald-700 font-medium">Sign in</a>
            </p>
        </div>
    </div>
</body>
</html>
