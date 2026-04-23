<?php
/**
 * Chat Logs - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filters
$filterType = $_GET['type'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterSender = $_GET['sender'] ?? '';

// Build query
$where = '1=1';
$params = [];
if ($filterType) {
    $where .= ' AND message_type = ?';
    $params[] = $filterType;
}
if ($filterDate) {
    $where .= ' AND DATE(created_at) = ?';
    $params[] = $filterDate;
}
if ($filterSender) {
    $where .= ' AND (sender_phone LIKE ? OR sender_name LIKE ?)';
    $params[] = "%{$filterSender}%";
    $params[] = "%{$filterSender}%";
}

$total = $db->count('chat_logs', $where, $params);
$totalPages = max(1, ceil($total / $perPage));
$logs = $db->fetchAll(
    "SELECT * FROM chat_logs WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Calculate showing range
$showingFrom = $total > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $total);

adminHeader('Chat Logs', 'logs');
?>

<!-- Filter Bar -->
<div class="bg-gray-800 rounded-xl border border-gray-700 p-4 mb-6">
    <form method="GET" action="logs.php" class="flex flex-col sm:flex-row items-end gap-3">
        <!-- Type Filter -->
        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-gray-400 mb-1">Type</label>
            <select name="type" class="w-full sm:w-40 bg-gray-700 border border-gray-600 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All Types</option>
                <option value="incoming" <?= $filterType === 'incoming' ? 'selected' : '' ?>>Incoming</option>
                <option value="outgoing" <?= $filterType === 'outgoing' ? 'selected' : '' ?>>Outgoing</option>
            </select>
        </div>

        <!-- Date Filter -->
        <div class="w-full sm:w-auto">
            <label class="block text-xs font-medium text-gray-400 mb-1">Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
                   class="w-full sm:w-44 bg-gray-700 border border-gray-600 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <!-- Sender Filter -->
        <div class="w-full sm:flex-1">
            <label class="block text-xs font-medium text-gray-400 mb-1">Sender</label>
            <input type="text" name="sender" value="<?= htmlspecialchars($filterSender) ?>" placeholder="Search by phone or name..."
                   class="w-full bg-gray-700 border border-gray-600 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-500">
        </div>

        <!-- Buttons -->
        <div class="flex gap-2 w-full sm:w-auto">
            <button type="submit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Filter
            </button>
            <?php if ($filterType || $filterDate || $filterSender): ?>
                <a href="logs.php" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Stats Bar -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-4">
    <div class="flex items-center gap-4">
        <span class="inline-flex items-center gap-2 text-sm text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
            </svg>
            Total: <span class="font-semibold text-white"><?= number_format($total) ?></span> logs
        </span>
    </div>
    <span class="text-sm text-gray-500">
        <?php if ($total > 0): ?>
            Showing <?= $showingFrom ?>-<?= $showingTo ?> of <?= number_format($total) ?>
        <?php else: ?>
            No logs found
        <?php endif; ?>
    </span>
</div>

<!-- Logs Table -->
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-700">
                    <th class="px-5 py-3 w-8"></th>
                    <th class="px-5 py-3">Time</th>
                    <th class="px-5 py-3">Sender</th>
                    <th class="px-5 py-3">Message</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3">Tokens</th>
                    <th class="px-5 py-3">Response Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                                </svg>
                                <p class="text-gray-500 font-medium">No chat logs found</p>
                                <p class="text-gray-600 text-xs">
                                    <?php if ($filterType || $filterDate || $filterSender): ?>
                                        Try adjusting your filters
                                    <?php else: ?>
                                        Logs will appear here when the bot processes messages
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $i => $log): ?>
                        <?php
                            $rowId = 'log-' . ($log['id'] ?? $i);
                            $message = $log['message'] ?? '';
                            $truncated = mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message;
                            $isOutgoing = ($log['message_type'] ?? $log['direction'] ?? 'incoming') === 'outgoing';
                            $tokens = $log['tokens_used'] ?? $log['tokens'] ?? null;
                            $responseTime = $log['response_time_ms'] ?? $log['response_time'] ?? null;
                            $senderName = $log['sender_name'] ?? $log['sender_phone'] ?? $log['sender'] ?? 'Unknown';
                            $senderPhone = $log['sender_phone'] ?? '';
                            $aiPrompt = $log['ai_prompt'] ?? $log['system_prompt'] ?? '';
                            $aiResponse = $log['ai_response'] ?? $log['response'] ?? '';
                            $createdAt = $log['created_at'] ?? '';
                        ?>
                        <tr class="hover:bg-gray-700/40 transition-colors cursor-pointer log-row" data-target="<?= $rowId ?>">
                            <td class="px-5 py-3 text-gray-500">
                                <svg class="w-4 h-4 transition-transform duration-200 expand-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-400 text-xs">
                                <?php if ($createdAt): ?>
                                    <div><?= date('Y-m-d', strtotime($createdAt)) ?></div>
                                    <div class="text-gray-500"><?= date('H:i:s', strtotime($createdAt)) ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="text-gray-200 font-medium text-sm"><?= htmlspecialchars($senderName) ?></div>
                                <?php if ($senderPhone && $senderPhone !== $senderName): ?>
                                    <div class="text-gray-500 text-xs"><?= htmlspecialchars($senderPhone) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-gray-300 max-w-xs">
                                <span class="message-truncated"><?= htmlspecialchars($truncated) ?></span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $isOutgoing ? 'bg-green-900/50 text-green-400' : 'bg-blue-900/50 text-blue-400' ?>">
                                    <?= $isOutgoing ? 'Outgoing' : 'Incoming' ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-400 text-xs">
                                <?php if ($tokens !== null): ?>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        <?= number_format($tokens) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-600">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-400 text-xs">
                                <?php if ($responseTime !== null): ?>
                                    <?= number_format($responseTime) ?>ms
                                <?php else: ?>
                                    <span class="text-gray-600">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- Expanded Detail Row -->
                        <tr id="<?= $rowId ?>" class="hidden">
                            <td colspan="7" class="px-5 py-0">
                                <div class="py-4 border-t border-gray-700/50 space-y-4">
                                    <!-- Full Message -->
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Full Message</h4>
                                        <div class="bg-gray-900 rounded-lg p-3 text-sm text-gray-300 whitespace-pre-wrap break-words max-h-48 overflow-y-auto"><?= htmlspecialchars($message ?: '(empty)') ?></div>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                        <!-- AI Prompt -->
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">AI Prompt / System Prompt</h4>
                                            <div class="bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono whitespace-pre-wrap break-words max-h-64 overflow-y-auto"><?= htmlspecialchars($aiPrompt ?: '(not recorded)') ?></div>
                                        </div>

                                        <!-- AI Response -->
                                        <div>
                                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">AI Response</h4>
                                            <div class="bg-gray-900 rounded-lg p-3 text-sm text-gray-300 whitespace-pre-wrap break-words max-h-64 overflow-y-auto"><?= htmlspecialchars($aiResponse ?: '(not recorded)') ?></div>
                                        </div>
                                    </div>

                                    <!-- Meta Info -->
                                    <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                                        <?php if (!empty($log['id'])): ?>
                                            <span>ID: <?= htmlspecialchars($log['id']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($log['chat_id'])): ?>
                                            <span>Chat: <?= htmlspecialchars($log['chat_id']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($tokens !== null): ?>
                                            <span>Tokens: <?= number_format($tokens) ?></span>
                                        <?php endif; ?>
                                        <?php if ($responseTime !== null): ?>
                                            <span>Response Time: <?= number_format($responseTime) ?>ms</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <p class="text-sm text-gray-500">
            Page <?= $page ?> of <?= $totalPages ?>
        </p>
        <nav class="flex items-center gap-1">
            <?php
                // Build base query string for pagination links
                $queryParams = [];
                if ($filterType) $queryParams['type'] = $filterType;
                if ($filterDate) $queryParams['date'] = $filterDate;
                if ($filterSender) $queryParams['sender'] = $filterSender;

                function buildPageUrl(int $p, array $params): string {
                    $params['page'] = $p;
                    return 'logs.php?' . http_build_query($params);
                }
            ?>

            <!-- Previous -->
            <?php if ($page > 1): ?>
                <a href="<?= buildPageUrl($page - 1, $queryParams) ?>" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Prev
                </a>
            <?php else: ?>
                <span class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-600 bg-gray-800/50 border border-gray-700/50 rounded-lg cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Prev
                </span>
            <?php endif; ?>

            <!-- Page Numbers -->
            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if ($startPage > 1): ?>
                    <a href="<?= buildPageUrl(1, $queryParams) ?>" class="inline-flex items-center justify-center w-10 h-10 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="px-1 text-gray-600">...</span>
                    <?php endif; ?>
                <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="inline-flex items-center justify-center w-10 h-10 text-sm font-bold text-white bg-indigo-600 border border-indigo-500 rounded-lg"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= buildPageUrl($p, $queryParams) ?>" class="inline-flex items-center justify-center w-10 h-10 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="px-1 text-gray-600">...</span>
                <?php endif; ?>
                <a href="<?= buildPageUrl($totalPages, $queryParams) ?>" class="inline-flex items-center justify-center w-10 h-10 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors"><?= $totalPages ?></a>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($page < $totalPages): ?>
                <a href="<?= buildPageUrl($page + 1, $queryParams) ?>" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition-colors">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php else: ?>
                <span class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-600 bg-gray-800/50 border border-gray-700/50 rounded-lg cursor-not-allowed">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </span>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>

<script>
// Expandable rows
document.querySelectorAll('.log-row').forEach(row => {
    row.addEventListener('click', function() {
        const targetId = this.dataset.target;
        const detailRow = document.getElementById(targetId);
        const icon = this.querySelector('.expand-icon');
        
        if (detailRow) {
            const isHidden = detailRow.classList.contains('hidden');
            
            // Close all other expanded rows
            document.querySelectorAll('tr[id^="log-"]:not(.hidden)').forEach(openRow => {
                if (openRow.id !== targetId) {
                    openRow.classList.add('hidden');
                    const parentRow = document.querySelector(`[data-target="${openRow.id}"]`);
                    if (parentRow) {
                        parentRow.querySelector('.expand-icon')?.classList.remove('rotate-90');
                    }
                }
            });
            
            // Toggle current row
            detailRow.classList.toggle('hidden');
            icon?.classList.toggle('rotate-90');
        }
    });
});
</script>

<?php adminFooter(); ?>
