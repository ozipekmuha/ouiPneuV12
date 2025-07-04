<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php'; // Connexion à la base de données
require_once 'includes/functions.php'; // Include centralized functions

// Récupération des filtres GET initiaux
$where_clauses = ["est_actif = TRUE"];
$bindings = [];
$active_filters_for_display = [];

$qs_width = filter_input(INPUT_GET, 'width', FILTER_SANITIZE_SPECIAL_CHARS);
$qs_ratio = filter_input(INPUT_GET, 'ratio', FILTER_SANITIZE_SPECIAL_CHARS);
$qs_diameter = filter_input(INPUT_GET, 'diameter', FILTER_SANITIZE_SPECIAL_CHARS);
$qs_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_SPECIAL_CHARS); // Saison

if ($qs_width) {
    $active_filters_for_display['Largeur'] = $qs_width;
    $where_clauses[] = "taille LIKE :width_pattern";
    $bindings[':width_pattern'] = $qs_width . "/%";
}
if ($qs_ratio) {
    $where_clauses[] = "taille LIKE :ratio_pattern";
    $bindings[':ratio_pattern'] = ($qs_width ? $qs_width : '%') . "/" . $qs_ratio . "%";
    $active_filters_for_display['Ratio'] = $qs_ratio;
}
if ($qs_diameter) {
    $where_clauses[] = "taille LIKE :diameter_pattern";
    $bindings[':diameter_pattern'] = "%R" . $qs_diameter . "%";
    $active_filters_for_display['Diamètre'] = 'R' . $qs_diameter;
}
if ($qs_type) {
    $where_clauses[] = "saison = :saison";
    $bindings[':saison'] = $qs_type;
    $active_filters_for_display['Saison'] = $qs_type;
}

// --- Pagination Setup ---
$produits_par_page = 24; // Nombre de produits à afficher par page
$page_actuelle = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

// --- Récupération du nombre total de produits pour la pagination (avec filtres) ---
$sql_count = "SELECT COUNT(*) FROM Pneus";
if (!empty($where_clauses)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_clauses);
}

try {
    $stmt_count = $pdo->prepare($sql_count);
    if (!empty($bindings)) {
        $stmt_count->execute($bindings);
    } else {
        $stmt_count->execute();
    }
    $total_produits = (int)$stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur PDO lors du comptage des pneus filtrés : " . $e->getMessage() . " SQL: " . $sql_count . " Bindings: " . print_r($bindings, true));
    $total_produits = 0;
}

$total_pages = ($total_produits > 0) ? (int)ceil($total_produits / $produits_par_page) : 1;

if ($page_actuelle > $total_pages) {
    $page_actuelle = $total_pages;
}
if ($page_actuelle < 1) {
    $page_actuelle = 1;
}

$offset = ($page_actuelle - 1) * $produits_par_page;
// --- Fin Pagination Setup ---


