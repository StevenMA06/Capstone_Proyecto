<?php $__env->startSection('title', 'Chatbot IA'); ?>

<?php $__env->startSection('head'); ?>
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
/* Chatbot IA Styles v1.1 */
.chatbot-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 120px);
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.chatbot-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-header h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #111827;
}

.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.chatbot-message {
    display: flex;
    gap: 12px;
    max-width: 80%;
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.chatbot-message.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.chatbot-message.assistant {
    align-self: flex-start;
}

.message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.message-avatar.user {
    background: #3b82f6;
    color: white;
}

.message-avatar.assistant {
    background: #10b981;
    color: white;
    font-size: 18px;
}

.message-avatar i {
    line-height: 1;
}

.message-content {
    background: #f3f4f6;
    padding: 12px 16px;
    border-radius: 12px;
    word-wrap: break-word;
    white-space: pre-wrap;
}

.chatbot-message.user .message-content {
    background: #3b82f6;
    color: white;
}

.message-source {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 4px;
    font-style: italic;
}

.chatbot-input-area {
    padding: 20px 24px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}

.chatbot-suggestions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.suggestion-chip {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}

.suggestion-chip:hover {
    background: #f3f4f6;
    border-color: #3b82f6;
}

.chatbot-input-form {
    display: flex;
    gap: 12px;
}

.chatbot-input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9375rem;
    resize: none;
    max-height: 120px;
    font-family: inherit;
}

