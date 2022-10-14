<?php require "../connect.php";

require_once "../model/Authenticate.php"; 
$authenticate = new Authenticate($con);

require_once "../model/Banks.php"; 
$banks = new Banks($con);

if(isset($_POST["verifyAccount"])) {
    
    $account_number = filter_var($_POST["account_number"], FILTER_SANITIZE_STRING);
    $bankCode = $_POST["bankCode"];
    
    if(strlen($account_number) < 10 OR strlen($account_number) > 10) {
        echo "<b>Error: </b> Invalid Account Number provided"; 
    } else {
        $verifyInter = $banks->verifyAccount($bankCode, $account_number);
        $decode_response = json_decode($verifyInter, true)['data'];
        
        if(isset($decode_response['responseCode']) AND $decode_response['responseCode'] == 00) {?> 
        
            <p style='font-size: 28px'><b style='color: red'>Account Name: </b> <?php echo $decode_response['accountName'];?> </p>
            
            <input value="<?php echo $decode_response['accountName'];?>" class="accountName" name="accountName" type="hidden">
            
            <label class="mb-2"><b>Amount (&#8358;)</b></label>
            <input type="text" placeholder="Enter amount" class="form-control form-control-lg mb-2 amount" name="amount" required/>
                
            <!-- Needed for instant pay out ---->
            
            <input value="<?php echo $decode_response['bankVerificationNumber'];?>" class="form-control form-control-lg mb-2 bankVerificationNumber" name="bankVerificationNumber" type="hidden" />
            <input value="<?php echo $decode_response['sessionID'];?>" class="form-control form-control-lg mb-2 sessionID" type="hidden" />
            <input value="<?php echo $decode_response['channelCode'];?>" class="form-control form-control-lg mb-2 channelCode" type="hidden" />
            <input value="<?php echo $decode_response['accountName'];?>" class="form-control form-control-lg mb-2 accountName" type="hidden" />
            
            <label>Enter Password</label>
            <input type="password" placeholder="Enter your password" class="form-control form-control-lg mb-2 accountPassword" name="accountPassword" required/>
            
            <!-- Needed for instant pay out ---->
            <input name="createTransfer" type="hidden">
            <button class="btn btn-primary btn-block btn-lg mt-4 saveTransferRequest" type="submit" style="padding: 20px; font-size: 18px" name="saveTransferRequest"><b>Make Transfer</b></button>
            
            <script>
               
                var load_form = true; //Should form load...?
                
                // vending of Airtime...
                $(".saveTransferRequest").click(function(e) {
                    var amount = $(".amount").val();
                    var accountName = $(".accountName").val();
                    var bankCode = $(".bankName").val();
                    var bankName = $(".bankName option:selected").attr('bank_name');
                    var accountNo = $(".accountNo").val();
                    
                    
        	        if(load_form) {
        	            
                        if(amount == undefined || amount == "" || bankCode == undefined || bankCode == "" || accountNo == undefined || accountNo == "")  {
                            swal.fire({
        						icon: "info",
        						html: "Please fill all field",
        						title: "Error",
        						allowOutsideClick: false
        					})
                        }
                        else {
                            
        					var form = $(this).parents('form');
                            
            				swal.fire({
            				    html: "You are about to initiate a transfer of &#8358;"+amount+" to "+bankName+ "("+accountNo+") which belongs to "+accountName+"<br> <b>Intiate Transfer</b> ?",
            				    title: "Intiate Transfer",
            				    icon: "question",
        						allowOutsideClick: false,
        						showCancelButton: true,
        						showLoaderOnConfirm: true
            				}).then((result) => {
    						    if (result.isConfirmed) { 
    						        form.submit();
                					$(".saveTransferRequest").html("Please wait <i class='fa fa-spinner fa-spin'></i>").prop("disabled", true);
        					    }
    					    });
                        }
        	            
        	        }    
        	        return false;
                });
            </script>
            
            <?php
        } else {
            echo "<p style='font-size: 28px'><b style='color: red'>Error : </b> We could not validate account number. Kindly confirm this account number or try again later</p>";
        }
        
    }
    
}
?>