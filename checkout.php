<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// --- Pre-Action Setup & Access Control ---
if (!isset($_SESSION['id_utilisateur'])) {
    $_SESSION['error_message'] = "Vous devez être connecté pour finaliser votre commande.";
    header("Location: login.php?redirect=checkout.php");
    exit;
}

if (empty($_SESSION['panier']) || !is_array($_SESSION['panier']) || array_sum($_SESSION['panier']) === 0) {
    $_SESSION['info_message'] = 'Votre panier est vide.';
    header("Location: panier.php");
    exit;
}

$user_id = $_SESSION['id_utilisateur'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$TVA_RATE = 0.20;

// --- POST Action Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF token validation for all POST actions on this page
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['checkout_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité. Veuillez réessayer.'];
        header("Location: checkout.php");
        exit;
    }

    // Handle Add Address Action
    if (isset($_POST['action']) && $_POST['action'] == 'add_address') {
        $destinataire = trim($_POST['destinataire_nom_complet'] ?? '');
        $ligne1 = trim($_POST['adresse_ligne1'] ?? '');
        $ligne2 = trim($_POST['adresse_ligne2'] ?? '');
        $cp = trim($_POST['code_postal'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $pays = trim($_POST['pays'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $type_adresse = trim($_POST['type_adresse'] ?? 'Autre'); // Default type if not provided

        $errors_address = [];
        if (empty($destinataire)) $errors_address[] = "Le nom du destinataire est requis.";
        if (empty($ligne1)) $errors_address[] = "L'adresse (ligne 1) est requise.";
        if (empty($cp)) $errors_address[] = "Le code postal est requis.";
        if (empty($ville)) $errors_address[] = "La ville est requise.";
        if (empty($pays)) $errors_address[] = "Le pays est requis.";
        // Add more specific validations as needed

        if (empty($errors_address)) {
            try {
            // Vérification CSRF pour l'ajout d'adresse
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                throw new Exception("Erreur de sécurité CSRF. Veuillez réessayer.");
            }

            $stmt_add = $pdo->prepare(
                "INSERT INTO Adresses (
                    id_utilisateur, destinataire_nom_complet, adresse_ligne1, adresse_ligne2, 
                    code_postal, ville, pays, telephone_contact, type_adresse, 
                    est_principale_livraison, est_principale_facturation
                 ) VALUES (
                    :id_user, :dest, :l1, :l2, 
                    :cp, :ville, :pays, :tel, :type_form, 
                    FALSE, FALSE
                 )"
            );
                $stmt_add->execute([
                ':id_user' => $user_id,
                    ':dest' => $destinataire,
                    ':l1' => $ligne1,
                ':l2' => !empty($ligne2) ? $ligne2 : null,
                    ':cp' => $cp,
                    ':ville' => $ville,
                    ':pays' => $pays,
                ':tel' => !empty($telephone) ? $telephone : null,        // Valeur de $_POST['telephone'] pour la colonne telephone_contact
                ':type_form' => $type_adresse  // Valeur de $_POST['type_adresse'] pour la colonne type_adresse
                ]);
                $_SESSION['checkout_message'] = ['type' => 'success', 'text' => 'Nouvelle adresse ajoutée avec succès.'];
            } catch (PDOException $e) {
            error_log("Erreur PDO ajout adresse checkout: " . $e->getMessage() . (isset($stmt_add) ? " SQL: " . $stmt_add->queryString : ""));
            $_SESSION['checkout_message'] = ['type' => 'error', 'text' => 'Erreur technique lors de l\'ajout de l\'adresse. <!-- Debug PDO: ' . $e->getMessage() . '-->'];
        } catch (Exception $e) { 
            error_log("Erreur générale ajout adresse checkout: " . $e->getMessage());
            $_SESSION['checkout_message'] = ['type' => 'error', 'text' => 'Une erreur est survenue: ' . $e->getMessage()];
            }
        } else {
            $_SESSION['checkout_message'] = ['type' => 'error', 'text' => implode("<br>", $errors_address)];
            // Preserve form input if needed, for now, just show errors.
        }
        header("Location: checkout.php"); // Reload to show messages and updated address list
        exit;

    // Handle Place Order Action
    } elseif (isset($_POST['action']) && $_POST['action'] == 'place_order') {
        $shipping_address_id = filter_input(INPUT_POST, 'shipping_address_id', FILTER_VALIDATE_INT);
        $billing_same_as_shipping = isset($_POST['billing_same_as_shipping']);
        $billing_address_id = $billing_same_as_shipping ? $shipping_address_id : filter_input(INPUT_POST, 'billing_address_id', FILTER_VALIDATE_INT);

        $errors_checkout = [];
        if (!$shipping_address_id) $errors_checkout[] = "Veuillez sélectionner une adresse de livraison.";
        if (!$billing_address_id) $errors_checkout[] = "Veuillez sélectionner une adresse de facturation.";

        // Further validation: Check if these addresses belong to the user
        $valid_addresses = true;
        if ($shipping_address_id) {
            $stmt_check_addr = $pdo->prepare("SELECT id_adresse FROM Adresses WHERE id_adresse = :id_addr AND id_utilisateur = :id_user");
            $stmt_check_addr->execute([':id_addr' => $shipping_address_id, ':id_user' => $user_id]);
            if (!$stmt_check_addr->fetch()) {
                $errors_checkout[] = "Adresse de livraison invalide.";
                $valid_addresses = false;
            }
        }
        if ($billing_address_id && $billing_address_id !== $shipping_address_id) { // Check only if different
            $stmt_check_addr->execute([':id_addr' => $billing_address_id, ':id_user' => $user_id]);
            if (!$stmt_check_addr->fetch()) {
                $errors_checkout[] = "Adresse de facturation invalide.";
                $valid_addresses = false;
            }
        }

        if (empty($errors_checkout) && $valid_addresses) {
            $current_cart_items_for_order = [];
            $current_subtotal_ttc_for_order = 0;
        $current_shipping_cost_for_order = 5.00;

        if (!empty($_SESSION['panier'])) {
            $product_ids_in_cart_order = array_keys($_SESSION['panier']);
            $placeholders_cart_order = implode(',', array_fill(0, count($product_ids_in_cart_order), '?'));
            $stmt_recheck_cart_order = $pdo->prepare("SELECT id, nom, prix, stock_disponible, taille FROM Pneus WHERE id IN ($placeholders_cart_order)");
            $stmt_recheck_cart_order->execute($product_ids_in_cart_order);
            $products_data_recheck_order = [];
            while ($p_row = $stmt_recheck_cart_order->fetch(PDO::FETCH_ASSOC)) {
                $products_data_recheck_order[$p_row['id']] = $p_row;
            }

            foreach ($_SESSION['panier'] as $p_id => $p_qty) {
                if (!isset($products_data_recheck_order[$p_id])) {
                     $errors_checkout[] = "Un produit (ID: $p_id) de votre panier n'existe plus."; break;
                }
                if ($p_qty > $products_data_recheck_order[$p_id]['stock_disponible']) {
                    $errors_checkout[] = "Stock insuffisant pour " . sanitize_html_output($products_data_recheck_order[$p_id]['nom']) . ". Disponible: " . $products_data_recheck_order[$p_id]['stock_disponible'];
                    break;
                }
                $price_ttc = convertPriceToFloat($products_data_recheck_order[$p_id]['prix']);
                $current_cart_items_for_order[] = [
                    'id' => $p_id, 'nom' => $products_data_recheck_order[$p_id]['nom'],
                    'quantite' => $p_qty, 'prix_unitaire_ttc' => $price_ttc,
                    'taille' => $products_data_recheck_order[$p_id]['taille']
                ];
                $current_subtotal_ttc_for_order += $price_ttc * $p_qty;
            }
        }

        if (empty($current_cart_items_for_order) && empty($errors_checkout)) $errors_checkout[] = "Votre panier est vide.";

        if (empty($errors_checkout)) {
            $db_subtotal_ttc = $current_subtotal_ttc_for_order;
            $db_shipping_ttc = $current_shipping_cost_for_order;
            $db_reduction_ttc = 0.00;
            $db_grand_total_ttc = $db_subtotal_ttc + $db_shipping_ttc - $db_reduction_ttc;

            $db_grand_total_ht = round($db_grand_total_ttc / (1 + $TVA_RATE), 2);
            $db_tva_amount = $db_grand_total_ttc - $db_grand_total_ht;
            $db_subtotal_ht = round($db_subtotal_ttc / (1 + $TVA_RATE), 2);
            $db_shipping_ht = round($db_shipping_ttc / (1 + $TVA_RATE), 2); // Assuming shipping is also taxed
            $db_reduction_ht = round($db_reduction_ttc / (1 + $TVA_RATE), 2);


            try {
                $pdo->beginTransaction();
                $stmt_order = $pdo->prepare(
                    "INSERT INTO Commandes (id_utilisateur, id_adresse_livraison, id_adresse_facturation, statut_commande,
                                       montant_sous_total, montant_livraison, montant_reduction,
                                       montant_total_ht, montant_tva, montant_total_ttc, methode_paiement)
                     VALUES (:uid, :adr_liv, :adr_fact, :statut, :sous_total_ht, :liv_ht, :reduc_ht, :total_ht, :tva, :total_ttc, :methode)"
                );

                $stmt_order->execute([
                    ':uid' => $user_id, ':adr_liv' => $shipping_address_id, ':adr_fact' => $billing_address_id,
                    ':statut' => 'En attente de paiement', ':sous_total_ht' => $db_subtotal_ht,
                    ':liv_ht' => $db_shipping_ht, ':reduc_ht' => $db_reduction_ht,
                    ':total_ht' => $db_grand_total_ht, ':tva' => $db_tva_amount,
                    ':total_ttc' => $db_grand_total_ttc, ':methode' => 'N/A'
                ]);
                $order_id = $pdo->lastInsertId();

                $stmt_item = $pdo->prepare(
                    "INSERT INTO Lignes_Commande (id_commande, id_pneu, quantite, prix_unitaire_ht_commande, taux_tva_applique, nom_produit_commande, taille_produit_commande)
                     VALUES (:order_id, :pneu_id, :qty, :prix_ht, :tva_rate, :nom, :taille)"
                );

                foreach ($current_cart_items_for_order as $item) {
                    $item_prix_ht = round($item['prix_unitaire_ttc'] / (1 + $TVA_RATE), 2);
                    $stmt_item->execute([
                        ':order_id' => $order_id, ':pneu_id' => $item['id'], ':qty' => $item['quantite'],
                        ':prix_ht' => $item_prix_ht, ':tva_rate' => $TVA_RATE * 100,
                        ':nom' => $item['nom'], ':taille' => $item['taille']
                    ]);
                    // Stock deduction
                    $stmt_stock = $pdo->prepare("UPDATE Pneus SET stock_disponible = stock_disponible - :qty WHERE id = :pneu_id");
                    $stmt_stock->execute([':qty' => $item['quantite'], ':pneu_id' => $item['id']]);
                }
                $pdo->commit();
                unset($_SESSION['panier']);
                $_SESSION['order_confirmation'] = ['order_id' => $order_id, 'total' => $db_grand_total_ttc];
                header("Location: order_confirmation.php");
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erreur placement commande: " . $e->getMessage());
                $_SESSION['checkout_message'] = ['type' => 'error', 'text' => 'Une erreur technique est survenue lors de la création de votre commande.'];
            }
        }
    }
    if (!empty($errors_checkout)) {
         $_SESSION['checkout_message'] = ['type' => 'error', 'text' => implode("<br>", $errors_checkout)];
        }
        // No redirect here if errors for 'place_order', let the page reload to display errors.
        // Form data retention for 'place_order' errors is not fully implemented for all fields but messages are shown.
    }
    // else: Potentially handle unknown POST action or do nothing and let the page render.
}
// End of POST handling block

