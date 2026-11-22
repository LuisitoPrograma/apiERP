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
'ws_masterEndpoints' => "https://apierp-master.s3.us-west-1.amazonaws.com/configMasterEndPoints.json",

//WS GENERALES - DOMINIOS E IPS AUTORIZADOS
'ws_authorizedDomains' => "https://apierp-master.s3.us-west-1.amazonaws.com/authorizedDomains.json",

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
if($endpoint === '' || preg_match('/[\x00-\x1F\x7F]/', $endpoint) || filter_var($endpoint, FILTER_VALIDATE_URL) === false) throw new \InvalidArgumentException('El endpoint proporcionado no es una URL valida.');
$urlParts = parse_url($endpoint);
if(!is_array($urlParts) || empty($urlParts['scheme']) || empty($urlParts['host']) || strtolower($urlParts['scheme']) !== 'https' || isset($urlParts['user']) || isset($urlParts['pass'])) throw new \InvalidArgumentException('El endpoint debe utilizar HTTPS y no contener credenciales.');
$apierp_host = strtolower($urlParts['host']);
$apierp_isIp = filter_var($apierp_host, FILTER_VALIDATE_IP) !== false;
if(!$apierp_isIp && filter_var($apierp_host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) throw new \InvalidArgumentException('El dominio del endpoint no es valido.');
$apierp_authorized = $this->apierp_getCachedAuthorizedDomains();
foreach($apierp_authorized[$apierp_isIp ? 'ips' : 'domains'] as $apierp_allowed){
$apierp_allowed = strtolower(trim($apierp_allowed));
if($apierp_isIp && $apierp_host === $apierp_allowed) return $endpoint;
if(!$apierp_isIp && ($apierp_host === $apierp_allowed || str_ends_with($apierp_host, '.' . $apierp_allowed))) return $endpoint;
}
throw new \InvalidArgumentException('El dominio o IP del endpoint no esta autorizado.');
}

