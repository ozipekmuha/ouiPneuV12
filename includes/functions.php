<?php
// File: includes/functions.php
// Purpose: Centralized helper functions for the Ouipneu.fr website.

if (session_status() == PHP_SESSION_NONE) {
    // session_start();
}

function extractBrandFromName(string $product_name): string {
    $common_brands = ['Michelin', 'Continental', 'Goodyear', 'Pirelli', 'Bridgestone', 'Hankook', 'Dunlop', 'Nokian', 'Aptany', 'BFGoodrich'];
    foreach ($common_brands as $brand) {
        if (stripos($product_name, $brand) === 0) {
            return $brand;
        }
    }
    $parts = explode(' ', $product_name);
    return count($parts) > 0 ? $parts[0] : 'N/A';
}

function getProductStockStatus(?int $stock_disponible): array {
    $stock = (int)$stock_disponible;
    if ($stock > 20) {
        return ['text' => 'En Stock', 'class' => 'in-stock'];
    } elseif ($stock > 0) {
        return ['text' => 'Stock Faible', 'class' => 'low-stock'];
    } else {
        return ['text' => 'Épuisé', 'class' => 'out-of-stock'];
    }
}

function parseProductSpecifications(?string $specs_string): array {
    $is_runflat = false;
    $is_reinforced = false;
    if ($specs_string) {
        $specs_lower = strtolower($specs_string);
        if (strpos($specs_lower, 'runflat') !== false) $is_runflat = true;
        if (strpos($specs_lower, 'xl') !== false || strpos($specs_lower, 'reinforced') !== false || strpos($specs_lower, 'renforcé') !== false) $is_reinforced = true;
    }
    return ['is_runflat' => $is_runflat, 'is_reinforced' => $is_reinforced];
}

function getProductDisplayDetails(array $pneu_data): array {
    $stock_info = getProductStockStatus($pneu_data['stock_disponible'] ?? 0);
    $parsed_specs = parseProductSpecifications($pneu_data['specifications'] ?? '');
    $badge_html = '';
    if ($parsed_specs['is_runflat']) {
        $badge_html = '<span class="product-badge runflat">Runflat</span>';
    } elseif ($parsed_specs['is_reinforced']) {
        $badge_html = '<span class="product-badge reinforced">XL</span>';
    }
    return [
        'stock_text' => $stock_info['text'],
        'stock_class' => $stock_info['class'],
        'badge_html' => $badge_html
    ];
}

function parseTireSize(?string $taille_string): array {
    $details = ['dimension_base' => $taille_string ?? '', 'indice_charge' => '', 'indice_vitesse' => '', 'data_width' => '', 'data_ratio' => '', 'data_diameter' => ''];
    if (!$taille_string) return $details;
    if (preg_match('/(\d+\s*\/\s*\d+\s*Z?R\s*\d+)\s*(\d+[A-Z]?)\s*([A-Z](?:\/[A-Z])?)/i', $taille_string, $matches_full)) {
        $details['dimension_base'] = trim($matches_full[1]);
        $details['indice_charge'] = trim($matches_full[2]);
        $details['indice_vitesse'] = trim($matches_full[3]);
    } elseif (preg_match('/(\d+\s*\/\s*\d+\s*Z?R\s*\d+)/i', $taille_string, $matches_base)) {
        $details['dimension_base'] = trim($matches_base[1]);
    }
    if (preg_match('/(\d+)\s*\/\s*(\d+)\s*Z?R\s*(\d+)/i', $details['dimension_base'], $matches_data)) {
        $details['data_width'] = $matches_data[1];
        $details['data_ratio'] = $matches_data[2];
        $details['data_diameter'] = $matches_data[3];
    }
    return $details;
}

function sanitize_html_output(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function convertPriceToFloat(?string $prix_string): float {
    if ($prix_string === null) return 0.0;
    $cleaned_price = str_replace(['€', '$', ' ', 'EUR'], '', $prix_string);
    $cleaned_price = str_replace(',', '.', $cleaned_price);
    return floatval($cleaned_price);
}

function getUserAddresses(PDO $pdo, int $id_utilisateur): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Adresses WHERE id_utilisateur = :id_utilisateur ORDER BY est_principale_livraison DESC, est_principale_facturation DESC, id_adresse ASC");
        $stmt->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur getUserAddresses pour utilisateur ID $id_utilisateur: " . $e->getMessage());
        return [];
    }
}

