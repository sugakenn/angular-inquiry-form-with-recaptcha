<?php

// settings
define('C_XSRF_COOKIE_NAME','XSRF-TOKEN');
define('C_XSRF_COOKIE_EXPIRE',60 * 60 * 24);
define('C_XSRF_COOKIE_TOKEN_LENGTH',24);

define('C_XSRF_HEADER_NAME','x-xsrf-token');

//スパムチェックをしたい場合は下で設定するディレクトリを手動で作成してください
//If you want to check for spam, please manually create the directory set below
//and hide from web access use .htaccess
define('C_SPAM_MANAGE_DIR','access');

//Invalid list file
//in v4 192-168-001-001\n
//in v6 2001-0000-0000-0001\n (network address)
define('C_SPAM_INVALID_LIST_V4','invalid.v4');
define('C_SPAM_INVALID_LIST_V6','invalid.v6');

define('C_SPAM_CHECK_MINUTES_RANGE',60 * 15);
define('C_SPAM_CHECK_LIMIT_CNT',3);

define('C_SAVE_DIR','message');

//reCAPTCHA setting
define('C_USE_CAPTCHA',false);//set true if necessary CAPTCHA
define('C_CAPTCHA_SECRET','YOUR_reCAPTCHA_SECRET'); // input your secret if necessary CAPTCHA
define('C_CAPTCHA_VERIFY_URL','https://www.google.com/recaptcha/api/siteverify');
define('C_CAPTCHA_BORDER',0.5);

//意図しないPOSTを処理するのを避けるため、受け取るJSONオブジェクトのプロパティを限定します
//Limit the properties of the JSON object you receive to avoid handling unintended POSTs
define('C_PROPERTIES','nicname,mail,hp,summary,detail,token');

define('DEBUG',false);

if (DEBUG) {
    //デバッグ時にCORSエラーを回避します
    //Avoid CORS errors when debugging
    header('Access-Control-Allow-Origin: http://localhost:4200');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
    header('Access-Control-Allow-Methods: GET, HEAD, POST, DELETE, PUT, OPTIONS, X-XSRF-TOKEN');
}

//攻撃者にヒントを与えたくない場合は、エラー時のexitPhpメソッドに渡すメッセージを編集して下さい
//If you don't want to give hints to the attacker, edit the message you pass to the "exitPhp" method on error

//entry
main();

function main() {
    if (isset($_GET['c'])) {
        checkCookie();
    } else {
        saveMessage();
    }
}

function checkCookie() {
    //check cookie
    if (isset($_COOKIE[C_XSRF_COOKIE_NAME])) {
        //exist
        $var = array();
        $var['']=false;
        exitPhp($var,true,'cookie exists');
    } else {
        //not exist
        //save cookie and exit;
        setXsrfToken();

        $var = array();
        $var['first_access']=true;
        exitPhp($var,true,'cookie made');
    }
}

function saveMessage() {
    
    //make dir when not exist
    if (is_dir(C_SAVE_DIR)==false) {
        if (mkdir(C_SAVE_DIR)==false) {
            exitPhp(array(),false,'failed create save dir');
        }
        file_put_contents(C_SAVE_DIR.'/.htaccess',"<Files \"*\">\n  Require all denied\n</Files>");
    }

    //XSRF check
    //デバッグ時にはXSRFチェックは常にFalseとなるので、チェックしません
    //When debugging, the XSRF check is always False, so don't check it.
    if (DEBUG == false) {
        if (compareCookieWithHeader()==false) {
            exitPhp(array(),false,'invalid access');
        }
    }

    //データが送信されていない場合は、以降のチェックをせずに終了します
    //If no data has been sent, exit without further checks
    $str = file_get_contents('php://input');
    if ($str===false || $str=="") {
        exitPhp(array(),false,'params not exit');
    }
    $json = json_decode($str,true);

    //check json values
    $list = explode(',',C_PROPERTIES);

    $bln = array();    
    foreach($list as $value) {
        $bln[$value]=false;
    }

    foreach($json as $key =>$value) {
        if (in_array($key,$list,true)) {
            $bln[$key]=true;
        } else {
            exitPhp(array(),false,'Contains unexpected properties');
        }
    }

    if (in_array(false,$bln,true)) {
        exitPhp(array(),false,'Required property does not exist');
    }

    //reCAPTCHA check
    $fltScore=0;
    if(DEBUG == false && C_USE_CAPTCHA) {
        $fltScore = verifyCaptcha($json['token']);
        if ($fltScore < C_CAPTCHA_BORDER) {
            exitPhp(array(),false,'CAPTCHA error');
        }
    }

    //spam check
    checkSpam();

    //add timestamp
    $json['timestamp']=date('Y/m/d H:i:s');
    //add remote address
    $json['remoteaddress']=$_SERVER['REMOTE_ADDR'];
    //add score
    $json['score']=$fltScore;

    $strFileName = date('Ymd_His').'.dat';
    $intLoopBreaker=10;
    while (is_file(C_SAVE_DIR.'/'.$strFileName)) {

        $strFileName = date('Ymd_His').'.dat';
        
        if($intLoopBreaker-- < 0) {
            exitPhp(array(),false,'failed save data (filename)');
        }

        sleep(1);
    }
  
    //remove token value
    unset($json['token']);
    if (file_put_contents(C_SAVE_DIR.'/'.$strFileName,json_encode($json))===false) {
        exitPhp(array(),false,'failed save data (write)');
    }

    exitPhp(array(),true,'saved data');
}

