<?php

namespace PHPSankhyaAPI;


class Clientes extends CRUDServiceProvider {
    public function __construct($http_client){
        $this->http_client = $http_client;
        $this->root_entity = "Parceiro";
    }
}

?>




