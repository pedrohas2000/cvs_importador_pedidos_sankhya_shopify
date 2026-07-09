<?php


namespace PHPSankhyaAPI;



class DBExplorer extends SankhyaAPIResource {
    const DB_EXPLORER_SERVICE_NAME = "DbExplorerSP.executeQuery";
    public function __construct($http_client){
        $this->http_client = $http_client;
        $this->root_entity = "";
    }

    public function execute_query($query){
        $data = $this->get_db_explorer_query_fields($query);
        $response = $this->http_client->get(SankhyaAPI::API_PATH . $this::DB_EXPLORER_SERVICE_NAME . "&outputType=" . $this->output_type,
            [\GuzzleHttp\RequestOptions::JSON => $data]);

        return $this->response_to_object_array($response->getBody());
    }



    public function response_to_object_array($response){
        $object_array = [];
        $content = $response->getContents();

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        // Corrige a codificação da string para UTF-8
        $utf8Content = mb_convert_encoding($content, 'UTF-8', $encoding);

        // Decodifica corretamente
        $object = json_decode($utf8Content, true);

        if(!isset($object["responseBody"]))
            return $object_array;

        $rows = $object["responseBody"]["rows"];
        $total = count($rows);
        if($total == 0)
            return $object_array;

        $fields = $object["responseBody"]["fieldsMetadata"];

        $cols_count = count($fields);

        for($row = 0; $row < $total; $row++) {
            for($col = 0; $col < $cols_count; $col++)
            {

                $field_name = $fields[$col]["name"];
                $field_pos = $fields[$col]["order"] - 1;
                $field_value =  $rows[$row][$field_pos];
                $object_array[$row][$field_name] = $field_value;

            }
        }

        return $object_array;
    }

}

