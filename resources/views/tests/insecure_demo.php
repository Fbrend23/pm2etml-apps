<?php

/**
 * insecure_demo.php
 *
 * FICHIER VOLONTAIREMENT VULNÉRABLE
 * - Ne jamais utiliser en production
 * - Uniquement pour tester des outils comme Snyk, CodeQL, etc.
 */

// ==== 1. Configuration avec secrets en dur (Sensitive Data Exposure / Hardcoded secrets) ====
// Mauvaise pratique : mot de passe et config dans le code source.
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = 'rootpassword'; // <- secret en dur
$dbName = 'test_db';

// Connexion non sécurisée (pas de gestion d’erreurs robuste, pas de SSL, etc.)
$conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$conn) {
    // Information trop détaillée renvoyée au client (Information Disclosure)
    die('Erreur de connexion DB: ' . mysqli_connect_error());
}

// ==== 2. Lecture d’un paramètre GET et SQL Injection (OWASP A03: Injection) ====
// Exemple d’URL : insecure_demo.php?action=view_user&id=1 OR 1=1
if (isset($_GET['action']) && $_GET['action'] === 'view_user') {
    // AUCUNE validation / filtration
    $id = $_GET['id']; // <- dangereux

    // Requête construite par concaténation de string (SQL Injection)
    $query = "SELECT id, username, email FROM users WHERE id = $id";
    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<p>User: ' . $row['username'] . ' (' . $row['email'] . ')</p>';
        }
    } else {
        echo 'Erreur requête SQL: ' . mysqli_error($conn);
    }
}

// ==== 3. XSS réfléchi (OWASP A03: Injection / XSS) ====
// Exemple d’URL : insecure_demo.php?action=hello&name=<script>alert("XSS")</script>
if (isset($_GET['action']) && $_GET['action'] === 'hello') {
    // Aucune échappement => XSS
    $name = $_GET['name'] ?? 'inconnu';
    echo '<h1>Bonjour ' . $name . ' !</h1>';
}

// ==== 4. Authentification avec mot de passe en clair (Broken Authentication) ====
if (isset($_POST['login'])) {
    // Mauvaise pratique : mot de passe codé en dur + aucun hash
    $adminUser = 'admin';
    $adminPass = 'admin123'; // <- mot de passe en clair

    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $adminUser && $pass === $adminPass) {
        // Pas de gestion de session correcte, juste un cookie non sécurisé
        setcookie('auth', '1', time() + 3600); // pas de HttpOnly, pas de Secure
        echo '<p>Connexion réussie (non sécurisée) !</p>';
    } else {
        echo '<p>Identifiants invalides.</p>';
    }
}

// ==== 5. Upload de fichier non sécurisé (OWASP A01: Broken Access Control / A05: Security Misconfiguration) ====
// Formulaire simple pour tester
if (isset($_GET['action']) && $_GET['action'] === 'upload_form') {
?>
    <form action="insecure_demo.php?action=upload_file" method="post" enctype="multipart/form-data">
        <input type="file" name="file"><br>
        <button type="submit">Uploader</button>
    </form>
<?php
}

// Traitement de l’upload : aucune vérification du type, taille, nom, etc.
if (isset($_GET['action']) && $_GET['action'] === 'upload_file') {
    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // DANGEREUX : on utilise directement le nom fourni par l’utilisateur
        $target = __DIR__ . '/uploads/' . $_FILES['file']['name'];

        // Pas de validation du chemin, du type MIME, de l’extension...
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads'); // pas de droits spécifiques
        }

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            echo '<p>Fichier uploadé (non sécurisé) : ' . $target . '</p>';
        } else {
            echo '<p>Échec de l’upload.</p>';
        }
    } else {
        echo '<p>Aucun fichier reçu.</p>';
    }
}

// ==== 6. Command Injection (OWASP A03: Injection) ====
// Exemple d’URL : insecure_demo.php?action=ping&host=127.0.0.1
// ou avec injection : insecure_demo.php?action=ping&host=127.0.0.1;dir
if (isset($_GET['action']) && $_GET['action'] === 'ping') {
    $host = $_GET['host'] ?? '127.0.0.1';

    // Aucune validation, aucun échappement => Command Injection
    $cmd = 'ping -c 1 ' . $host;
    echo '<pre>Commande exécutée : ' . $cmd . "\n\n";
    system($cmd); // DANGEREUX
    echo '</pre>';
}

// ==== 7. Utilisation de eval() sur un input utilisateur (Remote Code Execution) ====
// Exemple d’URL : insecure_demo.php?action=eval&code=phpinfo();
if (isset($_GET['action']) && $_GET['action'] === 'eval') {
    $code = $_GET['code'] ?? '';
    // DANGEREUX : exécution directe de code fourni par l’utilisateur
    eval($code);
}

// ==== 8. Absence de CSRF protection sur une action sensible ====
// Exemple : suppression d’un utilisateur par requête POST sans token CSRF
if (isset($_POST['delete_user_id'])) {
    $deleteId = $_POST['delete_user_id'];

    // Pas de vérification que l’utilisateur est authentifié
    // Pas de token CSRF
    $deleteQuery = "DELETE FROM users WHERE id = $deleteId"; // Injection possible en plus
    mysqli_query($conn, $deleteQuery);

    echo '<p>Utilisateur supprimé (sans CSRF protection) : ID ' . $deleteId . '</p>';
}

// ==== 9. Informations sensibles dans les messages d’erreur ====
// Exemple : affichage de la requête SQL directement dans la réponse
if (isset($_GET['action']) && $_GET['action'] === 'debug_query') {
    $table = $_GET['table'] ?? 'users';
    $debugQuery = "SELECT * FROM $table";
    $res = mysqli_query($conn, $debugQuery);

    if (!$res) {
        // On leak la requête + erreur SQL
        echo '<p>Erreur SQL : ' . mysqli_error($conn) . '</p>';
        echo '<p>Requête : ' . $debugQuery . '</p>';
    } else {
        echo '<p>Requête exécutée avec succès.</p>';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Insecure Demo</title>
</head>

<body>
    <h2>Insecure Demo – Ne pas utiliser en production</h2>

    <h3>Login non sécurisé</h3>
    <form method="post" action="insecure_demo.php">
        <label>Nom d'utilisateur : <input type="text" name="username"></label><br>
        <label>Mot de passe : <input type="password" name="password"></label><br>
        <button type="submit" name="login">Se connecter (insecure)</button>
    </form>

    <h3>Suppression d'utilisateur sans CSRF</h3>
    <form method="post" action="insecure_demo.php">
        <label>ID utilisateur à supprimer : <input type="text" name="delete_user_id"></label><br>
        <button type="submit">Supprimer (sans CSRF)</button>
    </form>

    <p>
        Liens de test rapides :
    <ul>
        <li><a href="insecure_demo.php?action=view_user&id=1 OR 1=1">SQL Injection – view_user</a></li>
        <li><a href="insecure_demo.php?action=hello&name=<script>alert('XSS')</script>">XSS – hello</a></li>
        <li><a href="insecure_demo.php?action=upload_form">Upload non sécurisé</a></li>
        <li><a href="insecure_demo.php?action=ping&host=127.0.0.1;dir">Command Injection – ping</a></li>
        <li><a href="insecure_demo.php?action=eval&code=phpinfo();">eval() – RCE</a></li>
        <li><a href="insecure_demo.php?action=debug_query&table=users;DROP TABLE users;--">Debug query (Injection + leak)</a></li>
    </ul>
    </p>
</body>

</html>