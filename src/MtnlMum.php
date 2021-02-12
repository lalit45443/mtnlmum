<?php

namespace MtnlMum;

class Client
{
    private $url;
    private $merchant_id;
    private $merchant_key;

    function __construct($url, $merchant_id, $merchant_key) {
        $this->url = $url;
        $this->merchant_id = $merchant_id;
        $this->merchant_key = $merchant_key; 
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

    public function ebill($tel_no, $subs_no, $bill_no, $bill_date, $amount, $email){

        if($this->url == ''){
            throw new \Exception("URL is not provided");
        }

        if($this->merchant_id == ''){
            throw new \Exception("Merchant id is not provided");
        }

        if($this->merchant_key == ''){
            throw new \Exception("Merchant key is not provided");
        }

        try{
            $m = new \stdClass();
            $m->tel_no = $tel_no;
            $m->subs_no = $subs_no;
            $m->bill_no = $bill_no;
            $m->bill_date = $bill_date;
            $m->amount = $amount;
            $m->email = $email;

            $msg = $this->generate_msg($m);
            $url = $this->url."/api/ebill/";
            return $this->call_api($url, $msg); 
        }
        catch(Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    public function van($data){

        if($this->url == ''){
            throw new \Exception("URL is not provided");
        }

        if($this->merchant_id == ''){
            throw new \Exception("Merchant id is not provided");
        }

        if($this->merchant_key == ''){
            throw new \Exception("Merchant key is not provided");
        }

        try{
            $msg = $this->generate_msg($data);
            $url = $this->url."/api/van/";
            return $this->call_api($url, $msg); 
        }
        catch(Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    public function fetch($tel_no, $subs_no, $count = 1, $transaction = 'Y'){

        if($this->url == ''){
            throw new \Exception("URL is not provided");
        }

        if($this->merchant_id == ''){
            throw new \Exception("Merchant id is not provided");
        }

        if($this->merchant_key == ''){
            throw new \Exception("Merchant key is not provided");
        }

        try{
            $m = new \stdClass;
            $m->tel_no = $tel_no;
            $m->subs_no = $subs_no;
            $m->count = $count;
            $m->transaction = $transaction;

            $msg = $this->generate_msg($m);
            $url = $this->url."/api/fetch/";
            return $this->call_api($url, $msg); 
        }
        catch(Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    public function checkout($data){

        if($this->url == ''){
            throw new \Exception("URL is not provided");
        }

        if($this->merchant_id == ''){
            throw new \Exception("Merchant id is not provided");
        }

        if($this->merchant_key == ''){
            throw new \Exception("Merchant key is not provided");
        }

        try{
            $msg = $this->generate_msg($data);
            $url = $this->url."/api/checkout/";
            return $this->call_api($url, $msg); 
        }
        catch(Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    public function payment($payment_id, $txn_ref_no, $amount, $txn_date, $error_status, $error_description){

        if($this->url == ''){
            throw new \Exception("URL is not provided");
        }

        if($this->merchant_id == ''){
            throw new \Exception("Merchant id is not provided");
        }

        if($this->merchant_key == ''){
            throw new \Exception("Merchant key is not provided");
        }

        try{
            $m = new \stdClass();
            $m->payment_id        = $payment_id;
            $m->txn_ref_no        = $txn_ref_no;
            $m->amount            = $amount;
            $m->txn_date          = $txn_date;
            $m->error_status      = $error_status;
            $m->error_description = $error_description;

            $msg = $this->generate_msg($m);
            $url = $this->url."/api/payment/";
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
            return $errmsg;
        } else {
            $content = base64_decode($content);
            $result = json_decode($content);
            if(is_object($result)){
                if($result->error_code == '000'){
                    if( !$this->validate_checksum($result->data, $result->checksum) ){
                        throw new \Exception("Invalid response checksum");
                    } else {
                        $res = new \stdClass();
                        $res->response = 'SUCCESS';
                        $res->data = $result->data;
                        $res->merchant_id = $result->merchant_id;
                        return $res;
                    }
                } else {
                    $err = new \stdClass();
                    $err->error_code = $result->error_code;
                    $err->error_message = $result->error_message;
                    return $err;
                }
            } else {
                throw new \Exception($result);
            }
        }  
    }
}
