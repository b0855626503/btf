<?php


namespace App\Libraries;


class KbankBiz
{
    private $username = null;
    private $password = null;
    private $accnum = null;
    private $ch = null;
    public $balance = null;
    public $showbalance = null;
    public $cookiefilename = ".kbizcookie";
    public $parafilename = '.kbizpara';
    public $ownidfilename = '.kbizownid';
    public $datarssofilename = '.kbizdatarsso';
    private $X_SESSION_TOKEN = '';
    private $PATH = '';

    public function setLogin($user, $pass, $showbalance = true)
    {
        $this->username = $user;
        $this->password = $pass;
        $this->showbalance = $showbalance;
        $this->PATH = storage_path('cookies');
    }

    public function setAccountNumber($accnum)
    {
        if (!is_string($accnum)) {
            exit("Account number must be string.");
        }
        if (strlen($accnum) !== 10) {
            exit("Account number must be 10 digits.");
        }
        $this->accnum = $accnum;
        $this->cookiefilename .= $accnum;
        $this->parafilename .= $accnum;
        $this->ownidfilename .= $accnum;
        $this->datarssofilename .= $accnum;
    }


    public function cutString($content, $text1, $text2)
    {
        $fcontents2 = stristr($content, $text1);
        $rest2 = substr($fcontents2, strlen($text1));
        $extra2 = stristr($fcontents2, $text2);
        $titlelen2 = strlen($rest2) - strlen($extra2);
        $gettitle2 = trim(substr($rest2, 0, $titlelen2));
        return $gettitle2;
    }

    private function readFile($path)
    {
        if (file_exists($path)) {
//            $myfile = fopen($path, "r") or die("Unable to open file!");
            $data = file_get_contents($path);
//            fclose($myfile);
            return $data;
        }
        return "";
    }

    public function get_headers_from_curl_response($response)
    {
        $headers = array();

        $header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

        foreach (explode("\r\n", $header_text) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list($key, $value) = explode(': ', $line);

                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    private function curlInit()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($this->ch, CURLOPT_CAINFO, $this->PATH . '/cacert.pem');
        //curl_setopt($this->ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->PATH . '/' . $this->cookiefilename);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->PATH . '/' . $this->cookiefilename);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 60);
        //curl_setopt($this->ch, CURLOPT_VERBOSE, true);
        //curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.54 Safari/537.36");
    }

    public function login()
    {
        date_default_timezone_set('Asia/Bangkok');
        $this->curlInit();

        $this->X_SESSION_TOKEN = $this->readFile($this->PATH . '/' . $this->parafilename);
        $this->OWNERID = $this->readFile($this->PATH . '/' . $this->ownidfilename);
        $dataRsso = $this->readFile($this->PATH . '/' . $this->datarssofilename);

        $this->refreshSession();
        //$this->X_SESSION_TOKEN = '';
        if ($dataRsso != '') {
            //$this->validateSession($dataRsso);
        }

        if ($this->X_SESSION_TOKEN == '' || $this->OWNERID == '') {
            $this->deleteFile();
            $this->curlInit();
//            echo 'LOGIN';
            curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/authen/login.jsp?lang=th");
            curl_setopt($this->ch, CURLOPT_POST, 0);
            $html = curl_exec($this->ch);

            $html = str_replace(array("\r", "\t", "\n"), "", $html);
            preg_match("/(\\<input type=\"hidden\" name=\"tokenId\" id=\"tokenId\" value=\")(.*?)(\"\\/\\>)/", $html, $temp);
            $tokenid = $temp[2];


            curl_setopt($this->ch, CURLOPT_REFERER, 'https://kbiz.kasikornbank.com/authen/login.jsp?lang=th');
            curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/authen/login.do");
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, "tokenId=" . $tokenid . "&userName=" . urlencode($this->username) . "&password=" . urlencode($this->password) . "&cmd=authenticate&locale=en&captcha=&app=0");
            $temp = curl_exec($this->ch);

            curl_setopt($this->ch, CURLOPT_REFERER, 'https://kbiz.kasikornbank.com/authen/login.do');
            curl_setopt($this->ch, CURLOPT_URL, 'https://kbiz.kasikornbank.com/authen/ib/redirectToIB.jsp');
            curl_setopt($this->ch, CURLOPT_POST, 0);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
            $temp = curl_exec($this->ch);

            if (preg_match("/.*?Invalid User ID or Password.*?/", $temp)) {
                echo 'Invalid User ID or Password';
                return false;
            }

            if (preg_match("/.*?Unsuccessful Login.*?/", $html)) {
                return false;
            }

            $redirect_to = $this->cutString($temp, 'window.top.location.href = "', '";');

            curl_setopt($this->ch, CURLOPT_URL, $redirect_to);
            curl_setopt($this->ch, CURLOPT_POST, 0);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
            $data = curl_exec($this->ch);

            $dataRsso = $this->cutString($temp, 'dataRsso=', '";');
            file_put_contents($this->PATH . '/' . $this->datarssofilename, $dataRsso);

            $this->validateSession($dataRsso);
        }

        if ($this->X_SESSION_TOKEN != '' && $this->OWNERID != '') {
            $this->refreshSession();
        } else {
            return false;
        }

        return true;
    }