// --- Récupération des pneus pour la page actuelle ---
$sql_produits = "SELECT id, nom, taille, saison, image, decibels, adherenceRouillee, specifications, prix, lienProduit, description, stock_disponible FROM Pneus";
if (!empty($where_clauses)) {
    $sql_produits .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_produits .= " ORDER BY nom ASC";
$sql_produits .= " LIMIT :limit OFFSET :offset";

$pneus = [];
try {
    $stmt_produits = $pdo->prepare($sql_produits);
    if (!empty($bindings)) {
        foreach ($bindings as $key => $value) {
            $stmt_produits->bindValue($key, $value);
        }
    }
    $stmt_produits->bindValue(':limit', $produits_par_page, PDO::PARAM_INT);
    $stmt_produits->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt_produits->execute();
    $pneus = $stmt_produits->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des pneus paginés : " . $e->getMessage() . " SQL: " . $sql_produits . " Bindings: " . print_r($bindings, true) . " Limit: $produits_par_page Offset: $offset");
    // $pneus reste un tableau vide
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Pneus - Ouipneu.fr</title>
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
                <a href="index.php"><img src="images/logo-placeholder-dark.png" alt="Logo Ouipneu.fr"></a>
            </div>
            <nav class="main-nav">
                <ul id="main-nav-links">
                    <li><a href="index.php" aria-label="Accueil">Accueil</a></li>
                    <li><a href="produits.php" class="active" aria-label="Nos Pneus">Nos Pneus</a></li>
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

    <main class="site-main-content">
        <section id="all-products" class="all-products-section section-padding">
            <div class="container" style="padding: 0 0.75rem;">
                <h1 class="page-title" data-aos="fade-up">Tous Nos Pneus</h1>
                <p class="section-intro product-page-intro" data-aos="fade-up" data-aos-delay="50">
                    Explorez notre catalogue complet de pneus. Utilisez les filtres pour affiner votre recherche et trouver le pneu parfait pour votre véhicule, quelle que soit la saison ou votre style de conduite.
                </p>

                <div class="product-search-and-filter-bar" data-aos="fade-up" data-aos-delay="100">
                    <form class="product-search-form" role="search">
                        <input type="search" id="product-search-input" placeholder="Rechercher un pneu par nom, marque..." aria-label="Rechercher un pneu">
                        <button type="submit" aria-label="Lancer la recherche de produit"><i class="fas fa-search"></i></button>
                    </form>
                    <button type="button" id="open-filters-panel" class="cta-button secondary">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                </div>

                <aside id="filters-panel" class="filters-off-canvas-panel" aria-labelledby="filters-panel-title">
                    <div class="filters-panel-header">
                        <h2 id="filters-panel-title" class="filters-panel-title">Filtres Avancés</h2>
                        <button id="close-filters-panel" class="close-filters-button" aria-label="Fermer le panneau de filtres">&times;</button>
                    </div>
                    <div class="filters-panel-body">
                        <form id="product-filters-form">
                            <div class="filter-group tire-dimensions-group">
                                <h3 class="filter-group-main-title">Dimensions du Pneu</h3>
                                <div class="tire-size-inputs-row">
                                    <div class="filter-sub-group">
                                        <label for="filter-width">Largeur:</label>
                                        <select id="filter-width" name="width" aria-label="Largeur du pneu">
                                            <option value="">Tout</option>
                                        </select>
                                    </div>
                                    <div class="filter-sub-group">
                                        <label for="filter-ratio">Ratio:</label>
                                        <select id="filter-ratio" name="ratio" aria-label="Ratio du pneu">
                                            <option value="">Tout</option>
                                        </select>
                                    </div>
                                    <div class="filter-sub-group">
                                        <label for="filter-diameter">Diamètre:</label>
                                        <select id="filter-diameter" name="diameter" aria-label="Diamètre du pneu">
                                            <option value="">Tout</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="filter-group brand-filter-group">
                                <label for="filter-brand" class="filter-group-main-title">Marque:</label>
                                <select id="filter-brand" name="brand" aria-label="Marque du pneu">
                                    <option value="">Toutes</option>
                                </select>
                            </div>

                            <div class="filter-group type-filter-group">
                                <label for="filter-type" class="filter-group-main-title">Saison:</label>
                                <select id="filter-type" name="type" aria-label="Type de pneu">
                                    <option value="">Tous</option>
                                    <option value="Été">Été</option>
                                    <option value="Hiver">Hiver</option>
                                    <option value="Toutes Saisons">Toutes Saisons</option>
                                </select>
                            </div>

                            <div class="filter-group features-filter-group">
                                <h3 class="filter-group-main-title">Caractéristiques:</h3>
                                <div class="checkbox-option">
                                    <input type="checkbox" id="filter-runflat" name="runflat" value="true">
                                    <label for="filter-runflat">Runflat</label>
                                </div>
                                <div class="checkbox-option">
                                    <input type="checkbox" id="filter-reinforced" name="reinforced" value="true">
                                    <label for="filter-reinforced">Pneu Renforcé (XL)</label>
                                </div>
                            </div>

                            <div class="filter-actions">
                                <button type="button" id="reset-filters-button" class="cta-button secondary">
                                    <i class="fas fa-undo"></i> Réinitialiser
                                </button>
                                <button type="button" id="apply-filters-button" class="cta-button">Appliquer les Filtres</button>
                            </div>
                        </form>
                    </div>
                </aside>
                <div id="filters-overlay" class="filters-panel-overlay"></div>

                <div class="product-list-controls" data-aos="fade-up" data-aos-delay="150">
                    <div class="product-count">
                        <span id="product-results-count"><?php echo $total_produits; ?></span> produits trouvés <!-- Utiliser $total_produits ici -->
                    </div>
                    <div class="sort-options">
                        <label for="sort-by" class="sr-only">Trier par:</label>
                        <select id="sort-by" name="sort_by" aria-label="Trier les produits par">
                            <option value="relevance">Pertinence</option>
                            <option value="price-asc">Prix: Croissant</option>
                            <option value="price-desc">Prix: Décroissant</option>
                            <option value="name-asc">Nom: A-Z</option>
                        </select>
                    </div>
                </div>

                <?php if (!empty($active_filters_for_display)): ?>
                    <div class="active-search-criteria" data-aos="fade-up" data-aos-delay="200">
                        <p style="margin-bottom: 0.5rem; font-weight: var(--font-weight-medium); color: var(--text-light);">Filtres actifs :</p>
                        <?php foreach ($active_filters_for_display as $label => $valeur): ?>
                            <span class="filter-tag"><?php echo sanitize_html_output($label) . ': ' . sanitize_html_output($valeur); ?></span>
                        <?php endforeach; ?>
                        <a href="produits.php" class="clear-filters-link">(Effacer les filtres)</a>
                    </div>
                <?php endif; ?>

                <div class="product-grid">
                    <?php if (empty($pneus)): ?>
                        <p>Aucun pneu trouvé pour les critères sélectionnés.</p>
                    <?php else: ?>
                        <?php foreach ($pneus as $index => $pneu): ?>
                            <?php
                                $parsed_specs_for_data = parseProductSpecifications($pneu['specifications']);
                                $display_details = getProductDisplayDetails($pneu);
                                $marque_nom_pour_data = extractBrandFromName($pneu['nom']);
                                $taille_parsed = parseTireSize($pneu['taille']);
                                $prix_pour_data = convertPriceToFloat($pneu['prix']);
                            ?>
                            <div class="product-card"
                                 data-aos="fade-up"
                                 data-aos-duration="500"
                                 data-aos-delay="<?php echo ($index % 4 + 1) * 50; ?>"
                                 data-aos-once="true"
                                 data-runflat="<?php echo $parsed_specs_for_data['is_runflat'] ? 'true' : 'false'; ?>"
                                 data-reinforced="<?php echo $parsed_specs_for_data['is_reinforced'] ? 'true' : 'false'; ?>"
                                 data-brand="<?php echo sanitize_html_output($marque_nom_pour_data); ?>"
                                 data-name="<?php echo sanitize_html_output($pneu['nom']); ?>"
                                 data-price="<?php echo $prix_pour_data; ?>"
                                 data-type="<?php echo sanitize_html_output($pneu['saison']); ?>"
                                 data-width="<?php echo sanitize_html_output($taille_parsed['data_width']); ?>"
                                 data-ratio="<?php echo sanitize_html_output($taille_parsed['data_ratio']); ?>"
                                 data-diameter="<?php echo sanitize_html_output($taille_parsed['data_diameter']); ?>"
                                 >
                                <div class="product-image-placeholder">
                                    <img loading="lazy" src="<?php echo sanitize_html_output(!empty($pneu['image']) ? $pneu['image'] : 'https://placehold.co/400x300/121212/ffdd03?text=Image+Indisponible'); ?>" alt="<?php echo sanitize_html_output($pneu['nom']); ?>" width="400" height="300">
                                    <?php echo $display_details['badge_html']; ?>
                                </div>
                                <div class="product-card-content">
                                    <h3 class="product-name"><?php echo sanitize_html_output($pneu['nom']); ?></h3>
                                    <p class="product-brand">Marque: <?php echo sanitize_html_output($marque_nom_pour_data); ?></p>
                                    <p class="product-specs">
                                        <?php echo sanitize_html_output($pneu['taille']); ?> | <?php echo sanitize_html_output($pneu['saison']); ?>
                                        <?php if(!empty($pneu['specifications'])): 
                                            $specs_text_to_display = trim($pneu['specifications']);
                                            if (!empty($specs_text_to_display)) {
                                                echo ' | ' . sanitize_html_output($specs_text_to_display);
                                            }
                                        endif; ?>
                                    </p>
                                    <div class="product-price-stock">
                                        <p class="product-price"><?php echo sanitize_html_output($pneu['prix']); ?></p>
                                        <p class="product-stock <?php echo $display_details['stock_class']; ?>"><?php echo sanitize_html_output($display_details['stock_text']); ?></p>
                                    </div>
                                    <a href="produit.php?id=<?php echo $pneu['id']; ?>" class="cta-button product-cta secondary" aria-label="Voir détails pour <?php echo sanitize_html_output($pneu['nom']); ?>">Voir Détails</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="infinite-scroll-loader" style="display: none; text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--accent-primary);"></i>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">Chargement de plus de pneus...</p>
                </div>

                <!-- Pagination Links -->
                <?php if ($total_pages > 1): ?>
                <nav class="pagination-container" aria-label="Pagination des produits">
                    <ul class="pagination">
                        <?php
                        // Conserver les paramètres GET existants pour les liens de pagination
                        $query_params = $_GET;
                        unset($query_params['page']); // Retirer l'ancien paramètre de page
                        $base_query_string = http_build_query($query_params);
                        if (!empty($base_query_string)) {
                            $base_query_string .= '&';
                        }

                        if ($page_actuelle > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?php echo $base_query_string; ?>page=<?php echo $page_actuelle - 1; ?>">Précédent</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Précédent</span></li>
                        <?php endif; ?>

                        <?php 
                        $num_links_visibles = 2; 
                        $start_page = max(1, $page_actuelle - $num_links_visibles);
                        $end_page = min($total_pages, $page_actuelle + $num_links_visibles);

                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . $base_query_string . 'page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php if ($i == $page_actuelle) echo 'active'; ?>">
                                <a class="page-link" href="?<?php echo $base_query_string; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . $base_query_string . 'page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        
                        if ($page_actuelle < $total_pages): ?>
                            <li class="page-item"><a class="page-link" href="?<?php echo $base_query_string; ?>page=<?php echo $page_actuelle + 1; ?>">Suivant</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Suivant</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <!-- Fin Pagination Links -->
            </div>
        </section>
    </main>

    <footer id="main-footer">
            <div class="container" style="padding: 0 0.75rem;">
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