// --- Data for Page Display (GET request or after POST if not redirected/exited) ---
$user_prenom = $_SESSION['prenom_utilisateur'] ?? 'Client';
$header_cart_count = array_sum($_SESSION['panier'] ?? []);

$cart_items_details_display = []; // Use a different variable name for display to avoid conflict
$subtotal_display = 0;
$shipping_cost_display = 5.00;

if (!empty($_SESSION['panier'])) {
    $cart_product_ids_display = array_keys($_SESSION['panier']);
    if (!empty($cart_product_ids_display)) {
        $placeholders_display = implode(',', array_fill(0, count($cart_product_ids_display), '?'));
        $stmt_cart_display = $pdo->prepare("SELECT id, nom, image, prix FROM Pneus WHERE id IN ($placeholders_display)");
        $stmt_cart_display->execute($cart_product_ids_display);
        $products_in_db_for_display = [];
        while ($row_display = $stmt_cart_display->fetch(PDO::FETCH_ASSOC)) {
            $products_in_db_for_display[$row_display['id']] = $row_display;
        }

        foreach ($_SESSION['panier'] as $product_id_disp => $quantity_disp) {
            if (isset($products_in_db_for_display[$product_id_disp])) {
                $product_disp = $products_in_db_for_display[$product_id_disp];
                $product_price_disp = convertPriceToFloat($product_disp['prix']);
                $line_total_disp = $product_price_disp * $quantity_disp;
                $subtotal_display += $line_total_disp;
                $cart_items_details_display[] = [
                    'id' => $product_disp['id'], 'nom' => $product_disp['nom'], 'image' => $product_disp['image'],
                    'prix_unitaire_formate' => number_format($product_price_disp, 2, ',', ' ') . ' €',
                    'quantite' => $quantity_disp, 'total_ligne_formate' => number_format($line_total_disp, 2, ',', ' ') . ' €'
                ];
            }
        }
    }
}
$grand_total_display = $subtotal_display + $shipping_cost_display;

