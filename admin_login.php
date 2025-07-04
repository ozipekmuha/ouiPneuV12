<?php
session_start();
require_once 'includes/db_connect.php'; // Pour une utilisation future avec la table Utilisateurs

// Si l'admin est déjà connecté, rediriger vers le tableau de bord
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // TODO: Remplacer par une vraie vérification en base de données avec password_verify
    // Exemple simplifié (NON SÉCURISÉ POUR PRODUCTION)
    $admin_email_correct = 'admin@example.com'; // Ou simplement 'admin' si pas un email
    $admin_password_correct = 'password123'; // Utiliser un mot de passe plus fort

    if ($email === $admin_email_correct && $password === $admin_password_correct) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $email; // Ou un nom d'utilisateur admin
        // Régénérer l'ID de session pour la sécurité
        session_regenerate_id(true);
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error_message = 'Email ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Connexion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Styles spécifiques pour la page de connexion admin (copiés de l'ancien HTML) */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--bg-dark);
            color: var(--text-light); /* Ajout pour que le message d'erreur soit visible */
            font-family: 'Poppins', sans-serif; /* Ajout pour la cohérence */
            margin: 0; /* Ajout pour la cohérence */
        }
        .admin-login-section {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem; /* Ajout pour les petits écrans */
        }
        .admin-auth-container {
            background-color: var(--bg-surface);
            padding: 2rem; /* Réduit un peu pour mobile */
            border-radius: var(--border-radius-medium, 8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px; /* Réduit un peu */
            text-align: center;
        }
        .admin-auth-title {
            color: var(--accent-primary);
            margin-bottom: 1.5rem;
            font-size: 1.6rem; /* Réduit un peu */
        }
        .admin-auth-form .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .admin-auth-form label {
            display: block;
            font-weight: var(--font-weight-medium);
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }
        .admin-auth-form input[type="email"],
        .admin-auth-form input[type="password"] {
            width: 100%;
            padding: 0.7rem 0.9rem; /* Ajusté */
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-small, 4px);
            font-size: 0.95rem; /* Ajusté */
            color: var(--text-light);
            background-color: var(--bg-dark);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box; /* Important */
        }
        .admin-auth-form input[type="email"]:focus,
        .admin-auth-form input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(255, 221, 3, 0.25);
        }
        .admin-auth-button {
            width: 100%;
            padding: 0.8rem; /* Ajusté */
            font-size: 0.95rem; /* Ajusté */
            margin-top: 0.5rem;
        }
        .admin-footer {
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            background-color: var(--bg-surface); /* Ajout pour visibilité */
            border-top: 1px solid var(--border-color); /* Ajout pour visibilité */
        }
        .error-message {
            color: #e74c3c; /* Rouge pour erreur */
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid #e74c3c;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: var(--border-radius-small, 4px);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <section class="admin-login-section">
        <div class="admin-auth-container">
            <img src="assets/images/logobg.png" alt="Logo Ouipneu.fr" style="max-width: 160px; margin-bottom: 1.5rem; filter: invert(1) brightness(1.8) contrast(1.1);">
            <h1 class="admin-auth-title">Accès Administration</h1>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form id="admin-login-form" class="admin-auth-form" method="POST" action="admin_login.php">
                <div class="form-group">
                    <label for="admin-email">Email</label>
                    <input type="email" id="admin-email" name="email" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="admin-password">Mot de passe</label>
                    <input type="password" id="admin-password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="cta-button admin-auth-button">Se connecter</button>
            </form>
        </div>
    </section>
    <footer class="admin-footer">
        <p>&copy; <span id="current-year-admin"></span> Ouipneu.fr - Interface d'Administration</p>
    </footer>
    <script>
        document.getElementById('current-year-admin').textContent = new Date().getFullYear();
        // La simulation de connexion JS est retirée car gérée par PHP maintenant.
    </script>
</body>
</html>
