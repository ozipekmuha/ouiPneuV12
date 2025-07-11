<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politique de Confidentialité - Ouipneu.fr</title>
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
        <section id="privacy-policy-section" class="section-padding static-page-section">
            <div class="container">
                <h1 class="page-title" data-aos="fade-up">Politique de Confidentialité</h1>
                <div class="static-page-content" data-aos="fade-up" data-aos-delay="100">
                    <p><em>Dernière mise à jour : [Date de la dernière mise à jour]</em></p>

                    <h2>Introduction</h2>
                    <p>Ouipneu SAS ("nous", "notre" ou "nos") s'engage à protéger la vie privée des utilisateurs ("vous" ou "votre") de notre site web ouipneu.fr (le "Site"). Cette Politique de Confidentialité décrit comment nous collectons, utilisons, divulguons et protégeons vos informations personnelles.</p>

                    <h2>Collecte des Informations Personnelles</h2>
                    <p>Nous pouvons collecter les types d'informations personnelles suivants :</p>
                    <ul>
                        <li><strong>Informations d'identification :</strong> Nom, prénom, adresse e-mail, adresse postale, numéro de téléphone.</li>
                        <li><strong>Informations de connexion :</strong> Nom d'utilisateur, mot de passe (crypté).</li>
                        <li><strong>Informations de commande :</strong> Détails des produits achetés, historique des commandes, informations de paiement (traitées par nos prestataires de paiement sécurisés).</li>
                        <li><strong>Informations techniques :</strong> Adresse IP, type de navigateur, système d'exploitation, pages visitées sur notre Site, dates et heures de visite, données de cookies.</li>
                        <li><strong>Communications :</strong> Toute correspondance que vous nous envoyez.</li>
                    </ul>
                    <p>Nous collectons ces informations lorsque vous :</p>
                    <ul>
                        <li>Créez un compte sur notre Site.</li>
                        <li>Passez une commande.</li>
                        <li>Vous abonnez à notre newsletter.</li>
                        <li>Nous contactez via notre formulaire de contact ou par e-mail.</li>
                        <li>Naviguez sur notre Site (via les cookies et technologies similaires).</li>
                    </ul>

                    <h2>Utilisation de Vos Informations</h2>
                    <p>Nous utilisons vos informations personnelles aux fins suivantes :</p>
                    <ul>
                        <li>Fournir et gérer nos services, y compris le traitement de vos commandes et paiements.</li>
                        <li>Gérer votre compte utilisateur.</li>
                        <li>Communiquer avec vous concernant vos commandes, votre compte ou vos demandes.</li>
                        <li>Vous envoyer notre newsletter et des offres promotionnelles (si vous y avez consenti).</li>
                        <li>Améliorer notre Site, nos produits et nos services.</li>
                        <li>Assurer la sécurité de notre Site et prévenir la fraude.</li>
                        <li>Respecter nos obligations légales.</li>
                    </ul>

                    <h2>Partage de Vos Informations</h2>
                    <p>Nous ne vendons ni ne louons vos informations personnelles à des tiers. Nous pouvons partager vos informations avec :</p>
                    <ul>
                        <li><strong>Nos prestataires de services :</strong> Tels que les processeurs de paiement, les services de livraison, les hébergeurs de site web, qui ont besoin d'accéder à vos informations pour nous fournir leurs services. Ils sont contractuellement tenus de protéger vos informations.</li>
                        <li><strong>Autorités légales :</strong> Si la loi l'exige ou pour protéger nos droits légaux.</li>
                    </ul>

                    <h2>Sécurité des Données</h2>
                    <p>Nous mettons en œuvre des mesures de sécurité techniques et organisationnelles appropriées pour protéger vos informations personnelles contre l'accès non autorisé, la divulgation, l'altération ou la destruction. Cependant, aucune méthode de transmission sur Internet ou de stockage électronique n'est totalement sécurisée.</p>

                    <h2 id="cookies">Cookies et Technologies Similaires</h2>
                    <p>Notre Site utilise des cookies pour améliorer votre expérience utilisateur, analyser le trafic du site et personnaliser le contenu. Les cookies sont de petits fichiers texte stockés sur votre appareil.</p>
                    <p>Types de cookies que nous utilisons :</p>
                    <ul>
                        <li><strong>Cookies essentiels :</strong> Nécessaires au fonctionnement du Site (ex: panier d'achat, connexion au compte).</li>
                        <li><strong>Cookies de performance/analytiques :</strong> Nous aident à comprendre comment les visiteurs interagissent avec notre Site (ex: Google Analytics).</li>
                        <li><strong>Cookies de fonctionnalité :</strong> Permettent de mémoriser vos préférences (ex: langue).</li>
                        <li><strong>Cookies de ciblage/publicité :</strong> Peuvent être utilisés pour vous présenter des publicités pertinentes (nous n'utilisons pas ce type de cookie actuellement sans votre consentement explicite pour des publicités tierces).</li>
                    </ul>
                    <p>Vous pouvez gérer vos préférences en matière de cookies via les paramètres de votre navigateur. Le refus de certains cookies peut affecter la fonctionnalité du Site.</p>

                    <h2>Vos Droits</h2>
                    <p>Conformément au RGPD, vous disposez des droits suivants concernant vos informations personnelles :</p>
                    <ul>
                        <li><strong>Droit d'accès :</strong> Demander une copie des informations que nous détenons sur vous.</li>
                        <li><strong>Droit de rectification :</strong> Demander la correction d'informations inexactes ou incomplètes.</li>
                        <li><strong>Droit à l'effacement ("droit à l'oubli") :</strong> Demander la suppression de vos informations dans certaines circonstances.</li>
                        <li><strong>Droit à la limitation du traitement :</strong> Demander la restriction du traitement de vos informations dans certaines circonstances.</li>
                        <li><strong>Droit à la portabilité des données :</strong> Recevoir vos informations dans un format structuré et les transmettre à un autre responsable de traitement.</li>
                        <li><strong>Droit d'opposition :</strong> Vous opposer au traitement de vos informations à des fins de marketing direct ou pour des raisons tenant à votre situation particulière.</li>
                        <li><strong>Droit de retirer votre consentement :</strong> Si le traitement est basé sur votre consentement, vous pouvez le retirer à tout moment.</li>
                    </ul>
                    <p>Pour exercer ces droits, veuillez nous contacter à <a href="mailto:privacy@ouipneu.fr">privacy@ouipneu.fr</a>. Nous pourrons vous demander de vérifier votre identité avant de répondre à votre demande.</p>
                    <p>Vous avez également le droit d'introduire une réclamation auprès de l'autorité de contrôle compétente (en France, la CNIL).</p>

                    <h2>Conservation des Données</h2>
                    <p>Nous conservons vos informations personnelles aussi longtemps que nécessaire pour atteindre les finalités pour lesquelles elles ont été collectées, y compris pour satisfaire à toute exigence légale, comptable ou en matière de reporting.</p>

                    <h2>Modifications de cette Politique</h2>
                    <p>Nous pouvons mettre à jour cette Politique de Confidentialité de temps à autre. Nous vous informerons de tout changement important en publiant la nouvelle politique sur cette page et en mettant à jour la date de "Dernière mise à jour".</p>

                    <h2>Nous Contacter</h2>
                    <p>Si vous avez des questions concernant cette Politique de Confidentialité ou nos pratiques en matière de données, veuillez nous contacter à :</p>
                    <p>Ouipneu SAS</p>
                    <p>Service de la Protection des Données</p>
                    <p>123 Rue du Pneu, 75000 Pneuhouse, France</p>
                    <p>Email : <a href="mailto:privacy@ouipneu.fr">privacy@ouipneu.fr</a></p>
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
