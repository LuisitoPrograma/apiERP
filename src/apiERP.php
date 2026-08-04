<?php

//STRICT TYPES
declare(strict_types=1);

//NAMESPACE
namespace apiERP;

//USES GUZZLE HTTP
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

//CLASS APIERP
class apiERP {
private $client;

//WS GENERALES
private static $webservices = [

//WS GENERALES - CREATE COMPANY
'ws_createCompany' => "https://apierp.dev/api/company/create/",

//WS GENERALES - READ COMPANY
'ws_readCompany' => "https://apierp.dev/api/company/read/",

//WS GENERALES - UPDATE COMPANY
'ws_updateCompany' => "https://apierp.dev/api/company/update/",

//WS GENERALES - READ COMPANY ENDPOINTS
'ws_readCompanyEndPoints' => "https://apierp.dev/api/company/endpoints/read/",

//WS GENERALES - READ COMPANY CATALOGS
'ws_readCompanyCatalogs' => "https://apierp.dev/api/company/catalogs/read/"
];

public function __construct() {
$this->client = new Client([
'timeout' => 15,
'verify' => true
]);
}

//METODO PARA OBTENER LOS WEBSERVICES
private function getWebservices(string $key): string {
if (!isset(self::$webservices[$key])) {
throw new \Exception("Webservice para '$key' no definido.");
}
return self::$webservices[$key];
}

//METODO PARA VALIDAR ENDPOINTS DINAMICOS
private function validateEndpoint(string $endpoint): string {
$endpoint = trim($endpoint);

if ($endpoint === '') {
throw new \InvalidArgumentException("El endpoint no puede estar vacío.");
}

if (preg_match('/[\x00-\x1F\x7F]/', $endpoint)) {
throw new \InvalidArgumentException("El endpoint contiene caracteres no permitidos.");
}

if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
throw new \InvalidArgumentException("El endpoint proporcionado no es una URL válida.");
}

$urlParts = parse_url($endpoint);

if (
!is_array($urlParts) ||
empty($urlParts['scheme']) ||
empty($urlParts['host'])
) {
throw new \InvalidArgumentException(
"El endpoint debe contener un protocolo y un dominio válidos."
);
}

if (strtolower($urlParts['scheme']) !== 'https') {
throw new \InvalidArgumentException(
"El endpoint debe utilizar obligatoriamente el protocolo HTTPS."
);
}

// No se permiten credenciales dentro de la URL:
// https://usuario:clave@dominio.com
if (isset($urlParts['user']) || isset($urlParts['pass'])) {
throw new \InvalidArgumentException(
"El endpoint no puede contener credenciales dentro de la URL."
);
}

if (
filter_var(
$urlParts['host'],
FILTER_VALIDATE_DOMAIN,
FILTER_FLAG_HOSTNAME
) === false
) {
throw new \InvalidArgumentException(
"El dominio o subdominio del endpoint no es válido."
);
}

return $endpoint;
}

//METODO PARA MANEJAR LAS SOLICITUDES API
private function sendRequest(?string $key, array $data, bool $async = false, ?string $endpoint = null): ?array {
try {
if($endpoint === null && ($key === null || $key === '')){
throw new \InvalidArgumentException("No se ha definido el endpoint de la solicitud.");
}
$ws_url = $endpoint !== null ? $this->validateEndpoint($endpoint) : $this->getWebservices($key);
$options = ['json' => $data];
if($endpoint !== null){
$options['allow_redirects'] = false;
}
if($async){
$this->client->postAsync($ws_url, $options + ['timeout' => 1])->then(null, function($exception){});
return null;
}
$response = $this->client->post($ws_url, $options);
$returnResponse = json_decode($response->getBody()->getContents(), true);
if(json_last_error() !== JSON_ERROR_NONE || !is_array($returnResponse)){
throw new \Exception("La respuesta del endpoint no contiene un JSON válido.");
}
return $this->validateApiERPResponse($returnResponse);
} catch (RequestException $e){
if($async){
return null;
}
return $this->validateApiERPResponse([
'success' => false,
'message' => 'No se pudo conectar con el servicio apiERP.',
]);
} catch (\Throwable $e){
if($async){
return null;
}
return $this->validateApiERPResponse([
'success' => false,
'message' => 'No se pudo procesar la solicitud.',
]);
}
}

//METODO PARA VALIDAR RESPUESTAS API
private function validateApiERPResponse(mixed $response): array {

//BASIC VALIDATIONS
if(!is_array($response)){
throw new \UnexpectedValueException('La respuesta no es válida.');
}
if(count($response) !== 2){
throw new \UnexpectedValueException('La respuesta no es válida.');
}
if(!array_key_exists('success', $response) || !array_key_exists('message', $response)){
throw new \UnexpectedValueException('La respuesta no es válida.');
}
if(!is_bool($response['success'])){
throw new \UnexpectedValueException('La respuesta no es válida.');
}
if($response['success'] === false && !is_string($response['message'])){
throw new \UnexpectedValueException('La respuesta no es válida.');
}
if($response['success'] === true && !is_string($response['message']) && !is_array($response['message'])){
throw new \UnexpectedValueException('La respuesta no es válida.');
}

//VALIDATE RESPONSE ERROR
if($response['success'] === false){
echo json_encode(['success' => false, 'message' => $response['message']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
}

//RETURN
return [
'success' => $response['success'],
'message' => $response['message']
];
}

//FUNCTION - CREATE COMPANY
public function createCompany(array $data, bool $async = false): ?array {
return $this->sendRequest('ws_createCompany', $data, $async);
}

//FUNCTION - READ COMPANY
public function readCompany(array $data, bool $async = false): ?array {
return $this->sendRequest('ws_readCompany', $data, $async);
}

//FUNCTION - UPDATE COMPANY
public function updateCompany(array $data, bool $async = false): ?array {
return $this->sendRequest('ws_updateCompany', $data, $async);
}

//FUNCTION - READ COMPANY ENDPOINTS
public function readCompanyEndPoints(array $data, bool $async = false): ?array {
return $this->sendRequest('ws_readCompanyEndPoints', $data, $async);
}

//FUNCTION - READ COMPANY CATALOGS
public function readCompanyCatalogs(array $data, bool $async = false): ?array {
return $this->sendRequest('ws_readCompanyCatalogs', $data, $async);
}

//FUNCTION - CREATE COMPANY FASYB
public function createCompanyFasyb(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - READ COMPANY FASYB
public function readCompanyFasyb(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - UPDATE COMPANY FASYB
public function updateCompanyFasyb(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - CREATE COMPANY FASYB OPERATIONS
public function createCompanyFasybOperations(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - READ COMPANY FASYB OPERATIONS
public function readCompanyFasybOperations(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - UPDATE COMPANY FASYB OPERATIONS
public function updateCompanyFasybOperations(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - CREATE COMPANY FASYB RECORDS
public function createCompanyFasybRecords(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - READ COMPANY FASYB RECORDS
public function readCompanyFasybRecords(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - UPDATE COMPANY FASYB RECORDS
public function updateCompanyFasybRecords(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - SEND EMAIL
public function sendEmail(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}

//FUNCTION - SEND WHATSAPP
public function sendWhatsApp(array $data, string $endpoint, bool $async = false): ?array {
return $this->sendRequest(null, $data, $async, $endpoint);
}
}