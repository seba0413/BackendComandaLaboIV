<?php
class PedidoApi 
{

    public function GetProductosCocina($request, $response, $args)
    {
        $productos = new App\Models\Producto;
        $listaProductos = $productos->where('id_sector', '=', '3')->get();  

        return $response->withJson($listaProductos, 200);
    }

    public function GetVinos($request, $response, $args)
    {
        $productos = new App\Models\Producto;
        $listaProductos = $productos->where('id_sector', '=', '4')->get();  

        return $response->withJson($listaProductos, 200);
    }

    public function GetCervezas($request, $response, $args)
    {
        $productos = new App\Models\Producto;
        $listaProductos = $productos->where('id_sector', '=', '5')->get();  

        return $response->withJson($listaProductos, 200);
    }

    public function GetPostres($request, $response, $args)
    {
        $productos = new App\Models\Producto;
        $listaProductos = $productos->where('id_sector', '=', '6')->get();  

        return $response->withJson($listaProductos, 200);
    }

    public function CargarPedido($request, $response, $args)
    {
        try
        {     
            $data = file_get_contents('php://input');
            $pedidoAux = json_decode($data);
            $idMesa = $pedidoAux->idMesa;
            $idProducto = $pedidoAux->idProducto;
            $cantidad = $pedidoAux->cantidad;
            $nombreCliente = $pedidoAux->nombreCliente;
            $idCliente = $pedidoAux->idCliente;
            $codigo = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 3)), 0, 5);
            date_default_timezone_set("America/Argentina/Buenos_Aires");
            $fecha = date('Y-m-d');
            $horaInicial = date('H:i');
            $pedido = new App\Models\Pedido;

            $pedido->id_mesa = $idMesa;
            $pedido->id_producto = $idProducto;
            $pedido->cantidad = $cantidad;
            $pedido->nombreCliente = $nombreCliente;
            $pedido->idCliente = $idCliente; 
            $pedido->id_estadoPedido = 1;
            $pedido->codigo = $codigo;
            $pedido->horaInicial = $horaInicial;
            $pedido->fecha = $fecha;

            if($_FILES)
            {
                $nombreFoto = PedidoApi::GuardarFoto($_FILES['foto'], $codigo, $idMesa);
                $pedido->nombreFoto = $nombreFoto;
            }         
            
            $pedido->save();
            $respuesta = array("Estado" => "El pedido se registro correctamente", "CodigoPedido" => $codigo);
        }
        catch(Exception $e)
        {
            $mensaje = $e->getMessage();
            $respuesta = array("Estado" => "ERROR", "Mensaje" => "$mensaje");
        }

        return $response->withJson($respuesta,200);
    }

    static function GuardarFoto($foto, $codigo, $idMesa)
    {        
        $ruta = $foto['tmp_name'];
        $extension = explode(".",$foto['name']);
        $index = count($extension) - 1; 
        $nombreFoto = $codigo . "_" . $idMesa . "." . $extension[$index];
        $rutafoto = "../app/fotos/" . $nombreFoto;
        
        move_uploaded_file($ruta, $rutafoto);     
        
        return $nombreFoto;
    }

    public function VerPedidosPendientes($request, $response, $args)
    {
        $payload = $request->getAttribute("payload")["Payload"];
        $infoEmpleado = $payload->data;

        $idSector = $infoEmpleado->id_sector;

        $pedido = new App\Models\Pedido;

        $pedidosPendientes = $pedido->rightJoin('productos', 'pedidos.id_producto', '=', 'productos.id')
        ->where('pedidos.id_estadoPedido', '=', 1)->get();

        $mensaje = [];
        $flag = false; 

        for($i = 0; $i < count($pedidosPendientes); $i++)
        {
            if($pedidosPendientes[$i]->id_sector == $idSector)
            {
                $flag = true;
                echo    "\nProducto: " . $pedidosPendientes[$i]->nombre .    
                        "\nCantidad: " . $pedidosPendientes[$i]->cantidad .                         
                        "\nMesa: " . $pedidosPendientes[$i]->id_mesa . 
                        "\nCliente: " . $pedidosPendientes[$i]->nombreCliente .
                        "\nCodigo: " . $pedidosPendientes[$i]->codigo .
                        "\n------------------------";
            }
        }
        if(!$flag)
            echo 'No tiene pedidos pendientes';
    }

    public function TomarPedido($request, $response, $args)
    {
        $payload = $request->getAttribute("payload")["Payload"];
        $infoEmpleado = $payload->data;
        $idSector = $infoEmpleado->id_sector;

        $parametros = $request->getParsedBody();
        $codigo = $parametros['codigo'];
        $tiempoEstimado = $parametros['tiempoEstimado'];
        $pedido = new App\Models\Pedido;
        try
        {
            $pedidoATomar = $pedido->where('codigo', '=', $codigo)->firstOrFail();        

            $idSectorProducto = $pedido->join('productos', 'pedidos.id_producto', '=', 'productos.id')
                                        ->where('productos.id', '=', $pedidoATomar->id_producto)
                                        ->select('productos.id_sector')->firstOrFail();

            $horaActual = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
            $horaActual->add(new DateInterval('PT' . $tiempoEstimado . 'M'));
            $horaEntregaEstimada = $horaActual->format('H:i');

            $pedidoATomar->id_estadoPedido = 2;
            $pedidoATomar->tiempoEstimado = $tiempoEstimado;
            $pedidoATomar->horaEntregaEstimada = $horaEntregaEstimada;
            $pedidoATomar->id_empleado = $infoEmpleado->id;
            $pedidoATomar->save();
            $mensaje = array("Estado" => "Ok", "Mensaje " => "Pedido en preparacion ");
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "Error", "Mensaje " => $error);
        }     
        return $response->withJson($mensaje,200);
    }

    public function ServirPedido($request, $response, $args)
    {
        $payload = $request->getAttribute("payload")["Payload"];
        $infoEmpleado = $payload->data;
        $id = $infoEmpleado->id;
        $horaEntrega = date('H:i');
        $pedido = new App\Models\Pedido;
        try
        {
            $pedidoAServir = $pedido->where([['id_estadoPedido', '=', 2],['id_empleado', '=', $id],])->firstOrFail();        
            $pedidoAServir->id_estadoPedido = 3;
            $pedidoAServir->horaEntrega = $horaEntrega;
            $pedidoAServir->save();
            $mensaje = array("Estado" => "Ok", "Mensaje " => "El pedido " . $pedidoAServir->codigo . " esta listo para servir");
        }
        catch(Exception $e)
        {
            $mensaje = array("Estado" => "Error", "Mensaje " => "No tiene pedidos en preparacion");
        }       
        return $response->withJson($mensaje,200);
    }

    public function VerEstadoPedidos($request, $response, $args)
    {
        $pedido = new App\Models\Pedido;

        $pedidos = $pedido->join('estado_pedidos', 'pedidos.id_estadoPedido', '=', 'estado_pedidos.id')
        ->select('pedidos.id', 'estado_pedidos.estado', 'pedidos.codigo', 'pedidos.nombreCliente')
        ->get();

        for($i = 0; $i < count($pedidos); $i++)
        {
            echo "Pedido : " . $pedidos[$i]->id . "\n" . 
                 "Estado : " . $pedidos[$i]->estado . "\n" . 
                 "Código : " . $pedidos[$i]->codigo . "\n" .
                 "Cliente : " . $pedidos[$i]->nombreCliente . "\n" .                 
                 "-------------------------------------------------\n";
        }
    }

    public function TiempoRestante($request, $response, $args)
    {
        // $parametros = $request->getParsedBody();
        $codigoMesa = $args['codigoMesa'];
        $codigoPedido = $args['codigoPedido'];

        $mesa = new App\Models\Mesa;
        $idMesa = $mesa->where('codigo', '=', $codigoMesa)->select('id')->first();

        $pedido = new App\Models\Pedido;
        $pedidoActual = $pedido->where('codigo', '=', $codigoPedido)->first();

        if($pedidoActual)
        {        
            $entrega = $pedidoActual->horaEntregaEstimada;

            if($pedidoActual->id_estadoPedido == 1 && $pedidoActual->id_mesa == $idMesa->id)
            {
                $resultado = array("Estado" => "Pendiente", "TiempoRestante" => "El pedido todavía no ha sido tomado para su preparación");
            }

            if($pedidoActual->id_estadoPedido == 2 && $pedidoActual->id_mesa == $idMesa->id)
            {            
                $horaActual = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
                $horaEntregaEstimada = new DateTime($entrega ,new DateTimeZone('America/Argentina/Buenos_Aires'));
                
                if($horaActual > $horaEntregaEstimada)
                {
                    $resultado = array("Estado" => "Retrasado", "TiempoRestante" => "El tiempo de espera ha superado la hora estimada de entrega del pedido");
                }
                else
                {
                    $intervalo = $horaEntregaEstimada->diff($horaActual);
                    $resultado = array("Estado" => "En preparación", "TiempoRestante" => $intervalo->format('%H:%I:%S'));
                }                
            }
            if($pedidoActual->id_estadoPedido == 3 && $pedidoActual->id_mesa == $idMesa->id)
            {
                $resultado = array("Estado" => "Listo", "TiempoRestante" => "El pedido se encuentra listo para servir");
            }

        }
        else
        {
            $resultado = array("Estado" => "Error", "TiempoRestante" => "No se encontro el pedido");
        }

        return $response->withJson($resultado,200);
    }

    public function PedidosRetrasados($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];

        $pedido = new App\Models\Pedido;

        if($fecha != 0)
        {
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);
            
            $pedidosDao = $pedido->where('id_estadoPedido', '=', 3)
                                 ->where('fecha', '=', $fecha)
                                 ->get();

            if(!$pedidosDao->isEmpty())
            {
                for($i = 0; $i < count($pedidosDao); $i++)
                {           
                    $entregaDao = $pedidosDao[$i]->horaEntrega;
                    $entregaEstimadaDao = $pedidosDao[$i]->horaEntregaEstimada;
                    $horaEntrega = new DateTime($entregaDao,new DateTimeZone('America/Argentina/Buenos_Aires'));
                    $horaEntregaEstimada = new DateTime($entregaEstimadaDao ,new DateTimeZone('America/Argentina/Buenos_Aires'));

                    if($horaEntrega > $horaEntregaEstimada)
                        echo 'Codigo de pedido: ' . $pedidosDao[$i]->codigo . ". Hora de entrega estimada: " . $pedidosDao[$i]->horaEntregaEstimada . ". Hora de entrega: " . $pedidosDao[$i]->horaEntrega . "\n";
                }
            }
            else
            {
                echo 'No hay pedidos retrasados';
            }
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $pedidosDao = $pedido->where('id_estadoPedido', '=', 3)
                                 ->where('fecha', '>=', $fecha_desde)
                                 ->where('fecha', '<=', $fecha_hasta)
                                 ->get();

            if(!$pedidosDao->isEmpty())
            {
                for($i = 0; $i < count($pedidosDao); $i++)
                {           
                    $entregaDao = $pedidosDao[$i]->horaEntrega;
                    $entregaEstimadaDao = $pedidosDao[$i]->horaEntregaEstimada;
                    $horaEntrega = new DateTime($entregaDao,new DateTimeZone('America/Argentina/Buenos_Aires'));
                    $horaEntregaEstimada = new DateTime($entregaEstimadaDao ,new DateTimeZone('America/Argentina/Buenos_Aires'));

                    if($horaEntrega > $horaEntregaEstimada)
                        echo 'Codigo de pedido: ' . $pedidosDao[$i]->codigo . ". Hora de entrega estimada: " . $pedidosDao[$i]->horaEntregaEstimada . ". Hora de entrega: " . $pedidosDao[$i]->horaEntrega . "\n";
                }
            }
            else
            {
                echo 'No hay pedidos retrasados';
            }
        }
    }

    public function LoMasVendido($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];

        $pedido = new App\Models\Pedido;

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);
            
            $productosVendidosDao = $pedido->join('productos', 'pedidos.id_producto', '=', 'productos.id')
            ->where('pedidos.fecha', '=', $fecha)
            ->select('pedidos.id_producto', 'productos.nombre')
            ->orderBy('pedidos.id_producto')->get();

            PedidoApi::ProductoMasVendido($productosVendidosDao);            
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $productosVendidosDao = $pedido->join('productos', 'pedidos.id_producto', '=', 'productos.id')
            ->where('pedidos.fecha', '>=', $fecha_desde)
            ->where('pedidos.fecha', '<=', $fecha_hasta)
            ->select('pedidos.id_producto', 'productos.nombre')
            ->orderBy('pedidos.id_producto')->get();

            PedidoApi::ProductoMasVendido($productosVendidosDao);
        }   
    }

    public function LoMenosVendido($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];

        $pedido = new App\Models\Pedido;

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha);
            
            $productosVendidosDao = $pedido->join('productos', 'pedidos.id_producto', '=', 'productos.id')
                                            ->where('pedidos.fecha', '=', $fecha)
                                            ->select('pedidos.id_producto', 'productos.nombre')
                                            ->orderBy('pedidos.id_producto')->get();

                PedidoApi::ProductoMenosVendido($productosVendidosDao);          
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $productosVendidosDao = $pedido->join('productos', 'pedidos.id_producto', '=', 'productos.id')
                                            ->where('pedidos.fecha', '>=', $fecha_desde)
                                            ->where('pedidos.fecha', '<=', $fecha_hasta)
                                            ->select('pedidos.id_producto', 'productos.nombre')
                                            ->orderBy('pedidos.id_producto')->get();
            
                PedidoApi::ProductoMenosVendido($productosVendidosDao);          
            }
    }

    public function CancelarPedido($request, $response, $args)
    {
        try
        {
            $parametros = $request->getParsedBody();
            $codigo = $parametros['codigo'];
            
            $pedido = new App\Models\Pedido;
            $pedidoActual = $pedido->where('codigo', '=', $codigo)->first();
            $pedidoActual->id_estadoPedido = 4;
            $pedidoActual->save();
            $mensaje = array("Estado" => "Ok", "Mensaje" => "Pedido " . $codigo . " cancelado");
        }
        catch(Exception $e)
        {
            $error = $e->getMessage();
            $mensaje = array("Estado" => "Error", "Mensaje" => $error);
        }

        return $response->withJson($mensaje, 200);        
    }

    public function PedidosCancelados($request, $response, $args)
    {
        $fecha = $_GET['fecha'];
        $fecha_desde = $_GET['fecha_desde'];
        $fecha_hasta = $_GET['fecha_hasta'];

        $pedido = new App\Models\Pedido;

        if($fecha != 0)
        {         
            $fecha = strtotime($fecha);
            $fecha = date('Y-m-d H:i:s' , $fecha); 

            $pedidosCancelados = $pedido->where('pedidos.id_estadoPedido', '=', '4')
                                        ->where('pedidos.fecha', '=', $fecha)
                                        ->get();

            if(count($pedidosCancelados) > 0)
            {
                echo 'Pedidos cancelados' . "\n";

                for($i = 0; $i < count($pedidosCancelados); $i++)
                {
                    echo 'Codigo de pedido: ' . $pedidosCancelados[$i]->codigo . "\n";
                }
            }
            else
            {
                echo 'No se registran pedidos cancelados';
            }
        }
        else
        {
            $fecha_desde = strtotime($fecha_desde);
            $fecha_desde = date('Y-m-d H:i:s' , $fecha_desde);  
            $fecha_hasta = strtotime($fecha_hasta);
            $fecha_hasta = date('Y-m-d H:i:s' , $fecha_hasta);

            $pedidosCancelados = $pedido->where('pedidos.id_estadoPedido', '=', '4')
                                        ->where('pedidos.fecha', '>=', $fecha_desde)
                                        ->where('pedidos.fecha', '<=', $fecha_hasta)
                                        ->get();
 
            if(count($pedidosCancelados) > 0)
            {
                echo 'Pedidos cancelados' . "\n";

                for($i = 0; $i < count($pedidosCancelados); $i++)
                {
                    echo 'Codigo de pedido: ' . $pedidosCancelados[$i]->codigo . "\n";
                }
            }
            else
            {
                echo 'No se registran pedidos cancelados';
            }
        }
    }

    static function ProductoMasVendido($productosVendidosDao)
    {
        $productosVendidos = [];
    
        for($i = 0; $i < count($productosVendidosDao); $i++)
        {
            $productosVendidos[] = $productosVendidosDao[$i]->nombre;
        }

        $productosVendidos[] = -1;
        $productoMasVendido;
        $cantidad = 0;

        if(count($productosVendidos) > 1)
        {
            $contador = 1;            
    
            for($i = 0; $i <= count($productosVendidos); $i++)
            {
                if($productosVendidos[$i+1] == -1)
                {
                    if($contador > $cantidad)
                    {
                        $cantidad = $contador;
                        $productoMasVendido = $productosVendidos[$i];
                        $contador = 1;        
                    } 
                    break;            
                }

                if($productosVendidos[$i+1] == $productosVendidos[$i])
                {
                    $contador++;
                }

                else
                {
                    if($contador > $cantidad)
                    {
                        $cantidad = $contador;
                        $productoMasVendido = $productosVendidos[$i];
                        $contador = 1;        
                    }                    
                }  
            }
            echo 'Producto mas vendido: ' . $productoMasVendido . "\n" . "Cantidad de pedidos: " . $cantidad . "\n";
        }
        else
        {
            echo 'Sin operaciones' . "\n";
        }
    }
    
    static function ProductoMenosVendido($productosVendidosDao)
    {
        $productosVendidos = [];
    
        for($i = 0; $i < count($productosVendidosDao); $i++)
        {
            $productosVendidos[] = $productosVendidosDao[$i]->nombre;
        }

        $productosVendidos[] = -1;
        $productoMenosVendido;
        $cantidad = 999999999;

        if(count($productosVendidos) > 1)
        {
            $contador = 1;            
    
            for($i = 0; $i <= count($productosVendidos); $i++)
            {
                if($productosVendidos[$i+1] == -1)
                {
                    if($contador < $cantidad)
                    {
                        $cantidad = $contador;
                        $productoMenosVendido = $productosVendidos[$i];
                        $contador = 1;        
                    } 
                    break;            
                }

                if($productosVendidos[$i+1] == $productosVendidos[$i])
                {
                    $contador++;
                }

                else
                {
                    if($contador < $cantidad)
                    {
                        $cantidad = $contador;
                        $productoMenosVendido = $productosVendidos[$i];
                        $contador = 1;        
                    }                    
                }  
            }
            echo 'Producto menos vendido: ' . $productoMenosVendido . "\n" . "Cantidad de pedidos: " . $cantidad . "\n";
        }
        else
        {
            echo 'Sin operaciones' . "\n";
        }
    }
}

?>