//METODO PARA MANEJAR LAS SOLICITUDES API
private function sendRequest(?string $key, array $data, bool $async = false, ?string $endpoint = null, ?int $timeout = null): ?array {
try {
if($endpoint === null && ($key === null || $key === '')){
throw new \InvalidArgumentException("No se ha definido el endpoint de la solicitud.");
}
$ws_url = $endpoint !== null ? $this->validateEndpoint($endpoint) : $this->getWebservices($key);
$options = ['json' => $data];
if($timeout !== null && !$async){$options['timeout'] = $timeout; $options['http_errors'] = false;}
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

//VALIDAR LA ESTRUCTURA DEL JSON DE ENDPOINTS
private function isValidMasterEndpointsConfig(mixed $config): bool {
if(!is_array($config) || !isset($config['setMasterEndPoints']) || !is_array($config['setMasterEndPoints']) || $config['setMasterEndPoints'] === []) return false;
foreach($config['setMasterEndPoints'] as $entry){
if(!is_array($entry) || !is_string($entry['setMasterAuthToken'] ?? null) || !is_array($entry['setCompanyEndPoints'] ?? null)) return false;
}
return true;
}

//VALIDAR LA ESTRUCTURA DEL JSON DE DOMINIOS AUTORIZADOS
private function apierp_isValidAuthorizedDomains(mixed $config): bool {
if(!is_array($config) || !isset($config['domains'], $config['ips']) || !is_array($config['domains']) || !is_array($config['ips'])) return false;
foreach(array_merge($config['domains'], $config['ips']) as $apierp_value){
if(!is_string($apierp_value) || trim($apierp_value) === '') return false;
}
return true;
}

//CACHE PRIVADA DE ENDPOINTS MAESTROS
private function getCachedMasterEndpoints(): array {
return $this->apierp_getCachedS3Json('ws_masterEndpoints', [$this, 'isValidMasterEndpointsConfig']);
}

//CACHE PRIVADA DE DOMINIOS E IPS AUTORIZADOS
private function apierp_getCachedAuthorizedDomains(): array {
return $this->apierp_getCachedS3Json('ws_authorizedDomains', [$this, 'apierp_isValidAuthorizedDomains']);
}

//DESCARGAR Y COMPARTIR CONFIGURACIONES S3 CON ACTUALIZACION DIARIA
private function apierp_getCachedS3Json(string $apierp_key, callable $apierp_validator): array {
$apierp_cacheIdentity = PHP_OS_FAMILY === 'Windows' ? (string)getenv('USERNAME') : (string)posix_geteuid();
$directory = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'apierp_' . hash('sha256', __DIR__ . '|' . $apierp_cacheIdentity);
$cacheFile = $directory . '/' . $apierp_key . '.json';
$stateFile = $directory . '/.' . $apierp_key . '.state';
$lockFile = $directory . '/.' . $apierp_key . '.lock';
$today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
$isWindows = PHP_OS_FAMILY === 'Windows';

//PREPARAR DIRECTORIO COMPARTIDO
if(!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('No se pudo crear el directorio de configuraciones.');
if(is_link($directory) || !is_writable($directory)) throw new \RuntimeException('El directorio de configuraciones no esta disponible.');
if(!$isWindows && !@chmod($directory, 0700)) throw new \RuntimeException('No se pudo restringir el directorio de configuraciones.');
$accessFile = $directory . '/.htaccess';
if(is_link($accessFile)) throw new \RuntimeException('La proteccion HTTP no es valida.');
if(!is_file($accessFile) || @file_get_contents($accessFile) !== "Require all denied\n"){
if(@file_put_contents($accessFile, "Require all denied\n", LOCK_EX) !== strlen("Require all denied\n")) throw new \RuntimeException('No se pudo proteger el directorio de configuraciones.');
}
if(!$isWindows && !@chmod($accessFile, 0600)) throw new \RuntimeException('No se pudo restringir la proteccion HTTP.');
if(is_link($lockFile) || is_link($stateFile) || is_link($cacheFile)) throw new \RuntimeException('La ruta del cache no es valida.');

//LECTURA RAPIDA DE CONFIGURACION ACTUALIZADA
$state = is_file($stateFile) ? trim((string)@file_get_contents($stateFile)) : '';
if(!$isWindows && $state === $today . '|ok' && is_file($cacheFile)){
$raw = @file_get_contents($cacheFile);
$config = is_string($raw) ? json_decode($raw, true) : null;
if($apierp_validator($config)) return $config;
}

//BLOQUEAR ACTUALIZACION CONCURRENTE
$lock = @fopen($lockFile, 'c');
if($lock === false) throw new \RuntimeException('No se pudo crear el bloqueo del cache.');
try {
if(!$isWindows && !@chmod($lockFile, 0600)) throw new \RuntimeException('No se pudo restringir el bloqueo del cache.');
if(!@flock($lock, LOCK_EX)) throw new \RuntimeException('No se pudo bloquear el cache.');
$state = is_file($stateFile) ? trim((string)@file_get_contents($stateFile)) : '';
$raw = is_file($cacheFile) ? @file_get_contents($cacheFile) : false;
$config = is_string($raw) ? json_decode($raw, true) : null;
if($state === $today . '|ok' && $apierp_validator($config)) return $config;
if($state === $today . '|failed|missing' || ($state === $today . '|failed|cached' && is_file($cacheFile))) throw new \RuntimeException('La actualizacion diaria de la configuracion fallo. Elimine su archivo de estado para reintentar.');

//REGISTRAR INTENTO DIARIO ANTES DE CONSULTAR S3
$failedState = $today . '|failed|' . (is_file($cacheFile) ? 'cached' : 'missing');
if(@file_put_contents($stateFile, $failedState, LOCK_EX) !== strlen($failedState)) throw new \RuntimeException('No se pudo registrar el intento de actualizacion.');
if(!$isWindows && !@chmod($stateFile, 0600)) throw new \RuntimeException('No se pudo restringir el estado del cache.');
try {
$response = $this->client->get($this->getWebservices($apierp_key), ['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false]);
$remoteRaw = $response->getBody()->getContents();
if(strlen($remoteRaw) > 8388608) throw new \RuntimeException('La configuracion supera el tamano permitido.');
$remote = json_decode($remoteRaw, true, 512, JSON_THROW_ON_ERROR);
if(!$apierp_validator($remote)) throw new \RuntimeException('La configuracion descargada no es valida.');

//REEMPLAZAR ARCHIVO COMPLETO DE FORMA ATOMICA
$tempFile = @tempnam($directory, '.' . $apierp_key . '_');
if($tempFile === false) throw new \RuntimeException('No se pudo crear el archivo temporal.');
try {
if(!$isWindows && !@chmod($tempFile, 0600)) throw new \RuntimeException('No se pudo restringir el archivo temporal.');
if(@file_put_contents($tempFile, $remoteRaw) !== strlen($remoteRaw) || !@rename($tempFile, $cacheFile)) throw new \RuntimeException('No se pudo guardar el archivo de configuracion.');
} finally {
if(is_file($tempFile)) @unlink($tempFile);
}
$okState = $today . '|ok';
if(@file_put_contents($stateFile, $okState, LOCK_EX) !== strlen($okState)) throw new \RuntimeException('No se pudo confirmar el cache.');
return $remote;
} catch (\Throwable $e){
throw new \RuntimeException('No se pudo actualizar la configuracion diaria.', 0, $e);
}
} finally {
@flock($lock, LOCK_UN);
fclose($lock);
}
}

//RESOLVER EL ENDPOINT OBLIGATORIO POR TIPO
private function resolveCompanyEndpoint(string $masterAuthToken, int $type): string {
if($masterAuthToken === '' || $type <= 0) throw new \InvalidArgumentException('El Master Auth Token y el tipo de endpoint son obligatorios.');
$response = $this->readCompanyEndPoints(['setMasterAuthToken' => $masterAuthToken, 'setCompanyEndPointType' => $type]);
if(($response['success'] ?? false) !== true) throw new \RuntimeException('No se pudo consultar el endpoint solicitado.');
$endpoints = $response['message']['setCompanyEndPoints'] ?? null;
if(!is_array($endpoints) || count($endpoints) !== 1 || !is_string($endpoints[0]['setCompanyEndPoint'] ?? null)) throw new \RuntimeException('El tipo solicitado debe tener exactamente un endpoint valido.');
return $endpoints[0]['setCompanyEndPoint'];
}

//ENVIAR SOLICITUD AL TIPO OBLIGATORIO
private function apierp_sendCompanyEndpointRequest(array $data, string $masterAuthToken, int $type, bool $async, ?int $timeout = null): ?array {
try {
return $this->sendRequest(null, $data, $async, $this->resolveCompanyEndpoint($masterAuthToken, $type), $timeout);
} catch (\Throwable $e){
return $async ? null : ['success' => false, 'message' => 'No se pudo resolver el endpoint solicitado.'];
}
}

//FUNCTION - READ COMPANY ENDPOINTS
public function readCompanyEndPoints(array $data): array {
try {
$masterToken = $data['setMasterAuthToken'] ?? null;
if(!is_string($masterToken) || $masterToken === '') throw new \RuntimeException('Master AuthToken Inválido');
$config = $this->getCachedMasterEndpoints();
$selected = null;
foreach($config['setMasterEndPoints'] as $entry){
if(hash_equals($entry['setMasterAuthToken'], $masterToken)){
$selected = $entry;
break;
}
}
if($selected === null) throw new \RuntimeException('Master AuthToken Inválido');
$endpoints = $selected['setCompanyEndPoints'];
if(isset($data['setCompanyEndPointType']) && (int)$data['setCompanyEndPointType'] !== 0){
$type = (int)$data['setCompanyEndPointType'];
$endpoints = array_values(array_filter($endpoints, static function($entry) use ($type): bool {
return is_array($entry) && isset($entry['setCompanyEndPointType']) && (int)$entry['setCompanyEndPointType'] === $type;
}));
}
return ['success' => true, 'message' => ['setCompanyEndPoints' => $endpoints]];
} catch (\Throwable $e){
return ['success' => false, 'message' => $e->getMessage()];
}
}

//FUNCTION - READ COMPANY CATALOGS
public function readCompanyCatalogs(array $data, bool $async = false): ?array {
return $this->sendRequest('ws_readCompanyCatalogs', $data, $async);
}

//FUNCTION - CREATE COMPANY FASYB
public function createCompanyFasyb(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 7, $async);
}

//FUNCTION - READ COMPANY FASYB
public function readCompanyFasyb(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 8, $async);
}

//FUNCTION - UPDATE COMPANY FASYB
public function updateCompanyFasyb(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 9, $async);
}

//FUNCTION - CREATE COMPANY FASYB OPERATIONS
public function createCompanyFasybOperations(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 11, $async);
}

//FUNCTION - READ COMPANY FASYB OPERATIONS
public function readCompanyFasybOperations(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 12, $async);
}

