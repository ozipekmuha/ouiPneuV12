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
require_once 'includes/functions.php'; // Pour sanitize_html_output, getUserAddresses, getUserOrdersSummary

$client_id = $_GET['id_client'] ?? null;
$client_details = null;
$client_addresses = [];
$client_orders = [];
$admin_message_display_client = null;

if (!$client_id || !filter_var($client_id, FILTER_VALIDATE_INT)) {
    $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Aucun ID de client valide fourni.'];
    header('Location: admin_dashboard.php#admin-clients-content');
    exit;
}

// Récupération des détails du client
try {
    $sql_client = "SELECT u.id_utilisateur, u.nom_utilisateur, u.prenom_utilisateur, u.email_utilisateur,
                          u.telephone_utilisateur, u.date_creation_compte, u.derniere_connexion
                   FROM Utilisateurs u
                   WHERE u.id_utilisateur = :id_client AND (u.est_admin = 0 OR u.est_admin IS NULL)";
    $stmt_client = $pdo->prepare($sql_client);
    $stmt_client->bindParam(':id_client', $client_id, PDO::PARAM_INT);
    $stmt_client->execute();
    $client_details = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client_details) {
        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Client non trouvé ou non autorisé.'];
        header('Location: admin_dashboard.php#admin-clients-content');
        exit;
    }

    $client_addresses = getUserAddresses($pdo, $client_id);
    $client_orders = getUserOrdersSummary($pdo, $client_id);

} catch (PDOException $e) {
    error_log("Erreur Admin - Détail Client ID $client_id: " . $e->getMessage());
    $admin_message_display_client = ['type' => 'error', 'text' => 'Erreur de chargement des détails du client.'];
}