function checkSpam() {

    $intDot = substr_count($_SERVER['REMOTE_ADDR'], '.');
    $intColon = substr_count($_SERVER['REMOTE_ADDR'], ':');
    $strType = "";
    $strConvAddr="";

    if (0 < $intDot) {
        //ipv4
        $strConvAddr=convertAddressV4($_SERVER['REMOTE_ADDR']);
        chcekSpamSub($strConvAddr,true);
    } else if(0 < $intColon) {
        //ipv6
        $strConvAddr=convertAddressV6($_SERVER['REMOTE_ADDR']);
        chcekSpamSub($strConvAddr,true);
    } else {
        //unknown
        exitPhp(array(),false,'host unknown error');
    }

    return true;
}

function chcekSpamSub($strConvAddress,$blnV4) {
    
    if($blnV4) {
        $strTarget = $strConvAddress;
        $strInvalidList = C_SPAM_INVALID_LIST_V4;
    } else {
        //In IPv6, check by network address (substr 19)
        $strTarget = substr($strConvAddress,19);
        $strInvalidList = C_SPAM_INVALID_LIST_V6;
    }

    //Do not check for spam if the dir does not exist
    if (is_dir(C_SPAM_MANAGE_DIR)==false) {
        return true;
    }

    //check InvalidList
    if (is_file(C_SPAM_MANAGE_DIR.'/'.$strInvalidList)) {
        $fp = @fopen(C_SPAM_MANAGE_DIR.'/'.$strInvalidList,"r");

        if ($fp) {
            while(($buffer = fgets($fp)) !== false) {
                if ($buffer == $strTarget) {
                    fclose($fp);

                    //save access history for Invalid list
                    if (is_file(C_SPAM_MANAGE_DIR.'/'.$strTarget)==false) {
                        //サーバー管理者以外にシェルにアクセスできる人がいない場合はchmodコマンドは不要です
                        //You don't need "chmod" if no one else has access to the shell
                        if (touch(C_SPAM_MANAGE_DIR.'/'.$strTarget) == false || chmod(C_SPAM_MANAGE_DIR.'/'.$strTarget,0600) == false) {
                            exitPhp(array(),false,'error in saving history');
                        }
                    }
                    
                    file_put_contents(C_SPAM_MANAGE_DIR.'/'.$strTarget,(string)time()."\n",FILE_APPEND);

                    exitPhp(array(),false,'invalid host error');
                }
            }
        } else {
            exitPhp(array(),false,'unknown error in host check');
        }
    }

    //IPアドレス毎のアクセス管理(一定期間における送信回数)
    //Access management for each IP address(Number of transmissions in a certain period)
    $intBorder = time() - C_SPAM_CHECK_MINUTES_RANGE;
    $intLimitCnt = C_SPAM_CHECK_LIMIT_CNT;
    $intCnt = 0;

    if (is_file(C_SPAM_MANAGE_DIR.'/'.$strTarget)==false) {
        //サーバー管理者以外にシェルにアクセスできる人がいない場合はchmodコマンドは不要です
        //You don't need "chmod" if no one else has access to the shell
        if (touch(C_SPAM_MANAGE_DIR.'/'.$strTarget) == false || chmod(C_SPAM_MANAGE_DIR.'/'.$strTarget,0600) == false) {
            exitPhp(array(),false,'make file error in host check');
        }
    }

    // check access count
    $fp = @fopen(C_SPAM_MANAGE_DIR.'/'.$strTarget,"r+");

    if ($fp) {
        $blnLocked = false;

        for ($i = 0; $i < 3; $i++) {
            $blnLocked = flock($fp,LOCK_EX);
            if ($blnLocked) {
                $blnLocked=true;
            } else {
                sleep(1);
            }
        }

        if ($blnLocked==false) {
            fclose($fp);
            exitPhp(array(),false,'lock file error in host check');
        }

        while(($buffer = fgets($fp)) !== false) {
            if ($intBorder <= intval($buffer)) {
                $intCnt++;
                
                if ($intLimitCnt < $intCnt) {
                    flock($fp,LOCK_UN);
                    fclose($fp);
                    exitPhp(array(),false,'exceeds the limit');
                }
            }
        }

        fwrite($fp,(string)time()."\n");
        flock($fp,LOCK_UN);
        fclose($fp);
    } else {
        exitPhp(array(),false,'manage file open error');
    }

    return true;
}

