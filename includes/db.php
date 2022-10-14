<?php
class DB {
    
    /**
     * database connection object
     * @var PDO
     */
    protected $con;

    protected $responseBody;

    public function __construct(PDO $con) {
        $this->con = $con;
        $this->con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
        
    public function lastInsertId() {
        $lastInsertId = $this->con->lastInsertId();
        return $lastInsertId;
    }

    public function beginTransaction() {
        $this->con->beginTransaction();
    }

    public function commit() {
        $this->con->commit(); 
    }
    
    public function rollBack() {
        $this->con->rollBack(); 
    }
    
    public function getAllRecords($tableName, $fields='*', $cond='', $orderBy='', $limit='')	{
        try {
            $stmt = $this->con->prepare("SELECT $fields FROM $tableName WHERE 1 ".$cond." ".$orderBy." ".$limit);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;

        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function getSingleRecord($tableName, $fields='*', $cond='', $orderBy='', $limit='')	{
        try {
            $stmt = $this->con->prepare("SELECT $fields FROM $tableName WHERE 1 ".$cond." ".$orderBy." ".$limit);
            $stmt->execute();
            $rows = $stmt->fetch(PDO::FETCH_ASSOC);
            return $rows;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function getLogin($tableName, $fields='*', $cond='') {
        try {
            $stmt = $this->con->prepare("SELECT $fields FROM $tableName WHERE ".$cond);
            $stmt->execute();
            $rows = $stmt->fetch(PDO::FETCH_ASSOC);
            return $rows;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
	}

    public function countTable($tableName, $field, $cond='') {
        try {
            $stmt = $this->con->prepare("SELECT count($field) as total FROM $tableName WHERE 1 ".$cond);
            $stmt->execute();
            $counter = 0;
            $counter = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if(count($counter) != 1) {
                return $counter;
            }
		    return $counter;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function updateTable($tableName, array $set, array $where) {
        $arrSet = array_map(
            function($value) {
                 return $value . '=:' . $value;
            },
            array_keys($set)
         );
         
         $stmt = $this->con->prepare(
             "UPDATE $tableName SET ". implode(',', $arrSet).' WHERE '. key($where). '=:'. key($where) . 'Field'
          );
 
         foreach ($set as $field => $value) {
             $stmt->bindValue(':'.$field, $value);
         }
         $stmt->bindValue(':'.key($where) . 'Field', current($where));
         try {
             $stmt->execute();
 
             return $stmt->rowCount();
         } catch (PDOException $e) {
            return $e->getMessage();
         }
    }

    public function updateMultiColumn($tableName, $indexColum, $valueColumn, array $arrSet) {
        $rowCount = 0;
        foreach ($arrSet as $index => $value) {
            
            $stmt = $this->con->prepare(
                "UPDATE $tableName SET ". $valueColumn .'=:'. $index.' WHERE '. $indexColum. '=:'. $index . 'Field'
            );
            $stmt->bindValue(':'.$index, $value);
            $stmt->bindValue(':'.$index . 'Field', $index);
            
            try {
                $stmt->execute();
                $rowCount++;
            } catch (PDOException $e) {
                return $e->getMessage();
            }
        }
        
        return $rowCount;

    }
    
    public function countRecords($tableName, $cond) {
        try {
            $stmt = $this->con->prepare("SELECT * FROM $tableName WHERE 1 ".$cond);
            $stmt->execute();
            return $stmt->rowCount() ? $stmt->rowCount() : 0;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function deleteRecord($tableName,  array $cond) {
        $stmt = $this->con->prepare("DELETE FROM $tableName WHERE ".key($cond) . ' = ?');
        try {
            return $stmt->execute(array(current($cond)));
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

	public function runQuery($query) {
        try {
            $stmt = $this->con->prepare($query);
            if($stmt->execute()) { 
                $this->responseBody = true; 
            } else { 
                $this->responseBody = false; 
            }
            return $this->responseBody;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }
    
    public function getAllRecordsBySyntax($query) {
        try {
            $stmt = $this->con->prepare($query); $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }
    
    public function getSingleRecordBySyntax($query) {
        try {
            $stmt = $this->con->prepare($query); $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function insertRecord($tableName, array $data) {
        
		$stmt = $this->con->prepare("INSERT IGNORE INTO $tableName (".implode(',', array_keys($data)).")
            VALUES (".implode(',', array_fill(0, count($data), '?')).")"
        );
        try {
            $stmt->execute(array_values($data));

            return $stmt->rowCount();
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function multiInsert($tableName, array $data) {
        
        //Will contain SQL snippets.
        $rowsSQL = array();

        //Will contain the values that we need to bind.
        $toBind = array();
        
        //Get a list of column names to use in the SQL statement.
        $columnNames = array_keys($data[0]);

        //Loop through our $data array.
        foreach($data as $arrayIndex => $row){
            $params = array();
            foreach($row as $columnName => $columnValue){
                $param = ":" . $columnName . $arrayIndex;
                $params[] = $param;
                $toBind[$param] = $columnValue; 
            }
            $rowsSQL[] = "(" . implode(", ", $params) . ")";
        }

        //Construct our SQL statement
        $sql = "INSERT INTO `$tableName` (" . implode(", ", $columnNames) . ") VALUES " . implode(", ", $rowsSQL);

        //Prepare our PDO statement.
        $stmt = $this->con->prepare($sql);

        //Bind our values.
        foreach($toBind as $param => $val){
            $stmt->bindValue($param, $val);
        }
        
        //Execute our statement (i.e. insert the data).
        try {
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function objectToArray($object) {
	    return (array) $object;
    }
    
    public function arrayToObject($array) {
        return (object) $array;
    } 

    public function dateFormatter($date) {
        $timestamp = strtotime($date);
        $niceFormat = date('D j, M Y h:i a', $timestamp);

        return $niceFormat;
    }

    public function sanitizeHTML_String($content) {

        //Allowed tags...
        $_basicHTMLTags = '<b><blockquote><br><code><dd><dl>'
            . '<em><hr><h1><h2><h3><h4><h5><h6><i><img><label><li><p><span>'
            . '<strong><sub><sup><ul>';

        return strip_tags($content, $_basicHTMLTags);
    }

    public function paginate($sql) {

        $result = $this->con->prepare($sql); 
        $result->execute();
        $count = $result->rowCount();
        $output = '';

        if(count($_GET) > 0) {

            if(isset($_GET['search_val'])) {
                $href = str_replace("?search_val=".$_GET['search_val'], "", $_SERVER['REQUEST_URI'])."?";
            }

            if(isset($_GET['currentpage'])) {
                $href = str_replace("?currentpage=".$_GET['currentpage'], "", $_SERVER['REQUEST_URI'])."?";
            }

            if(isset($_SESSION['original_search_value']) AND $_SESSION['original_search_value'] == $_REQUEST['search_val']) {
                $href = $href."?search_val=".base64_encode($_REQUEST['search_val'])."&";
            } else {
                if(isset($_REQUEST['search_val'])) {
                    $search_val = $_REQUEST['search_val']; 
                    $href = $href."?search_val=".$search_val."&";
                } else { 
                    $search_val = ''; 
                    // $href = str_replace("?currentpage=".$_GET['currentpage'], "", $_SERVER['REQUEST_URI'])."&";
                    $href = preg_replace('/(\?|&)currentpage=\d+/', '', $_SERVER['REQUEST_URI']).'?';
                }                
            }

        } else {
            $href = $_SERVER['REQUEST_URI'].'?';
        }

        if(isset($_REQUEST['viewCategory']) OR isset($_REQUEST['viewPhone']) OR isset($_REQUEST['viewUser'])) {
            $href = str_replace("?search_val=", "", $href);
        }

        if(isset($_SESSION['currPage']) AND $_SESSION['currPage'] == true) { //Current page for search while GET Method is active...
            $page = 1;
            unset($_SESSION['currPage']);
        } else {
            if(!isset($_REQUEST["currentpage"])){
                $_REQUEST["currentpage"] = 1;
                $page = $_REQUEST["currentpage"];
            } else {
                $page = $_REQUEST["currentpage"];
            }
        }

        if($this->PERPAGE_LIMIT != 0)
            $pages  = ceil($count/$this->PERPAGE_LIMIT);
            
        //if pages exists after loop's lower limit
        if($pages>1) {
            
            if(($_REQUEST["currentpage"]-3)>0) {
                $output = $output . '<a href="' . $href . 'currentpage=1" class="btn btn-primary btn-sm">1</a>';
            }
            if(($_REQUEST["currentpage"]-3)>1) {
                $output = $output . ' ... ';
            }
            
            // Page: 1 - 20 out of 364
            //Loop for provides links for 2 pages before and after current page
            for($i=($_REQUEST["currentpage"]-2); $i<=($_REQUEST["currentpage"]+2); $i++)	{
                if($i<1) continue;
                if($i>$pages) break;
                if($_REQUEST["currentpage"] == $i)
                    $output = $output . '<span id='.$i.' class="btn btn-primary btn-sm">'.$i.'</span>';
                else
                    $output = $output . '<a href="' . $href . "currentpage=".$i . '" class="btn btn-primary btn-sm" style="margin-left: 5px; margin-right: 5px">'.$i.'</a>';
            }

            //if pages exists after loop's upper limit
            if(($pages-($_REQUEST["currentpage"]+2))>1) {
                $output = $output . ' ... ';
            }
            if(($pages-($_REQUEST["currentpage"]+2))>0) {
                if($_REQUEST["currentpage"] == $pages)
                    $output = $output . '<span id=' . ($pages) .' class="btn btn-primary btn-sm">' . ($pages) .'</span>';
                else
                    $output =  $output . '<a href="' . $href .  "currentpage=" .($pages) .'" class="btn btn-primary btn-sm">' . ($pages) .'</a>';
            }
            
        }
        
        echo "Page:   $page  - ";  if($this->PERPAGE_LIMIT * $page > $count) { echo $count; } else { echo $this->PERPAGE_LIMIT * $page; } ?> <?php echo ' out of '. $count  . ' ' . $output;
        
    }
    
    // Numeric reference only
    public function orderID($length) {
        $vowels = '67892'; 
        $reference = '123456789987654321'; 
        $idnumber = ''; 
        $alt = time() % 2; 
        for ($i = 0; $i < $length; $i++) { 
            if ($alt == 1) { 
                $idnumber.= $reference[(rand() % strlen($reference)) ]; 
                $alt = 0; 
            } else { 
                $idnumber.= $vowels[(rand() % 5) ]; 
                $alt = 1; 
            } 
        } 
         
        return $idnumber; 
    }

    public function randID($length) { 
        $vowels = 'aeiu'; 
        $consonants = '123456789bcdfghjklmnpqrstvwxyz'; 
        $idnumber = ''; 
        $alt = time() % 2; 
        for ($i = 0; $i < $length; $i++) { 
            if ($alt == 1) { 
                $idnumber.= $consonants[(rand() % strlen($consonants)) ]; 
                $alt = 0; 
            } else { 
                $idnumber.= $vowels[(rand() % strlen($vowels)) ]; 
                $alt = 1; 
            } 
        } 
         
        return $idnumber; 
    }
    
}
?>