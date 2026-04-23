<?php
/**
 * Admin Panel Layout Helper
 * 
 * Usage:
 *   require_once __DIR__ . '/layout.php';
 *   adminHeader('Page Title');
 *   // ... page content ...
 *   adminFooter();
 */

function adminHeader(string $title = 'Dashboard', string $activeMenu = ''): void
{
    $username = \BotWA\AdminAuth::getUsername();
    $csrf = \BotWA\AdminAuth::generateCsrfToken();
    
    $menuItems = [
        ['url' => 'index.php', 'label' => 'Dashboard', 'icon' => 'chart-bar', 'id' => 'dashboard'],
        ['url' => 'settings.php', 'label' => 'AI Settings', 'icon' => 'cog', 'id' => 'settings'],
        ['url' => 'waha.php', 'label' => 'WAHA Config', 'icon' => 'server', 'id' => 'waha'],
        ['url' => 'personality.php', 'label' => 'Personality', 'icon' => 'user-circle', 'id' => 'personality'],
        ['url' => 'skills.php', 'label' => 'Skills', 'icon' => 'lightning-bolt', 'id' => 'skills'],
        ['url' => 'memory.php', 'label' => 'Memory', 'icon' => 'database', 'id' => 'memory'],
        ['url' => 'triggers.php', 'label' => 'Triggers', 'icon' => 'hashtag', 'id' => 'triggers'],
        ['url' => 'members.php', 'label' => 'Members', 'icon' => 'users', 'id' => 'members'],
        ['url' => 'logs.php', 'label' => 'Chat Logs', 'icon' => 'chat-alt-2', 'id' => 'logs'],
        ['url' => 'test.php', 'label' => 'Test Bot', 'icon' => 'play', 'id' => 'test'],
    ];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Cimol Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="h-full bg-gray-900 text-gray-100">
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-800 border-r border-gray-700 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-200 ease-in-out flex flex-col">
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-700">
                <span class="text-3xl">🤖</span>
                <div>
                    <h1 class="text-xl font-bold text-indigo-400">Cimol</h1>
                    <p class="text-xs text-gray-400">Admin Panel</p>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                <?php foreach ($menuItems as $item): ?>
                    <?php $isActive = ($activeMenu === $item['id'] || (empty($activeMenu) && $item['id'] === 'dashboard')); ?>
                    <a href="<?= $item['url'] ?>" 
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <?= getMenuIcon($item['icon']) ?>
                        </svg>
                        <?= $item['label'] ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- User -->
            <div class="border-t border-gray-700 px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-sm font-bold">
                            <?= strtoupper(substr($username, 0, 1)) ?>
                        </div>
                        <span class="text-sm text-gray-300"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <a href="logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Sidebar overlay (mobile) -->
        <div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black/50 lg:hidden hidden" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-h-screen overflow-x-hidden">
            <!-- Top Bar -->
            <header class="sticky top-0 z-10 bg-gray-800/80 backdrop-blur-sm border-b border-gray-700 px-4 lg:px-6 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <h2 class="text-lg font-semibold"><?= htmlspecialchars($title) ?></h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="bot-status" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-900/50 text-green-400">
                            <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                            Bot Active
                        </span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 p-4 lg:p-6">
                <input type="hidden" id="csrf_token" value="<?= $csrf ?>">
<?php
}

function adminFooter(): void
{
?>
            </div>

            <!-- Footer -->
            <footer class="border-t border-gray-700 px-4 lg:px-6 py-3 text-center text-xs text-gray-500">
                Cimol Bot Admin &copy; <?= date('Y') ?>
            </footer>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
<?php
}

function getMenuIcon(string $name): string
{
    return match ($name) {
        'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
        'cog' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'server' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>',
        'user-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'lightning-bolt' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
        'database' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
        'hashtag' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
        'chat-alt-2' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>',
        'play' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    };
}
