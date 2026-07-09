<?php

namespace PHPSankhyaAPI;

class Auth extends SankhyaAPIResource {

  // Properties

  const LOGIN_SERVICE_NAME = "MobileLoginSP.login";
  const LOGOUT_SERVICE_NAME = "MobileLoginSP.logout";


 public function __construct($http_client){
     $this->http_client = $http_client;
 }

     /**
      * @throws \GuzzleHttp\Exception\GuzzleException
      */
     public function login($user, $password){


             $data = array(
                 'requestBody' => array('NOMUSU' => array('$' => $user),
                                        'INTERNO' => array('$' => $password),
                                        'KEEPCONNECTED' => array('$' => 'S')
                                        ),
                 );

        $response =  $this->http_client->post(SankhyaAPI::API_PATH . $this::LOGIN_SERVICE_NAME . "&outputType=" . $this->output_type,
            [\GuzzleHttp\RequestOptions::JSON => $data]);
        return $this->response_to_object_array($response->getBody());

  }

    public function response_to_object_array($response){
        $object_array = [];
        $content = $response->getContents();

        $object = json_decode(utf8_encode($content), true);
      ;
        if(!isset($object["responseBody"]))
            return $object_array;


        $fields = $object["responseBody"];
        $colums_name =  array_keys($fields);
        for($col = 0; $col < count($fields); $col++)
        {
            $field_value =  $fields[$colums_name[$col]];
            $field_value =  isset($field_value["$"]) ? $field_value["$"] : "";
            $object_array[$colums_name[$col]] = $field_value;

        }


        return $object_array;
    }

     /**
      * @throws \GuzzleHttp\Exception\GuzzleException
      */
     public function logout(){

         $data = array(
             'status' => "1",
             'pendingPrinting' => 'false',
         );
         $response = $this->http_client->post(SankhyaAPI::API_PATH . $this::LOGOUT_SERVICE_NAME . "&outputType=" . $this->output_type,
             [\GuzzleHttp\RequestOptions::JSON => $data]);
         return $response->getBody();

     }



}

?>




