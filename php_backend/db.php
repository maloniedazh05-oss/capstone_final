<?php
// Single clock for the whole system: PHP date() calls (3-day windows,
// "today" filters, chart ranges) must agree with MySQL TIMESTAMP reads,
// which follow the server SYSTEM clock (UTC+8). Without this, PHP can
// sit a day behind MySQL and same-day rows vanish from date filters.
date_default_timezone_set('Asia/Manila');
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