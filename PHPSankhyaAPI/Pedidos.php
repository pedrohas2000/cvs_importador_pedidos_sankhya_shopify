<?php

namespace PHPSankhyaAPI;


class Pedidos extends CRUDServiceProvider {
    public $itens;
    const INCLUIR_PEDIDO_SERVICE_NAME = "CACSP.incluirNota";
    const UPDATE_PEDIDO_SERVICE_NAME = "DatasetSP.save";
    const FATURAR_PEDIDO_SERVICE_NAME = "SelecaoDocumentoSP.faturar";
    const CONFIRMAR_PEDIDO_SERVICE_NAME = "CACSP.confirmarNota";

    public function __construct($http_client){
         $this->http_client = $http_client;
         $this->root_entity = "CabecalhoNota";
         $this->itens = new Itens($http_client);
     }



    public function insert_pedido($fields =[], $jsessionid)
    {

        $data = $this->get_pedido_insert_query_fields($fields);

        $response = $this->http_client->post(SankhyaAPI::API_PATH_COM . $this::INCLUIR_PEDIDO_SERVICE_NAME . "&mgeSession=$jsessionid&outputType=" . $this->output_type,
            [\GuzzleHttp\RequestOptions::JSON => $data]);

        return $this->response_to_object_array($response->getBody());
    }



    public function update_pedido($chave, $fields, $jsessionid)
    {
    
        $values = new \stdClass();
        $cont = 0;
        foreach($fields as $field_name => $field_value)
        {
            $values->{(string)$cont} = $field_value;
            $cont++;
        }

        $field_names = array_keys($fields);

        $data = array("serviceName" => "DatasetSP.save", 'requestBody' => 
            array("entityName" => $this->root_entity, 
            'standAlone' => false,
            'fields' => $field_names,
            'records' => array(array("pk" => array("NUNOTA" => $chave), "values" => $values))
            )
        );
 
         $response = $this->http_client->post(SankhyaAPI::API_PATH . $this::UPDATE_PEDIDO_SERVICE_NAME . "&mgeSession=$jsessionid&outputType=" . $this->output_type,
         [\GuzzleHttp\RequestOptions::JSON => $data]);
    

        return $this->response_to_object_array($response->getBody());
    }

    public function faturar_pedido($chave, $tipoOperacao,  $serie, $jsessionid, $verbose = false)
    {
    
 
        $data = array("serviceName" => $this::FATURAR_PEDIDO_SERVICE_NAME, 'requestBody' => 
                    array('notas' =>
                            array("codTipOper" => $tipoOperacao, 
                            'dtFaturamento' =>  date("d/m/Y"),
                            "tipoFaturamento" => "FaturamentoNormal", 
                            "dataValidada" => true, 
                            "serie" => $serie, 
                            'notasComMoeda' => (object)[],
                            'nota' => array(array("$" => $chave)),
                            "codLocalDestino" => "",
                            "faturarTodosItens" => true,
                            "umaNotaParaCada" => false,
                            "ehWizardFaturamento" => true,
                            "dtFixaVenc" => "",
                            "ehPedidoWeb" => false,
                            "nfeDevolucaoViaRecusa" => false
                            )
                        )
                    );
 
 
        If ($verbose)
        {
            echo "Faturando pedido $chave com os seguintes dados: <br>";
            print_r($data);
        }
       
          

         $response = $this->http_client->post(SankhyaAPI::API_PATH_COM . $this::FATURAR_PEDIDO_SERVICE_NAME . "&mgeSession=$jsessionid&outputType=" . $this->output_type,
         [\GuzzleHttp\RequestOptions::JSON => $data]);

         If ($verbose)
         {
            echo "Resposta da tentativa de faturamento: <br>";
            print_r($response);
         }
       
         
        return $this->response_to_object_array($response->getBody());
    }


    public function confirmar_pedido($chave, $jsessionid)
    {
        $data = [
            "serviceName" => self::CONFIRMAR_PEDIDO_SERVICE_NAME,
            "requestBody" => [
                "nota" => [
                    "confirmacaoCentralNota" => "false",
                    "ehPedidoWeb" => "false",
                    "atualizaPrecoItemPedCompra" => "false",
                    "ownerServiceCall" => "CentralNotas",
                    "NUNOTA" => [
                        "$" => $chave
                    ]
                ]
            ]
        ];

        $response = $this->http_client->post(
            SankhyaAPI::API_PATH_COM . self::CONFIRMAR_PEDIDO_SERVICE_NAME . "&mgeSession=$jsessionid&outputType=" . $this->output_type,
            [\GuzzleHttp\RequestOptions::JSON => $data]
        );

        return $this->response_to_object_array($response->getBody());
    }



    public function alterarStatusPedido($nunota, $status, $jsessionid)
    {
    
        $values = new \stdClass();
        $values->{"0"} = $status;
        $data = array("serviceName" => "DatasetSP.save", 'requestBody' => 
            array("entityName" => "CabecalhoNota", 
            'standAlone' => false,
            'fields' => ["STATUSNOTA"],
            'records' => array(array("pk" => array("NUNOTA" => $nunota), "values" => $values))
            )
        );
 
         $response = $this->http_client->post(SankhyaAPI::API_PATH . $this::UPDATE_PEDIDO_SERVICE_NAME . "&mgeSession=$jsessionid&outputType=" . $this->output_type,
         [\GuzzleHttp\RequestOptions::JSON => $data]);
    

        return $this->response_to_object_array($response->getBody());
    }


    protected function get_pedido_insert_query_fields($fields)
    {

        $data = array('requestBody' => array('nota' => $fields));
        return $data;
    }

    protected function get_pedido_save_records_query_fields($fields){

        $data = array('requestBody' =>$fields);
        return $data;
    }



}

class Itens extends CRUDServiceProvider {

    public function __construct($http_client){
        $this->http_client = $http_client;
        $this->root_entity = "ItemNota";
    }
}

?>




