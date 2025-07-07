<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Include centralized functions

$pneu_id = null;
$pneu = null;
$error_message = '';

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    $pneu_id = (int)$_GET['id'];
} else {
    $error_message = "ID de produit non valide ou manquant.";
    // Optionnel: rediriger vers une page 404 ou la page produits
    // header("Location: produits.php");
    // exit;
}

if ($pneu_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Pneus WHERE id = :id AND est_actif = TRUE");
        $stmt->bindParam(':id', $pneu_id, PDO::PARAM_INT);
        $stmt->execute();
        $pneu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pneu) {
            $error_message = "Produit non trouvé ou indisponible.";
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du produit ID $pneu_id: " . $e->getMessage());
        $error_message = "Une erreur est survenue lors de la récupération des informations du produit.";
        $pneu = null; // S'assurer que pneu est null en cas d'erreur
    }
}

// Local functions extractBrandFromProductName, getStockStatusDetails, parseTireSizeString
// have been moved to includes/functions.php and will be replaced by calls to:
// extractBrandFromName()
// getProductStockStatus() -> part of getProductDisplayDetails() or directly
// parseTireSize()

$page_title = "Détails du Produit - Ouipneu.fr";
if ($pneu && isset($pneu['nom'])) {
    $page_title = sanitize_html_output($pneu['nom']) . " - Ouipneu.fr";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
                    <a href="panier.php" aria-label="Panier"><i class="fas fa-shopping-cart"></i><span class="cart-item-count"><?php echo array_sum($_SESSION['panier'] ?? []); ?></span></a>
                </div>
                <button type="button" id="mobile-search-toggle-button" class="mobile-search-toggle" aria-label="Ouvrir la recherche" aria-expanded="false">
                    <i class="fas fa-search"></i>
                </button>
                <button id="hamburger-button" class="hamburger-button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="main-nav-links">
                    <span class="hamburger-box">
                        <span class="hamburger-inner"></span>
                    </span>
                </button>
            </div>
        </div>
    </header>

    <main class="site-main-content">
        <section id="product-detail-section" class="section-padding">
            <div class="container">
                <?php if ($pneu): ?>
                    <?php
                        $marque = extractBrandFromName($pneu['nom']); // Updated
                        $display_details = getProductDisplayDetails($pneu); // New comprehensive function
                        $taille_parsed = parseTireSize($pneu['taille']); // Updated

                        // $is_xl and $is_runflat are now part of $display_details['badge_html'] logic or parseProductSpecifications()
                        // $badge_text logic is now handled by getProductDisplayDetails()
                    ?>
                    <div class="product-detail-layout">
                        <div class="product-detail-image-column">
                            <div class="product-image-gallery" data-aos="fade-right">
                                <?php echo $display_details['badge_html']; // Use badge from new function ?>
                                <img src="<?php echo sanitize_html_output(!empty($pneu['image']) ? $pneu['image'] : 'https://placehold.co/600x500/121212/ffdd03?text=Image+Indisponible'); ?>" alt="<?php echo sanitize_html_output($pneu['nom']); ?>" id="main-product-image">
                                <!-- La section des miniatures a été supprimée -->
                            </div>
                        </div>

                        <div class="product-detail-info-column" data-aos="fade-left" data-aos-delay="100">
                            <h1 class="product-title" id="product-detail-name"><?php echo sanitize_html_output($pneu['nom']); ?></h1>

                            <div class="product-meta-info">
                                <span class="meta-brand"><i class="fas fa-tag"></i> Marque: <strong id="product-detail-brand"><?php echo sanitize_html_output($marque); ?></strong></span>
                                <span class="meta-availability <?php echo sanitize_html_output($display_details['stock_class']); ?>"><i class="fas fa-check-circle"></i> Disponibilité: <strong id="product-detail-availability"><?php echo sanitize_html_output($display_details['stock_text']); ?></strong></span>
                            </div>

                            <p class="product-price" id="product-detail-price"><?php echo sanitize_html_output($pneu['prix']); ?></p>

                            <div class="product-short-description">
                                <p><?php echo nl2br(sanitize_html_output($pneu['description'] ?? 'Aucune description courte disponible.')); ?></p>
                            </div>

                        <form method="post" action="panier.php" class="product-actions-form product-actions">
                            <input type="hidden" name="id_produit" value="<?php echo $pneu['id']; ?>">
                            <input type="hidden" name="action" value="ajouter">
                            <div class="quantity-selector">
                                <label for="quantity-<?php echo $pneu['id']; ?>" class="sr-only">Quantité:</label>
                                <button type="button" class="quantity-btn minus" aria-label="Diminuer la quantité">-</button>
                                <input type="number" id="quantity-<?php echo $pneu['id']; ?>" name="quantite" value="1" min="1" max="<?php echo max(1, (int)($pneu['stock_disponible'] ?? 10)); ?>">
                                <button type="button" class="quantity-btn plus" aria-label="Augmenter la quantité">+</button>
                            </div>
                            <button type="submit" class="cta-button add-to-cart-button">
                                <i class="fas fa-shopping-cart"></i> Ajouter au Panier
                            </button>
                        </form>

                            <div class="product-extra-info">
                                <span><i class="fas fa-truck"></i> Livraison rapide</span>
                                <span><i class="fas fa-undo-alt"></i> Retours faciles</span>
                                <span><i class="fas fa-shield-alt"></i> Garantie constructeur</span>
                            </div>

                            <div class="accordion-container">
                                <div class="accordion-item">
                                    <button class="accordion-header" aria-expanded="true" aria-controls="desc-content">
                                        Description Détaillée
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </button>
                                    <div id="desc-content" class="accordion-content" style="max-height: fit-content;">
                                        <p id="product-detail-description">
                                            <?php echo nl2br(sanitize_html_output(!empty($pneu['descriptionComplete']) ? $pneu['descriptionComplete'] : ($pneu['description'] ?? 'Aucune description détaillée disponible.'))); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <button class="accordion-header" aria-expanded="false" aria-controls="specs-content">
                                        Caractéristiques Techniques
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </button>
                                    <div id="specs-content" class="accordion-content">
                                        <?php $parsed_specs = parseProductSpecifications($pneu['specifications']); ?>
                                        <ul id="product-detail-specs-list">
                                            <li><span>Dimension:</span> <strong class="spec-value"><?php echo sanitize_html_output($taille_parsed['dimension_base']); ?></strong></li>
                                            <?php if(!empty($taille_parsed['indice_charge'])): ?>
                                                <li><span>Indice de charge:</span> <strong class="spec-value"><?php echo sanitize_html_output($taille_parsed['indice_charge']); ?></strong></li>
                                            <?php endif; ?>
                                            <?php if(!empty($taille_parsed['indice_vitesse'])): ?>
                                                <li><span>Indice de vitesse:</span> <strong class="spec-value"><?php echo sanitize_html_output($taille_parsed['indice_vitesse']); ?></strong></li>
                                            <?php endif; ?>
                                            <li><span>Saison:</span> <strong class="spec-value"><?php echo sanitize_html_output($pneu['saison']); ?></strong></li>
                                            <li><span>Runflat:</span> <strong class="spec-value"><?php echo $parsed_specs['is_runflat'] ? 'Oui' : 'Non'; ?></strong></li>
                                            <li><span>Renforcé (XL):</span> <strong class="spec-value"><?php echo $parsed_specs['is_reinforced'] ? 'Oui' : 'Non'; ?></strong></li>
                                            <?php if(!empty($pneu['decibels'])): ?>
                                                <li><span>Bruit de roulement:</span> <strong class="spec-value"><?php echo sanitize_html_output($pneu['decibels']); ?></strong></li>
                                            <?php endif; ?>
                                            <?php if(!empty($pneu['adherenceRouillee'])): ?>
                                                <li><span>Adhérence sol mouillé:</span> <strong class="spec-value"><?php echo sanitize_html_output(strtoupper($pneu['adherenceRouillee'])); ?></strong></li>
                                            <?php endif; ?>
                                            <!-- Ajouter d'autres specs si disponibles dans la BDD -->
                                        </ul>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <button class="accordion-header" aria-expanded="false" aria-controls="reviews-content">
                                        Avis Clients (0) <!-- À dynamiser plus tard -->
                                        <i class="fas fa-chevron-down accordion-icon"></i>
                                    </button>
                                    <div id="reviews-content" class="accordion-content">
                                        <p><em>Section des avis clients (à venir).</em></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($error_message): ?>
                    <p class="error-message" style="text-align: center; color: var(--accent-primary); font-size: 1.2rem;"><?php echo htmlspecialchars($error_message); ?></p>
                <?php else: ?>
                    <p style="text-align: center;">Chargement des informations du produit...</p>
                <?php endif; ?>

                <!-- Section Produits Similaires (statique pour l'instant) -->
                <?php if ($pneu): // Afficher seulement si un produit principal est chargé ?>
                <section id="related-products" class="related-products-section section-padding" data-aos="fade-up">
                    <h2 class="section-title">Produits Similaires</h2>
                    <div class="product-grid">
                        </div>
                </section>
                <?php endif; ?>
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
                        <li><a href="dashboard.php">Mon Compte</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Informations</h3>
                    <ul>
                        <li><a href="legal-notice.html">Mentions Légales</a></li>
                        <li><a href="privacy-policy.html">Politique de Confidentialité</a></li>
                        <li><a href="cgv.html">Conditions Générales de Vente</a></li>
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
