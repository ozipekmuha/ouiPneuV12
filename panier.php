<?php
session_start(); // Démarrer la session en tout début de script
require_once 'includes/db_connect.php'; // Connexion à la base de données
require_once 'includes/functions.php'; // Inclure les fonctions globales

// Initialiser le panier en session s'il n'existe pas
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Gestion des actions du panier
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == 'vider') {
        $_SESSION['panier'] = [];
        header('Location: panier.php?status=vidé'); // Redirection pour éviter re-soumission au rafraîchissement
        exit;
    }
    if ($action == 'supprimer' && isset($_GET['id_produit'])) {
        $id_produit_a_supprimer = (int)$_GET['id_produit'];
        if (isset($_SESSION['panier'][$id_produit_a_supprimer])) {
            unset($_SESSION['panier'][$id_produit_a_supprimer]);
        }
        header('Location: panier.php?status=supprimé');
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'mettre_a_jour') {
    if (isset($_POST['quantites']) && is_array($_POST['quantites'])) {
        foreach ($_POST['quantites'] as $id_pneu => $quantite) {
            $id_pneu = (int)$id_pneu;
            $quantite = (int)$quantite;
            if (isset($_SESSION['panier'][$id_pneu])) {
                if ($quantite > 0) {
                    $_SESSION['panier'][$id_pneu] = $quantite;
                } else {
                    // Si la quantité est 0 ou moins, supprimer l'article
                    unset($_SESSION['panier'][$id_pneu]);
                }
            }
        }
    }
    header('Location: panier.php?status=misajour');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'ajouter') {
    if (isset($_POST['id_produit']) && isset($_POST['quantite'])) {
        $id_produit_ajoute = (int)$_POST['id_produit'];
        $quantite_ajoutee = (int)$_POST['quantite'];

        if ($id_produit_ajoute > 0 && $quantite_ajoutee > 0) {
            // Optionnel: Vérifier l'existence du produit et le stock en BDD ici
            // avant d'ajouter/mettre à jour la session.
            // Pour l'instant, on fait confiance aux données du formulaire.

            if (isset($_SESSION['panier'][$id_produit_ajoute])) {
                $_SESSION['panier'][$id_produit_ajoute] += $quantite_ajoutee;
            } else {
                $_SESSION['panier'][$id_produit_ajoute] = $quantite_ajoutee;
            }
            // Rediriger pour afficher le panier et éviter la resoumission du formulaire
            header('Location: panier.php?status=ajoute');
            exit;
        } else {
            // Rediriger vers la page produit avec une erreur si les données sont invalides
            // (ou une page d'erreur générale)
            $redirect_url = 'produits.php'; // Fallback
            if ($id_produit_ajoute > 0) {
                $redirect_url = 'produit.php?id=' . $id_produit_ajoute . '&error=quantite_invalide';
            }
            header('Location: ' . $redirect_url);
            exit;
        }
    }
}


// Récupération des détails des produits du panier
$produits_panier_details = [];
$ids_produits_panier = [];
if (!empty($_SESSION['panier'])) {
    $ids_produits_panier = array_keys($_SESSION['panier']);
}

$sous_total_panier = 0;
$frais_port = 0; // Pour l'instant, à définir plus tard (ex: basé sur poids, montant, ou fixe)
$total_panier = 0;

