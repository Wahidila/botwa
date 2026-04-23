<?php
/**
 * Members Management - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();
$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\BotWA\AdminAuth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $db->insert('members', [
                'phone_number' => $_POST['phone_number'],
                'lid' => $_POST['lid'] ?? null,
                'nickname' => $_POST['nickname'],
                'role' => $_POST['role'] ?? 'member',
                'notes' => $_POST['notes'] ?? '',
                'is_active' => 1,
            ]);
            $message = 'Member added!';
            $messageType = 'success';
        } elseif ($action === 'update') {
            $db->update('members', [
                'phone_number' => $_POST['phone_number'],
                'lid' => $_POST['lid'] ?? null,
                'nickname' => $_POST['nickname'],
                'role' => $_POST['role'],
                'notes' => $_POST['notes'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ], 'id = ?', [(int) $_POST['id']]);
            $message = 'Member updated!';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $db->delete('members', 'id = ?', [(int) $_POST['id']]);
            $message = 'Member deleted!';
            $messageType = 'success';
        }
    }
}

$members = $db->fetchAll("SELECT * FROM members ORDER BY role, nickname");
adminHeader('Members', 'members');

// Role color helpers
function roleAvatarColor(string $role): string {
    return match ($role) {
        'owner' => 'bg-indigo-600',
        'princess' => 'bg-pink-500',
        'bot' => 'bg-green-600',
        default => 'bg-gray-600',
    };
}

function roleBadgeClasses(string $role): string {
    return match ($role) {
        'owner' => 'bg-indigo-900/50 text-indigo-400 border border-indigo-700/50',
        'princess' => 'bg-pink-900/50 text-pink-400 border border-pink-700/50',
        'bot' => 'bg-green-900/50 text-green-400 border border-green-700/50',
        default => 'bg-gray-700 text-gray-400 border border-gray-600',
    };
}

function roleCardBorder(string $role): string {
    return match ($role) {
        'owner' => 'border-l-indigo-500',
        'princess' => 'border-l-pink-500',
        'bot' => 'border-l-green-500',
        default => 'border-l-gray-500',
    };
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="text-sm text-indigo-300">Members define who's in the group and their nicknames. The bot uses these nicknames when talking.</p>
            <p class="text-xs text-indigo-400/70 mt-1">Assign roles to control how the bot addresses and interacts with each member.</p>
        </div>
    </div>
</div>

<!-- Members Grid -->
<?php if (empty($members)): ?>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-8 text-center mb-8">
        <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <p class="text-gray-400 text-sm">No members yet. Add one below to get started.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        <?php foreach ($members as $m): ?>
            <?php
                $isActive = (bool) $m['is_active'];
                $initial = mb_strtoupper(mb_substr($m['nickname'] ?: '?', 0, 1));
            ?>
            <div class="bg-gray-800 rounded-xl border border-gray-700 border-l-4 <?= roleCardBorder($m['role']) ?> overflow-hidden" id="member-card-<?= $m['id'] ?>">
                <!-- Card Display -->
                <div class="p-5" id="member-display-<?= $m['id'] ?>">
                    <div class="flex items-start gap-4">
                        <!-- Avatar -->
                        <div class="w-12 h-12 rounded-full <?= roleAvatarColor($m['role']) ?> flex items-center justify-center text-white text-lg font-bold flex-shrink-0 shadow-lg">
                            <?= htmlspecialchars($initial) ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-bold text-white truncate"><?= htmlspecialchars($m['nickname']) ?></h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $isActive ? 'bg-green-900/50 text-green-400' : 'bg-red-900/50 text-red-400' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>

                            <p class="text-sm text-gray-400 font-mono mt-1"><?= htmlspecialchars($m['phone_number']) ?></p>
                            <?php if (!empty($m['lid'])): ?>
                                <p class="text-xs text-gray-500 font-mono">LID: <?= htmlspecialchars($m['lid']) ?></p>
                            <?php endif; ?>

                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= roleBadgeClasses($m['role']) ?>">
                                    <?= htmlspecialchars(ucfirst($m['role'])) ?>
                                </span>
                            </div>

                            <?php if (!empty($m['notes'])): ?>
                                <p class="text-xs text-gray-500 italic mt-2 line-clamp-2"><?= htmlspecialchars($m['notes']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-700">
                        <button type="button"
                                onclick="toggleEdit(<?= $m['id'] ?>)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-400 hover:text-indigo-300 text-xs font-medium rounded-lg border border-indigo-700/50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </button>
                        <button type="button"
                                onclick="deleteMember(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nickname']), ENT_QUOTES) ?>')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-900/20 hover:bg-red-900/30 text-red-400 hover:text-red-300 text-xs font-medium rounded-lg border border-red-800/50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>

                <!-- Edit Form (hidden by default) -->
                <div id="member-edit-<?= $m['id'] ?>" class="hidden">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">

                        <div class="p-5 space-y-4">
                            <!-- Phone Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Phone Number</label>
                                <input type="text" name="phone_number" required
                                       value="<?= htmlspecialchars($m['phone_number']) ?>"
                                       placeholder="628xxxxxxxxxx"
                                       class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                            </div>

                            <!-- LID (WAHA Linked ID) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">LID <span class="text-gray-500 font-normal">(WAHA Linked ID)</span></label>
                                <input type="text" name="lid"
                                       value="<?= htmlspecialchars($m['lid'] ?? '') ?>"
                                       placeholder="e.g. 2057322926141"
                                       class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                <p class="mt-1 text-xs text-gray-500">Dari WAHA Chat UI, format: angka@lid (isi angkanya saja)</p>
                            </div>

                            <!-- Nickname -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Nickname</label>
                                <input type="text" name="nickname" required
                                       value="<?= htmlspecialchars($m['nickname']) ?>"
                                       placeholder="e.g. Budi"
                                       class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                            </div>

                            <!-- Role -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Role</label>
                                <select name="role"
                                        class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                                    <option value="owner" <?= $m['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                                    <option value="princess" <?= $m['role'] === 'princess' ? 'selected' : '' ?>>Princess</option>
                                    <option value="member" <?= $m['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                    <option value="bot" <?= $m['role'] === 'bot' ? 'selected' : '' ?>>Bot</option>
                                </select>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1.5">Notes</label>
                                <textarea name="notes"
                                          rows="2"
                                          placeholder="Optional notes about this member..."
                                          class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors resize-y"><?= htmlspecialchars($m['notes'] ?? '') ?></textarea>
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

                        <!-- Form Actions -->
                        <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700 flex items-center gap-2">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save
                            </button>
                            <button type="button"
                                    onclick="toggleEdit(<?= $m['id'] ?>)"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add New Member -->
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden border-l-4 border-l-emerald-500">
    <div class="px-5 py-4 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white">Add New Member</h3>
                <p class="text-xs text-gray-400 mt-0.5">Register a new group member for the bot to recognize</p>
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">
        <input type="hidden" name="action" value="add">

        <div class="p-5 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Phone Number -->
                <div>
                    <label for="new_phone_number" class="block text-sm font-medium text-gray-300 mb-1.5">Phone Number</label>
                    <input type="text" id="new_phone_number" name="phone_number" required
                           placeholder="628xxxxxxxxxx"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">WhatsApp number with country code</p>
                </div>

                <!-- LID -->
                <div>
                    <label for="new_lid" class="block text-sm font-medium text-gray-300 mb-1.5">LID <span class="text-gray-500 font-normal">(WAHA)</span></label>
                    <input type="text" id="new_lid" name="lid"
                           placeholder="e.g. 2057322926141"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm font-mono placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">Dari WAHA Chat UI (angka@lid)</p>
                </div>

                <!-- Nickname -->
                <div>
                    <label for="new_nickname" class="block text-sm font-medium text-gray-300 mb-1.5">Nickname</label>
                    <input type="text" id="new_nickname" name="nickname" required
                           placeholder="e.g. Budi"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">How the bot will call this person</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Role -->
                <div>
                    <label for="new_role" class="block text-sm font-medium text-gray-300 mb-1.5">Role</label>
                    <select id="new_role" name="role"
                            class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                        <option value="member">Member</option>
                        <option value="owner">Owner</option>
                        <option value="princess">Princess</option>
                        <option value="bot">Bot</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Determines how the bot treats this member</p>
                </div>

                <!-- Notes -->
                <div>
                    <label for="new_notes" class="block text-sm font-medium text-gray-300 mb-1.5">Notes</label>
                    <input type="text" id="new_notes" name="notes"
                           placeholder="Optional notes..."
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-colors">
                    <p class="mt-1 text-xs text-gray-500">Extra info about this member</p>
                </div>
            </div>
        </div>

        <!-- Add Button -->
        <div class="px-5 py-3 bg-gray-800/50 border-t border-gray-700">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Add Member
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
function toggleEdit(id) {
    const display = document.getElementById('member-display-' + id);
    const edit = document.getElementById('member-edit-' + id);

    if (edit.classList.contains('hidden')) {
        // Show edit, hide display
        display.classList.add('hidden');
        edit.classList.remove('hidden');
    } else {
        // Show display, hide edit
        edit.classList.add('hidden');
        display.classList.remove('hidden');
    }
}

function deleteMember(id, nickname) {
    if (!confirm('Delete member "' + nickname + '"?\n\nThis action cannot be undone.')) {
        return;
    }

    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

<?php adminFooter(); ?>
