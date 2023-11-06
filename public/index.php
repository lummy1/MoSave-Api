<?php

ini_set('display_errors', 'Off');

use App\Models\Db;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;

use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use PHPMailer\PHPMailer\SMTP;



require_once __DIR__ . '/../vendor/autoload.php';


$envPath =dirname(__DIR__, 1);

$dotenv = Dotenv\Dotenv::createImmutable($envPath);
$dotenv->load();



$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new BasePathMiddleware($app));
$app->addErrorMiddleware(true, true, true);

$app->get('/', function (Request $request, Response $response) {

    $response->getBody()->write('Hello World!');
    return $response;
});


//put / update
$app->put(
    '/customers-data/update/{id}',
    function (Request $request, Response $response, array $args) {

        $params = $request->getQueryParams();
       // var_dump($params);
        $id = $request->getAttribute('id');
        $data = $request->getParsedBody();
        $name = $data['name'];
        $email = $data["email"];
        $phone = $data["phone"];
    }
);

//delete 

$app->delete('/customers-data/delete/{id}', function (Request $request, Response $response, array $args) {
    $id = $args["id"];

    $sql = "DELETE FROM customers WHERE id = $id";

    //print_r($_ENV);
    // Retrieve the request's body and parse it as JSON

    // if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' ) || !array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER) ) 
    // exit();

    // Retrieve the request's body

    // $input = @file_get_contents("php://input");
    // define('PAYSTACK_SECRET_KEY','SECRET_KEY');

    // validate event do all at once to avoid timing attack

    // if($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY))
    //     exit();



    // $input = @file_get_contents("php://input");
    // $event = json_decode($input);
    // // Do something with $event
    // http_response_code(200); // PHP 5.4
});

$app->post('/confirm/jwt/test', function (Request $req, Response $res) {

    $response = array();
    $conn = new Db();
    $db = $conn->connect();
    $data = $req->getParsedBody();
    $firstname = $data["status"];
    $bvn = $data["bvn"];
    $phoneno = $data["phoneno"];
    $lastname = $data["lastname"];

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);

    $res->getBody()->write(json_encode($firstname));
    return $res
        ->withHeader('content-type', 'application/json')
        ->withStatus(401);

    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] != 1) {
       
        $res->getBody()->write(json_encode($result));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
    } else {
        
    

        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    }
});

$app->post('/customer/wallettransfer', function (Request $req, Response $res) {


    $response = array();
    $conn = new Db();
    $db = $conn->connect();
    $data = $req->getParsedBody();
    $senderwallet = $data["senderwallet"]; //e.g LOY, SAV, TIC
    $planId = $data["saving_plan_id"];
    $accountId = $data["saving_account_id"];   // if SAV, then this is required
    $receiverwallet = $data["receiverwallet"];  //e.g LOY, SAV, TIC
    $transAmount = $data["amount"];
  

    if($senderwallet === 'LOY' ){

        $walletName= "MoLoyal Wallet";
    }elseif($senderwallet === 'SAV'){

        $walletName= "MoSave Wallet";
    }elseif($senderwallet === 'TIC'){
        $walletName= "MoTicket Wallet";
    }

    if($receiverwallet === 'LOY' ){

        $rec_walletName= "MoLoyal Wallet";
    }elseif($receiverwallet === 'SAV'){

        $rec_walletName= "MoSave Wallet";
    }elseif($receiverwallet === 'TIC'){
        $rec_walletName= "MoTicket Wallet";
    }


    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] != 1) {

        $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    } else {



        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


            if ($senderwallet === "SAV") {


                $sqlsa = "SELECT  `account_code`, `account_name`, `minimum_bal`, `desc` FROM `mosave_account_type` WHERE sn=:actid";

                $stmtb = $db->prepare($sqlsa);
                $stmtb->bindParam("actid", $accountId);

                $stmtb->execute();
                $resultsa = $stmtb->fetch();

                //$response=$email;
                if (!$resultsa) {
                    //$response['error']=true;
                    $response['message'] = "Cannot find Customer account type";
                } else {

                    $accountCode = $resultsa['account_code'];
                    $accountType = $resultsa['account_name'];
                    $bal = $resultsa['minimum_bal'];


                    $walletqrys = "SELECT `account_bal` as wBalances FROM `mosave_wallet` WHERE  customerId=:custid and accountId=:actid ";


                    $walstmts = $db->prepare($walletqrys);

                    $walstmts->bindParam(":custid", $customerId, PDO::PARAM_STR);
                    $walstmts->bindParam(":actid", $accountId, PDO::PARAM_STR);

                    $walstmts->execute();
                    $reds = $walstmts->fetch();


                    $wallet_balances = $reds['wBalances'];

                    $walletqry = "SELECT `available_bal` as wBalance, `account_bal`  FROM `mosave_plan_wallet` WHERE  customerId=:custid and accountId=:actid and plan_id=:pid ";


                    $walstmt = $db->prepare($walletqry);

                    $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                    $walstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);
                    $walstmt->bindParam(":pid", $planId, PDO::PARAM_STR);

                    $walstmt->execute();
                    $red = $walstmt->fetch();


                    $wBalance = $red['wBalance'];
                    $account_bal = $red['account_bal'];



                    $amts = $transAmount;
                    $amt = $transAmount * (-1);
                    $newBalance = $wBalance + $amt;
                    $newBalancecheck = $newBalance * (-1);

                    $mosave_wal = $wallet_balances - $transAmount;
                    $ac_bal = $account_bal - $transAmount;
                    // $response['t']=$newBalancecheck;
                    //$response['n']=$newBalance;
                    //$response['j']=$bal;



                    if ($amts > $wBalance) {

                        $response['error'] = true;
                        $response['message'] = "Insufficient funds";
                        $res->getBody()->write(json_encode($response));
                        return $res
                            ->withHeader('content-type', 'application/json')
                            ->withStatus(500);
                    }else{
                    $available_bal = $newBalance - $bal;


                    $updwalqryplan = "UPDATE `mosave_plan_wallet` SET `account_bal`=:ac_bal, `available_bal`=:newb  WHERE accountId=:actid and customerId=:custid and plan_id=:pid ";

                    $walstmtplan = $db->prepare($updwalqryplan);
                    $walstmtplan->bindParam(":ac_bal", $ac_bal, PDO::PARAM_STR);
                    $walstmtplan->bindParam(":newb", $newBalance, PDO::PARAM_STR);
                    $walstmtplan->bindParam(":custid", $customerId, PDO::PARAM_STR);
                    $walstmtplan->bindParam(":actid", $accountId, PDO::PARAM_STR);
                    $walstmtplan->bindParam(":pid", $planId, PDO::PARAM_STR);

                    $walstmtplan->execute();

                    $updwalqry = "UPDATE `mosave_wallet` SET `account_bal`='$mosave_wal'  WHERE accountId=:actid and customerId=:custid ";

                    $walstmt = $db->prepare($updwalqry);

                    $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                    $walstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);

                    $walstmt->execute();

                    $trans_mode = 'WT';
                    $desc = 'Transfer to '.$walletName;

                    $sql = "INSERT INTO `mosave_savingtransaction`(`customerId`, `agentId`,`accountId`, `planId`, `accountNo`, `transAmount`,`transType`,`transref`,`des`
            `accountType`, `accountCode`,`trans_mode`,  `transDate`,`time`,`ip`) VALUES (:customerId,:agentId,:actids,:pid,:accountNo,:transAmount,:transtype,:transref,:des,:accountType,:accountCode,:trans_mode,:datecreated,:time,:ip)";


                    $stmt = $db->prepare($sql);

                    $stmt->bindParam(":customerId", $customerId, PDO::PARAM_STR);
                    $stmt->bindParam(":agentId", $agentId, PDO::PARAM_STR);
                    $stmt->bindParam(":actids", $accountId, PDO::PARAM_STR);
                    $stmt->bindParam(":pid", $planId, PDO::PARAM_STR);
                    $stmt->bindParam(":accountNo", $accountNo, PDO::PARAM_STR);
                    $stmt->bindParam(":transAmount", $transAmount, PDO::PARAM_STR);
                    $stmt->bindParam(":transtype", $transtype, PDO::PARAM_STR);
                    $stmt->bindParam(":des", $desc, PDO::PARAM_STR);
                    $stmt->bindParam(":transref", $refs, PDO::PARAM_STR);
                    $stmt->bindParam(":trans_mode", $trans_mode, PDO::PARAM_STR);
                    $stmt->bindParam(":accountType", $accountType, PDO::PARAM_STR);
                    $stmt->bindParam(":accountCode", $accountCode, PDO::PARAM_STR);




                    $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                    $stmt->bindParam(":time", $time, PDO::PARAM_STR);
                    $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);

                    //$stmt->execute();
                    $result = $stmt->execute();
                    //$result = $stmt->fetch();
                }
            }
            }

            if ($senderwallet === "LOY") {
                $walletqrys = "SELECT `redeemableamt`  FROM `mosave_loyalty_wallet` WHERE  customerId=:custid ";


                $walstmts = $db->prepare($walletqrys);

                $walstmts->bindParam(":custid", $customerId, PDO::PARAM_STR);
               

                $walstmts->execute();
                $reds = $walstmts->fetch();


                $redeemableamt = $reds['redeemableamt'];

                if ($transAmount > $redeemableamt) {

                    $response['error'] = true;
                    $response['message'] = "Insufficient funds";
                }else{
                $moloyal_bal = $redeemableamt - $transAmount;
                $ac_bal = $account_bal - $transAmount;
                
                $updwalqry = "UPDATE `mosave_loyalty_wallet` SET `redeemableamt`=:moloyal_bal  WHERE  customerId=:custid ";

                $walstmt = $db->prepare($updwalqry);

                $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                $walstmt->bindParam(":moloyal_bal", $moloyal_bal, PDO::PARAM_STR);

                $walstmt->execute();

                $trans_mode = 'WT';
                $desc = 'Transfer to '.$walletName;

                $sql = "INSERT INTO `mosave_savingtransaction`(`customerId`, `agentId`,`accountId`, `planId`, `accountNo`, `transAmount`,`transType`,`transref`,`des`
        `accountType`, `accountCode`,`trans_mode`,  `transDate`,`time`,`ip`) VALUES (:customerId,:agentId,:actids,:pid,:accountNo,:transAmount,:transtype,:transref,:des,:accountType,:accountCode,:trans_mode,:datecreated,:time,:ip)";


                $stmt = $db->prepare($sql);

                $stmt->bindParam(":customerId", $customerId, PDO::PARAM_STR);
                $stmt->bindParam(":agentId", $agentId, PDO::PARAM_STR);
                $stmt->bindParam(":actids", $accountId, PDO::PARAM_STR);
                $stmt->bindParam(":pid", $planId, PDO::PARAM_STR);
                $stmt->bindParam(":accountNo", $accountNo, PDO::PARAM_STR);
                $stmt->bindParam(":transAmount", $transAmount, PDO::PARAM_STR);
                $stmt->bindParam(":transtype", $transtype, PDO::PARAM_STR);
                $stmt->bindParam(":des", $desc, PDO::PARAM_STR);
                $stmt->bindParam(":transref", $refs, PDO::PARAM_STR);
                $stmt->bindParam(":trans_mode", $trans_mode, PDO::PARAM_STR);
                $stmt->bindParam(":accountType", $accountType, PDO::PARAM_STR);
                $stmt->bindParam(":accountCode", $accountCode, PDO::PARAM_STR);




                $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                $stmt->bindParam(":time", $time, PDO::PARAM_STR);
                $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);

                //$stmt->execute();
                $result = $stmt->execute();


                } 


            }
        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    }
});


$app->post('/db/test', function (Request $req, Response $response) {
    //print_r($_ENV);

    $conn = new Db();
    $db = $conn->connect();
    $res = $_ENV['JWT_SECRET_KEY'];
    //echo $_SERVER['NAME'] . "\n";

    $docRoot = __DIR__;
    $newdir = dirname(__DIR__, 1);
    //$newdir = "../".$docRoot;

    $co = "SELECT bank_code FROM bank_settings";
    $conn = new Db();
    $db = $conn->connect();

    $cons = $db->prepare($co);
    $cons->execute();

    $resulta = $cons->fetch();


    $response->getBody()->write(json_encode($resulta));
    return $response
    ->withHeader('content-type', 'application/json')
    ->withStatus(401);
});
//customer register

