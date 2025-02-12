<?php
require_once 'database.php';
class Stats
{
    private $auth_connection;

    public function __construct()
    {
        $database = new Database();
        $this->auth_connection = $database->getConnection();
    }

    public function totalAccounts()
    {
        return $this->auth_connection->count('account');
    }

    public function totalOnline()
    {
        return $this->auth_connection->count('account', ['online' => 1]);
    }

    public function totalBan()
    {
        return $this->auth_connection->count('account_banned');
    }

    public function uptime()
    {
        // Get the latest starttime value
        $latest_starttime = $this->auth_connection->get('uptime', 'starttime', [
            "ORDER" => ["starttime" => "DESC"],
            "LIMIT" => 1
        ]);
    
        if (!$latest_starttime) {
            return "No uptime data available";
        }
    
        $current_time = time();
        $server_up = abs($current_time - $latest_starttime);
        $up_hrs = floor($server_up / 3600);
        $up_min = floor(($server_up % 3600) / 60);
        return $up_hrs . ' hours ' . $up_min . ' minutes';
    }
}

?>