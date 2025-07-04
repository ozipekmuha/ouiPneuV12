<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Protection Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Pour sanitize_html_output et getOrderLineItems

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$order_id = $_GET['id_commande'] ?? null;
$order_details = null;
$order_items = [];
$admin_message_display_order = null;

if (!$order_id) {
    $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Aucun ID de commande fourni.'];
    header('Location: admin_dashboard.php#admin-orders-content');
    exit;
}

// Traitement de la mise à jour du statut
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_order_status') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $admin_message_display_order = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF). Action annulée.'];
    } else {
        $new_status = $_POST['order_status'] ?? '';
        $order_id_update = $_POST['order_id_update'] ?? '';

        if ($order_id_update == $order_id && !empty($new_status)) {
            $allowed_statuses = ['En attente', 'En traitement', 'Expédiée', 'Livrée', 'Annulée', 'Remboursée'];
            if (in_array($new_status, $allowed_statuses)) {
                try {
                    $stmt_update = $pdo->prepare("UPDATE Commandes SET statut_commande = :statut WHERE id_commande = :id_commande");
                    if ($stmt_update->execute([':statut' => $new_status, ':id_commande' => $order_id])) {
                        $_SESSION['admin_message_order_detail'] = ['type' => 'success', 'text' => 'Statut de la commande mis à jour avec succès.'];
                    } else {
                        $_SESSION['admin_message_order_detail'] = ['type' => 'error', 'text' => 'Erreur lors de la mise à jour du statut.'];
                    }
                } catch (PDOException $e) {
                    error_log("Erreur PDO update_order_status: " . $e->getMessage());
                    $_SESSION['admin_message_order_detail'] = ['type' => 'error', 'text' => 'Erreur base de données lors de la mise à jour.'];
                }
            } else {
                $_SESSION['admin_message_order_detail'] = ['type' => 'error', 'text' => 'Statut de commande non valide.'];
            }
        } else {
            $_SESSION['admin_message_order_detail'] = ['type' => 'error', 'text' => 'Données de mise à jour invalides.'];
        }
        header("Location: admin_order_detail.php?id_commande=" . urlencode($order_id));
        exit;
    }
}

if (isset($_SESSION['admin_message_order_detail'])) {
    $admin_message_display_order = $_SESSION['admin_message_order_detail'];
    unset($_SESSION['admin_message_order_detail']);
}

try {
    $sql_order = "SELECT c.*,
                         u.nom_utilisateur, u.prenom_utilisateur, u.email_utilisateur,
                         adr_liv.destinataire_nom_complet AS livraison_nom, adr_liv.adresse_ligne1 AS livraison_l1, adr_liv.adresse_ligne2 AS livraison_l2, adr_liv.code_postal AS livraison_cp, adr_liv.ville AS livraison_ville, adr_liv.pays AS livraison_pays, adr_liv.telephone AS livraison_tel,
                         adr_fact.destinataire_nom_complet AS facturation_nom, adr_fact.adresse_ligne1 AS facturation_l1, adr_fact.adresse_ligne2 AS facturation_l2, adr_fact.code_postal AS facturation_cp, adr_fact.ville AS facturation_ville, adr_fact.pays AS facturation_pays, adr_fact.telephone AS facturation_tel
                  FROM Commandes c
                  LEFT JOIN Utilisateurs u ON c.id_utilisateur = u.id_utilisateur
                  LEFT JOIN Adresses adr_liv ON c.id_adresse_livraison = adr_liv.id_adresse
                  LEFT JOIN Adresses adr_fact ON c.id_adresse_facturation = adr_fact.id_adresse
                  WHERE c.id_commande = :id_commande";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->bindParam(':id_commande', $order_id, PDO::PARAM_STR);
    $stmt_order->execute();
    $order_details = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order_details) {
        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Commande non trouvée.'];
        header('Location: admin_dashboard.php#admin-orders-content');
        exit;
    }
    $order_items = getOrderLineItems($pdo, $order_id);
} catch (PDOException $e) {
    error_log("Erreur Admin - Détail Commande ID $order_id: " . $e->getMessage());
    $admin_message_display_order = ['type' => 'error', 'text' => 'Erreur de chargement des détails de la commande.'];
}

