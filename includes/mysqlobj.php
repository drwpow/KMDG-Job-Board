<?php
class mysqlobj extends PDO {
    
    #                #
    #    SETTINGS    #
    #                #

    protected $db_host = 'localhost';
    protected $db_un = 'username';
    protected $db_pw = 'password';
    protected $db_name = 'dbname';
    
    protected $pathtologs = 'mysql';
    protected $enablelogs = FALSE;
    protected $threshold = 0; // Minimum execution time, in seconds, a query must take in order to be logged (for debugging slow queries). Set to 0 to log all queries.

    #  END SETTINGS  #
    
    protected $pdo;
    public $table;
    
    function __construct() {
        $this->pdo = new PDO('mysql:host='.$this->db_host.';dbname='.$this->db_name, $this->db_un, $this->db_pw);
    }
    
    function table($input) { // Alternate declaration of $this->table just for ease of use
        $this->table = $input;
        $this->maketable();
    }
    
    function columns($table = '') {
        $table = (empty($table)) ? $this->table : $table;
        $query = "SHOW COLUMNS FROM ". $table;
        $start = microtime(true);
        $sth = $this->pdo->query($query);
        $xtime = microtime(true)-$start;
        $this->savelog($query, $xtime, $sth->rowCount(), $sth->errorCode(), $sth->errorInfo());
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }
    
    function delete($where, $limit = 1, $debug_mode = FALSE) {
        $where = (!empty($where)) ? ' WHERE '. trim($where) : '';
        $query = "DELETE FROM ". $this->table. $where. " LIMIT ". trim((int)$limit);
        $sth = $this->pdo->prepare($query);
        $start = microtime(true);
        return $sth->execute();
        $xtime = microtime(true)-$start;
        $this->savelog($query, $xtime, $sth->rowCount(), $sth->errorCode(), $sth->errorInfo());
        if($debug_mode) {
            echo $this->pdo->errorCode(). ': '. $this->pdo->errorInfo();
        }
    }
    
    function insert($values, $add_columns = FALSE, $debug_mode = FALSE) { // Returns last insert ID
        if($add_columns) {
            foreach(array_keys($values) as $column) {
                $this->pdo->query("ALTER TABLE ". $this->table. " ADD COLUMN ". $column. " VARCHAR(255) NOT NULL default '';");
            }
        }
        $model = (is_array($values[0])) ? $values[0] : $values; // If handling data set, use first result as model
        $fields = implode(', ', array_keys($model));
        $bindings = ':'.implode(', :', array_keys($model));
        $query = "INSERT INTO ". $this->table. " (". $fields. ") VALUES (". $bindings. ")";
        $sth = $this->pdo->prepare($query);
        if(is_array($values[0])) {
            foreach($values as $row) {
                $start = microtime(true);
                $sth->execute($row);
                $xtime = microtime(true)-$start;
                $this->savelog($query, $xtime, $sth->rowCount(), $sth->errorCode(), $sth->errorInfo());
            }
        } else {
            $start = microtime(true);
            $sth->execute($values);
            $xtime = microtime(true)-$start;
            $this->savelog($query, $xtime, $sth->rowCount(), $sth->errorCode(), $sth->errorInfo());
        }
        return $this->pdo->lastInsertID();
        if($debug_mode) {
            echo $sth->errorCode(). ': '. $sth->errorInfo(). '('.$sth->rowCount().' row(s) affected)';
        }
    }
    
    function update($values, $where = '', $debug_mode = FALSE) {
        $where = (!empty($where)) ? ' WHERE '. trim($where) : '';
        $valuepairs = array();
        foreach($values as $key => $value) {
            $valuepairs[] = $key. " = '{$value}'";
        }
        $valuepairs = implode(', ', $valuepairs);
        $query = "UPDATE ". $this->table. " SET ". $valuepairs. $where;
        $sth = $this->pdo->prepare($query);
        $start = microtime(true);
        return $sth->execute($values);
        $xtime = microtime(true)-$start;
        $this->savelog($query, $xtime, $sth->rowCount(), $sth->errorCode(), $sth->errorInfo());
        if($debug_mode) {
            echo $sth->errorCode(). ': '. $sth->errorInfo(). '('.$sth->rowCount().' row(s) affected)';
        }
    }
    
    function select($fields = '*', $where = '', $options = array(), $debug_mode = FALSE) {
        $results = array();
        $fields = (is_array($fields)) ? implode(', ', $fields) : $fields;
        $where = (!empty($where)) ? ' WHERE '. ltrim($where, ' where') : '';
		$limit = '';
		$orderby = '';
		$sort = '';
		if(isset($options['limit'])) $limit = ' LIMIT '. trim($options['limit']);
		if(isset($options['orderby'])) $orderby = ' ORDER BY '. trim($options['orderby']);
		if(isset($options['order'])) $sort = ' '. trim($options['order']);
		if(isset($options['sort'])) $sort = ' '. trim($options['sort']);
        $query = "SELECT ". $fields. " FROM ". $this->table. $where. $limit. $orderby. $sort;
        $start = microtime(true);
        $sth = $this->pdo->query($query);
        $xtime = microtime(true)-$start;
        $this->savelog($query, $xtime, $sth->rowCount(), $sth->errorCode(), $sth->errorInfo());
        return $sth->fetchAll(PDO::FETCH_ASSOC);
        if($debug_mode) {
            echo $sth->errorCode(). ': '. $sth->errorInfo(). '('.$sth->rowCount().' row(s) affected)';
        }
    }
    
    function query($query, $debug_mode = FALSE) {
        $start = microtime(true);
        $sth = $this->pdo->query($query);
        $xtime = microtime(true)-$start;
		$rows = $sth->rowCount();
		$rows = $rows ? $rows : 0;
        $this->savelog($query, $xtime, $rows, $sth->errorCode(), $sth->errorInfo());
        return $sth;
    }
    
    protected function maketable() {
        $this->pdo->query("CREATE TABLE ". $this->table. " IF NOT EXISTS (`ID` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`ID`)) COLLATE='utf8_general_ci' ENGINE=InnoDB");
    }
    
    protected function savelog($query, $xtime = 0, $affected = 0, $errorCode = '', $errorInfo = '') {
        if($this->enablelogs) {
            if($xtime !== 0 && $xtime >= $this->threshold) {
                switch(is_dir($_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR. $this->pathtologs)) {
                    case false :
                    mkdir($_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR. $this->pathtologs, 500);
                    break;
                }
                $datetime = explode(' ', date('Y-m-d H:i:s'));
                $filename = $_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR. $this->pathtologs. DIRECTORY_SEPARATOR. $datetime[0]. '.log';
                $errorstring = ($errorCode != 0) ? ' / ERROR '. $errorCode. ': '. $errorInfo : '';
                $handle = fopen($filename, 'a');
                fwrite($handle, $datetime[0]. ' '. $datetime[1]. ' '. $_SERVER['PHP_SELF']. ' '. $query. $errorstring. ' ('. $xtime. ' sec / '. (int)$affected. " row(s) affected) \r\n");
                fclose($handle);
            }
        }
    }
    
    function __destruct() {
        $this->pdo = NULL;
    }
}
?>