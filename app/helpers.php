<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ClientesReactivados;
use App\Models\Factura;
use App\Models\Factura_Detalle;
use App\Models\FacturaHistorial;
use App\Models\IncentivosHistorial;
use App\Models\Meta;
use App\Models\MetaHistorial;
use App\Models\MetaRecuperacion;
use App\Models\Producto;
use App\Models\TazaCambio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function validarStatusPagadoGlobal($clienteID)
{

    $cliente = Cliente::find($clienteID);

    $cliente->factura = $cliente->facturas()->where([
        ['status', '=', 1],
        ['tipo_venta', '=', 1]  // 1 = Credito | 2 = Contado,
        // ['status_pagado', '=', 0] // 0 = en proceso | 1 = Finalizado,
    ])->get();

    $cliente->factura_historial = $cliente->factura_historial()->where([
        ['estado', '=', 1],
        // ['debitado', '=', 0] // 0 = aun no usado el abono | 1 = ya se uso el abono,
    ])->get();

    $totalAbonos = 0;
    if (count($cliente->factura_historial) > 0) {
        foreach ($cliente->factura_historial as $itemHistorial) {
            $totalAbonos += $itemHistorial["precio"];

            $itemHistorial["debitado"] = 1; // coloco como debitado los abonos que ya fuy sumando al acumulador
            $itemHistorial->update();
        }
    }

    if (count($cliente->factura) > 0) {
        $tieneSaldo = TRUE; // Bandera para saber cuando debo de dejar ajustar el calculo de saldo restante de las facturas

        foreach ($cliente->factura as $factura) {
            $fechaPago = is_null($factura["status_pagado_at"]) ? Carbon::now() : $factura["status_pagado_at"];

            if ($tieneSaldo) {
                // print_r (json_encode( ["monto" => $factura["monto"], "totalAbonos"=>$totalAbonos ]));
                $totalAbonos =  $totalAbonos - $factura["monto"];
                // print_r (json_encode( ["monto" => $factura["monto"], "totalAbonos"=>$totalAbonos ]));
                if ($totalAbonos < 0) { // si el precio es mas alto que el total de abonos (dejo la factura abierta y ajusto el saldo_restante)
                    $tieneSaldo = FALSE;
                    $factura["status_pagado"] = 0;
                    $factura["saldo_restante"] = abs($totalAbonos);
                    $factura["status_pagado_at"] = null;
                } else { // cierro la factura y el saldo restante lo dejo 0
                    $factura["status_pagado"] = 1;
                    $factura["saldo_restante"] = 0;
                    $factura["status_pagado_at"] = $fechaPago;
                }
            } else { // si no tiene saldo reinicio la factura
                $factura["saldo_restante"] = $factura["monto"];
                $factura["status_pagado"] = 0;
                $factura["status_pagado_at"] = null;
            }

            $factura->update();
        }
    }

    // print_r (json_encode( ["cliente" => $cliente, "totalAbonos"=>$totalAbonos ]));

}

function debitarAbonosClientes($clienteID)
{
    $cliente = Cliente::find($clienteID);
    // $cliente = Cliente::find($clienteID);
    // $cliente = $abono->cliente;

    $cliente->factura = $cliente->facturas()->where([
        ['status', '=', 1],
        ['status_pagado', '=', 0] // 0 = en proceso | 1 = Finalizado,
    ])->get();

    $cliente->factura_historial = $cliente->factura_historial()->where([
        ['estado', '=', 1],
        ['debitado', '=', 0] // 0 = aun no usado el abono | 1 = ya se uso el abono,
    ])->get();

    // print_r(json_encode($cliente->factura_historial));
    $totalAbonos = 0;
    if (count($cliente->factura_historial) > 0) {
        foreach ($cliente->factura_historial as $itemHistorial) {
            $totalAbonos += $itemHistorial["precio"];

            $itemHistorial["debitado"] = 1; // coloco como debitado los abonos que ya fuy sumando al acumulador
            $itemHistorial->update();
        }
    }

    if (count($cliente->factura) > 0) {
        $tieneSaldo = TRUE; // Bandera para saber cuando debo de dejar ajustar el calculo de saldo restante de las facturas

        foreach ($cliente->factura as $factura) {
            $fechaPago = is_null($factura["status_pagado_at"]) ? Carbon::now() : $factura["status_pagado_at"];
            if ($tieneSaldo) {
                // 200 - 500 = -300 ajusta el restante
                // 500 - 500 = 0  cierra factura y ajusta restante
                $totalAbonos =  $totalAbonos - $factura["saldo_restante"];

                if ($totalAbonos < 0) { // si el precio es mas alto que el total de abonos (dejo la factura abierta y ajusto el saldo_restante)
                    $tieneSaldo = FALSE;
                    $factura["saldo_restante"] = abs($totalAbonos);
                } else { // cierro la factura y el saldo restante lo dejo 0
                    $factura["saldo_restante"] = 0;
                    $factura["status_pagado"] = 1;
                    $factura["status_pagado_at"] = $fechaPago;
                }
            }

            $factura->update();
        }
    }

    // print_r (json_encode( ["cliente" => $cliente->factura, "totalAbonos"=>$totalAbonos ]));

}

