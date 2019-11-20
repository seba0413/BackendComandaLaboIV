<?php

require_once "../app/clases/token.php";

class Middleware
{
    public static function ValidarToken($request,$response,$next){
        $token = $request->getHeader("Autorizacion");
        $validacionToken = Token::VerificarToken($token[0]);
        if($validacionToken["Estado"] == "OK"){
            $request = $request->withAttribute("payload", $validacionToken);
            return $next($request,$response);
        }
        else{
            $newResponse = $response->withJson($validacionToken,401);
            return $newResponse;
        }
    }

    public static function ValidarSocio($request,$response,$next){
        $payload = $request->getAttribute("payload")["Payload"];
        $data = $payload->data;

        if($data->tipoEmpleado == "socio"){
            return $next($request,$response);
        }
        else{
            $respuesta = array("Estado" => "ERROR", "Mensaje" => "No tiene permiso para realizar esta accion.");
            $newResponse = $response->withJson($respuesta,401);
            return $newResponse;
        }
    }

    public static function ValidarMozo($request,$response,$next)
    {
        $payload = $request->getAttribute("payload")["Payload"];
        $data = $payload->data;
        
        if($data->tipoEmpleado == "mozo" || $data->tipoEmpleado == "socio")
        {
            return $next($request,$response);
        }
        else
        {
            $respuesta = array("Estado" => "ERROR", "Mensaje" => "No tiene permiso para realizar esta accion");
            $newResponse = $response->withJson($respuesta,401);
            return $newResponse;
        }
    }

    public static function SumarOperacion($request, $response, $next)
    {
        $payload = $request->getAttribute("payload")["Payload"];
        $data = $payload->data;
        $nombreOperacion = $_SERVER["REQUEST_URI"];
        date_default_timezone_set("America/Argentina/Buenos_Aires");
        $fecha = date('Y-m-d');
        try
        {
            $operacion = new App\Models\Operacion;        
            $operacion->id_empleado = $data->id;
            $operacion->operacion = $nombreOperacion;
            $operacion->fecha = $fecha;
            $operacion->save();
            return $next($request,$response);
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "ERROR", "Mensaje" => "Hubo un problema al grabar la operacion", "Excepcion" => $error);
            return $response->withJson($mensaje, 200);
        } 
    }
}

?>
