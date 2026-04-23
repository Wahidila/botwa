<?php
/**
 * WAHA Config - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();
require_once __DIR__ . '/layout.php';

$db = \BotWA\Database::getInstance();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\BotWA\AdminAuth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token';
        $messageType = 'error';
    } else {
        $fields = ['waha_api_url', 'waha_api_key', 'waha_session', 'waha_group_id', 'waha_webhook_secret', 'bot_name', 'bot_phone', 'bot_enabled', 'bot_typing_delay', 'bot_memory_limit', 'bot_response_chance'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                \BotWA\Config::set($field, $_POST[$field]);
            }
        }
        // Handle checkbox for bot_enabled
        \BotWA\Config::set('bot_enabled', isset($_POST['bot_enabled']) ? '1' : '0');
        \BotWA\Config::clearCache();
        $message = 'WAHA config saved!';
        $messageType = 'success';
    }
}

// Load current values
$wahaApiUrl = \BotWA\Config::get('waha_api_url', '');
$wahaApiKey = \BotWA\Config::get('waha_api_key', '');
$wahaSession = \BotWA\Config::get('waha_session', 'default');
$wahaGroupId = \BotWA\Config::get('waha_group_id', '');
$wahaWebhookSecret = \BotWA\Config::get('waha_webhook_secret', '');
$botName = \BotWA\Config::get('bot_name', 'Cimol');
$botPhone = \BotWA\Config::get('bot_phone', '');
$botEnabled = \BotWA\Config::get('bot_enabled', '1');
$botTypingDelay = \BotWA\Config::get('bot_typing_delay', 2);
$botMemoryLimit = \BotWA\Config::get('bot_memory_limit', 20);
$botResponseChance = \BotWA\Config::get('bot_response_chance', 100);

// Determine webhook URL
$webhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . '/webhook.php';

adminHeader('WAHA Config', 'waha');
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

<!-- Test Connection Result -->
<div id="test-result" class="mb-6 hidden px-4 py-3 rounded-lg text-sm font-medium border"></div>

<form method="POST" action="" id="waha-form">
    <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">

        <!-- WAHA Server Card -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">WAHA Server</h3>
                        <p class="text-xs text-gray-400 mt-0.5">WhatsApp HTTP API connection settings</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-5">
                <!-- API URL -->
                <div>
                    <label for="waha_api_url" class="block text-sm font-medium text-gray-300 mb-1.5">API URL</label>
                    <input type="text" id="waha_api_url" name="waha_api_url"
                           value="<?= htmlspecialchars($wahaApiUrl) ?>"
                           placeholder="https://waha-xxx.sgp.../api"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">WAHA server base URL (e.g. https://waha.example.com/api)</p>
                </div>

                <!-- API Key -->
                <div>
                    <label for="waha_api_key" class="block text-sm font-medium text-gray-300 mb-1.5">API Key</label>
                    <div class="relative">
                        <input type="password" id="waha_api_key" name="waha_api_key"
                               value="<?= htmlspecialchars($wahaApiKey) ?>"
                               placeholder="your-waha-api-key"
                               class="w-full px-3 py-2.5 pr-10 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                        <button type="button" onclick="togglePassword('waha_api_key', 'waha-eye', 'waha-eye-off')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-200 transition-colors">
                            <svg id="waha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg id="waha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">WAHA API authentication key</p>
                </div>

                <!-- Session Name -->
                <div>
                    <label for="waha_session" class="block text-sm font-medium text-gray-300 mb-1.5">Session Name</label>
                    <input type="text" id="waha_session" name="waha_session"
                           value="<?= htmlspecialchars($wahaSession) ?>"
                           placeholder="default"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">WAHA session name (default: "default")</p>
                </div>

                <!-- Group ID -->
                <div>
                    <label for="waha_group_id" class="block text-sm font-medium text-gray-300 mb-1.5">Group ID</label>
                    <input type="text" id="waha_group_id" name="waha_group_id"
                           value="<?= htmlspecialchars($wahaGroupId) ?>"
                           placeholder="628xxx@g.us"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">Format: 628xxx@g.us</p>
                </div>

                <!-- Webhook Secret -->
                <div>
                    <label for="waha_webhook_secret" class="block text-sm font-medium text-gray-300 mb-1.5">Webhook Secret</label>
                    <input type="text" id="waha_webhook_secret" name="waha_webhook_secret"
                           value="<?= htmlspecialchars($wahaWebhookSecret) ?>"
                           placeholder="optional-secret-key"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">Optional, for webhook verification</p>
                </div>

                <!-- Test WAHA Connection Button -->
                <div class="pt-2">
                    <button type="button" onclick="testWahaConnection()"
                            id="test-waha-btn"
                            class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 text-sm font-medium rounded-lg border border-emerald-700/50 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                        <svg id="waha-test-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <svg id="waha-test-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="waha-test-text">Test WAHA Connection</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Bot Settings Card -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-purple-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">Bot Settings</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Configure bot behavior and identity</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-5">
                <!-- Bot Name -->
                <div>
                    <label for="bot_name" class="block text-sm font-medium text-gray-300 mb-1.5">Bot Name</label>
                    <input type="text" id="bot_name" name="bot_name"
                           value="<?= htmlspecialchars($botName) ?>"
                           placeholder="Cimol"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">Display name for the bot in conversations</p>
                </div>

                <!-- Bot Phone Number -->
                <div>
                    <label for="bot_phone" class="block text-sm font-medium text-gray-300 mb-1.5">Bot Phone Number</label>
                    <input type="text" id="bot_phone" name="bot_phone"
                           value="<?= htmlspecialchars($botPhone) ?>"
                           placeholder="628xxxxxxxxxx"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">WhatsApp number used by the bot (with country code)</p>
                </div>

                <!-- Bot Enabled -->
                <div>
                    <div class="flex items-center justify-between">
                        <div>
                            <label for="bot_enabled" class="text-sm font-medium text-gray-300">Bot Enabled</label>
                            <p class="text-xs text-gray-500 mt-0.5">Toggle bot on/off without changing other settings</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="bot_enabled" name="bot_enabled" value="1"
                                   <?= ($botEnabled == '1' || $botEnabled === true) ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Typing Delay -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="bot_typing_delay" class="text-sm font-medium text-gray-300">Typing Delay</label>
                        <span id="delay-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($botTypingDelay) ?>s</span>
                    </div>
                    <input type="number" id="bot_typing_delay" name="bot_typing_delay"
                           value="<?= htmlspecialchars($botTypingDelay) ?>"
                           min="0" max="10" step="1"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('delay-value').textContent = this.value + 's'">
                    <p class="mt-1.5 text-xs text-gray-500">Simulated typing delay before sending reply (0-10 seconds)</p>
                </div>

                <!-- Memory Limit -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="bot_memory_limit" class="text-sm font-medium text-gray-300">Memory Limit</label>
                        <span id="memory-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($botMemoryLimit) ?> msgs</span>
                    </div>
                    <input type="number" id="bot_memory_limit" name="bot_memory_limit"
                           value="<?= htmlspecialchars($botMemoryLimit) ?>"
                           min="5" max="50" step="1"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('memory-value').textContent = this.value + ' msgs'">
                    <p class="mt-1.5 text-xs text-gray-500">Number of recent messages to remember per conversation (5-50)</p>
                </div>

                <!-- Response Chance -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="bot_response_chance" class="text-sm font-medium text-gray-300">Response Chance</label>
                        <span id="chance-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($botResponseChance) ?>%</span>
                    </div>
                    <input type="number" id="bot_response_chance" name="bot_response_chance"
                           value="<?= htmlspecialchars($botResponseChance) ?>"
                           min="0" max="100" step="1"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('chance-value').textContent = this.value + '%'">
                    <p class="mt-1.5 text-xs text-gray-500">Probability of bot responding to non-triggered messages (0-100%)</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Webhook Info Card -->
    <div class="mb-6 bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">Webhook Info</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Configure this URL in your WAHA server webhook settings</p>
                </div>
            </div>
        </div>
        <div class="p-5 space-y-4">
            <!-- Webhook URL -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Webhook URL</label>
                <div class="flex items-center gap-2">
                    <div class="flex-1 px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-sm font-mono text-emerald-400 overflow-x-auto whitespace-nowrap" id="webhook-url-display">
                        <?= htmlspecialchars($webhookUrl) ?>
                    </div>
                    <button type="button" onclick="copyWebhookUrl()"
                            id="copy-webhook-btn"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg border border-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-800 flex-shrink-0"
                            title="Copy to clipboard">
                        <svg id="copy-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <svg id="check-icon" class="w-4 h-4 hidden text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span id="copy-text">Copy</span>
                    </button>
                </div>
                <p class="mt-1.5 text-xs text-gray-500">Set this URL as the webhook endpoint in your WAHA dashboard</p>
            </div>

            <!-- Webhook Secret Display -->
            <?php if (!empty($wahaWebhookSecret)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Webhook Secret</label>
                <div class="px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-sm font-mono text-gray-400">
                    <?= htmlspecialchars(str_repeat('*', min(strlen($wahaWebhookSecret), 20))) ?>
                    <span class="text-xs text-gray-500 ml-2">(configured)</span>
                </div>
            </div>
            <?php else: ?>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Webhook Secret</label>
                <div class="px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-sm text-gray-500 italic">
                    Not configured &mdash; set one above for added security
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Save Config
        </button>
    </div>
</form>

<script>
function togglePassword(inputId, eyeId, eyeOffId) {
    const input = document.getElementById(inputId);
    const eyeIcon = document.getElementById(eyeId);
    const eyeOffIcon = document.getElementById(eyeOffId);

    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.classList.add('hidden');
        eyeOffIcon.classList.remove('hidden');
    } else {
        input.type = 'password';
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
    }
}

function testWahaConnection() {
    const btn = document.getElementById('test-waha-btn');
    const icon = document.getElementById('waha-test-icon');
    const spinner = document.getElementById('waha-test-spinner');
    const text = document.getElementById('waha-test-text');
    const result = document.getElementById('test-result');
    const csrf = document.getElementById('csrf_token').value;

    // Disable button and show spinner
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    text.textContent = 'Testing...';
    result.classList.add('hidden');

    fetch('test.php?action=test_waha', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ csrf_token: csrf })
    })
    .then(response => response.json())
    .then(data => {
        result.classList.remove('hidden', 'bg-green-900/50', 'text-green-400', 'border-green-700', 'bg-red-900/50', 'text-red-400', 'border-red-700');

        if (data.success) {
            result.classList.add('bg-green-900/50', 'text-green-400', 'border-green-700');
            result.innerHTML = '<div class="flex items-center gap-2">' +
                '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<span>WAHA connection successful! ' + (data.message || 'Server is reachable.') + '</span>' +
                '</div>';
        } else {
            result.classList.add('bg-red-900/50', 'text-red-400', 'border-red-700');
            result.innerHTML = '<div class="flex items-center gap-2">' +
                '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                '<span>Connection failed: ' + (data.message || 'Unknown error') + '</span>' +
                '</div>';
        }
    })
    .catch(error => {
        result.classList.remove('hidden', 'bg-green-900/50', 'text-green-400', 'border-green-700', 'bg-red-900/50', 'text-red-400', 'border-red-700');
        result.classList.add('bg-red-900/50', 'text-red-400', 'border-red-700');
        result.innerHTML = '<div class="flex items-center gap-2">' +
            '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
            '<span>Request failed: ' + error.message + '</span>' +
            '</div>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
        text.textContent = 'Test WAHA Connection';
    });
}

function copyWebhookUrl() {
    const url = document.getElementById('webhook-url-display').textContent.trim();
    const copyIcon = document.getElementById('copy-icon');
    const checkIcon = document.getElementById('check-icon');
    const copyText = document.getElementById('copy-text');

    navigator.clipboard.writeText(url).then(() => {
        copyIcon.classList.add('hidden');
        checkIcon.classList.remove('hidden');
        copyText.textContent = 'Copied!';

        setTimeout(() => {
            copyIcon.classList.remove('hidden');
            checkIcon.classList.add('hidden');
            copyText.textContent = 'Copy';
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = url;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        copyIcon.classList.add('hidden');
        checkIcon.classList.remove('hidden');
        copyText.textContent = 'Copied!';

        setTimeout(() => {
            copyIcon.classList.remove('hidden');
            checkIcon.classList.add('hidden');
            copyText.textContent = 'Copy';
        }, 2000);
    });
}
</script>

<?php adminFooter(); ?>
