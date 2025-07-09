<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Sécurité : Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['id_utilisateur']) || !$_SESSION['est_admin']) {
    $_SESSION['error_message'] = "Accès non autorisé.";
    header("Location: login.php");
    exit;
}

$admin_page_title = "Gestion des Garages Partenaires";
$feedback_message = '';
$feedback_type = '';

// Traitement des actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    try {
        if ($_POST['action'] == 'approve_candidature' && isset($_POST['id_candidat_modal'])) {
            $id_candidat = (int)$_POST['id_candidat_modal'];
            // Récupérer les données du formulaire modal
            $nom_garage = trim($_POST['nom_garage']);
            $adresse_complete = trim($_POST['adresse_complete']);
            $telephone = trim($_POST['telephone']);
            $email = trim($_POST['email']);
            $services_offerts = trim($_POST['services_offerts']);
            $description_courte = trim($_POST['description_courte']);
            $latitude = !empty($_POST['latitude']) ? filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT) : null;
            $longitude = !empty($_POST['longitude']) ? filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT) : null;
            $horaires_ouverture = trim($_POST['horaires_ouverture']);
            $url_website = trim($_POST['url_website']);
            // est_visible est à 1 par défaut pour une nouvelle approbation

            if (empty($nom_garage) || empty($adresse_complete)) {
                $feedback_message = "Le nom du garage et l'adresse sont requis pour l'approbation.";
                $feedback_type = 'error';
            } else {
                // Insérer dans GaragesPartenaires
                $sql_insert_partenaire = "INSERT INTO GaragesPartenaires
                    (nom_garage, adresse_complete, telephone, email, services_offerts, description_courte, latitude, longitude, horaires_ouverture, url_website, est_visible)
                    VALUES (:nom, :adresse, :tel, :email, :services, :desc, :lat, :lon, :horaires, :site, TRUE)";
                $stmt_insert = $pdo->prepare($sql_insert_partenaire);
                $stmt_insert->execute([
                    ':nom' => $nom_garage, ':adresse' => $adresse_complete, ':tel' => $telephone, ':email' => $email,
                    ':services' => $services_offerts, ':desc' => $description_courte, ':lat' => $latitude, ':lon' => $longitude,
                    ':horaires' => $horaires_ouverture, ':site' => $url_website
                ]);

                // Mettre à jour le statut du candidat
                $sql_update_candidat = "UPDATE GaragesCandidats SET statut = 'approuve' WHERE id_candidat = :id_candidat";
                $stmt_update = $pdo->prepare($sql_update_candidat);
                $stmt_update->execute([':id_candidat' => $id_candidat]);

                $feedback_message = "Candidature approuvée et ajoutée aux partenaires.";
                $feedback_type = 'success';
            }
        } elseif ($_POST['action'] == 'reject_candidature' && isset($_POST['id_candidat'])) {
            $id_candidat = (int)$_POST['id_candidat'];
            $sql_update_candidat = "UPDATE GaragesCandidats SET statut = 'rejete' WHERE id_candidat = :id_candidat";
            $stmt_update = $pdo->prepare($sql_update_candidat);
            $stmt_update->execute([':id_candidat' => $id_candidat]);
            $feedback_message = "Candidature rejetée.";
            $feedback_type = 'success';

        } elseif ($_POST['action'] == 'update_partenaire' && isset($_POST['id_garage_modal'])) {
            $id_garage = (int)$_POST['id_garage_modal'];
            // Récupérer toutes les données du formulaire modal
            $nom_garage = trim($_POST['nom_garage']);
            $adresse_complete = trim($_POST['adresse_complete']);
            $telephone = trim($_POST['telephone']);
            $email = trim($_POST['email']);
            $services_offerts = trim($_POST['services_offerts']);
            $description_courte = trim($_POST['description_courte']);
            $latitude = !empty($_POST['latitude']) ? filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT) : null;
            $longitude = !empty($_POST['longitude']) ? filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT) : null;
            $horaires_ouverture = trim($_POST['horaires_ouverture']);
            $url_website = trim($_POST['url_website']);
            $est_visible = isset($_POST['est_visible']) ? (int)$_POST['est_visible'] : 0;

            if (empty($nom_garage) || empty($adresse_complete)) {
                 $feedback_message = "Le nom du garage et l'adresse sont requis.";
                 $feedback_type = 'error';
            } else {
                $sql_update_partenaire = "UPDATE GaragesPartenaires SET
                    nom_garage = :nom, adresse_complete = :adresse, telephone = :tel, email = :email,
                    services_offerts = :services, description_courte = :desc, latitude = :lat, longitude = :lon,
                    horaires_ouverture = :horaires, url_website = :site, est_visible = :visible
                    WHERE id_garage = :id_garage";
                $stmt_update = $pdo->prepare($sql_update_partenaire);
                $stmt_update->execute([
                    ':nom' => $nom_garage, ':adresse' => $adresse_complete, ':tel' => $telephone, ':email' => $email,
                    ':services' => $services_offerts, ':desc' => $description_courte, ':lat' => $latitude, ':lon' => $longitude,
                    ':horaires' => $horaires_ouverture, ':site' => $url_website, ':visible' => $est_visible,
                    ':id_garage' => $id_garage
                ]);
                $feedback_message = "Informations du partenaire mises à jour.";
                $feedback_type = 'success';
            }
        } elseif ($_POST['action'] == 'delete_partenaire' && isset($_POST['id_garage'])) {
            $id_garage = (int)$_POST['id_garage'];
            $sql_delete = "DELETE FROM GaragesPartenaires WHERE id_garage = :id_garage";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([':id_garage' => $id_garage]);
            $feedback_message = "Garage partenaire supprimé.";
            $feedback_type = 'success';
        }
    } catch (PDOException $e) {
        error_log("Erreur action admin_garages: " . $e->getMessage());
        $feedback_message = "Une erreur de base de données est survenue: " . $e->getMessage();
        $feedback_type = 'error';
    }
    // Pour éviter la resoumission du formulaire POST lors du rafraîchissement
    // header("Location: admin_garages.php?feedback_type=" . $feedback_type . "&feedback_message=" . urlencode($feedback_message) . "#" . ($_POST['action'] == 'approve_candidature' || $_POST['action'] == 'reject_candidature' ? 'candidatures' : 'partenaires'));
    // exit;
    // Note: la redirection ci-dessus peut être complexe avec les messages. On va se contenter d'afficher le message sur la même page pour l'instant.
}


