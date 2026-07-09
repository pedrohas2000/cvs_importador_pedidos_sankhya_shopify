<?php


namespace PHPSankhyaAPI;

use Exception;

class CRUDServiceProvider extends SankhyaAPIResource
{

    const GET_SERVICE_NAME = "CRUDServiceProvider.loadRecords";
    const POST_SERVICE_NAME = "CRUDServiceProvider.saveRecord";

    const DB_SAVE_SERVICE_NAME = "DatasetSP.save";

    public function update($pk, $payload)
    {
    
        $values = new \stdClass();
        $field_names = [];
        $cont = 0;
        foreach($pk as $field_name => $field_value)
        {
            $field_names[] = $field_name; //inclui os campos de pk nos field names, para que retorne no resultado
            $cont++;
        }

        foreach($payload as $field_name => $field_value)
        {
            $values->{(string)$cont} = $field_value["$"] ?? $field_value;
            $field_names[] = $field_name;
            $cont++;
        }
        

        $data = array("serviceName" => $this::DB_SAVE_SERVICE_NAME, 'requestBody' => 
            array("entityName" => $this->root_entity, 
            'standAlone' => false,
            'fields' => $field_names,
            'records' => array(array("pk" => $pk, "values" => $values))
            )
        );
         //print_r(json_encode($data));
         $json = json_encode($data);
         $response = $this->http_client->post(SankhyaAPI::API_PATH . $this::DB_SAVE_SERVICE_NAME . "&outputType=" . $this->output_type,
         [\GuzzleHttp\RequestOptions::JSON => $data]);
    

         $content = $response->getBody()->getContents();

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        // Corrige a codificação da string para UTF-8
        $utf8Content = mb_convert_encoding($content, 'UTF-8', $encoding);

        // Decodifica corretamente
        $object = json_decode($utf8Content, true);
        
         $object_array = [];
 
         if($object["status"] == 1){
             $object_array["success"] = true;
         }
         else
         {
             $object_array["success"] = false;
             $object_array["message"] = $object["statusMessage"];
             return $object_array;
         }
 
         $results = $object["responseBody"]["result"];
         $total = $object["responseBody"]["total"];
         $object_array["total"] = $total;
         $object_array["data"] = [];

         if(count($results) == 0)
         {
            return $object_array;
         }


         $data = [];
         foreach ($results as $result)
         {
             $data_item = [];
             $cont = 0;
             foreach($field_names as $field_name)
             {
                 if(is_array($result[$cont]) && isset($result[$cont]["$"]))
                     $data_item[$field_name] = $result[$cont]["$"];
                 else
                     $data_item[$field_name] = $result[$cont];
                $cont++;
             }
            
             $data[] = $data_item;
         }
        

         $object_array["data"] = $data;

        return $object_array;
    }


    public function get($expression="", $result_fieldset="", $offsetPage = 0, $limit = 0){


        $params = $this->get_load_records_query_fields($this->root_entity, $expression, $result_fieldset, $offsetPage, $limit);

        $response = $this->http_client->get(SankhyaAPI::API_PATH . $this::GET_SERVICE_NAME . "&outputType=" . $this->output_type,
            [\GuzzleHttp\RequestOptions::JSON => $params]);


        return $this->response_to_object_array($response->getBody());
    }

    public function get_all($expression="", $result_fieldset="", $limit = 0){

        $offsetPage = 0;
        $total = 0;
        $data = null;
        do
        {
            $params = $this->get_load_records_query_fields($this->root_entity, $expression, $result_fieldset, $offsetPage, $limit);
            $response = $this->http_client->get(SankhyaAPI::API_PATH . $this::GET_SERVICE_NAME . "&outputType=" . $this->output_type,
                [\GuzzleHttp\RequestOptions::JSON => $params]);

            $result = $this->response_to_object_array($response->getBody());
            if(isset($result["success"]) && $result["success"] == false)
                throw new Exception("Erro: " .$result["message"]);


            $total = $result["total"];

            if($data == null)
                $data = $result;
            else if($total > 0)
            {
                foreach ($result["data"] as $item)
                {
                    $data["data"][] = $item;
                    $data["total"]++;
                    if($limit > 0 && count($data["data"]) >= $limit)
                        break;
                }

            }

            if($limit > 0 && count($data["data"]) >= $limit){
                $data["total"] = $limit;
                break;
            }

            $offsetPage++;

        }while($total > 0);

        $data["hasMoreResult"] = false;
        if(!isset($data["data"]))
            $data["data"] = array();

            $data["data"] = $limit > 0 ? array_slice($data["data"], 0, $limit) : $data["data"];
        return $data;
    }


    public function post($fields =[], $result_fieldset=""){

        $params = $this->get_save_records_query_fields($this->root_entity, $fields, $result_fieldset);
        $response = $this->http_client->post(SankhyaAPI::API_PATH . $this::POST_SERVICE_NAME . "&outputType=" . $this->output_type,
            [\GuzzleHttp\RequestOptions::JSON => $params]);
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
   
    
        if($object["status"] == 1)
            $object_array["success"] = true;
        else
        {
            $object_array["success"] = false;
            $object_array["message"] = $object["statusMessage"];
            return $object_array;
        }

        if(!isset($object["responseBody"]))
            return $object_array;

        if(!isset($object["responseBody"]["entities"]))
            return $object_array = array("data" => $object["responseBody"]);

        $entities = $object["responseBody"]["entities"];

        $total = $entities["total"];
        $object_array["total"] = $total;

        if(isset($entities["hasMoreResult"]))
            $object_array["hasMoreResult"] = $entities["hasMoreResult"];

        if(isset($entities["offsetPage"]))
            $object_array["offsetPage"] = $entities["offsetPage"];


        if($entities["total"] == 0)
            return $object_array;

        $fields = isset($entities["metadata"]) ? $entities["metadata"]["fields"]["field"] : array_keys($entities["entity"]);
        $values = $entities["entity"];


        $cols_count = isset($fields[0])  ? count($fields) : 1;
        for($row = 0; $row < $total; $row++) {
            for($col = 0; $col < $cols_count; $col++)
            {

                if(isset($entities["metadata"]))
                {
                    $field_name = isset($fields[0])  ? $fields[$col]["name"] : $fields["name"];
                    $field_value =  isset($values[0])  ?  $values[$row]["f$col"] : $values["f$col"];
                    $field_value =  isset($field_value["$"]) ? $field_value["$"] : "";
                }
                else
                {
                    $field_name = $fields[$col];
                    $field_value =  isset($values[$field_name]["$"]) ? $values[$field_name]["$"] : "";

                }

                $object_array["data"][$row][$field_name] = $field_value;

            }
        }
  
        return $object_array;
    }
}

?>