function getUserOrdersSummary(PDO $pdo, int $id_utilisateur): array {
    try {
        $sql = "SELECT
                    c.id_commande, c.date_commande, c.montant_total_ttc, c.statut_commande,
                    c.montant_sous_total, c.montant_livraison, c.montant_reduction,
                    adr_liv.destinataire_nom_complet AS livraison_destinataire,
                    adr_liv.adresse_ligne1 AS livraison_l1, adr_liv.adresse_ligne2 AS livraison_l2,
                    adr_liv.code_postal AS livraison_cp, adr_liv.ville AS livraison_ville, adr_liv.pays AS livraison_pays,
                    adr_fact.destinataire_nom_complet AS facturation_destinataire,
                    adr_fact.adresse_ligne1 AS facturation_l1, adr_fact.adresse_ligne2 AS facturation_l2,
                    adr_fact.code_postal AS facturation_cp, adr_fact.ville AS facturation_ville, adr_fact.pays AS facturation_pays
                FROM Commandes c
                LEFT JOIN Adresses adr_liv ON c.id_adresse_livraison = adr_liv.id_adresse
                LEFT JOIN Adresses adr_fact ON c.id_adresse_facturation = adr_fact.id_adresse
                WHERE c.id_utilisateur = :id_utilisateur
                ORDER BY c.date_commande DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Construct full address strings
        foreach($orders as &$order) { // Pass by reference to modify
            $order['livraison_adresse_complete'] =
                ($order['livraison_destinataire'] ? sanitize_html_output($order['livraison_destinataire']) . "<br>" : "") .
                sanitize_html_output($order['livraison_l1']) . "<br>" .
                ($order['livraison_l2'] ? sanitize_html_output($order['livraison_l2']) . "<br>" : "") .
                sanitize_html_output($order['livraison_cp'] . " " . $order['livraison_ville']) . "<br>" .
                sanitize_html_output($order['livraison_pays']);

            $order['facturation_adresse_complete'] =
                ($order['facturation_destinataire'] ? sanitize_html_output($order['facturation_destinataire']) . "<br>" : "") .
                sanitize_html_output($order['facturation_l1']) . "<br>" .
                ($order['facturation_l2'] ? sanitize_html_output($order['facturation_l2']) . "<br>" : "") .
                sanitize_html_output($order['facturation_cp'] . " " . $order['facturation_ville']) . "<br>" .
                sanitize_html_output($order['facturation_pays']);
        }
        unset($order); // Unset reference
        return $orders;

    } catch (PDOException $e) {
        error_log("Erreur getUserOrdersSummary pour utilisateur ID $id_utilisateur: " . $e->getMessage());
        return [];
    }
}

function getOrderLineItems(PDO $pdo, int $id_commande): array {
    try {
        $sql = "SELECT lc.nom_produit_commande, lc.taille_produit_commande, lc.quantite, lc.prix_unitaire_ht_commande, lc.taux_tva_applique, p.image
                FROM Lignes_Commande lc
                LEFT JOIN Pneus p ON lc.id_pneu = p.id
                WHERE lc.id_commande = :id_commande";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_commande', $id_commande, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) { // Pass by reference
            $prix_ht = (float)$item['prix_unitaire_ht_commande'];
            $tva_rate = (float)$item['taux_tva_applique'] / 100;
            $item['prix_unitaire_ttc_calc'] = $prix_ht * (1 + $tva_rate);
            $item['total_ligne_ttc_calc'] = $item['prix_unitaire_ttc_calc'] * $item['quantite'];
        }
        unset($item); // Unset reference
        return $items;

    } catch (PDOException $e) {
        error_log("Erreur getOrderLineItems pour commande ID $id_commande: " . $e->getMessage());
        return [];
    }
}

?>
