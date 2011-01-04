<?php
// Table Prefix
$wordpress_table_prefix = 'wp_';
$app_same_db=0;
// ** MySQL Einstellungen ** //
$app_db='database';    // Ersetze putyourdbnamehere mit dem Namen der Datenbank, die du benutzt.
$app_login='username';     // Ersetze usernamehere mit deinem MySQL-Datenbank-Benutzernamen.
$app_pass='password'; // Ersetze yourpasswordhere mit deinem MySQL-Passwort.
$app_host='localhost';    // In 99% der Fälle musst du hier nichts ändern. Falls doch ersetze localhost mit der MySQL-Serveradresse.
define('DB_CHARSET', 'utf8');	// Der Datenbankzeichensatz sollte nicht geändert werden
define('DB_COLLATE', '');
?>