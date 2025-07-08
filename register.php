<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Assuming sanitize_html_output is here

$errors = [];
$inputs = [
    'firstname' => '', // Corresponds to 'Prénom'
    'lastname' => '',  // Corresponds to 'Nom'
    'email' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputs['firstname'] = trim($_POST['firstname'] ?? '');
    $inputs['lastname'] = trim($_POST['lastname'] ?? '');
    $inputs['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']);

    // Validation
    if (empty($inputs['firstname'])) {
        $errors[] = "Le prénom est requis.";
    }
    if (empty($inputs['lastname'])) {
        $errors[] = "Le nom est requis.";
    }
    if (empty($inputs['email'])) {
        $errors[] = "L'adresse email est requise.";
    } elseif (!filter_var($inputs['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Le format de l'adresse email est invalide.";
    }
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis.";
    } elseif (strlen($password) < 8) {
        // Basic length check, more complex rules can be added
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    if (!$agree_terms) {
        $errors[] = "Vous devez accepter les Conditions Générales de Vente et la Politique de Confidentialité.";
    }

    if (empty($errors)) {
        echo "<pre>DEBUG: Validation passed.</pre>";
        echo "<pre>DEBUG: Inputs: " . print_r($inputs, true) . "</pre>";
        // die("[REGISTER DEBUG] After validation"); // First debug point

        try {
            if (!$pdo) {
                $errors[] = "Erreur critique: La connexion à la base de données n'est pas disponible.";
                echo "<pre>DEBUG: Critical: PDO object is null or false before email check.</pre>";
                // die("[REGISTER DEBUG] PDO object error");
            } else {
                echo "<pre>DEBUG: PDO object seems available for email check.</pre>";

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateurs WHERE email = :email");
                $stmt->bindParam(':email', $inputs['email']);
                $stmt->execute();
                echo "<pre>DEBUG: Email check query executed for: " . sanitize_html_output($inputs['email']) . "</pre>";

                $existing_user = $stmt->fetch();
                if ($existing_user) {
                    $errors[] = "Cette adresse email est déjà utilisée. Veuillez en choisir une autre ou vous connecter.";
                    echo "<pre>DEBUG: Email already exists: " . sanitize_html_output($inputs['email']) . "</pre>";
                } else {
                    echo "<pre>DEBUG: Email " . sanitize_html_output($inputs['email']) . " does not exist. Proceeding with hashing and insertion.</pre>";
                    $mot_de_passe_hash = password_hash($password, PASSWORD_DEFAULT);
                    echo "<pre>DEBUG: Password hashed for " . sanitize_html_output($inputs['email']) . ". Hash: " . $mot_de_passe_hash . "</pre>";

                    $sql_insert = "INSERT INTO Utilisateurs (prenom, nom, email, mot_de_passe_hash) VALUES (:prenom, :nom, :email, :mot_de_passe_hash)";
                    echo "<pre>DEBUG: Insert SQL: " . $sql_insert . "</pre>";
                    $insert_stmt = $pdo->prepare($sql_insert);

                    $insert_stmt->bindParam(':prenom', $inputs['firstname']);
                    $insert_stmt->bindParam(':nom', $inputs['lastname']);
                    $insert_stmt->bindParam(':email', $inputs['email']);
                    $insert_stmt->bindParam(':mot_de_passe_hash', $mot_de_passe_hash);
                    echo "<pre>DEBUG: Parameters for insert: prenom=" . sanitize_html_output($inputs['firstname']) . ", nom=" . sanitize_html_output($inputs['lastname']) . ", email=" . sanitize_html_output($inputs['email']) . "</pre>";

                    // die("[REGISTER DEBUG] Before insert execute"); // Second debug point

                    if ($insert_stmt->execute()) {
                        $new_user_id = $pdo->lastInsertId();
                        echo "<pre>DEBUG: User inserted successfully for " . sanitize_html_output($inputs['email']) . ". New User ID: " . $new_user_id . "</pre>";
                        // die("[REGISTER DEBUG] SUCCESSFUL INSERT - Redirecting soon"); // Success debug point

                        $_SESSION['success_message'] = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                        // header("Location: login.php"); // Comment out for debugging
                        // exit;
                        echo "<p style='color:green; font-weight:bold;'>Inscription réussie! Redirection vers login.php dans 3 secondes...</p><script>setTimeout(function(){ window.location.href='login.php'; }, 3000);</script>";
                        exit;

                    } else {
                        $pdo_error_info = $insert_stmt->errorInfo();
                        $errors[] = "Une erreur est survenue lors de l'enregistrement de votre compte.";
                        echo "<pre>DEBUG: Failed to execute insert statement for " . sanitize_html_output($inputs['email']) . ". Error: " . print_r($pdo_error_info, true) . "</pre>";
                        // die("[REGISTER DEBUG] Insert execute failed");
                    }
                }
            }
        } catch (PDOException $e) {
            echo "<pre>DEBUG: PDOException during registration for " . sanitize_html_output($inputs['email']) . ": " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "</pre>";
            $errors[] = "Une erreur technique est survenue lors de l'inscription. Veuillez réessayer plus tard.";
            // die("[REGISTER DEBUG] PDO Exception caught");
        }
    } else {
        echo "<pre>DEBUG: Validation failed. Errors: " . print_r($errors, true) . "</pre>";
        // die("[REGISTER DEBUG] Validation errors");
    }
    // If errors occurred, they will be displayed below in the HTML form.
    // We also clear any success message that might exist from other pages.
    unset($_SESSION['success_message']);
}

// Retrieve stored errors and inputs from session if redirected from old handler (fallback, should not be needed now)
if (isset($_SESSION['register_errors'])) {
    $errors = array_merge($errors, $_SESSION['register_errors']);
    unset($_SESSION['register_errors']);
}
if (isset($_SESSION['register_inputs'])) {
    $inputs = array_merge($inputs, $_SESSION['register_inputs']);
    unset($_SESSION['register_inputs']);
}

$page_title = "Inscription - Ouipneu.fr";
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
                        <a href="login.php" aria-label="Mon Compte" class="active"><i class="fas fa-user-circle"></i></a> <?php // 'active' class might be relevant here if on login/register page ?>
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
        <section id="register-section" class="section-padding auth-section">
            <div class="container">
                <div class="auth-container" style="justify-self: center;" data-aos="fade-up">
                    <h1 class="page-title auth-title">Créer un compte</h1>

                    <?php if (!empty($errors)): ?>
                        <div class="global-notification-bar error show" style="position: static; transform: none; margin-bottom: 1rem;">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize_html_output($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form id="register-form" class="auth-form" method="POST" action="register.php">
                        <div class="form-group">
                            <label for="register-firstname">Prénom</label>
                            <input type="text" id="register-firstname" name="firstname" required autocomplete="given-name" value="<?php echo sanitize_html_output($inputs['firstname']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="register-lastname">Nom</label>
                            <input type="text" id="register-lastname" name="lastname" required autocomplete="family-name" value="<?php echo sanitize_html_output($inputs['lastname']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="register-email">Adresse Email</label>
                            <input type="email" id="register-email" name="email" required autocomplete="email" value="<?php echo sanitize_html_output($inputs['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="register-password">Mot de passe</label>
                            <input type="password" id="register-password" name="password" required autocomplete="new-password" aria-describedby="password-constraints">
                            <p id="password-constraints" class="form-text help-text">Minimum 8 caractères.</p> <!-- Simplified constraint message -->
                        </div>
                        <div class="form-group">
                            <label for="register-confirm-password">Confirmer le mot de passe</label>
                            <input type="password" id="register-confirm-password" name="confirm_password" required autocomplete="new-password">
                        </div>
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="register-agree-terms" name="agree_terms" required <?php echo (isset($_POST['agree_terms'])) ? 'checked' : ''; ?>>
                            <label for="register-agree-terms" class="checkbox-label">J'accepte les <a href="cgv.php" target="_blank">Conditions Générales de Vente</a> et la <a href="privacy-policy.php" target="_blank">Politique de Confidentialité</a>.</label>
                        </div>
                        <div class="form-group form-actions">
                            <button type="submit" class="cta-button auth-button">S'inscrire</button>
                        </div>
                        <div class="form-links">
                            <p>Déjà un compte ? <a href="login.php" class="form-link">Connectez-vous</a></p>
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
