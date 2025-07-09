<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // For sanitize_html_output, if needed for displaying errors

$errors = [];
$email_input = ''; // To repopulate email field

// Display success message from registration if any
$success_message = $_SESSION['success_message'] ?? null;
if ($success_message) {
    unset($_SESSION['success_message']);
}
// Display message from logout
$logout_message = $_SESSION['logout_message'] ?? null;
if ($logout_message) {
    unset($_SESSION['logout_message']);
}

// Display general error message if any (e.g., from dashboard redirect)
$error_message_generic = $_SESSION['error_message'] ?? null;
if ($error_message_generic) {
    unset($_SESSION['error_message']);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_input = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email_input)) {
        $errors[] = "L'adresse email est requise.";
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Le format de l'adresse email est invalide.";
    }
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id_utilisateur, prenom, nom, email, mot_de_passe_hash, est_admin FROM Utilisateurs WHERE email = :email");
            $stmt->bindParam(':email', $email_input);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['mot_de_passe_hash'])) {
                // Password is correct, start session and store user data
                session_regenerate_id(true); // Regenerate session ID for security

                $_SESSION['id_utilisateur'] = $user['id_utilisateur'];
                $_SESSION['prenom_utilisateur'] = $user['prenom'];
                $_SESSION['nom_utilisateur'] = $user['nom'];
                $_SESSION['email_utilisateur'] = $user['email'];
                $_SESSION['est_admin'] = (bool)$user['est_admin'];

                // Update derniere_connexion
                $update_stmt = $pdo->prepare("UPDATE Utilisateurs SET derniere_connexion = CURRENT_TIMESTAMP WHERE id_utilisateur = :id_utilisateur");
                $update_stmt->bindParam(':id_utilisateur', $user['id_utilisateur']);
                $update_stmt->execute();

                // Redirect to dashboard or admin dashboard
                if ($_SESSION['est_admin']) {
                    // Potentially redirect to a specific admin dashboard if it exists
                    // header("Location: admin_dashboard.php"); // Example
                    header("Location: dashboard.php"); // For now, admin also goes to general dashboard
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                $errors[] = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            error_log("Erreur de connexion : " . $e->getMessage());
            $errors[] = "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
        }
    }
}
$page_title = "Connexion - Ouipneu.fr";
$header_cart_count = array_sum($_SESSION['panier'] ?? []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize_html_output($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header id="main-header">
        <div class="container">
            <div class="logo">
                <a href="index.php"><img src="./assets/images/logobg.png" alt="Logo Ouipneu.fr"></a>
            </div>
            <nav class="main-nav">
                <ul id="main-nav-links">
                    <li><a href="index.php" aria-label="Accueil">Accueil</a></li>
                    <li><a href="produits.php" aria-label="Nos Pneus">Nos Pneus</a></li>
                    <li><a href="contact.php" aria-label="Contact">Contact</a></li>
                    <li><a href="index.php#about-us" aria-label="À propos de nous">À Propos</a></li>
                </ul>
            </nav>
            <div class="header-actions">
                <form class="search-bar" role="search">
                    <input type="search" placeholder="Rechercher des pneus..." aria-label="Rechercher des pneus">
                    <button type="submit" aria-label="Lancer la recherche"><i class="fas fa-search"></i></button>
                </form>
                <div class="account-icon">
                    <?php if (isset($_SESSION['id_utilisateur'])): ?>
                        <a href="dashboard.php" aria-label="Mon Compte"><i class="fas fa-user-circle"></i></a>
                        <?php /* Le lien de déconnexion textuel est retiré du header principal. Il reste dans dashboard.php */ ?>
                    <?php else: ?>
                        <a href="login.php" aria-label="Mon Compte"><i class="fas fa-user-circle"></i></a>
                    <?php endif; ?>
                </div>
                <div class="cart-icon">
                    <a href="panier.php" aria-label="Panier"><i class="fas fa-shopping-cart"></i><span class="cart-item-count"><?php echo $header_cart_count; ?></span></a>
                </div>
                <button id="hamburger-button" class="hamburger-button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="main-nav-links">
                    <span class="hamburger-box">
                        <span class="hamburger-inner"></span>
                    </span>
                </button>
            </div>
        </div>
    </header>

    <main class="site-main-content">
        <section id="login-section" class="section-padding auth-section">
            <div class="container">
                <div class="auth-container" style="justify-self: center;" data-aos="fade-up">
                    <h1 class="page-title auth-title">Connexion</h1>

                    <?php if (!empty($errors)): ?>
                        <div class="global-notification-bar error show" style="position: static; transform: none; margin-bottom: 1rem;">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize_html_output($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="global-notification-bar success show" style="position: static; transform: none; margin-bottom: 1rem;">
                            <?php echo sanitize_html_output($success_message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($logout_message): ?>
                        <div class="global-notification-bar info show" style="position: static; transform: none; margin-bottom: 1rem;">
                            <?php echo sanitize_html_output($logout_message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message_generic): ?>
                        <div class="global-notification-bar error show" style="position: static; transform: none; margin-bottom: 1rem;">
                            <?php echo sanitize_html_output($error_message_generic); ?>
                        </div>
                    <?php endif; ?>

                    <form id="login-form" class="auth-form" method="POST" action="login.php">
                        <div class="form-group">
                            <label for="login-email">Adresse Email</label>
                            <input type="email" id="login-email" name="email" required autocomplete="email" value="<?php echo sanitize_html_output($email_input); ?>">
                        </div>
                        <div class="form-group">
                            <label for="login-password">Mot de passe</label>
                            <input type="password" id="login-password" name="password" required autocomplete="current-password">
                        </div>
                        <div class="form-group form-actions">
                            <button type="submit" class="cta-button auth-button">Se connecter</button>
                        </div>
                        <div class="form-links">
                            <a href="#" class="form-link">Mot de passe oublié ?</a> <!-- Placeholder -->
                            <p>Pas encore de compte ? <a href="register.php" class="form-link">Inscrivez-vous</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer id="main-footer">
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column">
                    <h3>Ouipneu.fr</h3>
                    <p>Votre partenaire de confiance pour des pneus premium, un montage expert et un service client exceptionnel.</p>
                </div>
                <div class="footer-column">
                    <h3>Navigation</h3>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="produits.php">Produits</a></li>
                        <li><a href="index.php#promotions">Promotions</a></li>
                        <li><a href="contact.php">Contactez-nous</a></li>
                        <li><a href="<?php echo isset($_SESSION['id_utilisateur']) ? 'dashboard.php' : 'login.php'; ?>">Mon Compte</a></li>
                        <li><a href="devenir_partenaire.php">Devenir Garage Partenaire</a></li>
                        <li><a href="nos_garages_partenaires.php">Nos Garages Partenaires</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Informations</h3>
                    <ul>
                        <li><a href="legal-notice.php">Mentions Légales</a></li>
                        <li><a href="privacy-policy.php">Politique de Confidentialité</a></li>
                        <li><a href="cgv.php">Conditions Générales de Vente</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Suivez-Nous</h3>
                    <div class="social-icons">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <span id="current-year"><?php echo date('Y'); ?></span> Ouipneu.fr. Tous droits réservés. <span style="margin-left: 10px;">|</span> <a href="admin_login.html" style="font-size: 0.8em; color: var(--text-secondary);">Admin</a></p>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