    private function validateSession($dataRsso)
    {
        $post_fields = array();
        $post_fields['dataRsso'] = $dataRsso;

        $headers = array('Host: kbiz.kasikornbank.com',
            'Connection: keep-alive',
            'Content-Length: ' . strlen(json_encode($post_fields)),
            'Pragma: no-cache',
            'Cache-Control: no-cache',
            'sec-ch-ua: " Not A;Brand";v="99", "Chromium";v="96", "Google Chrome";v="96"',
            'sec-ch-ua-mobile: ?0',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36',
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
            'X-RE-FRESH: N',
            'X-REQUEST-ID: ' . date("YmdHisu"),
            'sec-ch-ua-platform: "Windows"',
            'Origin: https://kbiz.kasikornbank.com',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Dest: empty',
            'Referer: https://kbiz.kasikornbank.com/',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: th,en;q=0.9');

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
        curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/services/api/authentication/validateSession");

        $response = curl_exec($this->ch);
        $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $headers = $this->get_headers_from_curl_response(substr($response, 0, $header_size));
        $body = substr($response, $header_size);

        if (isset($headers['x-session-token'])) {
            $this->X_SESSION_TOKEN = $headers['x-session-token'];
        }
        if (isset($headers['X-SESSION-TOKEN'])) {
            $this->X_SESSION_TOKEN = $headers['X-SESSION-TOKEN'];
        }

        $body_array = json_decode($body, true);
        $this->OWNERID = $body_array['data']['userProfiles'][0]['ibId'];

        $this->setToken();
    }

    private function setToken()
    {
        if ($this->X_SESSION_TOKEN != '' && $this->OWNERID != '') {
            file_put_contents($this->PATH . '/' . $this->parafilename, $this->X_SESSION_TOKEN);
            file_put_contents($this->PATH . '/' . $this->ownidfilename, $this->OWNERID);
        } else {
            $this->X_SESSION_TOKEN = '';
            $this->OWNERID = '';
            file_put_contents($this->PATH . '/' . $this->parafilename, $this->X_SESSION_TOKEN);
            file_put_contents($this->PATH . '/' . $this->ownidfilename, $this->OWNERID);
        }
    }

    private function refreshSession_()
    {
        $headers = array();
        $headers[] = 'Accept: application/json, text/plain, */*';
        $headers[] = 'Accept-Language: en-US,en;q=0.9,th;q=0.8';
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Host: ib.gateway.kasikornbank.com';
        $headers[] = 'Authorization: ' . $this->X_SESSION_TOKEN;
        $headers[] = 'X-IB-ID: ' . $this->OWNERID;
        $headers[] = 'X-RE-FRESH: Y';
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_URL, "https://ib.gateway.kasikornbank.com/gateway/refreshSession");
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        $temp = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            $this->X_SESSION_TOKEN = '';
            return false;
        }
        $this->X_SESSION_TOKEN = $this->cutString($temp, 'X-SESSION-TOKEN: ', PHP_EOL);
    }

    private function refreshSession()
    {
        $headers = array();
        $headers = array('Host: kbiz.kasikornbank.com',
            'Connection: keep-alive',
            'Content-Length: 2',
            'Pragma: no-cache',
            'Cache-Control: no-cache',
            'sec-ch-ua: " Not A;Brand";v="99", "Chromium";v="96", "Google Chrome";v="96"',
            'X-IB-ID: ' . $this->OWNERID,
            'sec-ch-ua-mobile: ?0',
            'Authorization: ' . $this->X_SESSION_TOKEN,
            'Content-Type: application/json',
            'X-URL: https://kbiz.kasikornbank.com/menu/fundtranfer',
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36',
            'X-RE-FRESH: Y',
            'X-REQUEST-ID: ' . date("YmdHisu"),
            'sec-ch-ua-platform: "Windows"',
            'Origin: https://kbiz.kasikornbank.com',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Dest: empty',
            'Referer: https://kbiz.kasikornbank.com/',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: th,en;q=0.9');

//        echo '<pre>';
//        print_r($headers);
//        echo '</pre>';

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/services/api/refreshSession");
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        $response = curl_exec($this->ch);

        if (preg_match("/.*?UNAUTHORIZED.*?/", $response)) {
            $this->X_SESSION_TOKEN = '';
            $this->deleteFile();
            return false;
        }

        if (curl_errno($this->ch)) {
            $this->X_SESSION_TOKEN = '';
            return false;
        }

        try {

            $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
            $headers = $this->get_headers_from_curl_response(substr($response, 0, $header_size));
            $body = substr($response, $header_size);
            if (isset($headers['x-session-token'])) {
                $this->X_SESSION_TOKEN = $headers['x-session-token'];
            }
            if (isset($headers['X-SESSION-TOKEN'])) {
                $this->X_SESSION_TOKEN = $headers['X-SESSION-TOKEN'];
            }

            $this->setToken();

        } catch (\Throwable $th) {
            $this->X_SESSION_TOKEN = '';
            $this->deleteFile();
            return false;
        }
    }


    public function getBalance()
    {
        if ($this->X_SESSION_TOKEN == '' || $this->OWNERID == '') {
            return false;
        }
        $headers = array();
        $headers[] = 'Accept: application/json, text/plain, */*';
        $headers[] = 'Accept-Language: en-US,en;q=0.9,th;q=0.8';
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Host: kbiz.kasikornbankgroup.com';
        $headers[] = 'Authorization: ' . $this->X_SESSION_TOKEN;
        $headers[] = 'X-IB-ID: ' . $this->OWNERID;
        $headers[] = 'X-RE-FRESH: N';
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/services/api/bankaccountget/getOwnBankAccountFromList");
        curl_setopt($this->ch, CURLOPT_POST, 1);
        $formdata = array();
        $formdata['acctNo'] = $this->accnum;
        $formdata['acctType'] = "CA,FD,SA";
        $formdata['checkBalance'] = "Y";
        $formdata['custType'] = "I";
        $formdata['ownerId'] = $this->OWNERID;
        $formdata['ownerType'] = 'Retail';
        $formdata['language'] = 'th';
        $formdata['nicknameType'] = 'OWNAC';
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($formdata));
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        $temp = curl_exec($this->ch);

        if (preg_match("/.*?UNAUTHORIZED.*?/", $temp)) {
            $this->deleteFile();
            return false;
        }
        //exit;
        if (curl_errno($this->ch)) {
            return false;
        }
        $this->balance = $this->cutString($temp, '"availableBalance":"', '","');
        return $this->balance;
    }

