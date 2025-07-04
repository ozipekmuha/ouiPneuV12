-- Script de création de la base de données pour Ouipneu.fr
-- Version: 1.2 (Correction contrainte CHECK sur Paniers)
-- Date: 2024-07-28

-- Supprime les tables si elles existent déjà (pour un environnement de développement/test)
-- ATTENTION: Ceci effacera toutes les données existantes dans ces tables.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS Commande_Promotions;
DROP TABLE IF EXISTS Promotions;
DROP TABLE IF EXISTS Panier_Produits;
DROP TABLE IF EXISTS Paniers;
DROP TABLE IF EXISTS Lignes_Commande;
DROP TABLE IF EXISTS Commandes;
DROP TABLE IF EXISTS Adresses;
DROP TABLE IF EXISTS Utilisateurs;
DROP TABLE IF EXISTS Pneus;
SET FOREIGN_KEY_CHECKS = 1;

-- Table des Pneus (Produits) - Structure Simplifiée
CREATE TABLE Pneus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    taille VARCHAR(255) COMMENT 'Ex: "215/55R16 97V"',
    saison VARCHAR(50) COMMENT 'Ex: "Été"',
    image VARCHAR(512) COMMENT 'URL de l''image',
    decibels VARCHAR(50) COMMENT 'Ex: "70 db"',
    adherenceRouillee VARCHAR(10) COMMENT 'Ex: "C" (pour classe d''adhérence sol mouillé)',
    specifications VARCHAR(255) COMMENT 'Ex: "XL", ou autres specs combinées',
    prix VARCHAR(50) COMMENT 'Ex: "48,49 €" - Stocké en texte',
    lienProduit VARCHAR(512),
    descriptionTitle VARCHAR(255) NULL,
    description TEXT NULL,
    descriptionComplete TEXT NULL,
    -- Champs de gestion ajoutés
    stock_disponible INT DEFAULT 0,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    est_actif BOOLEAN DEFAULT TRUE,

    INDEX idx_nom (nom),
    INDEX idx_saison (saison),
    INDEX idx_est_actif (est_actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des Utilisateurs
CREATE TABLE Utilisateurs (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL,
    est_admin BOOLEAN DEFAULT FALSE,
    token_reinitialisation_mdp VARCHAR(255) NULL UNIQUE,
    date_expiration_token_reinitialisation TIMESTAMP NULL,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des Adresses
CREATE TABLE Adresses (
    id_adresse INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    type_adresse VARCHAR(50) COMMENT 'Livraison, Facturation, Domicile, Travail, etc.',
    destinataire_nom_complet VARCHAR(200),
    adresse_ligne1 VARCHAR(255) NOT NULL,
    adresse_ligne2 VARCHAR(255),
    code_postal VARCHAR(10) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    pays VARCHAR(100) NOT NULL DEFAULT 'France',
    telephone_contact VARCHAR(20),
    est_principale_livraison BOOLEAN DEFAULT FALSE,
    est_principale_facturation BOOLEAN DEFAULT FALSE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateurs(id_utilisateur) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_utilisateur_type (id_utilisateur, type_adresse)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des Commandes
CREATE TABLE Commandes (
    id_commande INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    id_adresse_livraison INT NOT NULL,
    id_adresse_facturation INT NOT NULL,
    date_commande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut_commande VARCHAR(50) NOT NULL DEFAULT 'En attente de paiement' COMMENT 'En attente de paiement, Payée, En préparation, Expédiée, Livrée, Annulée, Remboursée',
    montant_sous_total DECIMAL(10, 2) NOT NULL COMMENT 'Prix total des articles HT avant réductions/frais',
    montant_livraison DECIMAL(10, 2) DEFAULT 0.00,
    montant_reduction DECIMAL(10, 2) DEFAULT 0.00,
    montant_total_ht DECIMAL(10, 2) NOT NULL,
    montant_tva DECIMAL(10, 2) NOT NULL,
    montant_total_ttc DECIMAL(10, 2) NOT NULL,
    methode_paiement VARCHAR(50),
    id_transaction_paiement VARCHAR(255) UNIQUE,
    notes_client TEXT,
    date_expedition TIMESTAMP NULL,
    numero_suivi_colis VARCHAR(100),
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateurs(id_utilisateur) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_adresse_livraison) REFERENCES Adresses(id_adresse) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_adresse_facturation) REFERENCES Adresses(id_adresse) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_statut_commande (statut_commande),
    INDEX idx_date_commande (date_commande)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des Lignes de Commande (détail des produits par commande)
CREATE TABLE Lignes_Commande (
    id_ligne_commande INT AUTO_INCREMENT PRIMARY KEY,
    id_commande INT NOT NULL,
    id_pneu INT NOT NULL,
    quantite INT NOT NULL, -- CHECK (quantite > 0) -- Check constraint simple, peut être gardée ou gérée en applicatif
    prix_unitaire_ht_commande DECIMAL(10, 2) NOT NULL COMMENT 'Prix HT du pneu au moment de la commande',
    taux_tva_applique DECIMAL(4,2) NOT NULL COMMENT 'Ex: 20.00 pour 20%',
    nom_produit_commande VARCHAR(255),
    taille_produit_commande VARCHAR(255),

    FOREIGN KEY (id_commande) REFERENCES Commandes(id_commande) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_pneu) REFERENCES Pneus(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des Paniers (Corrigée)
CREATE TABLE Paniers (
    id_panier INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT UNIQUE COMMENT 'Un utilisateur connecté a un seul panier actif. NULL si panier anonyme.',
    id_session_anonyme VARCHAR(255) UNIQUE COMMENT 'Pour les paniers des utilisateurs non connectés',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_derniere_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateurs(id_utilisateur) ON DELETE CASCADE ON UPDATE CASCADE
    -- CONSTRAINT chk_panier_owner CHECK (id_utilisateur IS NOT NULL OR id_session_anonyme IS NOT NULL) -- Supprimée
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des Produits dans un Panier (Panier_Produits)
CREATE TABLE Panier_Produits (
    id_panier_produit INT AUTO_INCREMENT PRIMARY KEY,
    id_panier INT NOT NULL,
    id_pneu INT NOT NULL,
    quantite INT NOT NULL DEFAULT 1, -- CHECK (quantite > 0) -- Check constraint simple, peut être gardée ou gérée en applicatif
    prix_unitaire_au_moment_ajout DECIMAL(10,2) COMMENT 'Optionnel: stocker le prix au moment de l''ajout',
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_panier) REFERENCES Paniers(id_panier) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_pneu) REFERENCES Pneus(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unq_panier_pneu (id_panier, id_pneu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table pour les promotions/coupons
CREATE TABLE Promotions (
    id_promotion INT AUTO_INCREMENT PRIMARY KEY,
    code_promo VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    type_reduction ENUM('pourcentage', 'montant_fixe') NOT NULL,
    valeur_reduction DECIMAL(10,2) NOT NULL,
    date_debut TIMESTAMP NOT NULL,
    date_fin TIMESTAMP NOT NULL,
    montant_minimum_commande DECIMAL(10,2) NULL,
    utilisations_max INT NULL,
    utilisations_actuelles INT DEFAULT 0,
    est_actif BOOLEAN DEFAULT TRUE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table de liaison pour appliquer les promotions aux commandes
CREATE TABLE Commande_Promotions (
    id_commande INT NOT NULL,
    id_promotion INT NOT NULL,
    montant_economise DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_commande, id_promotion),
    FOREIGN KEY (id_commande) REFERENCES Commandes(id_commande) ON DELETE CASCADE,
    FOREIGN KEY (id_promotion) REFERENCES Promotions(id_promotion) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Exemples d'insertion de données

INSERT INTO Pneus (
    nom, taille, saison, image, decibels, adherenceRouillee, specifications, prix, lienProduit,
    descriptionTitle, description, descriptionComplete, stock_disponible, est_actif
) VALUES (
    'Aptany RP203',
    '215/55R16 97V',
    'Été',
    'https://cdn.tiresleader.com/static/img/rw/tyre_small_cp/aptany-rp203-9965965.jpg',
    '70 db',
    'C',
    'XL',
    '48,49 €',
    'https://www.centralepneus.fr/pneu-auto/aptany/rp203/215-55-r16-97v-824722?adref=%2Fpneu-auto-215-55-16%2F',
    NULL,
    'Le pneu Aptany RP203 215/55 R16 97V XL dispose de 4 grandes rainures longitudinales permettant une excellente capacité d''évacuation de l''eau. Le composé de sa gomme innovante, et son profil asymétrique sont spécialement conçus pour accrocher la route et assurer un meilleur freinage sur sols secs ou humides. Matériaux de haute technologie. Ses performances apportent sécurité et plaisir à la conduite du véhicule.',
    'Le pneu Aptany RP203 215/55 R16 97V XL dispose de 4 grandes rainures longitudinales permettant une excellente capacité d''évacuation de l''eau. Le composé de sa gomme innovante, et son profil asymétrique sont spécialement conçus pour accrocher la route et assurer un meilleur freinage sur sols secs ou humides. Matériaux de haute technologie. Ses performances apportent sécurité et plaisir à la conduite du véhicule.',
    50, TRUE
);

INSERT INTO Pneus (
    nom, taille, saison, image, decibels, adherenceRouillee, specifications, prix, lienProduit,
    description, stock_disponible, est_actif
) VALUES (
    'Michelin Primacy 4', '205/55R16 91V', 'Été', 'https://placehold.co/400x300/121212/ffdd03?text=Michelin+Primacy',
    '69 db', 'A', NULL, '95,00 €', 'produit.php?id=michelin-primacy-4',
    'Le pneu Michelin Primacy 4 offre une excellente longévité et de très bonnes performances de freinage sur sol mouillé.',
    120, TRUE
),
(
    'Continental EcoContact 6', '195/65R15 91H', 'Été', 'https://placehold.co/400x300/121212/ffdd03?text=Continental+Eco',
    '71 db', 'B', 'Runflat', '85,00 €', 'produit.php?id=continental-ecocontact-6',
    'Le Continental EcoContact 6 est optimisé pour une faible résistance au roulement et une grande efficacité énergétique.',
    15, TRUE
),
(
    'Goodyear Vector 4Seasons Gen-3', '225/45R17 94W', 'Toutes Saisons', 'https://placehold.co/400x300/121212/ffdd03?text=Goodyear+4S',
    '70 db', 'B', 'XL', '110,00 €', 'produit.php?id=goodyear-vector-4s',
    'Le Goodyear Vector 4Seasons Gen-3 offre d''excellentes performances toute l''année.',
    80, TRUE
),
(
    'Pirelli Winter Sottozero 3', '235/40R18 95V', 'Hiver', 'https://placehold.co/400x300/121212/ffdd03?text=Pirelli+Winter',
    '72 db', 'B', 'XL, Runflat', '150,00 €', 'produit.php?id=pirelli-winter-sottozero-3',
    'Le Pirelli Winter Sottozero 3 est conçu pour des performances optimales en conditions hivernales sévères.',
    0, TRUE
);

-- Fin du script
