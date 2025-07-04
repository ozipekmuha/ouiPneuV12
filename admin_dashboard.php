
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Protection Admin améliorée
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // Assurez-vous que sanitize_html_output existe ici

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Traitement des actions POST
$admin_message_display = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF). Action annulée.'];
    } else {
        // --- Traitement Ajout/Modification Produit ---
        if ($_POST['action'] == 'add_edit_product') {
            // Récupération des données du formulaire
            $nom = trim($_POST['nom'] ?? '');
            $taille = trim($_POST['taille'] ?? '');
            $saison = $_POST['saison'] ?? 'Été';
            // $marque = trim($_POST['marque'] ?? ''); // Retiré

            // MODIFICATION ICI POUR LE PRIX: Convertir la virgule en point avant validation
            $prix_raw_input = trim($_POST['prix'] ?? '');
            $prix_clean_for_validation = str_replace(',', '.', $prix_raw_input);
            $prix = filter_var($prix_clean_for_validation, FILTER_VALIDATE_FLOAT); // Validation avec le point

            $stock_disponible = filter_input(INPUT_POST, 'stock_disponible', FILTER_VALIDATE_INT);
            $description = trim($_POST['description'] ?? '');
            $specifications = trim($_POST['specifications'] ?? '');
            $est_actif = isset($_POST['est_actif']) ? (int)$_POST['est_actif'] : 0;
            $id_pneu_edit = filter_input(INPUT_POST, 'id_pneu_edit', FILTER_VALIDATE_INT);

            // Validation basique (incluant le nouveau traitement du prix)
            if (empty($nom) || empty($taille) || $prix === false || $prix < 0 || $stock_disponible === false || $stock_disponible < 0) {
                $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Veuillez remplir tous les champs obligatoires correctement (le prix doit être un nombre positif).'];
            } else {
                $image_path_db = $id_pneu_edit ? ($_POST['current_image_path'] ?? '') : ''; 

                // Gestion de l'upload d'image
                if (isset($_FILES['image_produit']) && $_FILES['image_produit']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = 'assets/images/pneus/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $filename = uniqid('pneu_', true) . '_' . basename($_FILES['image_produit']['name']);
                    $target_file = $upload_dir . $filename;
                    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                    // Vérifications de l'image
                    $check = getimagesize($_FILES['image_produit']['tmp_name']);
                    if ($check === false) {
                        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Le fichier n\'est pas une image valide.'];
                    } elseif ($_FILES['image_produit']['size'] > 2000000) { // 2MB
                        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'L\'image est trop volumineuse (max 2MB).'];
                    } elseif (!in_array($image_file_type, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Seuls les formats JPG, JPEG, PNG & WEBP sont autorisés.'];
                    } else {
                        if (move_uploaded_file($_FILES['image_produit']['tmp_name'], $target_file)) {
                            $image_path_db = $target_file;
                            if ($id_pneu_edit && !empty($_POST['current_image_path']) && file_exists($_POST['current_image_path']) && $_POST['current_image_path'] !== $image_path_db) {
                                unlink($_POST['current_image_path']);
                            }
                        } else {
                            $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Erreur lors du téléchargement de l\'image.'];
                        }
                    }
                } elseif ($id_pneu_edit && !empty($_POST['current_image_path'])) {
                    $image_path_db = $_POST['current_image_path'];
                }


                if (!isset($_SESSION['admin_message'])) {
                    try {
                        if ($id_pneu_edit) { // Modification
                            if (empty($image_path_db)) {
                                $stmt_check_image = $pdo->prepare("SELECT image FROM Pneus WHERE id = :id");
                                $stmt_check_image->execute([':id' => $id_pneu_edit]);
                                $current_db_image = $stmt_check_image->fetchColumn();
                                if(!empty($current_db_image)) {
                                    $image_path_db = $current_db_image;
                                }
                            }

                            $sql = "UPDATE Pneus SET nom = :nom, taille = :taille, saison = :saison, prix = :prix, stock_disponible = :stock, description = :description, image = :image, specifications = :specifications, est_actif = :est_actif WHERE id = :id";
                            $stmt = $pdo->prepare($sql);
                            $params = [
                                ':nom' => $nom, ':taille' => $taille, ':saison' => $saison,
                                ':prix' => $prix,
                                ':stock' => $stock_disponible, ':description' => $description,
                                ':image' => $image_path_db, ':specifications' => $specifications, ':est_actif' => $est_actif, ':id' => $id_pneu_edit
                            ];
                        } else { // Ajout
                            // CORRECTION: 'date_ajout' et 'NOW()' retirés
                            $sql = "INSERT INTO Pneus (nom, taille, saison, prix, stock_disponible, description, image, specifications, est_actif) 
                                    VALUES (:nom, :taille, :saison, :prix, :stock, :description, :image, :specifications, :est_actif)";
                            $stmt = $pdo->prepare($sql);
                             $params = [
                                ':nom' => $nom, ':taille' => $taille, ':saison' => $saison,
                                ':prix' => $prix,
                                ':stock' => $stock_disponible, ':description' => $description,
                                ':image' => $image_path_db, ':specifications' => $specifications, ':est_actif' => $est_actif
                            ];
                        }

                        if ($stmt->execute($params)) {
                            $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Produit ' . ($id_pneu_edit ? 'mis à jour' : 'ajouté') . ' avec succès.'];
                        } else {
                            $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Erreur lors de l\'enregistrement du produit.'];
                        }
                    } catch (PDOException $e) {
                        error_log("Erreur PDO add_edit_product: " . $e->getMessage());
                        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Erreur base de données lors de l\'enregistrement du produit. Debug: ' . $e->getMessage()];
                    }
                }
            }
            header("Location: admin_dashboard.php#admin-products-content-NEW");
            exit;
        }
        // ... (Le reste de votre logique POST est conservé)
    }
}