$app->post('/customer/register', function (Request $request, Response $response) {
    $res = array();
    $data = $request->getParsedBody();
    $firstname = $data["firstname"];
    $bvn = $data["bvn"];
    $phoneno = $data["phoneno"];
    $lastname = $data["lastname"];
    $email = $data["email"];
    $referralId = $data["referralId"];
    $password = $data["password"];
    $gender = $data["gender"];
    $email = $data["email"];
    $referralId = $data["referralId"];


    $expiryDate = date('Y-m-d', strtotime('+50 years'));



    $parentphone = $referralId;





    $password    = password_hash($password, PASSWORD_BCRYPT);

    //  $phoneno='0'.$phoneno;
    //      $firstname  = 'lums';
    //     $phoneno    = '08090963549';
    //     $mobilenetwork    = 'mtn';
    //     $lastname    = 'dde';
    //   $email       = 'oodexxbdsiyi@avante-cs.com';
    //     $email       = 'oluodebiyi@gmail.com';
    //     $merchantId  = 201501;
    //      $referno  = '0906355466';






    try {
        //auto assign SerialNo
        if (isEmailExist($email)) {
            $res['error']   = true;
            $res['message'] = "This email already exist";
            $response->getBody()->write(json_encode($res));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
        } elseif (isPhoneExist($phoneno)) {
            $res['error']   = true;
            $res['message'] = "This phone number already exist";
            $response->getBody()->write(json_encode($res));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            return;
        } elseif ($bvn != '' && isBvnExist($bvn)) {
            $res['error']   = true;
            $res['message'] = "This bvn number already exist for another customer";
            $response->getBody()->write(json_encode($res));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            return;
        } else {

            //All inputs are valid

            $mobilenetwork = getMobileNetwork($phoneno);
            $merchantId = 2015;
            $accountype = 1;


            $dateCreated = date("Y-m-d H:i:s");

            $co = "SELECT bank_code FROM bank_settings";
            $conn = new Db();
            $db = $conn->connect();

            $cons = $db->prepare($co);
            $cons->execute();

            $resulta = $cons->fetch();


            if ($resulta) {
                $bkcode = $resulta['bank_code'];
            }


            $format = "%1$07d";
            $i = 1;
            $go = sprintf($format, $i);

            $bank_act = $bkcode . $go;
            $rando = ucwords(random());
            //$checkacc_no="SELECT * FROM customer WHERE account_num='$bank_act'";
            $stmta = $db->prepare("SELECT * FROM config_customer_account_info order by sn desc ");
            // $stmta->bindParam(":bank_act", $bank_act,PDO::PARAM_STR);


            $stmta->execute();
            $results = $stmta->fetch();

            $acc = $results['account_num'];

            if ($results) {

                $account_num = $acc + 1;
            } else {
                $account_num = $bank_act;
            }

            $date = date("Y-m-d");

            $time = date("H:i:s");

            $ip = $_SERVER['REMOTE_ADDR'];


            //auto assign SerialNo
            $qrydbname = "SELECT * FROM `config_program` where programId=:merchantId limit 1";
            $conn = new Db();
            $db = $conn->connect();

            $stmtdbname = $db->prepare($qrydbname);
            $stmtdbname->bindParam(":merchantId", $merchantId, PDO::PARAM_STR);
            $stmtdbname->execute();
            $resu    = $stmtdbname->fetch();
            $dbname = $resu['programDb'];

            //auto assign SerialNo
            $qrySerialNo = "SELECT * FROM `code_carddetails` where status='I' and printed=0 and merchantId=:merchantId limit 1";

            $stmtSerialNo = $db->prepare($qrySerialNo);
            $stmtSerialNo->bindParam(":merchantId", $merchantId, PDO::PARAM_STR);
            $stmtSerialNo->execute();
            $resas     = $stmtSerialNo->fetch();
            $cserialno = $resas['serialNo'];

            if ($cserialno != "") {

                if (!empty($_FILES)) {

                    $target_path = "customer_pics/";

                    $target_path = $target_path . basename($_FILES['photo']['name']);
                    $file_upload = move_uploaded_file($_FILES['photo']['tmp_name'], $target_path);
                }
                if ($cserialno) {


                    $sql = "INSERT INTO config_user(`firstname`,`lastname`,`email`,`img`,`password`,`BVN_num`,`gender`,
      `mobilenetwork`,`country_code`,`userId`,`agentId`,`dateCreated`,`locked`, `tp_checker`,`email_confirm_flag`, `phoneno_confirm_flag`, `date`,`time`,`ip`)
      VALUES (:firstname,:lastname,:email,:img,:password,:bvn,:gender,:mobilenetwork,:ccode,:phoneno,:agentid,:datecreated,'0',:tp_check,0,0,:date,:time,:ip)";
                    $conn = new Db();
                    $db = $conn->connect();

                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(":firstname", $firstname, PDO::PARAM_STR);
                    $stmt->bindParam(":lastname", $lastname, PDO::PARAM_STR);
                    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
                    $stmt->bindParam(":mobilenetwork", $mobilenetwork, PDO::PARAM_STR);
                    $stmt->bindParam(":bvn", $bvn, PDO::PARAM_STR);
                    $stmt->bindParam(":password", $password);
                    $stmt->bindParam(":gender", $gender);
                    $stmt->bindParam(":img", $target_path);
                    $stmt->bindParam(":ccode", $ccode, PDO::PARAM_STR);
                    $stmt->bindParam(":phoneno", $phoneno, PDO::PARAM_STR);
                    $stmt->bindParam(":agentid", $agentsn, PDO::PARAM_STR);
                    $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                    $stmt->bindParam(":tp_check", $rando, PDO::PARAM_STR);

                    $stmt->bindParam(":date", $date, PDO::PARAM_STR);
                    $stmt->bindParam(":time", $time, PDO::PARAM_STR);
                    $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);


                    //if(!$stmt->execute()) echo $stmt->error;
                    $rs = $stmt->execute();



                    $custid = $db->lastInsertId();

                    $sql3 = "INSERT INTO `config_customer_account_info`(`customerId`, `account_typeId`,  `account_num`) 
                VALUES (:custid,:accountype,:account_num)";
                    $conn = new Db();
                    $db = $conn->connect();

                    $stmt3 = $db->prepare($sql3);

                    $stmt3->bindParam(":custid", $custid, PDO::PARAM_STR);
                    $stmt3->bindParam(":accountype", $accountype, PDO::PARAM_STR);


                    $stmt3->bindParam(":account_num", $account_num, PDO::PARAM_STR);
                    $result3 = $stmt3->execute();




                    $channel = '2';
                    //post info in conig_user_merchant_info
                    $qrypostinfo = "INSERT INTO `config_user_merchant_info`(`userId`,`merchantId`, `serialNo`,`tierLevel`,`registered_from`)
      VALUES(:phone,:merchantId,:serialno,'1',:channel)";
                    $stmtpostinfo = $db->prepare($qrypostinfo);
                    $stmtpostinfo->bindParam(":phone", $phoneno, PDO::PARAM_STR);
                    $stmtpostinfo->bindParam(":merchantId", $merchantId, PDO::PARAM_STR);
                    $stmtpostinfo->bindParam(":serialno", $cserialno, PDO::PARAM_STR);
                    $stmtpostinfo->bindParam(":channel", $channel, PDO::PARAM_STR);
                    $stmtpostinfo->execute();

                    if ($rs) {

                        $qryupdatecard  = "UPDATE `code_carddetails` SET dateActivated=:dateCreated, status='A' WHERE `serialNo`=:serialno";
                        $stmtupdatecard = $db->prepare($qryupdatecard);
                        $stmtupdatecard->bindParam(":dateCreated", $dateCreated, PDO::PARAM_STR);

                        $stmtupdatecard->bindParam(":serialno", $cserialno, PDO::PARAM_STR);
                        $stmtupdatecard->execute();

                        // $db2    = getDynamicConnection($dbname);
                        // $qry10  = "INSERT INTO `subscription`(`userId`, `merchantId`, `serialNo`, `dateCreated`, `lastSubscriptionDate`, `expiryDate`, `status`) VALUES ('$phoneno','$merchantId','$cserialno','$dateCreated','$dateCreated','$expiryDate','A')";
                        // $qry10s = $db2->prepare($qry10);

                        // $qry10s->execute();
                        $smsmsg     = 'Hi  ' . $firstname . ', Welcome to MoSave. 
          
              You have been registered successfully \nYour Acct No: ' . $account_num . '';

                        //$whatsappchk=sendWhatsApp($phone,$smsmsg);
                        $smscheck = sendSMS($phoneno, $smsmsg);


                        $from    = "noreply@moloyal.com";
                        $to      = $email;
                        $msg1     = 'Thank you for your registration on MoLoyal. <br><br> Kindly copy the code below and verify your email<br>' . $rando . ' <br> and  Your MoLoyal Registration is now complete. <br>
  
                    You have been assigned serial number <strong>' . $cserialno . '</strong> and your MoSave account number is <strong>' . $account_num . '</strong> . <br>This will be used for all future transactions.<br>
                    <br><br>
                    Yours sincerely,
                    <br>
                    MoLoyal Team
                    ';

                        $msg = '<tr>
                                            <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                                                    <h4 style="color:#000;"> Hi ' . $firstname . ',</h4>
                                                <p style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; color: #000000; text-align: left;">
                        
                                      
                    ' . $msg1 . '
  
                  </p>
                                        </td>
                                    </tr>';
                        $subject = "MoLoyal Registration";
                        $type    = $firstname;
                        //$gender='';

                        $sqlsa = "SELECT  `account_code`, `account_name`, `minimum_bal`, `desc` FROM `mosave_account_type` WHERE sn=:actid";

                        $stmtb = $db->prepare($sqlsa);
                        $stmtb->bindParam("actid", $accountype);

                        $stmtb->execute();
                        $resultsa = $stmtb->fetch();


                        $accountCode = $resultsa['account_code'];
                        $account_name = $resultsa['account_name'];


                        if ($parentphone != '') {

                            $chekpar = "SELECT * FROM `config_user` where userId=:ch";


                            $qrySerialNoa = $db->prepare($chekpar);

                            $qrySerialNoa->bindParam(":ch", $parentphone, PDO::PARAM_STR);
                            $qrySerialNoa->execute();
                            $resultsas = $qrySerialNoa->fetch();
                            $parent_id = $resultsas['sn'];

                            if ($parent_id == '') {


                                $res['error']   = true;
                                $res['message'] = "The Referrer is not registered on the platform";
                                $response->getBody()->write(json_encode($res));
                                    return $response
                                        ->withHeader('content-type', 'application/json')
                                        ->withStatus(401);
                                return;
                            } else {
                                //checj child emailif available
                                $chek = "SELECT * FROM `multilevel` where child_email=:childemail";


                                $qrySerialNo = $db->prepare($chek);

                                $qrySerialNo->bindParam(":childemail", $email, PDO::PARAM_STR);
                                $qrySerialNo->execute();
                                $results = $qrySerialNo->fetch();
                                $child_email = $results['child_email'];


                                $chek2 = "SELECT * FROM `multilevel` where child_phone=:childphone";
                                $qrySerphone = $db->prepare($chek2);

                                $qrySerphone->bindParam(":childphone", $phoneno, PDO::PARAM_STR);


                                $qrySerphone->execute();
                                $rest = $qrySerphone->fetch();
                                $child_phone = $rest['child_phone'];

                                if ($child_email != '') {
                                    $res['error']   = true;
                                    $res['message'] = 'Email has been referred before';
                                    $response->getBody()->write(json_encode($res));
                                    return $response
                                        ->withHeader('content-type', 'application/json')
                                        ->withStatus(401);

                                    return;
                                } elseif ($child_phone != '') {
                                    $res['error']   = true;
                                    $res['message'] = 'Phone number has been referred before';
                                    $response->getBody()->write(json_encode($res));
                                    return $response
                                        ->withHeader('content-type', 'application/json')
                                        ->withStatus(401);

                                    return;
                                } else {
                                    $conn = new Db();
                                    $db = $conn->connect();
                                    $sql = "INSERT INTO  `multilevel`( `parent_id`,`child_id`, `child_phone`, `child_email`, `submerchantId`, `activated`,`datecreated`)
                                    VALUES (:parid,:childid,:childphone,:childemail,:merchantId,0,:datecreated)";
                                    $stmt = $db->prepare($sql);
                                    $stmt->bindParam(":parid", $parent_id, PDO::PARAM_STR);
                                    $stmt->bindParam(":childid", $custid, PDO::PARAM_STR);
                                    $stmt->bindParam(":childemail", $email, PDO::PARAM_STR);
                                    $stmt->bindParam(":childphone", $phoneno, PDO::PARAM_STR);
                                    $stmt->bindParam(":merchantId", $merchantId, PDO::PARAM_STR);
                                    $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);


                                    $stmt->execute();
                                }
                            }
                        }



                        $emailsent = sendEmail($from, $to, $msg, $subject, $type);

                        //$res['emailsent']   =  $emailsent;
                        $res['error']   =  false;
                        $res['message'] = "You have been registered as a new user";
                        $response->getBody()->write(json_encode($res));
                    return $response
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(200);
                    } else {

                        $res['error']   = true;
                        $res['message'] = "There was an error contacting the server, please retry";
                        $response->getBody()->write(json_encode($res));
                        return $response
                            ->withHeader('content-type', 'application/json')
                            ->withStatus(401);
                        
                        }

                    
                }
            }
        }
    } catch (PDOException $e) {
        $res['error']   = true;
        $res['message'] = $e->getMessage();
        $response->getBody()->write(json_encode($res));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

$app->post('/customer/update', function (Request $req, Response $res) {

    $response = array();
    


    $conn = new Db();
    $db = $conn->connect();


    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;
            $data = $req->getParsedBody();
            $firstname = $data["firstname"];
            $bvn = $data["bvn"];
            $phoneno = $data["phoneno"];
            $lastname = $data["lastname"];
            $email = $data["email"];
           
           
            $gender = $data["gender"];
            
            $accountNo = $data["accountNo"];
            $city = $data["city"];
            $state = $data["state"];
            $accountName = $data["accountName"];
            $dateOfBirth = $data["birthdate"];
            $country = $data["country"];
            $bank = $data["bank"];
            $bankcode = $data["bankcode"];
    
    
            $lastResetDate  = date('Y-m-d H:i:s');

            $updateUserquery = "UPDATE `config_user` SET `BVN_num`= '$bvn',  `city`= '$city', `state`= '$state',
      `country`= '$country', `dateOfBirth`= '$dateOfBirth', `gender`= '$gender', `lastResetDate`= '$lastResetDate' WHERE `userId` = ? AND `email` = ? ";
            // $updateUser =  mysqli_query($con, $updateUserquery);

            $stmt = $db->prepare($updateUserquery);
            $stmt->bindParam(1, $token_userid);
            $stmt->bindParam(2, $token_email);


            $stmt->execute();

            $errorInfo = $stmt->errorInfo();

            if (isset($errorInfo[2])) {
                $response['error']   = true;
                $response['message'] = $errorInfo[2];
                $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            } else {
                $response['error'] = false;
                $response['message'] = 'Profile has been updated successfully';
                $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);
            }
            
        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    }
});

//customer login
$app->post('/customer/login', function (Request $request, Response $response) {

    $data = $request->getParsedBody();
    $identity = $data["identity"];
    $password = $data["password"];





    // $postdata = file_get_contents("php://input");
    // $request  = json_decode($postdata);
     $res = array();



    // $identity = $app->request()->params('identity');
    // if ($identity == null || $identity == '') {
    //   $postdata   = file_get_contents("php://input");
    //   $request    = json_decode($postdata);
    //   $identity    = $request->identity;
    // }

    // $password = $app->request()->params('password');
    // if ($password == null || $password == '') {
    //   $postdata   = file_get_contents("php://input");
    //   $request    = json_decode($postdata);
    //   $password    = $request->password;
    // }
    //$password = md5($password);
    // $userno   = '09028652543';
    // $password = 'test';





    //             $sql2="Select * from config_user where userId=:usernos and password=:password";
    try {

        $conn = new Db();
        $db = $conn->connect();
        $query = "SELECT sn, firstname, lastname, `password` FROM config_user  WHERE email = ? or userId= ? LIMIT 0,1";

        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $identity);
        $stmt->bindParam(2, $identity);
        $stmt->execute();
        $num = $stmt->rowCount();

        if ($num > 0) {

            $resulta = $stmt->fetch();
            $enc_pass = $resulta['password'];

            $auth = password_verify($password, $enc_pass);

            //$stmt->bindParam(":password", $password,PDO::PARAM_STR);
            //$stmt->execute();
            //$errorInfo = $stmt->errorInfo();
            //if(isset($errorInfo[2])){
            //  $response['error']=true;
            //  $response['message']=$errorInfo[2];
            //  echo json_encode($response);
            //}
            // $resulta = $stmt->fetch();
            // $merchantId= $resulta['merchantId'];
            // $userId= $resulta['userId'];
            // $response['err']=$enc_pass;
            // $response['error']   = $auth;
            // echo json_encode($response);
            if ($auth == 1) {
                $sql2 = "SELECT CM.`programName` , CM.`programDb` , CM.`programEmail` , UM.`userId` ,UM.`merchantId`, UM.`serialNo` , U.`firstName` ,
                                  U.`lastName` ,U.`default` ,U.`userId`,U.`country_code`, U.`email` ,U.`mobilenetwork`, U.`status` ,U.`email_confirm_flag`,U.`phoneno_confirm_flag`, U.`dateCreated`, U.`sn`
                    FROM `config_user_merchant_info` UM
                      JOIN  `config_user` U ON U.userId=UM.userId 
            JOIN `config_program` CM ON CM.programId=UM.merchantId
  
            WHERE U.`email` =:email OR U.`userId`=:userno
                
                    LIMIT 1";

                $stmt2 = $db->prepare($sql2);
                $stmt2->bindParam(":email", $identity, PDO::PARAM_STR);
                $stmt2->bindParam(":userno", $identity, PDO::PARAM_STR);
                //$stmt2->bindParam(":merchantId", $merchantId,PDO::PARAM_STR);

                $stmt2->execute();
                $errorInfos = $stmt2->errorInfo();
                if (isset($errorInfos[2])) {
                    $res['error']   = true;
                    $res['message'] = $errorInfos[2];
                   
                    $response->getBody()->write(json_encode($res));
                        return $response
                            ->withHeader('content-type', 'application/json')
                            ->withStatus(401);
                }
                $result     = $stmt2->fetch();
                $programDB = $result['programDb'];

                if ($result) {


                    if ($programDB) {
                            $date   = new DateTimeImmutable();
                         

                        $secret_key = $_ENV['JWT_SECRET_KEY'];
                        $issuer_claim = "www.moloyal.com"; // this can be the servername
                        $audience_claim = "www.moloyal.com";
                        $issuedat_claim = $date->getTimestamp(); // issued at
                        $notbefore_claim = $issuedat_claim + 10; //not before in seconds
                        $expire_claim = $date->modify('+40 minutes')->getTimestamp(); // expire time in seconds
                        $token = array(
                            "iss" => $issuer_claim,
                            "aud" => $audience_claim,
                            "iat" => $issuedat_claim,
                            "nbf" => $notbefore_claim,
                            "exp" => $expire_claim,
                            "data" => array(
                                "id" => $result['sn'],
                                "userId" => $result['userId'],
                                "email" => $result['email'],
                                "default" => $result['default'],
                                "programDb" => $result['programDb'],
                                "serialNo"  => $result['serialNo'],
                                "mobilenetwork"   => $result['mobilenetwork']
                            )
                        );



                        $jwt = JWT::encode($token, $secret_key, 'HS256');



                        $data = array(
                            "error" => false,
                            "message" => "Successful login.",
                            "token" => $jwt,
                            "identity" => $identity,
                            "expireAt" => $expire_claim,
                            "data" => array(
                                "id" => $result['sn'],
                                "firstName" => $result['firstName'],
                                "email" => $result['lastName'],
                                "email_confirm_flag" => $result['email_confirm_flag'],
                                "phoneno_confirm_flag" => $result['phoneno_confirm_flag'],
                                "phoneno" => $result['userId'],
                                "country_code" => $result['country_code'],
                                "email" => $result['email'],
                                "default" => $result['default'],
                                "programDb" => $result['programDb'],
                                "serialNo"  => $result['serialNo'],
                                "mobilenetwork" => $result['mobilenetwork']
                            )
                        );
                        //return $data;

                        // callOutput($request, $response, 200, $data);
                        $response->getBody()->write(json_encode($data));
                        return $response
                            ->withHeader('content-type', 'application/json')
                            ->withStatus(200);
                    } else {


                        $err = array("message" => "Login failed1.", "error" => true);
                    }
                } else {

                    $err =  array("message" => "Login failed2.", "error" => true);
                }
            } else {

                $err = array("message" => "Login failed3. not verified", "error" => true);
            }

            //callOutput($request, $response, 401, $err);
            $response->getBody()->write(json_encode($err));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
        } else {
            http_response_code(401);
           $err=array("message" => "Login failed4.", "error" => true);

            $response->getBody()->write(json_encode($err));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
        }



        $db  = null;
        $db2 = null;
    } catch (PDOException $e) {
        $error = array(
            "message" => $e->getMessage()
        );

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});


//Setup customer pin
$app->post('/customer/setup_pin', function (Request $req, Response $res) {
    

    $conn = new Db();
    $db = $conn->connect();

    $response   = array();
    $lastResetDate  = date('Y-m-d H:i:s');
    // $agentid       = $request->agentid;
    // $pin      = $request->pin;

    $data = $req->getParsedBody();
    $pin = $data["pin"];
   

    
    if ($pin == '') {
        $response['error']   = true;
        $response['message'] = 'no value supplied for pin';
        $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    } else {

        $jwt = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

        $arr = explode(" ", $authHeader);



        $jwt = $arr[1];
        $result = validateJWT($jwt);

        if ($result['validate_flag'] == 1) {



            /*
     $customerid='08090963549';
     
      $pin =2345;
     */
            try {

                $token_email = $result['token_value']->data->email;
                //echo $token_email;

                $token_id = $result['token_value']->data->id;
                //echo $token_id;

                $token_userid = $result['token_value']->data->userId;
                //echo $token_userid;




                $pin    = password_hash($pin, PASSWORD_BCRYPT);
                $updateUserquery = "UPDATE `config_user` SET `pin`= ? WHERE `userId` = ? or `email` = ? ";
                // $updateUser =  mysqli_query($con, $updateUserquery);

                $stmt = $db->prepare($updateUserquery);
                $stmt->bindParam(1, $pin);
                $stmt->bindParam(2, $token_userid);
                $stmt->bindParam(3, $token_email);

                $result = $stmt->execute();

                $errorInfo = $stmt->errorInfo();

                if (isset($errorInfo[2])) {
                    $response['error']   = true;
                    $response['message'] = $errorInfo[2];
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
                }
                if ($result) {
                    $response['error'] = false;
                    $response['message'] = 'Pin has been updated successfully';
                    $response['source'] = 'Pin';
                    $response['pinbool'] = true;
                    $response['response'] = 'You have successfully completed your account activation.';
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
                }
            } catch (PDOException $e) {
                $response['error']   = true;
                $response['message'] = $e->getMessage();
                $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
            }
        } else {
            
            $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
        }
    }
});


