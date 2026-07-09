<?php


namespace PHPSankhyaAPI;

use \PHPSankhyaAPI\CRUDServiceProvider;

class Cidades extends CRUDServiceProvider {
    public function __construct($http_client){
        $this->http_client = $http_client;
        $this->root_entity = "Cidade";
    }
}

