<?php
class Transactions {
    public function __construct($con) {
        $this->con = $con;
        $this->interbank_transactTbl = "tbl__fcmb_to_fcmb_transact";
        $this->intrabank_transactTbl = "intrabank_transact";
        $this->virtual_accountTbl = "tbl__fcmb_virtual_account";
        $this->failed_virtual_accountTbl = "tbl__fcmb_failed_virtual_account";
        $this->savingsTbl = "tbldaily_savings";
    }
    
    public function updateTransaction($type, $updateData, $reference) {
        try {
            switch($type) {
                case "fcmbfcmb":
                    $updateTran = $this->con->updateTable($this->interbank_transactTbl, $updateData, ["request_id" => $reference]);
                break;
            }
            return $updateTran;
        } 
        catch(Throwable $e) {
            return $e->getMessage();
            die;
        }
    }
    
    public function createFCMBToFCMBTransfer($debitAccount, $creditAccount, $requestBody, $request_id, $amount, $transRef, $response, $status) {
        $this->con->insertRecord($this->interbank_transactTbl, [
                "debitAccount" => $debitAccount,
                "creditAccount" => $creditAccount,
                "requestBody" => $requestBody,
                "request_id" => $request_id,
                "amount" => $amount,
                "transaction_reference" => $transRef,
                "transaction_response" => $response,
                "status_response" => $status,
                "date_created" => date("y-m-d H:i:s")
            ]
        );
        
        return true;
    }
    
    public function createIntraBank_Transaction($debitAccount, $creditAccount, $requestBody, $request_id, $amount, $transRef, $response, $status) {
        $this->con->insertRecord($this->intrabank_transactTbl, [
                "debitAccount" => $debitAccount,
                "creditAccount" => $creditAccount,
                "requestBody" => $requestBody,
                "request_id" => $request_id,
                "amount" => $amount,
                "transaction_reference" => $transRef,
                "transaction_response" => $response,
                "status_response" => $status,
                "date_created" => date("y-m-d H:i:s")
            ]
        );
    }
    
    public function createVirtual($request_id, $requestType = "", $requestNo, $collection_accountNo, $accountNo, $requestBody, $response) {
        $this->con->insertRecord($this->virtual_accountTbl, [
                "request_id" => $request_id,
                "requestType" => ($requestType == "") ? "single":"bulk",
                "requestNo" => $requestNo,
                "collection_accountNo" => $collection_accountNo,
                "accountNo" => ($accountNo == "") ? NULL:$accountNo,
                "requestBody" => $requestBody,
                "response" => $response,
                "date_created" => date("y-m-d H:i:s")
            ]
        );
    }
    
    public function createFailedVirtual($request_id, $requestType = "", $requestNo, $collection_accountNo, $accountNo, $requestBody, $response) {
        $this->con->insertRecord($this->failed_virtual_accountTbl, [
                "request_id" => $request_id,
                "requestType" => ($requestType == "") ? "single":"bulk",
                "requestNo" => $requestNo,
                "collection_accountNo" => $collection_accountNo,
                "accountNo" => ($accountNo == "") ? NULL:$accountNo,
                "requestBody" => $requestBody,
                "response" => $response,
                "date_created" => date("y-m-d H:i:s")
            ]
        );
    }
    
    public function createSavings($systemid, $clientname, $amountPaid, $transactId) {
        $createRecord = $this->con->insertRecord($this->savingsTbl, [
            "invoiceno" => $transactId,
            "systemid" => $systemid,
            "systemid2" => $systemid,
            "clientname" => $clientname,
            "paymentdate" => date("d/m/Y"),
            "paymenttime" => date("H:i:s a"),
            "paymentdatesql" => date("Y-m-d"),
            "amountpaid" => $amountPaid,
            "goal" => "Virtual wallet funding",
            "definition" => "Virtual wallet funding",
            "author" => "Virtual wallet funding",
            "locationcode" => "000",
            "ddate" => date("d/m/Y"),
            "ddates" => date("m/d/Y"),
            "done" => $systemid, 
            "companyname" => "Osemo Borealis Ltd.",
            "branchid" => "000001",
            "corrected" => "Yes"
        ]);
        
        if($createRecord) {
            return true;
        } else {
            return false;
        }
    }
    
}