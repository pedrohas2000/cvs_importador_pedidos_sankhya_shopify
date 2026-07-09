<?php

namespace PHPSankhyaAPI;


class Contato extends CRUDServiceProvider {
    public function __construct($http_client){
        $this->http_client = $http_client;
        $this->root_entity = "Contato";
    }
}

?>




