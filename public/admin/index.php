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

// Handle POST actions (clear history, etc)
$actionMessage = '';
$actionType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\BotWA\AdminAuth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $actionMessage = 'Invalid CSRF token';
        $actionType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'clear_conversation_history') {
            $db->query("TRUNCATE TABLE conversation_history");
            $actionMessage = 'Conversation history cleared!';
            $actionType = 'success';
        } elseif ($action === 'clear_chat_logs') {
            $db->query("TRUNCATE TABLE chat_logs");
            $actionMessage = 'Chat logs cleared!';
            $actionType = 'success';
            $recentLogs = [];
            $totalMessagesToday = 0;
            $totalResponses = 0;
        } elseif ($action === 'clear_all') {
            $db->query("TRUNCATE TABLE conversation_history");
            $db->query("TRUNCATE TABLE chat_logs");
            $actionMessage = 'All history cleared! Bot starts fresh.';
            $actionType = 'success';
            $recentLogs = [];
            $totalMessagesToday = 0;
            $totalResponses = 0;
        }
    }
}

// Counts for maintenance section
try {
    $conversationCount = $db->count('conversation_history');
} catch (\Exception $e) {
    $conversationCount = 0;
}
try {
    $chatLogCount = $db->count('chat_logs');
} catch (\Exception $e) {
    $chatLogCount = 0;
}

adminHeader('Dashboard', 'dashboard');
?>

<?php if ($actionMessage): ?>
    <div data-flash class="mb-6 px-4 py-3 rounded-lg text-sm font-medium <?= $actionType === 'success' ? 'bg-green-900/50 text-green-400 border border-green-700' : 'bg-red-900/50 text-red-400 border border-red-700' ?>">
        <?= htmlspecialchars($actionMessage) ?>
    </div>
<?php endif; ?>

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

<!-- Maintenance Section -->
<div class="mt-6 bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-red-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">Maintenance</h3>
                <p class="text-xs text-gray-400 mt-0.5">Clear history and reset bot memory</p>
            </div>
        </div>
    </div>
    <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <!-- Conversation History Count -->
            <div class="bg-gray-900 rounded-lg p-4">
                <p class="text-xs text-gray-400 mb-1">Conversation History</p>
                <p class="text-2xl font-bold text-white"><?= number_format($conversationCount) ?></p>
                <p class="text-xs text-gray-500 mt-1">Messages stored for AI context</p>
            </div>
            <!-- Chat Logs Count -->
            <div class="bg-gray-900 rounded-lg p-4">
                <p class="text-xs text-gray-400 mb-1">Chat Logs</p>
                <p class="text-2xl font-bold text-white"><?= number_format($chatLogCount) ?></p>
                <p class="text-xs text-gray-500 mt-1">All incoming/outgoing messages logged</p>
            </div>
            <!-- Memory Count -->
            <div class="bg-gray-900 rounded-lg p-4">
                <p class="text-xs text-gray-400 mb-1">Active Memories</p>
                <p class="text-2xl font-bold text-white"><?= number_format($activeMemories) ?></p>
                <p class="text-xs text-gray-500 mt-1">Facts & preferences stored</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <!-- Clear Conversation History -->
            <form method="POST" onsubmit="return confirm('Clear conversation history? Bot will lose all chat context but personality stays.')">
                <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                <input type="hidden" name="action" value="clear_conversation_history">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-400 text-sm font-medium rounded-lg border border-amber-700/50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Clear Conversation History
                </button>
            </form>

            <!-- Clear Chat Logs -->
            <form method="POST" onsubmit="return confirm('Clear all chat logs? This cannot be undone.')">
                <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                <input type="hidden" name="action" value="clear_chat_logs">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-400 text-sm font-medium rounded-lg border border-amber-700/50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Clear Chat Logs
                </button>
            </form>

            <!-- Clear All -->
            <form method="POST" onsubmit="return confirm('CLEAR EVERYTHING? This will reset conversation history AND chat logs. Bot starts completely fresh. Are you sure?')">
                <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600/20 hover:bg-red-600/30 text-red-400 text-sm font-medium rounded-lg border border-red-700/50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Clear All (Fresh Start)
                </button>
            </form>
        </div>

        <p class="mt-3 text-xs text-gray-500">Note: Clearing conversation history resets the bot's chat context. Personality, skills, memories, and triggers are NOT affected.</p>
    </div>
</div>

<?php adminFooter(); ?>
