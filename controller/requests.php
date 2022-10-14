<?php
require_once "../connect.php";
require_once "../model/Authenticate.php";
require_once "../model/VirtualAccount.php";
require_once "../model/Banks.php";

$virtacct = new VirtualAccount($con);
$banks = new Banks($con);

if(isset($_REQUEST["generateAccount"])) {
    $clientName = filter_var($_POST["clientName"], FILTER_SANITIZE_STRING);
    
    $result = $virtacct->generatesingle_virtual($clientName);
    $decode_result = json_decode($result, true);
    if($decode_result["status"] == "success") {
        echo "Account Number is : <b>".$decode_result["account_number"]."</b><br/> Request ID is : <b>".$decode_result["request_id"]."</b><br/>";
    } else {
        echo $decode_result["message"];
    }
}
else if(isset($_REQUEST["fetchSingleRequest"])) {
    $requestID = filter_var($_POST["requestID"], FILTER_SANITIZE_STRING);
    $virtualAccount = filter_var($_POST["virtualAccount"], FILTER_SANITIZE_STRING);
    
    $result = $virtacct->getSingleVirtual($requestID, $virtualAccount);
    print_r($result);
}
else if(isset($_REQUEST["generateBulkAccount"])) {
    $totalNo = $_POST["totalNo"];
    $result = $virtacct->generatebulk_virtual($totalNo);
    
    print_r($result);
    
}
else if(isset($_REQUEST["getBulkAccount"])) {
    $requestID = $_POST["requestID"];
    $result = $virtacct->getbulkvirtual_accountno($requestID);
    
    print_r($result);
}
else if(isset($_REQUEST["updateVirtualAccount"])) {
    $newName = filter_var($_POST["newName"], FILTER_SANITIZE_STRING);
    $virtualAccount = filter_var($_POST["virtualAccount"], FILTER_SANITIZE_STRING);
    
    $result = $virtacct->updateVirtualAccount($newName, $virtualAccount);
    print_r($result);
    
}
else if(isset($_REQUEST["verifyAccount"])) {
    
    $account_number = filter_var($_POST["account_number"], FILTER_SANITIZE_STRING);
    $bankCode = $_POST["bankCode"];
    
    if(strlen($account_number) < 10 OR strlen($account_number) > 10) {
        echo "<b>Error: </b> Invalid Account Number provided"; 
    } else {
        $verifyInter = $banks->verifyAccount($bankCode, $account_number);
        
        $decode_response = json_decode($verifyInter, true)['data'];
        
        if($decode_response->responseCode == 00) {
            echo "<b>Account Name: </b>" . $decode_response['accountName'];
        ?>
            <br/><label class="mt-2"><b>Amount (&#8358;)</b></label>
            <input type="text" placeholder="Enter amount" class="form-control form-control-lg mb-2 amount" required/>
            <input value="<?php echo $decode_response['bankVerificationNumber'];?>" class="form-control form-control-lg mb-2 bankVerificationNumber" type="hidden" />
            <input value="<?php echo $decode_response['sessionID'];?>" class="form-control form-control-lg mb-2 sessionID" type="hidden" />
            <input value="<?php echo $decode_response['channelCode'];?>" class="form-control form-control-lg mb-2 channelCode" type="hidden" />
            <input value="<?php echo $decode_response['accountName'];?>" class="form-control form-control-lg mb-2 accountName" type="hidden" />
            
            <button class="btn btn-primary btn-block btn-lg mt-2 makeTransfer"><b>Make Transfer</b></button> 
            
            <div class="transfer_result"></div>
            
            <script>
                $(".makeTransfer").on("click", function(e) {
                    e.preventDefault();
            
                    var button = $(this);
                    var amount = $(".amount").val();
                    var bankCode = $(".bankName").val();
                    var bankName = $(".bankName option:selected").attr('bank_name');
                    var account_number = $(".account_number").val();
                    var bankVerificationNumber = $(".bankVerificationNumber").val();
                    var sessionID = $(".sessionID").val();
                    var channelCode = $(".channelCode").val();
                    var accountName = $(".accountName").val();
                    var resultfield = $(".transfer_result");
                    
                    if(amount == undefined || amount == "" || bankCode == undefined || bankCode == "" || bankName == undefined || bankName == "") {
                        swal.fire({
                            icon: "error",
                            text: "Please fill all field",
                            title: "Error"
                        })
                    }
                    else {
                         swal.fire({
                            icon: "info",
                            html: "You are about to send NGN"+ amount +" to "+account_number +" (" + bankName +")",
                            title: "Transfer",
        					allowOutsideClick: false,
        					showCancelButton: true,
        					showLoaderOnConfirm: true,
        					confirmButtonText: 'Make Transfer',
                        }).then((result) => {
                            if (result.isConfirmed) {
            					$.ajax({
            					    url: "<?php echo BASE_URL;?>controller/requests.php",
            					    data: {"makeInterTransfer": true, "account_number": account_number, "bankCode": bankCode, "bankName": bankName, "bankVerificationNumber": bankVerificationNumber, "sessionID": sessionID, "channelCode": channelCode, "accountName": accountName, "amount":amount},
            					    type: "post",
            					    beforeSend: function() {
            					        button.html("<b><i class='fa fa-spinner fa-spin'></i> Processing</b>").prop("disabled", true);
            					    },
            					    success: function(response) {
            					        button.html("Make Transfer").prop("disabled", false);
            					        resultfield.html(response);
            					    }
            					})
                            }
        				})
                    }
                    
                });
            </script>
            
        <?php } else {
            echo "<b>Error validating account";
        }
    }
}
else if(isset($_REQUEST["makeInterTransfer"])) {
    $account_number = $_POST["account_number"];
    $bankCode = $_POST["bankCode"];
    $bankName = $_POST["bankName"];
    $bankVerificationNumber = $_POST["bankVerificationNumber"];
    $sessionID = $_POST["sessionID"];
    $channelCode = $_POST["channelCode"];
    $accountName = $_POST["accountName"];
    $amount = $_POST["amount"];
    
    $sendInter = $banks->InterBankTransfer($account_number, $bankCode, $bankName, $bankVerificationNumber, $sessionID, $channelCode, $accountName, $amount);
    $dcodeInter = json_decode($sendInter, true);
    
    echo $dcodeInter["message"];
    
}
else if(isset($_REQUEST["verifyIntraAccount"])) {
    
    $account_number = filter_var($_POST["account_number"], FILTER_SANITIZE_STRING);
    
    if(strlen($account_number) < 10 OR strlen($account_number) > 10) {
        echo "<b>Error: </b> Invalid Account Number provided"; 
    } else {
        $verifyIntra = $banks->verifyIntraAccount($account_number);
        
        $decode_response = json_decode($verifyIntra, true)['data'];
        
        if($decode_response["accT_NAME"] !== NULL) {
            echo "<b>Account Name: </b>" . $decode_response['accT_NAME'];
            
            ?>
            <br/><label class="mt-2"><b>Amount (&#8358;)</b></label>
            <input type="text" placeholder="Enter amount" class="form-control form-control-lg mb-2 amount" required/>
            <input value="<?php echo $decode_response['accT_NAME'];?>" class="form-control form-control-lg mb-2 accountName" type="hidden" />
            
            <button class="btn btn-primary btn-block btn-lg mt-2 makeTransfer"><b>Make Transfer</b></button> 
            
            <div class="transfer_result"></div>
            
            <script>
                $(".makeTransfer").on("click", function(e) {
                    e.preventDefault();
            
                    var button = $(this);
                    var amount = $(".amount").val();
                    var account_number = $(".account_number").val();
                    var accountName = $(".accountName").val();
                    var resultfield = $(".transfer_result");
                    
                    if(amount == undefined || amount == "" || account_number == undefined || account_number == "") {
                        swal.fire({
                            icon: "error",
                            text: "Please fill all field",
                            title: "Error"
                        })
                    }
                    else {
                         swal.fire({
                            icon: "info",
                            html: "You are about to send NGN"+ amount +" to "+account_number +" (" + accountName +")",
                            title: "Transfer",
        					allowOutsideClick: false,
        					showCancelButton: true,
        					showLoaderOnConfirm: true,
        					confirmButtonText: 'Make Transfer',
                        }).then((result) => {
                            if (result.isConfirmed) {
            					$.ajax({
            					    url: "<?php echo BASE_URL;?>controller/requests.php",
            					    data: {"makeIntraTransfer": true, "account_number": account_number, "amount":amount},
            					    type: "post",
            					    beforeSend: function() {
            					        button.html("<b><i class='fa fa-spinner fa-spin'></i> Processing</b>").prop("disabled", true);
            					    },
            					    success: function(response) {
            					        button.html("Make Transfer").prop("disabled", false);
            					        resultfield.html(response);
            					    }
            					})
                            }
        				})
                    }
                    
                });
            </script>
        <?php
        } else {
            echo "<b>Error validating account";
        }
    }
}
else if(isset($_POST["makeIntraTransfer"])) {
    $account_number = $_POST["account_number"];
    $amount = $_POST["amount"];
    
    $sendIntra = $banks->IntraBankTransfer($account_number, $amount);
    $dcodeIntra = json_decode($sendIntra, true);
    
    echo $dcodeIntra["message"];
    
}
?>