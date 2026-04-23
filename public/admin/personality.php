<?php
/**
 * Personality Management - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();
$personality = new \BotWA\PersonalityEngine();
$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\BotWA\AdminAuth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            $id = (int) $_POST['id'];
            $value = $_POST['persona_value'];
            $isActive = isset($_POST['is_active']);
            $personality->update($id, $value, $isActive);
            $message = 'Personality updated!';
            $messageType = 'success';
        } elseif ($action === 'add') {
            $personality->add($_POST['persona_key'], $_POST['persona_value'], $_POST['description'] ?? '', (int)($_POST['sort_order'] ?? 99));
            $message = 'New personality component added!';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $personality->delete((int) $_POST['id']);
            $message = 'Personality component deleted!';
            $messageType = 'success';
        }
    }
}

$personas = $personality->getAll();
adminHeader('Personality', 'personality');
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="text-sm text-indigo-300">Personality components define how Cimol behaves. They are combined into the system prompt in order.</p>
            <p class="text-xs text-indigo-400/70 mt-1">Drag the sort order to control the sequence. Only active components are included in the prompt.</p>
        </div>
    </div>
</div>

<!-- Personality Components -->
<div class="space-y-4 mb-8">
    <?php if (empty($personas)): ?>
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-8 text-center">
            <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-gray-400 text-sm">No personality components yet. Add one below to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($personas as $p): ?>
            <?php $isActive = (bool) $p['is_active']; ?>
            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 <?= $isActive ? 'border-l-indigo-500' : 'border-l-gray-600' ?>">
                <!-- Card Header (clickable to expand) -->
                <button type="button"
                        onclick="toggleCard(<?= $p['id'] ?>)"
                        class="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-750 transition-colors text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-200" id="chevron-<?= $p['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-bold text-white"><?= htmlspecialchars($p['persona_key']) ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $isActive ? 'bg-green-900/50 text-green-400' : 'bg-gray-700 text-gray-400' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                                <span class="text-xs text-gray-500 font-mono">#<?= (int) $p['sort_order'] ?></span>
                            </div>
                            <?php if (!empty($p['description'])): ?>
                                <p class="text-xs text-gray-400 mt-0.5 truncate"><?= htmlspecialchars($p['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                        <span class="text-xs text-gray-500"><?= mb_strlen($p['persona_value']) ?> chars</span>
                    </div>
                </button>

                <!-- Card Body (expandable) -->
                <div id="card-body-<?= $p['id'] ?>" class="hidden border-t border-gray-700">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">

                        <div class="p-5 space-y-4">
                            <!-- Persona Value -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Persona Value</label>
                                <textarea name="persona_value"
                                          rows="6"
                                          class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors font-mono leading-relaxed resize-y"
                                          placeholder="Enter the personality prompt text..."><?= htmlspecialchars($p['persona_value']) ?></textarea>
                            </div>

                            <!-- Active Toggle -->
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" <?= $isActive ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                                <span class="text-sm text-gray-300">Active</span>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center justify-between">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save
                            </button>

                            <button type="button"
                                    onclick="deletePersona(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['persona_key']), ENT_QUOTES) ?>')"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-900/30 hover:bg-red-900/50 text-red-400 hover:text-red-300 text-sm font-medium rounded-lg border border-red-800/50 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add New Component -->
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 border-l-emerald-500">
    <div class="px-5 py-4 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">Add New Component</h3>
                <p class="text-xs text-gray-400 mt-0.5">Create a new personality building block for Cimol</p>
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
        <input type="hidden" name="action" value="add">

        <div class="p-5 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Key -->
                <div>
                    <label for="new_persona_key" class="block text-sm font-medium text-gray-300 mb-1.5">Key</label>
                    <input type="text" id="new_persona_key" name="persona_key" required
                           placeholder="e.g. base_identity"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">Unique identifier (snake_case)</p>
                </div>

                <!-- Description -->
                <div>
                    <label for="new_description" class="block text-sm font-medium text-gray-300 mb-1.5">Description</label>
                    <input type="text" id="new_description" name="description"
                           placeholder="e.g. Core identity and name"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">Short description of this component</p>
                </div>

                <!-- Sort Order -->
                <div>
                    <label for="new_sort_order" class="block text-sm font-medium text-gray-300 mb-1.5">Sort Order</label>
                    <input type="number" id="new_sort_order" name="sort_order" value="99" min="0" max="999"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">Lower = higher priority in prompt</p>
                </div>
            </div>

            <!-- Value -->
            <div>
                <label for="new_persona_value" class="block text-sm font-medium text-gray-300 mb-1.5">Value</label>
                <textarea id="new_persona_value" name="persona_value" required
                          rows="6"
                          placeholder="Enter the personality prompt text for this component..."
                          class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors font-mono leading-relaxed resize-y"></textarea>
            </div>
        </div>

        <!-- Add Button -->
        <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Add Component
            </button>
        </div>
    </form>
</div>

<!-- Delete Form (hidden) -->
<form id="delete-form" method="POST" action="" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete-id" value="">
</form>

<script>
function toggleCard(id) {
    const body = document.getElementById('card-body-' + id);
    const chevron = document.getElementById('chevron-' + id);

    if (body.classList.contains('hidden')) {
        body.classList.remove('hidden');
        chevron.style.transform = 'rotate(90deg)';
    } else {
        body.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}

function deletePersona(id, key) {
    if (!confirm('Delete personality component "' + key + '"?\n\nThis action cannot be undone.')) {
        return;
    }

    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php adminFooter(); ?>
