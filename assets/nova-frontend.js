/**
 * Nova AI Frontend JavaScript
 * Version: 2.0.0
 */

class NovaAI {
    constructor(config) {
        this.config = {
            ...{
                container: '.nova-ai-chat-container',
                timeout: 120000,
                retryAttempts: 3,
                maxContext: 10,
                enableHistory: true,
                enableRetry: true,
                debugMode: false
            },
            ...config
        };
        
        this.state = {
            currentMode: 'chat',
            context: [],
            isProcessing: false,
            retryCount: 0,
            conversationId: this.generateConversationId(),
            messageCounter: 0
        };
        
        this.elements = {};
        this.messageHistory = [];
        
        this.init();
    }
    
    init() {
        if (!this.validateConfig()) {
            return;
        }
        
        this.bindElements();
        this.bindEvents();
        this.initializeChat();
        this.loadStoredHistory();
        
        if (this.config.debugMode) {
            console.log('Nova AI initialized', this.config);
        }
    }
    
    validateConfig() {
        if (!window.novaAI) {
            console.error('Nova AI: Configuration not found');
            return false;
        }
        
        this.config = { ...this.config, ...window.novaAI };
        return true;
    }
    
    bindElements() {
        const container = document.querySelector(this.config.container);
        if (!container) {
            console.error('Nova AI: Container not found');
            return;
        }
        
        this.elements = {
            container,
            messages: container.querySelector('.nova-ai-messages'),
            input: container.querySelector('#nova-prompt'),
            sendBtn: container.querySelector('#nova-send'),
            modeBtns: container.querySelectorAll('.mode-btn'),
            inputModes: container.querySelectorAll('.input-mode'),
            typingIndicator: container.querySelector('#nova-typing'),
            visionFile: container.querySelector('#nova-vision-file'),
            visionPrompt: container.querySelector('#nova-vision-prompt'),
            visionBtn: container.querySelector('#nova-vision-analyze'),
            retryBtn: container.querySelector('.retry-btn'),
            statusIndicator: container.querySelector('.status-indicator')
        };
    }
    
    bindEvents() {
        // Mode switching
        this.elements.modeBtns.forEach(btn => {
            btn.addEventListener('click', (e) => this.switchMode(e.target.dataset.mode));
        });
        
        // Send message
        this.elements.sendBtn?.addEventListener('click', () => this.sendMessage());
        
        // Vision analysis
        this.elements.visionBtn?.addEventListener('click', () => this.analyzeImage());
        
        // Input handling
        if (this.elements.input) {
            this.elements.input.addEventListener('keydown', (e) => this.handleKeyDown(e));
            this.elements.input.addEventListener('input', () => this.autoResize());
        }
        
        // File input
        this.elements.visionFile?.addEventListener('change', (e) => this.handleFileSelect(e));
        
        // Retry functionality
        this.elements.retryBtn?.addEventListener('click', () => this.retryLastMessage());
        
        // Window events
        window.addEventListener('beforeunload', () => this.saveHistory());
        window.addEventListener('online', () => this.updateConnectionStatus(true));
        window.addEventListener('offline', () => this.updateConnectionStatus(false));
    }
    
    switchMode(mode) {
        if (this.state.isProcessing) {
            this.showNotification(this.config.i18n.processing || 'Processing...', 'warning');
            return;
        }
        
        this.state.currentMode = mode;
        
        // Update UI
        this.elements.modeBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
        
        this.elements.inputModes.forEach(inputMode => {
            inputMode.style.display = inputMode.id === `mode-${mode}` ? 'flex' : 'none';
        });
        
        // Update placeholder
        if (this.elements.input) {
            const placeholders = {
                chat: this.config.i18n.placeholder || 'Schreibe eine Nachricht...',
                image: this.config.i18n.placeholderImage || 'Beschreibe das gewünschte Bild...',
                vision: this.config.i18n.placeholderVision || 'Was möchtest du über das Bild wissen?'
            };
            this.elements.input.placeholder = placeholders[mode] || placeholders.chat;
        }
        
        this.log('Mode switched to:', mode);
    }
    