//    public function getBalance()
//    {
//        return $this->balance;
//    }

    public function getTransaction๘()
    {
        if ($this->X_SESSION_TOKEN == '' || $this->OWNERID == '') {
            return array();
        }

        $headers = array();
        $headers[] = 'Accept: application/json, text/plain, */*';
        $headers[] = 'Accept-Language: en-US,en;q=0.9,th;q=0.8';
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Host: ib.gateway.kasikornbank.com';
        $headers[] = 'Authorization: ' . $this->X_SESSION_TOKEN;
        $headers[] = 'X-IB-ID: ' . $this->OWNERID;
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'sec-ch-ua: "Google Chrome";v="95", "Chromium";v="95", ";Not A Brand";v="99"';
        $headers[] = 'sec-ch-ua-mobile: ?0';
        $headers[] = 'sec-ch-ua-platform: "Windows"';
        $headers[] = 'Sec-Fetch-Dest: empty';
        $headers[] = 'Sec-Fetch-Mode: cors';
        $headers[] = 'Sec-Fetch-Site: cross-site';
        $headers[] = 'Origin: https://kbiz.kasikornbankgroup.com';
        $headers[] = 'Referer: https://kbiz.kasikornbankgroup.com/';

        //echo "<pre>";
        //print_r($headers);
        //echo "</pre>";

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_URL, "https://ib.gateway.kasikornbank.com/api/accountsummary/getRecentTransactionList");
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        $formdata = array();
        $formdata['acctNo'] = $this->accnum;
        $formdata['acctType'] = 'SA';
        $formdata['custType'] = 'I';
        $formdata['endDate'] = date('d/m/Y');
        $formdata['ownerId'] = $this->OWNERID;
        $formdata['ownerType'] = 'Retail';
        $formdata['pageNo'] = '1';
        $formdata['refKey'] = '';
        $formdata['rowPerPage'] = '7';
        $formdata['startDate'] = date('d/m/Y');

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($formdata));
        $temp = curl_exec($this->ch);

        if (preg_match("/.*?UNAUTHORIZED.*?/", $temp)) {
            $this->deleteFile();
            return array();
        }
        if (curl_errno($this->ch)) {
            return array();
        }

        $path = storage_path('logs/kbank/getdetail_' . $this->accnum . '_' . now()->format('Y_m_d') . '.log');


        $row = json_decode($temp);