function calcularDeudaFacturaCliente($clienteID)
{
    $cliente = Cliente::find($clienteID);
    $data = array(
        "saldo_restante" => 0,
        "totalFactura" => 0
    );

    $cliente->factura = $cliente->facturas()->where([
        ['status', '=', 1], //1 Activo | 0 Inactivo
        ['tipo_venta', '=', 1], // 1 = Credito | 2 = Contado,
        ['status_pagado', '=', 0] // 0 = en proceso | 1 = Finalizado,
    ])->get();

    // print_r (count($cliente->factura));
    if (count($cliente->factura) > 0) {

        $totalFactura = 0;
        $saldoRestanteFactura = 0;

        foreach ($cliente->factura as $factura) {
            $saldoRestanteFactura += $factura["saldo_restante"];
            $totalFactura += $factura["monto"];
        }

        $data["saldo_restante"] = $saldoRestanteFactura;
        $data["totalFactura"] = $totalFactura;
    }


    return $data;
}

function calcularDeudaFacturasGlobal($clienteID)
{
    $cliente = Cliente::find($clienteID);

    $cliente->factura = $cliente->facturas()->where([
        ['status', '=', 1],
        ['tipo_venta', '=', 1]  // 1 = Credito | 2 = Contado,
        // ['status_pagado', '=', 0] // 0 = en proceso | 1 = Finalizado,
    ])->get();

    $cliente->factura_historial = $cliente->factura_historial()->where([
        ['estado', '=', 1],
        // ['debitado', '=', 0] // 0 = aun no usado el abono | 1 = ya se uso el abono,
    ])->get();

    $totalAbonos = 0;
    if (count($cliente->factura_historial) > 0) {
        foreach ($cliente->factura_historial as $itemHistorial) {
            $totalAbonos += $itemHistorial["precio"];
        }
    }

    if (count($cliente->factura) > 0) {
        foreach ($cliente->factura as $factura) {
            $totalAbonos =  $totalAbonos - $factura["monto"];
        }
    }

    return number_format($totalAbonos, 2);
}

function actualizarCantidadDetalleProducto($detalleID, $cantidad)
{
    $detalle = Factura_Detalle::find($detalleID);

    if ($detalle) {
        if (($detalle->cantidad - $cantidad) <= 0) {
            $detalle->cantidad = 0;  // si la cantidad es menor o igual a 0 la pongo en 0
            $detalle->estado = 0;    // si la cantidad es menor o igual a 0 desactivo el producto de la factura
        } else {
            $detalle->cantidad = $detalle->cantidad - $cantidad;
            // $total +=  $productoDetalle["precio_unidad"] * $productoDetalle["cantidad"];
            $detalle->precio = $detalle["precio_unidad"] * $detalle->cantidad;
        }

        $detalle->update();

        ActualizarPrecioFactura($detalle->factura_id);
        return true;
    }



    return false;
}


function ActualizarPrecioFactura($factura_id)
{
    $factura = Factura::find($factura_id);
    $factura_detalle = $factura->factura_detalle()->where([
        ['estado', '=', 1],
    ])->get();

    if (count($factura_detalle) > 0) {
        $total = 0;

        foreach ($factura_detalle as $productoDetalle) {
            // $total +=  $productoDetalle["precio_unidad"] * $productoDetalle["cantidad"];
            $total +=  $productoDetalle["precio"];
        }

        $factura->monto = $total;
        $factura->saldo_restante = $total;
    } else { // NO TIENES PRODUCTOS ACTIVOS EN LA FACTURA
        // $factura->monto = 0;
        // $factura->saldo_restante = 0;

        // desactibo la factura para que no se tome en cuenta
        $factura->status = 0;
        validarFacturaClienteReactivado($factura_id);
    }

    // print_r (json_encode($factura));

    $factura->update();

    validarStatusPagadoGlobal($factura->cliente_id);
}