//verify customer pin
$app->post('/customer/verifypin', function (Request $req, Response $res) {

    $response   = array();
    $conn = new Db();
    $db = $conn->connect();






    $data = $req->getParsedBody();
    $pin = $data["pin"];
   

    
    if ($pin == '') {
        $response['error']   = true;
        $response['message'] = 'no value supplied for pin ';
        $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    } else {
        // $data = json_decode(file_get_contents("php://input"));
        // $firstname    = $data->test;

        $jwt = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

        $arr = explode(" ", $authHeader);


        // echo json_encode(array(
        //     "message" => "sd" .$arr[1],
        //     "error" => $password
        // ));

        $jwt = $arr[1];
        $result = validateJWT($jwt);
        //var_dump($result['validate_flag']);


        // echo json_decode($result)->message;
        if ($result['validate_flag'] == 1) {

            try {

                $token_email = $result['token_value']->data->email;
                //echo $token_email;

                $token_id = $result['token_value']->data->id;
                //echo $token_id;

                $token_userid = $result['token_value']->data->userId;
                //echo $token_userid;


                //do my code
                $updateUserquery = "SELECT `pin` FROM `config_user` WHERE  `userId` = ? or `email` = ? ";

                $stmt = $db->prepare($updateUserquery);
                $stmt->bindParam(1, $token_userid);
                $stmt->bindParam(2, $token_email);



                $stmt->execute();
                $result = $stmt->fetch();



                //echo json_encode($response);
                $errorInfo = $stmt->errorInfo();

                if (isset($errorInfo[2])) {
                    $response['error']   = true;
                    $response['message'] = $errorInfo[2];
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
                } else {

                    $enc_pin = $result['pin'];
                    if ($enc_pin == '') {
                        $response['error']   = true;
                        $response['message'] = 'no pin value in database';
                        $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
                    } else {
                        $auth = password_verify($pin, $enc_pin);
                        if ($auth == '1') {



                            $response['verified'] = 1;
                            $response['message'] = 'pin verified successfully';
                            $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
                        } else {
                            $response['verified'] = 0;
                            $response['message'] = 'pin verification failed';
                            $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
                        }
                        
                    }
                }
            } catch (PDOException $e) {
                $response['error']   = true;
                $response['message'] = $e->getMessage();
                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(500);
            }
        } else {
            $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
        }
    }
});


// customer forgot pin
$app->post('/customer/forgotpin',function (Request $req, Response $res){

    $response = array();
    $conn = new Db();
    $db = $conn->connect();


    $rando = random();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;

            $sqlsa1 = "SELECT  `firstname`,  `lastname`, `email`,`userId` FROM `config_user` WHERE sn='$token_id'";

            $stmtb1 = $db->prepare($sqlsa1);
            // $stmtb1->bindParam("custid", $userid);

            $stmtb1->execute();
            $resultsa1 = $stmtb1->fetch();
            $email = $resultsa1['email'];
            $phone = $resultsa1['userId'];
            $name = $resultsa1['firstName'] . ' ' . $resultsa1['lastName'];

            
            //$response['errors']   = $email;
            //$response['errors1']   = $userid;
            $from    = "noreply@moloyal.com";
            $to      = $email;
            $msg1      = 'We received a forgot pin request for your MoLoyal account.  Your new temporary token is ' . $rando . ' <br>kindly use reset to a new pin. <br>If you did not request a pin reset assistance, you can let us know here:
              support@moloyal.com.
              ';

            $msg = '<tr>
                      <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                              <h4 style="color:#000;"> Dear ' . $name . ',</h4>
                          <p style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; color: #000000; text-align: left;">
   
                 
        ' . $msg1 . '
  
        </p>
                      </td>
                  </tr>';




            $subject   = "MoLoyal Forgot Pin";

            $type = "Moloyal forgot pin";
            $mi = sendEmail($from, $to, $msg, $subject, $type);
            $kk = $mi;
            $def = '0';
            $smsmsg     = 'Your forgot pin request is received. Your new temporary token is \n' . $rando . '\nPlease use to reset';

            //$whatsappchk=sendWhatsApp($phone,$smsmsg);
            $smscheck = sendSMS($phone, $smsmsg);
            
            if ($kk == 1) {

                $sql2 = "Update config_user SET `password`=:rand, `default`=:def WHERE `email`=:userid ";
                $stmt2 = $db->prepare($sql2);

                $stmt2->bindParam(":userid", $email, PDO::PARAM_STR);
                // $stmt2->bindParam(":email", $email, PDO::PARAM_STR);
                $stmt2->bindParam(":def", $def, PDO::PARAM_STR);

                $stmt2->bindParam(":rand", $rando, PDO::PARAM_STR);




                $errorInfos = $stmt2->errorInfo();
                if (isset($errorInfos[2])) {
                    $response['error']   = true;
                    $response['message'] = $errorInfos[2];
                    $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(401);
                }

                $response['error']   = false;
                $response['message'] = "A temporary token has been sent to your email and phone number";
                $response['code'] = $rando;
                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(200);
            } else {
                $response['error']   = true;
                $response['message'] = "There was an error sending  the email";
                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(401);
            }
            //$response= $stmt2->fetch();


           
        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(500);
        }
    } else {
        $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    }
});


// customer forgot password
$app->post('/customer/forgotpass', function (Request $req, Response $res) {

    $response = array();
    $conn = new Db();
    $db = $conn->connect();


    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;

            $rando = random();

            //$randompwd = md5($rando);
            $randompwd    = password_hash($rando, PASSWORD_BCRYPT);
            // $userid="051125927";

            $def = '0';

            $sqlsa1 = "SELECT  `firstname`,  `lastname`, `email` FROM `config_user` WHERE userId='$token_userid'";

            $stmtb1 = $db->prepare($sqlsa1);
            // $stmtb1->bindParam("custid", $userid);

            $stmtb1->execute();
            $resultsa1 = $stmtb1->fetch();
            $email = $resultsa1['email'];
            $name = $resultsa1['firstname'] . ' ' . $resultsa1['lastname'];
            //$response['errors']   = $email;
            //$response['errors1']   = $userid;
            $from    = "noreply@moloyal.com";
            $to      = $email;
            $msg1      = 'We received a password reset request for your MoLoyal account.  Your new temporary token is ' . $rando . ' <br>kindly use to reset to a new password. <br>If you did not request a password assistance, you can let us know here:
            support@moloyal.com.
            <br>
  
            ';

            $msg = '<tr>
                     <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                             <h4 style="color:#000;"> Dear ' . $name . ',</h4>
                         <p style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; color: #000000; text-align: left;">
  
                
    ' . $msg1 . '
  
    </p>
                      </td>
                  </tr>';




            $subject   = "MoLoyal Forgot Password";

            $type = "MoLoyal forgot password";
            $mi = sendEmail($from, $to, $msg, $subject, $type);
            $kk = $mi;
            $phone = $token_userid;
            $smsmsg     = 'Your forgot login details request is received. Your new temporary token is \n ' . $rando . '\nPlease use to login';

            //$whatsappchk=sendWhatsApp($phone,$smsmsg);
            $smscheck = sendSMS($phone, $smsmsg);

            if ($kk == 1) {


                $sql2 = "Update config_user SET `password`=:rand, `default`=:def WHERE `email`=:userid ";
                $stmt2 = $db->prepare($sql2);

                $stmt2->bindParam(":userid", $email, PDO::PARAM_STR);
                // $stmt2->bindParam(":email", $email, PDO::PARAM_STR);
                $stmt2->bindParam(":def", $def, PDO::PARAM_STR);

                $stmt2->bindParam(":rand", $randompwd, PDO::PARAM_STR);




                $errorInfos = $stmt2->errorInfo();
                if (isset($errorInfos[2])) {
                    $response['error']   = true;
                    $response['message'] = $errorInfos[2];
                    $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(401);
                }

                $response['error']   = false;
                $response['message'] = "A temporary password has been sent to your email and phone number";
                $response['code'] = $rando;
                $response['phone'] = $phone;

                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(200);
            } else {
                $response['error']   = true;
                $response['message'] = "There was an error sending  the email";
                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(401);
            }
            //$response= $stmt2->fetch();


           
        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
        }
    } else {
        $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    }
});


// customer reset password
$app->post('/customer/resetpass', function (Request $req, Response $res) {

    $response = array();
    $conn = new Db();
    $db = $conn->connect();





    try {


        //echo $token_userid;
        $data = $req->getParsedBody();
    $identity = $data["identity"];
    $password = $data["password"];
   

    
        
            if ($identity == null || $identity == '') {
                $response['error']   = true;
                $response['message'] = "phone or email is required";

                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(401);
            }
       


       

        if ($password == null || $password == '') {
            
                $response['error']   = true;
                $response['message'] = "password is required";

                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(401);
           
        }

        $encpwd = password_hash($password, PASSWORD_BCRYPT);
        $def       = '0';


        $sqlsa1 = "SELECT `sn`, `userId`, `BVN_num`, `firstName`, `mname`, `lastName`, `email`, `mobilenetwork`, `lastResetDate`, `pic`, `locked` FROM `config_user` WHERE `userId`=:userid OR `email`=:email";

        $stmtb1 = $db->prepare($sqlsa1);

        $stmtb1->bindParam(":userid", $identity, PDO::PARAM_STR);
        $stmtb1->bindParam(":email", $identity, PDO::PARAM_STR);


        $stmtb1->execute();
        $resultsa1 = $stmtb1->fetch();
        $email = $resultsa1['email'];
        $name = $resultsa1['firstName'] . ' ' . $resultsa1['lastName'];

        $from    = "noreply@moloyal.com";
        $to      = $email;
        $msg1       = 'You have requested for a password reset on Moloyal. <br> If you did not initiate this request, Please contact the admin';

        $msg = '<tr>
                            <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                                    <h4 style="color:#000;"> Dear, ' . $name . '</h4>
                                <p style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; color: #000000; text-align: center;">
         
                       
            ' . $msg1 . '
    
        </p>
                              </td>
                          </tr>';



        $subject   = "MoLoyal Password Reset";

        $sql2 = "Update config_user SET `password`=:encpwd, `default`=:def WHERE  `email`=:email OR `userId`=:userid";
        $type = "Moloyal reset password";
        if (isIdentifierExist($identity)) {
            sendEmail($from, $to, $msg, $subject, $type);


            $stmt2 = $db->prepare($sql2);
            $stmt2->bindParam(":encpwd", $encpwd, PDO::PARAM_STR);

            $stmt2->bindParam(":def", $def, PDO::PARAM_STR);

            $stmt2->bindParam(":userid", $identity, PDO::PARAM_STR);
            $stmt2->bindParam(":email", $identity, PDO::PARAM_STR);
            $rr = $stmt2->execute();

            //$stmt2->bindParam(":merchantId", $merchantId,PDO::PARAM_STR);


            $errorInfos = $stmt2->errorInfo();
            if (isset($errorInfos[2])) {
                $response['error']   = true;
                $response['message'] = $errorInfos[2];
                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(401);
            }
            if ($rr) {
                $response['error']   = false;
                $response['message'] = "Your password reset is successful";
                $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(200);
            } else {
                $response['error']   = true;
                $response['message'] = "There was an error contacting the server";
                $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(401);
            }
            //$response= $stmt2->fetch();
           
        } else {

            $response['error']   = true;
            $response['message'] = "Kindly supply customer information";
            $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(401);
        }
    } catch (PDOException $e) {
        $response['error']   = true;
        $response['message'] = $e->getMessage();
        $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(401);
    }
});



$app->post('/paystack/initialise_transaction', function (Request $req, Response $res) {
    $response = array();
    $conn = new Db();
    $db = $conn->connect();

    $data = $req->getParsedBody();
    $amount = $data["amount"];
    $currency = $data["currency"];
  

    $refs= getTransactionRef();
    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] != 1) {
       
        $res->getBody()->write(json_encode($result));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
    } else {
        
    

        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;

             $url = "https://api.paystack.co/transaction/initialize";

            $paystack_secret_key = $_ENV['PAYSTACK_SECRET_KEY'];
            $paystack_callback_url = $_ENV['PAYSTACK_CALLBACK_URL'];
            $curl = curl_init();

            

           $fields=array(
            "email" => $token_email,
            "currency" => $currency,
            "amount" => $amount,
            "reference"=>$refs,
            "callback_url"=> $paystack_callback_url,
              );

              $json_fields=json_encode($fields);
            curl_setopt_array($curl, array(
              CURLOPT_URL => $url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
               CURLOPT_SSL_VERIFYPEER=> false,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => $json_fields,
              CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$paystack_secret_key,
                'Content-Type: application/json'
              ),
            ));
            
            $resulta = curl_exec($curl);
            
            curl_close($curl);
           //echo $response;
            


            
                if($resulta === "false"){
                    $dd= curl_error($curl);
                    $res->getBody()->write(json_encode($dd));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(401);
                }else{
                    

            $res->getBody()->write($resulta);
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);
        
                }



        }catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    
    }
    

    
});




$app->get('/paystack/verify_transaction', function (Request $req, Response $response) {
      

    $conn = new Db();
    $db = $conn->connect();

    try{
        $paystack_secret_key = $_ENV['PAYSTACK_SECRET_KEY'];
        $reference =$req->getQueryParams()['reference'] ?? null;
    $save_card =$req->getQueryParams()['savecard'] ?? null;
         //$ref1= json_decode($params);
         
         //$ref=$ref1->reference;
        
         $curl = curl_init();
          
          curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/".$reference,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
              "Authorization: Bearer $paystack_secret_key",
              "Cache-Control: no-cache",
            ),
          ));
          
          $resp = curl_exec($curl);
          $err = curl_error($curl);
        
          curl_close($curl);
          
          
           if ($err) {
             $response->getBody()->write(json_encode($err));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
          } else {
 echo $check =$resp;
             $check = json_decode($resp)->data->status;
            if($check === "success"){
            $refss = json_decode($resp)->data->reference;
               
                $customer_json_fields = json_decode($resp)->data->customer;
                $cust_email= $customer_json_fields->email;
               
                //select email if available
                //then update recurring_payment with authotisation

               


                    $json_fields = json_decode($resp)->data->authorization;
                    $auth_code= $json_fields->authorization_code;
                       $bin= $json_fields->bin;
                        $last4= $json_fields->last4;
                         $exp_month= $json_fields->exp_month;
                          $exp_year= $json_fields->exp_year;
                           $channel= $json_fields->channel;
                            $bank= $json_fields->bank;
                            $card_type= $json_fields->card_type;
                            $country_code= $json_fields->country_code;
                            $reusable= $json_fields->reusable;
                            $signature= $json_fields->signature;
                            $account_name= $json_fields->account_name;
    
    

                    if($save_card === "true" ){


                        $sqlv = "SELECT * FROM `recurring_payment` WHERE `email_address`=:email and `exp_month`=:exp_month and `exp_year`=:exp_year and `bin`=:bin";
                        $stmta = $db->prepare($sqlv);
                        $stmta->bindParam(":email", $cust_email, PDO::PARAM_STR);
                        $stmta->bindParam(":exp_month", $exp_month, PDO::PARAM_STR);
                        $stmta->bindParam(":exp_year", $exp_year, PDO::PARAM_STR);
                        $stmta->bindParam(":bin", $bin, PDO::PARAM_STR);
                        
                       
                          $rra= $stmta->execute();
        
                          $resultsa = $stmta->fetch();
                         $num = $stmta->rowCount();
        
                          if ($num == 0) {
                           //echo "got2"; 
                                  
                    $enc_last4=encrypt_decrypt('encrypt',$last4);
                    $enc_signature=encrypt_decrypt('encrypt',$signature);
                
                    $enc_card_type=encrypt_decrypt('encrypt',$card_type);
                
                    $enc_auth_code=encrypt_decrypt('encrypt',$auth_code);
                
                    // $authorization_code =encrypt_decrypt('decrypt',$rs['authorization_code']);
                    // $last4 =encrypt_decrypt('decrypt',$rs['last4']);

                    $sqla = "INSERT INTO `recurring_payment`( `email_address`, `authorization_code`, 
                    `card_type`, `last4`,
                     `exp_month`, `exp_year`, `bin`, `acct_name`,`bank`, `channel`, `signature`, `reusable`, 
                     `country_code`, `reference`, `status`) VALUES
                    (:email,:authcode,:cardtype,:last4,:expmonth,:expyr,:bin,:account_name, :bank,
                    :channel,:sign,:reusable,:countrycode,:ref, 1)";
                                 
              
                                  $stmt = $db->prepare($sqla);
                                  $stmt->bindParam(":authcode", $enc_auth_code, PDO::PARAM_STR);
                                  $stmt->bindParam(":cardtype", $enc_card_type, PDO::PARAM_STR);
                                  $stmt->bindParam(":last4", $enc_last4, PDO::PARAM_STR);
                                  $stmt->bindParam(":expmonth", $exp_month, PDO::PARAM_STR);
                                  $stmt->bindParam(":expyr", $exp_year, PDO::PARAM_STR);
                                  $stmt->bindParam(":bin", $bin, PDO::PARAM_STR);
                                  $stmt->bindParam(":account_name", $account_name, PDO::PARAM_STR);
                                  $stmt->bindParam(":bank", $bank, PDO::PARAM_STR);
                                  $stmt->bindParam(":channel", $channel, PDO::PARAM_STR);
                                  $stmt->bindParam(":sign", $enc_signature, PDO::PARAM_STR);
                                  $stmt->bindParam(":reusable", $reusable, PDO::PARAM_STR);
                                         $stmt->bindParam(":countrycode", $country_code, PDO::PARAM_STR);
                                  $stmt->bindParam(":email", $cust_email, PDO::PARAM_STR);
                                  $stmt->bindParam(":ref", $refss, PDO::PARAM_STR);
                                  
                                  $rr= $stmt->execute();
                    
                                }
                            }
          
                        


           


            $response->getBody()->write(json_encode('payment validated succesfully'));
            return $response->withHeader('content-type', 'application/json')
                        ->withStatus(200);
        
            
          }else{
            $response->getBody()->write('payment not validated');
            return $response->withHeader('content-type', 'application/json')
                        ->withStatus(301);

          }
        
        
    }
        } catch (PDOException $e) {
            $res['error']   = true;
            $res['message'] = $e->getMessage();
            $response->getBody()->write(json_encode($res));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }   
            
       
    
});


