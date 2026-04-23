<?php
/**
 * Memory Management - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();
$memoryManager = new \BotWA\MemoryManager();
$message = '';
$messageType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\BotWA\AdminAuth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $memoryManager->addMemory(
                $_POST['memory_type'],
                $_POST['subject'] ?? '',
                $_POST['content'],
                (int) ($_POST['importance'] ?? 5)
            );
            $message = 'Memory added!';
            $messageType = 'success';
        } elseif ($action === 'update') {
            $memoryManager->updateMemory((int) $_POST['id'], [
                'memory_type' => $_POST['memory_type'],
                'subject' => $_POST['subject'] ?? '',
                'content' => $_POST['content'],
                'importance' => (int) ($_POST['importance'] ?? 5),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);
            $message = 'Memory updated!';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $memoryManager->deleteMemory((int) $_POST['id']);
            $message = 'Memory deleted!';
            $messageType = 'success';
        } elseif ($action === 'clean_history') {
            $deleted = $memoryManager->cleanOldHistory(100);
            $message = "Cleaned {$deleted} old conversation entries!";
            $messageType = 'success';
        }
    }
}

// Filter by type
$filterType = $_GET['type'] ?? '';
$memories = $memoryManager->getAll($filterType);

// Stats
$totalMemories = $db->count('memory');
$typeCounts = [];
$typeRows = $db->fetchAll("SELECT memory_type, COUNT(*) as cnt FROM memory GROUP BY memory_type");
foreach ($typeRows as $row) {
    $typeCounts[$row['memory_type']] = (int) $row['cnt'];
}
$historyCount = $db->count('conversation_history');

// Type config
$memoryTypes = [
    'fact'         => ['label' => 'Fact',         'bg' => 'bg-blue-900/50',   'text' => 'text-blue-400',   'border' => 'border-blue-700',   'dot' => 'bg-blue-400'],
    'preference'   => ['label' => 'Preference',   'bg' => 'bg-purple-900/50', 'text' => 'text-purple-400', 'border' => 'border-purple-700', 'dot' => 'bg-purple-400'],
    'event'        => ['label' => 'Event',        'bg' => 'bg-yellow-900/50', 'text' => 'text-yellow-400', 'border' => 'border-yellow-700', 'dot' => 'bg-yellow-400'],
    'conversation' => ['label' => 'Conversation', 'bg' => 'bg-gray-700/50',   'text' => 'text-gray-400',   'border' => 'border-gray-600',   'dot' => 'bg-gray-400'],
    'relationship' => ['label' => 'Relationship', 'bg' => 'bg-pink-900/50',   'text' => 'text-pink-400',   'border' => 'border-pink-700',   'dot' => 'bg-pink-400'],
];

adminHeader('Memory', 'memory');
?>

<?php if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium <?= $messageType === 'success' ? 'bg-green-900/50 text-green-400 border border-green-700' : 'bg-red-900/50 text-red-400 border border-red-700' ?>">
        <div class="flex items-center gap-2">
            <?php if ($messageType === 'success'): ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            <?php else: ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            <?php endif; ?>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Info Banner -->
<div class="mb-6 px-4 py-3 rounded-lg bg-indigo-900/30 border border-indigo-700/50">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-indigo-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
        </svg>
        <div>
            <p class="text-sm text-indigo-300">Memories help Cimol remember facts, preferences, events, and relationships across conversations.</p>
            <p class="text-xs text-indigo-400/70 mt-1">Higher importance memories are prioritized when building context. Inactive memories are ignored.</p>
        </div>
    </div>
</div>

<!-- Stats Bar -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    <!-- Total -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3 text-center">
        <p class="text-2xl font-bold text-white"><?= $totalMemories ?></p>
        <p class="text-xs text-gray-400 mt-0.5">Total</p>
    </div>
    <?php foreach ($memoryTypes as $typeKey => $typeStyle): ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3 text-center">
            <p class="text-2xl font-bold <?= $typeStyle['text'] ?>"><?= $typeCounts[$typeKey] ?? 0 ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= $typeStyle['label'] ?></p>
        </div>
    <?php endforeach; ?>
    <!-- History -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3 text-center">
        <p class="text-2xl font-bold text-amber-400"><?= number_format($historyCount) ?></p>
        <p class="text-xs text-gray-400 mt-0.5">History</p>
    </div>
</div>

<!-- Type Filter Tabs -->
<div class="mb-6 flex flex-wrap gap-2">
    <a href="memory.php"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= empty($filterType) ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700' ?>">
        All
        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs <?= empty($filterType) ? 'bg-white/20' : 'bg-gray-700' ?>"><?= $totalMemories ?></span>
    </a>
    <?php foreach ($memoryTypes as $typeKey => $typeStyle): ?>
        <a href="memory.php?type=<?= $typeKey ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filterType === $typeKey ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700' ?>">
            <span class="w-2 h-2 rounded-full <?= $typeStyle['dot'] ?>"></span>
            <?= $typeStyle['label'] ?>
            <?php if (isset($typeCounts[$typeKey])): ?>
                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs <?= $filterType === $typeKey ? 'bg-white/20' : 'bg-gray-700' ?>"><?= $typeCounts[$typeKey] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Memory List -->
<div class="space-y-3 mb-8">
    <?php if (empty($memories)): ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-8 text-center">
            <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
            </svg>
            <p class="text-gray-400 text-sm">No memories found<?= !empty($filterType) ? ' for type "' . htmlspecialchars($filterType) . '"' : '' ?>. Add one below to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($memories as $m):
            $mType = $m['memory_type'] ?? 'fact';
            $mStyle = $memoryTypes[$mType] ?? $memoryTypes['fact'];
            $isActive = (bool) ($m['is_active'] ?? 1);
            $importance = (int) ($m['importance'] ?? 5);
            $contentLength = mb_strlen($m['content'] ?? '');
            $isLong = $contentLength > 200;
        ?>
            <div class="memory-card bg-gray-800 rounded-xl border border-gray-700 overflow-hidden" data-type="<?= $mType ?>">
                <!-- Card Header -->
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Type Badge -->
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $mStyle['bg'] ?> <?= $mStyle['text'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $mStyle['dot'] ?>"></span>
                                <?= $mStyle['label'] ?>
                            </span>
                            <!-- Active/Inactive Badge -->
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $isActive ? 'bg-green-900/50 text-green-400' : 'bg-gray-700 text-gray-500' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                            <!-- ID -->
                            <span class="text-xs text-gray-600 font-mono">#<?= (int) $m['id'] ?></span>
                        </div>
                        <!-- Importance Bar -->
                        <div class="flex items-center gap-2 flex-shrink-0" title="Importance: <?= $importance ?>/10">
                            <div class="flex gap-0.5">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <span class="w-1.5 h-4 rounded-sm <?php
                                        if ($i <= $importance) {
                                            if ($importance >= 8) echo 'bg-red-500';
                                            elseif ($importance >= 5) echo 'bg-amber-500';
                                            else echo 'bg-blue-500';
                                        } else {
                                            echo 'bg-gray-700';
                                        }
                                    ?>"></span>
                                <?php endfor; ?>
                            </div>
                            <span class="text-xs font-mono <?php
                                if ($importance >= 8) echo 'text-red-400';
                                elseif ($importance >= 5) echo 'text-amber-400';
                                else echo 'text-blue-400';
                            ?>"><?= $importance ?></span>
                        </div>
                    </div>

                    <!-- Subject -->
                    <?php if (!empty($m['subject'])): ?>
                        <h3 class="text-sm font-bold text-white mb-1"><?= htmlspecialchars($m['subject']) ?></h3>
                    <?php endif; ?>

                    <!-- Content -->
                    <div class="relative">
                        <?php if ($isLong): ?>
                            <p class="text-sm text-gray-300 leading-relaxed" id="content-short-<?= $m['id'] ?>">
                                <?= htmlspecialchars(mb_substr($m['content'], 0, 200)) ?>...
                                <button type="button" onclick="toggleContent(<?= $m['id'] ?>)" class="text-indigo-400 hover:text-indigo-300 text-xs font-medium ml-1">Show more</button>
                            </p>
                            <p class="text-sm text-gray-300 leading-relaxed hidden" id="content-full-<?= $m['id'] ?>">
                                <?= nl2br(htmlspecialchars($m['content'])) ?>
                                <button type="button" onclick="toggleContent(<?= $m['id'] ?>)" class="text-indigo-400 hover:text-indigo-300 text-xs font-medium ml-1">Show less</button>
                            </p>
                        <?php else: ?>
                            <p class="text-sm text-gray-300 leading-relaxed"><?= nl2br(htmlspecialchars($m['content'] ?? '')) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Meta -->
                    <?php if (!empty($m['updated_at'])): ?>
                        <p class="text-xs text-gray-600 mt-2">Updated: <?= htmlspecialchars($m['updated_at']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Card Actions -->
                <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center justify-between">
                    <button type="button" onclick="toggleEditForm(<?= $m['id'] ?>)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-900/30 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </button>
                    <button type="button" onclick="deleteMemory(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['subject'] ?: mb_substr($m['content'], 0, 30)), ENT_QUOTES) ?>')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-400 hover:text-red-300 hover:bg-red-900/30 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete
                    </button>
                </div>

                <!-- Inline Edit Form (hidden by default) -->
                <div id="edit-form-<?= $m['id'] ?>" class="hidden border-t border-gray-700">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">

                        <div class="p-5 space-y-4">
                            <!-- Type & Subject Row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Type</label>
                                    <select name="memory_type"
                                            class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                        <?php foreach ($memoryTypes as $tKey => $tVal): ?>
                                            <option value="<?= $tKey ?>" <?= $mType === $tKey ? 'selected' : '' ?>><?= $tVal['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Subject</label>
                                    <input type="text" name="subject"
                                           value="<?= htmlspecialchars($m['subject'] ?? '') ?>"
                                           placeholder="e.g. phone number, name, topic"
                                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                </div>
                            </div>

                            <!-- Content -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Content</label>
                                <textarea name="content" required rows="4"
                                          class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors font-mono leading-relaxed resize-y"><?= htmlspecialchars($m['content'] ?? '') ?></textarea>
                            </div>

                            <!-- Importance & Active Row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">
                                        Importance: <span id="importance-val-<?= $m['id'] ?>" class="font-mono text-indigo-400"><?= $importance ?></span>/10
                                    </label>
                                    <input type="range" name="importance" min="1" max="10" value="<?= $importance ?>"
                                           oninput="document.getElementById('importance-val-<?= $m['id'] ?>').textContent = this.value"
                                           class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-500">
                                    <div class="flex justify-between text-xs text-gray-600 mt-1">
                                        <span>Low</span>
                                        <span>High</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 pb-1">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" <?= $isActive ? 'checked' : '' ?>>
                                        <div class="w-9 h-5 bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                    <span class="text-sm text-gray-300">Active</span>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center gap-3">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Changes
                            </button>
                            <button type="button" onclick="toggleEditForm(<?= $m['id'] ?>)"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add New Memory -->
<div id="add-memory-section" class="mb-8">
    <!-- Add Button (shown when form is hidden) -->
    <button type="button" id="add-memory-btn" onclick="toggleAddForm()"
            class="w-full py-4 rounded-xl border-2 border-dashed border-gray-700 hover:border-emerald-600 bg-gray-800/50 hover:bg-gray-800 text-gray-400 hover:text-emerald-400 transition-all flex items-center justify-center gap-3 group">
        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 group-hover:bg-emerald-500/20 flex items-center justify-center transition-colors">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
        </div>
        <span class="text-sm font-medium">Add New Memory</span>
    </button>

    <!-- Add Form (hidden by default) -->
    <div id="add-memory-form" class="hidden bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 border-l-emerald-500">
        <div class="px-5 py-4 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">Add New Memory</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Store a new fact, preference, event, or relationship for Cimol</p>
                    </div>
                </div>
                <button type="button" onclick="toggleAddForm()" class="p-2 text-gray-400 hover:text-gray-200 hover:bg-gray-700 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
            <input type="hidden" name="action" value="add">

            <div class="p-5 space-y-5">
                <!-- Type & Subject Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="new_memory_type" class="block text-sm font-medium text-gray-300 mb-1.5">Type</label>
                        <select id="new_memory_type" name="memory_type" required
                                class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                            <?php foreach ($memoryTypes as $tKey => $tVal): ?>
                                <option value="<?= $tKey ?>"><?= $tVal['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Category of this memory</p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="new_subject" class="block text-sm font-medium text-gray-300 mb-1.5">Subject</label>
                        <input type="text" id="new_subject" name="subject"
                               placeholder="e.g. phone number, person name, topic"
                               class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                        <p class="mt-1 text-xs text-gray-500">Who or what this memory is about (optional)</p>
                    </div>
                </div>

                <!-- Content -->
                <div>
                    <label for="new_content" class="block text-sm font-medium text-gray-300 mb-1.5">Content</label>
                    <textarea id="new_content" name="content" required rows="4"
                              placeholder="The memory content that Cimol should remember..."
                              class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors font-mono leading-relaxed resize-y"></textarea>
                </div>

                <!-- Importance -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">
                        Importance: <span id="new-importance-val" class="font-mono text-emerald-400">5</span>/10
                    </label>
                    <input type="range" name="importance" min="1" max="10" value="5"
                           oninput="document.getElementById('new-importance-val').textContent = this.value"
                           class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                    <div class="flex justify-between text-xs text-gray-600 mt-1">
                        <span>1 - Low priority</span>
                        <span>10 - Critical</span>
                    </div>
                </div>
            </div>

            <!-- Add Button -->
            <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add Memory
                </button>
                <button type="button" onclick="toggleAddForm()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Maintenance Section -->
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 border-l-amber-500">
    <div class="px-5 py-4 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">Maintenance</h3>
                <p class="text-xs text-gray-400 mt-0.5">Clean up old conversation history to save database space</p>
            </div>
        </div>
    </div>

    <div class="p-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-300">
                    Conversation history entries: <span class="font-bold text-amber-400"><?= number_format($historyCount) ?></span>
                </p>
                <p class="text-xs text-gray-500 mt-1">Cleaning keeps the last 100 messages per chat and removes older entries.</p>
            </div>
            <form method="POST" action="" onsubmit="return confirm('Clean old conversation history?\n\nThis will keep the last 100 messages per chat and delete the rest.\nThis action cannot be undone.')">
                <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                <input type="hidden" name="action" value="clean_history">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Clean Old History
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="delete-form" method="POST" action="" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete-id" value="">
</form>

<script>
// Toggle content expand/collapse
function toggleContent(id) {
    const short = document.getElementById('content-short-' + id);
    const full = document.getElementById('content-full-' + id);
    if (short && full) {
        short.classList.toggle('hidden');
        full.classList.toggle('hidden');
    }
}

// Toggle inline edit form
function toggleEditForm(id) {
    const form = document.getElementById('edit-form-' + id);
    if (form.classList.contains('hidden')) {
        // Close all other edit forms first
        document.querySelectorAll('[id^="edit-form-"]').forEach(f => f.classList.add('hidden'));
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        form.classList.add('hidden');
    }
}

// Toggle add form
function toggleAddForm() {
    const btn = document.getElementById('add-memory-btn');
    const form = document.getElementById('add-memory-form');

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        btn.classList.add('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        form.classList.add('hidden');
        btn.classList.remove('hidden');
    }
}

// Delete memory
function deleteMemory(id, label) {
    if (!confirm('Delete memory "' + label + '"?\n\nThis action cannot be undone.')) {
        return;
    }
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php adminFooter(); ?>