if (isset($_SESSION['admin_message'])) {
    $admin_message_display = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

// --- Récupération des pneus pour l'affichage (champ 'marque' retiré) ---
$liste_pneus = [];
try {
    $stmt_pneus = $pdo->query("SELECT id, nom, image, taille, saison, prix, stock_disponible, est_actif, description, specifications FROM Pneus ORDER BY nom ASC");
    $liste_pneus = $stmt_pneus->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur Admin - Récupération pneus: " . $e->getMessage());
    $admin_message_display = ['type' => 'error', 'text' => 'Erreur de chargement des pneus.'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Ouipneu.fr</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-dark: #121212;
            --bg-surface: #1e1e1e;
            --text-light: #e0e0e0;
            --text-secondary: #b0b0b0;
            --accent-primary: #ffdd03;
            --text-on-accent: #1a1a1a;
            --border-color: #333333;
            --font-weight-regular: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }
        .admin-header {
            background-color: var(--bg-surface);
            padding: 0.8rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .admin-header .logo img {
            max-width: 150px;
            filter: invert(1) brightness(1.8) contrast(1.1);
        }
        .admin-header .admin-user-info a {
            color: var(--accent-primary);
            text-decoration: none;
            font-weight: var(--font-weight-medium);
        }
        .admin-main-layout {
            display: flex;
            flex-grow: 1;
        }
        .admin-sidebar {
            width: 250px;
            background-color: var(--bg-surface);
            padding: 1.5rem 1rem;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        .admin-sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .admin-sidebar nav ul li a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            transition: background-color 0.2s ease, color 0.2s ease;
            font-weight: var(--font-weight-medium);
        }
        .admin-sidebar nav ul li a i {
            width: 20px;
            text-align: center;
        }
        .admin-sidebar nav ul li a:hover,
        .admin-sidebar nav ul li a.active {
            background-color: var(--accent-primary);
            color: var(--text-on-accent);
        }
         .admin-sidebar nav ul li a.active i {
             color: var(--text-on-accent);
        }

        .admin-content-area {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        .admin-content-section {
            display: none;
        }
        .admin-content-section.is-active {
            display: block;
        }
        .admin-content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }
        .admin-content-header h1 {
            font-size: 1.8rem;
            color: var(--accent-primary);
            margin: 0;
        }
         .admin-content-header .cta-button {
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }


        .admin-table-container {
            background-color: var(--bg-surface);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th, .admin-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        .admin-table th {
            color: var(--text-light);
            font-weight: var(--font-weight-semibold);
            background-color: rgba(0,0,0,0.1);
        }
        .admin-table td {
            color: var(--text-secondary);
        }
        .admin-table tbody tr:hover {
            background-color: rgba(255,255,255,0.03);
        }
        .status-pending { color: #f39c12; }
        .status-shipped { color: #3498db; }
        .status-delivered { color: #2ecc71; }
        .status-cancelled { color: #e74c3c; }

        .admin-table .actions a {
            margin-right: 0.5rem;
            color: var(--accent-primary);
            font-size: 0.85rem;
        }
        .admin-table img.product-thumbnail {
            width: 60px;
            height: auto;
            max-height: 50px;
            object-fit: contain;
            border-radius: 4px;
            background-color: var(--bg-dark);
        }


        /* Styles pour les formulaires de paramètres */
        .admin-settings-form .detail-card, .admin-content-section .detail-card {
             background-color: var(--bg-surface);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            margin-bottom: 1.5rem;
        }
        .admin-settings-form .detail-card h2, .admin-content-section .detail-card h2 {
            font-size: 1.2rem;
            color: var(--accent-primary);
            margin-top: 0;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .admin-settings-form .form-group { margin-bottom: 1rem; }
        .admin-settings-form label { display: block; font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.3rem; }
        .admin-settings-form input,
        .admin-settings-form select,
        .admin-settings-form textarea {
            width: 100%;
            padding: 0.7rem 0.9rem;
            background-color: var(--bg-dark);
            color: var(--text-light);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        
        .admin-footer {
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            border-top: 1px solid var(--border-color);
            background-color: var(--bg-surface);
        }

        .admin-table .actions .admin-action-btn {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid;
            margin-bottom: 0.25rem;
        }
        .admin-table .actions .edit-btn { background-color: var(--bg-surface); color: var(--accent-primary); border-color: var(--accent-primary); }
        .admin-table .actions .toggle-status-btn.status-active-btn { background-color: #e74c3c; color: white; border-color: #e74c3c; }
        .admin-table .actions .toggle-status-btn.status-inactive-btn { background-color: #2ecc71; color: white; border-color: #2ecc71; }
        .admin-table .actions .delete-btn { background-color: var(--bg-surface); color: #e74c3c; border-color: #e74c3c; }

        #product-modal-overlay, #promo-code-modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7);
            z-index: 1050; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;
        }
        #product-modal-content, #promo-code-modal-content {
            background-color: var(--bg-surface); color: var(--text-light); border-radius: 8px; width: 100%; max-width: 650px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); display: flex; flex-direction: column; max-height: calc(100vh - 40px); overflow: hidden;
        }
        .product-modal-header, .promo-code-modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .product-modal-header h2, .promo-code-modal-header h2 { margin: 0; font-size: 1.4rem; color: var(--accent-primary); }
        .product-modal-body, .promo-code-modal-body { padding: 1.5rem; overflow-y: auto; }
        .product-modal-body .form-group, .promo-code-modal-body .form-group { margin-bottom: 1rem; }
        .product-modal-footer, .promo-code-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); text-align: right; }
        #close-product-modal-btn, #close-promo-code-modal-btn { background: none; border: none; font-size: 1.8rem; color: var(--text-secondary); cursor: pointer; }
        .search-bar-container { margin-bottom: 1.5rem; }
        .search-bar-container input { width: 100%; padding: 0.7rem 1rem; background-color: var(--bg-dark); color: var(--text-light); border: 1px solid var(--border-color); border-radius: 5px; font-size: 0.95rem; }
        .form-control { width: 100%; padding: 0.7rem 0.9rem; background-color: var(--bg-dark); color: var(--text-light); border: 1px solid var(--border-color); border-radius: 5px; font-size: 0.95rem; box-sizing: border-box; }

    </style>
</head>
<body>
    <header class="admin-header">
        <div class="logo">
            <a href="admin_dashboard.php"><img src="assets/images/ouiPneu.png" alt="Logo Ouipneu.fr" style="max-width: 150px; filter: invert(1) brightness(1.8) contrast(1.1);"></a>
        </div>
        <div class="admin-user-info">
            <span><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?> | <a href="admin_logout.php">Déconnexion</a></span>
        </div>
    </header>

    <div class="admin-main-layout">
        <aside class="admin-sidebar">
            <nav>
                <ul>
                    <li><a href="#" class="active" data-target="admin-dashboard-main"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                    <li><a href="#" data-target="admin-orders-content"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                    <li><a href="#" data-target="admin-products-content-NEW"><i class="fas fa-box-open"></i> Produits</a></li>
                    <li><a href="#" data-target="admin-promo-codes-content"><i class="fas fa-percentage"></i> Codes Promo</a></li>
                    <li><a href="#" data-target="admin-clients-content"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="#" data-target="admin-settings-content"><i class="fas fa-cog"></i> Paramètres</a></li>
                </ul>
            </nav>
        </aside>

        <main class="admin-content-area">
            <?php if (!empty($admin_message_display)): ?>
                <div class="global-notification-bar <?php echo sanitize_html_output($admin_message_display['type']); ?> show">
                    <?php echo sanitize_html_output($admin_message_display['text']); ?>
                </div>
            <?php endif; ?>

            <div id="admin-dashboard-main" class="admin-content-section is-active">
                <div class="admin-content-header">
                    <h1>Aperçu Général</h1>
                </div>
                <div class="dashboard-stats-grid">
                    <div class="stat-card detail-card"><h3><i class="fas fa-dollar-sign"></i> Chiffre d'Affaires (30j)</h3><p>12,345.67 €</p></div>
                    <div class="stat-card detail-card"><h3><i class="fas fa-shopping-cart"></i> Nouvelles Commandes (24h)</h3><p>15</p></div>
                    <div class="stat-card detail-card"><h3><i class="fas fa-users"></i> Nouveaux Clients (7j)</h3><p>8</p></div>
                    <div class="stat-card detail-card"><h3><i class="fas fa-box"></i> Produits en Faible Stock</h3><p>3 <a href="#" data-target="admin-products-content-NEW" class="admin-quick-link">(Voir)</a></p></div>
                </div>
                <div class="detail-card" style="margin-top: 2rem;">
                    <h2>Ventes Mensuelles (Exemple)</h2>
                    <div style="height: 300px; width: 100%;"><canvas id="salesChart"></canvas></div>
                </div>
                 <div class="admin-table-container detail-card" style="margin-top: 2rem;">
                    <h2>Activité Récente</h2>
                    <ul>
                        <li>Nouvelle commande <a href="#">#CMD005</a> reçue.</li>
                        <li>Produit "Pneu Michelin Pilot Sport 4" mis à jour.</li>
                        <li>Nouveau client enregistré: client.test@example.com</li>
                    </ul>
                </div>
            </div>

            <div id="admin-orders-content" class="admin-content-section">
                <div class="admin-content-header"><h1>Gestion des Commandes</h1></div>
                <div class="admin-table-container">
                    </div>
            </div>

            <div id="admin-products-content-NEW" class="admin-content-section">
                <div class="admin-content-header">
                    <h1>Gestion des Produits</h1>
                    <button class="cta-button secondary" id="add-product-button"><i class="fas fa-plus"></i> Ajouter un produit</button>
                </div>
                <div class="search-bar-container">
                    <input type="text" id="product-search-input" placeholder="Rechercher par nom, taille, saison, prix...">
                </div>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Image</th>
                                <th>Nom du Produit</th>
                                <th>Taille</th>
                                <th>Saison</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Statut</th>
                                <th style="width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="products-table-body-NEW">
                            <?php if (empty($liste_pneus)): ?>
                                <tr><td colspan="8" style="text-align: center; padding: 1rem;">Aucun pneu trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($liste_pneus as $pneu): ?>
                                    <tr data-product-search="<?php echo htmlspecialchars(strtolower($pneu['nom'] . ' ' . $pneu['taille'] . ' ' . $pneu['saison'] . ' ' . str_replace('.', ',', $pneu['prix'])), ENT_QUOTES, 'UTF-8'); ?>">
                                        <td><img src="<?php echo sanitize_html_output(!empty($pneu['image']) ? $pneu['image'] : 'https://placehold.co/80x60/1e1e1e/ffdd03?text=Pneu'); ?>" alt="<?php echo sanitize_html_output($pneu['nom']); ?>" class="product-thumbnail"></td>
                                        <td><?php echo sanitize_html_output($pneu['nom']); ?></td>
                                        <td><?php echo sanitize_html_output($pneu['taille']); ?></td>
                                        <td><?php echo sanitize_html_output($pneu['saison']); ?></td>
                                        <td><?php echo sanitize_html_output(number_format((float)$pneu['prix'], 2, ',', ' ')); ?> €</td>
                                        <td><?php echo sanitize_html_output($pneu['stock_disponible']); ?></td>
                                        <td><span class="<?php echo $pneu['est_actif'] ? 'status-delivered' : 'status-cancelled'; ?>"><?php echo $pneu['est_actif'] ? 'Actif' : 'Inactif'; ?></span></td>
                                        <td class="actions">
                                            <a href="#" class="admin-action-btn edit-btn edit-product-btn-js" data-product='<?php echo htmlspecialchars(json_encode($pneu), ENT_QUOTES, 'UTF-8'); ?>'>Modifier</a>
                                            <form method="POST" action="admin_dashboard.php#admin-products-content-NEW" style="display: inline-block;"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="id_pneu" value="<?php echo $pneu['id']; ?>"><input type="hidden" name="action" value="toggle_status"><button type="submit" class="admin-action-btn toggle-status-btn <?php echo $pneu['est_actif'] ? 'status-active-btn' : 'status-inactive-btn'; ?>"><?php echo $pneu['est_actif'] ? 'Désactiver' : 'Activer'; ?></button></form>
                                            <form method="POST" action="admin_dashboard.php#admin-products-content-NEW" style="display: inline-block;" onsubmit="return confirm('Êtes-vous sûr ?');"><input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"><input type="hidden" name="id_pneu" value="<?php echo $pneu['id']; ?>"><input type="hidden" name="action" value="delete_pneu"><button type="submit" class="admin-action-btn delete-btn">Supprimer</button></form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="admin-promo-codes-content" class="admin-content-section">
                </div>

            <div id="admin-clients-content" class="admin-content-section">
                 </div>

            <div id="admin-settings-content" class="admin-content-section">
                </div>
        </main>
    </div>

    <footer class="admin-footer">
        <p>&copy; <span id="current-year-admin-dash"></span> Ouipneu.fr - Interface d'Administration</p>
    </footer>

    <div id="product-modal-overlay">
        <div id="product-modal-content">
            <div class="product-modal-header">
                <h2 id="product-form-title-NEW">Ajouter un Produit</h2>
                <button type="button" id="close-product-modal-btn" aria-label="Fermer">&times;</button>
            </div>
            <div class="product-modal-body">
                <form id="add-product-form-NEW" method="POST" action="admin_dashboard.php#admin-products-content-NEW" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_edit_product">
                    <input type="hidden" name="id_pneu_edit" id="edit-product-id-NEW" value="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="current_image_path" id="current-image-path-NEW" value="">
                    <div class="form-group"><label for="product-nom">Nom <span style="color:red;">*</span></label><input type="text" id="product-nom" name="nom" required class="form-control"></div>
                    <div class="form-group"><label for="product-taille">Taille <span style="color:red;">*</span></label><input type="text" id="product-taille" name="taille" required class="form-control"></div>
                    <div class="form-group"><label for="product-saison">Saison <span style="color:red;">*</span></label><select id="product-saison" name="saison" required class="form-control"><option value="Été">Été</option><option value="Hiver">Hiver</option><option value="4 Saisons">4 Saisons</option></select></div>
                    <div class="form-group"><label for="product-prix">Prix (€) <span style="color:red;">*</span></label><input type="text" id="product-prix" name="prix" required class="form-control"></div>
                    <div class="form-group"><label for="product-stock">Stock <span style="color:red;">*</span></label><input type="number" id="product-stock" name="stock_disponible" min="0" required class="form-control"></div>
                    <div class="form-group"><label for="product-image">Image</label><input type="file" id="product-image" name="image_produit" class="form-control" accept="image/png, image/jpeg, image/webp"></div>
                    <div class="form-group"><label for="product-description">Description</label><textarea id="product-description" name="description" rows="3" class="form-control"></textarea></div>
                    <div class="form-group"><label for="product-specifications">Spécifications</label><input type="text" id="product-specifications" name="specifications" class="form-control"></div>
                    <div class="form-group"><label>Statut</label><div><input type="radio" id="product-status-active-NEW" name="est_actif" value="1" checked><label for="product-status-active-NEW">Actif</label><input type="radio" id="product-status-inactive-NEW" name="est_actif" value="0"><label for="product-status-inactive-NEW">Inactif</label></div></div>
                </form>
            </div>
            <div class="product-modal-footer">
                <button type="button" id="cancel-add-product-NEW" class="cta-button secondary">Annuler</button>
                <button type="submit" form="add-product-form-NEW" class="cta-button">Enregistrer</button>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('current-year-admin-dash').textContent = new Date().getFullYear();

        // --- ALGORITHME DE RECHERCHE FINAL ---
        const productSearchInput = document.getElementById('product-search-input');
        const productsTableBody = document.getElementById('products-table-body-NEW');

        if (productSearchInput && productsTableBody) {
            productSearchInput.addEventListener('input', function() { // 'input' est plus réactif que 'keyup'
                // 1. Sépare la recherche en plusieurs mots-clés
                const searchTerms = this.value.toLowerCase().trim().split(/\s+/).filter(term => term.length > 0);
                
                const rows = productsTableBody.querySelectorAll('tr');

                rows.forEach(row => {
                    if (row.hasAttribute('data-product-search')) {
                        const searchContent = row.dataset.productSearch || '';
                        // 2. Normalise le contenu de la ligne à chercher
                        const normalizedSearchContent = searchContent.toLowerCase().replace(/[^a-z0-9]/gi, '');
                        
                        let isMatch = true;

                        // 3. On vérifie si TOUS les mots-clés sont présents
                        for (const term of searchTerms) {
                            const normalizedTerm = term.replace(/[^a-z0-9]/gi, '');
                            if (normalizedTerm && !normalizedSearchContent.includes(normalizedTerm)) {
                                isMatch = false;
                                break; 
                            }
                        }

                        row.style.display = isMatch ? '' : 'none';
                    }
                });
            });
        }
        
        // --- LOGIQUE DE NAVIGATION ET GESTION DES MODALES ---
        const adminNavLinks = document.querySelectorAll('.admin-sidebar nav a[data-target], .admin-quick-link[data-target]');
        const adminContentSections = document.querySelectorAll('.admin-content-section');
        const defaultSectionId = 'admin-dashboard-main';

        function switchAdminSection(targetId) {
            adminContentSections.forEach(section => {
                section.classList.toggle('is-active', section.id === targetId);
            });
            document.querySelectorAll('.admin-sidebar nav a').forEach(link => {
                link.classList.toggle('active', link.dataset.target === targetId);
            });
        }

        adminNavLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = e.currentTarget.dataset.target;
                window.location.hash = targetId;
                switchAdminSection(targetId);
            });
        });
        
        const productModalOverlay = document.getElementById('product-modal-overlay');
        const addProductButton = document.getElementById('add-product-button');
        const closeProductModalBtn = document.getElementById('close-product-modal-btn');
        const cancelAddProductBtn = document.getElementById('cancel-add-product-NEW');

        function showProductModal() { if(productModalOverlay) productModalOverlay.style.display = 'flex'; }
        function hideProductModal() { if(productModalOverlay) productModalOverlay.style.display = 'none'; }

        if(addProductButton) {
            addProductButton.addEventListener('click', () => {
                document.getElementById('add-product-form-NEW').reset();
                document.getElementById('product-form-title-NEW').textContent = 'Ajouter un Nouveau Produit';
                document.getElementById('edit-product-id-NEW').value = '';
                showProductModal();
            });
        }
        
        document.querySelectorAll('.edit-product-btn-js').forEach(button => {
            button.addEventListener('click', function() {
                const productData = JSON.parse(this.dataset.product);
                document.getElementById('product-form-title-NEW').textContent = 'Modifier le Produit';
                document.getElementById('edit-product-id-NEW').value = productData.id;
                document.getElementById('product-nom').value = productData.nom;
                document.getElementById('product-taille').value = productData.taille;
                document.getElementById('product-saison').value = productData.saison;
                document.getElementById('product-prix').value = String(productData.prix).replace('.', ',');
                document.getElementById('product-stock').value = productData.stock_disponible;
                document.getElementById('product-description').value = productData.description || '';
                document.getElementById('product-specifications').value = productData.specifications || '';
                document.getElementById(productData.est_actif == 1 ? 'product-status-active-NEW' : 'product-status-inactive-NEW').checked = true;
                showProductModal();
            });
        });
        
        if(closeProductModalBtn) closeProductModalBtn.addEventListener('click', hideProductModal);
        if(cancelAddProductBtn) cancelAddProductBtn.addEventListener('click', hideProductModal);
        if(productModalOverlay) productModalOverlay.addEventListener('click', e => { if(e.target === productModalOverlay) hideProductModal(); });

        let initialSection = window.location.hash.substring(1) || defaultSectionId;
        if (!document.getElementById(initialSection)) initialSection = defaultSectionId;
        switchAdminSection(initialSection);
    });
    </script>
</body>
</html>
