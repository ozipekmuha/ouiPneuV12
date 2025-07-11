<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentions Légales - Ouipneu.fr</title>
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
                <!-- Modifié pour toujours avoir un lien vers l'index -->
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
                <form class="search-bar" role="search"> <!-- Ajout de role="search" -->
                    <input type="search" placeholder="Rechercher des pneus..." aria-label="Rechercher des pneus">
                    <button type="submit" aria-label="Lancer la recherche"><i class="fas fa-search"></i></button>
                </form>
                <div class="account-icon">
                    <!-- Lien standard vers dashboard.html, la logique de redirection vers login si non connecté sera gérée ultérieurement ou côté serveur -->
                    <a href="dashboard.html" aria-label="Mon Compte"><i class="fas fa-user-circle"></i></a>
                </div>
                <div class="cart-icon">
                    <a href="panier.html" aria-label="Panier"><i class="fas fa-shopping-cart"></i><span class="cart-item-count">0</span></a>
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
        <section id="legal-notice-section" class="section-padding static-page-section">
            <div class="container">
                <h1 class="page-title" data-aos="fade-up">Mentions Légales</h1>
                <div class="static-page-content" data-aos="fade-up" data-aos-delay="100">
                    <h2>Éditeur du Site</h2>
                    <p><strong>Nom de la société :</strong> Ouipneu SAS (Exemple)</p>
                    <p><strong>Forme juridique :</strong> Société par Actions Simplifiée (Exemple)</p>
                    <p><strong>Capital social :</strong> 10.000 € (Exemple)</p>
                    <p><strong>Adresse du siège social :</strong> 123 Rue du Pneu, 75000 Pneuhouse, France (Exemple)</p>
                    <p><strong>Numéro de téléphone :</strong> +33 1 23 45 67 89 (Exemple)</p>
                    <p><strong>Adresse e-mail :</strong> contact@ouipneu.fr (Exemple)</p>
                    <p><strong>RCS :</strong> Paris B 123 456 789 (Exemple)</p>
                    <p><strong>Numéro de TVA intracommunautaire :</strong> FR00123456789 (Exemple)</p>
                    <p><strong>Directeur de la publication :</strong> M. Jean Pneu (Exemple)</p>

                    <h2>Hébergement du Site</h2>
                    <p><strong>Nom de l'hébergeur :</strong> Hébergeur Pro XYZ (Exemple)</p>
                    <p><strong>Adresse de l'hébergeur :</strong> 456 Avenue du Cloud, 75015 Paris, France (Exemple)</p>
                    <p><strong>Numéro de téléphone de l'hébergeur :</strong> +33 9 87 65 43 21 (Exemple)</p>

                    <h2>Propriété Intellectuelle</h2>
                    <p>L'ensemble de ce site relève de la législation française et internationale sur le droit d'auteur et la propriété intellectuelle. Tous les droits de reproduction sont réservés, y compris pour les documents téléchargeables et les représentations iconographiques et photographiques.</p>
                    <p>La reproduction de tout ou partie de ce site sur un support électronique quel qu'il soit est formellement interdite sauf autorisation expresse du directeur de la publication.</p>

                    <h2>Données Personnelles</h2>
                    <p>Les informations recueillies font l’objet d’un traitement informatique destiné à la gestion des commandes et des relations commerciales. Conformément à la loi "Informatique et Libertés" du 6 janvier 1978 modifiée et au Règlement Général sur la Protection des Données (RGPD), vous bénéficiez d’un droit d’accès, de rectification, de suppression des informations qui vous concernent, que vous pouvez exercer en vous adressant à <a href="mailto:privacy@ouipneu.fr">privacy@ouipneu.fr</a>.</p>
                    <p>Pour plus d'informations, veuillez consulter notre <a href="privacy-policy.php">Politique de Confidentialité</a>.</p>

                    <h2>Cookies</h2>
                    <p>Le site Ouipneu.fr peut être amené à vous demander l’acceptation des cookies pour des besoins de statistiques et d'affichage. Un cookie est une information déposée sur votre disque dur par le serveur du site que vous visitez.</p>
                    <p>Pour en savoir plus sur notre utilisation des cookies, veuillez consulter notre <a href="privacy-policy.php#cookies">section Cookies dans la Politique de Confidentialité</a>.</p>

                    <h2>Limitation de responsabilité</h2>
                    <p>Ouipneu SAS s'efforce d'assurer au mieux de ses possibilités, l'exactitude et la mise à jour des informations diffusées sur ce site, dont elle se réserve le droit de corriger, à tout moment et sans préavis, le contenu. Toutefois, Ouipneu SAS ne peut garantir l'exactitude, la précision ou l'exhaustivité des informations mises à la disposition sur ce site.</p>
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
                        <li><a href="index.php#promotions">Promotions</a></li> <!-- Garder le lien vers la section pour l'instant -->
                        <li><a href="contact.php">Contactez-nous</a></li>
                        <li><a href="dashboard.html">Mon Compte</a></li>
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
                <p>&copy; <span id="current-year"></span> Ouipneu.fr. Tous droits réservés. <span style="margin-left: 10px;">|</span> <a href="admin_login.html" style="font-size: 0.8em; color: var(--text-secondary);">Admin</a></p>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
