<?php

namespace AvecdoSDK\Classes;

class Response
{
    public static function asJson($array, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);

        $array = Helpers::sanitizeArray($array);
		
        echo json_encode($array);

        exit;
    }

    public static function asJsonError($message, $statusCode = 400) 
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        
        echo json_encode(array(
            'error' => array(
                'message' => $message
            ),
            'code' => $statusCode
        ));

        exit;
    }
}