$page_title = $client_details ? "Détail Client #" . sanitize_html_output($client_id) : "Détail Client";

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
        /* Styles similaires à admin_order_detail.php et admin_dashboard.php */
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

        .client-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .detail-card { background-color: var(--bg-surface); padding: 1.5rem; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.2); }
        .detail-card h2 { font-size: 1.2rem; color: var(--accent-primary); margin-top: 0; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .detail-card p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.5rem; line-height: 1.6; }
        .detail-card p strong { color: var(--text-light); font-weight: 500; }
        .detail-card address { font-style: normal; margin-bottom: 0.8rem; padding-bottom:0.8rem; border-bottom: 1px dashed var(--border-color); }
        .detail-card address:last-child{ border-bottom: none; margin-bottom:0; padding-bottom:0;}

        .admin-table-container { background-color: var(--bg-surface); padding: 1.5rem; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.2); margin-bottom: 2rem; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
        .admin-table th { color: var(--text-light); font-weight: 600; background-color: rgba(0,0,0,0.1); }
        .admin-table td { color: var(--text-secondary); }
        .admin-table .status-shipped, .admin-table .status-livrée { color: #2ecc71; } /* Vert */
        .admin-table .status-en-traitement, .admin-table .status-en-attente { color: #f39c12; } /* Orange */
        .admin-table .status-annulée, .admin-table .status-remboursée { color: #e74c3c; } /* Rouge */

        .client-actions { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; flex-wrap: wrap; }
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
            .admin-table-container { overflow-x: auto; padding:1rem; }
            .client-details-grid { grid-template-columns: 1fr; }
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
                    <li><a href="admin_dashboard.php#admin-orders-content"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                    <li><a href="admin_dashboard.php#admin-products-content-NEW"><i class="fas fa-box-open"></i> Produits</a></li>
                    <li><a href="admin_dashboard.php#admin-promo-codes-content"><i class="fas fa-percentage"></i> Codes Promo</a></li>
                    <li><a href="admin_dashboard.php#admin-clients-content" class="active"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="admin_dashboard.php#admin-settings-content"><i class="fas fa-cog"></i> Paramètres</a></li>
                </ul>
            </nav>
        </aside>

        <main class="admin-content-area">
            <div class="admin-content-header">
                <h1><?php echo $page_title; ?></h1>
                <div class="breadcrumb">
                    <a href="admin_dashboard.php#admin-clients-content">Clients</a> / Détail #<?php echo sanitize_html_output($client_id); ?>
                </div>
            </div>

            <?php if (!empty($admin_message_display_client)): ?>
                <div class="global-notification-bar <?php echo sanitize_html_output($admin_message_display_client['type']); ?>">
                    <?php echo sanitize_html_output($admin_message_display_client['text']); ?>
                </div>
            <?php endif; ?>

            <?php if ($client_details): ?>
            <div class="client-details-grid">
                <div class="detail-card client-info-card">
                    <h2>Informations Personnelles</h2>
                    <p><strong>ID Client:</strong> <?php echo sanitize_html_output($client_details['id_utilisateur']); ?></p>
                    <p><strong>Nom:</strong> <?php echo sanitize_html_output(trim($client_details['prenom_utilisateur'] . ' ' . $client_details['nom_utilisateur'])); ?></p>
                    <p><strong>Email:</strong> <?php echo sanitize_html_output($client_details['email_utilisateur']); ?></p>
                    <p><strong>Téléphone:</strong> <?php echo sanitize_html_output($client_details['telephone_utilisateur'] ?: 'Non fourni'); ?></p>
                    <p><strong>Date d'inscription:</strong> <?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($client_details['date_creation_compte']))); ?></p>
                    <p><strong>Dernière connexion:</strong> <?php echo $client_details['derniere_connexion'] ? sanitize_html_output(date("d/m/Y H:i", strtotime($client_details['derniere_connexion']))) : 'Jamais connecté'; ?></p>
                </div>

                <div class="detail-card addresses-card">
                    <h2>Adresses Enregistrées</h2>
                    <?php if (!empty($client_addresses)): ?>
                        <?php foreach($client_addresses as $address): ?>
                            <address>
                                <strong><?php echo sanitize_html_output($address['type_adresse'] == 'livraison' ? 'Livraison' : 'Facturation'); ?>
                                <?php if($address['est_principale_livraison'] || $address['est_principale_facturation']) echo " (Principale)"; ?>
                                </strong><br>
                                <?php echo sanitize_html_output($address['destinataire_nom_complet']); ?><br>
                                <?php echo sanitize_html_output($address['adresse_ligne1']); ?><br>
                                <?php if(!empty($address['adresse_ligne2'])) echo sanitize_html_output($address['adresse_ligne2']) . '<br>'; ?>
                                <?php echo sanitize_html_output($address['code_postal'] . ' ' . $address['ville']); ?><br>
                                <?php echo sanitize_html_output($address['pays']); ?><br>
                                <?php if(!empty($address['telephone'])) echo 'Tél: ' . sanitize_html_output($address['telephone']); ?>
                            </address>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aucune adresse enregistrée pour ce client.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-table-container orders-history-card">
                <h2>Historique des Commandes</h2>
                <?php if (!empty($client_orders)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID Commande</th>
                            <th>Date</th>
                            <th class="text-right">Montant Total</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($client_orders as $order): ?>
                            <tr>
                                <td><?php echo sanitize_html_output(strtoupper($order['id_commande'])); ?></td>
                                <td><?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($order['date_commande']))); ?></td>
                                <td class="text-right"><?php echo sanitize_html_output(number_format($order['montant_total_ttc'], 2, ',', ' ')) . ' €'; ?></td>
                                <td>
                                    <span class="status-<?php echo sanitize_html_output(strtolower(str_replace(' ', '-', $order['statut_commande']))); ?>">
                                        <?php echo sanitize_html_output(ucfirst($order['statut_commande'])); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="admin_order_detail.php?id_commande=<?php echo urlencode($order['id_commande']); ?>" class="admin-action-btn view-btn">Voir Commande</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>Aucune commande trouvée pour ce client.</p>
                <?php endif; ?>
            </div>

            <div class="client-actions">
                <a href="mailto:<?php echo sanitize_html_output($client_details['email_utilisateur']); ?>?subject=Concernant votre compte Ouipneu.fr" class="cta-button secondary"><i class="fas fa-envelope"></i> Contacter le Client</a>
                <!-- TODO: Ajouter des actions comme "Modifier le client", "Réinitialiser le mot de passe", "Désactiver le compte" -->
            </div>

            <?php elseif (empty($admin_message_display_client['text'])) : ?>
                <p class="error-message">Les détails de ce client ne sont pas disponibles ou le client n'existe pas.</p>
            <?php endif; ?>
        </main>
    </div>

    <footer class="admin-footer">
        <p>&copy; <span id="current-year-admin-client"></span> Ouipneu.fr - Interface d'Administration</p>
    </footer>
    <script>
        document.getElementById('current-year-admin-client').textContent = new Date().getFullYear();
        const notification = document.querySelector('.global-notification-bar');
        if (notification) {
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
