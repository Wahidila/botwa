<?php
/**
 * AI Settings - Cimol Admin Panel
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
        // Update settings
        $fields = ['ai_base_url', 'ai_api_key', 'ai_model', 'ai_temperature', 'ai_max_tokens', 'ai_top_p', 'ai_frequency_penalty', 'ai_presence_penalty'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                \BotWA\Config::set($field, $_POST[$field]);
            }
        }
        \BotWA\Config::clearCache();
        $message = 'Settings saved successfully!';
        $messageType = 'success';
    }
}

// Load current values
$aiBaseUrl = \BotWA\Config::get('ai_base_url', '');
$aiApiKey = \BotWA\Config::get('ai_api_key', '');
$aiModel = \BotWA\Config::get('ai_model', '');
$aiTemperature = \BotWA\Config::get('ai_temperature', 0.7);
$aiMaxTokens = \BotWA\Config::get('ai_max_tokens', 1024);
$aiTopP = \BotWA\Config::get('ai_top_p', 1.0);
$aiFrequencyPenalty = \BotWA\Config::get('ai_frequency_penalty', 0.0);
$aiPresencePenalty = \BotWA\Config::get('ai_presence_penalty', 0.0);

adminHeader('AI Settings', 'settings');
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

<form method="POST" action="" id="settings-form">
    <input type="hidden" name="csrf_token" value="<?= \BotWA\AdminAuth::generateCsrfToken() ?>">

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">

        <!-- AI Provider Card -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">AI Provider</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Configure your AI API connection</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-5">
                <!-- Base URL -->
                <div>
                    <label for="ai_base_url" class="block text-sm font-medium text-gray-300 mb-1.5">Base URL</label>
                    <input type="text" id="ai_base_url" name="ai_base_url"
                           value="<?= htmlspecialchars($aiBaseUrl) ?>"
                           placeholder="https://api.openai.com"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">OpenAI-compatible API endpoint</p>
                </div>

                <!-- API Key -->
                <div>
                    <label for="ai_api_key" class="block text-sm font-medium text-gray-300 mb-1.5">API Key</label>
                    <div class="relative">
                        <input type="password" id="ai_api_key" name="ai_api_key"
                               value="<?= htmlspecialchars($aiApiKey) ?>"
                               placeholder="sk-..."
                               class="w-full px-3 py-2.5 pr-10 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                        <button type="button" onclick="toggleApiKey()" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-200 transition-colors">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg id="eye-off-icon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-500">Your API key (stored securely in database)</p>
                </div>

                <!-- Model -->
                <div>
                    <label for="ai_model" class="block text-sm font-medium text-gray-300 mb-1.5">Model</label>
                    <input type="text" id="ai_model" name="ai_model"
                           value="<?= htmlspecialchars($aiModel) ?>"
                           placeholder="gpt-4o"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors">
                    <p class="mt-1.5 text-xs text-gray-500">Model name (e.g. gpt-4o, gpt-3.5-turbo, claude-3-opus)</p>
                </div>
            </div>
        </div>

        <!-- Model Parameters Card -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-purple-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white">Model Parameters</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Fine-tune AI response behavior</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-5">
                <!-- Temperature -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="ai_temperature" class="text-sm font-medium text-gray-300">Temperature</label>
                        <span id="temp-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($aiTemperature) ?></span>
                    </div>
                    <input type="number" id="ai_temperature" name="ai_temperature"
                           value="<?= htmlspecialchars($aiTemperature) ?>"
                           step="0.1" min="0" max="2"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('temp-value').textContent = this.value">
                    <p class="mt-1.5 text-xs text-gray-500">Controls randomness. Lower = more focused, higher = more creative (0-2)</p>
                </div>

                <!-- Max Tokens -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="ai_max_tokens" class="text-sm font-medium text-gray-300">Max Tokens</label>
                        <span id="tokens-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($aiMaxTokens) ?></span>
                    </div>
                    <input type="number" id="ai_max_tokens" name="ai_max_tokens"
                           value="<?= htmlspecialchars($aiMaxTokens) ?>"
                           min="1" max="8192"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('tokens-value').textContent = this.value">
                    <p class="mt-1.5 text-xs text-gray-500">Maximum number of tokens in the AI response (1-8192)</p>
                </div>

                <!-- Top P -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="ai_top_p" class="text-sm font-medium text-gray-300">Top P</label>
                        <span id="topp-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($aiTopP) ?></span>
                    </div>
                    <input type="number" id="ai_top_p" name="ai_top_p"
                           value="<?= htmlspecialchars($aiTopP) ?>"
                           step="0.05" min="0" max="1"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('topp-value').textContent = this.value">
                    <p class="mt-1.5 text-xs text-gray-500">Nucleus sampling. Lower = more focused token selection (0-1)</p>
                </div>

                <!-- Frequency Penalty -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="ai_frequency_penalty" class="text-sm font-medium text-gray-300">Frequency Penalty</label>
                        <span id="freq-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($aiFrequencyPenalty) ?></span>
                    </div>
                    <input type="number" id="ai_frequency_penalty" name="ai_frequency_penalty"
                           value="<?= htmlspecialchars($aiFrequencyPenalty) ?>"
                           step="0.1" min="0" max="2"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('freq-value').textContent = this.value">
                    <p class="mt-1.5 text-xs text-gray-500">Reduces repetition of frequent tokens. Higher = less repetition (0-2)</p>
                </div>

                <!-- Presence Penalty -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="ai_presence_penalty" class="text-sm font-medium text-gray-300">Presence Penalty</label>
                        <span id="pres-value" class="text-xs font-mono text-indigo-400"><?= htmlspecialchars($aiPresencePenalty) ?></span>
                    </div>
                    <input type="number" id="ai_presence_penalty" name="ai_presence_penalty"
                           value="<?= htmlspecialchars($aiPresencePenalty) ?>"
                           step="0.1" min="0" max="2"
                           class="w-full px-3 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           oninput="document.getElementById('pres-value').textContent = this.value">
                    <p class="mt-1.5 text-xs text-gray-500">Encourages new topics. Higher = more topic diversity (0-2)</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-900">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Save Settings
        </button>

        <button type="button" onclick="testConnection()"
                id="test-btn"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg border border-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-900">
            <svg id="test-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <svg id="test-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span id="test-text">Test Connection</span>
        </button>
    </div>
</form>

<script>
function toggleApiKey() {
    const input = document.getElementById('ai_api_key');
    const eyeIcon = document.getElementById('eye-icon');
    const eyeOffIcon = document.getElementById('eye-off-icon');

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

function testConnection() {
    const btn = document.getElementById('test-btn');
    const icon = document.getElementById('test-icon');
    const spinner = document.getElementById('test-spinner');
    const text = document.getElementById('test-text');
    const result = document.getElementById('test-result');
    const csrf = document.getElementById('csrf_token').value;

    // Disable button and show spinner
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    text.textContent = 'Testing...';
    result.classList.add('hidden');

    fetch('test.php?action=test_ai', {
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
                '<span>Connection successful! ' + (data.message || 'AI provider is reachable.') + '</span>' +
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
        text.textContent = 'Test Connection';
    });
}
</script>

<?php adminFooter(); ?>
