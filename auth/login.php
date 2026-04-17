<?php
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /esg-report-test/phase3/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, company_id, name, email, password, role FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['email']      = $user['email'];
            header('Location: /esg-report-test/phase3/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ESG Reporting Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-teal-100 min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <!-- Logo / Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-600 rounded-2xl mb-4">
                <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">ESG Reporting Platform</h1>
            <p class="text-gray-500 mt-1 text-base">Sign in to your account</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Welcome Back</h2>

            <?php if (isset($_GET['registered'])): ?>
            <div class="mb-5 bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-emerald-700 font-medium">Account created successfully! Please sign in.</p>
            </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
            <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" id="email" name="email" required autofocus
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="you@company.com">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                           placeholder="Your password">
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-lg text-base transition duration-150 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-5 bg-amber-50 border border-amber-200 rounded-lg p-4">
                <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider mb-1">Demo Credentials</p>
                <p class="text-sm text-amber-800">Email: <span class="font-mono font-medium">admin@example.com</span></p>
                <p class="text-sm text-amber-800">Password: <span class="font-mono font-medium">admin123</span></p>
            </div>

            <p class="text-center text-sm text-gray-500 mt-5">
                Don't have an account?
                <a href="/esg-report-test/auth/register.php" class="text-emerald-600 hover:text-emerald-700 font-medium">Create one</a>
            </p>
        </div>
    </div>
</body>
</html>
