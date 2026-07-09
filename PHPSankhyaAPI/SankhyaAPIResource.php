<?php


namespace PHPSankhyaAPI;


class SankhyaAPIResource
{


    protected $output_type = "json";
    protected $http_client;
    protected $login;

    protected function get_db_explorer_query_fields($query)
    {
        $data = array('requestBody' => array('sql' => $query));
        return $data;
    }

    protected function get_load_records_query_fields($root_entity, $expression, $result_fieldset="", $offset=0, $limit=0)
    {
        $data = array('requestBody' => array('dataSet' => array('rootEntity' => "$root_entity", "includePresentationFields" => "S", "offsetPage" => "$offset",
            "criteria" => array("expression" => array("$" => $expression), $limit ?? "limit" => $limit),
            "entity" => array("fieldset" => array("list" => $result_fieldset))),
        ));

        return $data;
    }

    protected function get_save_records_query_fields($root_entity, $fields, $result_fieldset=""){

        $data = array('requestBody' => array('dataSet' => array('rootEntity' => "$root_entity", "includePresentationFields" => "S",
            "dataRow" => array("localFields"=> $fields),
            "entity" =>  array("fieldset" => array("list" =>  $result_fieldset))),
            )
        );
        return $data;
    }

    function hex_to_base64($hex){
        $return = '';
        foreach(str_split($hex, 2) as $pair){
            $return .= chr(hexdec($pair));
        }
        return base64_encode($return);
    }



}

