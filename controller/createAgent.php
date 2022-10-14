<?php
require "../connect.php";

require_once "../model/Authenticate.php";
require_once "../model/VirtualAccount.php";

$authenticate = new Authenticate($con);
$virtaccount = new VirtualAccount($con);

define('UPLOAD_PATHS', '../'.UPLOAD_PATH);

if(isset($_POST["createAgent"])) {
    $firstname = filter_var(trim($_POST['firstname']), FILTER_SANITIZE_STRING);
    $lastname = filter_var(trim($_POST['lastname']), FILTER_SANITIZE_STRING);
    $mobileno = filter_var(trim($_POST['mobileno']), FILTER_SANITIZE_STRING);
    $emailaddress = filter_var(trim($_POST['emailaddress']), FILTER_SANITIZE_EMAIL);
    $gender = filter_var(trim($_POST['gender']), FILTER_SANITIZE_STRING);
    $city = filter_var(trim($_POST['city']), FILTER_SANITIZE_STRING);
    $state = filter_var(trim($_POST['state']), FILTER_SANITIZE_STRING);
    $lga = filter_var(trim($_POST['lga']), FILTER_SANITIZE_STRING);
    $location = filter_var(trim($_POST['location']), FILTER_SANITIZE_STRING);
    $country = filter_var(trim($_POST['country']), FILTER_SANITIZE_STRING);
    $houseaddress = filter_var(trim($_POST['houseaddress']), FILTER_SANITIZE_STRING);
    $agentid = filter_var(trim($_POST['agentid']), FILTER_SANITIZE_STRING);
    $client_name = $firstname . ' '. $lastname;

    $allowedExtension = array('image/png', 'image/jpg', 'image/jpeg', 'image/bmp');

    $isAgentIDExist = $con->getSingleRecord('tblusers', "*", " AND systemid='$agentid'");
    
    $_SESSION['titleMessage'] = 'Error';
    if($_FILES['passport']['error'] > 0) {
        $_SESSION['errorMessage'] = 'Error';
    }
    else if($isAgentIDExist == NULL) {
        $_SESSION['errorMessage'] = 'Agent ID ('.$agentid.') does not exists';
    }
    else if(!in_array($_FILES['passport']['type'], $allowedExtension)) {
        $_SESSION['errorMessage'] = 'Unsupported file extension format selected. Only png, jpg and bmp are allowed';
    }
    elseif (round($_FILES['passport']["size"] / 1024) > 20480) {
        $_SESSION['errorMessage'] = "You can upload file size up to 2 MB";
    }
    else {
        
        /*create directory with 777 permission if not exist - start*/ 
        $tmpPath = $_FILES['passport']['tmp_name'];
        $newName = date("YmdHis").'.png';
        
        if (!file_exists(UPLOAD_PATHS)) {
            mkdir(UPLOAD_PATHS, 0775, true);
        }
        
        if (move_uploaded_file($tmpPath, UPLOAD_PATHS.strtolower($newName))) {
            
            $createVirtual = $virtaccount->generatesingle_virtual($client_name);
            $decode_virt = json_decode($createVirtual, true);
            $account_number = ($decode_virt['status'] == 'success' ? $decode_virt['account_number'] : NULL);
            if($account_number != NULL) {
                $userData = [
                    'email' => $emailaddress,  
                    'name' => $client_name, 
                    'fname' => $firstname, 
                    'lname' => $lastname, 
                    'mobile' => $mobileno, 
                    'gender' => $gender, 
                    'address' => $houseaddress, 
                    'city' => $city, 
                    'state' => $state, 
                    'lga' => $lga, 
                    'osmusernameosm' => $account_number,
                    'wemasystemid' => $account_number,
                    'systemid' => $account_number,
                    'locationcode' => $agentid,
                    'passport' => $newName
                ];
                
                $con->insertRecord("tblusers", $userData );
                $_SESSION['titleMessage'] = 'Success';
                $_SESSION['successMessage'] = 'User Account ('.$account_number.') created successfully';
                header("location: https://osb.ng/createUserAgent-Done/?va=".$account_number);
                die;
            }
            else {
                $_SESSION['errorMessage'] = "System Error! Account number could not be generated. Please try again";
            }
        } else {
            $_SESSION['errorMessage'] = "File could not be moved";
        }
    }
    header("location: ".$_SERVER['HTTP_REFERER']);
    die;
}
?>