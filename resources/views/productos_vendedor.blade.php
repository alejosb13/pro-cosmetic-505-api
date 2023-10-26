<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>pdf</title>
</head>
<style>
    body {
        position: relative;
    }

    .content-titulo {
        display: flex;
        flex-direction: column;
        text-align: center;
        margin-left: -40px;
    }

    h4 {
        line-height: 1;
    }

    .border {
        width: 98%;
        display: block;
        height: 88%;
        border: 2px solid #000;
        border-top-left-radius: 30px;
        border-top-right-radius: 30px;
        padding: 5px 10px;

    }

    .seccion_supeior {
        display: flex;
        justify-content: space-between;
        width: 100%;
        margin-top: 15px;
        border-bottom: 2px solid #000;
        padding-bottom: 40px
    }

    .left {
        display: inline-block;
    }

    .left span {
        display: block;

    }

    .right {
        display: inline-block;
        float: right;
    }

    .right span {
        display: block;
        width: 220px;
    }

    .detail {
        width: 100%;
        margin: 5px;
    }

    .detail table th {
        text-align: left;
        border-bottom: 1px solid;
        font-size: 12px;
        
    }

    .detail table td {
        font-size: 11.5px;
    }

    .footer {
        display: flex;
        justify-content: space-between;
        margin-top: 75px;
        width: 100%
    }

    .firmas {
        width: 150px;
        display: inline-block;
        border-top: 1px solid #000;
        margin: 0 40px;
        text-align: center;
    }

    .firmas span {
        display: block;
        font-size: 15px
    }

    .logo {
        /* position: absolute; */
        float: left;
        display: block;
        width: 80px;
        height: 56px;
        z-index: 9999;
    }

    .total {
        display: block;
        width: 95%;
        border: 2px solid #000;
        border-bottom-left-radius: 30px;
        border-bottom-right-radius: 30px;
        padding: 10px
    }

    .total .monto {
        float: right;
    }

    .item {
        display: block;
        width: 95%;
        border: 2px solid #000;
        padding: 10px
    }

    .item .monto {
        float: right;
    }

    .direccion {
        width: 200px;
    }

    .page-break {
        page-break-after: always;
    }
</style>
{{-- <div class="page-break"></div> --}}

<body>

    @foreach($data as $key => $page)
    <h6 style="float: right">Pagina {{ $key + 1 }} de {{ count($data) }} <br>Total {{ $cantidad }} </h6>
    <img class="logo" src="lib/img/logo_png.png" style="margin-top: 15px" alt="">
    <h5 style="text-align: center;">PRO COSMETIC 505 <br> Delicias del Volga, 1c. Abajo, 1&#189;C. al Sur, casa #403 <br> Teléfonos: 8765-5719 / 8422-0032 <br> Usuario: {{$usuario}} </h5>
    </div>
    <div class="border">


        <div class="detail">
            <table style="width: 100%">
                <thead>
                    <tr>
                        <th>#FACTURA</th>
                        <th>#PRODUCTO</th>
                        <th>MARCA</th>
                        <th>MODELO</th>
                        <th>LINEA</th>
                        <th>DESCRIPCIÓN</th>
                        <th>CANTIDAD</th>
                        <th>CLINTE</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach($data[$key] as $factura)
                    <tr>
                        <td>{{ $factura->id }}</td>
                        <td>{{ $factura->producto->id }}</td>
                        <td>{{ $factura->producto->marca }}</td>
                        <td>{{ $factura->producto->modelo }}</td>
                        <td>{{ $factura->producto->linea }}</td>
                        <td>{{ $factura->producto->descripcion }}</td>
                        <td>{{ $factura->cantidad_total }}</td>
                        <td>{{ $factura->cliente->nombreCompleto }}</td>
                    </tr>
                    @endforeach


                </tbody>
            </table>
        </div>
    </div>

    {{-- <div class="page-break"></div> --}}
    @endforeach




</body>

</html>