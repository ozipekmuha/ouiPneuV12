<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Nos Garages Partenaires - Ouipneu.fr";

// Récupération des garages partenaires visibles
$garages_partenaires = [];
try {
    $stmt = $pdo->prepare("SELECT nom_garage, adresse_complete, telephone, email, services_offerts, description_courte, latitude, longitude, horaires_ouverture, url_website FROM GaragesPartenaires WHERE est_visible = TRUE ORDER BY nom_garage ASC");
    $stmt->execute();
    $garages_partenaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des garages partenaires : " . $e->getMessage());
    // Gérer l'erreur, peut-être afficher un message à l'utilisateur
}

// Convertir les données des garages en JSON pour Leaflet
$garages_json_for_map = [];
foreach ($garages_partenaires as $garage) {
    if (!empty($garage['latitude']) && !empty($garage['longitude'])) {
        $garages_json_for_map[] = [
            'nom' => $garage['nom_garage'],
            'lat' => (float)$garage['latitude'],
            'lon' => (float)$garage['longitude'],
            'adresse' => $garage['adresse_complete'],
            'tel' => $garage['telephone']
        ];
    }
}
$garages_leaflet_data = json_encode($garages_json_for_map);

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

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        #mapPartenaires { height: 450px; width: 100%; border-radius: var(--border-radius-medium); margin-bottom: 2.5rem; box-shadow: 0 4px 10px var(--shadow-color); border: 1px solid var(--border-color); }
        .garage-card-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .garage-card {
            background-color: var(--bg-surface);
            padding: 1.5rem;
            border-radius: var(--border-radius-medium);
            box-shadow: 0 4px 12px var(--shadow-color);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        .garage-card h3 {
            color: var(--accent-primary);
            font-size: 1.4rem;
            margin-bottom: 0.75rem;
        }
        .garage-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        .garage-card p strong {
            color: var(--text-light);
            font-weight: var(--font-weight-medium);
        }
        .garage-card .services-title {
            font-weight: var(--font-weight-semibold);
            color: var(--text-light);
            margin-top: 0.75rem;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        .garage-card ul.services-list {
            list-style: none;
            padding-left: 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .garage-card ul.services-list li {
            margin-bottom: 0.2rem;
            padding-left: 1em;
            text-indent: -1em;
        }
        .garage-card ul.services-list li::before {
            content: "✓ ";
            color: var(--accent-primary);
            margin-right: 0.3em;
        }
        .garage-card .garage-website-link {
            margin-top: auto; /* Pushes link to bottom if card height varies */
            padding-top: 0.75rem;
        }
         .leaflet-popup-content-wrapper {
            background-color: var(--bg-surface);
            color: var(--text-light);
            border-radius: var(--border-radius-small);
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .leaflet-popup-content {
            font-size: 0.9rem;
        }
        .leaflet-popup-content p { margin-bottom: 0.3rem; }
        .leaflet-popup-content strong { color: var(--accent-primary); }
        .leaflet-popup-tip {
            background-color: var(--bg-surface);
        }
        a.leaflet-popup-close-button {
            color: var(--text-secondary);
        }
        a.leaflet-popup-close-button:hover {
            color: var(--text-light);
        }
    </style>
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

    <main class="site-main-content section-padding">
        <div class="container">
            <h1 class="section-title" data-aos="fade-up">Nos Garages Partenaires</h1>
            <p class="section-intro" data-aos="fade-up" data-aos-delay="100" style="text-align:center; max-width:700px; margin-left:auto; margin-right:auto; margin-bottom:2rem;">
                Trouvez un garage de confiance près de chez vous pour le montage de vos pneus. Nos partenaires sont sélectionnés pour leur professionnalisme et la qualité de leurs services.
            </p>

            <div id="mapPartenaires" data-aos="fade-up" data-aos-delay="150"></div>

            <?php if (empty($garages_partenaires)): ?>
                <p data-aos="fade-up" data-aos-delay="200" style="text-align:center;">Aucun garage partenaire à afficher pour le moment. Revenez bientôt !</p>
            <?php else: ?>
                <div class="garage-card-list">
                    <?php foreach ($garages_partenaires as $index => $garage): ?>
                        <div class="garage-card" data-aos="fade-up" data-aos-delay="<?php echo ($index % 3 + 1) * 100; ?>">
                            <h3><?php echo sanitize_html_output($garage['nom_garage']); ?></h3>
                            <p><strong>Adresse :</strong> <?php echo sanitize_html_output($garage['adresse_complete']); ?></p>
                            <?php if (!empty($garage['telephone'])): ?>
                                <p><strong>Téléphone :</strong> <a href="tel:<?php echo sanitize_html_output($garage['telephone']); ?>"><?php echo sanitize_html_output($garage['telephone']); ?></a></p>
                            <?php endif; ?>
                            <?php if (!empty($garage['email'])): ?>
                                <p><strong>Email :</strong> <a href="mailto:<?php echo sanitize_html_output($garage['email']); ?>"><?php echo sanitize_html_output($garage['email']); ?></a></p>
                            <?php endif; ?>
                             <?php if (!empty($garage['horaires_ouverture'])): ?>
                                <p><strong>Horaires :</strong> <?php echo nl2br(sanitize_html_output($garage['horaires_ouverture'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($garage['description_courte'])): ?>
                                <p><?php echo nl2br(sanitize_html_output($garage['description_courte'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($garage['services_offerts'])): ?>
                                <p class="services-title">Services proposés :</p>
                                <ul class="services-list">
                                    <?php
                                    $services = explode(',', $garage['services_offerts']);
                                    foreach ($services as $service) {
                                        echo '<li>' . sanitize_html_output(trim($service)) . '</li>';
                                    }
                                    ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($garage['url_website'])): ?>
                                <p class="garage-website-link"><a href="<?php echo sanitize_html_output($garage['url_website']); ?>" target="_blank" rel="noopener noreferrer" class="cta-button secondary" style="font-size:0.85rem; padding: 0.5rem 1rem;">Visiter le site <i class="fas fa-external-link-alt" style="font-size:0.8em; margin-left:4px;"></i></a></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            AOS.init({ duration: 800, once: true });

            const garagesData = <?php echo $garages_leaflet_data; ?>;

            if (garagesData.length > 0) {
                // Calculer le centre de la carte en fonction des points
                let avgLat = 0;
                let avgLon = 0;
                let validCoordsCount = 0;
                garagesData.forEach(g => {
                    if (g.lat && g.lon) {
                        avgLat += g.lat;
                        avgLon += g.lon;
                        validCoordsCount++;
                    }
                });

                let mapCenter = [46.2276, 2.2137]; // Centre de la France par défaut
                let defaultZoom = 5;

                if (validCoordsCount > 0) {
                    mapCenter = [avgLat / validCoordsCount, avgLon / validCoordsCount];
                    defaultZoom = (validCoordsCount === 1) ? 12 : 6; // Zoom plus proche si un seul point
                }

                const map = L.map('mapPartenaires').setView(mapCenter, defaultZoom);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);

                garagesData.forEach(function(garage) {
                    if (garage.lat && garage.lon) {
                        let popupContent = `<strong>${garage.nom}</strong><br>${garage.adresse}`;
                        if(garage.tel) popupContent += `<br>Tél: ${garage.tel}`;
                        L.marker([garage.lat, garage.lon]).addTo(map)
                            .bindPopup(popupContent);
                    }
                });
            } else {
                // Optionnel: Cacher la carte ou afficher un message si pas de garages avec coordonnées
                const mapDiv = document.getElementById('mapPartenaires');
                if(mapDiv) mapDiv.innerHTML = "<p style='text-align:center; padding: 2rem;'>Aucune coordonnée de garage disponible pour afficher la carte.</p>";
            }
        });
    </script>
</body>
</html>
