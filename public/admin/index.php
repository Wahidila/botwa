<?php
/**
 * Dashboard - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

// Fetch stats from database
$db = \BotWA\Database::getInstance();

// Total Messages Today
try {
    $totalMessagesToday = $db->count('chat_logs', 'DATE(created_at) = CURDATE()');
} catch (\Exception $e) {
    $totalMessagesToday = 0;
}

// Total Responses (outgoing messages today)
try {
    $totalResponses = $db->count('chat_logs', "DATE(created_at) = CURDATE() AND direction = 'outgoing'");
} catch (\Exception $e) {
    $totalResponses = 0;
}

// Active Memories
try {
    $activeMemories = $db->count('memory', 'is_active = 1');
} catch (\Exception $e) {
    $activeMemories = 0;
}

// Active Skills
try {
    $activeSkills = $db->count('skills', 'is_active = 1');
} catch (\Exception $e) {
    $activeSkills = 0;
}

// Recent Activity - last 10 chat logs
try {
    $recentLogs = $db->fetchAll(
        "SELECT * FROM chat_logs ORDER BY created_at DESC LIMIT 10"
    );
} catch (\Exception $e) {
    $recentLogs = [];
}

// Bot Status info
$botEnabled = \BotWA\Config::get('bot_enabled', false);
$wahaUrl = $_ENV['WAHA_API_URL'] ?? 'Not configured';
$aiModel = \BotWA\Config::get('ai_model', 'Unknown');

adminHeader('Dashboard', 'dashboard');
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <!-- Total Messages Today -->
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-blue-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-white"><?= number_format($totalMessagesToday) ?></p>
                <p class="text-sm text-gray-400">Messages Today</p>
            </div>
        </div>
    </div>

    <!-- Total Responses -->
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-green-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-white"><?= number_format($totalResponses) ?></p>
                <p class="text-sm text-gray-400">Responses Today</p>
            </div>
        </div>
    </div>

    <!-- Active Memories -->
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-purple-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-white"><?= number_format($activeMemories) ?></p>
                <p class="text-sm text-gray-400">Active Memories</p>
            </div>
        </div>
    </div>

    <!-- Active Skills -->
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-yellow-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-white"><?= number_format($activeSkills) ?></p>
                <p class="text-sm text-gray-400">Active Skills</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- Recent Activity -->
    <div class="xl:col-span-2 bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-700">
            <h3 class="text-base font-semibold text-white">Recent Activity</h3>
            <p class="text-xs text-gray-400 mt-0.5">Last 10 messages</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-700">
                        <th class="px-5 py-3">Time</th>
                        <th class="px-5 py-3">Sender</th>
                        <th class="px-5 py-3">Message</th>
                        <th class="px-5 py-3">Type</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-gray-500">No messages yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr class="hover:bg-gray-700/40 transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap text-gray-400">
                                    <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-gray-200 font-medium">
                                    <?= htmlspecialchars($log['sender_name'] ?? $log['sender'] ?? 'Unknown') ?>
                                </td>
                                <td class="px-5 py-3 text-gray-300 max-w-xs truncate">
                                    <?php
                                        $msg = $log['message'] ?? '';
                                        echo htmlspecialchars(mb_strlen($msg) > 80 ? mb_substr($msg, 0, 80) . '...' : $msg);
                                    ?>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <?php
                                        $direction = $log['direction'] ?? 'incoming';
                                        $isOutgoing = ($direction === 'outgoing');
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $isOutgoing ? 'bg-green-900/50 text-green-400' : 'bg-blue-900/50 text-blue-400' ?>">
                                        <?= $isOutgoing ? 'Outgoing' : 'Incoming' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($recentLogs)): ?>
            <div class="px-5 py-3 border-t border-gray-700">
                <a href="logs.php" class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors">View all logs &rarr;</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bot Status -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden h-fit">
        <div class="px-5 py-4 border-b border-gray-700">
            <h3 class="text-base font-semibold text-white">Bot Status</h3>
            <p class="text-xs text-gray-400 mt-0.5">Current configuration</p>
        </div>
        <div class="p-5 space-y-4">
            <!-- Bot Enabled -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-gray-700 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-200">Bot Status</p>
                        <p class="text-xs text-gray-400">Auto-reply</p>
                    </div>
                </div>
                <?php if ($botEnabled): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-900/50 text-green-400">
                        <span class="w-2 h-2 rounded-full bg-green-400"></span>
                        Enabled
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/50 text-red-400">
                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                        Disabled
                    </span>
                <?php endif; ?>
            </div>

            <hr class="border-gray-700">

            <!-- WAHA Connection -->
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg bg-gray-700 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-200">WAHA Server</p>
                    <p class="text-xs text-gray-400 truncate mt-0.5" title="<?= htmlspecialchars($wahaUrl) ?>">
                        <?= htmlspecialchars($wahaUrl) ?>
                    </p>
                </div>
            </div>

            <hr class="border-gray-700">

            <!-- AI Model -->
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg bg-gray-700 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-200">AI Model</p>
                    <p class="text-xs text-gray-400 truncate mt-0.5" title="<?= htmlspecialchars($aiModel) ?>">
                        <?= htmlspecialchars($aiModel) ?>
                    </p>
                </div>
            </div>

            <hr class="border-gray-700">

            <!-- Quick Links -->
            <div class="pt-1 space-y-2">
                <a href="settings.php" class="flex items-center gap-2 text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    AI Settings
                </a>
                <a href="waha.php" class="flex items-center gap-2 text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                    </svg>
                    WAHA Config
                </a>
                <a href="test.php" class="flex items-center gap-2 text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Test Bot
                </a>
            </div>
        </div>
    </div>
</div>

<?php adminFooter(); ?>