//        file_put_contents($path, print_r($row, true),FILE_APPEND);

        if (isset($row->data)) {
            $index = 0;
            $row = $row->data->recentTransactionList;
            foreach ($row as $item) {
                if ($item->debitCreditIndicator == 'DR') continue;

                if (is_null($item->toAccountNumber)) {
                    $detail = $this->getDetail($item);
                    $data[$index]["report_id"] = $detail['frombank'];
                    $item->toAccountNumber = $detail['fromacc'];
                    $data[$index]["channel"] = $detail['frombank'];
                    $titles = explode(' ', $detail['fromname']);
                } else {
                    $data[$index]["report_id"] = '';
                    $data[$index]["channel"] = $item->channelTh;
                    $titles = explode(' ', $item->fromAccountNameTh);
                }

                $title = isset($titles[1]) ? $titles[1] : '';


                $data[$index]["accno"] = $item->toAccountNumber;
                $data[$index]["date"] = $item->transDate;
                $data[$index]["out"] = $item->withdrawAmount;
                $data[$index]["in"] = $item->depositAmount;
                $data[$index]["fee"] = 0;
                $data[$index]["fromaccno"] = str_replace(array("x", "-"), array("", ""), $item->toAccountNumber);
                if ($data[$index]["report_id"] != '') {
                    $data[$index]["info"] = $item->transNameTh . ' ' . $detail['fromname'] . ' / ' . $data[$index]["fromaccno"];
                } else {
                    $data[$index]["info"] = $item->transNameTh . ' ' . $item->fromAccountNameTh . ' / X' . $data[$index]["fromaccno"];
                }
                $data[$index]["title"] = $title;
                $index++;
            }
        }
        //curl_close($this->ch);
        if (isset($data[0])) {
            return array_reverse($data);
        }
        return array();
    }

    public function getTransaction()
    {
        if ($this->X_SESSION_TOKEN == '' || $this->OWNERID == '') {
            return array();
        }

        $formdata = array();
        $formdata['acctNo'] = $this->accnum;
        $formdata['acctType'] = 'SA';
        $formdata['custType'] = 'I';
        $formdata['endDate'] = date('d/m/Y');
        $formdata['ownerId'] = $this->OWNERID;
        $formdata['ownerType'] = 'Retail';
        $formdata['pageNo'] = '1';
        $formdata['refKey'] = '';
        $formdata['rowPerPage'] = '7';
        $formdata['startDate'] = date('d/m/Y');

        $param = json_encode($formdata);
        $headers[] = 'Content-Length: ' . str_replace('\\', '', strlen($param));

        $params = array();
        $params['post_fields'] = $formdata;
        $params['ibId'] = $this->OWNERID;
        $params['X_SESSION_TOKEN'] = $this->X_SESSION_TOKEN;
        $headers = $this->generate_header($params);

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/services/api/accountsummary/getRecentTransactionList");
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, false);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($formdata));
        $temp = curl_exec($this->ch);
        if (preg_match("/.*?UNAUTHORIZED.*?/", $temp)) {
            $this->deleteFile();
            return array();
        }
        if (curl_errno($this->ch)) {
            return array();
        }

        $row = json_decode($temp);
        if (isset($row->data)) {
            $index = 0;
            $row = $row->data->recentTransactionList;
            foreach ($row as $item) {

                if ($item->debitCreditIndicator == 'DR') {
                    continue;
                }

                if (is_null($item->toAccountNumber)) {
                    $detail = $this->getDetail($item);
                    $data[$index]["report_id"] = $detail['frombank'];
                    $item->toAccountNumber = $detail['fromacc'];
                    $data[$index]["channel"] = $detail['frombank'];
                    $titles = explode(' ', $detail['fromname']);
                } else {
                    $data[$index]["report_id"] = '';
                    $data[$index]["channel"] = $item->channelTh;
                    $titles = explode(' ', $item->fromAccountNameTh);
                }

                $title = isset($titles[1]) ? $titles[1] : '';


                $data[$index]["accno"] = $item->toAccountNumber;
                $data[$index]["date"] = $item->transDate;
                $data[$index]["out"] = $item->withdrawAmount;
                $data[$index]["in"] = $item->depositAmount;
                $data[$index]["fee"] = 0;
                $data[$index]["fromaccno"] = str_replace(array("x", "-"), array("", ""), $item->toAccountNumber);
                // $data[$index]["info"] = $item->transNameTh . ' ' . $item->channelTh . ' /X' . $data[$index]["fromaccno"];
                if ($data[$index]["report_id"] != '') {
                    $data[$index]["info"] = $item->transNameTh . ' ' . $detail['fromname'] . ' / ' . $data[$index]["fromaccno"];
                } else {
                    $data[$index]["info"] = $item->transNameTh . ' ' . $item->fromAccountNameTh . ' / X' . $data[$index]["fromaccno"];
                }

                $data[$index]["title"] = $title;
                $index++;
            }
        }
        //curl_close($this->ch);
        if (isset($data[0])) {
            return array_reverse($data);
        }
        return array();
    }

    public function getDetail($data)
    {
        if ($this->X_SESSION_TOKEN == '' || $this->OWNERID == '') {
            return array();
        }
        $fromacc = [];

        $trandate = explode(' ', $data->transDate);

        $headers = array();
        $headers[] = 'Accept: application/json, text/plain, */*';
        $headers[] = 'Accept-Language: en-US,en;q=0.9,th;q=0.8';
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Host: kbiz.kasikornbank.com';
        $headers[] = 'Authorization: ' . $this->X_SESSION_TOKEN;
        $headers[] = 'X-IB-ID: ' . $this->OWNERID;
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'sec-ch-ua: "Google Chrome";v="95", "Chromium";v="95", ";Not A Brand";v="99"';
        $headers[] = 'sec-ch-ua-mobile: ?0';
        $headers[] = 'sec-ch-ua-platform: "Windows"';
        $headers[] = 'Sec-Fetch-Dest: empty';
        $headers[] = 'Sec-Fetch-Mode: cors';
        $headers[] = 'Sec-Fetch-Site: cross-site';
        $headers[] = 'Origin: https://kbiz.kasikornbank.com';
        $headers[] = 'Referer: https://kbiz.kasikornbank.com/';

        //echo "<pre>";
        //print_r($headers);
        //echo "</pre>";

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_URL, "https://kbiz.kasikornbank.com/services/api/accountsummary/getRecentTransactionDetail");
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, false);

        $formdata = array();
        $formdata['acctNo'] = $this->accnum;
        $formdata['debitCreditIndicator'] = 'CR';
        $formdata['custType'] = 'I';
        $formdata['origRqUid'] = $data->origRqUid;
        $formdata['originalSourceId'] = $data->originalSourceId;
        $formdata['ownerId'] = $this->OWNERID;
        $formdata['ownerType'] = 'Retail';
        $formdata['transCode'] = $data->transCode;
        $formdata['transDate'] = $trandate[0];
        $formdata['transType'] = $data->transType;

        $path = storage_path('logs/kbank/getdetail_' . $this->accnum . '_' . now()->format('Y_m_d') . '.log');
