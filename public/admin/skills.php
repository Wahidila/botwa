<?php
/**
 * Skills Management - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();
$skillManager = new \BotWA\SkillManager();
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
            $skillManager->add([
                'skill_name' => $_POST['skill_name'],
                'skill_description' => $_POST['skill_description'],
                'skill_prompt' => $_POST['skill_prompt'],
                'skill_trigger' => $_POST['skill_trigger'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0,
                'category' => $_POST['category'] ?? 'fun',
            ]);
            $message = 'Skill added!';
            $messageType = 'success';
        } elseif ($action === 'update') {
            $id = (int) $_POST['id'];
            $skillManager->update($id, [
                'skill_name' => $_POST['skill_name'],
                'skill_description' => $_POST['skill_description'],
                'skill_prompt' => $_POST['skill_prompt'],
                'skill_trigger' => $_POST['skill_trigger'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'is_hidden' => isset($_POST['is_hidden']) ? 1 : 0,
                'category' => $_POST['category'] ?? 'fun',
            ]);
            $message = 'Skill updated!';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $skillManager->delete((int) $_POST['id']);
            $message = 'Skill deleted!';
            $messageType = 'success';
        } elseif ($action === 'toggle') {
            $skillManager->toggle((int) $_POST['id']);
            $message = 'Skill toggled!';
            $messageType = 'success';
        }
    }
}

$skills = $skillManager->getAll();
adminHeader('Skills', 'skills');

// Category config
$categories = [
    'romance' => ['label' => 'Romance', 'bg' => 'bg-pink-900/50', 'text' => 'text-pink-400', 'border' => 'border-pink-700', 'dot' => 'bg-pink-400'],
    'game'    => ['label' => 'Game',    'bg' => 'bg-blue-900/50', 'text' => 'text-blue-400', 'border' => 'border-blue-700', 'dot' => 'bg-blue-400'],
    'fun'     => ['label' => 'Fun',     'bg' => 'bg-yellow-900/50', 'text' => 'text-yellow-400', 'border' => 'border-yellow-700', 'dot' => 'bg-yellow-400'],
    'secret'  => ['label' => 'Secret',  'bg' => 'bg-red-900/50', 'text' => 'text-red-400', 'border' => 'border-red-700', 'dot' => 'bg-red-400'],
    'utility' => ['label' => 'Utility', 'bg' => 'bg-gray-700/50', 'text' => 'text-gray-400', 'border' => 'border-gray-600', 'dot' => 'bg-gray-400'],
];

// Count per category
$categoryCounts = [];
foreach ($skills as $s) {
    $cat = $s['category'] ?? 'fun';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <div>
            <p class="text-sm text-indigo-300">Skills define what Cimol can do. Each skill has a prompt that gets injected when triggered.</p>
            <p class="text-xs text-indigo-400/70 mt-1">Hidden skills run silently in the background. Secret skills are never revealed to users.</p>
        </div>
    </div>
</div>

<!-- Category Filter Tabs -->
<div class="mb-6 flex flex-wrap gap-2">
    <button type="button" onclick="filterCategory('all')" id="tab-all"
            class="category-tab inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white transition-colors">
        All
        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs bg-white/20"><?= count($skills) ?></span>
    </button>
    <?php foreach ($categories as $catKey => $catStyle): ?>
        <button type="button" onclick="filterCategory('<?= $catKey ?>')" id="tab-<?= $catKey ?>"
                class="category-tab inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700 transition-colors">
            <span class="w-2 h-2 rounded-full <?= $catStyle['dot'] ?>"></span>
            <?= $catStyle['label'] ?>
            <?php if (isset($categoryCounts[$catKey])): ?>
                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs bg-gray-700"><?= $categoryCounts[$catKey] ?></span>
            <?php endif; ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Skills Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8" id="skills-grid">
    <?php if (empty($skills)): ?>
        <div class="lg:col-span-2 bg-gray-800 rounded-xl border border-gray-700 p-8 text-center">
            <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <p class="text-gray-400 text-sm">No skills configured yet. Add one below to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ($skills as $s):
            $cat = $s['category'] ?? 'fun';
            $catStyle = $categories[$cat] ?? $categories['fun'];
            $isActive = (bool) $s['is_active'];
            $isHidden = (bool) $s['is_hidden'];
            $triggers = !empty($s['skill_trigger']) ? array_map('trim', explode(',', $s['skill_trigger'])) : [];
        ?>
            <div class="skill-card bg-gray-800 rounded-xl border border-gray-700 overflow-hidden flex flex-col" data-category="<?= $cat ?>">
                <!-- Card Header -->
                <div class="px-5 py-4 flex-1">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Category Badge -->
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium <?= $catStyle['bg'] ?> <?= $catStyle['text'] ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $catStyle['dot'] ?>"></span>
                                <?= $catStyle['label'] ?>
                            </span>
                            <!-- Hidden Badge -->
                            <?php if ($isHidden): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-400" title="Hidden skill - runs silently">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.05 6.05m3.828 3.828l4.242 4.242M6.05 6.05l-1.414-1.414M6.05 6.05L3 3m3.05 3.05l4.243 4.243m4.242 4.242L21 21m-3.175-3.175a9.97 9.97 0 001.563-3.029C18.268 9.943 14.478 7 10 7c-.69 0-1.36.065-2.013.188"/>
                                    </svg>
                                    Hidden
                                </span>
                            <?php endif; ?>
                        </div>
                        <!-- Quick Toggle -->
                        <form method="POST" action="" class="flex-shrink-0">
                            <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" title="<?= $isActive ? 'Click to deactivate' : 'Click to activate' ?>"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-800 <?= $isActive ? 'bg-indigo-600' : 'bg-gray-600' ?>">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?= $isActive ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                            </button>
                        </form>
                    </div>

                    <!-- Skill Name -->
                    <h3 class="text-base font-bold text-white mb-1"><?= htmlspecialchars($s['skill_name']) ?></h3>

                    <!-- Description -->
                    <p class="text-xs text-gray-400 mb-3 line-clamp-2"><?= htmlspecialchars($s['skill_description']) ?></p>

                    <!-- Trigger Tags -->
                    <?php if (!empty($triggers)): ?>
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            <?php foreach ($triggers as $trigger): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-gray-700/80 text-gray-300 border border-gray-600">
                                    <?= htmlspecialchars($trigger) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Card Actions -->
                <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center justify-between">
                    <button type="button" onclick="toggleEditForm(<?= $s['id'] ?>)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-900/30 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </button>
                    <button type="button" onclick="deleteSkill(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['skill_name']), ENT_QUOTES) ?>')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-400 hover:text-red-300 hover:bg-red-900/30 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete
                    </button>
                </div>

                <!-- Inline Edit Form (hidden by default) -->
                <div id="edit-form-<?= $s['id'] ?>" class="hidden border-t border-gray-700">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">

                        <div class="p-5 space-y-4">
                            <!-- Name & Category Row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Skill Name</label>
                                    <input type="text" name="skill_name" required
                                           value="<?= htmlspecialchars($s['skill_name']) ?>"
                                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Category</label>
                                    <select name="category"
                                            class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                        <?php foreach ($categories as $cKey => $cVal): ?>
                                            <option value="<?= $cKey ?>" <?= $cat === $cKey ? 'selected' : '' ?>><?= $cVal['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Description</label>
                                <input type="text" name="skill_description" required
                                       value="<?= htmlspecialchars($s['skill_description']) ?>"
                                       class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                            </div>

                            <!-- Prompt -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Prompt</label>
                                <textarea name="skill_prompt" required rows="5"
                                          class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors font-mono leading-relaxed resize-y"><?= htmlspecialchars($s['skill_prompt']) ?></textarea>
                            </div>

                            <!-- Trigger Words -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Trigger Words</label>
                                <input type="text" name="skill_trigger"
                                       value="<?= htmlspecialchars($s['skill_trigger'] ?? '') ?>"
                                       placeholder="e.g. gombal, rayu, flirt (comma separated)"
                                       class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                <p class="mt-1 text-xs text-gray-500">Comma-separated keywords that activate this skill</p>
                            </div>

                            <!-- Checkboxes -->
                            <div class="flex items-center gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>
                                           class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-gray-800">
                                    <span class="text-sm text-gray-300">Active</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_hidden" value="1" <?= $isHidden ? 'checked' : '' ?>
                                           class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-gray-800">
                                    <span class="text-sm text-gray-300">Hidden</span>
                                </label>
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
                            <button type="button" onclick="toggleEditForm(<?= $s['id'] ?>)"
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

<!-- Add New Skill -->
<div id="add-skill-section">
    <!-- Add Button (shown when form is hidden) -->
    <button type="button" id="add-skill-btn" onclick="toggleAddForm()"
            class="w-full py-4 rounded-xl border-2 border-dashed border-gray-700 hover:border-emerald-600 bg-gray-800/50 hover:bg-gray-800 text-gray-400 hover:text-emerald-400 transition-all flex items-center justify-center gap-3 group">
        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 group-hover:bg-emerald-500/20 flex items-center justify-center transition-colors">
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
        </div>
        <span class="text-sm font-medium">Add New Skill</span>
    </button>

    <!-- Add Form (hidden by default) -->
    <div id="add-skill-form" class="hidden bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 border-l-emerald-500">
        <div class="px-5 py-4 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">Add New Skill</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Create a new ability for Cimol</p>
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
                <!-- Name & Category Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label for="new_skill_name" class="block text-sm font-medium text-gray-300 mb-1.5">Skill Name</label>
                        <input type="text" id="new_skill_name" name="skill_name" required
                               placeholder="e.g. Gombal Master"
                               class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    </div>
                    <div>
                        <label for="new_category" class="block text-sm font-medium text-gray-300 mb-1.5">Category</label>
                        <select id="new_category" name="category"
                                class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                            <?php foreach ($categories as $cKey => $cVal): ?>
                                <option value="<?= $cKey ?>"><?= $cVal['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="new_skill_description" class="block text-sm font-medium text-gray-300 mb-1.5">Description</label>
                    <input type="text" id="new_skill_description" name="skill_description" required
                           placeholder="Short description of what this skill does"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                </div>

                <!-- Prompt -->
                <div>
                    <label for="new_skill_prompt" class="block text-sm font-medium text-gray-300 mb-1.5">Prompt</label>
                    <textarea id="new_skill_prompt" name="skill_prompt" required rows="6"
                              placeholder="The instruction prompt that will be injected into the AI context when this skill is triggered..."
                              class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors font-mono leading-relaxed resize-y"></textarea>
                </div>

                <!-- Trigger Words -->
                <div>
                    <label for="new_skill_trigger" class="block text-sm font-medium text-gray-300 mb-1.5">Trigger Words</label>
                    <input type="text" id="new_skill_trigger" name="skill_trigger"
                           placeholder="e.g. gombal, rayu, flirt (comma separated)"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">Comma-separated keywords that activate this skill. Leave empty for always-on skills.</p>
                </div>

                <!-- Checkboxes -->
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked
                               class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-gray-800">
                        <span class="text-sm text-gray-300">Active</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_hidden" value="1"
                               class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-gray-800">
                        <span class="text-sm text-gray-300">Hidden</span>
                    </label>
                </div>
            </div>

            <!-- Add Button -->
            <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add Skill
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
// Category filter
let activeFilter = 'all';

function filterCategory(category) {
    activeFilter = category;
    const cards = document.querySelectorAll('.skill-card');
    const tabs = document.querySelectorAll('.category-tab');

    // Update tab styles
    tabs.forEach(tab => {
        tab.className = tab.className
            .replace('bg-indigo-600 text-white', '')
            .replace('bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700', '');

        if (tab.id === 'tab-' + category) {
            tab.classList.add('bg-indigo-600', 'text-white');
        } else {
            tab.classList.add('bg-gray-800', 'text-gray-400', 'hover:bg-gray-700', 'hover:text-gray-200', 'border', 'border-gray-700');
        }
    });

    // Filter cards
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
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
    const btn = document.getElementById('add-skill-btn');
    const form = document.getElementById('add-skill-form');

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        btn.classList.add('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        form.classList.add('hidden');
        btn.classList.remove('hidden');
    }
}

// Delete skill
function deleteSkill(id, name) {
    if (!confirm('Delete skill "' + name + '"?\n\nThis action cannot be undone.')) {
        return;
    }
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php adminFooter(); ?>
