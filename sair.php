<?php
session_start();

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir completamente a sessão, exclua também o cookie da sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: login.php');
exit();
?>