.chatbot-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-send {
    padding: 12px 24px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-send:hover {
    background: #2563eb;
}

.btn-send:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.btn-new {
    padding: 8px 16px;
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-new:hover {
    background: #f3f4f6;
}

.loading-indicator {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
}

.loading-dot {
    width: 8px;
    height: 8px;
    background: #9ca3af;
    border-radius: 50%;
    animation: bounce 1.4s infinite ease-in-out;
}

.loading-dot:nth-child(1) { animation-delay: -0.32s; }
.loading-dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

.empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    text-align: center;
    color: #6b7280;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.empty-state h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.empty-state p {
    font-size: 0.9375rem;
    max-width: 500px;
}

.alert-info {
    background: #dbeafe;
    border: 1px solid #93c5fd;
    color: #1e40af;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 0.875rem;
}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('workspace-content'); ?>
<div style="padding: 24px;">
    <div class="chatbot-container">
        <!-- Header -->
        <div class="chatbot-header">
            <div>
                <h1><i class="bi bi-robot"></i> Asistente IA - Gerencia</h1>
                <p style="margin: 4px 0 0 0; font-size: 0.875rem; color: #6b7280;">
                    Consulta información operativa, inventario e indicadores
                </p>
            </div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('chatbot.query')): ?><button type="button" class="btn-new" id="btnNewConversation">
                <i class="bi bi-arrow-clockwise"></i> Nueva conversación
            </button><?php endif; ?>
        </div>

        <!-- Messages Area -->
        <div class="chatbot-messages" id="messagesArea">
           <!--  <div class="alert-info">
                ℹ️ <strong>Configuración pendiente:</strong> El chatbot está listo, pero necesitas configurar tu API Key de OpenAI en el archivo .env para comenzar a usarlo. Lee el archivo RESUMEN_IMPLEMENTACION_CHATBOT.md para más detalles.
            </div> -->
            
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-chat-dots"></i></div>
                <h2>¡Hola! Soy tu asistente de Gerencia</h2>
                <p>Puedo ayudarte a consultar información sobre clientes, contratos, pagos, equipos, materiales y más. ¡Pregúntame lo que necesites!</p>
            </div>
        </div>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('chatbot.query')): ?>
        <!-- Input Area -->
        <div class="chatbot-input-area">
            <div class="chatbot-suggestions" id="suggestionsArea">
                <button type="button" class="suggestion-chip" data-query="¿Cuántos pagos vencidos tenemos?">
                    ¿Cuántos pagos vencidos tenemos?
                </button>
                <button type="button" class="suggestion-chip" data-query="Dame información del equipo EQ-001">
                    Información del equipo EQ-001
                </button>
                <button type="button" class="suggestion-chip" data-query="¿Hay materiales con stock crítico?">
                    ¿Hay materiales con stock crítico?
                </button>
                <button type="button" class="suggestion-chip" data-query="Muéstrame los contratos activos">
                    Contratos activos
                </button>
                <button type="button" class="suggestion-chip" data-query="¿Cuántos equipos están en mantenimiento?">
                    Equipos en mantenimiento
                </button>
                <button type="button" class="suggestion-chip" data-query="Resumen general del negocio">
                    Resumen general
                </button>
            </div>

            <form class="chatbot-input-form" id="chatForm">
                <?php echo csrf_field(); ?>
                <textarea 
                    class="chatbot-input" 
                    id="messageInput" 
                    placeholder="Pregunta lo que necesites: equipos, materiales, contratos, pagos..."
                    rows="1"
                    maxlength="1000"
                    required
                ></textarea>
                <button type="submit" class="btn-send" id="btnSend">
                    Enviar
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('chatbot.query')): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messagesArea = document.getElementById('messagesArea');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const btnSend = document.getElementById('btnSend');
    const btnNewConversation = document.getElementById('btnNewConversation');
    const suggestionsArea = document.getElementById('suggestionsArea');
    
    let conversationHistory = [];
    let isFirstMessage = true;

    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });

    // Sugerencias
    document.querySelectorAll('.suggestion-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            const query = this.dataset.query;
            messageInput.value = query;
            messageInput.focus();
            chatForm.dispatchEvent(new Event('submit'));
        });
    });

    // Nueva conversación
    btnNewConversation.addEventListener('click', function() {
        if (confirm('¿Iniciar una nueva conversación? Se perderá el historial actual.')) {
            conversationHistory = [];
            messagesArea.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-chat-dots"></i></div>
                    <h2>Nueva conversación iniciada</h2>
                    <p>¿En qué puedo ayudarte?</p>
                </div>
            `;
            suggestionsArea.style.display = 'flex';
            isFirstMessage = true;
        }
    });

    // Enviar mensaje
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;

        // Ocultar sugerencias después del primer mensaje
        if (isFirstMessage) {
            suggestionsArea.style.display = 'none';
            messagesArea.innerHTML = '';
            isFirstMessage = false;
        }

        // Agregar mensaje del usuario
        addMessage('user', message);
        
        // Limpiar input y deshabilitar
        messageInput.value = '';
        messageInput.style.height = 'auto';
        btnSend.disabled = true;
        
        // Mostrar indicador de carga
        const loadingId = addLoadingIndicator();

        try {
            const response = await fetch('<?php echo e(route("chatbot.query")); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                },
                body: JSON.stringify({
                    message: message,
                    history: conversationHistory
                })
            });

            const data = await response.json();

            // Remover indicador de carga
            removeLoadingIndicator(loadingId);

            if (data.success) {
                addMessage('assistant', data.response, data.source);
                
                // Guardar en historial
                conversationHistory.push(
                    { role: 'user', content: message },
                    { role: 'assistant', content: data.response }
                );

                // Limitar historial a últimos 10 mensajes
                if (conversationHistory.length > 10) {
                    conversationHistory = conversationHistory.slice(-10);
                }
            } else {
                addMessage('assistant', data.response || 'Ocurrió un error al procesar tu consulta.', null);
            }

        } catch (error) {
            removeLoadingIndicator(loadingId);
            addMessage('assistant', 'Error de conexión. Por favor, intenta de nuevo.', null);
            console.error('Error:', error);
        } finally {
            btnSend.disabled = false;
            messageInput.focus();
        }
    });

    function addMessage(role, content, source = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message ${role}`;
        
        const avatar = role === 'user' ? '<?php echo e(substr(auth()->user()->name, 0, 1)); ?>' : '<i class="bi bi-robot"></i>';
        const avatarClass = role === 'user' ? 'user' : 'assistant';
        
        messageDiv.innerHTML = `
            <div class="message-avatar ${avatarClass}">${avatar}</div>
            <div>
                <div class="message-content">
                    ${content}
                </div>
                ${source ? `<div class="message-source">Fuente interna: ${source}</div>` : ''}
            </div>
        `;
        
        messagesArea.appendChild(messageDiv);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function addLoadingIndicator() {
        const loadingDiv = document.createElement('div');
        const loadingId = 'loading-' + Date.now();
        loadingDiv.id = loadingId;
        loadingDiv.className = 'chatbot-message assistant';
        
        loadingDiv.innerHTML = `
            <div class="message-avatar assistant"><i class="bi bi-robot"></i></div>
            <div class="loading-indicator">
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
                <div class="loading-dot"></div>
            </div>
        `;
        
        messagesArea.appendChild(loadingDiv);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        return loadingId;
    }

    function removeLoadingIndicator(loadingId) {
        const loadingElement = document.getElementById(loadingId);
        if (loadingElement) {
            loadingElement.remove();
        }
    }
});
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\Capstone\Capstone_Proyecto\resources\views/chatbot/index.blade.php ENDPATH**/ ?>