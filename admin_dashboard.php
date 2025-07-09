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

// --- Chargement des paramètres ---
$settings_file = 'config/settings.json';
$settings = []; // Initialiser avec des valeurs par défaut au cas où le fichier n'existe pas ou une clé manque
$default_settings = [
    'admin_firstname' => '',
    'admin_name' => '',
    'admin_email' => '',
    'stripe_pk' => '',
    'stripe_sk' => '',
    'carriers' => [['name' => '', 'price' => '', 'delay' => '']] // Assurer au moins un transporteur vide
];

if (file_exists($settings_file)) {
    $settings_json = file_get_contents($settings_file);
    $loaded_settings = json_decode($settings_json, true);
    if ($loaded_settings !== null) {
        // Fusionner avec les défauts pour s'assurer que toutes les clés attendues existent
        $settings = array_merge($default_settings, $loaded_settings);
    } else {
        $settings = $default_settings; // Fichier JSON corrompu, utiliser les défauts
        error_log("Erreur de décodage JSON dans $settings_file");
    }
} else {
    $settings = $default_settings; // Fichier non trouvé, utiliser les défauts
}

// S'assurer que les transporteurs existent comme un tableau et qu'il y a au moins un élément
if (!isset($settings['carriers']) || !is_array($settings['carriers']) || empty($settings['carriers'])) {
    $settings['carriers'] = [['name' => '', 'price' => '', 'delay' => '']];
}


