<?php

ini_set('display_errors', 'Off');

use App\Models\DB;
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
});

$app->post('/confirm/jwt/test', function (Request $req, Response $res) {

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


        } catch (PDOException $e) {
            $response['error']   = true;
            $response['message'] = $e->getMessage();
            echo json_encode($response);
        }
    } else {
        echo json_encode($result);
    }
});


$app->post('/paystack/callback', function (Request $req, Response $response) {
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



    $input = @file_get_contents("php://input");
    $event = json_decode($input);
    // Do something with $event
    http_response_code(200); // PHP 5.4
    
$response->getBody()->write(json_encode($event));
return $response
    ->withHeader('content-type', 'application/json')
    ->withStatus(401);

    
});


$app->post('/db/test', function (Request $req, Response $response) {
    //print_r($_ENV);

    $conn = new Db();
    $db = $conn->connect();
   $res= $_ENV['JWT_SECRET_KEY'];
//echo $_SERVER['NAME'] . "\n";

$docRoot =__DIR__;
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


                        $secret_key = $_ENV['JWT_SECRET_KEY'];
                        $issuer_claim = "www.moloyal.com"; // this can be the servername
                        $audience_claim = "www.moloyal.com";
                        $issuedat_claim = time(); // issued at
                        $notbefore_claim = $issuedat_claim + 10; //not before in seconds
                        $expire_claim = $issuedat_claim + 600000; // expire time in seconds
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
    $mail->Host   = "premium42.web-hosting.com";
    $mail->Port       = 465;

    $mail->SMTPSecure = "ssl";
    //Sets SMTP authentication. Utilizes the Username and Password variables
    $mail->Username = 'care@moloyal.com';          //Sets SMTP username
    $mail->Password = 'Welcome@13#12';
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
    $characters = 8;
    $letters    = '234567890';
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
        CURLOPT_URL => BASE_URL . '/mosave/script/api/users/register',
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

$app->run();
