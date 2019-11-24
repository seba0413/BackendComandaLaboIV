<?php

require_once 'token.php';

class EmpleadoApi 
{
    public function LoginEmpleado($request, $response, $args)
    {        
        $data = file_get_contents('php://input');
        $empleadoAux = json_decode($data);
        $usuario = $empleadoAux->usuario;
        $clave = $empleadoAux->clave;

        $empleadoDao = new App\Models\Empleado;
        $tipoEmpleadoDao = new App\Models\TipoEmpleado;
        $logger = new App\Models\Logger;
        $horaActual = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
        try
        {            
            $empleado = $empleadoDao->where([['usuario', '=', $usuario],['clave', '=', $clave],])->first();        

            if($empleado) 
            {
                $tipoEmpleado = $tipoEmpleadoDao->where('id', '=', $empleado->id_tipoEmpleado)->first();

                $datos = [
                    'id' => $empleado->id,
                    'usuario' => $usuario,
                    'clave' => $clave,
                    'id_tipoEmpleado' => $tipoEmpleado->id,
                    'tipoEmpleado' => $tipoEmpleado->tipoEmpleado,
                    'id_sector' => $empleado->id_sector
                ];   

                $token = Token::CrearToken($datos); 
                $logger->id_empleado = $empleado->id;
                $logger->fechaIngreso = $horaActual;
                $logger->horaIngreso = $horaActual;
                $logger->save();

                $mensaje = array("Estado" => "Ok", "Token" => $token);
            }
            else
            {
                $mensaje = array("Estado" => "Error", "Mensaje " => "Usuario y/o clave incorrectos");
            }
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "Error", "Mensaje " => $error);
        }

