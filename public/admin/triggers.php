<?php
/**
 * Triggers Management - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();
$triggerManager = new \BotWA\TriggerManager();
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
            $triggerManager->add(
                $_POST['trigger_word'],
                $_POST['trigger_type'] ?? 'contains',
                $_POST['response_mode'] ?? 'ai',
                (int) ($_POST['priority'] ?? 5)
            );
            $message = 'Trigger added!';
            $messageType = 'success';
        } elseif ($action === 'update') {
            $triggerManager->update((int) $_POST['id'], [
                'trigger_word' => $_POST['trigger_word'],
                'trigger_type' => $_POST['trigger_type'],
                'response_mode' => $_POST['response_mode'],
                'priority' => (int) $_POST['priority'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);
            $message = 'Trigger updated!';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $triggerManager->delete((int) $_POST['id']);
            $message = 'Trigger deleted!';
            $messageType = 'success';
        } elseif ($action === 'toggle') {
            $triggerManager->toggle((int) $_POST['id']);
            $message = 'Trigger toggled!';
            $messageType = 'success';
        }
    }
}

$triggers = $triggerManager->getAll();
adminHeader('Triggers', 'triggers');

// Badge styles
$typeBadges = [
    'exact'      => ['bg' => 'bg-red-900/50',    'text' => 'text-red-400',    'border' => 'border-red-700',    'dot' => 'bg-red-400'],
    'contains'   => ['bg' => 'bg-blue-900/50',   'text' => 'text-blue-400',   'border' => 'border-blue-700',   'dot' => 'bg-blue-400'],
    'startswith' => ['bg' => 'bg-green-900/50',  'text' => 'text-green-400',  'border' => 'border-green-700',  'dot' => 'bg-green-400'],
    'regex'      => ['bg' => 'bg-purple-900/50', 'text' => 'text-purple-400', 'border' => 'border-purple-700', 'dot' => 'bg-purple-400'],
];

$modeBadges = [
    'ai'       => ['bg' => 'bg-indigo-900/50',  'text' => 'text-indigo-400',  'label' => 'AI'],
    'template' => ['bg' => 'bg-amber-900/50',   'text' => 'text-amber-400',   'label' => 'Template'],
    'game'     => ['bg' => 'bg-cyan-900/50',     'text' => 'text-cyan-400',    'label' => 'Game'],
];
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
        </svg>
        <div>
            <p class="text-sm text-indigo-300">Triggers are words/phrases that activate Cimol. When a message contains a trigger word, the bot will respond.</p>
            <p class="text-xs text-indigo-400/70 mt-1">Higher priority triggers are checked first. Use <span class="font-mono">exact</span> for precise matching, <span class="font-mono">contains</span> for partial, <span class="font-mono">startswith</span> for prefix, or <span class="font-mono">regex</span> for patterns.</p>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <?php
    $totalTriggers = count($triggers);
    $activeTriggers = count(array_filter($triggers, fn($t) => (bool) $t['is_active']));
    $inactiveTriggers = $totalTriggers - $activeTriggers;
    $typeGroups = [];
    foreach ($triggers as $t) {
        $typeGroups[$t['trigger_type']] = ($typeGroups[$t['trigger_type']] ?? 0) + 1;
    }
    ?>
    <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Total</p>
        <p class="text-xl font-bold text-white"><?= $totalTriggers ?></p>
    </div>
    <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Active</p>
        <p class="text-xl font-bold text-green-400"><?= $activeTriggers ?></p>
    </div>
    <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Inactive</p>
        <p class="text-xl font-bold text-red-400"><?= $inactiveTriggers ?></p>
    </div>
    <div class="bg-gray-800 rounded-xl border border-gray-700 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Types</p>
        <div class="flex items-center gap-1.5 mt-1">
            <?php foreach ($typeGroups as $type => $count):
                $badge = $typeBadges[$type] ?? $typeBadges['contains'];
            ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium <?= $badge['bg'] ?> <?= $badge['text'] ?>"><?= $count ?></span>
            <?php endforeach; ?>
            <?php if (empty($typeGroups)): ?>
                <span class="text-sm text-gray-500">-</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Triggers Table -->
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">All Triggers</h3>
                <p class="text-xs text-gray-400 mt-0.5"><?= $totalTriggers ?> trigger<?= $totalTriggers !== 1 ? 's' : '' ?> configured</p>
            </div>
        </div>
    </div>

    <?php if (empty($triggers)): ?>
        <div class="p-8 text-center">
            <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
            </svg>
            <p class="text-gray-400 text-sm">No triggers configured yet. Add one below to get started.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-gray-700 bg-gray-800/80">
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-16">Active</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Trigger Word</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-28">Type</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-28">Response</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-20 text-center">Priority</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-32 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    <?php foreach ($triggers as $t):
                        $isActive = (bool) $t['is_active'];
                        $type = $t['trigger_type'] ?? 'contains';
                        $mode = $t['response_mode'] ?? 'ai';
                        $tBadge = $typeBadges[$type] ?? $typeBadges['contains'];
                        $mBadge = $modeBadges[$mode] ?? $modeBadges['ai'];
                    ?>
                        <tr class="hover:bg-gray-700/30 transition-colors" id="trigger-row-<?= $t['id'] ?>">
                            <!-- Active Toggle -->
                            <td class="px-5 py-3">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" title="<?= $isActive ? 'Click to deactivate' : 'Click to activate' ?>"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-800 <?= $isActive ? 'bg-indigo-600' : 'bg-gray-600' ?>">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?= $isActive ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                                    </button>
                                </form>
                            </td>

                            <!-- Trigger Word -->
                            <td class="px-5 py-3">
                                <span class="font-mono text-sm font-medium <?= $isActive ? 'text-white' : 'text-gray-500' ?>"><?= htmlspecialchars($t['trigger_word']) ?></span>
                            </td>

                            <!-- Type Badge -->
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $tBadge['bg'] ?> <?= $tBadge['text'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $tBadge['dot'] ?>"></span>
                                    <?= $type ?>
                                </span>
                            </td>

                            <!-- Response Mode Badge -->
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $mBadge['bg'] ?> <?= $mBadge['text'] ?>">
                                    <?= $mBadge['label'] ?>
                                </span>
                            </td>

                            <!-- Priority -->
                            <td class="px-5 py-3 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-700 text-sm font-bold <?= $t['priority'] >= 8 ? 'text-amber-400' : ($t['priority'] >= 5 ? 'text-gray-200' : 'text-gray-500') ?>">
                                    <?= (int) $t['priority'] ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" onclick="toggleEditForm(<?= $t['id'] ?>)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-900/30 rounded-lg transition-colors"
                                            title="Edit trigger">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button type="button" onclick="deleteTrigger(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['trigger_word']), ENT_QUOTES) ?>')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-400 hover:text-red-300 hover:bg-red-900/30 rounded-lg transition-colors"
                                            title="Delete trigger">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Inline Edit Form (hidden by default) -->
                        <tr id="edit-form-<?= $t['id'] ?>" class="hidden">
                            <td colspan="6" class="p-0">
                                <div class="bg-gray-800/60 border-l-4 border-l-indigo-500">
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">

                                        <div class="p-5 space-y-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <!-- Trigger Word -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Trigger Word</label>
                                                    <input type="text" name="trigger_word" required
                                                           value="<?= htmlspecialchars($t['trigger_word']) ?>"
                                                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                                </div>

                                                <!-- Type -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Type</label>
                                                    <select name="trigger_type"
                                                            class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                                        <option value="exact" <?= $type === 'exact' ? 'selected' : '' ?>>exact</option>
                                                        <option value="contains" <?= $type === 'contains' ? 'selected' : '' ?>>contains</option>
                                                        <option value="startswith" <?= $type === 'startswith' ? 'selected' : '' ?>>startswith</option>
                                                        <option value="regex" <?= $type === 'regex' ? 'selected' : '' ?>>regex</option>
                                                    </select>
                                                </div>

                                                <!-- Response Mode -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Response Mode</label>
                                                    <select name="response_mode"
                                                            class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                                        <option value="ai" <?= $mode === 'ai' ? 'selected' : '' ?>>AI</option>
                                                        <option value="template" <?= $mode === 'template' ? 'selected' : '' ?>>Template</option>
                                                        <option value="game" <?= $mode === 'game' ? 'selected' : '' ?>>Game</option>
                                                    </select>
                                                </div>

                                                <!-- Priority -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Priority</label>
                                                    <input type="number" name="priority" min="0" max="100"
                                                           value="<?= (int) $t['priority'] ?>"
                                                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                                </div>
                                            </div>

                                            <!-- Active Checkbox -->
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>
                                                       class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-gray-800">
                                                <span class="text-sm text-gray-300">Active</span>
                                            </label>
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
                                            <button type="button" onclick="toggleEditForm(<?= $t['id'] ?>)"
                                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add New Trigger -->
<div id="add-trigger-section">
    <!-- Add Button (shown when form is hidden) -->
    <button type="button" id="add-trigger-btn" onclick="toggleAddForm()"
            class="w-full py-4 rounded-xl border-2 border-dashed border-gray-700 hover:border-emerald-600 bg-gray-800/50 hover:bg-gray-800 text-gray-400 hover:text-emerald-400 transition-all flex items-center justify-center gap-3 group">
        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 group-hover:bg-emerald-500/20 flex items-center justify-center transition-colors">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
        </div>
        <span class="text-sm font-medium">Add New Trigger</span>
    </button>

    <!-- Add Form (hidden by default) -->
    <div id="add-trigger-form" class="hidden bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 border-l-emerald-500">
        <div class="px-5 py-4 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">Add New Trigger</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Create a new trigger word for Cimol</p>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Trigger Word -->
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="new_trigger_word" class="block text-sm font-medium text-gray-300 mb-1.5">Trigger Word</label>
                        <input type="text" id="new_trigger_word" name="trigger_word" required
                               placeholder="e.g. cimol, hey bot"
                               class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="new_trigger_type" class="block text-sm font-medium text-gray-300 mb-1.5">Type</label>
                        <select id="new_trigger_type" name="trigger_type"
                                class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                            <option value="exact">exact</option>
                            <option value="contains" selected>contains</option>
                            <option value="startswith">startswith</option>
                            <option value="regex">regex</option>
                        </select>
                    </div>

                    <!-- Response Mode -->
                    <div>
                        <label for="new_response_mode" class="block text-sm font-medium text-gray-300 mb-1.5">Response Mode</label>
                        <select id="new_response_mode" name="response_mode"
                                class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                            <option value="ai" selected>AI</option>
                            <option value="template">Template</option>
                            <option value="game">Game</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label for="new_priority" class="block text-sm font-medium text-gray-300 mb-1.5">Priority</label>
                        <input type="number" id="new_priority" name="priority" min="0" max="100" value="5"
                               class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                        <p class="mt-1 text-xs text-gray-500">0-100, higher = checked first</p>
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
                    Add Trigger
                </button>
                <button type="button" onclick="toggleAddForm()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="delete-form" method="POST" action="" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete-id" value="">
</form>

<script>
// Toggle inline edit form
function toggleEditForm(id) {
    const row = document.getElementById('edit-form-' + id);
    if (row.classList.contains('hidden')) {
        // Close all other edit forms first
        document.querySelectorAll('[id^="edit-form-"]').forEach(function(el) {
            el.classList.add('hidden');
        });
        row.classList.remove('hidden');
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        row.classList.add('hidden');
    }
}

// Toggle add form
function toggleAddForm() {
    var btn = document.getElementById('add-trigger-btn');
    var form = document.getElementById('add-trigger-form');

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        btn.classList.add('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        document.getElementById('new_trigger_word').focus();
    } else {
        form.classList.add('hidden');
        btn.classList.remove('hidden');
    }
}

// Delete trigger
function deleteTrigger(id, word) {
    if (!confirm('Delete trigger "' + word + '"?\n\nThis action cannot be undone.')) {
        return;
    }
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php adminFooter(); ?>
