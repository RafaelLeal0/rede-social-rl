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

<style>
.feed-container {
    display: flex;
    justify-content: space-between;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.sidebar {
    flex: 0 0 250px;
    margin-right: 20px;
}

.feed {
    flex: 1;
}

.profile-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.profile-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 4px;
    text-align: center;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-primary {
    background: #007bff;
    color: #fff;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-outline {
    background: none;
    border: 2px solid #007bff;
    color: #007bff;
}

.btn-outline:hover {
    background: #007bff;
    color: #fff;
}

.trending-topics {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.trending-topics h3 {
    margin-bottom: 10px;
}

.trending-topics ul {
    list-style: none;
    padding: 0;
}

.trending-topics li {
    margin-bottom: 8px;
}

.search-box {
    position: relative;
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    padding: 10px 40px 10px 16px;
    border: 2px solid #007bff;
    border-radius: 4px;
    font-size: 16px;
}

.search-box button {
    position: absolute;
    right: 8px;
    top: 8px;
    background: none;
    border: none;
    color: #007bff;
    font-size: 18px;
    cursor: pointer;
}

.search-box button:hover {
    color: #0056b3;
}

.suggestions {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.suggestions h3 {
    margin-bottom: 10px;
}

.suggestion {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.suggestion-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 10px;
}

.suggestion-info {
    flex: 1;
}

.suggestion-info strong {
    display: block;
    color: #333;
}

.suggestion-info span {
    color: #666;
    font-size: 0.9rem;
}

.footer-links {
    margin-top: 20px;
    text-align: center;
}

.footer-links a {
    margin: 0 10px;
    color: #007bff;
    text-decoration: none;
}

.footer-links a:hover {
    text-decoration: underline;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border-radius: 8px;
    margin-top: 20px;
}

.empty-state i {
    font-size: 3rem;
    color: #007bff;
    margin-bottom: 10px;
}

.empty-state h3 {
    margin-bottom: 10px;
}

.post {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.post-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.post-header-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.post-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 0;
}

.post-user-time {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.post-user {
    font-weight: bold;
    color: #333;
}

.post-time {
    font-size: 0.9rem;
    color: #666;
}

.post-content {
    margin: 10px 0;
}

.post-image {
    max-width: 100%;
    border-radius: 8px;
    margin: 10px 0;
}

.post-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.post-action {
    display: flex;
    align-items: center;
    color: #007bff;
    text-decoration: none;
    font-size: 0.9rem;
}

.post-action:hover {
    text-decoration: underline;
}

.post-action .count {
    margin-left: 5px;
}

.post-options-menu {
    position: relative;
    display: inline-block;
}
.post-options-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #888;
    cursor: pointer;
    padding: 0 8px;
}
.post-options-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 28px;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    min-width: 120px;
    z-index: 10;
}
.post-options-menu.open .post-options-dropdown {
    display: block;
}
.post-options-dropdown a {
    display: block;
    padding: 10px 16px;
    color: #222;
    text-decoration: none;
    font-size: 0.95rem;
    border-bottom: 1px solid #f5f5f5;
}
.post-options-dropdown a:last-child {
    border-bottom: none;
}
.post-options-dropdown a:hover {
    background: #f5f5f5;
}
.post-date {
    font-size: 0.85rem;
    color: #aaa;
    margin-top: 2px;
}
</style>
<script>
// Abrir/fechar menu de opções dos posts
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.post-options-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.post-options-menu').forEach(function(menu) {
                if (menu !== btn.parentElement) menu.classList.remove('open');
            });
            btn.parentElement.classList.toggle('open');
        });
    });
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('post-options-btn')) {
            document.querySelectorAll('.post-options-menu').forEach(function(menu) {
                menu.classList.remove('open');
            });
        }
    });
});
</script>

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
                                <div class="post-header-info">
                                    <img src="../uploads/avatars/<?php echo $post['avatar']; ?>" alt="Avatar" class="post-avatar">
                                    <div class="post-user-time">
                                        <div class="post-user"><?php echo $post['username']; ?></div>
                                        <div class="post-time"><?php echo time_elapsed_string($post['data_postagem']); ?></div>
                                        <div class="post-date">
                                            <?php echo date('d/m/Y H:i', strtotime($post['data_postagem'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($_SESSION['usuario_id'] == $post['usuario_id']): ?>
                                    <div class="post-options-menu">
                                        <button class="post-options-btn" title="Opções">&#x22EE;</button>
                                        <div class="post-options-dropdown">
                                            <a href="edit-post.php?id=<?php echo $post['id']; ?>">Editar</a>
                                            <a href="delete-post.php?id=<?php echo $post['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este post?');">Excluir</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
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