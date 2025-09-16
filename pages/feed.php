<?php
// pages/feed.php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';
require_once '../includes/functions.php';

// Postar nova mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conteudo'])) {
    $conteudo = filter_input(INPUT_POST, 'conteudo', FILTER_SANITIZE_STRING);
    $usuario_id = $_SESSION['usuario_id'];
    
    // Processar upload de imagem
    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        // Criar diretório se não existir
        if (!file_exists('../uploads/posts')) {
            mkdir('../uploads/posts', 0777, true);
        }
        
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $nome_imagem = uniqid() . '.' . $extensao;
        $destino = '../uploads/posts/' . $nome_imagem;
        
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
            $imagem = $nome_imagem;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO posts (usuario_id, conteudo, imagem) VALUES (?, ?, ?)");
    $stmt->execute([$usuario_id, $conteudo, $imagem]);
    
    header('Location: feed.php');
    exit();
}

// Curtir/descurtir post
if (isset($_GET['curtir'])) {
    $post_id = filter_input(INPUT_GET, 'curtir', FILTER_SANITIZE_NUMBER_INT);
    
    // Verificar se já curtiu
    $stmt = $pdo->prepare("SELECT id FROM curtidas WHERE usuario_id = ? AND post_id = ?");
    $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    
    if ($stmt->fetch()) {
        // Descurtir
        $stmt = $pdo->prepare("DELETE FROM curtidas WHERE usuario_id = ? AND post_id = ?");
        $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    } else {
        // Curtir
        $stmt = $pdo->prepare("INSERT INTO curtidas (usuario_id, post_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    }
    
    header('Location: feed.php');
    exit();
}

// Buscar posts
$posts = getPosts($pdo, $_SESSION['usuario_id']);

// Buscar sugestões de usuários para seguir
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM usuarios u 
    WHERE u.id != ? 
    AND u.id NOT IN (
        SELECT seguido_id FROM seguidores WHERE seguidor_id = ?
    )
    ORDER BY RAND() 
    LIMIT 5
");
$stmt->execute([$_SESSION['usuario_id'], $_SESSION['usuario_id']]);
$sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuário atual
$usuario = getUsuarioById($pdo, $_SESSION['usuario_id']);
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="feed-container">
        <!-- Sidebar esquerda -->
        <div class="sidebar">
            <div class="profile-card">
                <img src="../uploads/avatars/<?php echo $usuario['avatar']; ?>" alt="Avatar" class="profile-avatar">
                <h3><?php echo $usuario['nome']; ?></h3>
                <p>@<?php echo $usuario['username']; ?></p>
                <p class="profile-bio"><?php echo $usuario['bio']; ?></p>
                <a href="profile.php?id=<?php echo $_SESSION['usuario_id']; ?>" class="btn btn-outline">Ver perfil</a>
            </div>
            
            <div class="trending-topics">
                <h3>Assuntos do momento</h3>
                <ul>
                    <li><a href="search.php?q=tecnologia">#Tecnologia</a></li>
                    <li><a href="search.php?q=programação">#Programação</a></li>
                    <li><a href="search.php?q=php">#PHP</a></li>
                    <li><a href="search.php?q=desenvolvimento">#Desenvolvimento</a></li>
                    <li><a href="search.php?q=redesocial">#RedeSocial</a></li>
                </ul>
            </div>
        </div>

        <!-- Feed principal -->
        <div class="feed">
            <div class="create-post">
                <form method="POST" enctype="multipart/form-data">
                    <div class="post-author">
                        <img src="../uploads/avatars/<?php echo $usuario['avatar']; ?>" alt="Avatar" class="post-avatar">
                        <textarea name="conteudo" placeholder="O que está acontecendo?" required></textarea>
                    </div>
                    <div class="post-options">
                        <label for="imagem" class="btn btn-outline">
                            <i class="fas fa-image"></i> Foto
                        </label>
                        <input type="file" id="imagem" name="imagem" accept="image/*" style="display: none;">
                        <button type="submit" class="btn btn-primary">Publicar</button>
                    </div>
                </form>
            </div>

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
                                    <i class="fas fa-heart"></i> <span class="count"><?php echo $post['curtidas_count']; ?></span>
                                </a>
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="post-action">
                                    <i class="fas fa-comment"></i> <span class="count"><?php echo $post['comentarios_count']; ?></span>
                                </a>
                                <a href="#" class="post-action">
                                    <i class="fas fa-share"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-feather-alt"></i>
                        <h3>Nada para ver aqui... ainda</h3>
                        <p>Siga alguns usuários para ver posts no seu feed!</p>
                        <a href="search.php" class="btn btn-primary">Encontrar pessoas</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="sidebar">
            <div class="search-box">
                <form action="search.php" method="GET">
                    <input type="text" name="q" placeholder="Buscar no RL">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            
            <div class="suggestions">
                <h3>Quem seguir</h3>
                <?php foreach ($sugestoes as $sugestao): ?>
                    <div class="suggestion">
                        <img src="../uploads/avatars/<?php echo $sugestao['avatar']; ?>" alt="Avatar" class="suggestion-avatar">
                        <div class="suggestion-info">
                            <strong><?php echo $sugestao['nome']; ?></strong>
                            <span>@<?php echo $sugestao['username']; ?></span>
                        </div>
                        <a href="profile.php?id=<?php echo $sugestao['id']; ?>" class="btn btn-outline">Ver</a>
                    </div>
                <?php endforeach; ?>
                <?php if (count($sugestoes) === 0): ?>
                    <p class="no-suggestions">Não há sugestões no momento.</p>
                <?php endif; ?>
            </div>
            
            <div class="footer-links">
                <a href="#">Termos de Serviço</a>
                <a href="#">Política de Privacidade</a>
                <a href="#">Contato</a>
                <p>© 2023 RL</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>