function validarFacturaClienteReactivado($factura_id)
{
    ClientesReactivados::where([
        ['estado', '=', 1],
        ['factura_id', '=', $factura_id],
    ])->update(['estado' => 0]);
}



function AsignarPrecioPorUnidadGlobal()
{
    $facturas = Factura::all();

    foreach ($facturas  as $key => $factura) {
        $factura->factura_detalle = $factura->factura_detalle()->where([
            ['estado', '=', 1],
        ])->get();
        if (count($factura->factura_detalle) > 0) {
            $precio_unidad = 0;

            foreach ($factura->factura_detalle as $productoDetalle) {

                $precio_unidad =  $productoDetalle["precio"] / $productoDetalle["cantidad"];
                $precio_unidad_format = number_format($precio_unidad, 2);

                $productoDetalle["precio_unidad"] = $precio_unidad_format;
                $productoDetalle->update();
            }
            // $factura->precio_unidad = $precio_unidad;

            ActualizarPrecioFactura($factura->id);
            // validarStatusPagadoGlobal($factura->cliente_id);

        }
    }
}

function devolverStockProducto($detalle_id, $cantidad)
{
    // print_r(json_encode($detalle_id));
    $detalle = Factura_Detalle::where("id", $detalle_id)->first();
    if ($detalle) {
        $producto = Producto::where("id", $detalle->producto_id)->first();
        // print_r(json_encode($producto));
        $producto->stock = $producto->stock + $cantidad;
        $producto->estado = 1;
        $producto->update();

        if (count($detalle->regaloFacturado) > 0) {
            foreach ($detalle->regaloFacturado as $regaloF) {
                // agrego la relacion con la tabla producto para regalo
                $regaloF->regalo;

                // producto para regalo
                $regalo = Producto::where("id", $regaloF->regalo->id_producto_regalo)->first();
                // print_r(json_encode($producto));
                $regalo->stock = $regalo->stock + ($regaloF->regalo->cantidad * $cantidad);
                $regalo->estado = 1;
                $regalo->update();

                // regalo facturado 
                $regaloF->cantidad_regalada = $regaloF->cantidad_regalada - ($regaloF->regalo->cantidad * $cantidad);
                $regaloF->update();
            }
        }

        // print_r(json_encode($producto));

        return true;
    }

    return false;
}

function queryEstadoCuenta($cliente_id)
{
    $response = [
        "estado_cuenta" => [],
    ];

    if (is_numeric($cliente_id)) {
        $query = "SELECT
                *
            FROM (
                SELECT
                    c.id AS cliente_id,
                    CONCAT('PED-',f.id ) AS `numero_documento`,
                    'Pedido' as tipo_documento,
                    f.created_at AS fecha,
                    f.fecha_vencimiento AS f_vencimiento,
                f.monto AS credito,
                    '' AS abono
                FROM clientes c
                INNER JOIN facturas f ON f.cliente_id = c.id
                WHERE
                    f.`status` = 1
                    UNION ALL
                SELECT
                    c.id AS cliente_id,
                    CONCAT('REC-', rh.numero ) AS `numero_documento`,
                    'Recibo' as tipo_documento,
                    rh.created_at AS fecha,
                    rh.created_at AS f_vencimiento,
                '' AS credito,
                    fh.precio AS abono
                FROM	clientes c
                INNER JOIN factura_historials fh ON fh.cliente_id = c.id
                INNER JOIN recibo_historials rh ON rh.factura_historial_id = fh.id
                WHERE
                    fh.`estado` = 1
            ) estado_cuenta
            WHERE estado_cuenta.cliente_id = $cliente_id
            ORDER BY estado_cuenta.fecha ASC
        ";

        // if($request->userId != 0){
        //     $query = $query." AND c.user_id = ".$request->userId;
        // }

        $estadoCuenta = DB::select($query);

        if (count($estadoCuenta) > 0) {
            $saldo = 0;
            foreach ($estadoCuenta as $operacion) {
                // if(!isset($operacion->saldo)) $operacion->saldo = 0;
                $saldo = ($operacion->credito != "") ? number_format((float)$operacion->credito, 2, ".", "") + number_format((float)$saldo, 2, ".", "")   : number_format((float)$saldo, 2, ".", "") - number_format((float)($operacion->abono), 2, ".", "");

                $operacion->saldo = $saldo;
                // print_r(intval($operacion->credito) + $operacion->saldo ."<br>");
            }
            $response["estado_cuenta"] = $estadoCuenta;
        }
    }

    return $response;
}