// Récupération des candidatures en attente
$candidatures = [];
try {
    $stmt_candidats = $pdo->query("SELECT * FROM GaragesCandidats WHERE statut = 'en_attente' ORDER BY date_soumission DESC");
    $candidatures = $stmt_candidats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur admin_garages (candidats): " . $e->getMessage());
    $feedback_message = "Erreur lors de la récupération des candidatures.";
    $feedback_type = 'error';
}

// Récupération des partenaires approuvés
$partenaires = [];
try {
    $stmt_partenaires = $pdo->query("SELECT * FROM GaragesPartenaires ORDER BY nom_garage ASC");
    $partenaires = $stmt_partenaires->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur admin_garages (partenaires): " . $e->getMessage());
    $feedback_message = "Erreur lors de la récupération des partenaires.";
    $feedback_type = 'error';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize_html_output($admin_page_title); ?> - Administration Ouipneu.fr</title>
    <link rel="stylesheet" href="css/style.css"> <!-- Assurez-vous que le style est adapté -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Styles spécifiques pour la page d'admin */
        .admin-container { max-width: 1200px; margin: 2rem auto; padding: 1.5rem; background-color: var(--bg-surface); border-radius: var(--border-radius-medium); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .admin-section { margin-bottom: 3rem; }
        .admin-section h2 { color: var(--accent-primary); margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .admin-table th, .admin-table td { padding: 0.75rem; border: 1px solid var(--border-color); text-align: left; vertical-align: top; }
        .admin-table th { background-color: var(--bg-dark); color: var(--text-light); font-weight: var(--font-weight-semibold); }
        .admin-table td .actions a { margin-right: 0.5rem; color: var(--accent-primary); text-decoration: none; }
        .admin-table td .actions a:hover { text-decoration: underline; }
        .admin-table td .actions .delete-link { color: #e74c3c; }
        .admin-table td ul { padding-left: 1.2rem; margin-top: 0.3rem; }
        .admin-table td ul li { font-size: 0.85rem; margin-bottom: 0.2rem; }
        .feedback-message { padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--border-radius-small); text-align: center; font-weight: var(--font-weight-medium); }
        .feedback-message.success { background-color: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; color: #2ecc71; }
        .feedback-message.error { background-color: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; }
        .form-admin-inline { display: inline-block; margin:0; padding:0;}
        .form-admin-inline button { background: none; border: none; color: var(--accent-primary); cursor: pointer; text-decoration: underline; padding: 0; font-size: inherit; }
        .form-admin-inline button.delete-link { color: #e74c3c; }
        .form-admin-inline button:hover { color: var(--accent-primary-darker); }
        .form-admin-inline button.delete-link:hover { color: #c0392b; }

        .modal-admin { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .modal-admin-content { background-color: var(--bg-surface); margin: 10% auto; padding: 2rem; border-radius: var(--border-radius-medium); width: 80%; max-width: 700px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .modal-admin-content h3 { color: var(--accent-primary); margin-top: 0; margin-bottom: 1.5rem; }
        .modal-admin-content .form-group { margin-bottom: 1rem; }
        .modal-admin-content .form-group label { display: block; color: var(--text-light); margin-bottom: 0.3rem; font-weight: var(--font-weight-medium); }
        .modal-admin-content .form-group input[type="text"],
        .modal-admin-content .form-group input[type="email"],
        .modal-admin-content .form-group input[type="tel"],
        .modal-admin-content .form-group input[type="number"],
        .modal-admin-content .form-group textarea {
            width: 100%; padding: 0.7rem; background-color: var(--bg-dark); color: var(--text-light); border: 1px solid var(--border-color); border-radius: var(--border-radius-small); font-size: 0.95rem; box-sizing: border-box;
        }
        .modal-admin-content .form-group textarea { min-height: 80px; resize: vertical; }
        .modal-admin-content .form-actions { margin-top: 1.5rem; text-align: right; }
        .modal-admin-content .form-actions .cta-button { margin-left: 0.5rem; }
        .close-modal-admin { color: var(--text-secondary); float: right; font-size: 2rem; font-weight: bold; line-height: 1; }
        .close-modal-admin:hover, .close-modal-admin:focus { color: var(--text-light); text-decoration: none; cursor: pointer; }
    </style>
</head>
<body style="background-color: var(--bg-dark); color: var(--text-secondary);">

    <header id="main-header" style="position: static; margin-bottom: 1rem;">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center;">
            <div class="logo">
                <a href="index.php"><img src="./assets/images/logobg.png" alt="Logo Ouipneu.fr" style="max-width:150px;"></a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="admin_dashboard.php">Tableau de bord Admin</a></li>
                    <li><a href="index.php" target="_blank">Voir le site</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="admin-container">
        <h1><?php echo sanitize_html_output($admin_page_title); ?></h1>

        <?php if ($feedback_message): ?>
            <div class="feedback-message <?php echo $feedback_type; ?>"><?php echo $feedback_message; ?></div>
        <?php endif; ?>

        <div class="admin-section" id="candidatures">
            <h2>Candidatures en attente</h2>
            <?php if (empty($candidatures)): ?>
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
                        <?php foreach ($candidatures as $candidat): ?>
                            <tr>
                                <td><?php echo sanitize_html_output($candidat['nom_garage']); ?></td>
                                <td>
                                    Email: <?php echo sanitize_html_output($candidat['email_contact']); ?><br>
                                    Tél: <?php echo sanitize_html_output($candidat['telephone_garage']); ?><br>
                                    Adresse: <?php echo sanitize_html_output($candidat['adresse_garage']); ?>
                                </td>
                                <td><?php echo nl2br(sanitize_html_output($candidat['services_proposes'])); ?></td>
                                <td><?php echo nl2br(sanitize_html_output($candidat['message_partenaire'])); ?></td>
                                <td><?php echo date("d/m/Y H:i", strtotime($candidat['date_soumission'])); ?></td>
                                <td class="actions">
                                    <button type="button" class="open-approve-modal"
                                            data-id="<?php echo $candidat['id_candidat']; ?>"
                                            data-nom="<?php echo sanitize_html_output($candidat['nom_garage']); ?>"
                                            data-adresse="<?php echo sanitize_html_output($candidat['adresse_garage']); ?>"
                                            data-tel="<?php echo sanitize_html_output($candidat['telephone_garage']); ?>"
                                            data-email="<?php echo sanitize_html_output($candidat['email_contact']); ?>"
                                            data-services="<?php echo sanitize_html_output($candidat['services_proposes']); ?>"
                                    >Approuver</button>
                                    <form action="admin_garages.php" method="POST" class="form-admin-inline" onsubmit="return confirm('Rejeter cette candidature ?');">
                                        <input type="hidden" name="action" value="reject_candidature">
                                        <input type="hidden" name="id_candidat" value="<?php echo $candidat['id_candidat']; ?>">
                                        <button type="submit" class="delete-link">Rejeter</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="admin-section" id="partenaires">
            <h2>Garages Partenaires Approuvés</h2>
            <?php if (empty($partenaires)): ?>
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
                        <?php foreach ($partenaires as $partenaire): ?>
                            <tr>
                                <td><?php echo sanitize_html_output($partenaire['nom_garage']); ?></td>
                                <td>
                                    Email: <?php echo sanitize_html_output($partenaire['email']); ?><br>
                                    Tél: <?php echo sanitize_html_output($partenaire['telephone']); ?>
                                </td>
                                <td><?php echo sanitize_html_output($partenaire['adresse_complete']); ?></td>
                                <td>
                                    Lat: <?php echo sanitize_html_output($partenaire['latitude'] ?? 'N/A'); ?><br>
                                    Lon: <?php echo sanitize_html_output($partenaire['longitude'] ?? 'N/A'); ?>
                                </td>
                                <td><?php echo $partenaire['est_visible'] ? 'Oui' : 'Non'; ?></td>
                                <td class="actions">
                                     <button type="button" class="open-edit-modal"
                                            data-id="<?php echo $partenaire['id_garage']; ?>"
                                            data-nom="<?php echo sanitize_html_output($partenaire['nom_garage']); ?>"
                                            data-adresse="<?php echo sanitize_html_output($partenaire['adresse_complete']); ?>"
                                            data-tel="<?php echo sanitize_html_output($partenaire['telephone']); ?>"
                                            data-email="<?php echo sanitize_html_output($partenaire['email']); ?>"
                                            data-services="<?php echo sanitize_html_output($partenaire['services_offerts']); ?>"
                                            data-description="<?php echo sanitize_html_output($partenaire['description_courte']); ?>"
                                            data-lat="<?php echo sanitize_html_output($partenaire['latitude']); ?>"
                                            data-lon="<?php echo sanitize_html_output($partenaire['longitude']); ?>"
                                            data-horaires="<?php echo sanitize_html_output($partenaire['horaires_ouverture']); ?>"
                                            data-website="<?php echo sanitize_html_output($partenaire['url_website']); ?>"
                                            data-visible="<?php echo $partenaire['est_visible'] ? '1' : '0'; ?>"
                                    >Modifier</button>
                                    <form action="admin_garages.php" method="POST" class="form-admin-inline" onsubmit="return confirm('Supprimer ce partenaire ? Cette action est irréversible.');">
                                        <input type="hidden" name="action" value="delete_partenaire">
                                        <input type="hidden" name="id_garage" value="<?php echo $partenaire['id_garage']; ?>">
                                        <button type="submit" class="delete-link">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour Approbation/Modification -->
    <div id="garageModal" class="modal-admin">
        <div class="modal-admin-content">
            <span class="close-modal-admin">&times;</span>
            <h3 id="modalTitle">Approuver/Modifier Garage</h3>
            <form id="garageForm" action="admin_garages.php" method="POST">
                <input type="hidden" name="action" id="modalAction" value="">
                <input type="hidden" name="id_candidat_modal" id="id_candidat_modal" value="">
                <input type="hidden" name="id_garage_modal" id="id_garage_modal" value="">

                <div class="form-group">
                    <label for="modal_nom_garage">Nom du Garage:</label>
                    <input type="text" id="modal_nom_garage" name="nom_garage" required>
                </div>
                <div class="form-group">
                    <label for="modal_adresse_complete">Adresse Complète:</label>
                    <textarea id="modal_adresse_complete" name="adresse_complete" rows="3" required></textarea>
                </div>
                 <div class="form-group">
                    <label for="modal_telephone">Téléphone:</label>
                    <input type="tel" id="modal_telephone" name="telephone">
                </div>
                <div class="form-group">
                    <label for="modal_email">Email:</label>
                    <input type="email" id="modal_email" name="email">
                </div>
                <div class="form-group">
                    <label for="modal_services_offerts">Services Offerts:</label>
                    <textarea id="modal_services_offerts" name="services_offerts" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="modal_description_courte">Description Courte (pour page publique):</label>
                    <textarea id="modal_description_courte" name="description_courte" rows="3"></textarea>
                </div>
                <div style="display:flex; gap: 1rem;">
                    <div class="form-group" style="flex:1;">
                        <label for="modal_latitude">Latitude (ex: 48.8566):</label>
                        <input type="number" step="any" id="modal_latitude" name="latitude">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label for="modal_longitude">Longitude (ex: 2.3522):</label>
                        <input type="number" step="any" id="modal_longitude" name="longitude">
                    </div>
                </div>
                <div class="form-group">
                    <label for="modal_horaires_ouverture">Horaires d'ouverture (texte libre):</label>
                    <input type="text" id="modal_horaires_ouverture" name="horaires_ouverture">
                </div>
                <div class="form-group">
                    <label for="modal_url_website">Site Web (URL complète):</label>
                    <input type="text" id="modal_url_website" name="url_website">
                </div>
                 <div class="form-group" id="visibility_field_modal" style="display:none;"> <!-- Caché pour l'approbation initiale -->
                    <label for="modal_est_visible">Visible sur le site public:</label>
                    <select id="modal_est_visible" name="est_visible">
                        <option value="1">Oui</option>
                        <option value="0">Non</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="cta-button secondary close-modal-admin-btn">Annuler</button>
                    <button type="submit" class="cta-button">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('garageModal');
    const closeModalSpans = document.querySelectorAll('.close-modal-admin, .close-modal-admin-btn');
    const form = document.getElementById('garageForm');

    const modalFields = {
        id_candidat: document.getElementById('id_candidat_modal'),
        id_garage: document.getElementById('id_garage_modal'),
        action: document.getElementById('modalAction'),
        title: document.getElementById('modalTitle'),
        nom_garage: document.getElementById('modal_nom_garage'),
        adresse_complete: document.getElementById('modal_adresse_complete'),
        telephone: document.getElementById('modal_telephone'),
        email: document.getElementById('modal_email'),
        services_offerts: document.getElementById('modal_services_offerts'),
        description_courte: document.getElementById('modal_description_courte'),
        latitude: document.getElementById('modal_latitude'),
        longitude: document.getElementById('modal_longitude'),
        horaires_ouverture: document.getElementById('modal_horaires_ouverture'),
        url_website: document.getElementById('modal_url_website'),
        est_visible: document.getElementById('modal_est_visible'),
        visibility_field: document.getElementById('visibility_field_modal')
    };

    document.querySelectorAll('.open-approve-modal').forEach(button => {
        button.addEventListener('click', function() {
            form.reset();
            modalFields.title.textContent = 'Approuver Candidature';
            modalFields.action.value = 'approve_candidature';
            modalFields.id_candidat.value = this.dataset.id;
            modalFields.id_garage.value = ''; // Pas d'ID garage existant

            modalFields.nom_garage.value = this.dataset.nom;
            modalFields.adresse_complete.value = this.dataset.adresse;
            modalFields.telephone.value = this.dataset.tel;
            modalFields.email.value = this.dataset.email;
            modalFields.services_offerts.value = this.dataset.services;

            // Champs spécifiques aux partenaires, peuvent être vides ou pré-remplis si on veut
            modalFields.description_courte.value = '';
            modalFields.latitude.value = '';
            modalFields.longitude.value = '';
            modalFields.horaires_ouverture.value = '';
            modalFields.url_website.value = '';
            modalFields.est_visible.value = '1'; // Par défaut visible lors de l'approbation
            modalFields.visibility_field.style.display = 'none'; // Caché pour approbation

            modal.style.display = 'block';
        });
    });

    document.querySelectorAll('.open-edit-modal').forEach(button => {
        button.addEventListener('click', function() {
            form.reset();
            modalFields.title.textContent = 'Modifier Garage Partenaire';
            modalFields.action.value = 'update_partenaire';
            modalFields.id_garage.value = this.dataset.id;
            modalFields.id_candidat.value = ''; // Pas de lien vers candidat ici

            modalFields.nom_garage.value = this.dataset.nom;
            modalFields.adresse_complete.value = this.dataset.adresse;
            modalFields.telephone.value = this.dataset.tel;
            modalFields.email.value = this.dataset.email;
            modalFields.services_offerts.value = this.dataset.services;
            modalFields.description_courte.value = this.dataset.description;
            modalFields.latitude.value = this.dataset.lat;
            modalFields.longitude.value = this.dataset.lon;
            modalFields.horaires_ouverture.value = this.dataset.horaires;
            modalFields.url_website.value = this.dataset.website;
            modalFields.est_visible.value = this.dataset.visible;
            modalFields.visibility_field.style.display = 'block'; // Visible pour modification

            modal.style.display = 'block';
        });
    });

    closeModalSpans.forEach(span => {
        span.onclick = function() {
            modal.style.display = 'none';
        }
    });

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});
</script>
</body>
</html>
