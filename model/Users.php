<?php
require "VirtualAccount.php";
class Users extends VirtualAccount {
    public function __construct($con) {
        $this->con = $con;
        $this->usertbl = "tbl__fcmb_users";
        $this->usertbl2 = "tblusers";
        $this->fcmbvirttbl = "tbl__fcmb_users_virtual_account";
        $this->virtualaccount = new VirtualAccount($this->con);
    }
    
    private function userExists($columnNeeded, $value) {
        $result = $this->con->getSingleRecord($this->usertbl, "*", " AND $columnNeeded='$value'");
        $this->responseBody = ($result == NULL) ? false: $result;
        return $this->responseBody;
    }
    
    public function createAccount($username, $firstname, $lastname, $email, $mobileno) {
        $_SESSION["titleMessage"] = "Error";
        $mobileno = $this->reformNumber($mobileno);
        
        if($this->userExists("email", $email) != NULL) {
            $_SESSION["errorMessage"] = "Email address ($email) already associated with another member";
            $this->responseBody = false;
        }
        else if($this->userExists("osmusernameosm", $username) != NULL) {
            $_SESSION["errorMessage"] = "Username ($username) already associated with another member";
            $this->responseBody = false;
        } 
        else if($this->userExists("mobile", $mobileno) != NULL) {
            $_SESSION["errorMessage"] = "Mobile number ($mobileno) already associated with another member";
            $this->responseBody = false;
        } 
        else {
            $systemid = mt_rand(11011, 99099).mt_rand(11011, 99099);
            
            $count_systemID = $this->con->countTable($this->usertbl, "*", " AND systemid='$systemid'");
            if($count_systemID > 0) {
                $systemid = mt_rand(11011, 99099).mt_rand(11011, 99099);
            }
            
            $createMember = $this->con->insertRecord($this->usertbl, [
                "osmusernameosm" => $username,
                "email" => $email,
                "fname" => $firstname,
                "lname" => $lastname,
                "mobile" => $mobileno,
                "systemid" => $systemid
            ]);
            $createMember = true;
            if($createMember) {
                $userId = $this->con->lastInsertId();
                
                //User session...
                $_SESSION['user_id'] = $systemid;
                
                $generate_virtual = $this->virtualaccount->generatesingle_virtual($firstname ." ".$lastname);
                $decodevirtual = json_decode($generate_virtual, true);
                if($decodevirtual["status"] == "success") {
                    
                    $account_number = $decodevirtual["account_number"];
                    $request_id = $decodevirtual["request_id"];
                    
                    $this->con->insertRecord($this->fcmbvirttbl, [
                        "user_id" => $userId,
                        "user_system_id" => $systemid,
                        "vritual_reference" => $request_id,
                        "vritual_account" => $account_number,
                        "date_created" => date("Y-m-d H:i:s")
                    ]);
                    $_SESSION["fcmb_created"] = true;
                }
                $_SESSION["titleMessage"] = "Success";
                $_SESSION["successMessage"] = "Your OSB Wallet has been created successfully. Kindly login to enjoy our amazing offers";
                $this->responseBody = $generate_virtual;
            } else {
                $_SESSION["errorMessage"] = "Registration failed";
                $this->responseBody = false;
            }
        }
        
        return $this->responseBody;
    }
    
    private function reformNumber($number) {
        $firstdigit = substr($number, 0, 1);
        if($firstdigit == 0) {
            $number = '234'.substr($number, 1, 10);
        }
        return $number;
    }
    
    public function getUser($systemId) {
        $result = $this->con->getSingleRecord($this->usertbl2, "*", " AND osmusernameosm='$systemId'");
        $this->responseBody = ($result == NULL) ? false: $result;
        return $this->responseBody;
    }
    
}