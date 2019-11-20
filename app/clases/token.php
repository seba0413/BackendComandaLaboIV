<?php

use Firebase\JWT\JWT;

class Token
{
    private static $claveSecreta = 'ClaveSuperSecreta@';
    private static $tipoEncriptacion = ['HS256'];
    
    public static function CrearToken($datos)
    {
        $ahora = time();
        $payload = array(
        	'iat'=>$ahora,
            'exp' => $ahora + (60*60),
            'aud' => "usuario",
            'data' => $datos,
            'app'=> "API REST La Comanda 2019"
        );
     
        return JWT::encode($payload, self::$claveSecreta);
    }
    
    public static function VerificarToken($token)
    {  
        $decodificado = [];

        if(empty($token)|| $token=="")
        {
            $decodificado = array("Estado" => "ERROR", "Mensaje" => "El token esta vacio");
        }     
        try 
        {
            $payload = JWT::decode($token, self::$claveSecreta, self::$tipoEncriptacion);
            $decodificado = array("Estado" => "OK", "Mensaje" => "OK", "Payload" => $payload);
        }
        catch (Exception $e) 
        {
            $mensaje = $e->getMessage();
            $decodificado = array("Estado" => "ERROR", "Mensaje" => "$mensaje.");
        }
        return $decodificado;
    }
}