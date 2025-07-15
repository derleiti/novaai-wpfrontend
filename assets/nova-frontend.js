jQuery(document).ready(function($) {

// === Session-ID generieren ===
let sessionId = '44728beb-431e-404f-a21b-97ad6cad3e53';

    // Chat-Kontext speichern
    let chatContext = [];
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
    
    // Chat Message
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
            success: function(response) {
                hideTyping();
                if (response.success) {
                    addMessage(response.data.content, 'assistant');
                    
                    // Context aktualisieren
                    chatContext.push({role: 'user', content: prompt});
                    chatContext.push({role: 'assistant', content: response.data.content});
                    
                    // Limitiere Context auf letzte 10 Nachrichten
                    if (chatContext.length > 10) {
                        chatContext = chatContext.slice(-10);
                    }
                } else {
                    addMessage('Entschuldigung, es gab einen Fehler: ' + response.data, 'error');
                }
            },
            error: function() {
                hideTyping();
                addMessage('Verbindungsfehler. Bitte versuche es später erneut.', 'error');
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
            error: function() {
                hideTyping();
                statusMsg.remove();
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
                success: function(response) {
                    hideTyping();
                    if (response.success) {
                        addMessage(response.data.content, 'assistant');
                    } else {
                        addMessage('Bildanalyse fehlgeschlagen: ' + response.data, 'error');
                    }
                },
                error: function() {
                    hideTyping();
                    addMessage('Fehler bei der Bildanalyse.', 'error');
                }
            });
        };
        reader.readAsDataURL(file);
        
        // Reset inputs
        $('#nova-vision-file').val('');
        $('#nova-vision-prompt').val('');
    }
    
    // Helper Functions
    function addMessage(content, type) {
        const timestamp = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        const messageId = 'msg-' + Date.now();
        
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
    }
    
    function hideTyping() {
        $('#nova-typing').hide();
    }
    
    // Image Preview bei Dateiauswahl
    $('#nova-vision-file').on('change', function() {
        const file = this.files[0];
        if (file && file.type.startsWith('image/')) {
            const $label = $(this).siblings('.file-label');
            $label.text(file.name);
        }
    });
    
    // Context Commands
    $(document).on('keydown', '#nova-prompt', function(e) {
        // Strg+K zum Löschen des Kontexts
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            if (confirm('Chat-Verlauf löschen?')) {
                chatContext = [];
                $('#nova-messages').empty();
                addMessage('💬 Neuer Chat gestartet', 'system');
            }
        }
    });
    
    // Initialize
    addMessage('👋 Hallo! Ich bin Nova AI. Wie kann ich dir helfen?', 'assistant');
});



    $('#vision-upload-btn').on('click', function() {
        const prompt = $('#vision-prompt').val() || " ";
        const fileInput = $('#vision-file')[0];
        if (!fileInput || !fileInput.files.length) {
            alert('Bitte ein Bild auswählen.');
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('prompt', prompt);
        formData.append('model', 'llava:latest');
        formData.append('session_id', sessionId);
        formData.append('file', file, 'image.jpg');

        $('#vision-status').text('⏳ Bild wird analysiert...');
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            enctype: 'multipart/form-data',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                if (response.success) {
                    $('#vision-response').html('<pre>' + response.data.response + '</pre>');
                } else {
                    $('#vision-response').html('<b>❌ Fehler:</b> ' + (response.data || 'Unbekannter Fehler'));
                }
            },
            error: function(xhr) {
                $('#vision-response').html('<b>❌ Fehler beim Senden:</b> ' + xhr.statusText);
            },
            complete: function() {
                $('#vision-status').text('');
            }
        });
    });
