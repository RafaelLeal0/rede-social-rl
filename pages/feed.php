<?php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conteudo'])) {
    $conteudo = filter_input(INPUT_POST, 'conteudo', FILTER_SANITIZE_STRING);
    $usuario_id = $_SESSION['usuario_id'];

    $imagem = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
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
if (isset($_GET['curtir'])) {
    $post_id = filter_input(INPUT_GET, 'curtir', FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT id FROM curtidas WHERE usuario_id = ? AND post_id = ?");
    $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM curtidas WHERE usuario_id = ? AND post_id = ?");
        $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO curtidas (usuario_id, post_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    }
    
    header('Location: feed.php');
    exit();
}
$posts = getPosts($pdo, $_SESSION['usuario_id']);
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
$usuario = getUsuarioById($pdo, $_SESSION['usuario_id']);
?>

<?php include '../includes/header.php'; ?>

<style>
:root {
  --primary-color: #4a90e2;
  --primary-hover: #3a80d2;
  --primary-active: #2a70c2;
  --danger-color: #e0245e;
  --danger-hover: #d0144e;
  --danger-active: #c0043e;
  --success-color: #28a745;
  --success-hover: #218838;
  --success-active: #1e7e34;
  --border-radius: 8px;
  --transition-speed: 0.2s;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  border: none;
  border-radius: var(--border-radius);
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--transition-speed) ease;
  position: relative;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.3);
}

.btn:active {
  transform: translateY(1px);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background-color: var(--primary-hover);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-primary:active {
  background-color: var(--primary-active);
}

.btn-outline {
  background: transparent;
  border: 2px solid var(--primary-color);
  color: var(--primary-color);
  padding: 10px 18px;
}

.btn-outline:hover {
  background: var(--primary-color);
  color: white;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-danger {
  background-color: var(--danger-color);
  color: white;
}

.btn-danger:hover {
  background-color: var(--danger-hover);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-danger:active {
  background-color: var(--danger-active);
}

.btn-success {
  background-color: var(--success-color);
  color: white;
}

.btn-success:hover {
  background-color: var(--success-hover);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-success:active {
  background-color: var(--success-active);
}

.btn-text {
  background: transparent;
  color: var(--primary-color);
  box-shadow: none;
  padding: 8px 12px;
}

.btn-text:hover {
  background-color: rgba(74, 144, 226, 0.1);
  box-shadow: none;
}

.btn-text:active {
  background-color: rgba(74, 144, 226, 0.2);
  transform: none;
}

.btn-icon {
  padding: 10px;
  border-radius: 50%;
  width: 40px;
  height: 40px;
}

.btn-sm {
  padding: 8px 16px;
  font-size: 14px;
}

.btn-sm.btn-outline {
  padding: 6px 14px;
}

.btn-lg {
  padding: 16px 28px;
  font-size: 18px;
}

.btn-lg.btn-outline {
  padding: 14px 26px;
}

.btn-block {
  display: flex;
  width: 100%;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn:disabled:hover {
  transform: none;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-ripple {
  position: relative;
  overflow: hidden;
}

.btn-ripple:after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 5px;
  height: 5px;
  background: rgba(255, 255, 255, 0.5);
  opacity: 0;
  border-radius: 100%;
  transform: scale(1, 1) translate(-50%);
  transform-origin: 50% 50%;
}

.btn-ripple:focus:not(:active)::after {
  animation: ripple 1s ease-out;
}

@keyframes ripple {
  0% {
    transform: scale(0, 0);
    opacity: 0.5;
  }
  100% {
    transform: scale(30, 30);
    opacity: 0;
  }
}

.post-actions .post-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 20px;
  transition: all var(--transition-speed) ease;
}

.post-actions .post-action:hover {
  background-color: rgba(74, 144, 226, 0.1);
  transform: translateY(-2px);
}

.message-input button {
  transition: all var(--transition-speed) ease;
}

.message-input button:hover {
  transform: scale(1.05);
}

.comment-input button {
  transition: all var(--transition-speed) ease;
}

.comment-input button:hover {
  transform: translateY(-1px);
}

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
    text-decoration: none;
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

/* ESTILOS ESPECÍFICOS PARA OS BOTÕES "FOTO" E "PUBLICAR" */
.create-post {
    background: #fff;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: 20px;
    border: 1px solid #e0e0e0;
}

.post-author {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
}

.post-author .post-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.post-author textarea {
    flex: 1;
    border: none;
    resize: none;
    min-height: 60px;
    font-size: 18px;
    padding: 12px;
    border-bottom: 2px solid #f0f2f5;
    font-family: inherit;
    background: #fafafa;
    border-radius: 8px;
}

.post-author textarea:focus {
    outline: none;
    border-color: #4a90e2;
    background: #fff;
}

.post-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
    gap: 15px; /* ESPAÇAMENTO ADICIONADO ENTRE OS BOTÕES */
}

/* BOTÃO FOTO MELHORADO */
.btn-file {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: transparent;
    border: 2px solid #4a90e2;
    border-radius: 8px;
    color: #4a90e2;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-file:hover {
    background: #4a90e2;
    color: white;
    box-shadow: 0 4px 12px rgba(74, 144, 226, 0.2);
    transform: translateY(-2px);
}

.btn-file:active {
    transform: translateY(0);
}

/* BOTÃO PUBLICAR MELHORADO */
.btn-publish {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: #4a90e2;
    border: none;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(74, 144, 226, 0.3);
}

.btn-publish:hover {
    background: #3a80d2;
    box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
    transform: translateY(-2px);
}

.btn-publish:active {
    transform: translateY(0);
}

/* RESPONSIVIDADE PARA OS BOTÕES */
@media (max-width: 768px) {
    .post-options {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .btn-file, .btn-publish {
        justify-content: center;
    }
}
</style>
<script>
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

    // Script para mostrar o nome do arquivo quando uma imagem é selecionada
    document.getElementById('imagem').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        if (fileName) {
            const label = document.querySelector('.btn-file');
            label.innerHTML = `<i class="fas fa-image"></i> ${fileName}`;
            
            // Adiciona uma indicação visual de que uma imagem foi selecionada
            label.style.background = 'rgba(74, 144, 226, 0.1)';
            label.style.borderColor = '#3a80d2';
        }
    });
});
</script>

<div class="container">
    <div class="feed-container">
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
        <div class="feed">
            <div class="create-post">
                <form method="POST" enctype="multipart/form-data">
                    <div class="post-author">
                        <img src="../uploads/avatars/<?php echo $usuario['avatar']; ?>" alt="Avatar" class="post-avatar">
                        <textarea name="conteudo" placeholder="O que está acontecendo?" required></textarea>
                    </div>
                    <div class="post-options">
                        <label for="imagem" class="btn-file">
                            <i class="fas fa-image"></i> Foto
                        </label>
                        <input type="file" id="imagem" name="imagem" accept="image/*" style="display: none;">
                        <button type="submit" class="btn-publish">Publicar</button>
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
                <p>© 2025 RL</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>