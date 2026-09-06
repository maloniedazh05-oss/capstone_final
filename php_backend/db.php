<?php 
$host = 'localhost';
$dbname = 'rural_urban';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
/* Potential
class dbconn {
    private $host = 'localhost';
    private $dbname = 'rural_urban';
    private $username = 'root';
    private $password = '';

    public function __construct() {
        $pdow = new PDO("mysql=$this->host;dbname=$this->dbname", $this->username, $this->password);
    }
}
then classes inventory, production, etc.    
*/
?>