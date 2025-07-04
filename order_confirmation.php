<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php'; // For header/footer consistency, though not strictly needed for core logic here
require_once 'includes/functions.php';   // For sanitize_html_output

// Check if order confirmation data exists in session
if (!isset($_SESSION['order_confirmation'])) {
    // If no order confirmation data, perhaps redirect to home or dashboard
    // For now, just show a generic message or redirect to prevent direct access without an order.
    header("Location: index.php");
    exit;
}

$order_details = $_SESSION['order_confirmation'];
unset($_SESSION['order_confirmation']); // Clear it after displaying once

$page_title = "Confirmation de Commande - Ouipneu.fr";
$header_cart_count = 0; // Cart should be empty now

// User details for header, if needed (though cart is main concern for header on this page)
$user_prenom = $_SESSION['prenom_utilisateur'] ?? '';

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
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .confirmation-container {
            background-color: var(--bg-surface);
            padding: 2rem;
            border-radius: var(--border-radius-medium);
            text-align: center;
            max-width: 700px;
            margin: 2rem auto;
        }
        .confirmation-container h1 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
        }
        .confirmation-container p {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        .confirmation-container .order-id {
            font-weight: var(--font-weight-semibold);
            color: var(--accent-primary-darker);
        }
        .confirmation-container .cta-button {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <header id="main-header">
        <div class="container">
            <div class="logo"><a href="index.php"><img src="images/logo-placeholder-dark.png" alt="Logo Ouipneu.fr"></a></div>
            <nav class="main-nav">
                <ul id="main-nav-links">
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="produits.php">Nos Pneus</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="index.php#about-us">À Propos</a></li>
                </ul>
            </nav>
            <div class="header-actions">
                <form class="search-bar" role="search"><input type="search" placeholder="Rechercher..."><button type="submit"><i class="fas fa-search"></i></button></form>
                <div class="account-icon">
                    <?php if (isset($_SESSION['id_utilisateur'])): ?>
                        <a href="dashboard.php" aria-label="Mon Compte"><i class="fas fa-user-circle"></i></a>
                    <?php else: ?>
                        <a href="login.php" aria-label="Mon Compte"><i class="fas fa-user-circle"></i></a>
                    <?php endif; ?>
                </div>
                <div class="cart-icon">
                    <a href="panier.php" aria-label="Panier"><i class="fas fa-shopping-cart"></i><span class="cart-item-count"><?php echo $header_cart_count; ?></span></a>
                </div>
                <button id="hamburger-button" class="hamburger-button"><span class="hamburger-box"><span class="hamburger-inner"></span></span></button>
            </div>
        </div>
    </header>

    <main class="site-main-content">
        <section id="order-confirmation-section" class="section-padding">
            <div class="container">
                <div class="confirmation-container" data-aos="fade-up">
                    <i class="fas fa-check-circle fa-4x" style="color: var(--accent-primary); margin-bottom: 1rem;"></i>
                    <h1>Merci pour votre commande !</h1>
                    <p>Votre commande a été enregistrée avec succès.</p>
                    <p>Numéro de commande : <strong class="order-id">#<?php echo sanitize_html_output($order_details['order_id']); ?></strong></p>
                    <p>Montant total : <strong><?php echo sanitize_html_output(number_format($order_details['total'], 2, ',', ' ')); ?> €</strong></p>
                    <p>Un email de confirmation vous sera envoyé prochainement avec les détails de votre commande.</p>
                    <a href="produits.php" class="cta-button">Continuer mes achats</a>
                    <a href="dashboard.php#dashboard-orders-content" class="cta-button secondary" style="margin-left:1rem;">Voir mes commandes</a>
                </div>
            </div>
        </section>
    </main>

    <footer id="main-footer">
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column"><h3>Ouipneu.fr</h3><p>Votre partenaire de confiance...</p></div>
                <div class="footer-column"><h3>Navigation</h3><ul><li><a href="index.php">Accueil</a></li><li><a href="produits.php">Produits</a></li><li><a href="contact.php">Contact</a></li><li><a href="<?php echo isset($_SESSION['id_utilisateur']) ? 'dashboard.php' : 'login.php'; ?>">Mon Compte</a></li></ul></div>
                <div class="footer-column"><h3>Informations</h3><ul><li><a href="legal-notice.php">Mentions Légales</a></li><li><a href="privacy-policy.php">Politique de Confidentialité</a></li><li><a href="cgv.php">CGV</a></li></ul></div>
                <div class="footer-column"><h3>Suivez-Nous</h3><div class="social-icons"><a href="#"><i class="fab fa-facebook-f"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
            </div>
            <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> Ouipneu.fr. Tous droits réservés. | <a href="admin_login.html">Admin</a></p></div>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