function validarReactivacionCliente($user_id, $cliente_id, $factura_id, $dataClienteInactivo)
{

    // print_r($listaInactivos);
    if (count($dataClienteInactivo) > 0) { // si existe en la lista de clientes inactivos registro el dia que se reactivo

        ClientesReactivados::create([
            'user_id'         => $user_id,
            'cliente_id' => $cliente_id,
            'factura_id' => $factura_id,
            'estado' => 1,

        ]); // inserto registro de reactivacion de cliente

    }
}

function carteraQuery($request)
{
    $response = [
        'factura' => [],
        'total' => 0,
        'recuperacion' => 0,
        'abonos' => [],
    ];

    $userId = $request['userId'];
    // "dateIni": "2022-03-15",
    // "dateFin": "2022-03-15",
    if (empty($request->dateIni)) {
        $dateIni = Carbon::now();
    } else {
        $dateIni = Carbon::parse($request->dateIni);
    }

    if (empty($request->dateIni)) {
        $dateFin = Carbon::now();
    } else {
        $dateFin = Carbon::parse($request->dateFin);
    }
    // DB::enableQueryLog();
    $facturasStorage = Factura::select("*")
        //->where('tipo_venta', $request->tipo_venta ? $request->tipo_venta : 1) // si envian valor lo tomo, si no por defecto toma credito
        ->where('status_pagado', $request->status_pagado ? $request->status_pagado : 0) // si envian valor lo tomo, si no por defecto asigno por pagar = 0
        ->where('status', 1);

    if (!$request->allDates) {
        $facturasStorage = $facturasStorage->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
    }

    if ($userId != 0) {
        $facturasStorage = $facturasStorage->where('user_id', $userId);
    }

    $facturas = $facturasStorage->get();
    // $query = DB::getQueryLog();
    // dd(json_encode($query));

    if (count($facturas) > 0) {
        $total = 0;
        $clientes = [];

        foreach ($facturas as $factura) {
            $total += $factura->saldo_restante;
            // $total += number_format((float) ($factura->monto),2,".","");
            //$total += number_format((float) ($factura->saldo_restante),2,".","");


            $factura->user;
            $factura->cliente->factura_historial = $factura->cliente->factura_historial()->where([
                ['estado', '=', 1],
            ])->get()->last();
            $factura->factura_detalle = $factura->factura_detalle()->where([
                ['estado', '=', 1],
            ])->get();

            $factura->montos_recibos = $factura
                ->cliente
                ->factura_historial()
                ->where([
                    ['estado', '=', 1],
                ])
                ->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"])
                ->get();

            // $factura->recibos = $factura->cliente->factura_historial()->where([
            //     ['estado', '=', 1],
            // ])->recibo_historial->get();

            array_push($clientes, $factura->cliente_id);
        }

        if (count($clientes) > 0) {
            $clientesUnicos = array_unique($clientes);

            $clienteStore =  FacturaHistorial::whereIn('cliente_id', $clientesUnicos)
                // ->select('id', 'cliente_id','precio','estado','created_at')
                ->where([
                    ['estado', '=', 1],
                ]);

            if (!$request->allDates) {
                $clienteStore = $clienteStore->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
            }

            $abonos = $clienteStore->get();
            $response["recuperacion"] = sumaRecuperacion($abonos);
            $response["abonos"] = $abonos;

            // echo json_encode($cliente);
        }

        $response["total"]   = $total;
        $response["factura"] = $facturas;
    }

    return $response;
}


function sumaRecuperacion($abonos)
{
    $result = 0;
    // echo json_encode($abonos);

    if (count($abonos) > 0) {
        foreach ($abonos as $abono) {
            $result += number_format((float) ($abono->precio), 2, ".", "");
        }
    }

    return $result;
}