if (!empty($ids_produits_panier)) {
    try {
        // Création des placeholders pour la requête IN (...)
        $placeholders = implode(',', array_fill(0, count($ids_produits_panier), '?'));
        $stmt_panier = $pdo->prepare("SELECT id, nom, taille, saison, image, prix, stock_disponible, specifications FROM Pneus WHERE id IN ($placeholders) AND est_actif = TRUE");
        $stmt_panier->execute($ids_produits_panier);
        // $produits_bdd = $stmt_panier->fetchAll(PDO::FETCH_KEY_PAIR); // Incorrect for this use case
        $produits_bdd_raw = $stmt_panier->fetchAll(PDO::FETCH_ASSOC);
        $produits_bdd = [];
        foreach ($produits_bdd_raw as $p_item) {
            $produits_bdd[$p_item['id']] = $p_item;
        }

        foreach ($_SESSION['panier'] as $id_pneu => $quantite_session) {
            if (isset($produits_bdd[$id_pneu])) {
                $pneu_info = $produits_bdd[$id_pneu];
                $prix_numerique = convertPriceToFloat($pneu_info['prix']); // Use centralized function

                $produits_panier_details[] = [
                    'id' => $pneu_info['id'], // id is already from $pneu_info which is from $produits_bdd[$id_pneu]
                    'nom' => $pneu_info['nom'],
                    'taille' => $pneu_info['taille'],
                    'saison' => $pneu_info['saison'],
                    'image' => $pneu_info['image'],
                    'prix_unitaire_texte' => $pneu_info['prix'],
                    'prix_unitaire_valeur' => $prix_numerique,
                    'quantite' => $quantite_session,
                    'stock_disponible' => $pneu_info['stock_disponible'],
                    'specifications' => $pneu_info['specifications'], // This was correct
                    'prix_ligne' => $prix_numerique * $quantite_session
                ];
                $sous_total_panier += ($prix_numerique * $quantite_session);
            } else {
                // Le produit n'est plus actif ou n'existe plus, le retirer du panier de session
                unset($_SESSION['panier'][$id_pneu]);
                // Optionnel: notifier l'utilisateur qu'un produit a été retiré
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des détails des produits du panier : " . $e->getMessage());
        // Gérer l'erreur, peut-être vider le panier ou afficher un message
        $_SESSION['panier'] = []; // Sécurité : vider le panier en cas d'erreur BDD
        $produits_panier_details = [];
    }
}

$total_panier = $sous_total_panier + $frais_port;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre Panier - Ouipneu.fr</title>
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
                    <a href="panier.php" class="active" aria-label="Panier"><i class="fas fa-shopping-cart"></i><span class="cart-item-count"><?php echo array_sum($_SESSION['panier'] ?? []); ?></span></a>
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
        <section id="cart-section" class="section-padding">
            <div class="container">
                <h1 class="page-title" data-aos="fade-up">Mon Panier</h1>

                <?php if (isset($_GET['status'])): ?>
                    <?php
                        $status_message = '';
                        $status_class = 'info'; // Default class
                        switch ($_GET['status']) {
                            case 'vidé':
                                $status_message = "Votre panier a été vidé.";
                                break;
                            case 'supprimé':
                                $status_message = "L'article a été supprimé de votre panier.";
                                break;
                            case 'misajour':
                                $status_message = "Panier mis à jour.";
                                break;
                            case 'ajoute':
                                $status_message = "Produit ajouté au panier !";
                                $status_class = 'success';
                                break;
                            // Vous pouvez ajouter d'autres statuts ici, par ex. pour les erreurs
                            case 'erreur_ajout':
                                $status_message = "Erreur lors de l'ajout du produit.";
                                $status_class = 'error';
                                break;
                        }
                    ?>
                    <?php if (!empty($status_message)): ?>
                        <div class="global-notification-bar <?php echo sanitize_html_output($status_class); ?> show" style="position: static; transform: none; top: auto; left: auto; margin-bottom:1.5rem;">
                            <?php echo sanitize_html_output($status_message); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($produits_panier_details)): ?>
                    <div class="cart-empty" data-aos="fade-up" data-aos-delay="100">
                        <p>Votre panier est actuellement vide.</p>
                        <a href="produits.php" class="cta-button">Continuer vos achats</a>
                    </div>
                <?php else: ?>
                    <form method="post" action="panier.php" class="cart-layout">
                        <input type="hidden" name="action" value="mettre_a_jour">
                        <div class="cart-items-column" data-aos="fade-up" data-aos-delay="100">
                            <?php foreach ($produits_panier_details as $item): ?>
                                <div class="cart-item">
                                    <div class="cart-item-image">
                                        <a href="produit.php?id=<?php echo $item['id']; ?>">
                                            <img src="<?php echo sanitize_html_output(!empty($item['image']) ? $item['image'] : 'https://placehold.co/100x80/1e1e1e/ffdd03?text=Pneu'); ?>" alt="<?php echo sanitize_html_output($item['nom']); ?>">
                                        </a>
                                    </div>
                                    <div class="cart-item-details">
                                        <h3 class="cart-item-name"><a href="produit.php?id=<?php echo $item['id']; ?>"><?php echo sanitize_html_output($item['nom']); ?></a></h3>
                                        <p class="cart-item-specs"><?php echo sanitize_html_output($item['taille']); ?> | <?php echo sanitize_html_output($item['saison']); ?></p>
                                        <p class="cart-item-price-unit">Prix unitaire: <?php echo sanitize_html_output($item['prix_unitaire_texte']); ?></p>
                                    </div>
                                    <div class="cart-item-quantity">
                                        <label for="qty-<?php echo $item['id']; ?>" class="sr-only">Quantité pour <?php echo sanitize_html_output($item['nom']); ?></label>
                                        <input type="number" id="qty-<?php echo $item['id']; ?>" name="quantites[<?php echo $item['id']; ?>]" value="<?php echo $item['quantite']; ?>" min="0" max="<?php echo max($item['quantite'], (int)($item['stock_disponible'] ?? 10)); ?>" class="quantity-input" aria-label="Quantité">
                                    </div>
                                    <div class="cart-item-total-price">
                                        <p>€<?php echo number_format($item['prix_ligne'], 2, ',', ' '); ?></p>
                                    </div>
                                    <div class="cart-item-remove">
                                        <a href="panier.php?action=supprimer&id_produit=<?php echo $item['id']; ?>" class="remove-item-button" aria-label="Supprimer <?php echo sanitize_html_output($item['nom']); ?> du panier">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="cart-actions-bar">
                                <a href="produits.php" class="cta-button secondary">Continuer les achats</a>
                                <button type="submit" class="cta-button">Mettre à jour le panier</button>
                            </div>
                        </div>

                        <aside class="cart-summary-box" data-aos="fade-up" data-aos-delay="200">
                            <h2>Récapitulatif</h2>
                            <div class="summary-row">
                                <span>Sous-total:</span>
                                <span id="cart-subtotal">€<?php echo number_format($sous_total_panier, 2, ',', ' '); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Livraison:</span>
                                <span id="cart-shipping"><?php echo ($frais_port > 0) ? '€' . number_format($frais_port, 2, ',', ' ') : 'Gratuite'; // Exemple ?></span>
                            </div>
                            <hr class="summary-divider">
                            <div class="summary-row total-row">
                                <span>Total TTC:</span>
                                <span id="cart-total">€<?php echo number_format($total_panier, 2, ',', ' '); ?></span>
                            </div>
                            <?php if (!empty($produits_panier_details)): // Afficher seulement si le panier n'est pas vide ?>
                                <a href="checkout.php" class="cta-button checkout-button">Passer la commande</a>
                            <?php endif; ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <a href="panier.php?action=vider" class="text-link clear-cart-link">Vider le panier</a>
                            </div>
                        </aside>
                    </form>
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