$app->post('/paystack/validate_banktransfer', function (Request $req, Response $res) {
      

    $conn = new Db();
    $db = $conn->connect();

  
      
          $payload=$req->getParsedBody();
    $isValidTransferRequest= validateTransferToBankRequest($payload);
// $res->getBody()->write(json_encode($isValidTransferRequest));
//                 return $res
//                 ->withHeader('content-type', 'application/json')
//                 ->withStatus(200);
                
                
    if( $isValidTransferRequest) {

      $res->getBody()->write('');
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);
      }
    
      $res->getBody()->write('');
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(400);
   
                   
    
});
//Customer Withdrawal

$app->post('/customer/withdrawal', function (Request $req, Response $res) {

    $response = array();
    $conn = new Db();
    $dbs = $conn->connect();
    $db = $conn->connect();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ",
        $authHeader
    );



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] != 1) {

        $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    } else {


        try {


            $email = $result['token_value']->data->email;
            //echo $token_email;

            $customerId = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;

            $data = $req->getParsedBody();
            $planId = $data["planId"];

            $transAmount = $data["transAmount"];
            $accountId = $data["accountId"];
           
            
            $otp = $data["otp"];
            
            


            if ($planId == null || $planId == '') {
                $response['error']   = true;
                $response['message'] = "plan id is required";
                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }

            if ($accountId == null || $accountId == '') {
                $response['error']   = true;
                $response['message'] = "account type id is required";

                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }

            
            if ($transAmount == null || $transAmount == '') {
                $response['error']   = true;
                $response['message'] = "transaction amount is required";

                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }
            if ($otp == null || $otp == '') {
                $response['error']   = true;
                $response['message'] = "transaction PIN is required";

                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }

            $getUserquery = "SELECT * FROM `config_user` WHERE `sn` = '$customerId' ";
            $stmtpin = $dbs->prepare($getUserquery);
                    $stmtpin->execute();
             $resultss = $stmtpin->fetch();
              $enc_pin= $resultss['pin'];

            $auth = password_verify($otp, $enc_pin);
            if($auth!='1'){
                $response['error']=true;
                $response['message']="Invalid Customer PIN or PIN expired";
                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(403);

            }else{
                $sqlscust = "SELECT * FROM `config_customer_account_info` WHERE customerId=:custid";

                $stmtcust = $dbs->prepare($sqlscust);
                $stmtcust->bindParam("custid", $customerId);

                $stmtcust->execute();
                $resultcust = $stmtcust->fetch();
                $accountNo = $resultcust['account_num'];
                $bank_code = $resultcust['bank_code'];
                $bank_name = $resultcust['bank_name'];
                $bank_account_no = $resultcust['bank_account_no'];
                $bank_account_name = $resultcust['bank_account_name'];
                $currency = $resultcust['currency'];
                $type='nuban';
              
                 $bank_details = array(
                        'bank_code' => $bank_code,
                        'type' => $type,
                         'account_number' => $bank_account_no,
                         'currency' => $currency,

                       
                        );


                $refs = getTransactionRef();
                $transtype = 'W';



                //$passw = md5($request->password);
                $work = str_pad(8, 2, '0', STR_PAD_LEFT);

                $dateCreated = date("Y-m-d");

                $time = date("H:i:s");

                $ip = $_SERVER['REMOTE_ADDR'];


                $sqlsssa = "SELECT * FROM `savings_plan` WHERE sn=:pid";

                $stmtbsss = $dbs->prepare($sqlsssa);
                $stmtbsss->bindParam("pid", $planId);

                $stmtbsss->execute();
                $resultsass = $stmtbsss->fetch();
                $plans_id = $resultsass['sn'];
                $plan_name = $resultsass['plan_name'];
                $plan_amount = $resultsass['plan_amount'];
                $days = $resultsass['days'];
                $percentage_commission = $resultsass['percentage_commission'];
                $money_commission = $resultsass['flat_commision'];
                $billing_type = $resultsass['billing_type'];



                $sqlsa = "SELECT  `account_code`, `account_name`, `minimum_bal`, `desc` FROM `mosave_account_type` WHERE sn=:actid";

                $stmtb = $dbs->prepare($sqlsa);
                $stmtb->bindParam("actid", $accountId);

                $stmtb->execute();
                $resultsa = $stmtb->fetch();

                //$response=$email;
                if (!$resultsa) {
                    $response['error'] = true;
                    $response['message'] = "Cannot find Customer account type";
                    $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(401);
                } else {


                    $accountCode = $resultsa['account_code'];
                    $accountType = $resultsa['account_name'];
                    $bal = $resultsa['minimum_bal'];

                    $walletqrys = "SELECT `account_bal` as wBalances FROM `mosave_wallet` WHERE  customerId=:custid and accountId=:actid ";

                    $waldb = $conn->connect();
                    $walstmts = $waldb->prepare($walletqrys);

                    $walstmts->bindParam(":custid", $customerId, PDO::PARAM_STR);
                    $walstmts->bindParam(":actid", $accountId, PDO::PARAM_STR);

                    $walstmts->execute();
                    $reds = $walstmts->fetch();


                    $wallet_balances = $reds['wBalances'];

                    //get transactions done between last charge date and today
                    $sqlupd = "SELECT * FROM `mosave_customer_savings_plan` where cust_id=:cust_id and plan_id=:pid";

                    $planwaldb = $conn->connect();
                    $plastmtss = $planwaldb->prepare($sqlupd);



                    $plastmtss->bindParam(":cust_id", $customerId, PDO::PARAM_STR);
                    $plastmtss->bindParam(":pid", $planId, PDO::PARAM_STR);

                    $plastmtss->execute();

                    $redtmt = $plastmtss->fetch();

                    $next_charge_date = $redtmt['next_charge_date'];

                    $last_charge_date = $redtmt['last_charge_date'];

                    $last_charge_plus1day = date('Y-m-d', strtotime($last_charge_date . " +1 days"));
                    $today = date("Y-m-d");
                    $sqsa = "SELECT * FROM `mosave_savingtransaction` WHERE customerId=:cid and planId=:pid and transType='S' AND `transDate` BETWEEN '$last_charge_plus1day' AND '$today'";
       
        $sdss = $dbs->prepare($sqsa);  
        $sdss->bindParam("cid", $customerId);
        $sdss->bindParam("pid", $planId);
        
                $sdss->execute();
                $resultbss = $sdss->fetch();










                    $walletqry = "SELECT `available_bal` as wBalance, `account_bal`  FROM `mosave_plan_wallet` WHERE  customerId=:custid and accountId=:actid and plan_id=:pid ";

                    $waldb = $conn->connect();
                    $walstmt = $waldb->prepare($walletqry);

                    $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                    $walstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);
                    $walstmt->bindParam(":pid", $planId, PDO::PARAM_STR);

                    $walstmt->execute();
                    $red = $walstmt->fetch();


                    $wBalance = $red['wBalance'];
                    $account_bal = $red['account_bal'];
                    if ($wBalance == "") {
                        //insert into wallet if account has no balance already
                        $response['error'] = true;
                        $response['message'] = "No balance in the account";
                    } else {
                        //update wallet if account has balance already

                        //$wBalance=$red['wBalance']==""?0.00:$red['wBalance'];
                        $amts = $transAmount;
                        $amt = $transAmount * (-1);
                        $newBalance = $wBalance + $amt;
                        $newBalancecheck = $newBalance * (-1);

                        $mosave_wal = $wallet_balances - $transAmount;
                        $ac_bal = $account_bal - $transAmount;
                        // $response['t']=$newBalancecheck;
                        //$response['n']=$newBalance;
                        //$response['j']=$bal;



                        if ($amts > $wBalance) {

                            $response['error'] = true;
                            $response['message'] = "Insufficient funds";
                        } elseif (($sdss->rowCount() != 0) && $wBalance == $transAmount) {


                            $response['error'] = true;
                            $response['message'] = "customer can not withdraw total amount, " . $money_commission . " monthly commission needs to be deducted";
                        }

                        // elseif($newBalance<$bal || $newBalancecheck>$bal  ){ 

                        //      $response['error']=true;
                        //     $response['message']="Minimum account balance cannot be withdrawn";
                        //   }

                        else {

                            $available_bal = $newBalance - $bal;

                            //do Paystack transfer to bank
                            $transferResult=paystackTransferToBank($transAmount,$bank_details);
                             $t=json_decode($transferResult);
           
           
           $transfer_code=$t->data->transfer_code;
                    $amtInKobo=$t->data->amount;
                    $transAmount= $amtInKobo/100;
                    $status=$t->data->status;
                   $refs=$t->data->reference;
                   $reason=$t->data->reason; 
                  
                  
 if($status!== "received" ){
     
      //$user->id = $db->lastInsertId();
                    $dbs = null;
                    $db = null;
                   $res->getBody()->write(json_encode($t));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);
                
 }else{

     //Insert into transfer table
    $sqltransfer = "INSERT INTO `mosave_banktransfer`(`customerId`, `accountName`, `accountNo`, `bank`, 
    `transAmount`, `transType`, `transref`, `status`, `des`, `createdDate`,  `time`, `ip`)
     VALUES (:customerId,:acct_name,:accountNo,:bank_name, :transAmount,:transtype,:transref,
     :stat, :descr,:datecreated,:time,:ip)";

$status='Pending';
$stmttransfer = $db->prepare($sqltransfer);

$stmttransfer->bindParam(":customerId", $customerId, PDO::PARAM_STR);

$stmttransfer->bindParam(":acct_name", $bank_account_name, PDO::PARAM_STR);
$stmttransfer->bindParam(":accountNo", $bank_account_no, PDO::PARAM_STR);
$stmttransfer->bindParam(":bank_name", $bank_name, PDO::PARAM_STR);
$stmttransfer->bindParam(":transAmount", $transAmount, PDO::PARAM_STR);
$stmttransfer->bindParam(":transtype", $customerId, PDO::PARAM_STR);
$stmttransfer->bindParam(":transref", $refs, PDO::PARAM_STR);
$stmttransfer->bindParam(":stat", $status, PDO::PARAM_STR);
$stmttransfer->bindParam(":descr", $reason, PDO::PARAM_STR);

$stmttransfer->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
$stmttransfer->bindParam(":time", $time, PDO::PARAM_STR);
$stmttransfer->bindParam(":ip", $ip, PDO::PARAM_STR);
$stmttransfer->execute();
                            $updwalqryplan = "UPDATE `mosave_plan_wallet` SET `account_bal`=:ac_bal, `available_bal`=:newb  WHERE accountId=:actid and customerId=:custid and plan_id=:pid ";
                            $waldbs = $conn->connect();
                            $walstmtplan = $waldbs->prepare($updwalqryplan);
                            $walstmtplan->bindParam(":ac_bal", $ac_bal, PDO::PARAM_STR);
                            $walstmtplan->bindParam(":newb", $newBalance, PDO::PARAM_STR);
                            $walstmtplan->bindParam(":custid", $customerId, PDO::PARAM_STR);
                            $walstmtplan->bindParam(":actid", $accountId, PDO::PARAM_STR);
                            $walstmtplan->bindParam(":pid", $planId, PDO::PARAM_STR);

                            $walstmtplan->execute();

                            $updwalqry = "UPDATE `mosave_wallet` SET `account_bal`='$mosave_wal'  WHERE accountId=:actid and customerId=:custid ";
                            $waldb = $conn->connect();
                            $walstmt = $waldb->prepare($updwalqry);

                            $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                            $walstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);

                            $walstmt->execute();

                            $trans_mode = 'CW';


                            $sql = "INSERT INTO `mosave_savingtransaction`(`customerId`, `agentId`,`accountId`, `planId`, `accountNo`, `transAmount`,`transType`,`transref`,
                                `accountType`, `accountCode`,`trans_mode`,  `transDate`,`time`,`ip`) VALUES (:customerId,:agentId,:actids,:pid,:accountNo,:transAmount,:transtype,:transref,:accountType,:accountCode,:trans_mode,:datecreated,:time,:ip)";

                            $db = $conn->connect();
                            $stmt = $db->prepare($sql);

                            $stmt->bindParam(":customerId", $customerId, PDO::PARAM_STR);
                            $stmt->bindParam(":agentId", $agentId, PDO::PARAM_STR);
                            $stmt->bindParam(":actids", $accountId, PDO::PARAM_STR);
                            $stmt->bindParam(":pid", $planId, PDO::PARAM_STR);
                            $stmt->bindParam(":accountNo", $accountNo, PDO::PARAM_STR);
                            $stmt->bindParam(":transAmount", $transAmount, PDO::PARAM_STR);
                            $stmt->bindParam(":transtype", $transtype, PDO::PARAM_STR);
                            $stmt->bindParam(":transref", $refs, PDO::PARAM_STR);
                            $stmt->bindParam(":trans_mode", $trans_mode, PDO::PARAM_STR);
                            $stmt->bindParam(":accountType", $accountType, PDO::PARAM_STR);
                            $stmt->bindParam(":accountCode", $accountCode, PDO::PARAM_STR);




                            $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                            $stmt->bindParam(":time", $time, PDO::PARAM_STR);
                            $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);

                            //$stmt->execute();
                            $result = $stmt->execute();
                            //$result = $stmt->fetch();

                            if ($result) {



                                $response['error'] = false;
                                $response['message'] = 'Withdrawal successful';
                                $response['source'] = "Withdrawal";
                                $response['agentId'] = $agentId;
                                $response['customerId'] = $customerId;
                                $response['transAmount'] = $transAmount;
                                $response['trxref'] = $refs;
                                $response['plan_name'] = $plan_name;
                                $response['accountId'] = $accountId;
                                $response['accountNo'] = $accountNo;
                                $response['timestamp'] = $dateCreated;
                                $response['time'] = $time;

                                $sqlsa1 = "SELECT `sn`, `userId`, `BVN_num`, `firstName`, `mname`, `lastName`, `email`, `mobilenetwork` FROM `config_user` WHERE sn=:custid";

                                $stmtb1 = $dbs->prepare($sqlsa1);
                                $stmtb1->bindParam("custid", $customerId);

                                $stmtb1->execute();
                                $resultsa1 = $stmtb1->fetch();
                                $phone = $resultsa1['userId'];
                                $email = $resultsa1['email'];
                                $name = $resultsa1['firstName'] . ' ' . $resultsa1['lastName'];

                                $smsmsg     = 'Dear ' . $name . ', there is withdrawal on your account. MoSave Acct: ' . $accountNo . '\nPlan: ' . $plan_name . '\nAmt: ' . $transAmount . ' DR' . '\nNet Bal: ' . $newBalance . '';

                                //$whatsappchk=sendWhatsApp($phone,$smsmsg);
                                $smscheck = sendSMS($phone, $smsmsg);
                                //$response['error']=$smscheck;
                                //$response['message']=$e->getMessage();
                                //echo json_encode($response);
                                $note = "";
                                $from    = "noreply@moloyal.com";
                                $to      = $email;



                                $msg1     = 'NGN ' . $transAmount . ' has been debited from your MoSave Account.<br>
            
                                    <br> <strong><u> Here is what you need to know: </u></strong><br>
                                    Transaction Ref.	:  ' . $refs . '<br>
                                    Account Number	:  ' . $accountNo . '<br>

                                    Account Name	:	' . $name . '<br>
                                    Plan Name	:	' . $plan_name . '<br>
                                    Amount	:	NGN' . $transAmount . '<br>
                                    Note	:	' . $note . '<br>
                                    Value Date	:	' . $dateCreated . '<br>

                                    Time of Transaction	:	' . $time . '<br>


                                    The balance on this account as at  ' . $time . '  are as follows;<br>

                                    Available Balance	:  NGN' . $newBalance . '<br>


                                    ';

                                $msg = '<tr>
                                <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                                <h4 style="color:#000;"> Dear ' . $name . ',</h4>
                                <p style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; color: #000000; text-align: left;">
     
                                                
                                ' . $msg1 . '

                                </p>
                                                        </td>
                                                    </tr>';

                                $subject = "MoLoyal Debit Transaction [ " . $transAmount . " ]";
                                $type    = $name;

                                $emailsent = sendEmail($from, $to, $msg, $subject, $type);
                            } else {
                                $response['error'] = true;
                                $response['message'] = "There was an error contacting the server, please retry";
                            }
                        }
                    }
                        
                    }
                }
            }
                    //$user->id = $db->lastInsertId();
                    $dbs = null;
                    $db = null;
                   $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);


        }catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    
    }
    

    
});

//Customer Savings

