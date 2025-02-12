<?php
require_once 'database.php';
session_start();

class Register
{
    private $username;
    private $password;
    private $email;
    private $auth_connection;

    public function __construct($username, $password, $email)
    {
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;

        $database = new Database();
        $this->auth_connection = $database->getConnection();
    }

    private function validate_email($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($this->auth_connection->has('account', ['email' => $email])) {
            $_SESSION['emailExist'] = "The email address is already in use";
            header("Location: ../index.php");
            exit();
        }

        return true;
    }

    private function validate_password($password)
    {
        if (strlen($password) < 6 ||
            !preg_match("#[0-9]+#", $password) ||
            !preg_match("#[a-z]+#", $password) ||
            !preg_match("#[A-Z]+#", $password)
        ) {
            $_SESSION['error'] = "Password must be at least 6 characters long and contain at least one number, one uppercase letter, and one lowercase letter";
            header("Location: ../index.php");
            exit();
        }

        // Password confirmation check can be added here or handled on the frontend
        return true;
    }

    private function validate_username($username)
    {
        if (strlen($username) < 4 || strlen($username) > 16) {
            $_SESSION['userExist'] = "Username must be between 4 and 16 characters long";
            header("Location: ../index.php");
            exit();
        }

        if ($this->auth_connection->has('account', ['username' => $username])) {
            $_SESSION['userExist'] = "The username is already in use";
            header("Location: ../index.php");
            exit();
        }

        return true;
    }

    public function calculate_verifier($username, $password, $salt)
    {
        $g = gmp_init(7);
        $N = gmp_init('894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7', 16);
        $h1 = sha1(strtoupper($username . ':' . $password), TRUE);
        $h2 = sha1($salt . $h1, TRUE);
        $h2 = gmp_import($h2, 1, GMP_LSW_FIRST);
        $verifier = gmp_powm($g, $h2, $N);
        $verifier = gmp_export($verifier, 1, GMP_LSW_FIRST);
        $verifier = str_pad($verifier, 32, chr(0), STR_PAD_RIGHT);
        return $verifier;
    }

    private function register($username, $email, $password)
    {
        $salt = random_bytes(32);
        $verifier = $this->calculate_verifier($username, $password, $salt);
        $expansion = 2;

        $this->auth_connection->insert('account', [
            'username' => $username,
            'salt' => $salt,
            'verifier' => $verifier,
            'email' => $email,
            'joindate' => time(),
            'last_ip' => $_SERVER['REMOTE_ADDR'],
            'expansion' => $expansion
        ]);

        $_SESSION['success'] = "Account Successfully Created";
        header("Location: ../index.php");
        exit();
    }

    public function process_registration()
    {
        if ($this->validate_email($this->email) && $this->validate_username($this->username) && $this->validate_password($this->password)) {
            $this->register($this->username, $this->email, $this->password);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];

    $register = new Register($username, $password, $email);
    $register->process_registration();
}
?>
