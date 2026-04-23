<?php

require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();

use BotWA\AdminAuth;

AdminAuth::startSession();

// Already logged in? Redirect to dashboard
if (AdminAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!AdminAuth::verifyCsrfToken($csrfToken)) {
        $error = 'Sesi tidak valid. Silakan coba lagi.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password harus diisi.';
    } elseif (!AdminAuth::login($username, $password)) {
        $error = 'Username atau password salah.';
    } else {
        header('Location: index.php');
        exit;
    }
}

$csrfToken = AdminAuth::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cimol Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md">
        <!-- Login Card -->
        <div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 p-8">

            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600/20 rounded-full mb-4">
                    <span class="text-3xl">&#129302;</span>
                </div>
                <h1 class="text-2xl font-bold text-white">Cimol</h1>
                <p class="text-gray-400 text-sm mt-1">Admin Panel</p>
            </div>

            <!-- Error Message -->
            <?php if ($error !== ''): ?>
                <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
                    <p class="text-red-400 text-sm text-center"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Username -->
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                        placeholder="Masukkan username"
                    >
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                        placeholder="Masukkan password"
                    >
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                >
                    Login
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-600 text-xs mt-6">&copy; <?= date('Y') ?> BotWA Cimol</p>
    </div>

</body>
</html>
