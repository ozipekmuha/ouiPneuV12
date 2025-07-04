<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['id_utilisateur'])) {
    $_SESSION['error_message'] = "Vous devez être connecté pour accéder à cette page.";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['id_utilisateur'];
$user_prenom = $_SESSION['prenom_utilisateur'] ?? 'Utilisateur';
$user_nom = $_SESSION['nom_utilisateur'] ?? '';
$user_email = $_SESSION['email_utilisateur'] ?? '';

// Initialize dashboard message system
$dashboard_message_display = null;
if (isset($_SESSION['dashboard_message'])) {
    $dashboard_message_display = $_SESSION['dashboard_message'];
    unset($_SESSION['dashboard_message']);
}

// CSRF Token Generation (do this early)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// --- ACTION HANDLING ---

// Handle Account Details Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_account_details') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF). Veuillez réessayer.'];
    } else {
        $new_prenom = trim($_POST['firstname'] ?? '');
        $new_nom = trim($_POST['lastname'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $current_email = $user_email;
        $errors_account = [];

        if (empty($new_prenom)) $errors_account[] = "Le prénom ne peut pas être vide.";
        if (empty($new_nom)) $errors_account[] = "Le nom ne peut pas être vide.";
        if (empty($new_email)) {
            $errors_account[] = "L'email ne peut pas être vide.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors_account[] = "Format d'email invalide.";
        }

        if ($new_email !== $current_email) {
            try {
                $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateurs WHERE email = :email AND id_utilisateur != :id_utilisateur_current");
                $stmt->execute([':email' => $new_email, ':id_utilisateur_current' => $user_id]);
                if ($stmt->fetch()) {
                    $errors_account[] = "Cette adresse email est déjà utilisée par un autre compte.";
                }
            } catch (PDOException $e) { error_log("Dashboard Account Update - Email Check PDOException: " . $e->getMessage()); $errors_account[] = "Erreur base de données (email check)."; }
        }

        if (empty($errors_account)) {
            try {
                $stmt = $pdo->prepare("UPDATE Utilisateurs SET prenom = :prenom, nom = :nom, email = :email WHERE id_utilisateur = :id_utilisateur");
                if ($stmt->execute([':prenom' => $new_prenom, ':nom' => $new_nom, ':email' => $new_email, ':id_utilisateur' => $user_id])) {
                    $_SESSION['prenom_utilisateur'] = $new_prenom;
                    $_SESSION['nom_utilisateur'] = $new_nom;
                    $_SESSION['email_utilisateur'] = $new_email;
                    $user_prenom = $new_prenom; $user_nom = $new_nom; $user_email = $new_email;
                    $_SESSION['dashboard_message'] = ['type' => 'success', 'text' => 'Vos informations ont été mises à jour.'];
                } else { $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur MAJ infos (DB).']; }
            } catch (PDOException $e) { error_log("Dashboard Account Update - PDOException: " . $e->getMessage()); $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur base de données (MAJ).']; }
        } else {
            $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => implode("<br>", $errors_account)];
        }
    }
    header("Location: dashboard.php#dashboard-account-content");
    exit;
}

// Handle Add/Edit Address
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_address') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF).'];
    } else {
        $address_id = !empty($_POST['address_id']) ? (int)$_POST['address_id'] : null;
        $type_adresse = trim($_POST['type_adresse'] ?? 'Adresse');
        $dest_prenom = trim($_POST['modal_firstname'] ?? '');
        $dest_nom = trim($_POST['modal_lastname'] ?? '');
        $destinataire_nom_complet = trim($dest_prenom . ' ' . $dest_nom);
        $adresse_ligne1 = trim($_POST['adresse_ligne1'] ?? '');
        $adresse_ligne2 = trim($_POST['adresse_ligne2'] ?? null);
        $code_postal = trim($_POST['code_postal'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $pays = trim($_POST['pays'] ?? 'France');
        $telephone_contact = trim($_POST['telephone_contact'] ?? null);
        $est_principale_livraison = isset($_POST['est_principale_livraison']) ? 1 : 0;
        $est_principale_facturation = isset($_POST['est_principale_facturation']) ? 1 : 0;

        $addr_errors = [];
        if (empty($destinataire_nom_complet) || $destinataire_nom_complet === ' ') $addr_errors[] = "Nom complet du destinataire requis.";
        if (empty($adresse_ligne1)) $addr_errors[] = "Ligne d'adresse 1 requise.";
        if (empty($code_postal)) $addr_errors[] = "Code postal requis.";
        if (empty($ville)) $addr_errors[] = "Ville requise.";

        if (empty($addr_errors)) {
            try {
                $pdo->beginTransaction();
                if ($est_principale_livraison) {
                    $stmt_unset_ship = $pdo->prepare("UPDATE Adresses SET est_principale_livraison = 0 WHERE id_utilisateur = :id_user AND (:id_addr IS NULL OR id_adresse != :id_addr)");
                    $stmt_unset_ship->execute([':id_user' => $user_id, ':id_addr' => $address_id]);
                }
                if ($est_principale_facturation) {
                    $stmt_unset_bill = $pdo->prepare("UPDATE Adresses SET est_principale_facturation = 0 WHERE id_utilisateur = :id_user AND (:id_addr IS NULL OR id_adresse != :id_addr)");
                    $stmt_unset_bill->execute([':id_user' => $user_id, ':id_addr' => $address_id]);
                }

                if ($address_id) {
                    $sql = "UPDATE Adresses SET type_adresse = :type, destinataire_nom_complet = :nom_complet, adresse_ligne1 = :l1, adresse_ligne2 = :l2, code_postal = :cp, ville = :ville, pays = :pays, telephone_contact = :tel, est_principale_livraison = :epl, est_principale_facturation = :epf WHERE id_adresse = :id_addr AND id_utilisateur = :id_user";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id_addr', $address_id, PDO::PARAM_INT);
                } else {
                    $sql = "INSERT INTO Adresses (id_utilisateur, type_adresse, destinataire_nom_complet, adresse_ligne1, adresse_ligne2, code_postal, ville, pays, telephone_contact, est_principale_livraison, est_principale_facturation) VALUES (:id_user, :type, :nom_complet, :l1, :l2, :cp, :ville, :pays, :tel, :epl, :epf)";
                    $stmt = $pdo->prepare($sql);
                }
                $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':type', $type_adresse);
                $stmt->bindParam(':nom_complet', $destinataire_nom_complet);
                $stmt->bindParam(':l1', $adresse_ligne1);
                $stmt->bindParam(':l2', $adresse_ligne2, PDO::PARAM_STR|PDO::PARAM_NULL);
                $stmt->bindParam(':cp', $code_postal);
                $stmt->bindParam(':ville', $ville);
                $stmt->bindParam(':pays', $pays);
                $stmt->bindParam(':tel', $telephone_contact, PDO::PARAM_STR|PDO::PARAM_NULL);
                $stmt->bindParam(':epl', $est_principale_livraison, PDO::PARAM_INT);
                $stmt->bindParam(':epf', $est_principale_facturation, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $pdo->commit();
                    $_SESSION['dashboard_message'] = ['type' => 'success', 'text' => 'Adresse ' . ($address_id ? 'mise à jour' : 'ajoutée') . ' avec succès.'];
                } else { $pdo->rollBack(); $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => "Erreur: Impossible de sauvegarder l'adresse."];}
            } catch (PDOException $e) { $pdo->rollBack(); error_log("Err adr save:".$e->getMessage()); $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => "Erreur DB."];}
        } else { $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => implode("<br>", $addr_errors)]; }
    }
    header("Location: dashboard.php#dashboard-addresses-content");
    exit;
}

