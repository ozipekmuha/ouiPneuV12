<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Include centralized functions

// Récupération des pneus pour "Nos Meilleures Ventes" (ex: 4 premiers produits actifs)
$best_sellers = [];
try {
    $stmt_bs = $pdo->query("SELECT id, nom, taille, saison, image, specifications, prix, stock_disponible FROM Pneus WHERE est_actif = TRUE ORDER BY id ASC LIMIT 4");
    $best_sellers = $stmt_bs->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des meilleures ventes : " . $e->getMessage());
}

// Récupération des pneus pour "Nouveautés" (ex: 3 produits suivants actifs, ou par date si disponible)
// Pour cet exemple, je prends les 3 suivants après les 4 meilleures ventes.
$new_arrivals = [];
try {
    // Si vous avez un champ comme 'date_creation' dans la table Pneus, utilisez-le:
    // $stmt_na = $pdo->query("SELECT id, nom, taille, saison, image, specifications, prix, stock_disponible FROM Pneus WHERE est_actif = TRUE ORDER BY date_creation DESC LIMIT 3");
    // Sinon, pour l'exemple, on prend des produits différents des best-sellers :
    $ids_best_sellers = array_map(function($p) { return $p['id']; }, $best_sellers);
    $na_query = "SELECT id, nom, taille, saison, image, specifications, prix, stock_disponible FROM Pneus WHERE est_actif = TRUE";
    if (!empty($ids_best_sellers)) {
        $na_query .= " AND id NOT IN (" . implode(',', array_fill(0, count($ids_best_sellers), '?')) . ")";
    }
    $na_query .= " ORDER BY id DESC LIMIT 3"; // Autre tri pour la nouveauté

    $stmt_na = $pdo->prepare($na_query);
    if (!empty($ids_best_sellers)) {
        $stmt_na->execute($ids_best_sellers);
    } else {
        $stmt_na->execute();
    }
    $new_arrivals = $stmt_na->fetchAll();

} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des nouveautés : " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magasin de Pneus - Pneus et Services Premium</title>
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
                    <li><a href="index.php" class="active" aria-label="Accueil">Accueil</a></li>
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
                <button id="hamburger-button" class="hamburger-button" aria-label="Ouvrir le menu" aria-expanded="false">
                    <span class="hamburger-box">
                        <span class="hamburger-inner"></span>
                    </span>
                </button>
            </div>
        </div>
    </header>

    <div id="global-promo-banner" style="background-color: var(--accent-primary); color: var(--text-on-accent); text-align: center; padding: 0.75rem 1rem; font-size: 0.9rem; font-weight: 500;">
        <p style="margin:0;">🔥 Livraison Gratuite sur toutes les commandes de plus de 150€ ! 🔥</p>
    </div>
<!-- 
    <section id="home" class="hero-section section-padding">
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Votre Destination Ultime pour des Pneus de Qualité</h1>
                <p class="hero-subtitle">Découvrez une large gamme de pneus adaptés à tous les véhicules et budgets. Performance, sécurité et les meilleures marques vous attendent.</p>
                <div class="hero-cta-buttons">
                    <a href="produits.php" class="cta-button hero-cta-primary">Trouver Vos Pneus</a>
                    <a href="#our-advantages" class="cta-button hero-cta-secondary">Nos Services</a>
                </div>
            </div>
        </div>
    </section> -->
    <style>
        .hero-section {
    position: relative;
    overflow: hidden;
    background-color: #000; /* fallback */
}

