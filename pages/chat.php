<?php
// pages/chat.php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';
require_once '../includes/functions.php';

// Buscar conversas
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

// Buscar usuário específico para conversa
$user_chat = null;
$mensagens = [];

if (isset($_GET['user'])) {
    $user_chat_id = filter_input(INPUT_GET, 'user', FILTER_SANITIZE_NUMBER_INT);
    
    // Buscar informações do usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_chat_id]);
    $user_chat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_chat) {
        // Buscar mensagens
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
        
        // Marcar mensagens como lidas
        $stmt = $pdo->prepare("
            UPDATE mensagens SET lida = 1 
            WHERE destinatario_id = ? AND remetente_id = ? AND lida = 0
        ");
        $stmt->execute([$_SESSION['usuario_id'], $user_chat_id]);
        
        // Enviar mensagem
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
                        <p>Nenhuma conversa ainda</p>
                        <p>Envie uma mensagem para alguém para começar uma conversa!</p>
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

<?php include '../includes/footer.php'; ?>

<script>
// Rolagem automática para a última mensagem
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});
</script>