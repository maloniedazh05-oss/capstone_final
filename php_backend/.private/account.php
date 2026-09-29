<?php 
require_once __DIR__ . "/../db.php";

class PrivateAccount {
    private static $instance = null;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new PrivateAccount();
        }
        return self::$instance;
    }

    public static function getUserAdmin() {
        return self::getInstance()->user_admin;
    }

    public static function getPasswordAdmin() {
        return self::getInstance()->password_admin;
    }

    public static function getAdminName() {
        return self::getInstance()->admin_name;
    }

    public static function setUserAdmin($user_admin) {
        self::getInstance()->user_admin = $user_admin;
    }

    public static function setPasswordAdmin($password_admin) {
        self::getInstance()->password_admin = $password_admin;
    }

    public static function setAdminName($admin_name) {
        self::getInstance()->admin_name = $admin_name;
    }

    private $user_admin = 'techne';
    private $password_admin = 'admin123';
    private $admin_name = 'vermabugbog';
}
?>