<?php
// Configuration de la base de données
$host = 'localhost';         // Ou l'IP de votre serveur de base de données si différent
$db_name = 'ouipneu.fr';     // Le nom de votre base de données
$username = 'root';          // Votre nom d'utilisateur pour la base de données
$password = 'root';          // Votre mot de passe pour la base de données
$charset = 'utf8mb4';        // Encodage des caractères

// Options PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Gérer les erreurs comme des exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Récupérer les résultats sous forme de tableau associatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Désactiver l'émulation des requêtes préparées pour une vraie préparation
];

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

try {
    // Création de l'instance PDO
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // En cas d'erreur de connexion, afficher un message et arrêter le script
    // En production, vous voudrez peut-être logger cette erreur et afficher un message plus générique à l'utilisateur.
    error_log("Erreur de connexion à la base de données : " . $e->getMessage());
    // Pour l'utilisateur final, un message simple :
    // die("Une erreur de connexion à la base de données est survenue. Veuillez réessayer plus tard.");
    // Pour le développement, il peut être utile d'afficher l'erreur :
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}

// À ce stade, la variable $pdo contient l'objet de connexion à la base de données
// et peut être utilisée dans d'autres scripts qui incluent ce fichier.
// Exemple: require_once 'includes/db_connect.php';
// $stmt = $pdo->query("SELECT * FROM Pneus");
// ...
?>
