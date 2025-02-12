<?php
require_once 'database.php';
session_start();

class BnetRegister
{
    private $email;
    private $password;
    private $auth_connection;

    public function __construct($email, $password)
    {
        $this->email = $email;
        $this->password = $password;

        $database = new Database();
        $this->auth_connection = $database->getConnection();
    }

	private function validate_email($email)
	{
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$_SESSION['error'] = "Invalid email format.";
			header("Location: ../index.php");
			exit();
		}

		// Check if the email exists using the `select` method
		$result = $this->auth_connection->select('battlenet_accounts', ['email'], ['email' => $email]);

		if (count($result) > 0) {
			$_SESSION['emailExist'] = "Email is already registered.";
			header("Location: ../index.php");
			exit();
		}

		return true;
	}
	
	function getRegistrationData($username, $password)
	{
		// generate a random salt
		$salt = random_bytes(32);

		// calculate verifier using this salt
		$verifier = calculateSRP6Verifier($username, $password, $salt);

		// done - this is what you put in the account table!
		if(get_config('server_core') == 5)
		{
			$salt = strtoupper(bin2hex($salt));             // From haukw
			$verifier = strtoupper(bin2hex($verifier));     // From haukw
		}
		
		return array($salt, $verifier);
	}

	function getRegistrationDataBnetV2($email, $password)
	{
		// generate a random salt
		$salt = random_bytes(32);

		// calculate verifier using this salt
		$verifier = $this->calculateSRP6VerifierBnetV2($email, $password, $salt);

		// done - this is what you put in the battlenet_accounts table!
		return array($salt, $verifier);
	}

    public function CalculateSRP6Verifier($username, $password, $salt)
    {
		$g = gmp_init(7);
		$N = gmp_init('894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7', 16);
		$x = gmp_import(
			sha1($salt . sha1(strtoupper($username . ':' . $password), TRUE), TRUE),
			1,
			GMP_LSW_FIRST
		);
		$v = gmp_powm($g, $x, $N);
		return ($verifier === str_pad(gmp_export($v, 1, GMP_LSW_FIRST), 32, chr(0), STR_PAD_RIGHT));
    }

	function calculateSRP6VerifierBnetV2($email, $password, $salt)
	{
		// algorithm constants
		$g = gmp_init(2);
		$N = gmp_init('AC6BDB41324A9A9BF166DE5E1389582FAF72B6651987EE07FC3192943DB56050A37329CBB4A099ED8193E0757767A13DD52312AB4B03310DCD7F48A9DA04FD50E8083969EDB767B0CF6095179A163AB3661A05FBD5FAAAE82918A9962F0B93B855F97993EC975EEAA80D740ADBF4FF747359D041D5C33EA71D281E446B14773BCA97B43A23FB801676BD207A436C6481F1D2B9078717461A5B9D32E688F87748544523B524B0D57D5EA77A2775D2ECFA032CFBDBF52FB3786160279004E57AE6AF874E7303CE53299CCC041C7BC308D82A5698F3A8D0C38271AE35F8E9DBFBB694B5C803D89F7AE435DE236D525F54759B65E372FCD68EF20FA7111F9E4AFF73', 16);

		$srpPassword = strtoupper(hash('sha256', strtoupper($email), false)) . ":" . $password;

		// calculate x
		$xBytes = hash_pbkdf2("sha512", $srpPassword, $salt, 15000, 64, true);
		$x = gmp_import($xBytes, 1, GMP_MSW_FIRST);
		if (ord($xBytes[0]) & 0x80)
		{
			$fix = gmp_init('100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000', 16);
			$x = gmp_sub($x, $fix);
		}
		$x = gmp_mod($x, gmp_sub($N, 1));

		// g^h2 mod N
		$verifier = gmp_powm($g, $x, $N);

		// convert back to a byte array (little-endian)
		$verifier = gmp_export($verifier, 1, GMP_LSW_FIRST);

		// pad to 256 bytes, remember that zeros go on the end in little-endian!
		$verifier = str_pad($verifier, 256, chr(0), STR_PAD_RIGHT);

		// done!
		return $verifier;
	}

	function verifySRP6BnetV2($email, $pass, $salt, $verifier)
	{
		return ($verifier === $this->calculateSRP6VerifierBnetV2($email, $pass, $salt));
	}

    private function register_battlenet_account()
    {
        try {
            // Start transaction
            $this->auth_connection->pdo->beginTransaction();
			
			list($salt, $verifier) = $this->getRegistrationDataBnetV2(strtoupper($this->email), $this->password);
			
            // Insert battlenet account
            $this->auth_connection->insert('battlenet_accounts', [
                'email' => $this->email,
				'srp_version' => 2,
				'salt' => $salt,
                'verifier' => $verifier,
                'last_ip' => $_SERVER['REMOTE_ADDR'],
                'joindate' => date("Y-m-d H:i:s")
            ]);

            // Get last inserted ID for battlenet account
            $bnet_account_id = $this->auth_connection->id();
            if (!$bnet_account_id) {
                throw new Exception("Error creating Battle.net account.");
            }

            // Generate username (e.g., 123#1)
            $username = $bnet_account_id . '#1';

            // Generate salt and verifier using username and password
            $salt = bin2hex(random_bytes(32)); // Store salt as HEX
            $verifier = $this->calculateSRP6VerifierBnetV2($username, $this->password, $salt);

            // Insert game account
            $this->auth_connection->insert('account', [
                'username' => $username,
                'email' => $this->email,
                'salt' => $salt, // Store salt as HEX
                'verifier' => $verifier,
                'expansion' => 10,
                'battlenet_account' => $bnet_account_id,
                'battlenet_index' => 1
            ]);

            // Commit transaction
            $this->auth_connection->pdo->commit();

            $_SESSION['success'] = "Battle.net account successfully created.";
            header("Location: ../index.php");
            exit();
        } catch (Exception $e) {
            // Rollback on error
            if ($this->auth_connection->pdo->inTransaction()) {
                $this->auth_connection->pdo->rollBack();
            }

            $_SESSION['error'] = "Registration failed: " . $e->getMessage();
            header("Location: ../index.php");
            exit();
        }
    }

    public function registerGameAccount($username, $email, $pass)
    {
        $salt = bin2hex(random_bytes(32)); // Store salt as HEX
        $verifier = $this->calculateSRP6VerifierBnetV2($email, $pass, $salt);

        $this->auth_connection->insert('account', [
            'username' => $username,
            'salt' => $salt,
            'verifier' => $verifier,
            'email' => $email,
            'reg_mail' => $email
        ]);
    }

    public function process_registration()
    {
        if ($this->validate_email($this->email)) {
            $this->register_battlenet_account();
        }
    }
}

// Process the POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $register = new BnetRegister($email, $password);
    $register->process_registration();
}
?>
