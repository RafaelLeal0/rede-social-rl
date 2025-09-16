<?php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';
require_once '../includes/functions.php';

// Sua consulta SQL aqui (mantida igual)
$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.username, u.avatar, 
           (SELECT mensagem FROM mensagens 
            WHERE (remetente_id = ? AND destinatario_id = u.id) 
               OR (remetente_id = u.id AND destinatario_id = ?) 
            ORDER BY data_envio DESC LIMIT 1) as ultima_mensagem,
           (SELECT data_envio FROM mensagens 
            WHERE (remetente_id = ? AND destinatario_id = u.id) 
               OR (remetente_id = u.id AND destinatario_id = ?) 
            ORDER BY data_envio DESC LIMIT 1) as ultima_mensagem_data,
           (SELECT COUNT(*) FROM mensagens 
            WHERE destinatario_id = ? AND remetente_id = u.id AND lida = 0) as mensagens_nao_lidas
    FROM usuarios u
    WHERE u.id IN (
        SELECT remetente_id FROM mensagens WHERE destinatario_id = ?
        UNION
        SELECT destinatario_id FROM mensagens WHERE remetente_id = ?
    )
    ORDER BY ultima_mensagem_data DESC
");
$stmt->execute([
    $_SESSION['usuario_id'], $_SESSION['usuario_id'],
    $_SESSION['usuario_id'], $_SESSION['usuario_id'],
    $_SESSION['usuario_id'], $_SESSION['usuario_id'], $_SESSION['usuario_id']
]);
$conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_chat = null;
$mensagens = [];

if (isset($_GET['user'])) {
    $user_chat_id = filter_input(INPUT_GET, 'user', FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_chat_id]);
    $user_chat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_chat) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.avatar 
            FROM mensagens m
            JOIN usuarios u ON m.remetente_id = u.id
            WHERE (remetente_id = ? AND destinatario_id = ?) 
               OR (remetente_id = ? AND destinatario_id = ?)
            ORDER BY data_envio ASC
        ");
        $stmt->execute([
            $_SESSION['usuario_id'], $user_chat_id,
            $user_chat_id, $_SESSION['usuario_id']
        ]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            UPDATE mensagens SET lida = 1 
            WHERE destinatario_id = ? AND remetente_id = ? AND lida = 0
        ");
        $stmt->execute([$_SESSION['usuario_id'], $user_chat_id]);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
            $mensagem = filter_input(INPUT_POST, 'mensagem', FILTER_SANITIZE_STRING);
            
            $stmt = $pdo->prepare("
                INSERT INTO mensagens (remetente_id, destinatario_id, mensagem) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['usuario_id'], $user_chat_id, $mensagem]);
            
            header('Location: chat.php?user=' . $user_chat_id);
            exit();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="chat-container">
        <!-- Lista de conversas -->
        <div class="conversations-list">
            <h2>Conversas</h2>
            <div class="conversations">
                <?php if (count($conversas) > 0): ?>
                    <?php foreach ($conversas as $conversa): ?>
                        <a href="chat.php?user=<?php echo $conversa['id']; ?>" class="conversation <?php echo ($user_chat && $user_chat['id'] == $conversa['id']) ? 'active' : ''; ?>">
                            <img src="../uploads/avatars/<?php echo $conversa['avatar']; ?>" alt="Avatar" class="conversation-avatar">
                            <div class="conversation-info">
                                <div class="conversation-name"><?php echo $conversa['nome']; ?></div>
                                <div class="conversation-last-message">
                                    <?php 
                                    if ($conversa['ultima_mensagem']) {
                                        echo strlen($conversa['ultima_mensagem']) > 30 
                                            ? substr($conversa['ultima_mensagem'], 0, 30) . '...' 
                                            : $conversa['ultima_mensagem'];
                                    } else {
                                        echo 'Nenhuma mensagem';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php if ($conversa['mensagens_nao_lidas'] > 0): ?>
                                <span class="unread-count"><?php echo $conversa['mensagens_nao_lidas']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-conversations">
                        <i class="fas fa-comments"></i>
                        <h3>Nenhuma conversa</h3>
                        <p>Envie uma mensagem para alguém para começar uma conversa!</p>
                        <a href="search.php" class="btn btn-primary">Encontrar pessoas</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Área de chat -->
        <div class="chat-area">
            <?php if ($user_chat): ?>
                <div class="chat-header">
                    <img src="../uploads/avatars/<?php echo $user_chat['avatar']; ?>" alt="Avatar" class="chat-user-avatar">
                    <div class="chat-user-info">
                        <h3><?php echo $user_chat['nome']; ?></h3>
                        <p>@<?php echo $user_chat['username']; ?></p>
                    </div>
                </div>

                <div class="messages-container" id="messages-container">
                    <?php foreach ($mensagens as $mensagem): ?>
                        <div class="message <?php echo $mensagem['remetente_id'] == $_SESSION['usuario_id'] ? 'sent' : 'received'; ?>">
                            <img src="../uploads/avatars/<?php echo $mensagem['avatar']; ?>" alt="Avatar" class="message-avatar">
                            <div class="message-content">
                                <p><?php echo nl2br(htmlspecialchars($mensagem['mensagem'])); ?></p>
                                <span class="message-time"><?php echo time_elapsed_string($mensagem['data_envio']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="message-form">
                    <div class="message-input">
                        <input type="text" name="mensagem" placeholder="Digite uma mensagem..." required>
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="no-chat-selected">
                    <i class="fas fa-comments"></i>
                    <h3>Selecione uma conversa</h3>
                    <p>Escolha uma conversa da lista ou encontre alguém para conversar</p>
                    <a href="search.php" class="btn btn-primary">Encontrar pessoas</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CSS DIRETO NA PÁGINA PARA GARANTIR QUE FUNCIONE -->
<style>
/* RESET DE ESTILOS PARA O CHAT */
.chat-container * {
    box-sizing: border-box;
}

/* CONTAINER PRINCIPAL */
.chat-container {
    display: flex;
    height: 70vh;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

/* LISTA DE CONVERSAS (LADO ESQUERDO) */
.conversations-list {
    width: 300px;
    border-right: 1px solid #eee;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.conversations-list h2 {
    padding: 20px;
    margin: 0;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    flex-shrink: 0;
}

.conversations {
    flex: 1;
    overflow-y: auto;
    padding: 0;
    height: 100%;
}

.conversation {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
    text-decoration: none;
    color: #333;
    transition: background-color 0.2s;
    min-height: 60px;
}

.conversation:hover {
    background-color: #f8f9fa;
}

.conversation.active {
    background-color: #e3f2fd;
}

.conversation-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
    flex-shrink: 0;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-weight: 600;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-last-message {
    font-size: 14px;
    color: #777;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-count {
    background: #e0245e;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    margin-left: 10px;
}

.empty-conversations {
    padding: 30px 20px;
    text-align: center;
    color: #777;
}

.empty-conversations i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #ccc;
}

.empty-conversations h3 {
    margin-bottom: 10px;
    color: #333;
}

.empty-conversations p {
    margin-bottom: 15px;
}

/* ÁREA DE CHAT (LADO DIREITO) */
.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.chat-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    background: #f8f9fa;
    flex-shrink: 0;
}

.chat-user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
    flex-shrink: 0;
}

