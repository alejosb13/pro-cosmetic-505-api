<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\Producto;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use App\Mail\PdfMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
class PdfController extends Controller
{


    public function facturaPago($id,Request $request)
    {
        $response = [];
        $status = 400;
        $facturaEstado = 1; // Activo
        
        if(is_numeric($id)){
                    
            // if($request->input("estado") != null) $facturaEstado = $request->input("estado");
            // dd($productoEstado);
        
            $factura =  Factura::with('factura_detalle','cliente','factura_historial')->where([
                ['id', '=', $id],
                // ['estado', '=', $facturaEstado],
            ])->first();
            
            if(count($factura->factura_detalle)>0){
                foreach ($factura->factura_detalle as $key => $productoDetalle) {
                    $producto = Producto::find($productoDetalle["producto_id"]);
                    // dd($productoDetalle["id"]);
                    $productoDetalle["marca"]       = $producto->marca; 
                    $productoDetalle["modelo"]      = $producto->modelo; 
                    // $productoDetalle["stock"]       = $producto->stock; 
                    // $productoDetalle["precio"]      = $producto->precio; 
                    $productoDetalle["linea"]       = $producto->linea; 
                    $productoDetalle["descripcion"] = $producto->descripcion; 
                    // $productoDetalle["estado"]      = $producto->estado; 
                }
            }
            
            if(count($factura->factura_historial)>0){
                foreach ($factura->factura_historial as $key => $itemHistorial) {
                    $user = User::find($itemHistorial["user_id"]);

                    $itemHistorial["name"]      = $user->name; 
                    $itemHistorial["apellido"]  = $user->apellido; 
                }
            }

            
            
            if($factura){
                $response = $factura;
                $status = 200;

            }else{
                $response[] = "La factura no existe o fue eliminado.";
            }
            
        }else{
            $response[] = "El Valor de Id debe ser numerico.";
        }
        
        
        $data = [
            'data' => $response
        ];

        $archivo = PDF::loadView('pdf', $data);
        $pdf = PDF::loadView('pdf', $data)->output();

        Storage::disk('public')->put('factura.pdf', $pdf);

     
        return $archivo->download('factura_'.$response->id.'.pdf');
      
    }

    public function SendMail($id)
    {


        $response = [];
        $status = 400;
        $facturaEstado = 1; // Activo
        
        if(is_numeric($id)){
                    
            // if($request->input("estado") != null) $facturaEstado = $request->input("estado");
            // dd($productoEstado);
        
            $factura =  Factura::with('factura_detalle','cliente','factura_historial')->where([
                ['id', '=', $id],
                // ['estado', '=', $facturaEstado],
            ])->first();
            
            if(count($factura->factura_detalle)>0){
                foreach ($factura->factura_detalle as $key => $productoDetalle) {
                    $producto = Producto::find($productoDetalle["producto_id"]);
                    // dd($productoDetalle["id"]);
                    $productoDetalle["marca"]       = $producto->marca; 
                    $productoDetalle["modelo"]      = $producto->modelo; 
                    // $productoDetalle["stock"]       = $producto->stock; 
                    // $productoDetalle["precio"]      = $producto->precio; 
                    $productoDetalle["linea"]       = $producto->linea; 
                    $productoDetalle["descripcion"] = $producto->descripcion; 
                    // $productoDetalle["estado"]      = $producto->estado; 
                }
            }
            
            if(count($factura->factura_historial)>0){
                foreach ($factura->factura_historial as $key => $itemHistorial) {
                    $user = User::find($itemHistorial["user_id"]);

                    $itemHistorial["name"]      = $user->name; 
                    $itemHistorial["apellido"]  = $user->apellido; 
                }
            }

            
            
            if($factura){
                $response = $factura;
                $status = 200;

            }else{
                $response[] = "La factura no existe o fue eliminado.";
            }
            
        }else{
            $response[] = "El Valor de Id debe ser numerico.";
        }
        
        
        $data = [
            'data' => $response
        ];

        $archivo = PDF::loadView('pdf', $data);
        $pdf = PDF::loadView('pdf', $data)->output();

        Storage::disk('public')->put('factura.pdf', $pdf);

        $msg = $id;


        

       /*  Mail::to('rjov41@gmail.com')->send($correo); */

        Mail::to('rjov41@gmail.com')->queue(new PdfMail($msg));
        
        return 'Mail enviado';
    }
}
