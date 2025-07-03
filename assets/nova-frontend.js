jQuery(document).ready(function($) {
    // Chat-Kontext speichern
    let chatContext = [];
    let currentMode = 'chat';
    let isProcessing = false; // Verhindert doppelte Requests
    
    // Mode Switching
    $('.mode-btn').on('click', function() {
        if (isProcessing) return; // Verhindert Switching während Request
        
        $('.mode-btn').removeClass('active');
        $(this).addClass('active');
        
        const mode = $(this).data('mode');
        currentMode = mode;
        
        // Hide all input modes
        $('.input-mode').hide();
        
        // Show selected mode
        if (mode === 'vision') {
            $('#mode-vision').show();
        } else {
            $('#mode-chat').show();
            $('#nova-prompt').attr('placeholder', 
                mode === 'chat' ? 'Schreibe eine Nachricht...' : 'Beschreibe das gewünschte Bild...'
            );
        }
        
        // Clear inputs when switching modes
        $('#nova-prompt').val('').css('height', 'auto');
        $('#nova-vision-prompt').val('');
        $('#nova-vision-file').val('');
        $('.file-label').text('📷 Bild auswählen');
    });
    
    // Auto-resize textarea
    $('#nova-prompt, #nova-vision-prompt').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Enter/Shift+Enter handling
    $('#nova-prompt').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Chat/Image Generation Handler
    $('#nova-send').on('click', sendMessage);
    
    function sendMessage() {
        if (isProcessing) return; // Verhindert doppelte Requests
        
        const prompt = $('#nova-prompt').val().trim();
        if (!prompt) {
            showAlert('Bitte gib eine Nachricht ein.', 'warning');
            return;
        }
        
        // Set processing state
        isProcessing = true;
        setUIState(false);
        
        // Nachricht zum Chat hinzufügen
        addMessage(prompt, 'user');
        
        // Reset textarea
        $('#nova-prompt').val('').css('height', 'auto');
        
        // Typing indicator
        showTyping();
        
        // Entscheide basierend auf Modus oder Inhalt
        if (currentMode === 'image' || isImageGenerationRequest(prompt)) {
            generateImage(prompt);
        } else {
            sendChatMessage(prompt);
        }
    }
    
    // Check if user wants to generate an image
    function isImageGenerationRequest(prompt) {
        const imageKeywords = [
            'erstelle ein bild', 'generiere ein bild', 'male ein bild', 'zeichne',
            'create an image', 'generate an image', 'draw', 'paint',
            'erstelle mir', 'zeige mir ein bild', 'bild von', 'bild mit'
        ];
        
        const lowerPrompt = prompt.toLowerCase();
        return imageKeywords.some(keyword => lowerPrompt.includes(keyword));
    }
    
    // Chat Message - Verbessert
    function sendChatMessage(prompt) {
        $.ajax({
            url: nova_ai.ajax_url,
            type: 'POST',
            data: {
                action: 'nova_ai_process',
                type: 'chat',
                prompt: prompt,
                context: JSON.stringify(chatContext),
                nonce: nova_ai.nonce
            },
            timeout: 180000, // 3 Minuten
            success: function(response) {
                hideTyping();
                
                if (response.success && response.data && response.data.content) {
                    const content = response.data.content;
                    addMessage(content, 'assistant');
                    
                    // Context aktualisieren
                    chatContext.push({role: 'user', content: prompt});
                    chatContext.push({role: 'assistant', content: content});
                    
                    // Limitiere Context auf letzte 20 Nachrichten (10 Paare)
                    if (chatContext.length > 20) {
                        chatContext = chatContext.slice(-20);
                    }
                    
                    // Save to localStorage for persistence
                    try {
                        localStorage.setItem('nova_ai_context', JSON.stringify(chatContext));
                    } catch(e) {
                        console.warn('Could not save context to localStorage:', e);
                    }
                } else {
                    const errorMsg = response.data || 'Unbekannter Fehler';
                    addMessage('Entschuldigung, es gab einen Fehler: ' + errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideTyping();
                let errorMessage = 'Verbindungsfehler. ';
                
                if (status === 'timeout') {
                    errorMessage += 'Die Anfrage dauerte zu lange.';
                } else if (xhr.status === 0) {
                    errorMessage += 'Keine Verbindung zum Server.';
                } else if (xhr.status >= 500) {
                    errorMessage += 'Server-Fehler.';
                } else {
                    errorMessage += 'Bitte versuche es später erneut.';
                }
                
                addMessage(errorMessage, 'error');
                console.error('Nova AI Error:', {xhr, status, error});
            },
            complete: function() {
                isProcessing = false;
                setUIState(true);
            }
        });
    }
    
    // Image Generation - Verbessert
    function generateImage(prompt) {
        // Zeige Generierungs-Status
        const statusMsg = addMessage('🎨 Generiere Bild... Dies kann 30-60 Sekunden dauern.', 'system');
        
        $.ajax({
            url: nova_ai.ajax_url,
            type: 'POST',
            data: {
                action: 'nova_ai_process',
                type: 'image_generate',
                prompt: prompt,
                nonce: nova_ai.nonce
            },
            timeout: 180000, // 3 Minuten
            success: function(response) {
                hideTyping();
                statusMsg.remove();
                
                if (response.success && response.data && response.data.image) {
                    const imageHtml = `
                        <div class="nova-generated-image">
                            <img src="${response.data.image}" alt="${escapeHtml(prompt)}" />
                            <div class="image-actions">
                                <a href="${response.data.image}" download="nova-ai-${Date.now()}.png" 
                                   class="download-btn">💾 Download</a>
                                ${response.data.seed ? `<span class="seed-info">Seed: ${response.data.seed}</span>` : ''}
                            </div>
                        </div>
                    `;
                    addMessage(imageHtml, 'assistant');
                } else {
                    const errorMsg = response.data || 'Unbekannter Fehler';
                    addMessage('Bildgenerierung fehlgeschlagen: ' + errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideTyping();
                statusMsg.remove();
                
                let errorMessage = 'Bildgenerierung fehlgeschlagen: ';
                if (status === 'timeout') {
                    errorMessage += 'Timeout - Die Generierung dauerte zu lange.';
                } else {
                    errorMessage += 'Verbindungsfehler.';
                }
                
                addMessage(errorMessage, 'error');
                console.error('Image Generation Error:', {xhr, status, error});
            },
            complete: function() {
                isProcessing = false;
                setUIState(true);
            }
        });
    }
    
    // Vision Analysis Handler - Verbessert
    $('#nova-vision-analyze').on('click', analyzeImage);
    
    function analyzeImage() {
        if (isProcessing) return;
        
        const fileInput = $('#nova-vision-file')[0];
        const prompt = $('#nova-vision-prompt').val().trim() || 'Was siehst du auf diesem Bild?';
        
        if (!fileInput.files[0]) {
            showAlert('Bitte wähle ein Bild aus.', 'warning');
            return;
        }
        
        const file = fileInput.files[0];
        
        // Validate file type
        if (!file.type.startsWith('image/')) {
            showAlert('Bitte wähle eine gültige Bilddatei aus.', 'error');
            return;
        }
        
        // Validate file size (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            showAlert('Das Bild ist zu groß. Maximal 10MB erlaubt.', 'error');
            return;
        }
        
        isProcessing = true;
        setUIState(false);
        
        // Vorschau anzeigen
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewHtml = `
                <div class="nova-vision-preview">
                    <img src="${e.target.result}" alt="Upload" />
                    <p>${escapeHtml(prompt)}</p>
                </div>
            `;
            addMessage(previewHtml, 'user');
            
            // Base64 für API
            const base64 = e.target.result.split(',')[1];
            
            showTyping();
            
            $.ajax({
                url: nova_ai.ajax_url,
                type: 'POST',
                data: {
                    action: 'nova_ai_process',
                    type: 'vision',
                    prompt: prompt,
                    image: base64,
                    nonce: nova_ai.nonce
                },
                timeout: 120000, // 2 Minuten
                success: function(response) {
                    hideTyping();
                    if (response.success && response.data && response.data.content) {
                        addMessage(response.data.content, 'assistant');
                    } else {
                        const errorMsg = response.data || 'Unbekannter Fehler';
                        addMessage('Bildanalyse fehlgeschlagen: ' + errorMsg, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    hideTyping();
                    let errorMessage = 'Fehler bei der Bildanalyse: ';
                    if (status === 'timeout') {
                        errorMessage += 'Timeout - Analyse dauerte zu lange.';
                    } else {
                        errorMessage += 'Verbindungsfehler.';
                    }
                    addMessage(errorMessage, 'error');
                    console.error('Vision Analysis Error:', {xhr, status, error});
                },
                complete: function() {
                    isProcessing = false;
                    setUIState(true);
                }
            });
        };
        
        reader.onerror = function() {
            isProcessing = false;
            setUIState(true);
            showAlert('Fehler beim Laden des Bildes.', 'error');
        };
        
        reader.readAsDataURL(file);
        
        // Reset inputs
        $('#nova-vision-file').val('');
        $('#nova-vision-prompt').val('');
        $('.file-label').text('📷 Bild auswählen');
    }
    
    // UI State Management
    function setUIState(enabled) {
        $('#nova-send, #nova-vision-analyze').prop('disabled', !enabled);
        $('.mode-btn').prop('disabled', !enabled);
        
        if (enabled) {
            $('#nova-prompt').focus();
        }
    }
    
    // Alert System
    function showAlert(message, type = 'info') {
        const alertClass = type === 'error' ? 'nova-error-message' : 
                          type === 'warning' ? 'nova-warning-message' : 'nova-system-message';
        
        const alertMsg = addMessage(message, type);
        
        // Auto-remove after 5 seconds for non-error messages
        if (type !== 'error') {
            setTimeout(() => {
                alertMsg.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    // Helper Functions
    function addMessage(content, type) {
        const timestamp = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const messageId = 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        
        let messageHtml = '';
        
        if (type === 'user') {
            messageHtml = `
                <div class="nova-message nova-user-message" id="${messageId}">
                    <div class="message-content">
                        <div class="message-text">${escapeHtml(content).replace(/\n/g, '<br>')}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                    <div class="message-avatar">👤</div>
                </div>
            `;
        } else if (type === 'assistant') {
            const processedContent = content.includes('<img') ? content : renderMarkdownLite(content);
            messageHtml = `
                <div class="nova-message nova-ai-message" id="${messageId}">
                    <div class="message-avatar">🤖</div>
                    <div class="message-content">
                        <div class="message-text">${processedContent}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
        } else if (type === 'system') {
            messageHtml = `
                <div class="nova-message nova-system-message" id="${messageId}">
                    <div class="message-content">${content}</div>
                </div>
            `;
        } else if (type === 'error') {
            messageHtml = `
                <div class="nova-message nova-error-message" id="${messageId}">
                    <div class="message-content">
                        <strong>⚠️ Fehler:</strong> ${escapeHtml(content)}
                    </div>
                </div>
            `;
        } else if (type === 'warning') {
            messageHtml = `
                <div class="nova-message nova-warning-message" id="${messageId}">
                    <div class="message-content">
                        <strong>⚠️ Warnung:</strong> ${escapeHtml(content)}
                    </div>
                </div>
            `;
        }
        
        const $message = $(messageHtml);
        $('#nova-messages').append($message);
        
        // Smooth scroll to bottom
        const messagesDiv = $('#nova-messages');
        messagesDiv.animate({ scrollTop: messagesDiv[0].scrollHeight }, 300);
        
        return $message;
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    function renderMarkdownLite(text) {
        // Escape HTML first
        let processed = escapeHtml(text);
        
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
        processed = processed.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        
        return processed;
    }
    
    function showTyping() {
        $('#nova-typing').show();
        
        // Auto-scroll to keep typing indicator visible
        const messagesDiv = $('#nova-messages');
        messagesDiv.animate({ scrollTop: messagesDiv[0].scrollHeight }, 300);
    }
    
    function hideTyping() {
        $('#nova-typing').hide();
    }
    
    // Image Preview bei Dateiauswahl
    $('#nova-vision-file').on('change', function() {
        const file = this.files[0];
        const $label = $(this).siblings('.file-label');
        
        if (file) {
            if (file.type.startsWith('image/')) {
                $label.text(file.name.length > 20 ? file.name.substring(0, 20) + '...' : file.name);
            } else {
                $label.text('📷 Bild auswählen');
                $(this).val('');
                showAlert('Bitte wähle eine gültige Bilddatei aus.', 'warning');
            }
        } else {
            $label.text('📷 Bild auswählen');
        }
    });
    
    // Context Commands
    $(document).on('keydown', function(e) {
        // Strg+K zum Löschen des Kontexts
        if (e.ctrlKey && e.key === 'k' && !isProcessing) {
            e.preventDefault();
            if (confirm('Chat-Verlauf löschen?')) {
                clearChat();
            }
        }
    });
    
    // Clear Chat Function
    function clearChat() {
        chatContext = [];
        $('#nova-messages').empty();
        
        try {
            localStorage.removeItem('nova_ai_context');
        } catch(e) {
            console.warn('Could not clear localStorage context:', e);
        }
        
        addMessage('💬 Neuer Chat gestartet', 'system');
    }
    
    // Load context from localStorage on init
    function loadSavedContext() {
        try {
            const saved = localStorage.getItem('nova_ai_context');
            if (saved) {
                const parsed = JSON.parse(saved);
                if (Array.isArray(parsed)) {
                    chatContext = parsed;
                    
                    // Display last few messages
                    const lastMessages = chatContext.slice(-6); // Last 3 exchanges
                    for (let i = 0; i < lastMessages.length; i++) {
                        const msg = lastMessages[i];
                        if (msg.role === 'user') {
                            addMessage(msg.content, 'user');
                        } else if (msg.role === 'assistant') {
                            addMessage(msg.content, 'assistant');
                        }
                    }
                    
                    if (lastMessages.length > 0) {
                        addMessage('💾 Vorheriger Chat wiederhergestellt', 'system');
                        return;
                    }
                }
            }
        } catch(e) {
            console.warn('Could not load saved context:', e);
        }
        
        // Default welcome message
        addMessage('👋 Hallo! Ich bin Nova AI. Wie kann ich dir helfen?', 'assistant');
    }
    
    // Auto-focus input on load
    setTimeout(() => {
        $('#nova-prompt').focus();
    }, 500);
    
    // Initialize
    loadSavedContext();
    
    // Heartbeat to keep session alive (every 5 minutes)
    setInterval(function() {
        if (!isProcessing) {
            $.post(nova_ai.ajax_url, {
                action: 'heartbeat',
                nonce: nova_ai.nonce
            });
        }
    }, 300000); // 5 minutes
});