function convertAddressV4($str) {
    $arr = explode(".",$str);
    $strRet = "";
    for ($i = 0; $i < 4; $i++) {
        if ($i !=0) {
            $strRet.="-";
        }

        //padding zero. format: 000-000-000-000
        $strRet .= str_pad((string)$arr[$i],3,"0",STR_PAD_LEFT);
    }
    return $strRet;
}

function convertAddressV6($str) {
    
    //reverse shortning 
    if (0 < strpos($str,"::")) {
        $intColonCnt = substr_count($_SERVER['REMOTE_ADDR'], ':');
        if ($intColCnt < 7) {
            $str = str_replace("::",str_repeat(":",7-$intColCnt),$str);
        }
    }

    $arr = explode(":",$str);
    
    $strRet = "";
    for ($i = 0; $i < 8; $i++) {
        if ($i !=0) {
            $srRet.="-";
        }

        //padding zero. format: 0000-0000-0000-0000-0000-0000-0000-0000
        $strRet .= str_pad((string)$arr[$i],4,"0",STR_PAD_LEFT);
    }
    return $strRet;
}


function compareCookieWithHeader(){
    $headers = getallheaders();
		
    if (isset($_COOKIE[C_XSRF_COOKIE_NAME],$headers[C_XSRF_HEADER_NAME])) {
        
        if ($_COOKIE[C_XSRF_COOKIE_NAME]=="") {
            return false;
        }

        if ($_COOKIE[C_XSRF_COOKIE_NAME]==$headers[C_XSRF_HEADER_NAME]) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }

}

function exitPhp($var=array(),$blnResult=true,$strMsg='') {
    $ret['data']=$var;
    $ret['result']=$blnResult;
    $ret['message']=$strMsg;
    echo json_encode($ret);
    exit(0);
}

function setXsrfToken($strCookieSameSite='Strict') {

    //make token
    $strToken = bin2hex(random_bytes(C_XSRF_COOKIE_TOKEN_LENGTH));
    
    //XSRF token
    setcookie(
    C_XSRF_COOKIE_NAME,
        $strToken,
        [
            'expires' => 0,
            'path' => '/',
            'domain' => $_SERVER["HTTP_HOST"],
            'secure' => true,
            'httponly' => false,
            'samesite' => DEBUG ? 'None' : $strCookieSameSite
        ]
    );
}

function deleteXsrfToken() {
    setcookie(
        C_XSRF_COOKIE_NAME,
        bin2hex(random_bytes(C_XSRF_COOKIE_TOKEN_LENGTH)), //temp value
        time()-42000,
        '/',
        $_SERVER["HTTP_HOST"],
        true,
        false
    );
}

function verifyCaptcha($strToken) {
    $params = array(
        'secret' => C_CAPTCHA_SECRET,
        'response' => $strToken
    );
    $data = http_build_query($params, "", "&");

    // header
    $header = array(
        "Content-Type: application/x-www-form-urlencoded",
        "Content-Length: ".strlen($data)
    );

    $context = array(
        "http" => array(
            "method"  => "POST",
            "header"  => implode("\r\n", $header),
            "content" => $data
        )
    );

    $str = file_get_contents(C_CAPTCHA_VERIFY_URL, false, stream_context_create($context));

    //file_put_contents('./debug-capcha-result.txt',$str);
    
    $json = json_decode($str,true);
    
    if (isset($json['score'],$json['success'],$json['action'])==false) {
        return -1;
    }

    if ($json['success']==false) {
        return -2;
    }

    return $json['score'];

}