//        file_put_contents($path, print_r($formdata, true),FILE_APPEND);


        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($formdata));
        $temp = curl_exec($this->ch);
        if (preg_match("/.*?UNAUTHORIZED.*?/", $temp)) {
            $this->deleteFile();
            return array();
        }
        if (curl_errno($this->ch)) {
            return array();
        }


        $path = storage_path('logs/kbank/getdetail_' . $this->accnum . '_' . now()->format('Y_m_d') . '.log');


        $row = json_decode($temp);

//        file_put_contents($path, print_r($row, true),FILE_APPEND);
        if (isset($row->data)) {
            $fromacc['frombank'] = !empty($row->data->bankNameEn) ? $row->data->bankNameEn : '';
            $fromacc['fromacc'] = !empty($row->data->toAccountNo) ? $row->data->toAccountNo : '';
            $fromacc['fromname'] = !empty($row->data->toAccountNameTh) ? $row->data->toAccountNameTh : '';
        }

        return $fromacc;
    }

    private function deleteFile()
    {
        if (file_exists($this->PATH . '/' . $this->cookiefilename)) {
            unlink($this->PATH . '/' . $this->cookiefilename);
        }
        if (file_exists($this->PATH . '/' . $this->parafilename)) {
            unlink($this->PATH . '/' . $this->parafilename);
        }
        if (file_exists($this->PATH . '/' . $this->ownidfilename)) {
            unlink($this->PATH . '/' . $this->ownidfilename);
        }
        if (file_exists($this->PATH . '/' . $this->datarssofilename)) {
            unlink($this->PATH . '/' . $this->datarssofilename);
        }
//        echo 'delete';
    }

    private function get_str_between($str, $starting_word, $ending_word)
    {
        $subtring_start = strpos($str, $starting_word);
        //Adding the strating index of the strating word to
        //its length would give its ending index
        $subtring_start += strlen($starting_word);
        //Length of our required sub string
        $size = strpos($str, $ending_word, $subtring_start) - $subtring_start;
        // Return the substring from the index substring_start of length size
        return substr($str, $subtring_start, $size);
    }

    public function generate_header($data)
    {
        return array('Host: kbiz.kasikornbank.com',
            'Connection: keep-alive',
            'Content-Length: ' . strlen(json_encode($data['post_fields'])),
            'Pragma: no-cache',
            'Cache-Control: no-cache',
            'sec-ch-ua: " Not A;Brand";v="99", "Chromium";v="96", "Google Chrome";v="96"',
            'X-IB-ID: ' . $data['ibId'],
            'sec-ch-ua-mobile: ?0',
            'Authorization: ' . $data['X_SESSION_TOKEN'],
            'Content-Type: application/json',
            'X-URL: https://kbiz.kasikornbank.com/menu/account/account/recent-transaction',
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36',
            'X-RE-FRESH: N',
            'X-REQUEST-ID: ' . date("YmdHisu"),
            'sec-ch-ua-platform: "Windows"',
            'Origin: https://kbiz.kasikornbank.com',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Dest: empty',
            'Referer: https://kbiz.kasikornbank.com/',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: th,en;q=0.9');
    }

}