$app->post('/customer/savings', function (Request $req, Response $res) {

    $response = array();
    $conn = new Db();
    $dbs = $conn->connect();


    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ",
        $authHeader
    );



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] != 1) {

        $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
    } else {


        try {


            $email = $result['token_value']->data->email;
            //echo $token_email;

            $customerId = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;

            $data = $req->getParsedBody();
            $planId = $data["planId"];

            $transAmount = $data["transAmount"];
            $accountId = $data["accountId"];
           
            $card_id = $data["paymentcard_id"];


            if ($card_id == null || $card_id == '') {
                $response['error']   = true;
                $response['message'] = "id of paymentcard to charge is required";
                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }

            if ($planId == null || $planId == '') {
                $response['error']   = true;
                $response['message'] = "plan id is required";
                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }

            if ($accountId == null || $accountId == '') {
                $response['error']   = true;
                $response['message'] = "account type id is required";

                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }

            
            if ($transAmount == null || $transAmount == '') {
                $response['error']   = true;
                $response['message'] = "transaction amount is required";

                $res->getBody()->write(json_encode($response));
                return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
            }


            //$refs = getTransactionRef();
            $transtype = 'S';


            //  $customerId = 1;
            // $agentId = '72';
            // $transAmount = 2000;
            //  $accountId = 1;
            //   $accountNo=1540000058;
            /*
           
         
         
           $accountId = $_POST['accountId']; */




            //$passw = md5($request->password);
            $work = str_pad(8, 2, '0', STR_PAD_LEFT);

            $dateCreated = date("Y-m-d");

            $time = date("H:i:s");
            $date_time = $dateCreated . ' ' . $time;
            $ip = $_SERVER['REMOTE_ADDR'];
            $tt = '';
            $wal_flag = '';

            $admin_charge = 0;

            $sqlsssa = "SELECT * FROM `savings_plan` WHERE sn=:pid";

            $stmtbsss = $dbs->prepare($sqlsssa);
            $stmtbsss->bindParam("pid", $planId);

            $stmtbsss->execute();
            $resultsass = $stmtbsss->fetch();
            $plans_id = $resultsass['sn'];
            $plan_name = $resultsass['plan_name'];
            $plan_amount = $resultsass['plan_amount'];
            $days = $resultsass['days'];
            $percentage_commission = $resultsass['percentage_commission'];
            $money_commission = $resultsass['flat_commision'];
            $billing_type = $resultsass['billing_type'];



            $sqlscust = "SELECT * FROM `config_customer_account_info` WHERE customerId=:custid";

            $stmtcust = $dbs->prepare($sqlscust);
            $stmtcust->bindParam("custid", $customerId);

            $stmtcust->execute();
            $resultcust = $stmtcust->fetch();
            $accountNo = $resultcust['account_num'];
            
            

            $sqlsngs = "SELECT * FROM `mosave_customer_savings_plan` WHERE cust_id=:cid and plan_id=:pid";

            $stmtgs = $dbs->prepare($sqlsngs);
            $stmtgs->bindParam("cid", $customerId);
            $stmtgs->bindParam("pid", $planId);

            $stmtgs->execute();
            $resustmtgs = $stmtgs->fetch();
            
            if (!$resustmtgs) {
                $response['error'] = true;
                $response['message'] = "cannot find customer savings plan";
                $res->getBody()->write(json_encode($response));
                return $res
                    ->withHeader('content-type', 'application/json')
                    ->withStatus(403);
            } else {

                       

                $maturity_date =  $resustmtgs['maturity_date'];
                $savings_amount =  $resustmtgs['savings_amount'];
                $savings_count =  $resustmtgs['savings_count'];
                $charge_flag =  $resustmtgs['charge_flag'];
                $savings_count_t = $savings_count;
                $charge_flag_t = $charge_flag;
                $sqlsa = "SELECT  `account_code`, `account_name`, `minimum_bal`, `desc` FROM `mosave_account_type` WHERE sn=:actid";

                $stmtb = $dbs->prepare($sqlsa);
                $stmtb->bindParam("actid", $accountId);

                $stmtb->execute();
                $resultsa = $stmtb->fetch();
               
                if (!$resultsa) {
                    $response['error'] = true;
                    $response['message'] = "Cannot find customer account type";
                    $res->getBody()->write(json_encode($response));
                    return $res
                        ->withHeader('content-type', 'application/json')
                        ->withStatus(403);
                } else {

                    $transAmountinKobo=$transAmount*100;
                    //charge card with saving amount here
                    $paymentresult= chargeSavedCard($card_id, $transAmountinKobo, $email);
                   
                    
                     $t=json_decode($paymentresult);
           
           
                    $amtInKobo=$t->data->amount;
                    $transAmount= $amtInKobo/100;
                    $status=$t->data->status;
                   $refs=$t->data->reference;
                   $gateresponse=$t->data->gateway_response;
                   
                    if($status=== "success" && $gateresponse=== "Approved" ){

                        $accountCode = $resultsa['account_code'];
                        $accountType = $resultsa['account_name'];
                        $bal = $resultsa['minimum_bal'];
    
                        //Calcualate Admin commission from here
                        if ($plans_id != 4)
                        {
                            if (($transAmount % $savings_amount) != 0) {
    
                                $response['error'] = true;
                                $response['message'] = "payment must be in multiples of " . $savings_amount;
                                $res->getBody()->write(json_encode($response));
                                return $res
                                    ->withHeader('content-type', 'application/json')
                                    ->withStatus(403);
                            } else {
                                $savings_count_t = $savings_count;
                                $charge_flag_t = $charge_flag;
    
    
                                if ($savings_count == 0 && $charge_flag == 0) 
                                {
    
                                    $sefss = " SELECT `sn`, `merchantName`, `merchantId`, `refer_amount`,`referral` FROM `mosave_settings` WHERE 1";
                                    $sffss = $dbs->prepare($sefss);
                                    $sffss->execute();
                                    $resultsddd = $sffss->fetch();
                                    $referral_amt = $resultsddd['refer_amount'];
                                    $referral = $resultsddd['referral'];
    
    
                                    if ($referral == '1') 
                                    {
                                        $totalairtime = $referral_amt;
                                        $se = "SELECT * from multilevel where child_id='$customerId' and activated=0";
                                        $sff = $dbs->prepare($se);
                                        $sff->execute();
                                        $results = $sff->fetch();
                                        $child_id = $results['child_id'];
                                        $parent_id = $results['parent_id'];
                                        //e.g parent_id= 201500000243, child_id=201500000290
                                        //       $response['child_id']=$child_id;
                                        //       $response['parent_id']=$parent_id;
                                        //   echo json_encode($response);
                                        //   return;
    
    
    
                                        //if referral is available
                                        if ($parent_id != '' && $child_id != '') {
                                            $db5   =  $conn->connect();
    
                                            $sql5 = "SELECT  `userId`,`mobilenetwork`,`sn` FROM	 `config_user` 
                                        WHERE `sn` =:usernos LIMIT 1";
    
                                            $stmt5 = $db5->prepare($sql5);
                                            $stmt5->bindParam(":usernos", $parent_id, PDO::PARAM_STR);
    
                                            $stmt5->execute();
                                            $result     = $stmt5->fetch();
    
                                            $parentPhoneno = $result['userId'];
    
                                            $mobilenetwork = $result['mobilenetwork'];
    
                                            sendAirtime($referral_amt, $parentPhoneno, $mobilenetwork);
    
                                            $sed = "UPDATE `multilevel` SET `activated`=1 where child_id='$customerId' ";
                                            $sffd = $dbs->prepare($sed);
                                            $sffd->execute();
                                        }
                                    }
    
                                    $wal_flag2 = 'check2';
                                    $charge_flag = 0;
                                    //$new_count=$transAmount / $savings_amount;
    
    
                                    $savings_count = $transAmount / $savings_amount;
    
                                    $charge_type = '';
                                    $d = strtotime("+1 Months");
                                    $chargedt = date(
                                        "Y-m-d",
                                        $d
                                    );
                                    $dateCre = date("Y-m-d");
    
                                    $sqlupd = "UPDATE `mosave_customer_savings_plan` SET `savings_count`=:scount, `user_starting_date`=:startdate,`last_charge_date`=:lastchargedt,`next_charge_date`=:chargedt WHERE plan_id=:pid and cust_id=:cid";
                                    $db =  $conn->connect();
                                    $stmtupd = $db->prepare($sqlupd);
    
    
                                    $stmtupd->bindParam(":scount", $savings_count, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":pid", $planId, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":cid", $customerId, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":lastchargedt", $dateCre, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":startdate", $dateCre, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":chargedt", $chargedt, PDO::PARAM_STR);
    
                                    $stmtupd->execute();
                                } else {
    
    
                                    $new_count = $transAmount / $savings_amount;
    
                                    //35=18+5
                                    $savings_count = $savings_count + $new_count;
    
    
                                    $sqlupd = "UPDATE `mosave_customer_savings_plan` SET `savings_count`=:scount WHERE plan_id=:pid and cust_id=:cid";
                                    $db =  $conn->connect();
                                    $stmtupd = $db->prepare($sqlupd);
    
    
                                    $stmtupd->bindParam(":scount", $savings_count, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":pid", $planId, PDO::PARAM_STR);
                                    $stmtupd->bindParam(":cid", $customerId, PDO::PARAM_STR);
    
                                    $stmtupd->execute();
    
                                    $k = $savings_count - ($charge_flag * $days);
                                    // for ($j=0; $j<=$k; $j++){
                                    //  $tt= $j % $days;
    
                                    // if($tt==1){
    
                                    //     $wal_flag='check';
                                    // //increment charge_flag by 1
                                    // $charge_flag=$charge_flag+1;
    
    
                                    // $sqlupd = "UPDATE `mosave_customer_savings_plan` SET `charge_flag`=:cflag WHERE plan_id=:pid and cust_id=:cid";
                                    // $db =  $conn->connect();
                                    //         $stmtupd = $db->prepare($sqlupd);  
    
                                    //         $stmtupd->bindParam(":cflag", $charge_flag,PDO::PARAM_STR);
    
                                    //           $stmtupd->bindParam(":pid", $planId,PDO::PARAM_STR);
                                    //         $stmtupd->bindParam(":cid", $customerId,PDO::PARAM_STR);
    
                                    // $stmtupd->execute();
    
    
                                    // // do an insert into admin_ledger.admin_charge
    
    
                                    //  //do an insert into admin_ledger.admin_charge
                                    //  if($billing_type=='P'){
                                    // $admin_charge= ($savings_amount * $days * $percentage_commission)/100;
                                    // $charge_type='P';
                                    // }
                                    //  if($billing_type=='F'){
                                    //   $admin_charge= $money_commission; 
                                    //   $charge_type='F';
                                    //  }
    
    
                                    // $transtype='commission';
                                    // $trans_mode='AC';
    
                                    // $sqla = "INSERT INTO `mosave_savingtransaction`(`customerId`, `agentId`,`accountId`,`planId`, `accountNo`, `transAmount`,`transType`,`transref`,
                                    //                     `accountType`, `accountCode`,`trans_mode`,  `transDate`,`time`,`ip`) VALUES (:customerId,:agentId,:actids,:pid,:accountNo,:transAmount,:transtype,:transref,:accountType,:accountCode,:trans_mode,:datecreated,:time,:ip)";
    
                                    // $db =  $conn->connect();
                                    //         $stmta = $db->prepare($sqla);  
    
                                    //         $stmta->bindParam(":customerId", $customerId,PDO::PARAM_STR);
                                    //          $stmta->bindParam(":agentId", $agentId,PDO::PARAM_STR);
                                    //           $stmta->bindParam(":actids", $accountId,PDO::PARAM_STR);
                                    //           $stmta->bindParam(":pid", $planId,PDO::PARAM_STR);
                                    //          $stmta->bindParam(":accountNo", $accountNo,PDO::PARAM_STR);
                                    //          $stmta->bindParam(":transAmount", $admin_charge,PDO::PARAM_STR);
                                    //           $stmta->bindParam(":transtype", $transtype,PDO::PARAM_STR);
                                    //           $stmta->bindParam(":trans_mode", $trans_mode,PDO::PARAM_STR);
                                    //           $stmta->bindParam(":transref", $refs,PDO::PARAM_STR);
                                    //         $stmta->bindParam(":accountType", $accountType,PDO::PARAM_STR);
                                    //          $stmta->bindParam(":accountCode", $accountCode,PDO::PARAM_STR);
    
    
    
    
                                    //       $stmta->bindParam(":datecreated", $dateCreated,PDO::PARAM_STR);
                                    //       $stmta->bindParam(":time", $time,PDO::PARAM_STR);
                                    //       $stmta->bindParam(":ip", $ip,PDO::PARAM_STR);
    
                                    //                 //$stmt->execute();
                                    //                 $resultas=$stmta->execute();
    
    
                                    // $sqlwa="INSERT INTO `mosave_admin_ledger`( `plan_id`, `cust_id`, `admin_charge`, `date`, `charge_type`) VALUES (:pId,:cId,:adcharge,:datecreated,:charge_type)";
    
                                    // $db =  $conn->connect();
                                    //         $stmtsqlwa = $db->prepare($sqlwa);  
    
                                    //         $stmtsqlwa->bindParam(":cId", $customerId,PDO::PARAM_STR);
                                    //          $stmtsqlwa->bindParam(":pId", $planId,PDO::PARAM_STR);
                                    //           $stmtsqlwa->bindParam(":adcharge", $admin_charge,PDO::PARAM_STR);
                                    //           $stmtsqlwa->bindParam(":datecreated", $date_time,PDO::PARAM_STR);
                                    //          $stmtsqlwa->bindParam(":charge_type", $charge_type,PDO::PARAM_STR);
    
    
    
    
    
    
    
                                    //                 //$stmt->execute();
                                    //                 $resultsqf=$stmtsqlwa->execute(); 
    
                                    // }
                                    // }
    
                                }
                            }
                        }
    
    
                        if ($plans_id == 4) 
                        {
    
                            // $savings_count_t=$savings_count;
                            // $charge_flag_t=$charge_flag;
    
    
                            if ($savings_count == 0 && $charge_flag == 0) 
                            {
    
                                $sefss = " SELECT `sn`, `merchantName`, `merchantId`, `refer_amount`,`referral` FROM `mosave_settings` WHERE 1";
                                $sffss = $dbs->prepare($sefss);
                                $sffss->execute();
                                $resultsddd = $sffss->fetch();
                                $referral_amt = $resultsddd['refer_amount'];
                                $referral = $resultsddd['referral'];
    
                                if ($referral == '1') {
    
    
    
    
                                    $totalairtime = $referral_amt;
                                    $se = "SELECT * from multilevel where child_id='$customerId' and activated=0";
                                    $sff = $dbs->prepare($se);
                                    $sff->execute();
                                    $results = $sff->fetch();
                                    $child_id = $results['child_id'];
                                    $parent_id = $results['parent_id'];
                                    //e.g parent_id= 201500000243, child_id=201500000290
    
                                    //if referral is available
                                    if ($parent_id != '' && $child_id != '') 
                                    {
                                        $db5   =  $conn->connect();
    
                                        $sql5 = "SELECT  `userId`,`mobilenetwork`,`sn` FROM	 `config_user` 
                                            WHERE `sn` =:usernos LIMIT 1";
    
                                        $stmt5 = $db5->prepare($sql5);
                                        $stmt5->bindParam(":usernos", $parent_id, PDO::PARAM_STR);
    
                                        $stmt5->execute();
                                        $result     = $stmt5->fetch();
    
                                        $parentPhoneno = $result['userId'];
    
                                        $mobilenetwork = $result['mobilenetwork'];
    
                                        sendAirtime($referral_amt, $parentPhoneno, $mobilenetwork);
    
    
    
    
                                        $dateCreated = date("Y-m-d g:i:s");
                                        $transtime = date("g:i:s");
    
                                        //$get_me2=mysqli_query($con,"INSERT INTO `airtime_manual_push`( `phoneno`, `network`, `transAmount`, `transref`, `comment`, `status`, `createdDate`, `transtime`) VALUES('$phoneno','$mobilenetwork','$amount','$ref','$totalairtime','S','$dateCreated','$transtime')");
    
                                        $sed = "UPDATE `multilevel` SET `activated`=1 where child_id='$customerId' ";
                                        $sffd = $dbs->prepare($sed);
                                        $sffd->execute();
                                    }
                                }
    
                                $wal_flag2 = 'check2';
                                $charge_flag = 0;
                                //$new_count=$transAmount / $savings_amount;
    
    
                                $savings_count = 1;
    
                                $charge_type = '';
    
                                //  if($billing_type=='P'){
                                //  //do an insert into admin_ledger.admin_charge
                                // $admin_charge= ($transAmount * $percentage_commission)/100;
                                // $charge_type='P';
                                // }
    
                                //do an insert into admin_ledger.admin_charge
                                // $admin_charge=  $money_commission;
                                // $charge_type='F';
    
                                // $transtype='commission';
                                // $trans_mode='AC';
    
                                // $sqla = "INSERT INTO `mosave_savingtransaction`(`customerId`, `agentId`,`accountId`,`planId`, `accountNo`, `transAmount`,`transType`,`transref`,
                                //                     `accountType`, `accountCode`,`trans_mode`,  `transDate`,`time`,`ip`) VALUES (:customerId,:agentId,:actids,:pid,:accountNo,:transAmount,:transtype,:transref,:accountType,:accountCode,:trans_mode,:datecreated,:time,:ip)";
    
                                // $db =  $conn->connect();
                                //         $stmta = $db->prepare($sqla);  
    
                                //         $stmta->bindParam(":customerId", $customerId,PDO::PARAM_STR);
                                //          $stmta->bindParam(":agentId", $agentId,PDO::PARAM_STR);
                                //           $stmta->bindParam(":actids", $accountId,PDO::PARAM_STR);
                                //           $stmta->bindParam(":pid", $planId,PDO::PARAM_STR);
                                //          $stmta->bindParam(":accountNo", $accountNo,PDO::PARAM_STR);
                                //          $stmta->bindParam(":transAmount", $admin_charge,PDO::PARAM_STR);
                                //           $stmta->bindParam(":transtype", $transtype,PDO::PARAM_STR);
                                //           $stmta->bindParam(":trans_mode", $trans_mode,PDO::PARAM_STR);
                                //           $stmta->bindParam(":transref", $refs,PDO::PARAM_STR);
                                //         $stmta->bindParam(":accountType", $accountType,PDO::PARAM_STR);
                                //          $stmta->bindParam(":accountCode", $accountCode,PDO::PARAM_STR);
    
    
    
    
                                //       $stmta->bindParam(":datecreated", $dateCreated,PDO::PARAM_STR);
                                //       $stmta->bindParam(":time", $time,PDO::PARAM_STR);
                                //       $stmta->bindParam(":ip", $ip,PDO::PARAM_STR);
    
                                //                 //$stmt->execute();
                                //                 $resultas=$stmta->execute();
    
                                // $sqlwa="INSERT INTO `mosave_admin_ledger`( `plan_id`, `cust_id`, `admin_charge`, `date`, `charge_type`) VALUES (:pId,:cId,:adcharge,:datecreated,:charge_type)";
    
                                // $db =  $conn->connect();
                                //         $stmtsqlwa = $db->prepare($sqlwa);  
    
                                //         $stmtsqlwa->bindParam(":cId", $customerId,PDO::PARAM_STR);
                                //          $stmtsqlwa->bindParam(":pId", $planId,PDO::PARAM_STR);
                                //           $stmtsqlwa->bindParam(":adcharge", $admin_charge,PDO::PARAM_STR);
                                //           $stmtsqlwa->bindParam(":datecreated", $date_time,PDO::PARAM_STR);
                                //          $stmtsqlwa->bindParam(":charge_type", $charge_type,PDO::PARAM_STR);
    
                                // $resultsqf=$stmtsqlwa->execute(); 
    
                                $d = strtotime("+1 Months");
                                $chargedt = date(
                                        "Y-m-d",
                                        $d
                                    );
                                $dateCre = date("Y-m-d");
    
                                $sqlupd = "UPDATE `mosave_customer_savings_plan` SET `savings_count`=:scount, `user_starting_date`=:startdate,`last_charge_date`=:lastchargedt,`next_charge_date`=:chargedt WHERE plan_id=:pid and cust_id=:cid";
                                $db =  $conn->connect();
                                $stmtupd = $db->prepare($sqlupd);
    
    
                                $stmtupd->bindParam(":scount", $savings_count, PDO::PARAM_STR);
                                $stmtupd->bindParam(":pid", $planId, PDO::PARAM_STR);
                                $stmtupd->bindParam(":cid", $customerId, PDO::PARAM_STR);
                                $stmtupd->bindParam(":startdate", $dateCre, PDO::PARAM_STR);
                                $stmtupd->bindParam(":lastchargedt", $dateCre, PDO::PARAM_STR);
                                $stmtupd->bindParam(":chargedt", $chargedt, PDO::PARAM_STR);
    
                                $stmtupd->execute();
    
                                // $sqlupd = "UPDATE `mosave_customer_savings_plan` SET `savings_count`=:scount,`charge_flag`=:cflag WHERE plan_id=:pid and cust_id=:cid";
                                // $db =  $conn->connect();
                                //         $stmtupd = $db->prepare($sqlupd);  
    
                                //         $stmtupd->bindParam(":cflag", $charge_flag,PDO::PARAM_STR);
                                //          $stmtupd->bindParam(":scount", $savings_count,PDO::PARAM_STR);
                                //           $stmtupd->bindParam(":pid", $planId,PDO::PARAM_STR);
                                //         $stmtupd->bindParam(":cid", $customerId,PDO::PARAM_STR);
    
                                // $stmtupd->execute();
    
                            }
                        }
    
    
    
    
    
    
                        $walletqry = "SELECT account_bal as wBalance FROM `mosave_wallet` WHERE  customerId=:custid and accountId=:actid";
    
                        $waldb =  $conn->connect();
                        $walstmt = $waldb->prepare($walletqry);
    
                        $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                        $walstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);
    
                        $walstmt->execute();
                        $red = $walstmt->fetch();
    
    
                        $wBalance = $red['wBalance'];
    
                        //if(is_null($wBalance) || $wBalance==0)
                        if ($walstmt->rowCount() == 0) 
                        {
    
    
                            //$test='lummy';
                            //insert into wallet if account has no balance already
                            $insewalqry = "INSERT INTO `mosave_wallet`( `agentId`, `accountId`,`accountNo`, `customerId`, `account_bal`) 
                            VALUES  (:agentid,:actid,:accno,:custid,:amt)";
                            $waldb =  $conn->connect();
                            $walstmt1 = $waldb->prepare($insewalqry);
                            $walstmt1->bindParam(":agentid", $agentId, PDO::PARAM_STR);
                            $walstmt1->bindParam(":actid", $accountId, PDO::PARAM_STR);
                            $walstmt1->bindParam(":accno", $accountNo, PDO::PARAM_STR);
    
                            $walstmt1->bindParam(":custid", $customerId, PDO::PARAM_STR);
                            $walstmt1->bindParam(":amt", $transAmount, PDO::PARAM_STR);
    
                            $walstmt1->execute();
                            $newBalance = $transAmount;
                        } else {
    
                            // $response['sav']=yes;
                            // $response['m']=$wBalance;
                            //update wallet if account has balance already
    
                            //$wBalance=$red['wBalance']==""?0.00:$red['wBalance'];
                            $newBalance = $wBalance + $transAmount;
    
                            $updwalqry = "UPDATE `mosave_wallet` SET `account_bal`=:newBalance  WHERE accountId=:actid and customerId=:custid ";
                            $waldbs =  $conn->connect();
                            $walstmt = $waldbs->prepare($updwalqry);
    
                            $walstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                            $walstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);
                            $walstmt->bindParam(":newBalance", $newBalance, PDO::PARAM_STR);
    
                            $walstmt->execute();
                        }
    
    
                        $planwalletqry = "SELECT `account_bal` as planwBalance, `available_bal` as planavailBalance FROM `mosave_plan_wallet` WHERE  customerId=:custid and plan_id=:pid  and accountId=:actid";
    
                        $planwaldb =  $conn->connect();
                        $planwalstmt = $planwaldb->prepare($planwalletqry);
    
                        $planwalstmt->bindParam(":custid", $customerId, PDO::PARAM_STR);
                        $planwalstmt->bindParam(":pid", $planId, PDO::PARAM_STR);
                        $planwalstmt->bindParam(":actid", $accountId, PDO::PARAM_STR);
    
                        $planwalstmt->execute();
                        $reds = $planwalstmt->fetch();
    
    
                        $planwBalance = $reds['planwBalance'];
                        $planavailBalance = $reds['planavailBalance'];
                        if ($savings_count_t == 0 && $charge_flag_t == 0) {
    
    
                            //      if($plans_id==4){
                            //          $avail_bal=$transAmount;
                            //      }else{
                            //  $avail_bal=$transAmount-$admin_charge;
                            //      }
                        }
                        if ($planwalstmt->rowCount() == 0) {
                            $avail_bal = $transAmount;
    
                            //$test='lummy';
                            //insert into wallet if account has no balance already
                            $planinsewalqry = "INSERT INTO `mosave_plan_wallet`( `agentId`, `accountId`,`accountNo`, `customerId`,`plan_id`, `account_bal`,`available_bal`) 
                                        VALUES  (:agentid,:actid,:accno,:custid,:pid,:amt,:avbal)";
                            $planwaldb =  $conn->connect();
                            $plnwalstmt1 = $planwaldb->prepare($planinsewalqry);
                            $plnwalstmt1->bindParam(":agentid", $agentId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":actid", $accountId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":accno", $accountNo, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":pid", $planId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":custid", $customerId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":amt", $transAmount, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":avbal", $avail_bal, PDO::PARAM_STR);
    
                            $plnwalstmt1->execute();
                            $plannewBalance = $transAmount;
                        } else {
    
                            // $response['sav']=yes;
                            // $response['m']=$wBalance;
                            //update wallet if account has balance already
    
                            //$wBalance=$red['wBalance']==""?0.00:$red['wBalance'];
                            $plannewBalance = $planwBalance + $transAmount;
    
    
    
                            $avail_bal = $planavailBalance + $transAmount;
                            $updwalqry11 = "UPDATE `mosave_plan_wallet` SET `account_bal`=:newBalance,`available_bal`=:avbal  WHERE accountId=:actid and plan_id=:pid and  customerId=:custid ";
                            $waldbs11 =  $conn->connect();
                            $plnwalstmt1 = $waldbs11->prepare($updwalqry11);
                            $plnwalstmt1->bindParam(":pid", $planId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":custid", $customerId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":actid", $accountId, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":newBalance", $plannewBalance, PDO::PARAM_STR);
                            $plnwalstmt1->bindParam(":avbal", $avail_bal, PDO::PARAM_STR);
    
                            $plnwalstmt1->execute();
                        }
    
    
    
    
    
    
    
                        //insert new $savings_count
                        //insert incremented $charge_flag
    
    
    
    
                        $transtype = 'S';
                        $trans_mode = 'CS';
    
                        $sql = "INSERT INTO `mosave_savingtransaction`(`customerId`, `agentId`,`accountId`,`planId`, `accountNo`, `transAmount`,`transType`,`transref`,
                              `accountType`, `accountCode`,`trans_mode`,  `transDate`,`time`,`ip`) VALUES (:customerId,:agentId,:actids,:pid,:accountNo,:transAmount,:transtype,:transref,:accountType,:accountCode,:trans_mode,:datecreated,:time,:ip)";
    
                        $db =  $conn->connect();
                        $stmt = $db->prepare($sql);
    
                        $stmt->bindParam(":customerId", $customerId, PDO::PARAM_STR);
                        $stmt->bindParam(":agentId", $agentId, PDO::PARAM_STR);
                        $stmt->bindParam(":actids", $accountId, PDO::PARAM_STR);
                        $stmt->bindParam(":pid", $planId, PDO::PARAM_STR);
                        $stmt->bindParam(":accountNo", $accountNo, PDO::PARAM_STR);
                        $stmt->bindParam(":transAmount", $transAmount, PDO::PARAM_STR);
                        $stmt->bindParam(":transtype", $transtype, PDO::PARAM_STR);
                        $stmt->bindParam(":trans_mode", $trans_mode, PDO::PARAM_STR);
                        $stmt->bindParam(":transref", $refs, PDO::PARAM_STR);
                        $stmt->bindParam(":accountType", $accountType, PDO::PARAM_STR);
                        $stmt->bindParam(":accountCode", $accountCode, PDO::PARAM_STR);
    
    
    
    
                        $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                        $stmt->bindParam(":time", $time, PDO::PARAM_STR);
                        $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);
    
                        //$stmt->execute();
                        $result = $stmt->execute();
                        //$result = $stmt->fetch();
    
                        if ($result) 
                        {
    
                            //Calculate loyalty based on Savings transaction
    
                            $getsettings = "SELECT * FROM `loyaltysettings` WHERE `setupName`='MoSave_Savings'";
                            $ds1   =   $conn->connect();
                            $sff1 = $ds1->prepare($getsettings);
                            $sff1->execute();
                            $resw = $sff1->fetch();
                            $referpoint = $resw['referpoint'];
                            $redemption_ratio = $resw['redemption_ratio'];
                            $accrue_ratio = $resw['accural_ratio'];
                            $min_airtime = $resw['min_airtime'];
                            $submerchantId = $resw['submerchantId'];
                            $merchantId = $resw['merchantId'];
    
    
                            //calculate redemption value to benefit      
                            $aredamt = $transAmount / $redemption_ratio;
                            $nredamt = round($aredamt, 2);
    
                            //calculate accrual value       
                            $aacramt = $transAmount / $accrue_ratio;
                            $nacruamt = round($aacramt, 2);
    
    
                            $qr = "SELECT * FROM `mosave_loyalty_wallet` WHERE customerId='$customerId' and submerchantId=$submerchantId";
    
                            $ds1sas   =   $conn->connect();
                            $sff1sas = $ds1sas->prepare($qr);
                            $sff1sas->execute();
                            $re = $sff1sas->fetch();
                            $s = $re['customerId'];
                            $sredeemableamt = $re['redeemableamt'];
                            $saccruedpoints = $re['accruedpoints'];
    
    
                            $transtype = 'M';
                            $refs = getTransactionRef();
                            $trans_mode = 'CS';
    
                              $desc= $nredamt.' loyalty gift on '.$transAmount.' Savings';
    
    
    
                            if ($s != ''
                            ) {
                                //update
    
                                $nredamt = $nredamt + $sredeemableamt;
                                $nacruamt = $nacruamt + $saccruedpoints;
    
                                $addpoint_qry = "UPDATE `mosave_loyalty_wallet` SET `accruedpoints`='$nacruamt',`redeemableamt`='$nredamt' where customerId='$customerId'and submerchantId=$submerchantId";
                            } else {
    
    
                                //insert
                                $addpoint_qry = "INSERT INTO `mosave_loyalty_wallet`(`agentId`, `merchantId`, `submerchantId`, `customerId`,`accruedpoints`, `redeemableamt`, `comment`)
                                              VALUES ('$agentId','$merchantId','$submerchantId','$customerId','$nacruamt','$nredamt', '$desc')";
                            }
    
                            $dwsa   =   $conn->connect();
                            $sf = $dwsa->prepare($addpoint_qry);
                            $sf->execute();
    
                            $sql = "INSERT INTO `mosave_loyalty_transactions`(`customerId`, `agentId`, `transAmount`,
                                    `transType`, `transref`, `des`, `transDate`,`time`,`ip`) 
                                    VALUES (:customerId,:agentId,:redemptionAmount,:transtype,:transref,:des,:datecreated,:time,:ip)";
    
                            $db =  $conn->connect();
                            $stmt = $db->prepare($sql);
    
                            $stmt->bindParam(":customerId", $customerId, PDO::PARAM_STR);
                            $stmt->bindParam(":agentId", $agentId, PDO::PARAM_STR);
                            $stmt->bindParam(":des", $desc, PDO::PARAM_STR);
                            $stmt->bindParam(":redemptionAmount", $nredamt, PDO::PARAM_STR);
                            $stmt->bindParam(":transtype", $transtype, PDO::PARAM_STR);
                            $stmt->bindParam(":transref", $refs, PDO::PARAM_STR);
    
    
    
    
                            $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                            $stmt->bindParam(":time", $time, PDO::PARAM_STR);
                            $stmt->bindParam(":ip", $ip, PDO::PARAM_STR);
    
                            //$stmt->execute();
                            $result = $stmt->execute();
    
    
                            $response['error'] = false;
                            $response['message'] = " Successful ";
                            $response['source'] = "Savings";
                            $response['agentId'] = $agentId;
                            $response['transAmount'] = $transAmount;
                            $response['accountId'] = $accountId;
                            $response['trxref'] = $refs;
                            $response['plan_name'] = $plan_name;
                            $response['accountNo'] = $accountNo;
                            $response['timestamp'] = $dateCreated;
                            $response['time'] = $time;
    
                            $sqlsa1 = "SELECT  `userId`, `BVN_num`, `firstName`, `mname`, `lastName`, `email`, `mobilenetwork` FROM `config_user` WHERE sn=:custid";
    
                            $stmtb1 = $dbs->prepare($sqlsa1);
                            $stmtb1->bindParam("custid", $customerId);
    
                            $stmtb1->execute();
                            $resultsa1 = $stmtb1->fetch();
                            $phone = $resultsa1['userId'];
                            $email = $resultsa1['email'];
                            $fname = $resultsa1['firstName'];
                            $name = $resultsa1['firstName'] . ' ' . $resultsa1['lastName'];
    
                            $smsmsg     = 'Dear ' . $fname . ', there is deposit on your MoSave account.  \nAcct: ' . $accountNo . '\nPlan: ' . $plan_name . '\nAmt: ' . $transAmount . ' CR' . '\nNet Bal: ' . $avail_bal . '';
    
                            //$whatsappchk=sendWhatsApp($phone,$smsmsg);
                            $smscheck = sendSMS($phone, $smsmsg);
                            //$response['error']=$smscheck;
                            //$response['message']=$phone;
                            //echo json_encode($response);
                            $note = "";
                            $from    = "noreply@moloyal.com";
                            $to      = $email;
                            $msg1     = 'NGN' . $transAmount .
                                ' has been credited into your MoSave Account.<br>
           
                                        <br> <strong><u> Here is what you need to know: </u></strong><br>
                                    Transaction Ref.	:' . $refs . '<br>
                                    Account Number	:' . $accountNo . '<br>
                                
                                    Account Name	:	' . $name . '<br>
                                    Plan Name	:	' . $plan_name . '<br>
                                    Amount	:	NGN' . $transAmount . '<br>
                                    Note	:	' . $note . '<br>
                                    Value Date	:	' . $dateCreated . '<br>
                                
                                    Time of Transaction	:	' . $time . '<br>
                                
                                
                                    The balance on this account as at  ' . $time . '  are as follows;<br>
                                    Net Balance	:  NGN' . $avail_bal . '<br>
                                
                                
                                    ';
    
                                        $msg = '<tr>
                                            <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 10px;">
                                                    <h4 style="color:#000;"> Dear ' . $name . ',</h4>
                                                <p style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; color: #000000; text-align: left;">
                        
                                                
                                ' . $msg1 . '
                            
                                </p>
                                                    </td>
                                                </tr>';
    
                                                $subject = "MoLoyal Credit Transaction [ " . $transAmount . " ]";
                                        $type    = $name;
    
                                $emailsent = sendEmail($from, $to, $msg, $subject, $type);
                        } else {
                                $response['error'] = true;
                                $response['message'] = "There was an error contacting the server, please retry";
                        }

                               
                    
                    }else{
                        
                        $response['error'] = true;
                                $response['message'] = "Card charge payment not successful, please retry"; 
                        
                    }

                     $res->getBody()->write(json_encode($response));
                                    return $res
                                ->withHeader('content-type', 'application/json')
                                ->withStatus(200);    
                }
            }
            //$user->id = $db->lastInsertId();

           
        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    }
});



