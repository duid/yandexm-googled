<?php
require_once __DIR__ . '/vendor/autoload.php';

define('APPLICATION_NAME', 'Google Sheets API PHP Quickstart');
define('CREDENTIALS_PATH', 'token.json');
define('CLIENT_SECRET_PATH', 'client_secret.json');
define('SCOPES', implode(' ', array(Google_Service_Sheets::SPREADSHEETS)));

require_once(dirname(__FILE__) . '/lib/YandexMoney.php');
#require_once(dirname(__FILE__) . '/sample/consts.php');
require_once(dirname(__FILE__) . '/config.php');




$sha1 = sha1( $_POST['notification_type'] . '&'. $_POST['operation_id']. '&' . $_POST['amount'] . '&643&' . $_POST['datetime'] . '&'. $_POST['sender'] . '&' . $_POST['codepro'] . '&' . $secret_key. '&' . $_POST['label'] );


if($sha1 == $_POST['sha1_hash'])
{
#if (php_sapi_name() != 'cli') {
#  throw new Exception('This application must be run on the command line.');
#}
	function getClient()
	{
		$client = new Google_Client();
		$client->setApplicationName(APPLICATION_NAME);
		$client->setScopes(SCOPES);
		$client->setAuthConfig(CLIENT_SECRET_PATH);
		$client->setAccessType('offline');
		$client->setApprovalPrompt('force');
		$credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);

		if(file_exists($credentialsPath))
		{
			$accessToken = json_decode(file_get_contents($credentialsPath), true);
		}else
		{
			$authUrl = $client->createAuthUrl();
			printf("Open the following link in your browser:\n%s\n", $authUrl);
			print 'Enter verification code: ';
			$authCode = trim(fgets(STDIN));
			$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

			if(!file_exists(dirname($credentialsPath)))
			{
				mkdir(dirname($credentialsPath), 0700, true);
			}
			file_put_contents($credentialsPath, json_encode($accessToken));
			printf("Credentials saved to %s\n", $credentialsPath);
		}
		$client->setAccessToken($accessToken);

		if($client->isAccessTokenExpired())
		{
			$refresh_token = $accessToken['refresh_token'];
			$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			$new_token = $client->getAccessToken();
			if(!isset($new_token['refresh_token']))
			{
				$new_token['refresh_token'] = $refresh_token;
			}
			file_put_contents($credentialsPath, json_encode($new_token));
		}
		return $client;
	}

	function expandHomeDirectory($path)
	{
		$homeDirectory = getenv('HOME');
		if(empty($homeDirectory))
		{
			$homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
		}
		return str_replace('~', realpath($homeDirectory), $path);
	}

	$ym		= new YandexMoney(CLIENT_ID, './ym.log');
	$operation_id	= (int)$_POST['operation_id'];

	$resp = $ym->operationDetail($token, $operation_id);

//	$message .= "\r\n". var_export($_POST, 1) . var_export($resp);


	if($resp->isSuccess())
	{
		$reflection = new ReflectionClass($resp);

		$property	= $reflection->getProperty("details");
		$property->setAccessible(true);
		$email		= $property->getValue($resp);

		$property	= $reflection->getProperty("operationId");
		$property->setAccessible(true);
		$opid		= $property->getValue($resp);

		$client = getClient();
		$service = new Google_Service_Sheets($client);
		$range = "A1:B";
		$valueRange= new Google_Service_Sheets_ValueRange();
		$valueRange->setValues(["values" => [$operation_id, $email]]);
		$conf = ["valueInputOption" => "RAW"];
		$ins = ["insertDataOption" => "INSERT_ROWS"];
		$service->spreadsheets_values->append($spreadsheetId, $range, $valueRange, $conf, $ins);
	}
}