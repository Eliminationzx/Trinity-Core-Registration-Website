<?php
// Include the Medoo library
require_once 'Medoo.php';

use Medoo\Medoo;

class Database
{
    private $database;

    public function __construct()
    {
        try {
            // Get the database configuration from environment variables
            $this->database = new Medoo([
                'type' => 'mysql',
                'host' => getenv('DB_HOST') ?: 'localhost',  // Default to 'localhost' if not set
                'database' => getenv('DB_NAME') ?: 'auth',  // Default to 'auth' if not set
                'username' => getenv('DB_USER') ?: 'root',  // Default to 'root' if not set
                'password' => getenv('DB_PASS') ?: 'ascent',  // Default to 'ascent' if not set
                'charset' => 'utf8'
            ]);
        } catch (Exception $e) {
            // Output error message
            echo 'Connection failed: ' . $e->getMessage();
        }
    }

    public function getConnection()
    {
        return $this->database;
    }
}
?>
