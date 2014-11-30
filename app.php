<?php

/*************
 * Exceptions
 ************/
 
class HttpException extends \RuntimeException {
	
}

class ClientHttpException extends \HttpException {
	
}

class UnauthorizedHttpException extends \ClientHttpException {
	
}

class TeapotHttpException extends \ClientHttpException {
	
}

class InternatServerErrorException extends \HttpException {
	
}


/****************
 * Value Objects
 ***************/

class Request
{
	/**
	 * @var string (e.g: GET)
	 */
	public $method;
	
	/**
	 * @var string (e.g: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.65 Safari/537.36)
	 */
	public $userAgent;
	
	/**
	 * @var string (e.g: https://en.wikipedia.org/wiki/Hypertext_Transfer_Protocol)
	 */
	public $url;
}

class Response
{
	/**
	 * @var int
	 */
	public $statusCode;
	
	/**
	 * @var string
	 */
	public $body;
}


/****************
 * Business Code
 ***************/

interface HttpClientInterface
{
	public function sendGet($path, array $data = array());
	//public function sendPost();
	//public function sendDelete();
	//public function sendHead();
}

class FakeHttpClient implements \HttpClientInterface
{
	/**
	 * @var string
	 */
	private $userAgent;
	
	/**
	 * @var string
	 */
	protected $baseUrl;
	
	/**
	 * @var FakeHttpServer
	 */
	protected $fakeHttpServer;
	
	/**
	 * @param string         $baseUrl
	 * @param FakeHttpServer $fakeHttpServer
	 */
	public function __construct($baseUrl, \FakeHttpServer $fakeHttpServer)
	{
		$this->fakeHttpServer = $fakeHttpServer;
	}
	
	/**
	 * @param string $path
	 * @param array  $data
	 * 
	 * @throws \HttpException
	 * @return string
	 */
	public function sendGet($path, array $data = array())
	{
		// Call helper to turn $data int $dataString safely...
		$dataString = '';
		
		$request = new \Request();
		
		$request->method = "GET";
		$request->url = $this->baseUrl . $path . '?' . $dataString;
		
		$response = $this->fakeHttpServer->respond($request);
		
		if ($response->statusCode === 500) {
			throw new \InternatServerErrorException("We are fucked");
		} elseif ($response->statusCode >= 400 && $response->statusCode < 500) {
			throw new \ClientHttpException("Client issues..." . $response->body);
		} elseif ($response->statusCode !== 200) {
			$msg = "Response " . $response->statusCode . ", " . $response->body;
			
			throw new \HttpException($msg);
		}
		
		return $response->body;
	}
}

class FakeHttpServer
{
	/**
	 * @param Request $request
	 * 
	 * @return Response $response
	 */
	public function respond(Request $request)
	{
		$response = new \Response();
		
		$rand = rand(0, 10);
		
		if ($rand < 2) {
			$response->statusCode = 500;
		} elseif ($rand < 4) {
			$response->statusCode = 401;
		} elseif ($rand == 4) {
			$response->statusCode = 418;
			$response->body = "I really am a teapot";
		} else {
			$response->statusCode = 200;
			$response->body = "Lorem ipsum dolor sit amet, consectetur.";
		}
		
		return $response;
	}
}

class Application
{
	/**
	 * @var FakeHttpClient
	 */
	protected $httpClient;
	
	/**
	 * @param \HttpClientInterface $httpClient
	 */
	public function __construct(\HttpClientInterface $httpClient)
	{
		$this->httpClient = $httpClient;
	}
	
	/**
	 * @return bool
	 */
	public function run()
	{
		$this->lockImportantResource();
		
		try {
			$response = $this->httpClient->sendGet("/invoice/534");
			
			echo "Response: 200, OK", PHP_EOL;
			echo "Response Body: ", $response, PHP_EOL;
			
		} catch (\InternatServerErrorException $e) {
			
			echo "Response: 500 Internal Server Error", PHP_EOL;
			
			return false;
			
		} catch (\ClientHttpException $e) {
			if (stripos($e->getMessage(), 'teapot') !== false) {
				echo "Response: 418 I'm a teapot (RFC 2324)", PHP_EOL;
				 
			} else {
				echo "Response: 401 Unauthorized", PHP_EOL;
			}
			
			return false;
			
		} catch (\HttpException $e) {
			echo $e->getMessage(), PHP_EOL;
			
			return false;
			
		} catch (\Exception $e) {
			echo "No Response", PHP_EOL;
			
			return false;
			
		}/* finally {
			$this->releaseImportantResource();
		}*/
		
		return true;
	}
	
	protected function lockImportantResource()
	{
		echo "Locked Important Resource", PHP_EOL;
	}
	
	protected function releaseImportantResource()
	{
		echo "Released Important Resource", PHP_EOL;
	}
}


/****************
 * Main
 ***************/

$fakeHttpServer = new \FakeHttpServer();
$fakeHttpClient = new \FakeHttpClient("http://yourserver.com/api", $fakeHttpServer);

$app = new \Application($fakeHttpClient);

$app->run();