function ventasMetaQuery($request)
{
    $response = [
        'factura' => [],
        'total' => 0,
        'meta' => 0,
        'meta_monto' => 0,
        'recuperacion' => 0,
        'recuperacion_monto' => 0,
        'abonos' => [],
    ];

    $metaValue = 0;
    $userId = $request['userId'];

    // "dateIni": "2022-03-15",
    // "dateFin": "2022-03-15",
    if (empty($request->dateIni)) {
        $dateIni = Carbon::now();
    } else {
        $dateIni = Carbon::parse($request->dateIni);
    }

    if (empty($request->dateIni)) {
        $dateFin = Carbon::now();
    } else {
        $dateFin = Carbon::parse($request->dateFin);
    }

    $dateIni =  Carbon::parse($dateIni);
    $dateFin =  Carbon::parse($dateFin);


    $facturasStorage = Factura::select("*")
        //->where('tipo_venta', $request->tipo_venta ? $request->tipo_venta : 1) // si envian valor lo tomo, si no por defecto toma credito
        // ->where('status_pagado', $request->status_pagado ? $request->status_pagado : 0) // si envian valor lo tomo, si no por defecto asigno por pagar = 0
        ->where('status', 1);

    if (!$request->allDates) {
        $facturasStorage = $facturasStorage->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
    }

    if ($userId != 0) {
        $facturasStorage = $facturasStorage->where('user_id', $userId);
    }

    $facturas = $facturasStorage->get();
    $clientes = [];
    if (count($facturas) > 0) {
        $total = 0;
        foreach ($facturas as $factura) {
            // $total += $factura->saldo_restante;
            $total += number_format((float) ($factura->monto), 2, ".", "");
            //$total += number_format((float) ($factura->saldo_restante),2,".","");


            $factura->user;
            $factura->cliente->factura_historial = $factura->cliente->factura_historial()->where([
                ['estado', '=', 1],
            ])->get()->last();
            $factura->factura_detalle = $factura->factura_detalle()->where([
                ['estado', '=', 1],
            ])->get();

            array_push($clientes, $factura->cliente_id);
        }

        $response["total"]    = $total;
        $response["factura"] = $facturas;
    }

    // $meta = Meta::where('user_id', $userId)->first();
    // DB::enableQueryLog();
    $meta = getMetaPorUsuario($userId, $dateIni->toDateString() . " 00:00:00", $dateFin->toDateString() . " 23:59:59");
    // dd(DB::getQueryLog());
    // $meta = Meta::select("*")
    //     ->where('user_id', $userId)
    //     ->first();
    // print_r(json_encode($meta));
    // (453 * 100)/1500

    // dd(json_encode([$userId, $dateIni->toDateString() . " 00:00:00", $dateFin->toDateString() . " 23:59:59"]));
    // dd(json_encode($meta));
    if ($meta) {
        $metaValue = $meta->monto_meta;
        $response["meta_monto"] = $meta->monto_meta;
        // print_r(json_encode($metaValue));
        if ($metaValue == 0) {
            $averageMeta = 0;
        } else {
            $averageMeta = ($response["total"] / $metaValue) * 100;
        }

        $response["meta"] = (float) number_format((float) ($averageMeta), 2, ".", "");
    }

    if (count($clientes) > 0) {
        $clientesUnicos = array_unique($clientes);

        $abonosStore =  FacturaHistorial::whereIn('cliente_id', $clientesUnicos)
            // ->select('id', 'cliente_id','precio','estado','created_at')
            ->where([
                ['estado', '=', 1],
            ]);

        if (!$request->allDates) {
            $abonosStore = $abonosStore->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
        }

        $abonos = $abonosStore->get();
        $response["recuperacion_monto"] = sumaRecuperacion($abonos);

        if ($response["meta_monto"] == 0) {
            $recuperacion = 0;
        } else {
            $recuperacion = ($response["recuperacion_monto"] * 100) / $response["meta_monto"];
        }

        $response["recuperacion"] = (float) number_format((float) $recuperacion, 2, ".", "");
        $response["abonos"] = $abonos;

        // echo json_encode($cliente);
    }

    return $response;
}

