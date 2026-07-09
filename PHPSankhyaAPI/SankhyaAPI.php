<?php

namespace PHPSankhyaAPI;

require dirname(__DIR__) . '/PHPSankhyaAPI/SankhyaAPIResource.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/CRUDServiceProvider.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Clientes.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Contato.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Auth.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Enderecos.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Cidades.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Bairros.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Produtos.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/DBExplorer.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/Pedidos.php';
require dirname(__DIR__) . '/PHPSankhyaAPI/DistribuicaoDestinatarios.php';

class SankhyaAPI {
 

    // Properties
    const API_PATH='/mge/service.sbr?serviceName=';
    const API_PATH_COM='/mgecom/service.sbr?serviceName=';

    private $host;
    public $clientes;
    public $contatos;
    public $auth;
    public $enderecos;
    public $cidades;
    public $bairros;
    public $produtos;
    public $db_explorer;
    public $pedidos;
    public $login;
    public $destinatarios;

    public function __construct($host){
        $this->host = $host;
        $http_client = new \GuzzleHttp\Client(array('cookies' => true, 'base_uri' => $this->host));

        $this->clientes = new Clientes($http_client);
        $this->contatos = new Contato($http_client);
        $this->auth = new Auth($http_client);
        $this->enderecos = new Enderecos($http_client);
        $this->cidades = new Cidades($http_client);
        $this->bairros = new Bairros($http_client);
        $this->db_explorer = new DBExplorer($http_client);
        $this->produtos = new Produtos($http_client);
        $this->pedidos = new Pedidos($http_client);
        $this->destinatarios = new DistribuicaoDestinatarios($http_client);
    }

     protected function get_http_client(){
         return $this->http_client;
     }


    protected function get_service_url(){
       return $this->host . $this::API_PATH;
    }

    public function Login($user, $password){
        $this->login = $this->auth->login($user, $password);
    }
    public function Logout(){
        $this->auth->logout();
    }


}