$user_addresses_display = getUserAddresses($pdo, $user_id);
$default_shipping_id_display = null; $default_billing_id_display = null;
foreach ($user_addresses_display as $addr_disp) {
    if ($addr_disp['est_principale_livraison']) $default_shipping_id_display = $addr_disp['id_adresse'];
    if ($addr_disp['est_principale_facturation']) $default_billing_id_display = $addr_disp['id_adresse'];
}

$checkout_message_to_show = $_SESSION['checkout_message'] ?? null;
if($checkout_message_to_show) unset($_SESSION['checkout_message']);

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
        .checkout-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 1.5rem; }
        .checkout-main, .checkout-sidebar { background-color: var(--bg-surface); padding: 1.5rem; border-radius: var(--border-radius-medium); }
        .checkout-sidebar { position: sticky; top: 100px; }
        .checkout-section { margin-bottom: 2rem; }
        .checkout-section h2 { font-size: 1.5rem; color: var(--accent-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .order-summary-table { width: 100%; margin-bottom:1rem; font-size:0.9rem; border-collapse: collapse; }
        .order-summary-table th, .order-summary-table td { text-align:left; padding:0.6rem 0.4rem; border-bottom:1px solid var(--border-color); }
        .order-summary-table thead th { color: var(--text-light); font-weight: var(--font-weight-semibold); }
        .order-summary-table img { width: 50px; height: auto; margin-right: 10px; border-radius: var(--border-radius-small); }
        .order-summary-table td:nth-child(3), .order-summary-table td:nth-child(4) { text-align:right; }
        .order-totals { margin-top:1rem; }
        .order-totals p { display:flex; justify-content:space-between; margin-bottom:0.5rem; font-size:1rem;}
        .order-totals p span:first-child { color: var(--text-secondary); }
        .order-totals p span:last-child { color: var(--text-light); font-weight: var(--font-weight-medium); }
        .order-totals p.grand-total span {color: var(--text-light); }
        .order-totals p.grand-total span:last-child { font-weight:bold; font-size:1.2rem; color:var(--accent-primary); }
        .address-selection-group { margin-bottom: 1.5rem; }
        .address-selection-group h3 { font-size: 1.2rem; margin-bottom: 0.75rem; color: var(--text-light); }
        .address-option { background-color: var(--bg-dark); padding: 1rem; border-radius: var(--border-radius-small); margin-bottom: 0.5rem; border: 1px solid var(--border-color); cursor: pointer; }
        .address-option:hover { border-color: var(--accent-primary-darker); }
        .address-option input[type="radio"] { margin-right: 0.5rem; accent-color: var(--accent-primary); }
        .address-option label { font-size: 0.9rem; color: var(--text-secondary); display: block; }
        .address-option label strong { color: var(--text-light); }
        .billing-same-as-shipping { margin-bottom: 1.5rem; font-size: 0.9rem; }
        .billing-same-as-shipping input { margin-right: 0.5rem; accent-color: var(--accent-primary); }
        .toggle-address-form-link { display: inline-block; margin-top: 0.5rem; font-size:0.9rem; cursor:pointer; color: var(--accent-primary); text-decoration: underline; }
        
        .checkout-back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            color: var(--text-secondary);
            text-decoration: none;
        }
        .checkout-back-link:hover {
            color: var(--accent-primary);
            text-decoration: underline;
        }
        .checkout-back-link .fas {
            margin-right: 0.5rem;
        }

        #add-new-address-form-checkout { 
            background-color: var(--bg-dark); 
            padding: 1.5rem; 
            border-radius: var(--border-radius-medium); 
            margin-top: 1rem; 
            border: 1px solid var(--border-color-dark);
        }
        #add-new-address-form-checkout h4 { font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-light); }
        #add-new-address-form-checkout .form-group { margin-bottom: 1rem; }
        #add-new-address-form-checkout .form-group label { display: block; margin-bottom: .3rem; font-size: .85rem; color:var(--text-secondary); }
        #add-new-address-form-checkout .form-group input[type="text"],
        #add-new-address-form-checkout .form-group input[type="tel"],
        #add-new-address-form-checkout .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            background-color: var(--bg-surface);
            color: var(--text-light);
            border-radius: var(--border-radius-small);
        }
         #add-new-address-form-checkout .form-actions { margin-top:1.5rem; display:flex; justify-content: flex-end; gap:1rem; }


        @media (max-width: 992px) {
            .checkout-layout { grid-template-columns: 1fr; }
            .checkout-sidebar { position: static; margin-top: 2rem; }
        }
    </style>