function newrecuperacionQuery($user, $dateini, $dateFin)
{
    $userId = $user->id;
    $response = [
        // 'facturasTotal' => 0,
        'abonosTotal' => 0,
        'abonosTotalLastMount' => 0,
        'recuperacionPorcentaje' => 0,
        'recuperacionTotal' => 0,
        'user_id' => $userId,

    ];

    $inicioMesActual =  Carbon::parse($dateini)->firstOfMonth()->toDateString();
    $finMesActual =  Carbon::parse($dateFin)->lastOfMonth()->toDateString();
    // $inicioMesActual =  Carbon::now()->firstOfMonth()->toDateString();
    // $finMesActual =  Carbon::now()->lastOfMonth()->toDateString();
    // dd([$inicioMesActual,$finMesActual]);

    $meta_recuperacion = getMetaRecuperacionMensual($userId, $inicioMesActual, $finMesActual);

    if ($meta_recuperacion) {
        $response["recuperacionTotal"] = (float) number_format((float) $meta_recuperacion->monto_meta, 2, ".", "");
    } else {
        crearMetaRecuperacionMensual();
        $metaCreada = getMetaRecuperacionMensual($userId, $inicioMesActual, $finMesActual);
        $response["recuperacionTotal"] = (float) number_format((float) $metaCreada, 2, ".", ""); // meta
    }


    // Inicio el calculo de recuperacion

    $abonosStore =  FacturaHistorial::where('user_id', $userId)
        // ->whereBetween('created_at', [$inicioMesAnterior." 00:00:00",  $finMesAnterior ." 23:59:59"])
        ->where('created_at', "<", $inicioMesActual . " 00:00:00")
        ->where('estado', 1);

    $abonos = $abonosStore->get();

    $response["abonosTotal"] =  (float) number_format((float) sumaRecuperacion($abonos), 2, ".", "");

    $clienteStoreCurrentMount =  FacturaHistorial::where('user_id', $userId)
        ->whereBetween('created_at', [$inicioMesActual . " 00:00:00",  $finMesActual . " 23:59:59"])
        ->where('estado', 1)
        ->get();

    // Ahora es el mes actual y no ultimo mes 
    $response["abonosTotalLastMount"] =  (float) number_format((float) sumaRecuperacion($clienteStoreCurrentMount), 2, ".", "");


    if ($response["abonosTotalLastMount"] >= 1 && $response["recuperacionTotal"] >= 1) {
        $porcentaje = ($response["abonosTotalLastMount"] * 100) / $response["recuperacionTotal"]; // porcentaje
    } else {
        $porcentaje = 0; // porcentaje
    }

    $response["recuperacionPorcentaje"] = (float) number_format((float) $porcentaje, 2, ".", "");
    // }

    $response["user"] = $user;

    return $response;
}

function productosVendidosPorUsuario($user, $request)
{
    $id = $user->id;
    $response = [
        'totalProductos' => 0,
        'productos' => [],
        'user' => $user,
    ];
    $contadorProductos = 0;
    $idProductos = [];

    if (empty($request->dateIni)) {
        $dateIni = Carbon::now();
    } else {
        $dateIni = Carbon::parse($request->dateIni);
    }

    if (empty($request->dateIni)) {
        $dateFin = Carbon::now();
    } else {
        $dateFin = Carbon::parse($request->dateFin);
    }

    $facturasStorage = Factura::select("*")
        // ->where('status_pagado', $request->status_pagado ? $request->status_pagado : 0) // si envian valor lo tomo, si no por defecto asigno por pagar = 0
        ->where('status', 1);

    if (!$request->allDates) {
        $facturasStorage = $facturasStorage->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
    }

    $facturasStorage = $facturasStorage->where('user_id', $id);

    $facturas = $facturasStorage->get();
    foreach ($facturas as $factura) {
        $factura->factura_detalle = $factura->factura_detalle()->where([
            ['estado', '=', 1],
        ])->get();

        if (count($factura->factura_detalle) > 0) {
            foreach ($factura->factura_detalle as $factura_detalle) {
                array_push($idProductos, $factura_detalle->id);
                $contadorProductos = $contadorProductos + $factura_detalle->cantidad;
                // $factura_detalle->producto  = $factura_detalle->producto; 
            }
        }

        // $response["productos"][] = $factura->factura_detalle; 
        // array_push($response["productos"],$factura->factura_detalle) ; 
    }

    if (count($idProductos) > 0) {
        // $facturas_detalle =  Factura_Detalle::whereIn('id', $idProductos)->get();
        // if(count($facturas_detalle)>0){
        //     foreach ($facturas_detalle as $factura_detalle) {
        //         $factura_detalle->producto = $factura_detalle->producto;
        //         $response["productos"][] = $factura_detalle;
        //     }
        // }


        $query = "SELECT 
            SUM(fd.cantidad) AS cantidad,
            p.*
        FROM factura_detalles fd
        INNER JOIN productos p ON p.id = fd.producto_id
        WHERE fd.id IN(" . implode(",", $idProductos) . ")
        GROUP BY fd.producto_id";

        $productos = DB::select($query);

        if (count($productos) > 0) {
            $response["productos"] = $productos;
        }
    }

    // $response = $facturas;
    $response["totalProductos"] = $contadorProductos;
    // $response["id"] = $idProductos;

    return $response;
}


