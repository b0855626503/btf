<?php
class BAYBIZ
{
    private $username;
    private $password;
    private $accnum;
    private $accdisp;
    private $ch;
    private $token;
    private $ma;
    private $next = 'https://www.krungsribizonline.com/BAY.KOL.Corp.WebSite/Pages/MyPortfolio.aspx?d';
    private $ref = '';
    public $balance;
    private $PATH = '';
    public $html = '';
    public $cookiefilename = '.htcookie';
    public $lastpage;
    public $resdata = array();

    private function getFormData($html, $key)
    {
        preg_match('/<input.*?name="' . $key . '".*?id="' . str_replace(array('\$'), '_', $key) . '" value="(.*?)".*?\/>/', $html, $ml);
        return $ml[1];
    }

    public function setLogin($user, $pass)
    {
        $this->username = $user;
        $this->password = $pass;
        $this->PATH = __DIR__ . '/protect';
    }

    public function setAccountNumber($accnum)
    {
        if (!is_string($accnum)) {
            die("Account number must be string.");
        }
        if (strlen($accnum) !== 10) {
            die("Account number must be 10 digits.");
        }
        $this->resdata = array();
        $this->accnum = $accnum;
        $this->accdisp = substr($accnum, 0, 3) . '-' . substr($accnum, 3, 1) . '-' . substr($accnum, 4, 6);
        $this->cookiefilename .= $accnum . date('H');
    }