// Traitement des actions POST
$admin_message_display = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Erreur de sécurité (CSRF). Action annulée.'];
    } else {
        // --- Traitement Actions Garages ---
        if ($_POST['action'] == 'approve_candidature_garage' && isset($_POST['id_candidat_modal_garage'])) {
            $id_candidat = (int)$_POST['id_candidat_modal_garage'];
            $nom_garage = trim($_POST['nom_garage_modal']);
            $adresse_complete = trim($_POST['adresse_complete_modal']);
            $telephone = trim($_POST['telephone_modal']);
            $email = trim($_POST['email_modal']);
            $services_offerts = trim($_POST['services_offerts_modal']);
            $description_courte = trim($_POST['description_courte_modal']);
            $latitude = !empty($_POST['latitude_modal']) ? filter_var($_POST['latitude_modal'], FILTER_VALIDATE_FLOAT) : null;
            $longitude = !empty($_POST['longitude_modal']) ? filter_var($_POST['longitude_modal'], FILTER_VALIDATE_FLOAT) : null;
            $horaires_ouverture = trim($_POST['horaires_ouverture_modal']);
            $url_website = trim($_POST['url_website_modal']);

            if (empty($nom_garage) || empty($adresse_complete)) {
                $_SESSION['admin_message'] = ['type' => 'error', 'text' => "Le nom du garage et l'adresse sont requis pour l'approbation."];
            } else {
                try {
                    $pdo->beginTransaction();
                    $sql_insert_partenaire = "INSERT INTO GaragesPartenaires (nom_garage, adresse_complete, telephone, email, services_offerts, description_courte, latitude, longitude, horaires_ouverture, url_website, est_visible) VALUES (:nom, :adresse, :tel, :email, :services, :desc, :lat, :lon, :horaires, :site, TRUE)";
                    $stmt_insert = $pdo->prepare($sql_insert_partenaire);
                    $stmt_insert->execute([':nom' => $nom_garage, ':adresse' => $adresse_complete, ':tel' => $telephone, ':email' => $email, ':services' => $services_offerts, ':desc' => $description_courte, ':lat' => $latitude, ':lon' => $longitude, ':horaires' => $horaires_ouverture, ':site' => $url_website]);
                    $sql_update_candidat = "UPDATE GaragesCandidats SET statut = 'approuve' WHERE id_candidat = :id_candidat";
                    $stmt_update = $pdo->prepare($sql_update_candidat);
                    $stmt_update->execute([':id_candidat' => $id_candidat]);
                    $pdo->commit();
                    $_SESSION['admin_message'] = ['type' => 'success', 'text' => "Candidature approuvée et ajoutée aux partenaires."];
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Erreur approve_candidature_garage: " . $e->getMessage());
                    $_SESSION['admin_message'] = ['type' => 'error', 'text' => "Erreur base de données approbation: " . $e->getMessage()];
                }
            }
            header("Location: admin_dashboard.php#admin-garages-section");
            exit;
        } elseif ($_POST['action'] == 'reject_candidature_garage' && isset($_POST['id_candidat_garage'])) {
            $id_candidat = (int)$_POST['id_candidat_garage'];
            try {
                $sql_update_candidat = "UPDATE GaragesCandidats SET statut = 'rejete' WHERE id_candidat = :id_candidat";
                $stmt_update = $pdo->prepare($sql_update_candidat);
                $stmt_update->execute([':id_candidat' => $id_candidat]);
                $_SESSION['admin_message'] = ['type' => 'success', 'text' => "Candidature rejetée."];
            } catch (PDOException $e) {
                error_log("Erreur reject_candidature_garage: " . $e->getMessage());
                $_SESSION['admin_message'] = ['type' => 'error', 'text' => "Erreur base de données rejet."];
            }
            header("Location: admin_dashboard.php#admin-garages-section");
            exit;
        } elseif ($_POST['action'] == 'update_partenaire_garage' && isset($_POST['id_garage_modal'])) {
            $id_garage = (int)$_POST['id_garage_modal'];
            $nom_garage = trim($_POST['nom_garage_modal']);
            $adresse_complete = trim($_POST['adresse_complete_modal']);
            $telephone = trim($_POST['telephone_modal']);
            $email = trim($_POST['email_modal']);
            $services_offerts = trim($_POST['services_offerts_modal']);
            $description_courte = trim($_POST['description_courte_modal']);
            $latitude = !empty($_POST['latitude_modal']) ? filter_var($_POST['latitude_modal'], FILTER_VALIDATE_FLOAT) : null;
            $longitude = !empty($_POST['longitude_modal']) ? filter_var($_POST['longitude_modal'], FILTER_VALIDATE_FLOAT) : null;
            $horaires_ouverture = trim($_POST['horaires_ouverture_modal']);
            $url_website = trim($_POST['url_website_modal']);
            $est_visible = isset($_POST['est_visible_modal']) ? (int)$_POST['est_visible_modal'] : 0;

            if (empty($nom_garage) || empty($adresse_complete)) {
                 $_SESSION['admin_message'] = ['type' => 'error', 'text' => "Le nom du garage et l'adresse sont requis."];
            } else {
                try {
                    $sql_update_partenaire = "UPDATE GaragesPartenaires SET nom_garage = :nom, adresse_complete = :adresse, telephone = :tel, email = :email, services_offerts = :services, description_courte = :desc, latitude = :lat, longitude = :lon, horaires_ouverture = :horaires, url_website = :site, est_visible = :visible WHERE id_garage = :id_garage";
                    $stmt_update = $pdo->prepare($sql_update_partenaire);
                    $stmt_update->execute([':nom' => $nom_garage, ':adresse' => $adresse_complete, ':tel' => $telephone, ':email' => $email, ':services' => $services_offerts, ':desc' => $description_courte, ':lat' => $latitude, ':lon' => $longitude, ':horaires' => $horaires_ouverture, ':site' => $url_website, ':visible' => $est_visible, ':id_garage' => $id_garage]);
                    $_SESSION['admin_message'] = ['type' => 'success', 'text' => "Informations du partenaire mises à jour."];
                } catch (PDOException $e) {
                    error_log("Erreur update_partenaire_garage: " . $e->getMessage());
                    $_SESSION['admin_message'] = ['type' => 'error', 'text' => "Erreur base de données mise à jour partenaire."];
                }
            }
            header("Location: admin_dashboard.php#admin-garages-section");
            exit;
        } elseif ($_POST['action'] == 'delete_partenaire_garage' && isset($_POST['id_garage'])) {
            $id_garage = (int)$_POST['id_garage'];
            try {
                $sql_delete = "DELETE FROM GaragesPartenaires WHERE id_garage = :id_garage";
                $stmt_delete = $pdo->prepare($sql_delete);
                $stmt_delete->execute([':id_garage' => $id_garage]);
                $_SESSION['admin_message'] = ['type' => 'success', 'text' => "Garage partenaire supprimé."];
            } catch (PDOException $e) {
                error_log("Erreur delete_partenaire_garage: " . $e->getMessage());
                $_SESSION['admin_message'] = ['type' => 'error', 'text' => "Erreur base de données suppression partenaire."];
            }
            header("Location: admin_dashboard.php#admin-garages-section");
            exit;
        }
        // --- Fin Traitement Actions Garages ---

        // --- Traitement Ajout/Modification Produit ---
        elseif ($_POST['action'] == 'add_edit_product') {
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
        // --- Traitement Mise à Jour Paramètres ---
        elseif ($_POST['action'] == 'update_settings') {
            if (isset($_POST['settings']) && is_array($_POST['settings'])) {
                $new_settings_input = $_POST['settings'];
                $current_settings = $settings; // $settings est déjà chargé au début du script

                $settings_to_save = []; // Initialiser le tableau des paramètres à sauvegarder

                // Informations Administrateur
                $settings_to_save['admin_firstname'] = trim($new_settings_input['admin_firstname'] ?? '');
                $settings_to_save['admin_name'] = trim($new_settings_input['admin_name'] ?? '');
                $settings_to_save['admin_email'] = trim($new_settings_input['admin_email'] ?? '');

                // Configuration Stripe
                $settings_to_save['stripe_pk'] = trim($new_settings_input['stripe_pk'] ?? '');
                // Gestion spécifique pour la clé secrète Stripe: ne pas l'écraser avec une valeur vide si elle n'est pas modifiée
                if (isset($new_settings_input['stripe_sk']) && !empty($new_settings_input['stripe_sk'])) {
                    $settings_to_save['stripe_sk'] = trim($new_settings_input['stripe_sk']);
                } elseif (isset($current_settings['stripe_sk'])) {
                    $settings_to_save['stripe_sk'] = $current_settings['stripe_sk']; // Conserver l'ancienne si non fournie
                } else {
                    $settings_to_save['stripe_sk'] = '';
                }

                // Nettoyage et préparation des données des transporteurs
                $updated_carriers = [];
                if (isset($new_settings_input['carriers']) && is_array($new_settings_input['carriers'])) {
                    foreach ($new_settings_input['carriers'] as $carrier_data) {
                        if (isset($carrier_data['name']) && trim($carrier_data['name']) !== '') { // N'ajoute que si le nom du transporteur est renseigné
                            $updated_carriers[] = [
                                'name' => trim($carrier_data['name']),
                                'price' => !empty($carrier_data['price']) ? (float)str_replace(',', '.', $carrier_data['price']) : 0.0,
                                'delay' => trim($carrier_data['delay'] ?? '')
                            ];
                        }
                    }
                }
                $settings_to_save['carriers'] = $updated_carriers;

                // S'assurer que le répertoire config existe
                if (!is_dir('config')) {
                    mkdir('config', 0777, true);
                }

                if (file_put_contents($settings_file, json_encode($settings_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Paramètres mis à jour avec succès.'];
                    $settings = $settings_to_save; // Mettre à jour la variable $settings pour la page actuelle
                } else {
                    $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Erreur lors de l\'enregistrement des paramètres. Vérifiez les permissions du dossier config.'];
                }
            } else {
                $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Données de paramètres invalides.'];
            }
            header("Location: admin_dashboard.php#admin-settings-content"); // Rediriger vers la section des paramètres
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
    // Ne pas écraser $admin_message_display s'il est déjà défini par une action POST
    if ($admin_message_display === null) {
        $admin_message_display = ['type' => 'error', 'text' => 'Erreur de chargement des pneus.'];
    }
}

// --- Récupération des clients pour l'affichage ---
$liste_clients = [];
try {
    // Utilisation des noms de colonnes corrects de la table Utilisateurs
    $stmt_clients = $pdo->query("SELECT id_utilisateur, nom, prenom, email, date_inscription FROM Utilisateurs WHERE est_admin = 0 OR est_admin IS NULL ORDER BY date_inscription DESC");
    $liste_clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur Admin - Récupération clients: " . $e->getMessage());
    if ($admin_message_display === null) {
        $admin_message_display = ['type' => 'error', 'text' => 'Erreur de chargement des clients.'];
    }
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

        #product-modal-overlay, #promo-code-modal-overlay, .modal-admin /* Ajout de .modal-admin ici */ {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7);
            z-index: 1050; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;
        }
        #product-modal-content, #promo-code-modal-content, .modal-admin-content /* Ajout de .modal-admin-content */ {
            background-color: var(--bg-surface); color: var(--text-light); border-radius: 8px; width: 100%; max-width: 650px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); display: flex; flex-direction: column; max-height: calc(100vh - 40px); overflow: hidden;
        }
        .product-modal-header, .promo-code-modal-header /* Partagé avec .modal-admin-content via réutilisation de classes CSS */ { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .product-modal-header h2, .promo-code-modal-header h2 /* Partagé */ { margin: 0; font-size: 1.4rem; color: var(--accent-primary); }
        .product-modal-body, .promo-code-modal-body /* Partagé */ { padding: 1.5rem; overflow-y: auto; }
        .product-modal-body .form-group, .promo-code-modal-body .form-group /* Partagé */ { margin-bottom: 1rem; }
        .product-modal-footer, .promo-code-modal-footer /* Partagé */ { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); text-align: right; }
        #close-product-modal-btn, #close-promo-code-modal-btn, .close-garage-admin-modal /* Ajout de .close-garage-admin-modal */ { background: none; border: none; font-size: 1.8rem; color: var(--text-secondary); cursor: pointer; }

        .search-bar-container { margin-bottom: 1.5rem; }
        .search-bar-container input { width: 100%; padding: 0.7rem 1rem; background-color: var(--bg-dark); color: var(--text-light); border: 1px solid var(--border-color); border-radius: 5px; font-size: 0.95rem; }
        .form-control { width: 100%; padding: 0.7rem 0.9rem; background-color: var(--bg-dark); color: var(--text-light); border: 1px solid var(--border-color); border-radius: 5px; font-size: 0.95rem; box-sizing: border-box; }

    </style>
</head>
<body>
    <header class="admin-header">
        <div class="logo">
            <a href="admin_dashboard.php"><img src="assets/images/logobg.png" alt="Logo Ouipneu.fr" style="max-width: 150px; filter: invert(1) brightness(1.8) contrast(1.1);"></a>
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
                    <li><a href="#" data-target="admin-garages-section"><i class="fas fa-warehouse"></i> Gestion Garages</a></li>
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
                <div class="admin-content-header">
                    <h1>Gestion des Clients</h1>
                </div>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID Client</th>
                                <th>Prénom</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Date d'Inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($liste_clients)): ?>
                                <tr><td colspan="6" style="text-align: center; padding: 1rem;">Aucun client trouvé.</td></tr>
                            <?php else: ?>
                                <?php foreach ($liste_clients as $client): ?>
                                    <tr>
                                        <td><?php echo sanitize_html_output($client['id_utilisateur']); ?></td>
                                        <td><?php echo sanitize_html_output($client['prenom']); ?></td>
                                        <td><?php echo sanitize_html_output($client['nom']); ?></td>
                                        <td><?php echo sanitize_html_output($client['email']); ?></td>
                                        <td><?php echo sanitize_html_output(date("d/m/Y H:i", strtotime($client['date_inscription']))); ?></td>
                                        <td class="actions">
                                            <a href="admin_client_detail.php?id_client=<?php echo $client['id_utilisateur']; ?>" class="admin-action-btn edit-btn">Voir Détails</a>
                                            <!-- Autres actions futures possibles : ex: Modifier, Supprimer -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 </div>

            <div id="admin-settings-content" class="admin-content-section">
                <div class="admin-content-header">
                    <h1>Paramètres du Site</h1>
                </div>
                <form id="admin-settings-form" class="admin-settings-form" method="POST" action="admin_dashboard.php#admin-settings-content">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="detail-card">
                        <h2>Informations Administrateur</h2>
                        <div class="form-group">
                            <label for="setting-admin-firstname">Prénom de l'administrateur:</label>
                            <input type="text" id="setting-admin-firstname" name="settings[admin_firstname]" value="<?php echo sanitize_html_output($settings['admin_firstname'] ?? ''); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="setting-admin-name">Nom de l'administrateur:</label>
                            <input type="text" id="setting-admin-name" name="settings[admin_name]" value="<?php echo sanitize_html_output($settings['admin_name'] ?? ''); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="setting-admin-email">Email de l'administrateur (pour notifications):</label>
                            <input type="email" id="setting-admin-email" name="settings[admin_email]" value="<?php echo sanitize_html_output($settings['admin_email'] ?? ''); ?>" class="form-control">
                        </div>
                    </div>

                    <div class="detail-card">
                        <h2>Configuration des Paiements (Stripe)</h2>
                        <p>Laissez vide si non utilisé. Les clés sont stockées de manière sécurisée.</p>
                        <div class="form-group">
                            <label for="setting-stripe-pk">Stripe Clé Publique (Publishable Key):</label>
                            <input type="text" id="setting-stripe-pk" name="settings[stripe_pk]" value="<?php echo sanitize_html_output($settings['stripe_pk'] ?? ''); ?>" class="form-control" placeholder="pk_test_...">
                        </div>
                        <div class="form-group">
                            <label for="setting-stripe-sk">Stripe Clé Secrète (Secret Key):</label>
                            <input type="password" id="setting-stripe-sk" name="settings[stripe_sk]" value="<?php echo sanitize_html_output($settings['stripe_sk'] ?? ''); ?>" class="form-control" placeholder="sk_test_... ou rk_test_...">
                             <small>La clé secrète n'est affichée qu'une seule fois. Si vous la modifiez, entrez la nouvelle clé.</small>
                        </div>
                    </div>

                    <div class="detail-card">
                        <h2>Configuration des Transporteurs</h2>
                        <div id="carriers-container">
                            <?php
                            $carriers = isset($settings['carriers']) && is_array($settings['carriers']) ? $settings['carriers'] : [['name'=>'', 'price'=>'', 'delay'=>'']];
                            foreach ($carriers as $index => $carrier): ?>
                            <div class="carrier-group" data-index="<?php echo $index; ?>">
                                <h4>Transporteur <?php echo $index + 1; ?> <?php if ($index > 0) echo '<button type="button" class="remove-carrier-btn" style="font-size:0.8em;padding:0.2em 0.5em; margin-left:10px;">&times; Supprimer</button>'; ?></h4>
                                <div class="form-group">
                                    <label for="carrier-name-<?php echo $index; ?>">Nom du transporteur:</label>
                                    <input type="text" id="carrier-name-<?php echo $index; ?>" name="settings[carriers][<?php echo $index; ?>][name]" value="<?php echo sanitize_html_output($carrier['name'] ?? ''); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="carrier-price-<?php echo $index; ?>">Prix (€):</label>
                                    <input type="number" step="0.01" id="carrier-price-<?php echo $index; ?>" name="settings[carriers][<?php echo $index; ?>][price]" value="<?php echo sanitize_html_output($carrier['price'] ?? ''); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="carrier-delay-<?php echo $index; ?>">Délai de livraison (ex: 2-3 jours ouvrés):</label>
                                    <input type="text" id="carrier-delay-<?php echo $index; ?>" name="settings[carriers][<?php echo $index; ?>][delay]" value="<?php echo sanitize_html_output($carrier['delay'] ?? ''); ?>" class="form-control">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-carrier-btn" class="cta-button secondary" style="margin-top:1rem;"><i class="fas fa-plus"></i> Ajouter un transporteur</button>
                    </div>

                    <div style="text-align: right; margin-top:1.5rem;">
                        <button type="submit" class="cta-button">Enregistrer les Paramètres</button>
                    </div>
                </form>
            </div>

            <!-- Section Gestion des Garages Partenaires -->
            <div id="admin-garages-section" class="admin-content-section">
                <div class="admin-content-header">
                    <h1>Gestion des Garages Partenaires</h1>
                </div>

                <?php
                // Récupération des candidatures en attente pour cette section
                $candidatures_garages_section = [];
                try {
                    $stmt_candidats_g_section = $pdo->query("SELECT * FROM GaragesCandidats WHERE statut = 'en_attente' ORDER BY date_soumission DESC");
                    if ($stmt_candidats_g_section) $candidatures_garages_section = $stmt_candidats_g_section->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) { /* Erreur déjà loggée plus haut ou à gérer spécifiquement */ }

                // Récupération des partenaires approuvés pour cette section
                $partenaires_garages_section = [];
                try {
                    $stmt_partenaires_g_section = $pdo->query("SELECT * FROM GaragesPartenaires ORDER BY nom_garage ASC");
                    if ($stmt_partenaires_g_section) $partenaires_garages_section = $stmt_partenaires_g_section->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) { /* Erreur déjà loggée plus haut ou à gérer spécifiquement */ }
                ?>

                <div class="admin-table-container detail-card" id="candidatures-garages-sub">
                    <h2>Candidatures en attente</h2>
                    <?php if (empty($candidatures_garages_section)): ?>
                        <p>Aucune nouvelle candidature pour le moment.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nom Garage</th>
                                    <th>Contact</th>
                                    <th>Services Proposés</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidatures_garages_section as $candidat_g): ?>
                                    <tr>
                                        <td><?php echo sanitize_html_output($candidat_g['nom_garage']); ?></td>
                                        <td>
                                            Email: <?php echo sanitize_html_output($candidat_g['email_contact']); ?><br>
                                            Tél: <?php echo sanitize_html_output($candidat_g['telephone_garage']); ?><br>
                                            Adresse: <?php echo sanitize_html_output($candidat_g['adresse_garage']); ?>
                                        </td>
                                        <td><?php echo nl2br(sanitize_html_output($candidat_g['services_proposes'])); ?></td>
                                        <td><?php echo nl2br(sanitize_html_output($candidat_g['message_partenaire'])); ?></td>
                                        <td><?php echo date("d/m/Y H:i", strtotime($candidat_g['date_soumission'])); ?></td>
                                        <td class="actions">
                                            <button type="button" class="admin-action-btn edit-btn open-approve-garage-modal"
                                                    data-id="<?php echo $candidat_g['id_candidat']; ?>"
                                                    data-nom="<?php echo sanitize_html_output($candidat_g['nom_garage']); ?>"
                                                    data-adresse="<?php echo sanitize_html_output($candidat_g['adresse_garage']); ?>"
                                                    data-tel="<?php echo sanitize_html_output($candidat_g['telephone_garage']); ?>"
                                                    data-email="<?php echo sanitize_html_output($candidat_g['email_contact']); ?>"
                                                    data-services="<?php echo sanitize_html_output($candidat_g['services_proposes']); ?>"
                                            >Approuver</button>
                                            <form method="POST" action="admin_dashboard.php#admin-garages-section" class="form-admin-inline" onsubmit="return confirm('Rejeter cette candidature ?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="reject_candidature_garage">
                                                <input type="hidden" name="id_candidat_garage" value="<?php echo $candidat_g['id_candidat']; ?>">
                                                <button type="submit" class="admin-action-btn delete-btn">Rejeter</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="admin-table-container detail-card" id="partenaires-garages-sub" style="margin-top: 2rem;">
                    <h2>Garages Partenaires Approuvés</h2>
                    <?php if (empty($partenaires_garages_section)): ?>
                        <p>Aucun garage partenaire approuvé pour le moment.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nom Garage</th>
                                    <th>Contact</th>
                                    <th>Adresse</th>
                                    <th>Coordonnées GPS</th>
                                    <th>Visible</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partenaires_garages_section as $partenaire_g): ?>
                                    <tr>
                                        <td><?php echo sanitize_html_output($partenaire_g['nom_garage']); ?></td>
                                        <td>
                                            Email: <?php echo sanitize_html_output($partenaire_g['email']); ?><br>
                                            Tél: <?php echo sanitize_html_output($partenaire_g['telephone']); ?>
                                        </td>
                                        <td><?php echo sanitize_html_output($partenaire_g['adresse_complete']); ?></td>
                                        <td>
                                            Lat: <?php echo sanitize_html_output($partenaire_g['latitude'] ?? 'N/A'); ?><br>
                                            Lon: <?php echo sanitize_html_output($partenaire_g['longitude'] ?? 'N/A'); ?>
                                        </td>
                                        <td><?php echo $partenaire_g['est_visible'] ? 'Oui' : 'Non'; ?></td>
                                        <td class="actions">
                                             <button type="button" class="admin-action-btn edit-btn open-edit-garage-modal"
                                                    data-id="<?php echo $partenaire_g['id_garage']; ?>"
                                                    data-nom="<?php echo sanitize_html_output($partenaire_g['nom_garage']); ?>"
                                                    data-adresse="<?php echo sanitize_html_output($partenaire_g['adresse_complete']); ?>"
                                                    data-tel="<?php echo sanitize_html_output($partenaire_g['telephone']); ?>"
                                                    data-email="<?php echo sanitize_html_output($partenaire_g['email']); ?>"
                                                    data-services="<?php echo sanitize_html_output($partenaire_g['services_offerts']); ?>"
                                                    data-description="<?php echo sanitize_html_output($partenaire_g['description_courte']); ?>"
                                                    data-lat="<?php echo sanitize_html_output($partenaire_g['latitude']); ?>"
                                                    data-lon="<?php echo sanitize_html_output($partenaire_g['longitude']); ?>"
                                                    data-horaires="<?php echo sanitize_html_output($partenaire_g['horaires_ouverture']); ?>"
                                                    data-website="<?php echo sanitize_html_output($partenaire_g['url_website']); ?>"
                                                    data-visible="<?php echo $partenaire_g['est_visible'] ? '1' : '0'; ?>"
                                            >Modifier</button>
                                            <form method="POST" action="admin_dashboard.php#admin-garages-section" class="form-admin-inline" onsubmit="return confirm('Supprimer ce partenaire ? Cette action est irréversible.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="delete_partenaire_garage">
                                                <input type="hidden" name="id_garage" value="<?php echo $partenaire_g['id_garage']; ?>">
                                                <button type="submit" class="admin-action-btn delete-btn">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Fin Section Gestion des Garages Partenaires -->
        </main>
    </div>

    <footer class="admin-footer">
        <p>&copy; <span id="current-year-admin-dash"></span> Ouipneu.fr - Interface d'Administration</p>
    </footer>

    <!-- Modal pour Produits -->
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

    <!-- Modal pour Gestion des Garages -->
    <div id="garage-admin-modal-overlay" class="modal-admin" style="display: none;">
        <div class="modal-admin-content">
            <div class="product-modal-header"> <!-- Réutilisation de la classe pour style similaire -->
                <h2 id="garage-modal-title">Approuver/Modifier Garage</h2>
                <button type="button" class="close-garage-admin-modal" aria-label="Fermer">&times;</button>
            </div>
            <div class="product-modal-body"> <!-- Réutilisation de la classe pour style similaire -->
                <form id="garage-admin-form" method="POST" action="admin_dashboard.php#admin-garages-section">
                    <input type="hidden" name="action" id="garage-modal-action" value="">
                    <input type="hidden" name="id_candidat_modal_garage" id="garage-modal-id-candidat" value="">
                    <input type="hidden" name="id_garage_modal" id="garage-modal-id-garage" value="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label for="garage-modal-nom-garage">Nom du Garage:</label>
                        <input type="text" id="garage-modal-nom-garage" name="nom_garage_modal" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-adresse-complete">Adresse Complète:</label>
                        <textarea id="garage-modal-adresse-complete" name="adresse_complete_modal" rows="3" required class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-telephone">Téléphone:</label>
                        <input type="tel" id="garage-modal-telephone" name="telephone_modal" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-email">Email:</label>
                        <input type="email" id="garage-modal-email" name="email_modal" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-services-offerts">Services Offerts:</label>
                        <textarea id="garage-modal-services-offerts" name="services_offerts_modal" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-description-courte">Description Courte (pour page publique):</label>
                        <textarea id="garage-modal-description-courte" name="description_courte_modal" rows="3" class="form-control"></textarea>
                    </div>
                    <div style="display:flex; gap: 1rem;">
                        <div class="form-group" style="flex:1;">
                            <label for="garage-modal-latitude">Latitude (ex: 48.8566):</label>
                            <input type="number" step="any" id="garage-modal-latitude" name="latitude_modal" class="form-control">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="garage-modal-longitude">Longitude (ex: 2.3522):</label>
                            <input type="number" step="any" id="garage-modal-longitude" name="longitude_modal" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-horaires-ouverture">Horaires d'ouverture (texte libre):</label>
                        <input type="text" id="garage-modal-horaires-ouverture" name="horaires_ouverture_modal" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="garage-modal-url-website">Site Web (URL complète):</label>
                        <input type="text" id="garage-modal-url-website" name="url_website_modal" class="form-control">
                    </div>
                    <div class="form-group" id="garage-modal-visibility-field" style="display:none;">
                        <label for="garage-modal-est-visible">Visible sur le site public:</label>
                        <select id="garage-modal-est-visible" name="est_visible_modal" class="form-control">
                            <option value="1">Oui</option>
                            <option value="0">Non</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="product-modal-footer"> <!-- Réutilisation de la classe pour style similaire -->
                <button type="button" id="cancel-garage-admin-action" class="cta-button secondary close-garage-admin-modal">Annuler</button>
                <button type="submit" form="garage-admin-form" class="cta-button">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('current-year-admin-dash').textContent = new Date().getFullYear();

        // --- ALGORITHME DE RECHERCHE PRODUITS ---
        const productSearchInput = document.getElementById('product-search-input');
        const productsTableBody = document.getElementById('products-table-body-NEW');

        if (productSearchInput && productsTableBody) {
            productSearchInput.addEventListener('input', function() {
                const searchTerms = this.value.toLowerCase().trim().split(/\s+/).filter(term => term.length > 0);
                const rows = productsTableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    if (row.hasAttribute('data-product-search')) {
                        const searchContent = row.dataset.productSearch || '';
                        const normalizedSearchContent = searchContent.toLowerCase().replace(/[^a-z0-9]/gi, '');
                        let isMatch = true;
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
        
        // --- LOGIQUE DE NAVIGATION ADMIN ---
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
             // Ensure the URL hash is updated for direct navigation/refresh
            if (window.location.hash !== `#${targetId}`) {
                window.location.hash = targetId;
            }
        }

        adminNavLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = e.currentTarget.dataset.target;
                switchAdminSection(targetId);
            });
        });
        
        // --- GESTION MODALE PRODUITS ---
        const productModalOverlay = document.getElementById('product-modal-overlay');
        const addProductButton = document.getElementById('add-product-button');
        const closeProductModalBtn = document.getElementById('close-product-modal-btn');
        const cancelAddProductBtn = document.getElementById('cancel-add-product-NEW');
        const productForm = document.getElementById('add-product-form-NEW');
        const productFormTitle = document.getElementById('product-form-title-NEW');
        const editProductIdInput = document.getElementById('edit-product-id-NEW');
        const currentImagePathInput = document.getElementById('current-image-path-NEW');


        function showProductModal() { if(productModalOverlay) productModalOverlay.style.display = 'flex'; }
        function hideProductModal() { if(productModalOverlay) productModalOverlay.style.display = 'none'; }

        if(addProductButton) {
            addProductButton.addEventListener('click', () => {
                if(productForm) productForm.reset();
                if(productFormTitle) productFormTitle.textContent = 'Ajouter un Nouveau Produit';
                if(editProductIdInput) editProductIdInput.value = '';
                if(currentImagePathInput) currentImagePathInput.value = '';
                // Ensure default radio for status is checked if applicable (e.g., 'Actif')
                const activeStatusRadio = document.getElementById('product-status-active-NEW');
                if (activeStatusRadio) activeStatusRadio.checked = true;
                showProductModal();
            });
        }
        
        document.querySelectorAll('.edit-product-btn-js').forEach(button => {
            button.addEventListener('click', function() {
                const productData = JSON.parse(this.dataset.product);
                if(productFormTitle) productFormTitle.textContent = 'Modifier le Produit';
                if(editProductIdInput) editProductIdInput.value = productData.id;
                if(currentImagePathInput) currentImagePathInput.value = productData.image || '';

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

        // --- GESTION MODALE GARAGES ---
        const garageModalOverlay = document.getElementById('garage-admin-modal-overlay');
        const garageModalForm = document.getElementById('garage-admin-form');
        const garageModalTitle = document.getElementById('garage-modal-title');
        const garageModalActionInput = document.getElementById('garage-modal-action');
        const garageModalIdCandidatInput = document.getElementById('garage-modal-id-candidat');
        const garageModalIdGarageInput = document.getElementById('garage-modal-id-garage');
        const garageModalVisibilityField = document.getElementById('garage-modal-visibility-field');

        function showGarageModal() { if(garageModalOverlay) garageModalOverlay.style.display = 'flex'; }
        function hideGarageModal() { if(garageModalOverlay) garageModalOverlay.style.display = 'none'; }

        document.querySelectorAll('.open-approve-garage-modal').forEach(button => {
            button.addEventListener('click', function() {
                if(garageModalForm) garageModalForm.reset();
                if(garageModalTitle) garageModalTitle.textContent = 'Approuver Candidature Garage';
                if(garageModalActionInput) garageModalActionInput.value = 'approve_candidature_garage';
                if(garageModalIdCandidatInput) garageModalIdCandidatInput.value = this.dataset.id;
                if(garageModalIdGarageInput) garageModalIdGarageInput.value = ''; // Pas d'ID de partenaire existant

                document.getElementById('garage-modal-nom-garage').value = this.dataset.nom || '';
                document.getElementById('garage-modal-adresse-complete').value = this.dataset.adresse || '';
                document.getElementById('garage-modal-telephone').value = this.dataset.tel || '';
                document.getElementById('garage-modal-email').value = this.dataset.email || '';
                document.getElementById('garage-modal-services-offerts').value = this.dataset.services || '';
                // Champs spécifiques à l'approbation/création, peuvent être vides ou pré-remplis si souhaité
                document.getElementById('garage-modal-description-courte').value = '';
                document.getElementById('garage-modal-latitude').value = '';
                document.getElementById('garage-modal-longitude').value = '';
                document.getElementById('garage-modal-horaires-ouverture').value = '';
                document.getElementById('garage-modal-url-website').value = '';

                if(garageModalVisibilityField) garageModalVisibilityField.style.display = 'none';
                const estVisibleSelect = document.getElementById('garage-modal-est-visible');
                if (estVisibleSelect) estVisibleSelect.value = '1'; // Default to visible on creation

                showGarageModal();
            });
        });

        document.querySelectorAll('.open-edit-garage-modal').forEach(button => {
            button.addEventListener('click', function() {
                if(garageModalForm) garageModalForm.reset();
                if(garageModalTitle) garageModalTitle.textContent = 'Modifier Garage Partenaire';
                if(garageModalActionInput) garageModalActionInput.value = 'update_partenaire_garage';
                if(garageModalIdGarageInput) garageModalIdGarageInput.value = this.dataset.id;
                if(garageModalIdCandidatInput) garageModalIdCandidatInput.value = ''; // Pas d'ID de candidat

                document.getElementById('garage-modal-nom-garage').value = this.dataset.nom || '';
                document.getElementById('garage-modal-adresse-complete').value = this.dataset.adresse || '';
                document.getElementById('garage-modal-telephone').value = this.dataset.tel || '';
                document.getElementById('garage-modal-email').value = this.dataset.email || '';
                document.getElementById('garage-modal-services-offerts').value = this.dataset.services || '';
                document.getElementById('garage-modal-description-courte').value = this.dataset.description || '';
                document.getElementById('garage-modal-latitude').value = this.dataset.lat || '';
                document.getElementById('garage-modal-longitude').value = this.dataset.lon || '';
                document.getElementById('garage-modal-horaires-ouverture').value = this.dataset.horaires || '';
                document.getElementById('garage-modal-url-website').value = this.dataset.website || '';

                const estVisibleSelect = document.getElementById('garage-modal-est-visible');
                if (estVisibleSelect) estVisibleSelect.value = this.dataset.visible === '1' ? '1' : '0';


                if(garageModalVisibilityField) garageModalVisibilityField.style.display = 'block';
                showGarageModal();
            });
        });

        document.querySelectorAll('.close-garage-admin-modal').forEach(btn => {
            btn.addEventListener('click', hideGarageModal);
        });
        if(garageModalOverlay) {
            garageModalOverlay.addEventListener('click', e => {
                if (e.target === garageModalOverlay) hideGarageModal();
            });
        }


        // --- INITIALISATION DE LA SECTION VISIBLE ---
        let initialSection = window.location.hash.substring(1) || defaultSectionId;
        if (!document.getElementById(initialSection)) {
            initialSection = defaultSectionId;
        }
        // Ensure the section exists before trying to switch to it
        if (document.getElementById(initialSection)) {
            switchAdminSection(initialSection);
        } else {
            switchAdminSection(defaultSectionId); // Fallback to default if hash is invalid
        }


        // Chart.js Example (si besoin)
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
                    datasets: [{
                        label: 'Ventes Mensuelles',
                        data: [1200, 1900, 3000, 5000, 2300, 3200],
                        borderColor: 'var(--accent-primary)',
                        backgroundColor: 'rgba(255, 221, 3, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { color: 'var(--text-secondary)'}, grid: { color: 'var(--border-color)' } },
                              x: { ticks: { color: 'var(--text-secondary)'}, grid: { color: 'var(--border-color)' } } },
                    plugins: { legend: { labels: { color: 'var(--text-light)' } } }
                }
            });
        }

        // --- GESTION DYNAMIQUE DES TRANSPORTEURS DANS LES PARAMÈTRES ---
        const carriersContainer = document.getElementById('carriers-container');
        const addCarrierBtn = document.getElementById('add-carrier-btn');

        function createCarrierGroupHTML(index) {
            return `
                <h4>Transporteur ${index + 1} <button type="button" class="remove-carrier-btn" style="font-size:0.8em;padding:0.2em 0.5em; margin-left:10px;">&times; Supprimer</button></h4>
                <div class="form-group">
                    <label for="carrier-name-${index}">Nom du transporteur:</label>
                    <input type="text" id="carrier-name-${index}" name="settings[carriers][${index}][name]" class="form-control">
                </div>
                <div class="form-group">
                    <label for="carrier-price-${index}">Prix (€):</label>
                    <input type="number" step="0.01" id="carrier-price-${index}" name="settings[carriers][${index}][price]" class="form-control">
                </div>
                <div class="form-group">
                    <label for="carrier-delay-${index}">Délai de livraison (ex: 2-3 jours ouvrés):</label>
                    <input type="text" id="carrier-delay-${index}" name="settings[carriers][${index}][delay]" class="form-control">
                </div>
            `;
        }

        function renumberCarrierGroups() {
            const groups = carriersContainer.querySelectorAll('.carrier-group');
            groups.forEach((group, i) => {
                group.dataset.index = i;
                group.querySelector('h4').innerHTML = `Transporteur ${i + 1} ${i > 0 ? '<button type="button" class="remove-carrier-btn" style="font-size:0.8em;padding:0.2em 0.5em; margin-left:10px;">&times; Supprimer</button>' : ''}`;
                group.querySelectorAll('label, input').forEach(el => {
                    if (el.hasAttribute('for')) {
                        el.setAttribute('for', el.getAttribute('for').replace(/-\d+$/, `-${i}`));
                    }
                    if (el.hasAttribute('id')) {
                        el.id = el.id.replace(/-\d+$/, `-${i}`);
                    }
                    if (el.hasAttribute('name')) {
                        el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
                    }
                });
            });
        }


        if (addCarrierBtn && carriersContainer) {
            addCarrierBtn.addEventListener('click', () => {
                const newIndex = carriersContainer.querySelectorAll('.carrier-group').length;
                const newGroup = document.createElement('div');
                newGroup.classList.add('carrier-group');
                newGroup.dataset.index = newIndex;
                newGroup.innerHTML = createCarrierGroupHTML(newIndex);
                carriersContainer.appendChild(newGroup);
                renumberCarrierGroups(); // Pour s'assurer que les boutons supprimer sont corrects
            });
        }

        if (carriersContainer) {
            carriersContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-carrier-btn') || event.target.closest('.remove-carrier-btn')) {
                    const groupToRemove = event.target.closest('.carrier-group');
                    if (groupToRemove) {
                        if (carriersContainer.querySelectorAll('.carrier-group').length > 1) {
                            groupToRemove.remove();
                            renumberCarrierGroups(); // Re-numéroter après suppression
                        } else {
                            // Optionnel: vider les champs du premier groupe
                            groupToRemove.querySelectorAll('input').forEach(input => input.value = '');
                            // alert("Vous ne pouvez pas supprimer le dernier transporteur. Videz ses champs si vous ne souhaitez pas l'utiliser.");
                        }
                    }
                }
            });
             // S'assurer que le premier groupe n'a pas de bouton supprimer s'il est seul au chargement
            renumberCarrierGroups();
        }
        // --- FIN GESTION DYNAMIQUE DES TRANSPORTEURS ---

        // --- Gestion de l'auto-masquage des notifications globales ---
        const globalNotificationBar = document.querySelector('.global-notification-bar.show');
        if (globalNotificationBar) {
            setTimeout(() => {
                globalNotificationBar.classList.remove('show');
                // Optionnel: supprimer l'élément du DOM après l'animation si vous avez une transition CSS
                // setTimeout(() => { globalNotificationBar.remove(); }, 500); // 500ms pour la transition
            }, 5000); // Masquer après 5 secondes

            // Masquer aussi si on clique dessus
            globalNotificationBar.addEventListener('click', () => {
                globalNotificationBar.classList.remove('show');
                // Optionnel: setTimeout(() => { globalNotificationBar.remove(); }, 500);
            });
        }
        // --- Fin gestion notifications ---

    });
    </script>
</body>
</html>