.chat-user-info {
    flex: 1;
    min-width: 0;
}

.chat-user-info h3 {
    margin: 0 0 5px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-user-info p {
    margin: 0;
    color: #777;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* CONTÊINER DE MENSAGENS - ESSENCIAL PARA O SCROLL */
.messages-container {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 15px;
    height: 100%;
}

.message {
    display: flex;
    max-width: 70%;
}

.message.sent {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.message.received {
    align-self: flex-start;
}

.message-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 10px;
    flex-shrink: 0;
}

.message-content {
    background: #f0f2f5;
    padding: 12px 15px;
    border-radius: 18px;
    position: relative;
    max-width: 100%;
    word-wrap: break-word;
}

.message.sent .message-content {
    background: #4a90e2;
    color: white;
}

.message-content p {
    margin: 0 0 5px 0;
    line-height: 1.4;
}

.message-time {
    font-size: 12px;
    opacity: 0.7;
}

.message-form {
    padding: 20px;
    border-top: 1px solid #eee;
    flex-shrink: 0;
}

.message-input {
    display: flex;
    gap: 10px;
}

.message-input input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 20px;
    font-size: 14px;
}

.message-input button {
    background: #4a90e2;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.message-input button:hover {
    background: #3a80d2;
}

.no-chat-selected {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    color: #777;
    padding: 20px;
}

.no-chat-selected i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #ccc;
}

.no-chat-selected h3 {
    margin-bottom: 10px;
    color: #333;
}

.no-chat-selected p {
    margin-bottom: 20px;
}

/* BARRAS DE SCROLL PERSONALIZADAS */
.conversations::-webkit-scrollbar {
    width: 8px;
}

.conversations::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.conversations::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.conversations::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.messages-container::-webkit-scrollbar {
    width: 8px;
}

.messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.messages-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.messages-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* RESPONSIVIDADE */
@media (max-width: 768px) {
    .chat-container {
        flex-direction: column;
        height: 80vh;
    }
    
    .conversations-list {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #eee;
        max-height: 40%;
    }
    
    .message {
        max-width: 85%;
    }
}

@media (max-width: 480px) {
    .chat-container {
        height: calc(100vh - 140px);
        border-radius: 0;
    }
    
    .conversation-avatar {
        width: 40px;
        height: 40px;
        margin-right: 10px;
    }
    
    .message-avatar {
        width: 30px;
        height: 30px;
    }
    
    .message-content {
        padding: 10px 12px;
    }
    
    .conversations-list h2,
    .chat-header {
        padding: 15px;
    }
}
</style>

<script>
// Script para garantir que o scroll funcione
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Forçar redimensionamento para garantir que o scroll funcione
    setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
    }, 100);
});

// Função auxiliar para debug - verificar se os elementos têm altura
function debugHeights() {
    console.log('Altura do container de conversas:', document.querySelector('.conversations').scrollHeight);
    console.log('Altura do container de mensagens:', document.getElementById('messages-container')?.scrollHeight);
}
</script>

<?php include '../includes/footer.php'; ?>