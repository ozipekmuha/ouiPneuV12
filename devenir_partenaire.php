<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php'; // Garder au cas où pour des évolutions futures
require_once 'includes/functions.php';

$form_message = '';
$form_message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données du formulaire
    $nom_garage = trim(filter_input(INPUT_POST, 'nom_garage', FILTER_SANITIZE_SPECIAL_CHARS));
    $adresse_garage = trim(filter_input(INPUT_POST, 'adresse_garage', FILTER_SANITIZE_SPECIAL_CHARS));
    $telephone_garage = trim(filter_input(INPUT_POST, 'telephone_garage', FILTER_SANITIZE_SPECIAL_CHARS));
    $email_contact = trim(filter_input(INPUT_POST, 'email_contact', FILTER_VALIDATE_EMAIL));
    $services_proposes = trim(filter_input(INPUT_POST, 'services_proposes', FILTER_SANITIZE_SPECIAL_CHARS));
    $message_partenaire = trim(filter_input(INPUT_POST, 'message_partenaire', FILTER_SANITIZE_SPECIAL_CHARS));

    // Validation
    $errors = [];
    if (empty($nom_garage)) {
        $errors[] = "Le nom du garage est requis.";
    }
    if (empty($adresse_garage)) {
        $errors[] = "L'adresse du garage est requise.";
    }
    if (empty($telephone_garage)) {
        $errors[] = "Le numéro de téléphone est requis.";
    }
    if (empty($email_contact)) {
        $errors[] = "L'adresse email de contact est requise ou invalide.";
    }

    if (empty($errors)) {
        try {
            // Création de la table GaragesCandidats si elle n'existe pas
            // Note: Il est préférable de créer les tables via un script SQL séparé ou phpMyAdmin,
            // mais pour simplifier, on peut le faire ici avec une vérification.
            $pdo->exec("CREATE TABLE IF NOT EXISTS GaragesCandidats (
                id_candidat INT AUTO_INCREMENT PRIMARY KEY,
                nom_garage VARCHAR(255) NOT NULL,
                adresse_garage TEXT NOT NULL,
                telephone_garage VARCHAR(50) NOT NULL,
                email_contact VARCHAR(255) NOT NULL,
                services_proposes TEXT,
                message_partenaire TEXT,
                date_soumission TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                statut VARCHAR(50) DEFAULT 'en_attente'
            )");

            $sql = "INSERT INTO GaragesCandidats (nom_garage, adresse_garage, telephone_garage, email_contact, services_proposes, message_partenaire)
                    VALUES (:nom_garage, :adresse_garage, :telephone_garage, :email_contact, :services_proposes, :message_partenaire)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom_garage' => $nom_garage,
                ':adresse_garage' => $adresse_garage,
                ':telephone_garage' => $telephone_garage,
                ':email_contact' => $email_contact,
                ':services_proposes' => $services_proposes,
                ':message_partenaire' => $message_partenaire
            ]);

            if ($stmt->rowCount() > 0) {
                $form_message = "Merci ! Votre candidature a bien été envoyée. Nous vous recontacterons bientôt.";
                $form_message_type = 'success';
            } else {
                $errors[] = "Une erreur est survenue lors de l'enregistrement de votre candidature. Veuillez réessayer.";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la soumission de candidature garage: " . $e->getMessage());
            $errors[] = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
        }
    }

    if (!empty($errors)) {
        $form_message = implode("<br>", $errors);
        $form_message_type = 'error';
    }

    // Si la requête est AJAX, on renvoie du JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['message' => $form_message, 'type' => $form_message_type]);
        exit;
    }
    // Si ce n'est pas AJAX, le message sera affiché dans le HTML (pour le cas où JS est désactivé)
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenir Garage Partenaire - Ouipneu.fr</title>
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
                    <!-- Le lien "Devenir Garage Partenaire" sera plutôt dans le footer pour ne pas surcharger le header -->
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
                    <?php else: ?>
                        <a href="login.php" aria-label="Mon Compte"><i class="fas fa-user-circle"></i></a>
                    <?php endif; ?>
                </div>
                <div class="cart-icon">
                    <a href="panier.php" aria-label="Panier"><i class="fas fa-shopping-cart"></i><span class="cart-item-count"><?php echo array_sum($_SESSION['panier'] ?? []); ?></span></a>
                </div>
                <button type="button" id="mobile-search-toggle-button" class="mobile-search-toggle" aria-label="Ouvrir la recherche" aria-expanded="false">
                    <i class="fas fa-search"></i>
                </button>
                <button id="hamburger-button" class="hamburger-button" aria-label="Ouvrir le menu" aria-expanded="false">
                    <span class="hamburger-box">
                        <span class="hamburger-inner"></span>
                    </span>
                </button>
            </div>
        </div>
    </header>

    <div id="global-promo-banner" style="background-color: var(--accent-primary); color: var(--text-on-accent); text-align: center; padding: 0.75rem 1rem; font-size: 0.9rem; font-weight: 500;">
        <p style="margin:0; color: black;">🔥 Livraison Gratuite sur toutes les commandes ! 🔥</p>
    </div>

    <main class="site-main-content section-padding">
        <div class="container">
            <section id="devenir-partenaire" class="text-content-section">
                <h1 class="section-title" data-aos="fade-up">Devenez Garage Partenaire Ouipneu.fr</h1>
                <p data-aos="fade-up" data-aos-delay="100">Rejoignez notre réseau croissant de professionnels du pneu et offrez vos services de montage à nos clients. En devenant partenaire, vous augmentez votre visibilité, attirez une nouvelle clientèle et bénéficiez d'un flux régulier de demandes de montage.</p>

                <h2 class="subsection-title" data-aos="fade-up" data-aos-delay="200">Vos Avantages</h2>
                <ul data-aos="fade-up" data-aos-delay="250">
                    <li>Augmentez votre chiffre d'affaires grâce à nos clients.</li>
                    <li>Gagnez en visibilité locale et régionale.</li>
                    <li>Intégrez un réseau de confiance et de qualité.</li>
                    <li>Processus simple et gestion facilitée des demandes.</li>
                </ul>

                <h2 class="subsection-title" data-aos="fade-up" data-aos-delay="300">Soumettez Votre Candidature</h2>
                <p data-aos="fade-up" data-aos-delay="350">Remplissez le formulaire ci-dessous pour nous faire part de votre intérêt. Nous examinerons votre demande et vous recontacterons dans les plus brefs délais.</p>

                <form id="form-devenir-partenaire" class="contact-form" action="#" method="POST" data-aos="fade-up" data-aos-delay="400">
                    <div class="form-group">
                        <label for="nom_garage">Nom du Garage <span class="required">*</span></label>
                        <input type="text" id="nom_garage" name="nom_garage" required>
                    </div>
                    <div class="form-group">
                        <label for="adresse_garage">Adresse Complète <span class="required">*</span></label>
                        <input type="text" id="adresse_garage" name="adresse_garage" required>
                    </div>
                    <div class="form-group">
                        <label for="telephone_garage">Téléphone <span class="required">*</span></label>
                        <input type="tel" id="telephone_garage" name="telephone_garage" required>
                    </div>
                    <div class="form-group">
                        <label for="email_contact">Adresse Email de Contact <span class="required">*</span></label>
                        <input type="email" id="email_contact" name="email_contact" required>
                    </div>
                    <div class="form-group">
                        <label for="services_proposes">Services de montage proposés (ex: tourisme, utilitaire, runflat, équilibrage, géométrie...)</label>
                        <textarea id="services_proposes" name="services_proposes" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="message_partenaire">Un message ou des informations complémentaires ?</label>
                        <textarea id="message_partenaire" name="message_partenaire" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                         <p style="font-size: 0.8em; color: #666;">Les champs marqués d'un <span class="required">*</span> sont obligatoires.</p>
                    </div>
                    <button type="submit" class="cta-button">Envoyer ma candidature</button>
                </form>
                <div id="form-feedback-partenaire" style="margin-top: 20px;"></div>
            </section>
        </div>
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
                        <li><a href="dashboard.php">Mon Compte</a></li>
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
                <p>&copy; <span id="current-year"><?php echo date('Y'); ?></span> Ouipneu.fr. Tous droits réservés. <span style="margin-left: 10px;">|</span> <a href="admin_login.php" style="font-size: 0.8em; color: var(--text-secondary);">Admin</a></p>
            </div>
        </div>
    </footer>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  // Initialisation Swiper si besoin sur cette page (normalement non)
  // S'il y a des éléments Swiper spécifiques à cette page, les initialiser ici.
  // Sinon, ce script peut être omis ou simplifié si le main.js le gère globalement
  // et qu'il n'y a pas de carrousels sur cette page.
