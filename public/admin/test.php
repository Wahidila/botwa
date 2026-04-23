<?php
/**
 * Test Bot - Cimol Admin Panel
 */
require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();
\BotWA\AdminAuth::requireAuth();

// Handle AJAX API calls
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];

    if ($action === 'test_ai') {
        try {
            $ai = new \BotWA\AIProvider();
            $result = $ai->testConnection();
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'test_firecrawl') {
        try {
            $fc = new \BotWA\FirecrawlSearch();
            if (!$fc->isAvailable()) {
                echo json_encode(['success' => false, 'message' => 'Firecrawl not configured. Set API key and enable it in settings first.']);
                exit;
            }
            $result = $fc->testConnection();
            $json = json_encode($result);
            if ($json === false) {
                echo json_encode(['success' => false, 'message' => 'JSON encode error: ' . json_last_error_msg()]);
            } else {
                echo $json;
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'test_waha') {
        try {
            $waha = new \BotWA\WahaClient();
            $result = $waha->checkSession();
            echo json_encode(['success' => $result !== null, 'data' => $result]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'test_message') {
        try {
            // Simulate bot response without sending to WA
            $testMessage = $_POST['message'] ?? '';
            $testSender = $_POST['sender'] ?? '628xxx';

            if (empty(trim($testMessage))) {
                echo json_encode(['error' => 'Message cannot be empty']);
                exit;
            }

            $personality = new \BotWA\PersonalityEngine();
            $memory = new \BotWA\MemoryManager();
            $skills = new \BotWA\SkillManager();
            $triggers = new \BotWA\TriggerManager();

            $messageData = [
                'id' => 'test_' . time(),
                'fromMe' => false,
                'chatId' => 'test@g.us',
                'senderPhone' => $testSender,
                'text' => $testMessage,
                'isGroup' => true,
                'timestamp' => time(),
            ];

            // Check trigger
            $triggerResult = $triggers->check($testMessage);

            // Build system prompt
            $systemPrompt = $personality->buildSystemPrompt($messageData);

            // Get memories
            $memories = $memory->getRelevantMemories($testMessage);
            $memoryContext = '';
            if (!empty($memories)) {
                $memoryContext = "KONTEKS YANG KAMU INGAT:\n";
                foreach ($memories as $mem) {
                    $memoryContext .= "- {$mem['content']}\n";
                }
            }

            // Get skills context
            $skillContext = $skills->getActiveSkillsContext($testMessage, $triggerResult);

            // Build messages
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            if ($memoryContext) {
                $messages[] = ['role' => 'system', 'content' => $memoryContext];
            }
            if ($skillContext) {
                $messages[] = ['role' => 'system', 'content' => $skillContext];
            }
            $messages[] = ['role' => 'user', 'content' => $testMessage];

            // Call AI
            $ai = new \BotWA\AIProvider();
            $result = $ai->chat($messages);

            echo json_encode([
                'triggered' => $triggerResult['triggered'],
                'trigger' => $triggerResult['trigger'] ?? null,
                'system_prompt' => $systemPrompt,
                'memory_context' => $memoryContext,
                'skill_context' => $skillContext,
                'messages_sent' => $messages,
                'ai_response' => $result['content'] ?? null,
                'tokens_used' => $result['tokens_used'] ?? null,
                'response_time_ms' => $result['response_time_ms'] ?? null,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

require_once __DIR__ . '/layout.php';
adminHeader('Test Bot', 'test');
?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- Left Column: Test Chat -->
    <div class="xl:col-span-2 space-y-6">
        <!-- Test Chat Section -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    Test Chat
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Simulate a conversation without sending to WhatsApp</p>
            </div>

            <!-- Chat Display Area -->
            <div id="chat-area" class="h-96 overflow-y-auto p-4 space-y-3 bg-gray-900/50">
                <!-- Welcome message -->
                <div class="flex justify-center">
                    <span class="inline-block px-3 py-1 rounded-full bg-gray-700/50 text-xs text-gray-400">
                        Send a test message to see how the bot responds
                    </span>
                </div>
            </div>

            <!-- Input Area -->
            <div class="border-t border-gray-700 p-4">
                <!-- Sender Phone -->
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-400 mb-1">Sender Phone (simulate)</label>
                    <input type="text" id="test-sender" value="628123456789" placeholder="628xxx"
                           class="w-full sm:w-64 bg-gray-700 border border-gray-600 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-500">
                </div>

                <!-- Message Input -->
                <div class="flex gap-2">
                    <textarea id="test-message" rows="2" placeholder="Type a message to test..."
                              class="flex-1 bg-gray-700 border border-gray-600 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-500 resize-none"
                              onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendTestMessage();}"></textarea>
                    <button onclick="sendTestMessage()" id="btn-send" class="self-end inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-800 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Send
                    </button>
                </div>
            </div>
        </div>

        <!-- Debug Info Section -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <button onclick="toggleDebug()" class="w-full px-5 py-4 border-b border-gray-700 flex items-center justify-between hover:bg-gray-700/30 transition-colors">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    Debug Info
                </h3>
                <svg id="debug-chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div id="debug-panel" class="hidden">
                <!-- Empty state -->
                <div id="debug-empty" class="p-8 text-center">
                    <svg class="w-10 h-10 text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    <p class="text-gray-500 text-sm">Send a test message to see debug information</p>
                </div>

                <!-- Debug content -->
                <div id="debug-content" class="hidden p-5 space-y-4">
                    <!-- Trigger Info -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Trigger Matched</h4>
                        <div id="debug-trigger" class="bg-gray-900 rounded-lg p-3 text-sm text-gray-300">-</div>
                    </div>

                    <!-- System Prompt -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">System Prompt</h4>
                        <pre id="debug-system-prompt" class="bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono whitespace-pre-wrap break-words max-h-64 overflow-y-auto">-</pre>
                    </div>

                    <!-- Memory Context -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Memory Context</h4>
                        <pre id="debug-memory" class="bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono whitespace-pre-wrap break-words max-h-48 overflow-y-auto">-</pre>
                    </div>

                    <!-- Skills Context -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Skills Context</h4>
                        <pre id="debug-skills" class="bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono whitespace-pre-wrap break-words max-h-48 overflow-y-auto">-</pre>
                    </div>

                    <!-- Full Messages Array -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Full Messages Array (sent to AI)</h4>
                        <pre id="debug-messages" class="bg-gray-900 rounded-lg p-3 text-xs text-gray-400 font-mono whitespace-pre-wrap break-words max-h-64 overflow-y-auto">-</pre>
                    </div>

                    <!-- Tokens & Response Time -->
                    <div class="flex flex-wrap gap-4">
                        <div class="bg-gray-900 rounded-lg px-4 py-3 flex items-center gap-3">
                            <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <div>
                                <p class="text-xs text-gray-500">Tokens Used</p>
                                <p id="debug-tokens" class="text-sm font-semibold text-white">-</p>
                            </div>
                        </div>
                        <div class="bg-gray-900 rounded-lg px-4 py-3 flex items-center gap-3">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <p class="text-xs text-gray-500">Response Time</p>
                                <p id="debug-response-time" class="text-sm font-semibold text-white">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Quick Tests -->
    <div class="space-y-6">
        <!-- Quick Tests -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Quick Tests
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Test individual connections</p>
            </div>

            <div class="p-5 space-y-4">
                <!-- Test AI Connection -->
                <div class="bg-gray-900 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-200">AI Provider</span>
                        </div>
                        <div id="ai-status" class="hidden">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"></span>
                        </div>
                    </div>
                    <div id="ai-result" class="hidden mb-3 text-xs text-gray-400 bg-gray-800 rounded-lg p-3 font-mono whitespace-pre-wrap break-words max-h-32 overflow-y-auto"></div>
                    <button onclick="testAI()" id="btn-test-ai" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-800 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        </svg>
                        Test AI Connection
                    </button>
                </div>

                <!-- Test WAHA Connection -->
                <div class="bg-gray-900 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-200">WAHA Server</span>
                        </div>
                        <div id="waha-status" class="hidden">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"></span>
                        </div>
                    </div>
                    <div id="waha-result" class="hidden mb-3 text-xs text-gray-400 bg-gray-800 rounded-lg p-3 font-mono whitespace-pre-wrap break-words max-h-32 overflow-y-auto"></div>
                    <button onclick="testWAHA()" id="btn-test-waha" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-800 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        </svg>
                        Test WAHA Connection
                    </button>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-700">
                <h3 class="text-base font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Tips
                </h3>
            </div>
            <div class="p-5 space-y-3 text-sm text-gray-400">
                <div class="flex items-start gap-2">
                    <span class="text-indigo-400 mt-0.5 flex-shrink-0">1.</span>
                    <p>Messages are processed through the full pipeline: triggers, personality, memory, and skills.</p>
                </div>
                <div class="flex items-start gap-2">
                    <span class="text-indigo-400 mt-0.5 flex-shrink-0">2.</span>
                    <p>Test messages are <strong class="text-gray-300">not</strong> sent to WhatsApp - they only simulate the AI response.</p>
                </div>
                <div class="flex items-start gap-2">
                    <span class="text-indigo-400 mt-0.5 flex-shrink-0">3.</span>
                    <p>Check the Debug Info panel to inspect the full prompt chain sent to the AI.</p>
                </div>
                <div class="flex items-start gap-2">
                    <span class="text-indigo-400 mt-0.5 flex-shrink-0">4.</span>
                    <p>Press <kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-xs text-gray-300">Enter</kbd> to send, <kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-xs text-gray-300">Shift+Enter</kbd> for new line.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const chatArea = document.getElementById('chat-area');
const csrf = document.getElementById('csrf_token')?.value || '';

// ─── Toggle Debug Panel ───
function toggleDebug() {
    const panel = document.getElementById('debug-panel');
    const chevron = document.getElementById('debug-chevron');
    panel.classList.toggle('hidden');
    chevron.classList.toggle('rotate-180');
}

// ─── Add Chat Bubble ───
function addBubble(text, type = 'user') {
    const wrapper = document.createElement('div');
    wrapper.className = `flex ${type === 'user' ? 'justify-end' : 'justify-start'}`;

    const bubble = document.createElement('div');
    bubble.className = type === 'user'
        ? 'max-w-[75%] bg-indigo-600 text-white rounded-2xl rounded-br-md px-4 py-2.5 text-sm whitespace-pre-wrap break-words'
        : 'max-w-[75%] bg-gray-700 text-gray-200 rounded-2xl rounded-bl-md px-4 py-2.5 text-sm whitespace-pre-wrap break-words';

    bubble.textContent = text;
    wrapper.appendChild(bubble);
    chatArea.appendChild(wrapper);
    chatArea.scrollTop = chatArea.scrollHeight;
}

function addSystemBubble(text) {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex justify-center';
    wrapper.innerHTML = `<span class="inline-block px-3 py-1 rounded-full bg-gray-700/50 text-xs text-gray-400">${escapeHtml(text)}</span>`;
    chatArea.appendChild(wrapper);
    chatArea.scrollTop = chatArea.scrollHeight;
}

function addLoadingBubble() {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex justify-start';
    wrapper.id = 'loading-bubble';
    wrapper.innerHTML = `
        <div class="max-w-[75%] bg-gray-700 text-gray-400 rounded-2xl rounded-bl-md px-4 py-3 text-sm">
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
            </div>
        </div>`;
    chatArea.appendChild(wrapper);
    chatArea.scrollTop = chatArea.scrollHeight;
}

function removeLoadingBubble() {
    document.getElementById('loading-bubble')?.remove();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ─── Send Test Message ───
async function sendTestMessage() {
    const messageEl = document.getElementById('test-message');
    const senderEl = document.getElementById('test-sender');
    const btnSend = document.getElementById('btn-send');
    const message = messageEl.value.trim();
    const sender = senderEl.value.trim() || '628xxx';

    if (!message) return;

    // Add user bubble
    addBubble(message, 'user');
    messageEl.value = '';
    messageEl.focus();

    // Show loading
    addLoadingBubble();
    btnSend.disabled = true;

    try {
        const formData = new FormData();
        formData.append('message', message);
        formData.append('sender', sender);
        formData.append('csrf_token', csrf);

        const response = await fetch('test.php?action=test_message', {
            method: 'POST',
            body: formData,
        });

        const data = await response.json();
        removeLoadingBubble();

        if (data.error) {
            addSystemBubble('Error: ' + data.error);
        } else if (data.ai_response) {
            addBubble(data.ai_response, 'bot');
            updateDebugInfo(data);
        } else {
            addSystemBubble('No response from AI');
        }
    } catch (err) {
        removeLoadingBubble();
        addSystemBubble('Request failed: ' + err.message);
    } finally {
        btnSend.disabled = false;
    }
}

// ─── Update Debug Info ───
function updateDebugInfo(data) {
    document.getElementById('debug-empty').classList.add('hidden');
    document.getElementById('debug-content').classList.remove('hidden');

    // Trigger
    const triggerEl = document.getElementById('debug-trigger');
    if (data.triggered) {
        triggerEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-green-900/50 text-green-400">Yes</span> <span class="text-gray-400 ml-1">${escapeHtml(data.trigger || 'N/A')}</span>`;
    } else {
        triggerEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-400">No trigger matched</span>`;
    }

    // System prompt
    document.getElementById('debug-system-prompt').textContent = data.system_prompt || '(empty)';

    // Memory
    document.getElementById('debug-memory').textContent = data.memory_context || '(no memories)';

    // Skills
    document.getElementById('debug-skills').textContent = data.skill_context || '(no skills context)';

    // Messages array
    document.getElementById('debug-messages').textContent = JSON.stringify(data.messages_sent, null, 2);

    // Tokens & response time
    document.getElementById('debug-tokens').textContent = data.tokens_used != null ? Number(data.tokens_used).toLocaleString() : '-';
    document.getElementById('debug-response-time').textContent = data.response_time_ms != null ? Number(data.response_time_ms).toLocaleString() + 'ms' : '-';
}

// ─── Test AI Connection ───
async function testAI() {
    const btn = document.getElementById('btn-test-ai');
    const statusEl = document.getElementById('ai-status');
    const resultEl = document.getElementById('ai-result');

    btn.disabled = true;
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Testing...`;

    try {
        const response = await fetch('test.php?action=test_ai');
        const data = await response.json();

        statusEl.classList.remove('hidden');
        resultEl.classList.remove('hidden');

        if (data.success) {
            statusEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-900/50 text-green-400"><span class="w-2 h-2 rounded-full bg-green-400"></span>Connected</span>`;
            resultEl.textContent = JSON.stringify(data, null, 2);
        } else {
            statusEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/50 text-red-400"><span class="w-2 h-2 rounded-full bg-red-400"></span>Failed</span>`;
            resultEl.textContent = data.error || JSON.stringify(data, null, 2);
        }
    } catch (err) {
        statusEl.classList.remove('hidden');
        statusEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/50 text-red-400"><span class="w-2 h-2 rounded-full bg-red-400"></span>Error</span>`;
        resultEl.classList.remove('hidden');
        resultEl.textContent = err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg> Test AI Connection`;
    }
}

// ─── Test WAHA Connection ───
async function testWAHA() {
    const btn = document.getElementById('btn-test-waha');
    const statusEl = document.getElementById('waha-status');
    const resultEl = document.getElementById('waha-result');

    btn.disabled = true;
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Testing...`;

    try {
        const response = await fetch('test.php?action=test_waha');
        const data = await response.json();

        statusEl.classList.remove('hidden');
        resultEl.classList.remove('hidden');

        if (data.success) {
            statusEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-900/50 text-green-400"><span class="w-2 h-2 rounded-full bg-green-400"></span>Connected</span>`;
            resultEl.textContent = JSON.stringify(data.data, null, 2);
        } else {
            statusEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/50 text-red-400"><span class="w-2 h-2 rounded-full bg-red-400"></span>Failed</span>`;
            resultEl.textContent = data.error || JSON.stringify(data, null, 2);
        }
    } catch (err) {
        statusEl.classList.remove('hidden');
        statusEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-900/50 text-red-400"><span class="w-2 h-2 rounded-full bg-red-400"></span>Error</span>`;
        resultEl.classList.remove('hidden');
        resultEl.textContent = err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg> Test WAHA Connection`;
    }
}
</script>

<?php adminFooter(); ?>