//recurring card charge  with authorisation_code

$app->post('/customer/recurringpaywithcard', function (Request $req, Response $res) {

    $response = array();
    $conn = new Db();
    $db = $conn->connect();
   

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] != 1) {
       
        $res->getBody()->write(json_encode($result));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);
    } else {
        
    
          try {

            
            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;

            $data = $req->getParsedBody();
            $amount = $data["amount"];
           
            $card_id = $data["card_id"];
            
            $sqlv = "SELECT * FROM `recurring_payment` WHERE `email_address`=:email and `serial_number`=:card_id";
            $stmta = $db->prepare($sqlv);
            $stmta->bindParam(":email", $token_email, PDO::PARAM_STR);
            $stmta->bindParam(":card_id", $card_id, PDO::PARAM_STR);
        
           
              $rra= $stmta->execute();
        
              $resultsa = $stmta->fetch();
              $num = $stmta->rowCount();
              $authcode=$resultsa['authorization_code'];
              $email=$resultsa['email_address'];
              $reference=$resultsa['reference'];
              $authcode=$resultsa['authorization_code'];
              $authcode=$resultsa['authorization_code'];


 $paystack_secret_key = $_ENV['PAYSTACK_SECRET_KEY'];
            //get authorization_code, email from recurring_payment table for the same customer
            $chargedata = array(
                'authorization_code' => $authcode,
                'email' => $email,
                 'reference' => $reference,
                'amount' => $amount
                );
        
          
        
        
        


            $payload = json_encode($chargedata);
            $ch = curl_init('https://api.paystack.co/transaction/charge_authorization');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            "authorization: Bearer $paystack_secret_key",
           //'authorization: Bearer sk_test_feeb3d34498e46330086fe2a73b02692a05adda5',
            'cache-control: no-cache',
            'content-type: application/json',
            'content-length: ' . strlen($payload))
             );
        
            $result = curl_exec($ch);
            
           $t=json_decode($result);
           
           
            $stat=$t->data->status;
           $txref=$t->data->reference;
           $gateresponse=$t->data->gateway_response;



        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            $res->getBody()->write(json_encode($response));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    }
});

