<?php

namespace PHPSankhyaAPI;


class Enderecos extends CRUDServiceProvider {

    public function __construct($http_client){
         $this->http_client = $http_client;
         $this->root_entity = "Endereco";
     }
}

?>




