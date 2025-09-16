<?php
// index.php
session_start();
require_once 'includes/conexao.php';
require_once 'includes/functions.php';

// Redireciona para o feed se o usuário estiver logado
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/feed.php');
    exit();
} else {
    header('Location: pages/login.php');
    exit();
}
?>