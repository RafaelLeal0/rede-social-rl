<?php
// pages/profile.php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';
require_once '../includes/functions.php'; // Adicionado esta linha

// Verificar se o parâmetro id está presente
if (!isset($_GET['id'])) {
    header('Location: feed.php');
    exit();
}

$perfil_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$usuario_perfil = getUsuarioById($pdo, $perfil_id);

if (!$usuario_perfil) {
    header('Location: feed.php');
    exit();
}

// Verificar se é o próprio perfil ou de outro usuário
$eh_proprio_perfil = ($_SESSION['usuario_id'] == $perfil_id);

// Seguir/deixar de seguir
if (isset($_GET['seguir'])) {
    if (!$eh_proprio_perfil) {
        // Verificar se já segue
        $stmt = $pdo->prepare("SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
        $stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
        
        if ($stmt->fetch()) {
            // Deixar de seguir
            $stmt = $pdo->prepare("DELETE FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
            $stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
        } else {
            // Seguir
            $stmt = $pdo->prepare("INSERT INTO seguidores (seguidor_id, seguido_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
        }
        
        header('Location: profile.php?id=' . $perfil_id);
        exit();
    }
}

// Verificar se o usuário atual segue este perfil
$stmt = $pdo->prepare("SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?");
$stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
$segue = $stmt->fetch() ? true : false;

// Buscar contagens de seguidores e seguindo
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM seguidores WHERE seguido_id = ?");
$stmt->execute([$perfil_id]);
$seguidores = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM seguidores WHERE seguidor_id = ?");
$stmt->execute([$perfil_id]);
$seguindo = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Buscar posts do usuário
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.avatar, 
           (SELECT COUNT(*) FROM curtidas WHERE post_id = p.id) as curtidas_count,
           (SELECT COUNT(*) FROM comentarios WHERE post_id = p.id) as comentarios_count,
           EXISTS(SELECT 1 FROM curtidas WHERE post_id = p.id AND usuario_id = ?) as curtiu
    FROM posts p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.usuario_id = ?
    ORDER BY p.data_postagem DESC
");
$stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="profile-header">
        <div class="profile-cover">
            <!-- Capa do perfil (pode ser implementada posteriormente) -->
        </div>
        <div class="profile-info">
            <img src="../uploads/avatars/<?php echo $usuario_perfil['avatar']; ?>" alt="Avatar" class="profile-avatar-large">
            <div class="profile-details">
                <h1><?php echo $usuario_perfil['nome']; ?></h1>
                <p>@<?php echo $usuario_perfil['username']; ?></p>
                <p class="profile-bio"><?php echo nl2br(htmlspecialchars($usuario_perfil['bio'])); ?></p>
                <div class="profile-stats">
                    <div class="stat">
                        <strong><?php echo count($posts); ?></strong>
                        <span>Posts</span>
                    </div>
                    <div class="stat">
                        <strong><?php echo $seguidores; ?></strong>
                        <span>Seguidores</span>
                    </div>
                    <div class="stat">
                        <strong><?php echo $seguindo; ?></strong>
                        <span>Seguindo</span>
                    </div>
                </div>
            </div>
            <div class="profile-actions">
                <?php if ($eh_proprio_perfil): ?>
                    <a href="edit-profile.php" class="btn btn-primary">Editar perfil</a>
                <?php else: ?>
                    <a href="profile.php?id=<?php echo $perfil_id; ?>&seguir=true" class="btn <?php echo $segue ? 'btn-outline' : 'btn-primary'; ?>">
                        <?php echo $segue ? 'Seguindo' : 'Seguir'; ?>
                    </a>
                    <a href="chat.php?user=<?php echo $perfil_id; ?>" class="btn btn-outline">Mensagem</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="profile-content">
        <h2>Posts</h2>
        <div class="posts">
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post">
                        <div class="post-header">
                            <img src="../uploads/avatars/<?php echo $post['avatar']; ?>" alt="Avatar" class="post-avatar">
                            <div>
                                <div class="post-user"><?php echo $post['username']; ?></div>
                                <div class="post-time"><?php echo time_elapsed_string($post['data_postagem']); ?></div>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['conteudo'])); ?>
                        </div>
                        <?php if ($post['imagem']): ?>
                            <img src="../uploads/posts/<?php echo $post['imagem']; ?>" alt="Post image" class="post-image">
                        <?php endif; ?>
                        <div class="post-actions">
                            <a href="feed.php?curtir=<?php echo $post['id']; ?>" class="post-action <?php echo $post['curtiu'] ? 'liked' : ''; ?>">
                                <i class="fas fa-heart"></i> <?php echo $post['curtidas_count']; ?>
                            </a>
                            <a href="post.php?id=<?php echo $post['id']; ?>" class="post-action">
                                <i class="fas fa-comment"></i> <?php echo $post['comentarios_count']; ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Nenhum post ainda.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>