// Handle Delete Address
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete_address') {
    if (!isset($_GET['csrf_token']) || !hash_equals($csrf_token, $_GET['csrf_token'])) {
        $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF).'];
    } else {
        $address_id_to_delete = (int)($_GET['id'] ?? 0);
        if ($address_id_to_delete > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM Adresses WHERE id_adresse = :id_adresse AND id_utilisateur = :id_utilisateur");
                if ($stmt->execute([':id_adresse' => $address_id_to_delete, ':id_utilisateur' => $user_id])) {
                    $_SESSION['dashboard_message'] = ['type' => 'success', 'text' => 'Adresse supprimée.'];
                } else { $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur suppression.'];}
            } catch (PDOException $e) { error_log("Err adr del:".$e->getMessage()); $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur DB del.'];}
        } else { $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'ID Adresse invalide.'];}
    }
    header("Location: dashboard.php#dashboard-addresses-content");
    exit;
}

// Handle Set Default Address
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'set_default_address') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF).'];
    } else {
        $address_id_to_set = (int)($_POST['address_id'] ?? 0);
        $address_type = $_POST['address_type'] ?? '';
        $is_making_default = isset($_POST['is_default']);

        if ($address_id_to_set > 0 && ($address_type === 'shipping' || $address_type === 'billing')) {
            try {
                $pdo->beginTransaction();
                $column_to_update = ($address_type === 'shipping') ? 'est_principale_livraison' : 'est_principale_facturation';

                if ($is_making_default) {
                    $stmt_unset = $pdo->prepare("UPDATE Adresses SET $column_to_update = 0 WHERE id_utilisateur = :id_user AND id_adresse != :id_addr");
                    $stmt_unset->execute([':id_user' => $user_id, ':id_addr' => $address_id_to_set]);
                }
                $stmt_set = $pdo->prepare("UPDATE Adresses SET $column_to_update = :is_def WHERE id_adresse = :id_addr AND id_utilisateur = :id_user");
                $stmt_set->bindValue(':is_def', $is_making_default ? 1 : 0, PDO::PARAM_INT);
                $stmt_set->bindParam(':id_addr', $address_id_to_set, PDO::PARAM_INT);
                $stmt_set->bindParam(':id_user', $user_id, PDO::PARAM_INT);

                if ($stmt_set->execute()) {
                    $pdo->commit();
                    $_SESSION['dashboard_message'] = ['type' => 'success', 'text' => 'Adresse par défaut mise à jour.'];
                } else { $pdo->rollBack(); $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur MAJ adresse défaut.'];}
            } catch (PDOException $e) { $pdo->rollBack(); error_log("Err set def adr:".$e->getMessage()); $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Erreur DB set def adr.'];}
        } else { $_SESSION['dashboard_message'] = ['type' => 'error', 'text' => 'Données invalides pour MAJ adresse défaut.'];}
    }
    header("Location: dashboard.php#dashboard-addresses-content");
    exit;
}
// --- END ACTION HANDLING ---

// Fetch data for display AFTER any actions
$addresses = getUserAddresses($pdo, $user_id);
$orders = getUserOrdersSummary($pdo, $user_id);

$page_title = "Mon Compte - Ouipneu.fr";
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
    <style>
        .address-card {
            background-color: var(--bg-dark); padding: 1rem; border-radius: var(--border-radius-small, 5px);
            margin-bottom: 1rem; border: 1px solid var(--border-color);
        }
        .address-card p { margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-secondary); }
        .address-card p strong:first-child { color: var(--text-light); font-size: 1rem; display: block; margin-bottom: 0.25rem; }
        .address-actions a { font-size: 0.85rem; color: var(--accent-primary); margin-right: 0.5rem; }
        .address-actions a:hover { text-decoration: underline; }
        .address-default-options { margin-top: 0.75rem; font-size: 0.85rem; }
        .address-default-options input[type="radio"], .address-default-options input[type="checkbox"] { margin-right: 0.3rem; accent-color: var(--accent-primary); }
        .address-default-options label { font-weight: normal; color: var(--text-secondary); }
        #address-list-container.placeholder-content { border: none; padding: 0; text-align: left; }
        #address-list-container #no-address-message { text-align: center; padding: 2rem; border: 2px dashed var(--border-color); border-radius: var(--border-radius-small); }
        .default-address-form { display: inline-block; margin-right: 10px; }
    </style>
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
                        <?php /* Le lien de déconnexion textuel est retiré du header principal. Il reste dans dashboard.php (sidebar) */ ?>
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
        <section id="dashboard-section" class="section-padding">
            <div class="container">
                <h1 class="page-title" style="text-align: center; margin-bottom: 2rem;">Mon Compte</h1>
                <div class="dashboard-layout">
                    <aside class="dashboard-sidebar">
                        <nav class="dashboard-nav">
                            <ul>
                                <li><a href="#dashboard-overview" class="dashboard-nav-item is-active" data-target="dashboard-overview-content"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                                <li><a href="#dashboard-orders" class="dashboard-nav-item" data-target="dashboard-orders-content"><i class="fas fa-box"></i> Mes Commandes</a></li>
                                <li><a href="#dashboard-addresses" class="dashboard-nav-item" data-target="dashboard-addresses-content"><i class="fas fa-map-marker-alt"></i> Mes Adresses</a></li>
                                <li><a href="#dashboard-account" class="dashboard-nav-item" data-target="dashboard-account-content"><i class="fas fa-user-cog"></i> Détails du Compte</a></li>
                                <li><a href="logout.php" class="dashboard-nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                            </ul>
                        </nav>
                    </aside>
                    <section class="dashboard-content">
                        <div id="dashboard-overview-content" class="dashboard-content-section is-active">
                            <h2>Tableau de Bord</h2>
                            <p>Bonjour <?php echo sanitize_html_output($user_prenom); ?> !</p>
                            <p>Bienvenue sur votre tableau de bord. D'ici, vous pouvez gérer vos commandes, adresses et détails de compte.</p>
                        </div>
                        <div id="dashboard-orders-content" class="dashboard-content-section">
                            <h2>Mes Commandes</h2>
                            <?php if (empty($orders)): ?>
                                <div class="placeholder-content"><p>Aucune commande pour le moment.</p><i class="fas fa-receipt fa-3x" style="color: var(--border-color);"></i></div>
                            <?php else: ?>
                                <table class="dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>N° Commande</th>
                                            <th>Date</th>
                                            <th>Statut</th>
                                            <th>Total</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo sanitize_html_output($order['id_commande']); ?></td>
                                                <td><?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($order['date_commande']))); ?></td>
                                                <td><?php echo sanitize_html_output($order['statut_commande']); ?></td>
                                                <td><?php echo sanitize_html_output(number_format($order['montant_total_ttc'], 2, ',', ' ')) . ' €'; ?></td>
                                                <?php
                                                    // Fetch line items for this order
                                                    $line_items_for_modal = getOrderLineItems($pdo, $order['id_commande']);
                                                    $order_details_for_modal = $order; // $order already contains address strings from getUserOrdersSummary
                                                    $order_details_for_modal['line_items'] = $line_items_for_modal;
                                                ?>
                                                <td><a href="#" class="cta-button-small view-order-details-link"
                                                       data-order-details='<?php echo sanitize_html_output(json_encode($order_details_for_modal)); ?>'
                                                       aria-label="Voir les détails de la commande #<?php echo sanitize_html_output($order['id_commande']); ?>">Voir</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div id="dashboard-addresses-content" class="dashboard-content-section">
                            <h2>Mes Adresses</h2>
                            <p>Gérez vos adresses de livraison et de facturation.</p>
                            <?php if (!empty($dashboard_message_display) && ($_GET['section'] ?? '') === 'addresses'): // Display message if relevant to this section ?>
                                <div class="global-notification-bar <?php echo sanitize_html_output($dashboard_message_display['type']); ?> show" style="position: static; transform: none; margin-bottom: 1rem;">
                                    <?php echo $dashboard_message_display['text']; // Text is pre-sanitized or simple strings ?>
                                </div>
                            <?php endif; ?>
                            <div id="address-list-container" class="<?php echo empty($addresses) ? 'placeholder-content' : ''; ?>">
                                <?php if (!empty($addresses)): ?>
                                    <?php foreach($addresses as $addr): ?>
                                    <div class="address-card" data-address-id="<?php echo sanitize_html_output($addr['id_adresse']); ?>">
                                        <p><strong><?php echo sanitize_html_output(!empty($addr['type_adresse']) ? $addr['type_adresse'] : 'Adresse'); ?></strong><br>
                                        <?php echo sanitize_html_output(!empty($addr['destinataire_nom_complet']) ? $addr['destinataire_nom_complet'] : ($user_prenom . ' ' . $user_nom)); ?><br>
                                        <?php echo sanitize_html_output($addr['adresse_ligne1']); ?><br>
                                        <?php if(!empty($addr['adresse_ligne2'])) { echo sanitize_html_output($addr['adresse_ligne2']) . '<br>'; } ?>
                                        <?php echo sanitize_html_output($addr['code_postal'] . ' ' . $addr['ville'] . ', ' . $addr['pays']); ?><br>
                                        <?php if(!empty($addr['telephone_contact'])) { echo 'Tél : ' . sanitize_html_output($addr['telephone_contact']); } ?></p>
                                        <div class="address-actions">
                                            <a href="#" class="edit-address-link" data-address='<?php echo sanitize_html_output(json_encode($addr)); ?>'>Modifier</a> |
                                            <a href="dashboard.php?action=delete_address&id=<?php echo $addr['id_adresse']; ?>&amp;csrf_token=<?php echo $csrf_token; ?>" class="delete-address-link" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette adresse ?');">Supprimer</a>
                                        </div>
                                        <div class="address-default-options">
                                            <form method="POST" action="dashboard.php#dashboard-addresses-content" class="default-address-form">
                                                <input type="hidden" name="action" value="set_default_address">
                                                <input type="hidden" name="address_id" value="<?php echo $addr['id_adresse']; ?>">
                                                <input type="hidden" name="address_type" value="shipping">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="checkbox" name="is_default" value="1" id="ds-<?php echo $addr['id_adresse']; ?>" <?php if($addr['est_principale_livraison']) echo 'checked'; ?> onchange="this.form.submit()">
                                                <label for="ds-<?php echo $addr['id_adresse']; ?>">Livraison par défaut</label>
                                            </form>
                                            <form method="POST" action="dashboard.php#dashboard-addresses-content" class="default-address-form">
                                                <input type="hidden" name="action" value="set_default_address">
                                                <input type="hidden" name="address_id" value="<?php echo $addr['id_adresse']; ?>">
                                                <input type="hidden" name="address_type" value="billing">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="checkbox" name="is_default" value="1" id="db-<?php echo $addr['id_adresse']; ?>" <?php if($addr['est_principale_facturation']) echo 'checked'; ?> onchange="this.form.submit()">
                                                <label for="db-<?php echo $addr['id_adresse']; ?>">Facturation par défaut</label>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                     <p id="no-address-message" style="display:block; text-align:center; padding:2rem; border:2px dashed var(--border-color); border-radius:var(--border-radius-small);">Vous n'avez pas encore ajouté d'adresse.</p>
                                <?php endif; ?>
                                <button type="button" class="cta-button secondary" id="add-new-address-button" style="margin-top: 1rem;">Ajouter une nouvelle adresse</button>
                            </div>
                        </div>

                        <div id="dashboard-account-content" class="dashboard-content-section">
                            <h2>Détails du Compte</h2>
                            <p>Modifiez vos informations personnelles et votre mot de passe.</p>
                            <?php if (!empty($dashboard_message_display) && (!isset($_GET['section']) || $_GET['section'] !== 'addresses')): // Display general messages here ?>
                                <div class="global-notification-bar <?php echo sanitize_html_output($dashboard_message_display['type']); ?> show" style="position: static; transform: none; margin-bottom: 1rem;">
                                    <?php echo $dashboard_message_display['text']; ?>
                                </div>
                            <?php endif; ?>
                            <form id="account-details-form" class="placeholder-form account-details-form" method="POST" action="dashboard.php#dashboard-account-content">
                                <input type="hidden" name="action" value="update_account_details">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="dash-firstname">Prénom</label>
                                        <input type="text" id="dash-firstname" name="firstname" value="<?php echo sanitize_html_output($user_prenom); ?>" placeholder="Votre prénom" autocomplete="given-name">
                                    </div>
                                    <div class="form-group">
                                        <label for="dash-lastname">Nom</label>
                                        <input type="text" id="dash-lastname" name="lastname" value="<?php echo sanitize_html_output($user_nom); ?>" placeholder="Votre nom" autocomplete="family-name">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="dash-email">Adresse Email</label>
                                    <input type="email" id="dash-email" name="email" value="<?php echo sanitize_html_output($user_email); ?>" autocomplete="email">
                                </div>
                                <button type="submit" class="cta-button" name="update_info">Mettre à jour les informations</button>
                            </form>
                            <hr class="form-divider">
                            <h3 class="form-section-title">Changer le mot de passe</h3>
                            <form id="change-password-form" class="placeholder-form account-details-form" method="POST" action="dashboard.php#dashboard-account-content">
                                <input type="hidden" name="action" value="update_password"> <!-- This action is not yet handled -->
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <div class="form-group">
                                    <label for="dash-current-password">Mot de passe actuel</label>
                                    <input type="password" id="dash-current-password" name="current_password" placeholder="Requis pour changer le mot de passe" autocomplete="current-password">
                                </div>
                                <div class="form-group">
                                    <label for="dash-new-password">Nouveau mot de passe</label>
                                    <input type="password" id="dash-new-password" name="new_password" placeholder="Minimum 8 caractères" autocomplete="new-password">
                                </div>
                                <div class="form-group">
                                    <label for="dash-confirm-password">Confirmer le nouveau mot de passe</label>
                                    <input type="password" id="dash-confirm-password" name="confirm_password" autocomplete="new-password">
                                </div>
                                <button type="submit" class="cta-button">Changer le mot de passe</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <footer id="main-footer"><!-- ... Footer HTML ... --></footer>

    <!-- Address Modal HTML moved here, just before closing </body> -->
    <div id="address-modal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <header class="modal-header">
                <h3 id="modal-title">Ajouter une nouvelle adresse</h3>
                <button type="button" class="modal-close-button" aria-label="Fermer">&times;</button>
            </header>
            <section class="modal-body">
                <form id="address-form" class="address-form" method="POST" action="dashboard.php#dashboard-addresses-content">
                    <input type="hidden" name="action" value="save_address">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="address-id" name="address_id" value="">
                    <div class="form-group">
                        <label for="address-nickname">Nom de l'adresse (ex: Maison, Travail)</label>
                        <input type="text" id="address-nickname" name="type_adresse" placeholder="Ex: Domicile">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address-firstname">Prénom du destinataire</label>
                            <input type="text" id="address-firstname" name="modal_firstname" required>
                        </div>
                        <div class="form-group">
                            <label for="address-lastname">Nom du destinataire</label>
                            <input type="text" id="address-lastname" name="modal_lastname" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address-street">Adresse (N° et rue)</label>
                        <input type="text" id="address-street" name="adresse_ligne1" required placeholder="Ex: 123 Rue de la Paix">
                    </div>
                    <div class="form-group">
                        <label for="address-complement">Complément d'adresse (Appartement, étage...)</label>
                        <input type="text" id="address-complement" name="adresse_ligne2" placeholder="Ex: Apt 4B, Bat C">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address-zipcode">Code Postal</label>
                            <input type="text" id="address-zipcode" name="code_postal" required placeholder="Ex: 75001">
                        </div>
                        <div class="form-group">
                            <label for="address-city">Ville</label>
                            <input type="text" id="address-city" name="ville" required placeholder="Ex: Paris">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address-country">Pays</label>
                        <select id="address-country" name="pays" required>
                            <option value="France">France</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                            <option value="Luxembourg">Luxembourg</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="address-phone">Numéro de téléphone</label>
                        <input type="tel" id="address-phone" name="telephone_contact" placeholder="Pour la livraison">
                    </div>
                        <div class="form-group">
                        <input type="checkbox" id="address-is-default-shipping" name="est_principale_livraison" value="1">
                        <label for="address-is-default-shipping" class="checkbox-label">Faire de cette adresse mon adresse de livraison par défaut</label>
                    </div>
                    <div class="form-group">
                        <input type="checkbox" id="address-is-default-billing" name="est_principale_facturation" value="1">
                        <label for="address-is-default-billing" class="checkbox-label">Faire de cette adresse mon adresse de facturation par défaut</label>
                    </div>
                </form>
            </section>
            <footer class="modal-footer">
                <button type="button" class="cta-button secondary modal-cancel-button">Annuler</button>
                <button type="submit" form="address-form" class="cta-button modal-save-button">Enregistrer</button>
            </footer>
        </div>
    </div>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="js/main.js"></script>

    <!-- Order Detail Modal -->
    <div id="order-detail-modal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 800px;"> <!-- Wider modal for order details -->
            <header class="modal-header">
                <h3 id="order-modal-title">Détails de la Commande</h3>
                <button type="button" class="modal-close-button" aria-label="Fermer">&times;</button>
            </header>
            <section class="modal-body" id="order-modal-body-content">
                <!-- Content will be populated by JavaScript -->
                <p>Chargement des détails de la commande...</p>
            </section>
            <footer class="modal-footer">
                <button type="button" class="cta-button secondary modal-cancel-button">Fermer</button>
            </footer>
        </div>
    </div>
</body>
</html>