//Setup customer autosave
$app->post('/customer/setup_autosave', function (Request $req, Response $res) {
    

    $conn = new Db();
    $db = $conn->connect();

    $response   = array();
    $lastResetDate  = date('Y-m-d H:i:s');
    // $agentid       = $request->agentid;
    // $pin      = $request->pin;

    $data = $req->getParsedBody();
    $amount = $data["amount"];
    $frequency = $data["frequency"];
    $startDate = $data["startDate"];
    $endDate = $data["endDate"];
    $time = $data["time"];
    $fundSource = $data["fundSource"];
    $timeline = $data["timeline"];
    $xter_rand=alphabeth_random();
        $jwt = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

        $arr = explode(" ", $authHeader);



        $jwt = $arr[1];
        $result = validateJWT($jwt);

        if ($result['validate_flag'] == 1) {



            /*
     $customerid='08090963549';
     
      $pin =2345;
     */
            try {

                $token_email = $result['token_value']->data->email;
                //echo $token_email;

                $token_id = $result['token_value']->data->id;
                //echo $token_id;

                $token_userid = $result['token_value']->data->userId;
                //echo $token_userid;
                $dateCreated = date("Y-m-d H:i:s");



               
                $updateUserquery = "INSERT INTO `config_autosave`(`customer_id`, 
                 `plan_id`,`amount`, `frequency`, `startDate`, `endDate`, `withdraw_time`,
                 `withdraw_source`, `timeline`, `status`,`dateCreated`) 
               VALUES (:custid,:xter_rand, :amt,:frequency,:startDate,
               :endDate,:wtime,:fundsrc,:timeline,1,:datecreated)";
                // $updateUser =  mysqli_query($con, $updateUserquery);

                $stmt = $db->prepare($updateUserquery);
                $stmt->bindParam(":custid", $token_id);
                $stmt->bindParam(":amt", $amount, PDO::PARAM_STR);
                $stmt->bindParam(":frequency", $frequency, PDO::PARAM_STR);
                $stmt->bindParam(":startDate", $startDate, PDO::PARAM_STR);
                $stmt->bindParam(":endDate", $endDate, PDO::PARAM_STR);
                $stmt->bindParam(":wtime", $time, PDO::PARAM_STR);
                $stmt->bindParam(":fundsrc", $fundSource, PDO::PARAM_STR);
                $stmt->bindParam(":timeline", $timeline, PDO::PARAM_STR);
                $stmt->bindParam(":datecreated", $dateCreated, PDO::PARAM_STR);
                $stmt->bindParam(":xter_rand", $xter_rand, PDO::PARAM_STR);
                

                $result = $stmt->execute();

                $errorInfo = $stmt->errorInfo();

                if (isset($errorInfo[2])) {
                    $response['error']   = true;
                    $response['message'] = $errorInfo[2];
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
                }
                if ($result) {

                    $data = array(
                        'plan_id' => $xter_rand,
                        'amount' => $amount,
                         'fundSource' => $fundSource,
                         'frequency' => $frequency,

                        'startDate' => $startDate
                        );


                    $response['error'] = false;
        
                    $response['message']='You have successfully created autosave.';
                    $response['data'] = $data;
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
                }
            } catch (PDOException $e) {
                $response['error']   = true;
                $response['message'] = $e->getMessage();
                $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
            }
        } else {
            
            $res->getBody()->write(json_encode($result));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
        }
    
});

//Setup customer bank account
$app->post('/customer/save_bankdetails', function (Request $req, Response $res) {
    

    $conn = new Db();
    $db = $conn->connect();

    $response   = array();
    $lastResetDate  = date('Y-m-d H:i:s');
    // $agentid       = $request->agentid;
    // $pin      = $request->pin;

    $data = $req->getParsedBody();
    $account_name = $data["account_name"];
    $account_no = $data["account_number"];
    $bank_name = $data["bank_name"];
    $bank_code = $data["bank_code"];
    $currency = "NGN";
    
        $jwt = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

        $arr = explode(" ", $authHeader);



        $jwt = $arr[1];
        $result = validateJWT($jwt);

        if ($result['validate_flag'] != 1) {
            $res->getBody()->write(json_encode($result));
            return $res
                ->withHeader('content-type', 'application/json')
                ->withStatus(401);

        } else {
            
         
        

            /*
     $customerid='08090963549';
     
      $pin =2345;
     */
            try {

                $token_email = $result['token_value']->data->email;
                //echo $token_email;

                $token_id = $result['token_value']->data->id;
                //echo $token_id;

                $token_userid = $result['token_value']->data->userId;
                //echo $token_userid;
                $dateCreated = date("Y-m-d H:i:s");



               
                $updateUserquery = "UPDATE `config_customer_account_info` SET `bank_code`=:bank_code,`bank_name`=:bank_name,`bank_account_no`=:account_no,`bank_account_name`=:acct_name,`currency`=:currency WHERE `customerId`=:custid";
                // $updateUser =  mysqli_query($con, $updateUserquery);

                $stmt = $db->prepare($updateUserquery);
                $stmt->bindParam(":custid", $token_id);
                $stmt->bindParam(":acct_name", $account_name);
                $stmt->bindParam(":bank_name", $bank_name);
                $stmt->bindParam(":bank_code", $bank_code);
                $stmt->bindParam(":account_no", $account_no);
                $stmt->bindParam(":currency", $currency);
                

                $result = $stmt->execute();

                $errorInfo = $stmt->errorInfo();

                if (isset($errorInfo[2])) {
                    $response['error']   = true;
                    $response['message'] = $errorInfo[2];
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(401);
                }
                if ($result) {

                    //$data = array(
                        // 'plan_id' => $xter_rand,
                        // 'amount' => $amount,
                        //  'fundSource' => $fundSource,
                        //  'frequency' => $frequency,

                        // 'startDate' => $startDate
                        // );


                    $response['error'] = false;
        
                    $response['message']='You have successfully created customer bank details';
                    //$response['data'] = $data;
                    $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
                }
            } catch (PDOException $e) {
                $response['error']   =true;
                $response['message'] = $e->getMessage();
                $res->getBody()->write(json_encode($response));
        return $res
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
            }
        }   
    
});





// customer savings plan
$app->get('/customer/saving_plans', function (Request $request, Response $response) {

  
    $conn = new Db();
    $db = $conn->connect();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    //echo '<br><br>';
    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


            $sql = "SELECT sn, plan_name, plan_amount,`days`
        FROM savings_plan
        WHERE sn NOT IN
        (SELECT plan_id 
         FROM mosave_customer_savings_plan WHERE cust_id=:cid)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(":cid", $token_id, PDO::PARAM_STR);
            $stmt->execute();
            //$result = $stmt->fetch();

            while ($results = $stmt->fetch(PDO::FETCH_ASSOC)) {
                //$result = $stmt->fetch(PDO::FETCH_ASSOC);

                $res['sn'] = $results['sn'];
                $res['amount'] = $results['plan_amount'];
                $res['plan_name'] = $results['plan_name'];
                $res['days'] = $results['days'];
                $cust   = array();
                array_push($cust, $res);
            }
            //$res = $stmt->fetchAll();
            // echo json_encode($cust);

            $response->getBody()->write(json_encode($cust));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);
        } catch (PDOException $e) {
            $error = array(
                "message" => $e->getMessage()
            );

            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});



