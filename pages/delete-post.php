<?php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';

if (!isset($_GET['id'])) {
    header('Location: feed.php');
    exit();
}

$post_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Buscar post
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND usuario_id = ?");
$stmt->execute([$post_id, $_SESSION['usuario_id']]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('Location: feed.php');
    exit();
}

// Excluir imagem do post se existir
if ($post['imagem']) {
    @unlink('../uploads/posts/' . $post['imagem']);
}

// Excluir post
$stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
$stmt->execute([$post_id]);

// Redireciona para o perfil do usuário dono do post com mensagem de sucesso
header('Location: profile.php?id=' . $_SESSION['usuario_id'] . '&sucesso=excluido');
exit();