//FUNCTION - UPDATE COMPANY FASYB OPERATIONS
public function updateCompanyFasybOperations(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 13, $async);
}

//FUNCTION - CREATE COMPANY FASYB RECORDS
public function createCompanyFasybRecords(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 14, $async);
}

//FUNCTION - READ COMPANY FASYB RECORDS
public function readCompanyFasybRecords(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 15, $async);
}

//FUNCTION - UPDATE COMPANY FASYB RECORDS
public function updateCompanyFasybRecords(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 16, $async);
}

//FUNCTION - SEND MESSAGE EMAIL
public function sendMessageEmail(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 21, $async);
}

//FUNCTION - GET QR WHATSAPP
public function getQrWhatsApp(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 22, $async);
}

//FUNCTION - SEND MESSAGE WHATSAPP
public function sendMessageWhatsApp(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 23, $async);
}

//FUNCTION - GET MESSAGE WHATSAPP
public function getMessageWhatsApp(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 24, $async);
}

//FUNCTION - CALL ARTIFICIAL INTELLIGENCE
public function callArtificialIntelligence(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 25, $async);
}

//FUNCTION - CONVERT TEXT TO SPEECH
public function convertTextToSpeech(array $data, string $masterAuthToken, bool $async = false): ?array {
if($async) return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 27, true);
try{
$endpoint = $this->validateEndpoint($this->resolveCompanyEndpoint($masterAuthToken, 27));
$response = $this->client->post($endpoint, ['json' => $data, 'allow_redirects' => false, 'http_errors' => false, 'timeout' => 35, 'connect_timeout' => 5, 'stream' => true]);
$status = $response->getStatusCode();
$contentType = strtolower($response->getHeaderLine('Content-Type'));
$stream = $response->getBody();
$raw = '';
while(!$stream->eof()){
$chunk = $stream->read(65536);
if($chunk === '') break;
$raw .= $chunk;
if(strlen($raw) > 67108864) throw new \RuntimeException('El audio recibido supera el tamaño máximo permitido.');
}
if($status === 200 && str_contains($contentType, 'audio/wav')){
$expectedBytes = (int)$response->getHeaderLine('Content-Length');
if($expectedBytes > 0 && strlen($raw) !== $expectedBytes) throw new \RuntimeException('El WAV fue recibido incompleto.');
if(strlen($raw) < 44 || substr($raw, 0, 4) !== 'RIFF' || substr($raw, 8, 4) !== 'WAVE') throw new \RuntimeException('La API TTS no devolvió un archivo WAV válido.');
return ['success' => true, 'message' => ['mime_type' => 'audio/wav', 'audio_base64' => base64_encode($raw), 'bytes' => strlen($raw)]];
}
$decoded = json_decode($raw, true);
if(!is_array($decoded)) throw new \RuntimeException('La API TTS no devolvió una respuesta JSON válida.');
$result = $this->validateApiERPResponse($decoded);
if($status >= 400 && $result['success']) return ['success' => false, 'message' => 'La API TTS rechazó la solicitud.'];
return $result;
}catch(\Throwable $e){
return ['success' => false, 'message' => 'No se pudo procesar la solicitud TTS.'];
}
}

//FUNCTION - UPLOAD FILES TO S3
public function uploadFilesToS3(array $data, string $masterAuthToken, bool $async = false): ?array {
return $this->apierp_sendCompanyEndpointRequest($data, $masterAuthToken, 28, $async, 90);
}
}