function crearMetaRecuperacionMensual()
{
    // La meta de recuperacion no es lo mismo que META. Es solo para la seccion de recuperacion
    $inicioMesActual =  Carbon::now()->firstOfMonth()->toDateString();
    $finMesActual =  Carbon::now()->lastOfMonth()->toDateString();
    // DB::enableQueryLog();

    $users = User::where([
        ["estado", "=", 1]
    ])->get();

    foreach ($users as $user) {
        $total = 0;

        $facturas = Factura::select("*")
            ->where('tipo_venta',  1) // credito 
            ->where('status_pagado', 0)
            ->where('created_at', "<", $inicioMesActual . " 00:00:00")
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->get();

        if (count($facturas) > 0) {
            foreach ($facturas as $factura) {
                $factura->user;
                $total += number_format((float) ($factura->saldo_restante), 2, ".", "");
            }
        }

        // $resultado = $total  * 0.85;
        $resultado = $total  * 1;
        $monto_meta = (float) number_format((float) $resultado, 2, ".", ""); // meta

        $existeUsuarioMesActual = !!getMetaRecuperacionMensual($user->id, $inicioMesActual, $finMesActual);

        if (!$existeUsuarioMesActual) {
            MetaRecuperacion::create([
                'user_id' => $user->id,
                'monto_meta' => $monto_meta,
                'estado' => 1,
            ]);
        }
    }
}

function crearMetaMensual($mes = "")
{
    // Para crear la meta mensual solo tomamos el valor de la tabla meta, ese es el valor por defecto
    $mensajes = [];

    $response[] = 'La meta fue eliminado con exito.';

    if ($mes == "") {
        $inicioMesActual =  Carbon::now()->firstOfMonth()->toDateString();
        $finMesActual =  Carbon::now()->lastOfMonth()->toDateString();
    } else {
        $inicioMesActual =  Carbon::parse($mes)->firstOfMonth()->toDateString();
        $finMesActual =  Carbon::parse($mes)->lastOfMonth()->toDateString();
    }
    // DB::enableQueryLog();

    $users = User::where([
        ["estado", "=", 1]
    ])->get();

    foreach ($users as $user) {
        $meta = Meta::where('user_id', $user->id)->first(); // Meta default

        if (is_null($meta)) { // agrego meta por no existir
            Meta::create([
                'user_id' => $user->id,
                'monto' => 0,
                'estado' => 1,
            ]);

            $meta = Meta::where('user_id', $user->id)->first(); // Meta default
            // dd(json_encode($meta ));
        }

        $existeUsuarioMesActual = getMetaPorUsuario($user->id, $inicioMesActual . " 00:00:00", $finMesActual . " 23:59:59");
        if (!$existeUsuarioMesActual) {
            // dd([$user->id, $inicioMesActual . " 00:00:00", $finMesActual . " 23:59:59"] );
            $fechaActual = Carbon::now();

            MetaHistorial::create([
                'user_id' => $user->id,
                'monto_meta' => $meta->monto,
                'fecha_asignacion' => ($mes == "") ? $fechaActual->toDateTimeString() : $inicioMesActual . " 00:00:00",
                'estado' => 1,
            ]);
        }
    }
}

function getMetaRecuperacionMensual($user_id, $inicioMesActual, $finMesActual)
{
    // $inicioMesActual =  Carbon::now()->firstOfMonth()->toDateString();
    // $finMesActual =  Carbon::now()->lastOfMonth()->toDateString();

    $meta_recuperacion = MetaRecuperacion::where('user_id', $user_id)
        ->whereBetween('created_at', [$inicioMesActual . " 00:00:00",  $finMesActual . " 23:59:59"])
        ->where('estado', 1)
        ->first();

    return ($meta_recuperacion) ? $meta_recuperacion : false;
}

function getMetaPorUsuario($user_id, $fechaInicio = "", $fechaFin = "")
{
    $meta_recuperacion = MetaHistorial::where('user_id', $user_id)
        ->where('estado', 1);


    $meta_recuperacion->when($fechaInicio != "", function ($q) use ($fechaInicio, $fechaFin) {
        return $q->whereBetween('fecha_asignacion', [$fechaInicio,  $fechaFin]);
    });

    // $meta_recuperacion->first();

    return ($meta_recuperacion) ? $meta_recuperacion->first() : false;
}

