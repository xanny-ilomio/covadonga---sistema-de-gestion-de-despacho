<?php
//tells browser we're using JSON instead of HTML
header("Content-type: application/json; charset=UTF-8");

//CORS
header("Access-Control-Allow-Origin: *"); //change * for url to front. anyone can make request
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept");

//check w server before a complex request
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS'){
    http_response_code(200);
    exit();
}
 