<?php

namespace MtnlMum;

class MtnlMum
{
    private $url; 
    private $merchant_id;
    private $merchant_key;
    private $apis = ['EBILL','VAN','FETCH','CHECKOUT','PAYMENT'];

    private $err;

    function __construct($url, $merchant_id, $merchant_key) {
        $this->url = $url;
        $this->merchant_id = $merchant_id;
        $this->merchant_key = $merchant_key; 

        $this->err = new \stdClass();        
        $this->err->response = 'ERROR';
        $this->err->error_code = 501;
    }

    private function generate_checksum($data){
        $checksum = hash_hmac('sha256',$data, $this->merchant_key, false);
        return strtoupper($checksum);
    }

    private function validate_checksum($data, $checksum){
        $gchecksum = hash_hmac('sha256',$data, $this->merchant_key, false);
        if(strtoupper($gchecksum) == $checksum){
            return true; 
        }
        return false;
    }

    private function generate_msg($data){
        $jdata = json_encode($data);
        $checksum = $this->generate_checksum($jdata);

        $m = new \stdClass();
        $m->merchant_id = $this->merchant_id;
        $m->data = $jdata;
        $m->checksum = $checksum;

        $msg = json_encode($m);
        $msg = base64_encode($msg);
        return $msg;
    }

    public function run($api, $data){

        $api = strtoupper($api);

        if (!in_array($api, $this->apis)){
            throw new \Exception("Invalid api parameter");
        }

        $url = $this->url;

        if( $api === 'EBILL'){
            $url = $url."/api/ebill/";
        } elseif( $api === 'VAN'){
            $url = $url."/api/van/";
        } elseif( $api === 'FETCH'){
            $url = $url."/api/fetch/";
        } elseif( $api === 'CHECKOUT'){
            $url = $url."/api/checkout/";
        } elseif( $api === 'PAYMENT'){
            $url = $url."/api/payment/";
        }

        try{
            $msg = $this->generate_msg($data);
            return $this->call_api($url, $msg); 
        }
        catch(Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    private function call_api($url,$msg){
        $result=array();
        $options = array(
            CURLOPT_URL => $url, // return web page
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => false, // don't return headers
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_ENCODING => "identity", // handle all encodings
            CURLOPT_USERAGENT => "spider", // who am i
            CURLOPT_AUTOREFERER => true, // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 15, // timeout on connect
            CURLOPT_TIMEOUT => 15, // timeout on response
            CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => 0, // Disabled SSL Cert checks
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POSTFIELDS => "msg=".$msg
        );
        $ch = curl_init( $url );
        curl_setopt_array( $ch, $options );
        $content = curl_exec( $ch );
        $err = curl_errno( $ch );
        $errmsg = curl_error( $ch );
        $header = curl_getinfo( $ch );
        curl_close( $ch );
        if($err != 0){
            $this->err->error_message = $errmsg;
            return $this->err;
        } else {
            $content = base64_decode($content);
            $result = json_decode($content);
            if(is_object($result)){
                if($result->error_code == '000'){
                    if( !$this->validate_checksum($result->data, $result->checksum) ){
                        $this->err->error_message = "Invalid response checksum";
                        return $this->err;
                    } else {
                        $res = new \stdClass();
                        $res->response = 'SUCCESS';
                        $res->data = $result->data;
                        $res->merchant_id = $result->merchant_id;
                        return $res;
                    }
                } else {
                    $this->err->error_message = $result->error_message;
                    return $this->err;
                }
            } else {
                $this->err->error_message = $result;
                return $this->err;
            }
        }  
    }
}

// Van data object classes

class VirtualAccountNumberVerificationIN
{
    public $ClientCode; //String
    public $VirtualAccountNumber; //String
    public $TransactionAmount; //String
    public $Mode; //String
    public $UTRnumber; //String
    public $RemitterName; //String
    public $RemitterAccountNumber; //String
    public $RemitterIFSCCode; //String
    public $SenderToReceiverInformation; //String
    public $Date; //String (dd/mm/yyyy e.g. 12/02/2021 for 12th Feb 2021)
}

class VanData
{
    public $VirtualAccountNumberVerificationIN = []; //array(VirtualAccountNumberVerificationIN)
}

// Ebill data object class

class EbillData
{
    public $TelNo; //Number
    public $SubsNo; //Number
    public $BillNo; //Number
    public $BillDate; //String (dd-mon-yyyy e.g. 12-FEB-2021 for 12th Feb 2021)
    public $Amount; //Number
    public $Email; //String
}

// Bill fetch data object class

class BillFetchData
{
    public $TelNo; //Number
    public $SubsNo; //Number
    public $Count; //Number (default is 1) set to number of bill items required during fetch
    public $Transaction; //String (default is 'Y') set 'N' if TID is not required during fetch
}

// Checkout data object classes

class BillCheckoutData
{
    public $TelNo; //number
    public $BillNo; //number
}

// Checkout data object classes

class BillPaymentData
{
    public $PaymentId; //String
    public $TxnRefNo; //String
    public $Amount; //number
    public $TxnDate; // (dd-mm-yyyy hh:ii:ss e.g. 12-02-2021 01:12:22 for 12th Feb 2021 01:12 AM AND 22 SECONDS)
    public $Status; //String ('SUCCESS' or 'ERROR')
    public $ErrorDescription; //String
}