function cambiarClientesAListaNegraFacturasMora60_90()
{
    // calculo si tiene un recibo en mora de 60 - 90
    $fechaHoy = Carbon::now();

    $categoriaListaNegra =  Categoria::where([
        ['tipo', '=', "LN"],
        ['estado', '=', 1]
    ])->first();

    $clientes = Cliente::select("*")
        ->where('categoria_id', $categoriaListaNegra->id)
        ->where('estado', 1)
        ->get();

    $idClientesConListaNegra = [];

    foreach ($clientes as $cliente) {
        $idClientesConListaNegra[] = $cliente->id;
    }

    $facturas = Factura::where('status_pagado', 0)
        ->where('status', 1);

    $facturas->when(count($idClientesConListaNegra) > 0, function ($q) use ($idClientesConListaNegra) {
        return $q->whereNotIn('cliente_id', $idClientesConListaNegra);
    });

    $facturas = $facturas->get();
    // dd(json_encode($facturas->get()));

    if (count($facturas) > 0) {
        foreach ($facturas as $factura) { // valido todas sus facturas, para ver si tiene una en mora
            $fechaPasado60DiasVencimiento = Carbon::parse($factura->created_at)->addDays(60)->toDateTimeString();

            if (Carbon::parse($fechaPasado60DiasVencimiento)->diffInDays($fechaHoy) >= 60) {
                $clienteEnMora = Cliente::find($factura->cliente_id);
                $clienteEnMora->categoria_id = $categoriaListaNegra->id; // agrego a lista negra por estas en mora de 60 dias o mas
                $clienteEnMora->save();
            }
        }
    }
}

function convertTazaCambio($monto)
{
    $result = number_format((float) (0), 2, ".", "");

    $tazacambio = TazaCambio::where('estado', 1)->first();
    $taza = number_format((float) ($tazacambio->monto), 2, ".", "");
    $montoCambio = number_format((float) ($monto), 2, ".", "");

    $result = (float) number_format((float) ($taza * $montoCambio), 2, ".", "");

    return $result;
}

function decimal($monto)
{
    return (float) number_format((float) ($monto), 2, ".", "");
}

function productosVendidos($request)
{
    // $id = $user->id;
    $response = [
        'totalProductos' => 0,
        'productos' => [],
        // 'user' => $user,
    ];
    $contadorProductos = 0;
    $idProductos = [];

    if (empty($request->dateIni)) {
        $dateIni = Carbon::now();
    } else {
        $dateIni = Carbon::parse($request->dateIni);
    }

    if (empty($request->dateFin)) {
        $dateFin = Carbon::now();
    } else {
        $dateFin = Carbon::parse($request->dateFin);
    }

    $facturasStorage = Factura::select("*")->where('status', 1);

    $facturasStorage->when($request->allDates && $request->allDates == "false", function ($q) use ($dateIni, $dateFin) {
        return $q->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
    });
    // if (!$request->allDates) {
    //     $facturasStorage = $facturasStorage->whereBetween('created_at', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
    // }

    // $facturasStorage = $facturasStorage->where('user_id', $id);

    $facturas = $facturasStorage->get();
    foreach ($facturas as $factura) {
        $factura->factura_detalle = $factura->factura_detalle()->where([
            ['estado', '=', 1],
        ])->get();

        if (count($factura->factura_detalle) > 0) {
            foreach ($factura->factura_detalle as $factura_detalle) {
                array_push($idProductos, $factura_detalle->id);
                $contadorProductos = $contadorProductos + $factura_detalle->cantidad;
            }
        }
    }

    if (count($idProductos) > 0) {

        $query = "SELECT 
            SUM(fd.cantidad) AS cantidad,
            p.*
        FROM factura_detalles fd
        INNER JOIN productos p ON p.id = fd.producto_id
        WHERE fd.id IN(" . implode(",", $idProductos) . ")
        GROUP BY fd.producto_id";

        $productos = DB::select($query);

        if (count($productos) > 0) {
            $response["productos"] = $productos;
        }
    }

    // $response = $facturas;
    $response["totalProductos"] = $contadorProductos;
    // $response["id"] = $idProductos;

    return $response;
}

function crearIncentivosHistorial($fechaIndice, $userId)
{
    $ultimoPorcentaje = IncentivosHistorial::where([["user_id", "=", $userId]])->orderBy('created_at', 'desc')->first();

    $incentivo = IncentivosHistorial::create([
        'user_id' => $userId,
        'porcentaje' => $ultimoPorcentaje->porcentaje,
        'fecha_indice' => $fechaIndice,
        'estado' => 1,
    ]);

    $incentivoCreated = IncentivosHistorial::find($incentivo->id)->first();
    return $incentivoCreated;
}