// customer withdraw history
$app->get('/customer/withdrawhistory', function (Request $request, Response $response) {

  
    $conn = new Db();
    $db = $conn->connect();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    //echo '<br><br>';
    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


            $sql = "SELECT ST.`customerId`, ST.`agentId`, ST.`accountId`, ST.`accountNo`, ST.`transAmount`, ST.`transType`, ST.`accountType`, ST.`accountCode`, ST.`status`, ST.`createdDate`, 
            ST.`transDate`, ST.`time`, ST.`ip`,C.`sn`, C.`userId`, C.`BVN_num`, C.`firstName`, C.`mname`, C.`lastName`, C.`email`, C.`city`, C.`state`, C.`gender`
            FROM `mosave_savingtransaction` ST JOIN `config_user` C 
            ON ST.customerId=C.sn WHERE ST.transType='W' and ST.`customerId`=:custid order by ST.`createdDate` Desc  limit 50";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(":custid", $token_id, PDO::PARAM_STR);
            $stmt->execute();
            //$result = $stmt->fetch();
            $result     = $stmt->fetchAll(PDO::FETCH_OBJ);
             
      if ($result) {
          
          
         
            
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
       
        
   } else {
       $response['error']   = true;
       $response['message'] = "No Transactions";
       $response->getBody()->write(json_encode($response));
       return $response
           ->withHeader('content-type', 'application/json')
           ->withStatus(401);
   }
   
            //$res = $stmt->fetchAll();
            // echo json_encode($cust);

           
        } catch (PDOException $e) {
            $error = array(
                "message" => $e->getMessage()
            );

            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

// customer savings history
$app->get('/customer/savingshistory', function (Request $request, Response $response) {

  
    $conn = new Db();
    $db = $conn->connect();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    //echo '<br><br>';
    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


            $sql = "SELECT ST.`customerId`, ST.`agentId`, ST.`accountId`, ST.`accountNo`, ST.`transAmount`, ST.`transType`, ST.`accountType`, ST.`accountCode`, ST.`status`, ST.`createdDate`, 
  ST.`transDate`, ST.`time`, ST.`ip`,C.`sn`, C.`userId`, C.`BVN_num`, C.`firstName`, C.`mname`, C.`lastName`, C.`email`, C.`city`, C.`state`, C.`gender`
  FROM `mosave_savingtransaction` ST JOIN `config_user` C 
  ON ST.customerId=C.sn WHERE ST.transType='S' and ST.`customerId`=:custid order by ST.`createdDate` Desc limit 50";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(":custid", $token_id, PDO::PARAM_STR);
            $stmt->execute();
            //$result = $stmt->fetch();
            $result     = $stmt->fetchAll(PDO::FETCH_OBJ);
             
      if ($result) {
          
      
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
       
        
   } else {
       $response['error']   = true;
       $response['message'] = "No Transactions";
       $response->getBody()->write(json_encode($response));
       return $response
           ->withHeader('content-type', 'application/json')
           ->withStatus(401);
   }
   
            //$res = $stmt->fetchAll();
            // echo json_encode($cust);

           
        } catch (PDOException $e) {
            $error = array(
                "message" => $e->getMessage()
            );

            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

// customer all transactions history
$app->get('/customer/alltransactions', function (Request $request, Response $response) {

  
    $conn = new Db();
    $db = $conn->connect();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    //echo '<br><br>';
    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


            $sql = " SELECT ST.`customerId`, ST.`agentId`, ST.`planId`, ST.`accountId`, ST.`accountNo`, ST.`transAmount`, ST.`transref`,ST.`transType`, ST.`accountType`, ST.`accountCode`, ST.`status`, ST.`createdDate`, (SELECT J.`plan_name` FROM `savings_plan` J WHERE J.sn =ST.`planId`) as plan_name,
                ST.`transDate`, ST.`time`, ST.`ip`,C.`sn`, C.`userId`, C.`BVN_num`, C.`firstName`, C.`mname`, C.`lastName`, C.`email`, C.`city`, C.`state`, C.`gender`
                FROM `mosave_savingtransaction` ST JOIN `config_user` C 
                ON ST.customerId=C.sn WHERE  ST.`customerId`=:custid order by ST.`createdDate` Desc limit 50";
                
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":custid", $token_id, PDO::PARAM_STR);
            $stmt->execute();
            //$result = $stmt->fetch();
            $result     = $stmt->fetchAll(PDO::FETCH_OBJ);
             
      if ($result) {
          
      
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
       
        
   } else {
       $response['error']   = true;
       $response['message'] = "No Transactions";
       $response->getBody()->write(json_encode($response));
       return $response
           ->withHeader('content-type', 'application/json')
           ->withStatus(401);
   }
   
            //$res = $stmt->fetchAll();
            // echo json_encode($cust);

           
        } catch (PDOException $e) {
            $error = array(
                "message" => $e->getMessage()
            );

            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

// customer savings plan
$app->get('/customer/payment_cards', function (Request $request, Response $response) {

  
    $conn = new Db();
    $db = $conn->connect();

    $jwt = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];

    //echo '<br><br>';
    $arr = explode(" ", $authHeader);



    $jwt = $arr[1];
    $result = validateJWT($jwt);

    if ($result['validate_flag'] == 1) {
        try {

            $token_email = $result['token_value']->data->email;
            //echo $token_email;

            $token_id = $result['token_value']->data->id;
            //echo $token_id;

            $token_userid = $result['token_value']->data->userId;
            //echo $token_userid;


            $sql = "SELECT * FROM `recurring_payment` WHERE email_address=:email";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(":email", $token_email, PDO::PARAM_STR);
            $stmt->execute();
            //$result = $stmt->fetch();
 $cust   = array();
            while ($results = $stmt->fetch(PDO::FETCH_ASSOC)) {
                //$result = $stmt->fetch(PDO::FETCH_ASSOC);

                $res['paymentcard_id'] = $results['serial_number'];
                $res['account_name'] = $results['acct_name'];
                $res['exp_month'] = $results['exp_month'];
                $res['exp_year'] = $results['exp_year'];
                $res['bank'] = $results['bank'];
                $enc_last4 = $results['last4'];
                $enc_card_type = $results['card_type'];

                $res['card_type']=encrypt_decrypt('decrypt',$enc_card_type);
                $res['last4']=encrypt_decrypt('decrypt',$enc_last4);
                

                
               
                array_push($cust, $res);
            }
            //$res = $stmt->fetchAll();
            // echo json_encode($cust);
    $data = array(
                "data" => $cust
            );

            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(200);
        } catch (PDOException $e) {
            $error = array(
                "message" => $e->getMessage()
            );

            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

//Functions

//send Email
function sendEmail($from, $to, $msg, $subject, $type)
{

    $from = "care@moloyal.com"; //enter your email address



    $text    = $msg; // text versions of email.
    $message = '<!DOCTYPE html>
    <html>
    <head>
    <title>Moloyal Registration</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style type="text/css">
    /* CLIENT-SPECIFIC STYLES */
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; }

    /* RESET STYLES */
    img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }

    /* iOS BLUE LINKS */
    a[x-apple-data-detectors] {
        color: inherit !important;
        text-decoration: none !important;
        font-size: inherit !important;
        font-family: inherit !important;
        font-weight: inherit !important;
        line-height: inherit !important;
    }

    /* MEDIA QUERIES */
    @media screen and (max-width: 480px) {
        .mobile-hide {
            display: none !important;
        }
        .mobile-center {
            text-align: center !important;
        }
    }

    /* ANDROID CENTER FIX */
    div[style="margin: 16px 0;"] { margin: 0 !important; }
    </style> 
    <body style="margin: 0 !important; padding: 0 !important; background-color: #eeeeee;" bgcolor="#eeeeee">

    <!-- HIDDEN PREHEADER TEXT -->



    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="background-color: #eeeeee;" bgcolor="#eeeeee">

            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                <tr>
                    <td align="center" valign="top" style="font-size:0; padding: 35px;" bgcolor="#fff">

                    <div style="display:inline-block; max-width:50%; min-width:100px; vertical-align:top; width:100%;">
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:300px;">
                            <tr>
                                <td align="left" valign="top" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 36px; font-weight: 800; line-height: 48px;" class="mobile-center">
                                  <!-- <h1 style="font-size: 36px; font-weight: 800; margin: 0; color: #ffffff;">Beretun</h1>-->
                                  <img src="https://moloyal.com/images/moloyal.png" >
                                </td>
                            </tr>
                        </table>
                    </div>


                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding: 35px 35px 20px 35px; background-color: #ffffff;" bgcolor="#ffffff">

                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                  ' . $msg . '
                        </table>

                    </td>
                </tr>
                <tr>
                    <td align="center" style=" padding: 35px; background-color: #1b9ba3;" bgcolor="#1b9ba3">

                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                        <tr>
                            <td align="center" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 400; line-height: 24px; padding-top: 25px;">
                                <h2 style="font-size: 24px; font-weight: 800; line-height: 30px; color: #ffffff; margin: 0;">
                                    <!--Get 15% off your next order.-->
                                    Don\'t you have the Mobile App!
                                </h2>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding: 25px 0 15px 0;">
                                <table border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td align="center" style="border-radius: 5px;" bgcolor="#66b3b7">
                                          <a href="https://play.google.com/store/apps/details?id=com.moloyal.moloyal.app&hl=en" target="_blank" style="font-size: 18px;  font-family: Open Sans, Helvetica, Arial, sans-serif;  display: block;"><img src="https://moloyal.com/test/loyaluserscript/api/images/downloadapp.png" style="width: 120px;"></a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding: 35px; background-color: #ffffff;" bgcolor="#ffffff">

                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">
                        <tr>
                            <td align="center" >
                            <a href="https://www.facebook.com/moloyalapp/">   <img src="https://moloyal.com/test/loyaluserscript/api/images/facebook.png" width="37" height="37" style="display: block; border: 0px; display:inline;"/></a>
                            <a href="https://bit.ly/hellomoloyal">   <img src="https://moloyal.com/test/loyaluserscript/api/images/whatsapp.png" width="37" height="37" style="display: block; border: 0px; display:inline;"/></a>
                            <a href="https://www.twitter.com/moloyalapp/">   <img src="https://moloyal.com/test/loyaluserscript/api/images/twitter.png" width="37" height="37" style="display: block; border: 0px; display:inline;"/></a>
                            <a href="https://www.instagram.com/moloyal_app/">   <img src="https://moloyal.com/test/loyaluserscript/api/images/instagram.png" width="37" height="37" style="display: block; border: 0px; display:inline;"/></a>
                            </td>
                        </tr>
                      <!-- <tr>
                            <td align="center" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px; padding: 5px 0 10px 0;">
                                <p style="font-size: 14px; font-weight: 800; line-height: 18px; color: #333333;">
                                    675 Massachusetts Avenue<br>
                                    Cambridge, MA 02139 
                                </p>
                            </td>
                        </tr>-->
                        <tr>
                            <td align="left" style="font-family: Open Sans, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 400; line-height: 24px;">
                                <p style="font-size: 14px; font-weight: 400; line-height: 20px; color: #777777; text-align:center;">
                                        Click on Profile Settings on the left-hand menu to update your profile. 
                                        <br>
                                        If you have any questions, please feel free to contact our support team 
                                        <br>
                                        
                                        Email: <a href="mailto:care@moloyal.com">care@moloyal.com</a>   or Call: 08188775534.
                                        <br>
                                        Our customer support team will be happy to assist you.
                                </p>
                            </td>
                        </tr>
                    </table>

                    </td>
                </tr>
            </table>

            </td>
        </tr>
    </table>
        
    </body>
    </html>
    ';

    /* Exception class. */
    require_once '../PHPMailer/src/Exception.php';

    /* The main PHPMailer class. */
    require_once '../PHPMailer/src/PHPMailer.php';

    /* SMTP class, needed if you want to use SMTP. */
    //require_once '/home/speckl7/gr8jobs.specklessinnovations.com/PHPMailer/src/SMTP.php';

    require_once '../PHPMailer/src/SMTP.php';


    $mail = new PHPMailer(TRUE);

    $mail = new PHPMailer();
    $mail->SMTPDebug = 0;
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->SMTPAuth   = true;
    $mail->Host   = $_ENV['EMAIL_HOST'];
    $mail->Port       = 465;

    $mail->SMTPSecure = "ssl";
    //Sets SMTP authentication. Utilizes the Username and Password variables
    $mail->Username = $_ENV['EMAIL_USERNAME'];        //Sets SMTP username
    $mail->Password = $_ENV['EMAIL_PASSWORD'];
    //Sets SMTP password


    $mail->From = 'care@moloyal.com';      //Sets the From email address for the message
    $mail->FromName = 'MoLoyal Support';      //Sets the From name of the message
    $mail->addAddress($to, $type);

    /* Set the subject. */
    $mail->Subject = $subject;

    /* Set the mail message body. */
    $mail->Body = $message;
    $mail->WordWrap = 50;            //Sets word wrapping on the body of the message to a given number of characters
    $mail->IsHTML(true);
    /* Finally send the mail. */
    $kk = $mail->send();
    /* Finally send the mail. */
    if ($kk == 0) {
        //echo 'Mailer Error: ' . $mail->ErrorInfo;

        return 'Mailer Error: ' . $mail->ErrorInfo;
        //echo("<p>" . $mail->getMessage() . "</p>");
    } else {
        return 1;
    }
}


//send Airtime

function sendAirtime($airtimeamt, $phoneno, $mobilenetwork)
{

    if ($mobilenetwork == 'MTN') {
        $billid = 1;
        $itemcode = 'M05';
    } elseif ($mobilenetwork == 'Glo') {
        $billid = 19;
        $itemcode = 'G05';
    } elseif ($mobilenetwork == '9mobile') {
        $billid = 18;
        $itemcode = 'E05';
    } elseif ($mobilenetwork == 'Airtel') {
        $billid = 20;
        $itemcode = 'A05';
    }

    $curl = curl_init();
    $headers = [];
    curl_setopt_array($curl, array(
        CURLOPT_URL => "http://91.109.117.92/party/?action=0&email_id=rdi@avante-cs.com&pass_key=Welcome123$",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        //CURLOPT_POSTFIELDS => "action=0&email_id=rdi@avante-cs.com&pass_key=Welcome123$",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded",

        ),
    ));
    $response1 = curl_exec($curl);
    //echo $err = curl_error($curl).'<br><br>';
    $da = json_decode($response1, true);
    //echo 'tt';
    $token = $da["sessionID"];
    $ref_no = $da["ex_ref_no"];
    curl_close($curl);

    //send airtime here

    $curls = curl_init();
    $headers = [];
    //$ref=22;
    //get userid and get network
    curl_setopt_array($curls, array(
        CURLOPT_URL => "http://91.109.117.92/party/?action=1&ex_ref_no=$ref_no&sessionID=$token&trans_amt=$airtimeamt&vend_email=rdi@avante-cs.com&bill_id=$billid&item_id=$itemcode&phone_numb=$phoneno",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        //CURLOPT_POSTFIELDS => "action=1&ex_ref_no=$ref_no&sessionID=$token&trans_amt=50&vend_email=rdi@avante-cs.com&bill_id=1&item_id=M01&phone_numb=08136458772",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded",

        ),
    ));
    //$response12 = curl_exec($curls);

    $err = curl_error($curls) . '<br><br>';
    $das = json_decode($response12, true);
    //echo 'tt';
    $session = $das["session_id"];
    $code = $das["ex_resp_code"];
    $ex_resp_desc = $das["ex_resp_desc"];

    curl_close($curls);


    return $das;
}


//generateotp

function generateotp()
{
    $characters = 6;
    $letters    = '123456789';
    $str        = '';
    for ($i = 0; $i < $characters; $i++) {
        $str .= substr($letters, mt_rand(0, strlen($letters) - 1), 1);
    }
    return $str;
}
//Generate Random Number
function random()
{
    $characters = 6;
    $letters    = '1234567890';
    $str        = '';
    for ($i = 0; $i < $characters; $i++) {
        $str .= substr($letters, mt_rand(0, strlen($letters) - 1), 1);
    }
    return $str;
}

//Generate Alphabeth Random 
function alphabeth_random()
{
    $characters = 8;
    $letters    = 'abccdefghjkmnpqrstuv';
    $str        = '';
    for ($i = 0; $i < $characters; $i++) {
        $str .= substr($letters, mt_rand(0, strlen($letters) - 1), 1);
    }
    return $str;
}


function sendSMS($phone, $msg)
{

    $msg = urlencode($msg);

    $nph = substr($phone, 1);
    $new_phone = '234' . $nph;


    $urls = 'https://httpsmsc05.montymobile.com/HTTP/api/Client/SendSMS?username=MoLoyal@1&password=MoLoyal@1&destination=' . $new_phone . '&source=MoLoyal&text=' . $msg . '&dataCoding=0';


    $curls = curl_init();
    //$url='https://www.bulksmsnigeria.com/api/v1/sms/create?api_token=t2T319wBXjEQsaGAHDQesL4EBss4VwmdjYRdGUQd0YgRVHt59vqI63IWcKln&from=MoLoyal&to=09096456814&body=testing messgae lummy&dnd=2';
    curl_setopt_array($curls, array(
        CURLOPT_URL => $urls,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',

    ));

    $responses = curl_exec($curls);
    //echo $responses;
    $res = json_decode($responses);

    if ($res->ErrorCode != 0) {


        $msg = urlencode($msg);
        $url = 'https://www.bulksmsnigeria.com/api/v1/sms/create?api_token=t2T319wBXjEQsaGAHDQesL4EBss4VwmdjYRdGUQd0YgRVHt59vqI63IWcKln&from=MoLoyal&to=' . $phone . '&body=' . $msg . '&dnd=2';

        $curl = curl_init();
        //$url='https://www.bulksmsnigeria.com/api/v1/sms/create?api_token=t2T319wBXjEQsaGAHDQesL4EBss4VwmdjYRdGUQd0YgRVHt59vqI63IWcKln&from=MoLoyal&to=09096456814&body=testing messgae lummy&dnd=2';
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',

        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }
}

function getTransactionRef()
{
    $characters = 3;
    $letters    = '23456789';
    $str        = '';
    for ($i = 0; $i < $characters; $i++) {
        $str .= substr($letters, mt_rand(0, strlen($letters) - 1), 1);
    }
    $ref = date("Ymdgis");
    $k = $ref . $str;
    return $k;
}

function registerUser($email, $phoneno, $firstname, $lastname, $agentId)
{

    $postData = [
        "firstname" => $firstname,
        "lastname" => $lastname,
        "email" => $email,
        "phoneno" => $phoneno,
        "sn" => $agentId
    ];
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => BASE_URL . '/users/register',
        //CURLOPT_URL => 'https://moloyal.com/test/mosave/script/api/registeruser/test',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));

    $response1 = curl_exec($curl);

    curl_close($curl);
    return $response1;
}

function getMobileNetwork($phoneno)
{

    $identifier = substr($phoneno, 0, 4);
    $conn = new Db();
    $db = $conn->connect();
    $stmt = $db->prepare("SELECT * from mobile_network_carrier  WHERE prefix=:identifier");
    $stmt->bindParam(":identifier", $identifier, PDO::PARAM_STR);

    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        return $result['mobile_network'];
    } else {
        return null;
    }
}


function isAgentPhoneExist($phoneno)
{
    $conn = new Db();
    $db = $conn->connect();
    $stmt = $db->prepare("SELECT * from config_agent WHERE agentId=:phoneno");
    $stmt->bindParam(":phoneno", $phoneno, PDO::PARAM_STR);

    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['agentId'] != '') {
        return true;
    } else {
        return false;
    }
}

function isBvnExist($bvn)
{
    $conn = new Db();
    $db = $conn->connect();
    $stmt = $db->prepare("SELECT * from config_user WHERE BVN_num=:bvn");
    $stmt->bindParam(":bvn", $bvn, PDO::PARAM_STR);

    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['BVN_num'] != '') {
        return true;
    } else {
        return false;
    }
}

function isPhoneExist($phoneno)
{
    $conn = new Db();
    $db = $conn->connect();
    $stmt = $db->prepare("SELECT * from config_user WHERE userId=:phoneno");
    $stmt->bindParam(":phoneno", $phoneno, PDO::PARAM_STR);

    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['userId'] != '') {
        return true;
    } else {
        return false;
    }
}

function isAgentEmailExist($email)
{
    $conn = new Db();
    $db = $conn->connect();;
    $stmt = $db->prepare("SELECT * from config_agent WHERE email =:email");
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);


    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['email'] != '') {
        return true;
    } else {
        return false;
    }
}


function isIdentifierExist($val)
{
    $conn = new Db();
    $db = $conn->connect();
    $stmt = $db->prepare("SELECT * from config_user WHERE email =:email OR userId=:userid");
    $stmt->bindParam(":email", $val, PDO::PARAM_STR);
    $stmt->bindParam(":userid", $val, PDO::PARAM_STR);

    $stmt->execute();
    $num = $stmt->rowCount();

    if ($num > 0) {

        return true;
    } else {
        return false;
    }
}


function isEmailExist($email)
{
    $conn = new Db();
    $db = $conn->connect();
    $stmt = $db->prepare("SELECT * from config_user WHERE email =:email");
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);


    $stmt->execute();
    $result = $stmt->fetch();
    if ($result['email'] != '') {
        return true;
    } else {
        return false;
    }
}

function callOutput(Request $request, Response $response, $status, $data)
{

    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus($status);
}
function validateJWT($jwt)
{
    if ($jwt) {

        try {

            $secret_key = $_ENV['JWT_SECRET_KEY'];
            $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
            $now = time();
            $issuer_claim = "www.moloyal.com";
            // Access is granted. Add code of the operation here 
            // if (
            //   $decoded->iss !== $issuer_claim ||
            //   $decoded->nbf > $now ||
            //   $decoded->exp < $now
            // ) {
            //   header('HTTP/1.1 401 Unauthorized');
            //   exit;
            // }
            return array(
                "validate_flag" => 1,
                "message" => "Access granted",
                "token_value" => $decoded

            );
        } catch (\Exception $e) {

            //return $e->getMessage();

            http_response_code(401);

            return array(
                "validate_flag" => 0,
                "message" => "Access denied.",
                "error" => $e->getMessage()
            );
        }
    } else {
        return array(
            "validate_flag" => 0,
            "message" => "Access denied.",
            "error" => "No token supplied in Authorization"
        );
    }
}

//Paystack Saved Card charge
function chargeSavedCard($card_id, $amount, $email)
{
    
     $paystack_secret_key = $_ENV['PAYSTACK_SECRET_KEY'];
    $conn = new Db();
    $db =  $conn->connect();
            $sqlv = "SELECT * FROM `recurring_payment` WHERE `email_address`=:email and `serial_number`=:card_id";
            $stmta = $db->prepare($sqlv);
            $stmta->bindParam(":email", $email, PDO::PARAM_STR);
            $stmta->bindParam(":card_id", $card_id, PDO::PARAM_STR);
        
           
              $rra= $stmta->execute();
        
              $resultsa = $stmta->fetch();
              $num = $stmta->rowCount();
              $enc_authcode=$resultsa['authorization_code'];
              $email=$resultsa['email_address'];
              $authcode=encrypt_decrypt('decrypt',$enc_authcode);
              $refs= getTransactionRef();
           
             


            //get authorization_code, email from recurring_payment table for the same customer
            $chargedata = array(
                'authorization_code' => $authcode,
                'email' => $email,
                 'reference' => $refs,
                'amount' => $amount
                );
        
          
            $payload = json_encode($chargedata);
            $ch = curl_init('https://api.paystack.co/transaction/charge_authorization');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "accept: application/json",  
            "authorization: Bearer $paystack_secret_key",
            'cache-control: no-cache',
            'content-type: application/json',
            'content-length: ' . strlen($payload))
             );
        
            $result = curl_exec($ch);
            
            return $result;
          

}


//Paystack Transfer To Bank
function paystackTransferToBank($transAmount,$bank_details)
{
    
    $paystack_secret_key = $_ENV['PAYSTACK_SECRET_KEY'];
    $refs= getTransactionRef();
        $curl = curl_init();
    $encodedbnkdetails=json_encode($bank_details);
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.paystack.co/transferrecipient',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$encodedbnkdetails,
      CURLOPT_HTTPHEADER => array(
          "authorization: Bearer $paystack_secret_key",
        //'Authorization: Bearer sk_live_283e8912e82f34b275a577b97659aec29bf778d1',
        'Accept: application/json',
        'Content-Type: text/plain',
       
      ),
    ));
    
    $res1 = curl_exec($curl);
    
    curl_close($curl);
    $kks=json_decode($res1);
    $data=$kks->data;
    
    $receipient_code=$data->recipient_code;
    $koboamt=$transAmount*100;
    $random1=random();
    $random2=alphabeth_random();
    $rando=$random1.$random2;
     $pay_details['source']="balance";
         $pay_details['amount']=$koboamt;
        $pay_details['reference']=$refs;
        
        $pay_details['reason']="Mosave Withdrawal";
        $pay_details['recipient']=$receipient_code;
        
        
    // $pay_details='{ "source": "balance", 
    //       "amount": echo $transAmount,
    //       "reference": "wsfzsgxzf2343dt45543232ez", 
    //       "recipient": ".$rec_code.", 
    //       "reason": "Holiday Flexing" 
    //     }';
    
   $encodedpayment_details= json_encode($pay_details);
        $curls = curl_init();
    
    curl_setopt_array($curls, array(
      CURLOPT_URL => 'https://api.paystack.co/transfer',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>$encodedpayment_details,
      CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $paystack_secret_key",
        'Accept: application/json',
        'Content-Type: text/plain',
       
      ),
    ));
    
    $result = curl_exec($curls);
    
    curl_close($curls);
    $decoded_result=json_decode($result);
        
      
        //echo json_encode($response);
        // $response['trans_mode']='BW';
        // $response['banktransfer_res']= $kk;
        
    return $result;
}

function validateTransferToBankRequest($request)
  {
    // validation logic goes here
    $conn = new Db();
    $db =  $conn->connect();

 $json_fields1 = json_decode($request);
    $ref1= $json_fields1->reference;
    
     $json_event = $request[event];
    $json_body = $request[data][details][body];
    $ref= $json_body[reference];
    
    
    
    
                    
    // // $json_fields = json_decode($request)->data;
    // // $ref= $json_fields->reference;
        $amountInkobo= $json_body[amount];;
        $amountInNaira=  $amountInkobo/100;


    $stmta = $db->prepare("SELECT * FROM mosave_banktransfer where transref=:ref  ");
         $stmta->bindParam(":ref", $ref,PDO::PARAM_STR);


            $stmta->execute();
            $results = $stmta->fetch();

            $db_amt = intval($results['transAmount']);

            if ($amountInNaira=== $db_amt){
                $stmtas = $db->prepare("UPDATE `mosave_banktransfer` SET `status`='$json_event', `rg`='$amountInNaira' WHERE transref=:ref  ");
                $stmtas->bindParam(":ref", $ref,PDO::PARAM_STR);
       
       
                    $stmtas->execute();
                  
               
                return true;  
             }
            
            
            

    return false; // update line based on logic
  }

function encrypt_decrypt($action, $string) {
    $output = false;
    $encrypt_method = "AES-256-CBC";
    $secret_key = $_ENV['ENCRYPT_SECRET_KEY'];
    $secret_salt = $_ENV['ENCRYPT_SALT'];
    // hash
    $key = hash('sha256', $secret_key);
    
    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_salt), 0, 16);
    if ( $action == 'encrypt' ) {
        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
    } else if( $action == 'decrypt' ) {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }
    return $output;
}


$app->run();
