<?php
// pages/edit-profile.php
require_once '../includes/auth-check.php';
require_once '../includes/conexao.php';
require_once '../includes/functions.php';

$usuario = getUsuarioById($pdo, $_SESSION['usuario_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    
    // Verificar se username já existe (exceto para o usuário atual)
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
    $stmt->execute([$username, $_SESSION['usuario_id']]);
    if ($stmt->fetch()) {
        $erro = "Este nome de usuário já está em uso!";
    } else {
        // Atualizar dados básicos
        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, username = ?, bio = ? WHERE id = ?");
        $stmt->execute([$nome, $username, $bio, $_SESSION['usuario_id']]);
        
        // Processar upload de avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $extensao = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $nome_avatar = uniqid() . '.' . $extensao;
            $destino = '../uploads/avatars/' . $nome_avatar;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destino)) {
                // Remover avatar antigo se não for o padrão
                if ($usuario['avatar'] !== 'default-avatar.jpg') {
                    unlink('../uploads/avatars/' . $usuario['avatar']);
                }
                
                $stmt = $pdo->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?");
                $stmt->execute([$nome_avatar, $_SESSION['usuario_id']]);
            }
        }
        
        // Alterar senha se fornecida
        if (!empty($senha_atual) && !empty($nova_senha)) {
            if (password_verify($senha_atual, $usuario['senha'])) {
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$nova_senha_hash, $_SESSION['usuario_id']]);
                $sucesso = "Perfil e senha atualizados com sucesso!";
            } else {
                $erro = "Senha atual incorreta!";
            }
        } else {
            $sucesso = "Perfil atualizado com sucesso!";
        }
        
        // Atualizar dados na sessão
        $_SESSION['username'] = $username;
    }
}

// Buscar dados atualizados
$usuario = getUsuarioById($pdo, $_SESSION['usuario_id']);
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="edit-profile">
        <h1>Editar Perfil</h1>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <?php if (isset($sucesso)): ?>
            <div class="alert alert-success"><?php echo $sucesso; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="edit-profile-form">
            <div class="form-group">
                <label for="avatar">Foto de perfil</label>
                <div class="avatar-upload">
                    <img src="../uploads/avatars/<?php echo $usuario['avatar']; ?>" alt="Avatar" class="avatar-preview">
                    <input type="file" id="avatar" name="avatar" accept="image/*">
                </div>
            </div>
            
            <div class="form-group">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="username">Nome de usuário</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($usuario['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="bio">Biografia</label>
                <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($usuario['bio']); ?></textarea>
            </div>
            
            <h3>Alterar senha</h3>
            
            <div class="form-group">
                <label for="senha_atual">Senha atual</label>
                <input type="password" id="senha_atual" name="senha_atual">
            </div>
            
            <div class="form-group">
                <label for="nova_senha">Nova senha</label>
                <input type="password" id="nova_senha" name="nova_senha">
            </div>
            
            <button type="submit" class="btn btn-primary">Salvar alterações</button>
            <a href="profile.php?id=<?php echo $_SESSION['usuario_id']; ?>" class="btn btn-outline">Cancelar</a>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>