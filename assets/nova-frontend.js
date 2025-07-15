jQuery(document).ready(function($) {
    // === Session Management mit 1-Stunden-Timeout ===
    function getOrCreateSessionId() {
        const sessionData = localStorage.getItem('nova_ai_session_data');
        const now = Date.now();
        const oneHour = 60 * 60 * 1000; // 1 Stunde in Millisekunden
        
        if (sessionData) {
            const data = JSON.parse(sessionData);
            // Prüfe ob Session noch gültig ist (unter 1 Stunde alt)
            if (data.timestamp && (now - data.timestamp) < oneHour) {
                return data.sessionId;
            }
        }
        
        // Neue Session erstellen
        const newSessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
        
        // Session mit Timestamp speichern
        localStorage.setItem('nova_ai_session_data', JSON.stringify({
            sessionId: newSessionId,
            timestamp: now
        }));
        
        // Info-Nachricht bei Session-Wechsel
        if (sessionData) {
            addMessage('🔄 Neue Session gestartet (vorherige Session abgelaufen)', 'system');
        }
        
        return newSessionId;
    }
    
    // Session-ID abrufen/erstellen
    let sessionId = getOrCreateSessionId();
    
    // Session-Timer - prüft alle 5 Minuten ob Session noch gültig ist
    setInterval(function() {
        const oldSessionId = sessionId;
        sessionId = getOrCreateSessionId();
        
        if (oldSessionId !== sessionId) {
            addMessage('⏰ Session abgelaufen - neue Session wurde erstellt', 'system');
        }
    }, 5 * 60 * 1000); // Alle 5 Minuten prüfen

    let currentMode = 'chat';
    
    // Mode Switching
    $('.mode-btn').on('click', function() {
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
    });
    
    // Auto-resize textarea
    $('#nova-prompt').on('input', function() {
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
        const prompt = $('#nova-prompt').val().trim();
        if (!prompt) return;
        
        // Session-Gültigkeit prüfen
        sessionId = getOrCreateSessionId();
        
        // Nachricht zum Chat hinzufügen
        addMessage(prompt, 'user');
        
        // Reset textarea
        $('#nova-prompt').val('').css('height', 'auto').focus();
        
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
            'erstelle ein bild', 'generiere ein bild', 'male ein bild',
            'create an image', 'generate an image', 'draw',
            'erstelle mir', 'zeige mir ein bild', 'bild von'
        ];
        
        const lowerPrompt = prompt.toLowerCase();
        return imageKeywords.some(keyword => lowerPrompt.includes(keyword));
    }
    
    // Chat Message - KEINE CONTEXT ÜBERTRAGUNG MEHR!
    function sendChatMessage(prompt) {
        // Session-Gültigkeit prüfen
        sessionId = getOrCreateSessionId();
        
        $.ajax({
            url: nova_ai.ajax_url,
            type: 'POST',
            data: {
                action: 'nova_ai_process',
                type: 'chat',
                prompt: prompt,
                session_id: sessionId,  // Nur prompt und session_id senden!
                nonce: nova_ai.nonce
            },
            success: function(response) {
                hideTyping();
                if (response.success) {
                    addMessage(response.data.content, 'assistant');
                    
                    // Context wird vom Backend verwaltet, nicht mehr hier
                    console.log('Chat Response erhalten:', response.data.content);
                } else {
                    addMessage('Entschuldigung, es gab einen Fehler: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideTyping();
                console.error('Chat Error:', status, error);
                console.error('Response:', xhr.responseText);
                addMessage('Verbindungsfehler: ' + error, 'error');
            }
        });
    }
    
    // Image Generation
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
            timeout: 120000, // 2 Minuten Timeout
            success: function(response) {
                hideTyping();
                statusMsg.remove();
                
                if (response.success) {
                    const imageHtml = `
                        <div class="nova-generated-image">
                            <img src="${response.data.image}" alt="${prompt}" />
                            <div class="image-actions">
                                <a href="${response.data.image}" download="nova-ai-${Date.now()}.png" 
                                   class="download-btn">💾 Download</a>
                                ${response.data.seed ? `<span class="seed-info">Seed: ${response.data.seed}</span>` : ''}
                            </div>
                        </div>
                    `;
                    addMessage(imageHtml, 'assistant');
                } else {
                    addMessage('Bildgenerierung fehlgeschlagen: ' + response.data, 'error');
                }
            },
            error: function(xhr) {
                hideTyping();
                statusMsg.remove();
                console.error('Image Error:', xhr);
                addMessage('Timeout - Die Bildgenerierung dauert zu lange.', 'error');
            }
        });
    }
    
    // Vision Analysis Handler
    $('#nova-vision-analyze').on('click', analyzeImage);
    
    function analyzeImage() {
        const fileInput = $('#nova-vision-file')[0];
        const prompt = $('#nova-vision-prompt').val().trim() || 'Was siehst du auf diesem Bild?';
        
        if (!fileInput.files[0]) {
            alert('Bitte wähle ein Bild aus.');
            return;
        }
        
        const file = fileInput.files[0];
        
        // Vorschau anzeigen
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewHtml = `
                <div class="nova-vision-preview">
                    <img src="${e.target.result}" alt="Upload" />
                    <p>${prompt}</p>
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
                    type: 'vision_upload',
                    prompt: prompt,
                    image: base64,
                    nonce: nova_ai.nonce
                },
                timeout: 60000, // 1 Minute Timeout
                success: function(response) {
                    hideTyping();
                    if (response.success) {
                        addMessage(response.data.content, 'assistant');
                    } else {
                        addMessage('Bildanalyse fehlgeschlagen: ' + response.data, 'error');
                    }
                },
                error: function(xhr) {
                    hideTyping();
                    console.error('Vision Error:', xhr);
                    addMessage('Fehler bei der Bildanalyse.', 'error');
                }
            });
        };
        reader.readAsDataURL(file);
        
        // Reset inputs
        $('#nova-vision-file').val('');
        $('#nova-vision-prompt').val('');
        $('.file-label').text('📷 Bild auswählen');
    }
    
    // Helper Functions
    function addMessage(content, type) {
        const timestamp = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const messageId = 'msg-' + Date.now();
        
        let messageHtml = '';
        
        if (type === 'user') {
            // Prüfe ob content HTML enthält (für Vision Preview)
            const isHtml = /<[a-z][\s\S]*>/i.test(content);
            const displayContent = isHtml ? content : escapeHtml(content).replace(/\n/g, '<br>');
            
            messageHtml = `
                <div class="nova-message nova-user-message" id="${messageId}">
                    <div class="message-content">
                        <div class="message-text">${displayContent}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                    <div class="message-avatar">👤</div>
                </div>
            `;
        } else if (type === 'assistant') {
            const processedContent = content.includes('<img') || content.includes('<div') ? content : renderMarkdownLite(content);
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
        return String(text).replace(/[&<>"']/g, m => map[m]);
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
    }
    
    function hideTyping() {
        $('#nova-typing').hide();
    }
    
    // Image Preview bei Dateiauswahl
    $('#nova-vision-file').on('change', function() {
        const file = this.files[0];
        if (file && file.type.startsWith('image/')) {
            const $label = $(this).siblings('.file-label');
            $label.text('📷 ' + file.name);
        }
    });
    
    // Context Commands
    $(document).on('keydown', '#nova-prompt', function(e) {
        // Strg+K zum Löschen des Kontexts (löscht nur Frontend-Anzeige)
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            if (confirm('Chat-Verlauf löschen?')) {
                $('#nova-messages').empty();
                addMessage('💬 Neuer Chat gestartet', 'system');
            }
        }
    });
    
    // Session Info anzeigen (optional)
    function showSessionInfo() {
        const sessionData = JSON.parse(localStorage.getItem('nova_ai_session_data'));
        if (sessionData && sessionData.timestamp) {
            const elapsed = Date.now() - sessionData.timestamp;
            const remaining = Math.max(0, 60 - Math.floor(elapsed / 60000));
            return `Session läuft noch ${remaining} Minuten`;
        }
        return 'Neue Session';
    }
    
    // Initialize
    addMessage('👋 Hallo! Ich bin Nova AI. Wie kann ich dir helfen?', 'assistant');
    
    // Optional: Session-Status in der Konsole
    console.log('Nova AI Session:', sessionId);
    console.log('Session Status:', showSessionInfo());
});