.hero-bg-video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6); /* noir semi-transparent */
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 2;
}
        /* Hero CTA Secondary Button - unified style */
        .cta-button.hero-cta-secondary {
            border: 2px solid #FFD700;
            color: #FFD700;
            background-color: transparent;
            font-weight: 600;
            padding: 1rem 2rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .cta-button.hero-cta-secondary:hover {
            background-color: #FFD700;
            color: #000;
        }
    </style>
    <section id="home" class="hero-section section-padding">
    <video class="hero-bg-video" autoplay muted loop playsinline>
        <source src="./assets/video.mp4" type="video/mp4">
        Votre navigateur ne supporte pas la vidéo HTML5.
    </video>
    <div class="hero-overlay"></div>
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Votre Destination Ultime pour des Pneus de Qualité</h1>
            <p class="hero-subtitle">Découvrez une large gamme de pneus adaptés à tous les véhicules et budgets. Performance, sécurité et les meilleures marques vous attendent.</p>
            <div class="hero-cta-buttons">
                <a href="produits.php" class="cta-button hero-cta-primary">Trouver Vos Pneus</a>
                <a href="#our-advantages" class="cta-button hero-cta-secondary">Nos Services</a>
            </div>
        </div>
    </div>
</section>

    <section id="quick-filter-section" class="section-padding">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Trouvez vos Pneus Rapidement</h2>
            <form id="quick-filter-form" class="quick-filter-form" data-aos="fade-up" data-aos-delay="100">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="qf-width">Largeur</label>
                        <select id="qf-width" name="width">
                            <option value="">Largeur</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="qf-ratio">Hauteur</label>
                        <select id="qf-ratio" name="ratio">
                            <option value="">Hauteur</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="qf-diameter">Diamètre</label>
                        <select id="qf-diameter" name="diameter">
                            <option value="">Diamètre</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="qf-type">Saison</label>
                        <select id="qf-type" name="type">
                            <option value="">Saison</option>
                            <option value="Été">Été</option>
                            <option value="Hiver">Hiver</option>
                            <option value="Toutes Saisons">Toutes Saisons</option>
                        </select>
                    </div>
                    <!-- Champs supplémentaires -->
                    <div class="filter-group">
                        <label for="charge">Charge</label>
                        <select name="charge" id="charge">
                            <option value="">Toutes</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="vitesse">Vitesse</label>
                        <select name="vitesse" id="vitesse">
                            <option value="">Toutes</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="marque">Marque</label>
                        <select name="marque" id="marque">
                            <option value="">Toutes</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="specificite">Spécificité</label>
                        <select name="specificite" id="specificite">
                            <option value="">Spécificité</option>
                        </select>
                    </div>
                    <div class="form-group checkbox-group">
                        <label><input type="checkbox" name="runflat"> Runflat</label>
                        <label><input type="checkbox" name="renforce"> Renforcé</label>
                    </div>
                </div>
                <button type="submit" class="cta-button qf-submit-button">Rechercher Pneus</button>
            </form>
        </div>
    </section>

    <main class="site-main-content">
        <section id="how-it-works" class="how-it-works-section section-padding">
            <div class="container">
            <h2 class="section-title" data-aos="fade-up">Comment Ça Marche</h2>
                <div class="steps-container">
                    <div class="step" data-aos="fade-up" data-aos-delay="100">
                        <div class="step-number-container">
                            <span class="step-number">01</span>
                        </div>
                        <div class="step-icon-container">
                            <i class="fas fa-shopping-cart fa-2x"></i>
                        </div>
                        <h3 class="step-title">Commandez vos pneus</h3>
                        <p class="step-description">Parcourez notre sélection et passez commande en quelques clics.</p>
                    </div>
                    <div class="step" data-aos="fade-up" data-aos-delay="250">
                        <div class="step-number-container">
                            <span class="step-number">02</span>
                        </div>
                        <div class="step-icon-container">
                            <i class="fas fa-truck fa-2x"></i>
                        </div>
                        <h3 class="step-title">Recevez-les rapidement</h3>
                        <p class="step-description">Livraison directe à votre domicile ou garage partenaire.</p>
                    </div>
                    <div class="step" data-aos="fade-up" data-aos-delay="400">
                        <div class="step-number-container">
                            <span class="step-number">03</span>
                        </div>
                        <div class="step-icon-container">
                            <i class="fas fa-tools fa-2x"></i>
                        </div>
                        <h3 class="step-title">Montage Facilité</h3>
                        <p class="step-description">Choisissez le montage à domicile ou chez l'un de nos partenaires agréés.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="best-sellers" class="best-sellers-section section-padding">
            <div class="container">
            <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">Nos Meilleures Ventes</h2>
                <div class="product-grid">
                    <?php if (!empty($best_sellers)): ?>
                        <?php foreach ($best_sellers as $index => $pneu): ?>
                            <?php
                                $marque = extractBrandFromName($pneu['nom']); // Updated
                                // $stock_details = getProductStockStatus($pneu['stock_disponible']); // Updated - Combined into getProductDisplayDetails
                                $display_details = getProductDisplayDetails($pneu); // New function call
                            ?>
                            <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo ($index % 4 + 1) * 50; ?>">
                                <div class="product-image-placeholder">
                                    <img loading="lazy" width="400" height="300" src="<?php echo sanitize_html_output(!empty($pneu['image']) ? $pneu['image'] : 'https://placehold.co/400x300/1e1e1e/ffdd03?text=Image+Pneu'); ?>" alt="<?php echo sanitize_html_output($pneu['nom']); ?>">
                                    <?php echo $display_details['badge_html']; // Use badge from new function ?>
                                </div>
                                <div class="product-card-content">
                                    <h3 class="product-name"><?php echo sanitize_html_output($pneu['nom']); ?></h3>
                                    <p class="product-brand">Marque: <?php echo sanitize_html_output($marque); ?></p>
                                    <p class="product-specs"><?php echo sanitize_html_output($pneu['taille']); ?> | <?php echo sanitize_html_output($pneu['saison']); ?></p>
                                    <div class="product-price-stock">
                                        <p class="product-price"><?php echo sanitize_html_output($pneu['prix']); ?></p>
                                        <p class="product-stock <?php echo $display_details['stock_class']; ?>"><?php echo sanitize_html_output($display_details['stock_text']); ?></p>
                                    </div>
                                    <a href="produit.php?id=<?php echo $pneu['id']; ?>" class="cta-button product-cta secondary">Voir Détails</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aucun produit à afficher dans les meilleures ventes pour le moment.</p>
                    <?php endif; ?>
                </div>
                <div class="section-cta-container">
                    <a href="produits.php" class="cta-button">Voir Tous Nos Pneus</a>
                </div>
            </div>
        </section>

        <section id="trusted-brands" class="trusted-brands-section section-padding">
            </section>

        <section id="our-brands" class="our-brands-section section-padding">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up">Nos Marques Partenaires</h2>
                <p class="section-intro" data-aos="fade-up" data-aos-delay="100">
                    Nous collaborons avec les plus grandes marques de pneumatiques pour vous garantir qualité, sécurité et performance. Classées par gamme pour répondre à tous vos besoins.
                </p>

                <div class="brand-tier" data-aos="fade-up" data-aos-delay="200">
                    <h3 class="brand-tier-title">Premium</h3>
                    <div class="swiper brand-swiper">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/michelin.png" alt="Michelin" loading="lazy">
                                <span>Michelin</span>
                            </div>
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/continental.png" alt="Continental" loading="lazy">
                                <span>Continental</span>
                            </div>
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/bridgeston.webp" alt="Bridgestone" loading="lazy">
                                <span>Bridgestone</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="brand-tier" data-aos="fade-up" data-aos-delay="300">
                    <h3 class="brand-tier-title">Confort & Performance</h3>
                    <div class="swiper brand-swiper">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/goodyear.png" alt="Goodyear" loading="lazy">
                                <span>Goodyear</span>
                            </div>
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/dunlop2.png" alt="Dunlop" loading="lazy">
                                <span>Dunlop</span>
                            </div>
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/kanhook.webp" alt="Hankook" loading="lazy">
                                <span>Hankook</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="brand-tier" data-aos="fade-up" data-aos-delay="400">
                    <h3 class="brand-tier-title">Économique</h3>
                    <div class="swiper brand-swiper">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/nexen.png" alt="Nexen" loading="lazy">
                                <span>Nexen</span>
                            </div>
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/kuhmo.png" alt="Kumho" loading="lazy">
                                <span>Kumho</span>
                            </div>
                            <div class="swiper-slide brand-card">
                                <img src="./assets/images/icone/kuhmo.png" alt="Kumho" loading="lazy">
                                <span>Kumho</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="new-arrivals" class="new-arrivals-section section-padding">
            <div class="container">
            <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">Nouveautés</h2>
                <div class="product-grid">
                    <?php if (!empty($new_arrivals)): ?>
                        <?php foreach ($new_arrivals as $index => $pneu): // Added $index here, assuming it might be missing from original new_arrivals loop ?>
                            <?php
                                $marque = extractBrandFromName($pneu['nom']); // Updated
                                // $stock_details = getProductStockStatus($pneu['stock_disponible']); // Updated - Combined into getProductDisplayDetails
                                $display_details = getProductDisplayDetails($pneu); // New function call
                            ?>
                             <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo ($index % 3 + 1) * 50; ?>">
                                <div class="product-image-placeholder">
                                     <img loading="lazy" width="400" height="300" src="<?php echo sanitize_html_output(!empty($pneu['image']) ? $pneu['image'] : 'https://placehold.co/400x300/1e1e1e/ffdd03?text=Nouveau+Pneu'); ?>" alt="<?php echo sanitize_html_output($pneu['nom']); ?>">
                                     <?php echo $display_details['badge_html']; // Use badge from new function, might need adjustment if "Nouveau" is a separate logic ?>
                                     <!-- If "Nouveau" badge logic is specific to this section and not covered by getProductDisplayDetails, it can be added here -->
                                     <!-- Example: <span class="product-badge new">Nouveau</span> -->
                                </div>
                                <div class="product-card-content">
                                    <h3 class="product-name"><?php echo sanitize_html_output($pneu['nom']); ?></h3>
                                    <p class="product-brand">Marque: <?php echo sanitize_html_output($marque); ?></p>
                                    <p class="product-specs"><?php echo sanitize_html_output($pneu['taille']); ?> | <?php echo sanitize_html_output($pneu['saison']); ?></p>
                                    <div class="product-price-stock">
                                        <p class="product-price"><?php echo sanitize_html_output($pneu['prix']); ?></p>
                                        <p class="product-stock <?php echo $display_details['stock_class']; ?>"><?php echo sanitize_html_output($display_details['stock_text']); ?></p>
                                    </div>
                                    <a href="produit.php?id=<?php echo $pneu['id']; ?>" class="cta-button product-cta secondary">Voir Détails</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Pas de nouveautés à afficher pour le moment.</p>
                    <?php endif; ?>
                </div>
                <div class="section-cta-container">
                    <a href="produits.php" class="cta-button">Découvrir Toutes les Nouveautés</a>
                </div>
            </div>
        </section>

        <section id="promotions" class="promotions-section section-padding">
            <div class="container">
            <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">Offres Spéciales</h2>
                <div class="promotion-grid">
                    <div class="promotion-card">
                    <h3>Soldes de Printemps - 20% sur Pirelli</h3>
                    <p>Réductions sur tous les modèles Pirelli. Préparez-vous pour la saison !</p>
                    <a href="#" class="cta-button promo-cta">Acheter Soldes Pirelli</a>
                    </div>
                    <div class="promotion-card">
                    <h3>Bridgestone: 3 Achetés = 1 Offert</h3>
                    <p>Offre à durée limitée sur une sélection de pneus Bridgestone. Ne manquez pas ça !</p>
                    <a href="#" class="cta-button promo-cta">Découvrir Offres Bridgestone</a>
                    </div>
                    <div class="promotion-card">
                    <h3>Parallélisme Offert avec un jeu Dunlop</h3>
                    <p>Achetez 4 pneus Dunlop et obtenez un service de parallélisme gratuit.</p>
                    <a href="#" class="cta-button promo-cta">Voir Offre Dunlop</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="testimonials" class="testimonials-section section-padding">
            <div class="container">
            <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">Ce Que Disent Nos Clients</h2>
            <div class="testimonial-carousel-wrapper">
                <div id="testimonial-carousel" class="testimonial-carousel-placeholder">
                    <div class="testimonial-slide" data-aos="fade-up" data-aos-delay="100">
                        <div class="testimonial-quote-icon">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="testimonial-quote">"Service incroyable et pneus de haute qualité ! J'ai trouvé exactement ce dont j'avais besoin pour mon SUV, et le montage a été rapide et professionnel. Je recommande vivement !"</p>
                        <p class="testimonial-author">- Alexandre P.</p>
                    </div>
                    <div class="testimonial-slide" data-aos="fade-up" data-aos-delay="200">
                        <div class="testimonial-quote-icon">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="testimonial-quote">"Très satisfait de mon achat. Livraison rapide et excellent service client. Les conseils pour choisir mes pneus étaient parfaits."</p>
                        <p class="testimonial-author">- Marie L.</p>
                    </div>
                    <div class="testimonial-slide" data-aos="fade-up" data-aos-delay="300">
                        <div class="testimonial-quote-icon">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="testimonial-quote">"Les meilleurs prix que j'ai pu trouver en ligne et une navigation facile sur le site. Le processus de commande était simple et clair."</p>
                        <p class="testimonial-author">- Jean D.</p>
                    </div>
                </div>
                <div class="testimonial-nav-buttons">
                    <button id="testimonial-prev" class="testimonial-nav-button prev" aria-label="Avis précédent">&lt;</button>
                    <button id="testimonial-next" class="testimonial-nav-button next" aria-label="Avis suivant">&gt;</button>
                    </div>
                </div>
            </div>
        </section>

        <section id="our-advantages" class="our-advantages-section section-padding section-animate">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">Pourquoi choisir Ouipneu.fr ?</h2>
                <p class="section-intro">Chez Ouipneu.fr, nous nous engageons à vous offrir la meilleure expérience d'achat de pneus en ligne, avec des avantages qui font la différence.</p>
                <div class="advantages-grid">
                    <div class="advantage-item">
                        <div class="advantage-icon-container">
                            <i class="fas fa-layer-group fa-2x"></i>
                        </div>
                        <h3 class="advantage-title">Large Choix de Pneus</h3>
                        <p class="advantage-description">Des milliers de références parmi les plus grandes marques pour tous les véhicules et budgets.</p>
                    </div>
                    <div class="advantage-item">
                        <div class="advantage-icon-container">
                            <i class="fas fa-shipping-fast fa-2x"></i>
                        </div>
                        <h3 class="advantage-title">Livraison Rapide et Flexible</h3>
                        <p class="advantage-description">Recevez vos pneus chez vous ou dans l'un de nos centres de montage partenaires, selon votre convenance.</p>
                    </div>
                    <div class="advantage-item">
                        <div class="advantage-icon-container">
                            <i class="fas fa-shield-alt fa-2x"></i>
                        </div>
                        <h3 class="advantage-title">Retour Facile & Garantie Constructeur</h3>
                        <p class="advantage-description">Achetez en toute confiance avec notre politique de retour simplifié et la garantie fabricant préservée.</p>
                    </div>
                    <div class="advantage-item">
                        <div class="advantage-icon-container">
                            <i class="fas fa-headset fa-2x"></i>
                        </div>
                        <h3 class="advantage-title">Service Client Réactif & Conseil d'Expert</h3>
                        <p class="advantage-description">Notre équipe est à votre écoute pour vous guider et répondre à toutes vos questions techniques.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about-us" class="about-us-section section-padding section-animate">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">À Propos de Nous</h2>
                <div class="about-us-content">
                    <div class="about-us-text">
                        <p>Bienvenue chez Ouipneu.fr, votre partenaire de confiance pour l'achat de pneus en ligne. Depuis notre création, nous nous engageons à offrir à nos clients une expérience d'achat simple, rapide et sécurisée, avec un catalogue complet de pneus pour tous types de véhicules et tous budgets.</p>
                        <p>Notre mission est de vous fournir non seulement des produits de qualité supérieure provenant des meilleures marques, mais aussi un service client exceptionnel. Notre équipe d'experts est toujours prête à vous conseiller et à vous aider à trouver les pneus parfaitement adaptés à vos besoins et à votre style de conduite. Nous croyons en la transparence, des prix compétitifs et une livraison efficace pour garantir votre satisfaction totale.</p>
                        <p>Merci de faire confiance à Ouipneu.fr pour la sécurité et la performance de votre véhicule.</p>
                    </div>
                    <div class="about-us-image-container">
                        <img loading="lazy" width="500" height="350" src="./assets/images/logobg.png" alt="L'équipe Ouipneu.fr">
                    </div>
                </div>
            </div>
        </section>

        <section id="newsletter-signup" class="newsletter-section section-padding">
            <div class="container">
            <h2 class="section-title" data-aos="fade-up" data-aos-duration="700" data-aos-once="true">Restez Informé</h2>
            <p>Abonnez-vous à notre newsletter pour les dernières offres, conseils d'entretien des pneus et nouveautés.</p>
                <form class="newsletter-form">
                <input type="email" placeholder="Entrez votre adresse e-mail" required autocomplete="email">
                <button type="submit" class="cta-button">S'abonner</button>
                </form>
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

</body>
<!-- Swiper JS CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const swipers = document.querySelectorAll('.brand-swiper');
    swipers.forEach(container => {
      new Swiper(container, {
        slidesPerView: 1,
        spaceBetween: 20,
        breakpoints: {
          576: { slidesPerView: 2 },
          768: { slidesPerView: 3 },
          1024: { slidesPerView: 4 }
        },
        loop: true,
        autoplay: { delay: 2500, disableOnInteraction: false }
      });
    });
  });
</script>
<script src="https://unpkg.com/aos@next/dist/aos.js" defer></script>
<script src="js/main.js" defer></script>
</body>
</html>