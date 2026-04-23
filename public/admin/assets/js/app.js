/**
 * BotWA "Cimol" Admin Panel - JavaScript
 */

// ============================================
// Sidebar Toggle (Mobile)
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('sidebar-open');
        overlay.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('sidebar-open');
        overlay.classList.add('hidden');
    }
}

// ============================================
// Flash Messages Auto-dismiss
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('[data-flash]');
    flashMessages.forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.5s, transform 0.5s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(function() { el.remove(); }, 500);
        }, 5000);
    });
});

// ============================================
// CSRF Token Helper
// ============================================
function getCsrfToken() {
    const el = document.getElementById('csrf_token');
    return el ? el.value : '';
}

// ============================================
// AJAX Helper
// ============================================
async function apiCall(url, method, data) {
    const options = {
        method: method || 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    if (data) {
        if (method === 'POST') {
            options.body = JSON.stringify(data);
        }
    }

    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API call failed:', error);
        return { success: false, error: error.message };
    }
}

// ============================================
// Confirm Delete
// ============================================
function confirmDelete(message, formId) {
    if (confirm(message || 'Are you sure you want to delete this?')) {
        document.getElementById(formId).submit();
    }
}

// ============================================
// Toast Notification
// ============================================
function showToast(message, type) {
    type = type || 'info';
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        info: 'bg-indigo-600',
        warning: 'bg-amber-600',
    };

    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg text-white text-sm font-medium shadow-lg ' + (colors[type] || colors.info);
    toast.style.animation = 'fadeIn 0.3s ease-out';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.style.transition = 'opacity 0.5s, transform 0.5s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(function() { toast.remove(); }, 500);
    }, 3000);
}

// ============================================
// Copy to Clipboard
// ============================================
function copyToClipboard(text, buttonEl) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            if (buttonEl) {
                const original = buttonEl.textContent;
                buttonEl.textContent = 'Copied!';
                setTimeout(function() { buttonEl.textContent = original; }, 2000);
            }
            showToast('Copied to clipboard!', 'success');
        });
    } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Copied to clipboard!', 'success');
    }
}

// ============================================
// Format JSON for display
// ============================================
function formatJson(obj) {
    try {
        return JSON.stringify(obj, null, 2);
    } catch (e) {
        return String(obj);
    }
}

// ============================================
// Debounce helper
// ============================================
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

// ============================================
// Bot Status Check
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const statusEl = document.getElementById('bot-status');
    if (!statusEl) return;

    // Check bot enabled status from the page (if available)
    // This is a simple visual indicator based on what's loaded
});

// ============================================
// Keyboard Shortcuts
// ============================================
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save forms
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const form = document.querySelector('form[data-autosave]');
        if (form) {
            form.submit();
            showToast('Saving...', 'info');
        }
    }
    
    // Escape to close sidebar on mobile
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
            toggleSidebar();
        }
    }
});
