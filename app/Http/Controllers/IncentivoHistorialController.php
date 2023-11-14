<?php

namespace App\Http\Controllers;

use App\Models\IncentivosHistorial;
use App\Models\Recibo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class IncentivoHistorialController extends Controller
{
    public function index(Request $request)
    {
        $response = [];
        $status = 200;
        // $facturaEstado = 1; // Activo
        $parametros = [["estado", 1]];

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

        // DB::enableQueryLog();

        $incentivos =  IncentivosHistorial::where($parametros);

        // ** Filtrado por rango de fechas 
        $incentivos->when($request->allDates && $request->allDates == "false", function ($q) use ($dateIni, $dateFin) {
            return $q->whereBetween('fecha_indice', [$dateIni->toDateString() . " 00:00:00",  $dateFin->toDateString() . " 23:59:59"]);
        });

        // ** userId  
        $incentivos->when($request->userId && $request->userId != 0, function ($q) use ($request) {
            return $q->where('user_id', $request->userId);
        });

        if ($request->disablePaginate == 0) {
            $incentivos = $incentivos->orderBy('created_at', 'desc')->paginate(15);
        } else {
            $incentivos = $incentivos->get();
        }

        if (count($incentivos) > 0) {
            foreach ($incentivos as $incentivo) {
                $incentivo->user;
            }

            // $response[] = $ProductosFacturados;
        }

        $response = $incentivos;

        return response()->json($response, $status);
    }

    public function show($id, Request $request)
    {
        $response = [];
        $status = 400;
        // $productoEstado = 1; // Activo

        if (is_numeric($id)) {

            // if($request->input("estado") != null) $productoEstado = $request->input("estado");
            // dd($productoEstado);

            $producto =  IncentivosHistorial::find($id);


            // $cliente =  Cliente::find($id);
            if ($producto) {
                $response = $producto;
                $status = 200;
            } else {
                $response[] = "El incentivo no existe o fue eliminado.";
            }
        } else {
            $response[] = "El Valor de Id debe ser numerico.";
        }

        return response()->json($response, $status);
    }

    public function store(Request $request)
    {
        $response = [];
        $status = 400;
        $validation = Validator::make($request->all(), [
            'user_id'        => 'required|numeric',
            'porcentaje'            => 'numeric|required',
            'fecha_indice'            => 'required',
        ]);
        if ($validation->fails()) {
            return response()->json([$validation->errors()], 400);
        } else {

            $inicioMesActual =  Carbon::parse($request['fecha_indice'])->firstOfMonth()->toDateString();
            $finMesActual =  Carbon::parse($request['fecha_indice'])->lastOfMonth()->toDateString();
            $existeCoincidencia = IncentivosHistorial::whereBetween('fecha_indice', [$inicioMesActual . " 00:00:00",  $finMesActual . " 23:59:59"])->where([
                ["user_id", "=", $request['user_id']],
            ])->exists();
            // print_r(json_encode($existeCoincidencia));
            if (!$existeCoincidencia) { // si no hay coincidencia
                $response = IncentivosHistorial::create([
                    'user_id' => $request['user_id'],
                    'porcentaje' => $request['porcentaje'],
                    'fecha_indice' => $request['fecha_indice'],
                    'estado' => 1,
                ]);
                $status = 201;
            }
        }
        return response()->json($response, $status);
    }

    public function update(Request $request, $id)
    {
        $response = [];
        $status = 400;

        if (is_numeric($id)) {
            $incentivo =  IncentivosHistorial::find($id);

            if ($incentivo) {
                $validation = Validator::make($request->all(), [
                    'user_id' => 'required',
                    'porcentaje' => 'required|numeric',
                    'fecha_indice' => 'required',
                ]);

                if ($validation->fails()) {
                    $response[] = $validation->errors();
                } else {
                    // $inicioMesActual =  Carbon::parse($request['fecha_indice'])->firstOfMonth()->toDateString();
                    // $finMesActual =  Carbon::parse($request['fecha_indice'])->lastOfMonth()->toDateString();
                    // // $existeCoincidencia = IncentivosHistorial::whereBetween('fecha_indice', [$inicioMesActual . " 00:00:00",  $finMesActual . " 23:59:59"])->where([
                    // //     ["user_id", "=", $request['user_id']],
                    // // ])->exists();

                    // if (!$existeCoincidencia) { // si no hay coincidencia
                        $incentivo->update([
                            'user_id' => $request['user_id'],
                            'porcentaje' => $request['porcentaje'],
                            'fecha_indice' => $request['fecha_indice'],
                        ]);
                        $response[] = 'Incentivo modificado con exito.';
                        $status = 200;
                    // } else {
                    //     $response[] = 'Error al modificar los datos.';
                    // }
                }
            } else {
                $response[] = "El incentivo no existe.";
            }
        } else {
            $response[] = "El Valor de Id debe ser numerico.";
        }

        return response()->json($response, $status);
    }
}
