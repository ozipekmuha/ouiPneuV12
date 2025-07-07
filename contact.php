<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php'; // For sanitize_html_output, if used, and for consistency
require_once 'includes/db_connect.php'; // Only if DB interaction is needed on this page directly

$page_title = "Contactez-Nous - Ouipneu.fr";
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
                    <li><a href="contact.php" class="active" aria-label="Contact">Contact</a></li>
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
        <section id="contact-section" class="section-padding">
            <div class="container">
                <h1 class="page-title" data-aos="fade-up">Contactez-Nous</h1>
                <p class="section-intro" data-aos="fade-up" data-aos-delay="100">
                    Une question ? Une demande particulière ? N'hésitez pas à nous contacter via le formulaire ci-dessous ou par nos autres canaux. Notre équipe est là pour vous aider !
                </p>

                <div class="contact-layout" data-aos="fade-up" data-aos-delay="200">
                    <div class="contact-form-container">
                        <h2 class="contact-subtitle">Envoyez-nous un message</h2>
                        <form id="contact-form" class="contact-form" method="POST" action="contact.php"> <!-- Assuming it posts to itself -->
                            <!-- Add PHP logic here to handle form submission if this page processes it -->
                            <!-- Example: Nonce for security, error display area -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact-name">Nom complet</label>
                                    <input type="text" id="contact-name" name="name" required autocomplete="name">
                                </div>
                                <div class="form-group">
                                    <label for="contact-email">Adresse Email</label>
                                    <input type="email" id="contact-email" name="email" required autocomplete="email">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="contact-subject">Sujet</label>
                                <input type="text" id="contact-subject" name="subject" required>
                            </div>
                            <div class="form-group">
                                <label for="contact-message">Votre message</label>
                                <textarea id="contact-message" name="message" rows="6" required></textarea>
                            </div>
                            <div class="form-group form-actions">
                                <button type="submit" class="cta-button contact-submit-button">Envoyer le message</button>
                            </div>
                        </form>
                    </div>

                    <div class="contact-info-container">
                        <h2 class="contact-subtitle">Nos Coordonnées</h2>
                        <ul class="contact-details-list">
                            <li>
                                <i class="fas fa-map-marker-alt contact-icon"></i>
                                <div>
                                    <strong>Adresse :</strong>
                                    <p>5 All. du Breuil, 54700 Pont-à-Mousson</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-phone-alt contact-icon"></i>
                                <div>
                                    <strong>Téléphone :</strong>
                                    <p><a href="tel:+33612912648">06 12 91 26 48</a></p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-envelope contact-icon"></i>
                                <div>
                                    <strong>Email :</strong>
                                    <p><a href="mailto:contact@ouipneu.fr">contact@ouipneu.fr</a></p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-clock contact-icon"></i>
                                <div>
                                    <strong>Horaires d'ouverture :</strong>
                                    <p>Lundi - Vendredi : 9h00 - 18h00</p>
                                    <p>Samedi : 9h00 - 12h00</p>
                                </div>
                            </li>
                        </ul>
                         <div id="contact-map-placeholder" style="height: 250px; background-color: var(--bg-dark); border-radius: var(--border-radius-medium); margin-top:1.5rem; display:flex; align-items:center; justify-content:center; color: var(--text-secondary);"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2621.809999663384!2d6.0610344!3d48.919012099999996!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4794c7cdaa267e09%3A0x336bf54067e9462a!2sOUI%20PNEU!5e0!3m2!1sfr!2sfr!4v1751906947064!5m2!1sfr!2sfr" width="100%" height="260" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
                    </div>
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
                        <li><a href="legal-notice.php">Mentions Légales</a></li> <!-- Assuming these will become .php -->
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