$page_title = $order_details ? "Détail Commande #" . sanitize_html_output(strtoupper($order_id)) : "Détail Commande";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Ouipneu.fr</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --bg-dark: #121212; --bg-surface: #1e1e1e; --text-light: #e0e0e0; --text-secondary: #b0b0b0;
            --accent-primary: #ffdd03; --text-on-accent: #1a1a1a; --border-color: #333333;
        }
        body { display: flex; flex-direction: column; min-height: 100vh; background-color: var(--bg-dark); color: var(--text-light); font-family: 'Poppins', sans-serif; margin: 0; }
        .admin-header { background-color: var(--bg-surface); padding: 0.8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .admin-header .logo img { max-width: 150px; filter: invert(1) brightness(1.8) contrast(1.1); }
        .admin-header .admin-user-info a { color: var(--accent-primary); text-decoration: none; font-weight: 500; }
        .admin-main-layout { display: flex; flex-grow: 1; }
        .admin-sidebar { width: 250px; background-color: var(--bg-surface); padding: 1.5rem 1rem; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .admin-sidebar nav ul { list-style: none; padding: 0; margin: 0; }
        .admin-sidebar nav ul li a { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; color: var(--text-secondary); text-decoration: none; border-radius: 4px; margin-bottom: 0.5rem; transition: background-color 0.2s ease, color 0.2s ease; font-weight: 500; }
        .admin-sidebar nav ul li a i { width: 20px; text-align: center; }
        .admin-sidebar nav ul li a:hover, .admin-sidebar nav ul li a.active { background-color: var(--accent-primary); color: var(--text-on-accent); }
        .admin-sidebar nav ul li a.active i { color: var(--text-on-accent); }
        .admin-content-area { flex-grow: 1; padding: 2rem; overflow-y: auto; }
        .admin-content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; }
        .admin-content-header h1 { font-size: 1.8rem; color: var(--accent-primary); margin: 0; }
        .admin-content-header .breadcrumb { font-size: 0.9rem; color: var(--text-secondary); }
        .admin-content-header .breadcrumb a { color: var(--accent-primary); text-decoration: none; }
        .admin-content-header .breadcrumb a:hover { text-decoration: underline; }
        .order-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .detail-card { background-color: var(--bg-surface); padding: 1.5rem; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.2); }
        .detail-card h2 { font-size: 1.2rem; color: var(--accent-primary); margin-top: 0; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .detail-card p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem; line-height: 1.6; }
        .detail-card p strong { color: var(--text-light); font-weight: 500; }
        .detail-card address { font-style: normal; }
        .detail-card .form-group { margin-top: 1rem; }
        .detail-card label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .detail-card select, .detail-card input[type="text"] { width: 100%; padding: 0.6rem 0.8rem; background-color: var(--bg-dark); color: var(--text-light); border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.9rem; box-sizing: border-box; }
        .admin-table-container { background-color: var(--bg-surface); padding: 1.5rem; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.2); margin-bottom: 2rem; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
        .admin-table th { color: var(--text-light); font-weight: 600; background-color: rgba(0,0,0,0.1); }
        .admin-table td { color: var(--text-secondary); }
        .admin-table img.product-thumbnail { width: 60px; height: auto; max-height: 50px; object-fit: contain; border-radius: 4px; background-color: var(--bg-dark); margin-right: 10px; vertical-align: middle; }
        .admin-table .product-name-cell { color: var(--text-light); }
        .admin-table .text-right { text-align: right; }
        .order-summary-table { width: 100%; max-width: 400px; margin-left: auto; }
        .order-summary-table td { padding: 0.5rem 0; }
        .order-summary-table .summary-label { color: var(--text-secondary); }
        .order-summary-table .summary-value { color: var(--text-light); font-weight: 600; text-align: right; }
        .order-summary-table .summary-total .summary-value { color: var(--accent-primary); font-size: 1.1em; }
        .order-actions { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .order-actions .form-group { margin-top: 0; margin-bottom: 0; flex-grow:1; max-width: 300px;}
        .admin-footer { text-align: center; padding: 1rem; font-size: 0.85rem; color: var(--text-secondary); border-top: 1px solid var(--border-color); background-color: var(--bg-surface); }
        .global-notification-bar { padding: 0.8rem 1.2rem; margin-bottom: 1rem; border-radius: 5px; font-size: 0.9rem; border: 1px solid transparent; text-align: center; }
        .global-notification-bar.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb;}
        .global-notification-bar.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;}
        @media (max-width: 768px) {
            .admin-main-layout { flex-direction: column; }
            .admin-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border-color); padding: 1rem; }
            .admin-sidebar nav ul { display: flex; overflow-x: auto; }
            .admin-sidebar nav ul li { flex-shrink: 0; }
            .admin-sidebar nav ul li a { margin-bottom: 0; margin-right: 0.5rem; padding: 0.7rem 0.9rem; }
            .admin-content-area { padding: 1.5rem 1rem; }
            .admin-table th, .admin-table td { font-size: 0.85rem; padding: 0.6rem 0.8rem; }
            .admin-table-container { overflow-x: auto; padding: 1rem; }
            .order-details-grid { grid-template-columns: 1fr; }
            .order-actions { flex-direction: column; align-items: stretch;}
            .order-actions .form-group {max-width: none;}
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="logo">
             <a href="admin_dashboard.php"><img src="assets/images/logobg.png" alt="Logo Ouipneu.fr"></a>
        </div>
        <div class="admin-user-info">
            <span><?php echo isset($_SESSION['admin_username']) ? sanitize_html_output($_SESSION['admin_username']) : 'Admin'; ?> | <a href="admin_logout.php">Déconnexion</a></span>
        </div>
    </header>
    <div class="admin-main-layout">
        <aside class="admin-sidebar">
            <nav>
                <ul>
                    <li><a href="admin_dashboard.php#admin-dashboard-main"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                    <li><a href="admin_dashboard.php#admin-orders-content" class="active"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                    <li><a href="admin_dashboard.php#admin-products-content-NEW"><i class="fas fa-box-open"></i> Produits</a></li>
                    <li><a href="admin_dashboard.php#admin-promo-codes-content"><i class="fas fa-percentage"></i> Codes Promo</a></li>
                    <li><a href="admin_dashboard.php#admin-clients-content"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="admin_dashboard.php#admin-settings-content"><i class="fas fa-cog"></i> Paramètres</a></li>
                </ul>
            </nav>
        </aside>
        <main class="admin-content-area">
            <div class="admin-content-header">
                <h1><?php echo $page_title; ?></h1>
                <div class="breadcrumb">
                    <a href="admin_dashboard.php#admin-orders-content">Commandes</a> / Détail #<?php echo sanitize_html_output(strtoupper($order_id)); ?>
                </div>
            </div>
            <?php if (!empty($admin_message_display_order)): ?>
                <div class="global-notification-bar <?php echo sanitize_html_output($admin_message_display_order['type']); ?>">
                    <?php echo sanitize_html_output($admin_message_display_order['text']); ?>
                </div>
            <?php endif; ?>
            <?php if ($order_details): ?>
            <div class="order-details-grid">
                <div class="detail-card order-info-card">
                    <h2>Informations Commande</h2>
                    <p><strong>ID Commande:</strong> <?php echo sanitize_html_output(strtoupper($order_details['id_commande'])); ?></p>
                    <p><strong>Date:</strong> <?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($order_details['date_commande']))); ?></p>
                    <p><strong>Client:</strong>
                        <?php
                        $client_display_name = $order_details['prenom_utilisateur'] || $order_details['nom_utilisateur']
                            ? trim(sanitize_html_output($order_details['prenom_utilisateur'] . ' ' . $order_details['nom_utilisateur']))
                            : 'N/A';
                        echo $client_display_name;
                        if (!empty($order_details['email_utilisateur'])) {
                            echo " (" . sanitize_html_output($order_details['email_utilisateur']) . ")";
                        }
                        ?>
                    </p>
                    <p><strong>Montant Total TTC:</strong> <?php echo sanitize_html_output(number_format($order_details['montant_total_ttc'], 2, ',', ' ')) . ' €'; ?></p>
                     <form method="POST" action="admin_order_detail.php?id_commande=<?php echo urlencode($order_id); ?>" style="margin-top: 1rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id_update" value="<?php echo sanitize_html_output($order_id); ?>">
                        <div class="form-group">
                            <label for="order-status"><strong>Statut Actuel:</strong></label>
                            <select id="order-status" name="order_status">
                                <?php
                                $statuses = ['En attente', 'En traitement', 'Expédiée', 'Livrée', 'Annulée', 'Remboursée'];
                                foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($order_details['statut_commande'] == $status) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="cta-button" style="margin-top: 0.5rem;"><i class="fas fa-save"></i> Mettre à jour Statut</button>
                    </form>
                </div>
                <div class="detail-card shipping-info-card">
                    <h2>Adresse de Livraison</h2>
                    <address>
                        <strong><?php echo sanitize_html_output($order_details['livraison_nom'] ?: $client_display_name); ?></strong><br>
                        <?php echo sanitize_html_output($order_details['livraison_l1']); ?><br>
                        <?php if(!empty($order_details['livraison_l2'])) echo sanitize_html_output($order_details['livraison_l2']) . '<br>'; ?>
                        <?php echo sanitize_html_output($order_details['livraison_cp'] . ' ' . $order_details['livraison_ville']); ?><br>
                        <?php echo sanitize_html_output($order_details['livraison_pays']); ?><br>
                        <?php if(!empty($order_details['livraison_tel'])) echo 'Téléphone: ' . sanitize_html_output($order_details['livraison_tel']); ?>
                    </address>
                </div>
                <div class="detail-card billing-info-card">
                    <h2>Adresse de Facturation</h2>
                     <?php if ($order_details['id_adresse_facturation'] == $order_details['id_adresse_livraison'] && $order_details['id_adresse_facturation'] !== null): ?>
                        <p>Identique à l'adresse de livraison.</p>
                    <?php elseif ($order_details['id_adresse_facturation'] === null && $order_details['id_adresse_livraison'] !== null): ?>
                         <p>Non spécifiée (probablement identique à la livraison ou client invité sans adresse de facturation séparée).</p>
                    <?php elseif ($order_details['id_adresse_facturation'] !== null): ?>
                    <address>
                        <strong><?php echo sanitize_html_output($order_details['facturation_nom'] ?: $client_display_name); ?></strong><br>
                        <?php echo sanitize_html_output($order_details['facturation_l1']); ?><br>
                        <?php if(!empty($order_details['facturation_l2'])) echo sanitize_html_output($order_details['facturation_l2']) . '<br>'; ?>
                        <?php echo sanitize_html_output($order_details['facturation_cp'] . ' ' . $order_details['facturation_ville']); ?><br>
                        <?php echo sanitize_html_output($order_details['facturation_pays']); ?><br>
                        <?php if(!empty($order_details['facturation_tel'])) echo 'Téléphone: ' . sanitize_html_output($order_details['facturation_tel']); ?>
                    </address>
                    <?php else: ?>
                        <p>Adresse de facturation non disponible.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-table-container items-ordered-card">
                <h2>Articles Commandés</h2>
                <?php if (!empty($order_items)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Image</th>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th class="text-right">Prix Unitaire TTC</th>
                            <th class="text-right">Total Ligne TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><img src="<?php echo sanitize_html_output(!empty($item['image']) ? $item['image'] : 'https://placehold.co/80x60/1e1e1e/ffdd03?text=Pneu'); ?>" alt="<?php echo sanitize_html_output($item['nom_produit_commande']); ?>" class="product-thumbnail"></td>
                                <td class="product-name-cell">
                                    <?php echo sanitize_html_output($item['nom_produit_commande']); ?><br>
                                    <small><?php echo sanitize_html_output($item['taille_produit_commande']); ?></small>
                                </td>
                                <td><?php echo sanitize_html_output($item['quantite']); ?></td>
                                <td class="text-right"><?php echo sanitize_html_output(number_format($item['prix_unitaire_ttc_calc'], 2, ',', ' ')) . ' €'; ?></td>
                                <td class="text-right"><?php echo sanitize_html_output(number_format($item['total_ligne_ttc_calc'], 2, ',', ' ')) . ' €'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>Aucun article trouvé pour cette commande.</p>
                <?php endif; ?>
            </div>
            <div class="detail-card order-financial-summary">
                 <h2>Récapitulatif Financier</h2>
                 <table class="order-summary-table">
                     <tbody>
                         <tr>
                             <td class="summary-label">Sous-total Articles (TTC):</td>
                             <td class="summary-value"><?php echo sanitize_html_output(number_format($order_details['montant_sous_total_ttc'] ?? $order_details['montant_total_ttc'] - ($order_details['montant_livraison'] ?? 0) + ($order_details['montant_reduction'] ?? 0), 2, ',', ' ')) . ' €'; ?></td>
                         </tr>
                         <?php if (isset($order_details['montant_livraison']) && $order_details['montant_livraison'] > 0): ?>
                         <tr>
                             <td class="summary-label">Frais de Livraison (TTC):</td>
                             <td class="summary-value"><?php echo sanitize_html_output(number_format($order_details['montant_livraison'], 2, ',', ' ')) . ' €'; ?></td>
                         </tr>
                         <?php endif; ?>
                         <?php if (isset($order_details['montant_reduction']) && $order_details['montant_reduction'] > 0): ?>
                         <tr>
                             <td class="summary-label">Réduction Appliquée:</td>
                             <td class="summary-value">- <?php echo sanitize_html_output(number_format($order_details['montant_reduction'], 2, ',', ' ')) . ' €'; ?></td>
                         </tr>
                         <?php endif; ?>
                         <?php if (isset($order_details['montant_tva_total']) && $order_details['montant_tva_total'] > 0): ?>
                         <tr>
                             <td class="summary-label">Dont TVA (Total):</td>
                             <td class="summary-value"><?php echo sanitize_html_output(number_format($order_details['montant_tva_total'], 2, ',', ' ')) . ' €'; ?></td>
                         </tr>
                         <?php endif; ?>
                         <tr class="summary-total">
                             <td class="summary-label">Total Commande (TTC):</td>
                             <td class="summary-value"><?php echo sanitize_html_output(number_format($order_details['montant_total_ttc'], 2, ',', ' ')) . ' €'; ?></td>
                         </tr>
                     </tbody>
                 </table>
            </div>
            <div class="order-actions">
                <button type="button" class="cta-button secondary" onclick="window.print();"><i class="fas fa-print"></i> Imprimer (Basique)</button>
                <?php if (!empty($order_details['email_utilisateur'])): ?>
                <a href="mailto:<?php echo sanitize_html_output($order_details['email_utilisateur']); ?>?subject=Concernant votre commande <?php echo sanitize_html_output(strtoupper($order_id)); ?>" class="cta-button secondary"><i class="fas fa-envelope"></i> Contacter le Client</a>
                <?php endif; ?>
            </div>
            <?php elseif (empty($admin_message_display_order['text'])) : ?>
                <p class="error-message">Les détails de cette commande ne sont pas disponibles ou la commande n'existe pas.</p>
            <?php endif; ?>
        </main>
    </div>
    <footer class="admin-footer">
        <p>&copy; <span id="current-year-admin-order"></span> Ouipneu.fr - Interface d'Administration</p>
    </footer>
    <script>
        document.getElementById('current-year-admin-order').textContent = new Date().getFullYear();
        const notification = document.querySelector('.global-notification-bar');
        if (notification) {
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