    async sendMessage() {
        const prompt = this.elements.input?.value.trim();
        if (!prompt || this.state.isProcessing) {
            return;
        }
        
        this.setProcessing(true);
        
        // Add user message
        this.addMessage(prompt, 'user');
        
        // Clear input
        this.elements.input.value = '';
        this.autoResize();
        
        // Show typing indicator
        this.showTyping();
        
        try {
            let response;
            
            if (this.state.currentMode === 'image' || this.isImageGenerationRequest(prompt)) {
                response = await this.generateImage(prompt);
            } else {
                response = await this.sendChatMessage(prompt);
            }
            
            this.handleResponse(response);
            
        } catch (error) {
            this.handleError(error, 'chat');
        } finally {
            this.hideTyping();
            this.setProcessing(false);
        }
    }
    
    async sendChatMessage(prompt) {
        // Update context
        this.state.context.push({ role: 'user', content: prompt });
        
        // Limit context size
        if (this.state.context.length > this.config.maxContext * 2) {
            this.state.context = this.state.context.slice(-this.config.maxContext * 2);
        }
        
        const requestData = {
            action: 'nova_ai_process',
            type: 'chat',
            prompt: prompt,
            context: JSON.stringify(this.state.context),
            nonce: this.config.nonce,
            conversation_id: this.state.conversationId
        };
        
        return await this.makeRequest(requestData);
    }
    
    async generateImage(prompt) {
        this.addMessage(`🎨 ${this.config.i18n.generating || 'Generiere Bild...'}`, 'system');
        
        const requestData = {
            action: 'nova_ai_process',
            type: 'image',
            prompt: prompt,
            nonce: this.config.nonce
        };
        
        return await this.makeRequest(requestData);
    }
    
    async analyzeImage() {
        const fileInput = this.elements.visionFile;
        const prompt = this.elements.visionPrompt?.value.trim() || 'Was siehst du auf diesem Bild?';
        
        if (!fileInput?.files[0]) {
            this.showNotification(this.config.i18n.selectImage || 'Bitte wähle ein Bild aus.', 'error');
            return;
        }
        
        if (this.state.isProcessing) {
            return;
        }
        
        this.setProcessing(true);
        
        const file = fileInput.files[0];
        
        // Validate file
        if (!this.validateImageFile(file)) {
            this.setProcessing(false);
            return;
        }
        
        try {
            const base64 = await this.fileToBase64(file);
            
            // Show preview
            this.addImagePreview(file, prompt);
            
            this.showTyping();
            
            const requestData = {
                action: 'nova_ai_process',
                type: 'vision',
                prompt: prompt,
                image: base64,
                nonce: this.config.nonce
            };
            
            const response = await this.makeRequest(requestData);
            this.handleResponse(response);
            
            // Clear inputs
            fileInput.value = '';
            if (this.elements.visionPrompt) {
                this.elements.visionPrompt.value = '';
            }
            
        } catch (error) {
            this.handleError(error, 'vision');
        } finally {
            this.hideTyping();
            this.setProcessing(false);
        }
    }
    
