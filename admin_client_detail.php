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
require_once 'includes/functions.php';

$client_id = filter_input(INPUT_GET, 'id_client', FILTER_VALIDATE_INT);
$client_details = null;
$client_commandes = [];
$client_adresses = [];
$page_title = "Détails du Client";
$error_message = '';

if (!$client_id) {
    $error_message = "ID client invalide ou manquant.";
} else {
    try {
        // Récupérer les détails du client
        $stmt_client = $pdo->prepare("SELECT * FROM Utilisateurs WHERE id_utilisateur = :id_client");
        $stmt_client->execute([':id_client' => $client_id]);
        $client_details = $stmt_client->fetch(PDO::FETCH_ASSOC);

        if (!$client_details) {
            $error_message = "Client non trouvé.";
        } else {
            $page_title = "Détails Client: " . sanitize_html_output($client_details['prenom'] . " " . $client_details['nom']);

            // Récupérer les commandes du client
            // Assurez-vous que les colonnes id_commande, date_commande, montant_total, statut_commande existent dans votre table Commandes
            $stmt_commandes = $pdo->prepare("SELECT id_commande, date_commande, montant_total, statut_commande FROM Commandes WHERE id_utilisateur = :id_client ORDER BY date_commande DESC");
            $stmt_commandes->execute([':id_client' => $client_id]);
            $client_commandes = $stmt_commandes->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les adresses du client
            // Assurez-vous que les colonnes type_adresse, rue, code_postal, ville, pays, telephone, est_principale_livraison, est_principale_facturation existent
            $stmt_adresses = $pdo->prepare("SELECT type_adresse, rue, code_postal, ville, pays, telephone, est_principale_livraison, est_principale_facturation FROM Adresses WHERE id_utilisateur = :id_client");
            $stmt_adresses->execute([':id_client' => $client_id]);
            $client_adresses = $stmt_adresses->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Erreur admin_client_detail: " . $e->getMessage());
        $error_message = "Une erreur est survenue lors de la récupération des informations du client.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize_html_output($page_title); ?> - Admin Ouipneu.fr</title>
    <link rel="stylesheet" href="css/style.css"> <!-- Assurez-vous que le chemin est correct -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Styles minimaux pour admin_client_detail.php - à externaliser/améliorer */
        body { font-family: 'Poppins', sans-serif; background-color: #121212; color: #e0e0e0; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .admin-header-minimal { background-color: #1e1e1e; padding: 1rem 1.5rem; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
        .admin-header-minimal .logo img { max-width: 150px; filter: invert(1) brightness(1.8) contrast(1.1); }
        .admin-header-minimal a { color: #ffdd03; text-decoration: none; }
        .admin-content-area-minimal { padding: 2rem; flex-grow: 1; max-width: 1200px; margin: 0 auto; width:100%; box-sizing:border-box;}
        .detail-card { background-color: #1e1e1e; padding: 1.5rem; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.2); margin-bottom: 1.5rem; }
        .detail-card h2 { font-size: 1.5rem; color: #ffdd03; margin-top: 0; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #333; }
        .detail-list { list-style: none; padding: 0; }
        .detail-list li { margin-bottom: 0.7rem; font-size: 0.95rem; }
        .detail-list li strong { color: #b0b0b0; min-width: 180px; display: inline-block; } /* Augmentation min-width */
        .admin-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .admin-table th, .admin-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #333; font-size: 0.9rem; }
        .admin-table th { color: #e0e0e0; font-weight: 600; background-color: rgba(0,0,0,0.1); }
        .admin-table td { color: #b0b0b0; }
        .admin-footer-minimal { text-align: center; padding: 1rem; font-size: 0.85rem; color: #b0b0b0; border-top: 1px solid #333; background-color: #1e1e1e; }
        .back-link { display: inline-block; margin-bottom: 1.5rem; color: #ffdd03; text-decoration: none; font-weight: 500; }
        .back-link i { margin-right: 0.5rem; }
        .error-message { background-color: #e74c3c; color: white; padding: 1rem; border-radius: 5px; text-align: center; margin-bottom:1rem; }
        .info-message { background-color: #3498db; color: white; padding: 1rem; border-radius: 5px; text-align: center; margin-bottom:1rem; }
        .admin-action-btn.edit-btn { background-color: var(--bg-surface); color: var(--accent-primary); border-color: var(--accent-primary); padding: 0.4rem 0.8rem; font-size:0.8em; text-decoration:none; border-radius:4px; border:1px solid; }
    </style>
</head>
<body>
    <header class="admin-header-minimal">
        <div class="logo">
            <a href="admin_dashboard.php"><img src="assets/images/logobg.png" alt="Logo Ouipneu.fr"></a>
        </div>
        <div>
             <a href="admin_dashboard.php#admin-clients-content" style="margin-right:15px;">Liste des clients</a>
             <a href="admin_logout.php">Déconnexion</a>
        </div>
    </header>

    <main class="admin-content-area-minimal">
        <a href="admin_dashboard.php#admin-clients-content" class="back-link"><i class="fas fa-arrow-left"></i> Retour à la liste des clients</a>
        <h1><?php echo sanitize_html_output($page_title); ?></h1>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo sanitize_html_output($error_message); ?></div>
        <?php elseif ($client_details): ?>
            <div class="detail-card">
                <h2>Informations Personnelles</h2>
                <ul class="detail-list">
                    <li><strong>ID Client:</strong> <?php echo sanitize_html_output($client_details['id_utilisateur']); ?></li>
                    <li><strong>Prénom:</strong> <?php echo sanitize_html_output($client_details['prenom']); ?></li>
                    <li><strong>Nom:</strong> <?php echo sanitize_html_output($client_details['nom']); ?></li>
                    <li><strong>Email:</strong> <?php echo sanitize_html_output($client_details['email']); ?></li>
                    <li><strong>Date d'inscription:</strong> <?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($client_details['date_inscription']))); ?></li>
                    <li><strong>Téléphone (compte):</strong> <?php echo sanitize_html_output($client_details['telephone'] ?? 'N/A'); ?></li>
                </ul>
            </div>

            <div class="detail-card">
                <h2>Adresses Enregistrées</h2>
                <?php if (empty($client_adresses)): ?>
                    <p>Aucune adresse enregistrée pour ce client.</p>
                <?php else: ?>
                    <?php foreach($client_adresses as $index => $adresse): ?>
                        <h4 style="color:#ffdd03; margin-top: <?php echo $index > 0 ? '1.5rem' : '0';?>; margin-bottom:0.5rem; padding-bottom:0.3rem; border-bottom:1px dashed #444;">
                            Adresse <?php echo $index + 1; ?>
                            <small style="font-weight:normal; color:#b0b0b0;">(Type: <?php echo sanitize_html_output($adresse['type_adresse'] ?? 'N/A'); ?>)</small>
                        </h4>
                        <ul class="detail-list">
                            <li><strong>Rue:</strong> <?php echo sanitize_html_output($adresse['rue'] ?? 'N/A'); ?></li>
                            <li><strong>Complément:</strong> <?php echo sanitize_html_output($adresse['complement_adresse'] ?? 'N/A'); ?></li>
                            <li><strong>Code Postal:</strong> <?php echo sanitize_html_output($adresse['code_postal'] ?? 'N/A'); ?></li>
                            <li><strong>Ville:</strong> <?php echo sanitize_html_output($adresse['ville'] ?? 'N/A'); ?></li>
                            <li><strong>Pays:</strong> <?php echo sanitize_html_output($adresse['pays'] ?? 'N/A'); ?></li>
                            <li><strong>Téléphone (adresse):</strong> <?php echo sanitize_html_output($adresse['telephone'] ?? 'N/A'); ?></li>
                            <?php if(!empty($adresse['est_principale_livraison']) && $adresse['est_principale_livraison']): ?>
                                <li><strong style="color: #2ecc71;">Adresse de livraison principale</strong></li>
                            <?php endif; ?>
                             <?php if(!empty($adresse['est_principale_facturation']) && $adresse['est_principale_facturation']): ?>
                                <li><strong style="color: #2ecc71;">Adresse de facturation principale</strong></li>
                            <?php endif; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h2>Historique des Commandes</h2>
                <?php if (empty($client_commandes)): ?>
                    <p>Ce client n'a passé aucune commande.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Date</th>
                                <th>Montant Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_commandes as $commande): ?>
                                <tr>
                                    <td>#<?php echo sanitize_html_output($commande['id_commande']); ?></td>
                                    <td><?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($commande['date_commande']))); ?></td>
                                    <td><?php echo sanitize_html_output(number_format((float)($commande['montant_total'] ?? 0), 2, ',', ' ')); ?> €</td>
                                    <td><?php echo sanitize_html_output($commande['statut_commande'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="admin_order_detail.php?id_commande=<?php echo $commande['id_commande']; ?>" class="admin-action-btn edit-btn">Voir Commande</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php else: ?>
             <div class="info-message">Veuillez sélectionner un client depuis le tableau de bord pour voir ses détails.</div>
        <?php endif; ?>
    </main>

    <footer class="admin-footer-minimal">
        <p>&copy; <?php echo date("Y"); ?> Ouipneu.fr - Interface d'Administration</p>
    </footer>
</body>
</html>