    private function config()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($this->ch, CURLOPT_PROXY, '127.0.0.1:8888');
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->PATH . '/' . $this->cookiefilename);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->PATH . '/' . $this->cookiefilename);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.54 Safari/537.36');
    }

    public function login()
    {
        $this->config();

        curl_setopt($this->ch, CURLOPT_URL, $this->next);
        //curl_setopt($this->ch, CURLOPT_REFERER, $this->ref);
        curl_setopt($this->ch, CURLOPT_POST, 0);
        echo $this->html = curl_exec($this->ch);
        exit;
        if (strpos($this->html, 'ออกจากระบบ') === false) {
            echo "new login";
            curl_setopt($this->ch, CURLOPT_URL, 'https://www.krungsribizonline.com/BAY.KOL.Corp.WebSite/Common/Login.aspx');
            $temp = curl_exec($this->ch);
            $VIEWSTATE = $this->getFormData($temp, "__VIEWSTATE");
            $VIEWSTATEGENERATOR = $this->getFormData($temp, "__VIEWSTATEGENERATOR");
            $EVENTVALIDATION = $this->getFormData($temp, "__EVENTVALIDATION");
            $EVENTARGUMENT = $this->getFormData($temp, "__EVENTARGUMENT");
            $PREVIOUSPAGE = $this->getFormData($temp, "__PREVIOUSPAGE");

            $formdata = array();
            $formdata['__LASTFOCUS'] = '';
            $formdata['__EVENTTARGET'] = 'ctl00$cphLoginBox$imgLogin';
            $formdata['__EVENTARGUMENT'] = $EVENTARGUMENT;
            $formdata['__VIEWSTATE'] = $VIEWSTATE;
            $formdata['__VIEWSTATEGENERATOR'] = $VIEWSTATEGENERATOR;
            $formdata['__VIEWSTATEENCRYPTED'] = '';
            $formdata['__PREVIOUSPAGE'] = $PREVIOUSPAGE;
            $formdata['__EVENTVALIDATION'] = $EVENTVALIDATION;
            $formdata['ctl00$hddApplicationMode'] = 'KBOL';
            $formdata['ctl00$cphLoginBox$hddPWD'] = '';
            $formdata['ctl00$cphLoginBox$hddLanguage'] = 'TH';
            $formdata['username'] = '';
            $formdata['password'] = '';
            $formdata['ctl00$cphLoginBox$txtUsernameSME'] = $this->username;
            $formdata['ctl00$cphLoginBox$txtPasswordSME'] = '';
            $formdata['ctl00$cphLoginBox$hdPassword'] = $this->password;
            $formdata['ctl00$cphLoginBox$hdLogin'] = '';
            $post = http_build_query($formdata);
            //echo '<pre>';
            //print_r($formdata); //exit;

            ////////////////////////////////////////////////
            curl_setopt($this->ch, CURLOPT_URL, 'https://www.krungsribizonline.com/BAY.KOL.Corp.WebSite/Common/Login.aspx');
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
            $temp = curl_exec($this->ch);

            curl_setopt($this->ch, CURLOPT_URL, $this->next);
            //curl_setopt($this->ch, CURLOPT_REFERER, $this->ref);
            curl_setopt($this->ch, CURLOPT_POST, 0);
            $this->html = curl_exec($this->ch);
        }

    }
    public function cutString($content, $text1, $text2)
    {
        $fcontents2 = stristr($content, $text1);
        $rest2 = substr($fcontents2, strlen($text1));
        $extra2 = @stristr($fcontents2, $text2);
        $titlelen2 = strlen($rest2) - strlen($extra2);
        $gettitle2 = trim(substr($rest2, 0, $titlelen2));
        return $gettitle2;
    }

    public function getBalance()
    {
        return $this->balance;
    }

    public function getTransaction()
    {
        //echo $this->html;exit;
        preg_match('/token=(.*?)&ma/', $this->html, $temp3);
        //preg_match('/doPostBack\((.*?),&#39;&#39;\)">' . $this->accnum . '</', $this->html, $temp);
        //echo $this->accnum;
        //echo $this->html;
        $temp2 = $this->cutString($this->html, 'acctype="Saving" account="', '" href="javascript'); // . $this->accnum . '"'

        //preg_match('/value="(.*?)\|' . $this->accnum . '"/', $this->html, $temp2);
        //echo $this->html;
        if ($temp2 == "") { //!== 2
            die("Not found that account.");
        }
        $this->token = $temp3[1];
        $this->ma = $temp2;

        $urltrans = 'https://www.krungsribizonline.com/BAY.KOL.Corp.WebSite/Pages/MyAccount.aspx?token' . $this->cutString($this->html, 'MyAccount.aspx?token', '&ma') . '&ma=' . $this->ma;
        //echo $urltrans;
        curl_setopt($this->ch, CURLOPT_URL, $urltrans);
        curl_setopt($this->ch, CURLOPT_POST, 0);
        $this->html = curl_exec($this->ch);
        //$VIEWSTATE = $this->getFormData($this->html, "__VIEWSTATE");
        //$VIEWSTATEGENERATOR = $this->getFormData($this->html, "__VIEWSTATEGENERATOR");
        //$EVENTVALIDATION = $this->getFormData($this->html, "__EVENTVALIDATION");
        //$PREVIOUSPAGE = $this->getFormData($this->html, "__PREVIOUSPAGE");
        $this->getstatement(1);
        if ($this->lastpage > 1) {
            if ($this->lastpage == 2) {
                $this->getstatement($this->lastpage);
            } else {
                $startpage = $this->lastpage > 5 ? $this->lastpage - 5 : 2;
                for ($i = $startpage; $i <= $this->lastpage; $i++) {
                    $this->getstatement($i);
                }
            }

        }
        //print_r($this->resdata);
        return array_reverse($this->resdata);

    }

    private function getstatement($page)
    {
        curl_setopt($this->ch, CURLOPT_URL, 'https://www.krungsribizonline.com/BAY.KOL.Corp.WebSite/Pages/MyAccount.aspx/GetStatementToday');
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, ('{"pageIndex":' . $page . ',"pageoffset":""}'));
        curl_setopt(
            $this->ch,
            CURLOPT_HTTPHEADER,
            array(
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Connection: keep-alive',
                'X-Requested-With: XMLHttpRequest',
                'Content-Type: application/json; charset=UTF-8',
            )
        );
        $this->html = stripslashes(curl_exec($this->ch)); //;
        $this->html = str_replace('"{', '{', $this->html);
        $this->html = str_replace('}"', '}', $this->html);
        $statements = json_decode($this->html);
        //echo '<pre>';
        //print_r($statements);
        //exit;
        $this->lastpage = 1;
        $this->balance = $statements->d->LastLedgerBalanceAmount;
        $temp = $statements->d->Statements;
        if (count($temp) > 0) {
            $data = array();
            $index = 0;
            foreach ($temp as $item) {

                //echo $cols[$colkey];
                $data[$index]["date"] = str_replace('T', ' ', $item->BookingDateTime);
                $data[$index]["fromaccno"] = substr($item->AccountRef, -6);
                $data[$index]["bank"] = $item->BankName;

                if ($item->MneIndicator != "DBIT") {
                    $data[$index]["in"] = str_replace(',', '', $item->Amount);
                    $data[$index]["info"] = 'BAY::' . $item->TransactionCategory . ' จาก ' . $item->BankName . ' X' . $data[$index]["fromaccno"];
                    $data[$index]["out"] = 0;
                } else {
                    $data[$index]["in"] = 0;
                    $data[$index]["info"] = 'BAY::' . $item->TransactionCategory . ' ไปยัง ' . $item->BankName . ' X' . $data[$index]["fromaccno"];
                    $data[$index]["out"] = str_replace(',', '', $item->Amount);
                }
                $data[$index]["fee"] = 0;

                $index++;
            }
            if (isset($data[0])) {

                if (count($this->resdata) > 0) {

                    $this->resdata[] = $data;
                } else {
                    $this->resdata = $data;

                }

            } else {
                $this->resdata = array();
            }
        } else {
            $this->resdata = array();
        }

    }

}

$autobank = new BAYBIZ();
$autobank->setLogin('Pigmoo121314','Aa121314@');
$autobank->setAccountNumber('8039427223');
$autobank->login();
$res = $autobank->getTransaction();
echo "Balance:" . $autobank->getBalance();
echo '<pre>';
print_r($res);