    async makeRequest(data, attempt = 1) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.config.timeout);
        
        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.data || 'Unknown error');
            }
            
            this.state.retryCount = 0; // Reset retry count on success
            return result;
            
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error(this.config.i18n.timeout || 'Request timed out');
            }
            
            // Retry logic
            if (attempt < this.config.retryAttempts && this.config.enableRetry) {
                this.log(`Retrying request (attempt ${attempt + 1})`);
                await this.delay(1000 * attempt); // Progressive delay
                return this.makeRequest(data, attempt + 1);
            }
            
            throw error;
        }
    }
    
    handleResponse(response) {
        if (response.success && response.data) {
            if (response.data.content) {
                // Chat response
                this.addMessage(response.data.content, 'assistant');
                this.state.context.push({ role: 'assistant', content: response.data.content });
            } else if (response.data.image) {
                // Image response
                this.addImageMessage(response.data.image, response.data.seed);
                this.removeSystemMessages(); // Remove generation message
            }
            
            this.saveToHistory();
        } else {
            throw new Error(response.data || 'Invalid response');
        }
    }
    
    handleError(error, context = 'general') {
        this.log('Error:', error, context);
        
        const errorMessages = {
            'network': this.config.i18n.connectionError || 'Verbindungsfehler',
            'timeout': this.config.i18n.timeout || 'Zeitüberschreitung',
            'server': this.config.i18n.serverError || 'Server-Fehler',
            'default': this.config.i18n.error || 'Ein Fehler ist aufgetreten'
        };
        
        let errorType = 'default';
        let errorMessage = error.message || errorMessages.default;
        
        if (error.message.includes('timeout') || error.message.includes('timed out')) {
            errorType = 'timeout';
        } else if (error.message.includes('HTTP') || error.message.includes('fetch')) {
            errorType = 'network';
        } else if (error.message.includes('500') || error.message.includes('502') || error.message.includes('503')) {
            errorType = 'server';
        }
        
        this.addMessage(`⚠️ ${errorMessages[errorType]}: ${errorMessage}`, 'error');
        this.removeSystemMessages(); // Remove any processing messages
        
        // Show retry option for certain error types
        if (this.config.enableRetry && ['timeout', 'network', 'server'].includes(errorType)) {
            this.showRetryOption();
        }
        
        this.updateConnectionStatus(false);
    }
    
    addMessage(content, type = 'user', options = {}) {
        const messageId = `msg-${this.state.messageCounter++}-${Date.now()}`;
        const timestamp = new Date().toLocaleTimeString('de-DE', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const messageElement = this.createMessageElement(messageId, content, type, timestamp, options);
        
        this.elements.messages.appendChild(messageElement);
        this.scrollToBottom();
        
        // Store in history
        this.messageHistory.push({
            id: messageId,
            content,
            type,
            timestamp: new Date().toISOString(),
            options
        });
        
        return messageElement;
    }
    
    createMessageElement(id, content, type, timestamp, options) {
        const message = document.createElement('div');
        message.className = `nova-message nova-${type}-message`;
        message.id = id;
        
        const templates = {
            user: () => `
                <div class="message-content">
                    <div class="message-text">${this.escapeHtml(content).replace(/\n/g, '<br>')}</div>
                    <div class="message-time">${timestamp}</div>
                </div>
                <div class="message-avatar">${options.avatar || '👤'}</div>
            `,
            assistant: () => `
                <div class="message-avatar">${options.avatar || '🤖'}</div>
                <div class="message-content">
                    <div class="message-text">${content.includes('<img') ? content : this.renderMarkdown(content)}</div>
                    <div class="message-time">${timestamp}</div>
                    ${options.showActions ? this.getMessageActions() : ''}
                </div>
            `,
            system: () => `
                <div class="message-content">${content}</div>
            `,
            error: () => `
                <div class="message-content">
                    <strong>⚠️ ${this.config.i18n.error || 'Fehler'}:</strong> ${this.escapeHtml(content)}
                </div>
            `
        };
        
        message.innerHTML = templates[type] ? templates[type]() : templates.system();
        
        // Add animation
        message.style.opacity = '0';
        message.style.transform = 'translateY(20px)';
        
        requestAnimationFrame(() => {
            message.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            message.style.opacity = '1';
            message.style.transform = 'translateY(0)';
        });
        
        return message;
    }
    
    addImageMessage(imageData, seed = null) {
        const imageHtml = `
            <div class="nova-generated-image">
                <img src="${imageData}" alt="Generated Image" loading="lazy" />
                <div class="image-actions">
                    <a href="${imageData}" download="nova-ai-${Date.now()}.png" 
                       class="download-btn">💾 ${this.config.i18n.download || 'Download'}</a>
                    ${seed ? `<span class="seed-info">Seed: ${seed}</span>` : ''}
                </div>
            </div>
        `;
        
        this.addMessage(imageHtml, 'assistant', { showActions: true });
    }
    
    addImagePreview(file, prompt) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const previewHtml = `
                <div class="nova-vision-preview">
                    <img src="${e.target.result}" alt="Upload" loading="lazy" />
                    <p>${this.escapeHtml(prompt)}</p>
                </div>
            `;
            this.addMessage(previewHtml, 'user');
        };
        reader.readAsDataURL(file);
    }
    
    getMessageActions() {
        return `
            <div class="message-actions">
                <button class="action-btn copy-btn" title="Kopieren">📋</button>
                <button class="action-btn regenerate-btn" title="Neu generieren">🔄</button>
            </div>
        `;
    }
    
    removeSystemMessages() {
        const systemMessages = this.elements.messages.querySelectorAll('.nova-system-message');
        systemMessages.forEach(msg => {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 300);
        });
    }
    
    showTyping() {
        if (this.elements.typingIndicator) {
            this.elements.typingIndicator.style.display = 'flex';
            this.scrollToBottom();
        }
    }
    
    hideTyping() {
        if (this.elements.typingIndicator) {
            this.elements.typingIndicator.style.display = 'none';
        }
    }
    
    showRetryOption() {
        if (!this.elements.retryBtn) {
            const retryBtn = document.createElement('button');
            retryBtn.className = 'retry-btn';
            retryBtn.innerHTML = '🔄 Erneut versuchen';
            retryBtn.addEventListener('click', () => this.retryLastMessage());
            
            this.elements.messages.appendChild(retryBtn);
            this.elements.retryBtn = retryBtn;
        }
        
        this.elements.retryBtn.style.display = 'block';
    }
    
    hideRetryOption() {
        if (this.elements.retryBtn) {
            this.elements.retryBtn.style.display = 'none';
        }
    }
    
    retryLastMessage() {
        // Find last user message and retry
        const messages = Array.from(this.elements.messages.children);
        const lastUserMessage = messages.reverse().find(msg => 
            msg.classList.contains('nova-user-message')
        );
        
        if (lastUserMessage) {
            const content = lastUserMessage.querySelector('.message-text').textContent;
            this.elements.input.value = content;
            this.sendMessage();
        }
        
        this.hideRetryOption();
    }
    
    updateConnectionStatus(isOnline) {
        if (this.elements.statusIndicator) {
            this.elements.statusIndicator.className = `status-indicator ${isOnline ? 'online' : 'offline'}`;
            this.elements.statusIndicator.title = isOnline ? 'Online' : 'Offline';
        }
        
        this.log('Connection status:', isOnline);
    }
    
    // Utility Methods
    isImageGenerationRequest(prompt) {
        const imageKeywords = [
            'erstelle ein bild', 'generiere ein bild', 'male ein bild',
            'create an image', 'generate an image', 'draw',
            'erstelle mir', 'zeige mir ein bild', 'bild von'
        ];
        
        const lowerPrompt = prompt.toLowerCase();
        return imageKeywords.some(keyword => lowerPrompt.includes(keyword));
    }
    
    validateImageFile(file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!validTypes.includes(file.type)) {
            this.showNotification('Ungültiger Dateityp. Erlaubt: JPEG, PNG, GIF, WebP', 'error');
            return false;
        }
        
        if (file.size > maxSize) {
            this.showNotification('Datei zu groß. Maximum: 10MB', 'error');
            return false;
        }
        
        return true;
    }
    
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                const base64 = reader.result.split(',')[1];
                resolve(base64);
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }
    
    handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            this.sendMessage();
        } else if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            this.clearChat();
        }
    }
    
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) {
            const label = e.target.nextElementSibling;
            if (label) {
                label.textContent = file.name;
            }
        }
    }
    
    autoResize() {
        if (this.elements.input) {
            this.elements.input.style.height = 'auto';
            this.elements.input.style.height = this.elements.input.scrollHeight + 'px';
        }
    }
    
    scrollToBottom() {
        if (this.elements.messages) {
            this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
        }
    }
    
    setProcessing(isProcessing) {
        this.state.isProcessing = isProcessing;
        
        if (this.elements.sendBtn) {
            this.elements.sendBtn.disabled = isProcessing;
        }
        
        if (this.elements.visionBtn) {
            this.elements.visionBtn.disabled = isProcessing;
        }
        
        if (this.elements.input) {
            this.elements.input.disabled = isProcessing;
        }
    }
    
    clearChat() {
        if (confirm('Chat-Verlauf löschen?')) {
            this.elements.messages.innerHTML = '';
            this.state.context = [];
            this.messageHistory = [];
            this.state.conversationId = this.generateConversationId();
            
            this.addMessage(
                '👋 ' + (this.config.i18n.welcome || 'Hallo! Ich bin Nova AI. Wie kann ich dir helfen?'), 
                'assistant'
            );
            
            this.saveHistory();
        }
    }
    
    initializeChat() {
        this.addMessage(
            '👋 ' + (this.config.i18n.welcome || 'Hallo! Ich bin Nova AI. Wie kann ich dir helfen?'), 
            'assistant'
        );
    }
    
    saveHistory() {
        if (this.config.enableHistory && window.localStorage) {
            try {
                localStorage.setItem('nova_ai_history', JSON.stringify({
                    messages: this.messageHistory,
                    context: this.state.context,
                    conversationId: this.state.conversationId
                }));
            } catch (e) {
                this.log('Could not save history:', e);
            }
        }
    }
    
    loadStoredHistory() {
        if (this.config.enableHistory && window.localStorage) {
            try {
                const stored = localStorage.getItem('nova_ai_history');
                if (stored) {
                    const data = JSON.parse(stored);
                    this.messageHistory = data.messages || [];
                    this.state.context = data.context || [];
                    this.state.conversationId = data.conversationId || this.generateConversationId();
                    
                    // Restore messages (limit to last 20)
                    const recentMessages = this.messageHistory.slice(-20);
                    recentMessages.forEach(msg => {
                        if (msg.type !== 'system') {
                            this.createMessageElement(msg.id, msg.content, msg.type, 
                                new Date(msg.timestamp).toLocaleTimeString('de-DE', { 
                                    hour: '2-digit', 
                                    minute: '2-digit' 
                                }), 
                                msg.options || {}
                            );
                        }
                    });
                    
                    this.scrollToBottom();
                }
            } catch (e) {
                this.log('Could not load history:', e);
            }
        }
    }
    
    saveToHistory() {
        setTimeout(() => this.saveHistory(), 100);
    }
    
    showNotification(message, type = 'info') {
        // Create or update notification
        let notification = document.querySelector('.nova-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.className = 'nova-notification';
            this.elements.container.appendChild(notification);
        }
        
        notification.className = `nova-notification ${type}`;
        notification.textContent = message;
        notification.style.display = 'block';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    }
    
    // Helper methods
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    renderMarkdown(text) {
        let processed = this.escapeHtml(text);
        
        // Code blocks
        processed = processed.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        
        // Inline code
        processed = processed.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Bold
        processed = processed.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic
        processed = processed.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Line breaks
        processed = processed.replace(/\n/g, '<br>');
        
        // Links
        processed = processed.replace(/\[([^\]]+)\]\(([^)]+)\)/g, 
            '<a href="$2" target="_blank" rel="noopener">$1</a>');
        
        return processed;
    }
    
    generateConversationId() {
        return 'conv_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    log(...args) {
        if (this.config.debugMode) {
            console.log('[Nova AI]', ...args);
        }
    }
    
    // Public API
    getState() {
        return { ...this.state };
    }
    
    getHistory() {
        return [...this.messageHistory];
    }
    
    setMode(mode) {
        this.switchMode(mode);
    }
    
    addCustomMessage(content, type = 'system') {
        return this.addMessage(content, type);
    }
    
    destroy() {
        // Cleanup
        this.saveHistory();
        
        // Remove event listeners
        window.removeEventListener('beforeunload', this.saveHistory);
        window.removeEventListener('online', this.updateConnectionStatus);
        window.removeEventListener('offline', this.updateConnectionStatus);
        
        // Clear intervals/timeouts if any
        this.log('Nova AI destroyed');
    }
}

// jQuery wrapper for backward compatibility
jQuery(document).ready(function($) {
    // Initialize Nova AI for each chat container
    $('.nova-ai-chat-container').each(function() {
        const container = this;
        const config = {
            container: container,
            ...window.novaAI // Global config
        };
        
        // Store instance on the container
        container.novaAI = new NovaAI(config);
    });
    
    // Global keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Escape to close any modals/notifications
        if (e.key === 'Escape') {
            $('.nova-notification').hide();
        }
    });
    
    // Expose to global scope
    window.NovaAI = NovaAI;
});