</head>
<body>
    <header id="main-header"><!-- Standard Header HTML --></header>

    <main class="site-main-content">
        <section id="checkout-section" class="section-padding">
            <div class="container">
                <a href="panier.php" class="checkout-back-link" aria-label="Retour au panier">
                    <i class="fas fa-arrow-left"></i> Retour au panier
                </a>
                <h1 class="page-title" data-aos="fade-up">Finaliser ma commande</h1>

                <?php if ($checkout_message_to_show): ?>
                    <div class="global-notification-bar <?php echo sanitize_html_output($checkout_message_to_show['type']); ?> show" style="position: static; transform: none; margin-bottom: 1rem;">
                        <?php echo sanitize_html_output($checkout_message_to_show['text']); ?>
                    </div>
                <?php endif; ?>

                <form id="checkout-form" method="POST" action="checkout.php">
                    <input type="hidden" name="action" value="place_order">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="checkout-layout">
                        <div class="checkout-main">
                            <section class="checkout-section address-selection-group" id="checkout-shipping-address">
                                <h3>Adresse de Livraison</h3>
                                <?php if (!empty($user_addresses_display)): ?>
                                    <?php foreach($user_addresses_display as $addr): ?>
                                        <div class="address-option">
                                            <input type="radio" name="shipping_address_id" value="<?php echo $addr['id_adresse']; ?>" id="ship_addr_<?php echo $addr['id_adresse']; ?>" <?php if($addr['id_adresse'] == $default_shipping_id_display) echo 'checked'; ?> required>
                                            <label for="ship_addr_<?php echo $addr['id_adresse']; ?>">
                                                <strong><?php echo sanitize_html_output($addr['type_adresse'] ?: $addr['destinataire_nom_complet']); ?></strong><br>
                                                <?php echo sanitize_html_output($addr['adresse_ligne1'] . ($addr['adresse_ligne2'] ? ', '.$addr['adresse_ligne2'] : '')); ?><br>
                                                <?php echo sanitize_html_output($addr['code_postal'] . ' ' . $addr['ville'] . ', ' . $addr['pays']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Vous n'avez pas d'adresse de livraison enregistrée.</p>
                                <?php endif; ?>
                                <span class="toggle-address-form-link" onclick="toggleAddressForm()">Ajouter une nouvelle adresse de livraison</span>
                                <a href="dashboard.php#dashboard-addresses-content" style="margin-left:1rem;">Gérer mes adresses (Tableau de bord)</a>

                                <div id="add-new-address-form-checkout" style="display:none;">
                                    <h4>Ajouter une nouvelle adresse</h4>
                                    <form method="POST" action="checkout.php"> <!-- This form will be part of the main checkout form, or submitted via JS -->
                                        <input type="hidden" name="action" value="add_address">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <div class="form-group">
                                            <label for="destinataire_nom_complet">Nom complet du destinataire</label>
                                            <input type="text" name="destinataire_nom_complet" id="destinataire_nom_complet" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="adresse_ligne1">Adresse Ligne 1</label>
                                            <input type="text" name="adresse_ligne1" id="adresse_ligne1" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="adresse_ligne2">Adresse Ligne 2 (Optionnel)</label>
                                            <input type="text" name="adresse_ligne2" id="adresse_ligne2">
                                        </div>
                                        <div class="form-group">
                                            <label for="code_postal">Code Postal</label>
                                            <input type="text" name="code_postal" id="code_postal" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="ville">Ville</label>
                                            <input type="text" name="ville" id="ville" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="pays">Pays</label>
                                            <input type="text" name="pays" id="pays" value="France" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="telephone">Téléphone (Optionnel)</label>
                                            <input type="tel" name="telephone" id="telephone">
                                        </div>
                                        <div class="form-group">
                                            <label for="type_adresse">Type d'adresse (ex: Domicile, Travail)</label>
                                            <input type="text" name="type_adresse" id="type_adresse" placeholder="Domicile">
                                        </div>
                                        <div class="form-actions">
                                             <button type="button" class="cta-button secondary" onclick="toggleAddressForm()">Annuler</button>
                                            <button type="submit" class="cta-button">Enregistrer l'adresse</button>
                                        </div>
                                    </form>
                                </div>
                            </section>

                            <section class="checkout-section address-selection-group" id="checkout-billing-address">
                                <h3>Adresse de Facturation</h3>
                                <div class="billing-same-as-shipping">
                                    <input type="checkbox" id="billing_same_as_shipping" name="billing_same_as_shipping" value="1" checked onchange="toggleBillingAddressSelection()">
                                    <label for="billing_same_as_shipping">Utiliser la même adresse pour la facturation</label>
                                </div>
                                <div id="billing-address-options" style="display:none;">
                                    <?php if (!empty($user_addresses_display)): ?>
                                        <?php foreach($user_addresses_display as $addr): ?>
                                            <div class="address-option">
                                                <input type="radio" name="billing_address_id" value="<?php echo $addr['id_adresse']; ?>" id="bill_addr_<?php echo $addr['id_adresse']; ?>" <?php if($addr['id_adresse'] == $default_billing_id_display ) echo 'checked';?> >
                                                <label for="bill_addr_<?php echo $addr['id_adresse']; ?>">
                                                    <strong><?php echo sanitize_html_output($addr['type_adresse'] ?: $addr['destinataire_nom_complet']); ?></strong><br>
                                                    <?php echo sanitize_html_output($addr['adresse_ligne1'] . ($addr['adresse_ligne2'] ? ', '.$addr['adresse_ligne2'] : '')); ?><br>
                                                    <?php echo sanitize_html_output($addr['code_postal'] . ' ' . $addr['ville'] . ', ' . $addr['pays']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>Vous n'avez pas d'adresse de facturation enregistrée.</p>
                                    <?php endif; ?>
                                    <a href="dashboard.php#dashboard-addresses-content">Gérer mes adresses</a>
                                </div>
                            </section>

                            <section class="checkout-section" id="checkout-payment">
                                <h2>Méthode de Paiement</h2>
                                <p>Les options de paiement seront bientôt disponibles. Votre commande sera enregistrée avec le statut "En attente de paiement".</p>
                            </section>
                        </div>

                        <aside class="checkout-sidebar">
                            <section class="checkout-section" id="checkout-summary">
                                <h2>Récapitulatif</h2>
                                <?php if (!empty($cart_items_details_display)): ?>
                                    <table class="order-summary-table">
                                        <thead><tr><th colspan="2">Produit</th><th>Qté</th><th>Total</th></tr></thead>
                                        <tbody>
                                            <?php foreach($cart_items_details_display as $item): ?>
                                            <tr>
                                                <td><img src="<?php echo sanitize_html_output($item['image'] ?: 'https://placehold.co/50x50'); ?>" alt="<?php echo sanitize_html_output($item['nom']); ?>"></td>
                                                <td><?php echo sanitize_html_output($item['nom']); ?><br><small><?php echo $item['prix_unitaire_formate']; ?></small></td>
                                                <td><?php echo sanitize_html_output($item['quantite']); ?></td>
                                                <td><?php echo $item['total_ligne_formate']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="order-totals">
                                        <p><span>Sous-total :</span> <span><?php echo sanitize_html_output(number_format($subtotal_display, 2, ',', ' ')); ?> €</span></p>
                                        <p><span>Livraison :</span> <span><?php echo sanitize_html_output(number_format($shipping_cost_display, 2, ',', ' ')); ?> €</span></p>
                                        <hr>
                                        <p class="grand-total"><span>TOTAL TTC :</span> <span><?php echo sanitize_html_output(number_format($grand_total_display, 2, ',', ' ')); ?> €</span></p>
                                    </div>
                                <?php else: ?>
                                    <p>Votre panier est vide.</p>
                                <?php endif; ?>
                                <button type="submit" class="cta-button" style="width:100%; margin-top:1.5rem;" <?php if(empty($cart_items_details_display) || empty($user_addresses_display)) echo 'disabled';?>>
                                    Confirmer la commande
                                </button>
                                <?php if(empty($user_addresses_display)): ?>
                                    <p style="font-size:0.8em; color:var(--accent-primary); text-align:center; margin-top:0.5rem;">Veuillez ajouter une adresse de livraison pour continuer.</p>
                                <?php endif; ?>
                            </section>
                        </aside>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer id="main-footer"><!-- Standard Footer HTML --></footer>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="js/main.js"></script>
    <script>
        function toggleAddressForm() {
            const formDiv = document.getElementById('add-new-address-form-checkout');
            const toggleLink = document.querySelector('.toggle-address-form-link');
            if (formDiv.style.display === 'none') {
                formDiv.style.display = 'block';
                toggleLink.textContent = 'Masquer le formulaire d\'ajout';
            } else {
                formDiv.style.display = 'none';
                toggleLink.textContent = 'Ajouter une nouvelle adresse de livraison';
            }
        }

        function toggleBillingAddressSelection() {
            const checkbox = document.getElementById('billing_same_as_shipping');
            const billingOptionsDiv = document.getElementById('billing-address-options');
            const billingRadios = document.getElementsByName('billing_address_id');

            if (checkbox.checked) {
                billingOptionsDiv.style.display = 'none';
                billingRadios.forEach(radio => radio.required = false);
            } else {
                billingOptionsDiv.style.display = 'block';
                let oneBillingChecked = false;
                billingRadios.forEach(radio => { if(radio.checked) oneBillingChecked = true; });

                if(!oneBillingChecked && billingRadios.length > 0) {
                    <?php if ($default_billing_id_display !== null): ?>
                    const defaultBillingRadio = document.getElementById('bill_addr_<?php echo $default_billing_id_display; ?>');
                    if (defaultBillingRadio) defaultBillingRadio.checked = true;
                    else if (billingRadios.length > 0) billingRadios[0].checked = true;
                    <?php elseif (count($user_addresses_display) > 0): ?>
                    if (billingRadios.length > 0) billingRadios[0].checked = true;
                    <?php endif; ?>
                }
                if (billingRadios.length > 0) {
                    billingRadios.forEach(radio => radio.required = true);
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            toggleBillingAddressSelection();

            const shippingRadios = document.getElementsByName('shipping_address_id');
            let oneShippingChecked = false;
            shippingRadios.forEach(radio => { if(radio.checked) oneShippingChecked = true; });
            if(!oneShippingChecked && shippingRadios.length > 0) {
                <?php if ($default_shipping_id_display !== null): ?>
                const defaultShippingRadio = document.getElementById('ship_addr_<?php echo $default_shipping_id_display; ?>');
                if (defaultShippingRadio) defaultShippingRadio.checked = true;
                else if (shippingRadios.length > 0) shippingRadios[0].checked = true;
                <?php elseif (count($user_addresses_display) > 0): ?>
                 if (shippingRadios.length > 0) shippingRadios[0].checked = true;
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>