</script>
<script src="https://unpkg.com/aos@next/dist/aos.js" defer></script>
<script src="js/main.js" defer></script>
<script>
    // Script spécifique pour la page devenir_partenaire.php si nécessaire
    document.addEventListener('DOMContentLoaded', function() {
        AOS.init({
            duration: 800,
            once: true
        });

        const form = document.getElementById('form-devenir-partenaire');
        const feedbackDiv = document.getElementById('form-feedback-partenaire');

        if (form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                feedbackDiv.innerHTML = ''; // Clear previous messages
                const submitButton = form.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = 'Envoi en cours... <i class="fas fa-spinner fa-spin"></i>';

                // Validation basique côté client (peut être plus poussée)
                let clientIsValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    field.style.borderColor = ''; // Reset border color
                    if (!field.value.trim()) {
                        clientIsValid = false;
                        field.style.borderColor = 'red';
                    }
                });

                if (!clientIsValid) {
                    feedbackDiv.innerHTML = '<p style="color: red;">Veuillez remplir tous les champs obligatoires.</p>';
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    return;
                }

                const formData = new FormData(form);

                fetch('devenir_partenaire.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Important pour que le PHP détecte l'AJAX
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    let messageColor = data.type === 'success' ? 'green' : 'red';
                    feedbackDiv.innerHTML = `<p style="color: ${messageColor};">${data.message}</p>`;
                    if (data.type === 'success') {
                        form.reset();
                        // Réinitialiser les bordures des champs après succès
                        requiredFields.forEach(field => field.style.borderColor = '');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la soumission du formulaire:', error);
                    feedbackDiv.innerHTML = '<p style="color: red;">Une erreur de communication est survenue. Veuillez réessayer.</p>';
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
            });
        }

        // Afficher les messages PHP si la soumission n'était pas AJAX (JS désactivé par exemple)
        <?php if (!empty($form_message)): ?>
        const serverMessageColor = "<?php echo $form_message_type === 'success' ? 'green' : 'red'; ?>";
        feedbackDiv.innerHTML = `<p style="color: ${serverMessageColor};"><?php echo addslashes($form_message); ?></p>`;
        <?php endif; ?>
    });
</script>
</body>
</html>
