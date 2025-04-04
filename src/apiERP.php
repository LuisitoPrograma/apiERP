<?php

namespace apiERP;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class apiERP {
    private $client;
    
    //WEBSERVICES URL
    private static $webservices = [
        'ws_getCalculos' => "https://apierp.dev/api/invoice/peru/calc/",
        'ws_createImg' => "https://apierp.dev/api/img/",
        'ws_createCompany' => "https://apierp.dev/api/company/create/",
        'ws_createProduct' => "https://apierp.dev/api/product/create/",
        'ws_createClient' => "https://apierp.dev/api/client/create/",
        'ws_generarPdf' => "https://apierp.dev/api/pdf/create/",
        'ws_generarInvoice' => "https://apierp.dev/api/invoice/peru/create/",
        'ws_apiPrint' => "https://apierp.dev/api/print/send/"
    ];

    public function __construct() {
        $this->client = new Client([
            'timeout' => 15,
            'verify' => false
        ]);
    }
    
    //METODO PARA OBTENER LOS WEBSERVICES
    private function getWebservices(string $key): string {
        if (!isset(self::$webservices[$key])) {
            throw new \Exception("Webservice para '$key' no definido.");
        }
        return self::$webservices[$key];
    }
    
    //METODO PARA MANEJAR LAS SOLICITUDES API
    private function sendRequest(string $key, array $data, bool $async = false): ?array {
        try {
            $ws_url = $this->getWebservices($key);

            if ($async) {
                // Ejecución en segundo plano
                $this->client->postAsync($ws_url, [
                    'json' => $data,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'timeout' => 1, // Tiempo máximo para enviar la solicitud
                ])->then(
                    function ($response) {
                        // Opcional: Manejar respuesta en segundo plano
                    },
                    function ($exception) {
                        error_log("Error en solicitud as�ncrona: " . $exception->getMessage());
                    }
                )->wait(false); // No esperar la finalización
                return null; // No devuelve datos en modo asíncrono
            } else {
                $response = $this->client->post($ws_url, [
                    'json' => $data,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);

                // Obtener el cuerpo de la respuesta
                $responseBody = $response->getBody()->getContents();

                // Decodificar la respuesta JSON
                $returnResponse = json_decode($responseBody, true);

                if ($returnResponse === null) {
                    throw new \Exception("Error al decodificar la respuesta JSON.");
                }

                return $returnResponse;
            }
        } catch (RequestException $e) {
            return [
            'estado' => 'ERROR',
            'codigo_error' => 'HTTP_EXCEPTION',
            'mensaje_error' => $e->getMessage(),
        ];
        } catch (\Exception $e) {
            return [
            'estado' => 'ERROR',
            'codigo_error' => 'GENERAL_EXCEPTION',
            'mensaje_error' => $e->getMessage(),
        ];
        }
    }

    //FUNCION GETCALCULOS
    public function getCalculos(array $data, bool $async = false): ?array {
        return $this->sendRequest('ws_getCalculos', $data, $async);
    }
    
    //FUNCION GENERARPDF
    public function generarPdf(array $data, bool $async = false): ?array {
        return $this->sendRequest('ws_generarPdf', $data, $async);
    }
    
    //FUNCION GENERARINVOICE
    public function generarInvoice(array $data, bool $async = false): ?array {
        return $this->sendRequest('ws_generarInvoice', $data, $async);
    }

    //FUNCION apiPrint
    public function apiPrint(array $data, bool $async = false): ?array {
        return $this->sendRequest('ws_apiPrint', $data, $async);
    }

    //FUNCION createCompany
    public function createCompany(array $data, bool $async = false): ?array {
        return $this->sendRequest('ws_createCompany', $data, $async);
    }
}
?>