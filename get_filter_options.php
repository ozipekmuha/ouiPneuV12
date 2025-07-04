<?php
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

$response = [
    'largeurs' => [],
    'hauteurs' => [],
    'diametres' => [],
    'charges' => [],
    'vitesses' => [],
    'marques' => [],
    'dimensions_completes' => [] // Pour aider à la logique de dépendance
];

try {
    // 1. Récupérer toutes les dimensions uniques et les stocker
    // La colonne 'taille' contient des chaînes comme "205/55R16 91V" ou "195/65R15"
    // Nous devons parser cela pour extraire les composants.
    $stmt_all_tailles = $pdo->query("SELECT DISTINCT taille, nom FROM Pneus WHERE est_actif = TRUE");
    $all_tailles_db = $stmt_all_tailles->fetchAll(PDO::FETCH_ASSOC);

    $parsed_dimensions = [];
    $marques_set = [];

    // Utilisation de la fonction existante parseTireSize si elle est adaptée, sinon parser ici
    // Supposons que parseTireSize existe et est accessible, sinon il faut la définir ou l'inclure.
    // Pour ce script, je vais inclure une version simplifiée ou une logique directe.

    foreach ($all_tailles_db as $pneu) {
        $taille_str = $pneu['taille'];
        $nom_pneu = $pneu['nom'];

        // Extraction Marque simplifiée (la première partie du nom)
        $nom_parts = explode(' ', $nom_pneu);
        if (count($nom_parts) > 0 && !empty($nom_parts[0])) {
            $marques_set[trim($nom_parts[0])] = true;
        }

        // Parser la taille: "Largeur/HauteurR Diametre ChargeVitesse"
        // Exemple: "205/55R16 91V" ou "195/65R15"
        $largeur = null;
        $hauteur = null;
        $diametre = null;
        $charge = null;
        $vitesse = null;

        // Regex pour "205/55R16 91V" ou "205/55 R16 91V" ou "205 / 55 R 16 91V"
        if (preg_match('/^(\d{3})\s*\/\s*(\d{2})\s*Z?R\s*(\d{2})\s*(\d{2,3}[A-Z]?)?\s*([A-Z](?:\/[A-Z])?)?$/i', $taille_str, $matches)) {
            $largeur = $matches[1];
            $hauteur = $matches[2];
            $diametre = $matches[3];
            if (isset($matches[4]) && !empty($matches[4])) {
                // Séparer charge et vitesse si groupés, ex: "91V"
                if (preg_match('/^(\d{2,3})([A-Z])$/i', $matches[4], $charge_vitesse_parts)) {
                    $charge = $charge_vitesse_parts[1];
                    $vitesse = strtoupper($charge_vitesse_parts[2]);
                } else if (is_numeric($matches[4])) { // Juste charge
                     $charge = $matches[4];
                     if(isset($matches[5]) && !empty($matches[5])) $vitesse = strtoupper($matches[5]);
                } else if (ctype_alpha($matches[4])) { // Juste vitesse (peu probable ici, mais pour être complet)
                    $vitesse = strtoupper($matches[4]);
                }
            }
            // Cas où charge et vitesse sont séparés par un espace et vitesse est dans $matches[5]
             if (isset($matches[5]) && !empty($matches[5]) && empty($vitesse)) {
                $vitesse = strtoupper($matches[5]);
            }


        } elseif (preg_match('/^(\d{3})\s*\/\s*(\d{2})\s*Z?R\s*(\d{2})$/i', $taille_str, $matches_simple)) {
            // Cas sans charge/vitesse: "205/55R16"
            $largeur = $matches_simple[1];
            $hauteur = $matches_simple[2];
            $diametre = $matches_simple[3];
        }


        if ($largeur && $hauteur && $diametre) {
            $dim_key = "{$largeur}-{$hauteur}-{$diametre}";
            if (!isset($parsed_dimensions[$dim_key])) {
                $parsed_dimensions[$dim_key] = [
                    'largeur' => $largeur,
                    'hauteur' => $hauteur,
                    'diametre' => $diametre,
                    'charges' => [],
                    'vitesses' => []
                ];
            }
            if ($charge && !in_array($charge, $parsed_dimensions[$dim_key]['charges'])) {
                $parsed_dimensions[$dim_key]['charges'][] = $charge;
            }
            if ($vitesse && !in_array($vitesse, $parsed_dimensions[$dim_key]['vitesses'])) {
                $parsed_dimensions[$dim_key]['vitesses'][] = $vitesse;
            }
        }
    }

    $temp_largeurs = [];
    $temp_hauteurs = [];
    $temp_diametres = [];
    $temp_charges_all = [];
    $temp_vitesses_all = [];

    foreach ($parsed_dimensions as $dim) {
        $temp_largeurs[$dim['largeur']] = true;
        $temp_hauteurs[$dim['hauteur']] = true;
        $temp_diametres[$dim['diametre']] = true;
        foreach($dim['charges'] as $ch) $temp_charges_all[$ch] = true;
        foreach($dim['vitesses'] as $vt) $temp_vitesses_all[$vt] = true;
        // Stocker la combinaison complète pour la logique de dépendance
        $response['dimensions_completes'][] = [
            'l' => $dim['largeur'],
            'h' => $dim['hauteur'],
            'd' => $dim['diametre'],
            'c' => array_values(array_unique($dim['charges'])), // S'assurer que c'est un tableau
            'v' => array_values(array_unique($dim['vitesses']))  // S'assurer que c'est un tableau
        ];
    }

    $response['largeurs'] = array_keys($temp_largeurs);
    sort($response['largeurs'], SORT_NUMERIC);

    $response['hauteurs'] = array_keys($temp_hauteurs);
    sort($response['hauteurs'], SORT_NUMERIC);

    $response['diametres'] = array_keys($temp_diametres);
    sort($response['diametres'], SORT_NUMERIC);
    
    $response['charges'] = array_keys($temp_charges_all);
    sort($response['charges'], SORT_NUMERIC);

    $response['vitesses'] = array_keys($temp_vitesses_all);
    sort($response['vitesses'], SORT_STRING);

    $response['marques'] = array_keys($marques_set);
    sort($response['marques'], SORT_STRING);


} catch (PDOException $e) {
    // En cas d'erreur, retourner une réponse d'erreur
    // Il serait préférable de loguer l'erreur côté serveur également.
    error_log("Erreur PDO dans get_filter_options.php: " . $e->getMessage());
    $response['error'] = "Erreur lors de la récupération des options de filtre.";
    // Il est important de ne pas exposer les détails de l'erreur PDO directement au client en production.
}

echo json_encode($response);
?>