        return $response->withJson($mensaje,200);
    }   

    public function RecuperarDatosCliente($request, $response, $args)
    {
        try
        {
            $idCliente = $args['idCliente'];
            $clienteEnEsperaDao = new App\Models\ClientesEspera;

            $clienteEnEspera = $clienteEnEsperaDao  ->where('idCliente', '=', $idCliente)
                                                    ->where('enEspera', '=', 1)
                                                    ->first();
            if($clienteEnEspera)
            {
                return $response->withJson(array("Estado"=>"Espera", "EstadoCliente"=>"A"));
            }

            $mesaDao = new App\Models\Mesa; 
            $mesa = $mesaDao ->where('id_clienteActual', '=', $idCliente)->first();

            if($mesa)
            {                
                $pedidoDao = new App\Models\Pedido;
                $pedidos = $pedidoDao   ->where('idCliente', '=', $idCliente)
                                        ->where('id_estadoPedido', '!=', 4)
                                        ->get();
                if(count($pedidos) > 0)
                {
                    $pedidosResponse = $pedidoDao   ->where('idCliente', '=', $idCliente)
                                                    ->where('id_estadoPedido', '!=', 4)
                                                    ->join('mesas', 'mesas.id', '=', 'pedidos.id_mesa')
                                                    ->join('productos', 'productos.id', '=', 'pedidos.id_producto')
                                                    ->select(   'pedidos.id_mesa', 'mesas.codigo as codigoMesa', 'productos.nombre', 
                                                                'pedidos.codigo as codigoPedido')
                                                    ->get();

                    return $response->withJson(array(
                                                        "Estado"=>"Pedidos", 
                                                        "Datos"=>$pedidosResponse,
                                                        "EstadoCliente"=>"S"
                                                    )
                                                );                                
                }
                else
                {
                    return $response->withJson(array(
                                                        "Estado"=>"Mesa",
                                                        "IdMesa"=>$mesa->id,
                                                        "CodigoMesa"=>$mesa->codigo,
                                                        "EstadoCliente"=>"S"
                                                    )
                                                );

                }                
            }

            return $response->withJson(array("Estado"=>"Nuevo"));
        }
        catch(Exception $e)
        {
            return $response->withJson(array("Estado"=>"Error", "Mensaje"=>$e->getMessage()));
        }
    }
    
    public function ListadoTiposDeEmpleado($request, $response, $args)
    {
        $tipos = new App\Models\TipoEmpleado;
        $listaTipos = $tipos->get();

        return $response->withJson($listaTipos, 200);
    }

    public function IngresosAlSistema($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];
        $logger = new App\Models\Logger;
        date_default_timezone_set("America/Argentina/Buenos_Aires");

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);   
            $logueos = $logger->rightJoin('empleados as em', 'loggers.id_empleado', '=', 'em.id')
            ->where('fechaIngreso', '=', $fecha)
            ->where('em.estado', '!=', 'E')->get();           
    
            for($i = 0; $i < count($logueos); $i++)
            {
                echo 'Empleado: '. $logueos[$i]->usuario ."\n". 
                     'Fecha de ingreso: ' . $logueos[$i]->fechaIngreso . "\n" .
                     'Hora de ingreso: ' . $logueos[$i]->horaIngreso . "\n" .
                     '-------------------------------------------------------'. "\n";
            }
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $logueos = $logger->rightJoin('empleados as em', 'loggers.id_empleado', '=', 'em.id')
            ->where('fechaIngreso', '>=', $fecha_desde)
            ->where('fechaIngreso', '<=', $fecha_hasta)
            ->where('em.estado', '!=', 'E')->get();

            for($i = 0; $i < count($logueos); $i++)
            {
                echo 'Empleado: '. $logueos[$i]->usuario ."\n". 
                     'Fecha de ingreso: ' . $logueos[$i]->fechaIngreso . "\n" .
                     'Hora de ingreso: ' . $logueos[$i]->horaIngreso . "\n" .
                     '-------------------------------------------------------'. "\n";
            }
        } 
    }

    public function CantidadOperacionesPorSector($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];
        $operacion = new App\Models\Operacion;

        $operacionesLocal = 0;
        $operacionesSalon = 0;
        $operacionesCocina = 0;
        $operacionesBarraTragos = 0;
        $operacionesBarraCervezas = 0;        
        $operacionesCandyBar = 0;

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);
            $operaciones = $operacion->join('empleados', 'operaciones.id_empleado', '=', 'empleados.id')
                                     ->where('operaciones.fecha', '=', $fecha)
                                     ->where('empleados.estado', '!=', 'E')
                                     ->select('operaciones.id as id', 'empleados.id as idEmpleado', 'empleados.usuario', 'empleados.id_sector')->get();

            for($i = 0; $i < count($operaciones); $i++)
            {
                if($operaciones[$i]->id_sector == 1)
                    $operacionesLocal++;
                if($operaciones[$i]->id_sector == 2)
                    $operacionesSalon++;
                if($operaciones[$i]->id_sector == 3)
                    $operacionesCocina++;
                if($operaciones[$i]->id_sector == 4)
                    $operacionesBarraTragos++;
                if($operaciones[$i]->id_sector == 5)
                    $operacionesBarraCervezas++;
                 if($operaciones[$i]->id_sector == 6)
                    $operacionesCandyBar++;
            }

            echo    'Local: ' . $operacionesLocal . ' operaciones' . "\n" .
                    'Salon: ' . $operacionesSalon . ' operaciones' . "\n" .
                    'Cocina: ' . $operacionesCocina . ' operaciones' . "\n" .
                    'Barra de tragos y vinos: ' . $operacionesBarraTragos . ' operaciones' . "\n" .
                    'Barra de cervezas artesanales: ' . $operacionesBarraCervezas . ' operaciones' . "\n" .                    
                    'Candy Bar: ' . $operacionesCandyBar . ' operaciones' . "\n";
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $operaciones = $operacion->join('empleados', 'operaciones.id_empleado', '=', 'empleados.id')
                                     ->where('operaciones.fecha', '>=', $fecha_desde)
                                     ->where('operaciones.fecha', '<=', $fecha_hasta)
                                     ->where('empleados.estado', '!=', 'E')
                                     ->select('operaciones.id as id', 'empleados.id as idEmpleado', 'empleados.usuario', 'empleados.id_sector')->get();

            for($i = 0; $i < count($operaciones); $i++)
            {
                if($operaciones[$i]->id_sector == 1)
                    $operacionesLocal++;
                if($operaciones[$i]->id_sector == 2)
                    $operacionesSalon++;
                if($operaciones[$i]->id_sector == 3)
                    $operacionesCocina++;
                if($operaciones[$i]->id_sector == 4)
                    $operacionesBarraTragos++;
                if($operaciones[$i]->id_sector == 5)
                    $operacionesBarraCervezas++;
                 if($operaciones[$i]->id_sector == 6)
                    $operacionesCandyBar++;
            }

            echo 'Local: ' . $operacionesLocal . ' operaciones' . "\n" .
                 'Salon: ' . $operacionesSalon . ' operaciones' . "\n" .
                 'Cocina: ' . $operacionesCocina . ' operaciones' . "\n" .
                 'Barra de tragos y vinos: ' . $operacionesBarraTragos . ' operaciones' . "\n" .
                 'Barra de cervezas artesanales: ' . $operacionesBarraCervezas . ' operaciones' . "\n" .                    
                 'Candy Bar: ' . $operacionesCandyBar . ' operaciones' . "\n";
        }

    }

    public function CantidadOperacionesPorSectorYEmpleado($request, $response, $args)
    {    
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];
        $operacion = new App\Models\Operacion;

        $idDeEmpleadosLocal = [];
        $idDeEmpleadosSalon = [];
        $idDeEmpleadosVinos = [];
        $idDeEmpleadosCerveza = [];
        $idDeEmpleadosCocina = [];
        $idDeEmpleadosCandyBar = [];

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);
            $operaciones = $operacion->join('empleados', 'operaciones.id_empleado', '=', 'empleados.id')
                                    ->where('operaciones.fecha', '=', $fecha)
                                    ->where('empleados.estado', '!=', 'E')
                                    ->select('operaciones.id as id', 'empleados.id as idEmpleado', 'empleados.usuario', 'empleados.id_sector')
                                    ->orderBy('empleados.id_sector')->get();

            for($i = 0; $i < count($operaciones); $i++)
            {
                if($operaciones[$i]->id_sector == 1)
                    $idDeEmpleadosLocal[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 2)
                    $idDeEmpleadosSalon[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 3)
                    $idDeEmpleadosCocina[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 4)
                    $idDeEmpleadosVinos[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 5)
                    $idDeEmpleadosCerveza[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 6)
                    $idDeEmpleadosCandyBar[] = $operaciones[$i]->idEmpleado;
            }

            //Se agrega un -1 al final del array para identificar el ultimo registro
            $idDeEmpleadosLocal[] = -1;
            $idDeEmpleadosSalon[] = -1;
            $idDeEmpleadosVinos[] = -1;
            $idDeEmpleadosCerveza[] = -1;
            $idDeEmpleadosCocina[] = -1;
            $idDeEmpleadosCandyBar[] = -1;

            EmpleadoApi::CalcularCantidadOperacionesPorEmpleado($idDeEmpleadosLocal, $idDeEmpleadosSalon, $idDeEmpleadosVinos, $idDeEmpleadosCerveza, $idDeEmpleadosCocina, $idDeEmpleadosCandyBar, $operaciones);
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $operaciones = $operacion->join('empleados', 'operaciones.id_empleado', '=', 'empleados.id')
                                    ->where('operaciones.fecha', '>=', $fecha_desde)
                                    ->where('operaciones.fecha', '<=', $fecha_hasta)
                                    ->where('empleados.estado', '!=', 'E')
                                    ->select('operaciones.id as id', 'empleados.id as idEmpleado', 'empleados.usuario', 'empleados.id_sector')
                                    ->orderBy('empleados.id_sector')->get();

            for($i = 0; $i < count($operaciones); $i++)
            {
                if($operaciones[$i]->id_sector == 1)
                    $idDeEmpleadosLocal[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 2)
                    $idDeEmpleadosSalon[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 3)
                    $idDeEmpleadosCocina[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 4)
                    $idDeEmpleadosVinos[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 5)
                    $idDeEmpleadosCerveza[] = $operaciones[$i]->idEmpleado;
                if($operaciones[$i]->id_sector == 6)
                    $idDeEmpleadosCandyBar[] = $operaciones[$i]->idEmpleado;
            }

            //Se agrega un -1 al final del array para identificar el ultimo registro
            $idDeEmpleadosLocal[] = -1;
            $idDeEmpleadosSalon[] = -1;
            $idDeEmpleadosVinos[] = -1;
            $idDeEmpleadosCerveza[] = -1;
            $idDeEmpleadosCocina[] = -1;
            $idDeEmpleadosCandyBar[] = -1;

            EmpleadoApi::CalcularCantidadOperacionesPorEmpleado($idDeEmpleadosLocal, $idDeEmpleadosSalon, $idDeEmpleadosVinos, $idDeEmpleadosCerveza, $idDeEmpleadosCocina, $idDeEmpleadosCandyBar, $operaciones);
        }
    }

    static function CalcularCantidadOperacionesPorEmpleado($idDeEmpleadosLocal, $idDeEmpleadosSalon, $idDeEmpleadosVinos, $idDeEmpleadosCerveza, $idDeEmpleadosCocina, $idDeEmpleadosCandyBar, $operaciones)
    {
        echo 'LOCAL' . "\n";   
            
        EmpleadoApi::CalcularCantidadPorEmpleado($idDeEmpleadosLocal, $operaciones);

        echo '--------------------------------------------------' . "\n";
        echo 'SALON' . "\n";   
            
        EmpleadoApi::CalcularCantidadPorEmpleado($idDeEmpleadosSalon, $operaciones);

        echo '--------------------------------------------------' . "\n";
        echo 'BARRA DE TRAGOS Y VINOS' . "\n";   
            
        EmpleadoApi::CalcularCantidadPorEmpleado($idDeEmpleadosVinos, $operaciones);

        echo '--------------------------------------------------' . "\n";
        echo 'BARRA DE CERVEZAS ARTESANALES' . "\n";            

        EmpleadoApi::CalcularCantidadPorEmpleado($idDeEmpleadosCerveza, $operaciones);

        echo '--------------------------------------------------' . "\n";
        echo 'COCINA' . "\n";            

        EmpleadoApi::CalcularCantidadPorEmpleado($idDeEmpleadosCocina, $operaciones);

        echo '--------------------------------------------------' . "\n";
        echo 'CANDY BAR' . "\n";            
        
        EmpleadoApi::CalcularCantidadPorEmpleado($idDeEmpleadosCandyBar, $operaciones);
    }

    static function CalcularCantidadPorEmpleado($arrayDeIdEmpleado, $operaciones)
    {
        //Si el array del sector tiene al menos un dato mas que el -1
        if(count($arrayDeIdEmpleado) > 1)
        {
            $contador = 1;

            for($i = 0; $i <= count($arrayDeIdEmpleado); $i++)
            { 
                //Si es el ultimo registro, se imprime la cantidad del registro anterior
                if($arrayDeIdEmpleado[$i+1] == -1)
                {
                    for($j = 0; $j < count($operaciones); $j++)
                    {
                        if($arrayDeIdEmpleado[$i] == $operaciones[$j]->idEmpleado)
                        {
                            echo 'Empleado: ' . $operaciones[$j]->usuario . ". Operaciones: " . $contador . "\n";
                            break;
                        }                            
                    }
                    break;
                }
                else if($arrayDeIdEmpleado[$i+1] == $arrayDeIdEmpleado[$i])
                {
                    $contador++;
                }
                else
                {
                    for($j = 0; $j < count($operaciones); $j++)
                    {
                        if($arrayDeIdEmpleado[$i] == $operaciones[$j]->idEmpleado)
                        {
                            echo 'Empleado: ' . $operaciones[$j]->usuario . " .Operaciones: " . $contador . "\n";
                            $contador = 1;
                            break;
                        }                            
                    }
                }                
            }
        }
        else
        {
            echo 'Sin operaciones' . "\n";
        }
    }

    public function CantidadOperacionesPorEmpleado($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];
        $empleado = $_GET['empleado'];
        $operacion = new App\Models\Operacion;
        $empleadoDao = new App\Models\Empleado;

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);             

            $operaciones = $operacion->join('empleados', 'operaciones.id_empleado', '=', 'empleados.id')
                                    ->where('operaciones.fecha', '=', $fecha)
                                    ->where('empleados.usuario', '=', $empleado)
                                    ->where('empleados.estado', '!=', 'E')
                                    ->select('empleados.id as idEmpleado', 'empleados.usuario')->get();

            $empleados = $empleadoDao->all();
            $existe = false;

            for($i = 0; $i < count($empleados); $i++)
            {
                if($empleados[$i]->usuario == $empleado)
                {
                    if(count($operaciones) > 0)
                        echo 'Empleado: ' . $empleado . '. Operaciones: ' . count($operaciones);
                    else
                        echo $empleado . ' no registra operaciones';

                    $existe = true;
                    break;    
                }
            }
            if(!$existe)
                echo 'Nombre de empleado inexistente';
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $operaciones = $operacion->join('empleados', 'operaciones.id_empleado', '=', 'empleados.id')
                                    ->where('operaciones.fecha', '>=', $fecha_desde)
                                    ->where('operaciones.fecha', '<=', $fecha_hasta)
                                    ->where('empleados.estado', '!=', 'E')
                                    ->where('empleados.usuario', '=', $empleado)
                                    ->select('empleados.id as idEmpleado', 'empleados.usuario')->get();

            $empleados = $empleadoDao->all();
            $existe = false;

            for($i = 0; $i < count($empleados); $i++)
            {
                if($empleados[$i]->usuario == $empleado)
                {
                    if(count($operaciones) > 0)
                        echo 'Empleado: ' . $empleado . '. Operaciones: ' . count($operaciones);
                    else
                        echo $empleado . ' no registra operaciones';

                    $existe = true;
                    break;    
                }
            }
            if(!$existe)
                echo 'Nombre de empleado inexistente';         
        }
    }

    public function AltaEmpleado($request, $response, $args)
    {
        // $parametros = $request->getParsedBody();
        // $usuario = $parametros['usuario'];
        // $clave = $parametros['clave'];
        // $tipoEmpleado = strtolower($parametros['tipo_empleado']);

        $data = file_get_contents('php://input');
        $empleadoAux = json_decode($data);
        $usuario = $empleadoAux->usuario;
        $clave = $empleadoAux->clave;
        // $tipoEmpleado = strlower($empleadoAux->tipoEmpleado);
        
        // $id_tipoEmpleado;
        // $id_sector;

        // switch($tipoEmpleado)
        // {
        //     case 'socio':
        //         $id_tipoEmpleado = 1;
        //         $id_sector = 1;
        //         break;
        //     case 'mozo':
        //         $id_tipoEmpleado = 2;
        //         $id_sector = 2;
        //         break;
        //     case 'cocinero':
        //         $id_tipoEmpleado = 3;
        //         $id_sector = 3;
        //         break;
        //     case 'bartender':
        //         $id_tipoEmpleado = 4;
        //         $id_sector = 4;
        //         break;
        //     case 'cervecero':
        //         $id_tipoEmpleado = 5;
        //         $id_sector = 5;
        //         break;
        //     case 'pastelero':
        //         $id_tipoEmpleado = 6;
        //         $id_sector = 6;
        //         break;
        // }
        try
        {
            $empleado = new App\Models\Empleado;
            $empleado->usuario = $usuario;
            $empleado->clave = $clave;
            // $empleado->id_tipoEmpleado = $id_tipoEmpleado;
            // $empleado->id_sector = $id_sector;
            // $empleado->estado = 'A';
            $empleado->save();
            $mensaje = array("Estado" => "Ok", "Mensaje" => "El alta se realizó correctamente");
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "ERROR", "Mensaje" => $error);
        }
        return $response->withJson($mensaje, 200);    
    }

    public function ListadoEmpleados($request, $response, $args)
    {
        $empleado = new App\Models\Empleado;
        $empleados = $empleado->where('estado', '!=', 'E')->get();
        for($i = 0; $i < count($empleados); $i++)
        {
            echo 'Id: ' . $empleados[$i]->id . ". Usuario: " . $empleados[$i]->usuario . ". Estado: " . $empleados[$i]->estado . "\n";
        }        
    }

    public function SuspenderEmpleado($request, $response, $args)
    {
        $parametros = $request->getParsedBody();
        $idEmpleado = $parametros['id_empleado'];

        try
        {
            $empleadoDao = new App\Models\Empleado;

            $empleado = $empleadoDao->where('id', '=', $idEmpleado)->first();
            $empleado->estado = 'S';
            $empleado->save();
            $mensaje = array("Estado" => "OK", "Mensaje" => "Empleado suspendido correctamente");
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "ERROR", "Mensaje" => $error);
        }
        return $response->withJson($mensaje, 200); 
    }

    public function EliminarEmpleado($request, $response, $args)
    {
        $parametros = $request->getParsedBody();
        $idEmpleado = $parametros['id_empleado'];

        try
        {
            $empleadoDao = new App\Models\Empleado;

            $empleado = $empleadoDao->where('id', '=', $idEmpleado)
            ->where('estado', '!=', 'E')->first();
            $empleado->estado = 'E';
            $empleado->save();
            $mensaje = array("Estado" => "OK", "Mensaje" => "Empleado eliminado correctamente");
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "ERROR", "Mensaje" => $error);
        }
        return $response->withJson($mensaje, 200);
    }

    public function VerEmpleadosPorPuesto($request, $response, $args)
    {
        try
        {
            $payload = $request->getAttribute("payload")["Payload"];
            $data = $payload->data;
            $empleado = new App\Models\Empleado;

            $empleadosPorPuesto = $empleado->where('id_sector', '=', $data->id_sector)
                                            ->where('estado', '=', 'A')
                                            ->where('id', '!=', $data->id)
                                            ->get();

            if($empleadosPorPuesto->isEmpty())
                echo 'No hay otros empleados en tu puesto';
            else
            {
                echo 'Empleados en su puesto ' . "\n";

                for($i = 0; $i < count($empleadosPorPuesto); $i++)
                {
                    echo $empleadosPorPuesto[$i]->usuario . "\n";
                }
            }                          
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "Error", "Mensaje" => $error);
            return $response->withJson($mensaje, 200);
        }
    }
}

?>