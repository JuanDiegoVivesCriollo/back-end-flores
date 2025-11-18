<!DOCTYPE html>
<html>

<head>
  <title>Flores y Detalles Lima - Pago</title>
  <link rel='stylesheet' href='{{ asset("css/style.css") }}' />
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@4.5.2/dist/journal/bootstrap.min.css"
      integrity="sha384-QDSPDoVOoSWz2ypaRUidLmLYl4RyoBWI44iA5agn6jHegBxZkNqgm2eHb6yZ5bYs" crossorigin="anonymous" />
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

  <!-- Libreria JS de la pasarela, debe incluir la clave pública -->
  <script type="text/javascript"
    src="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js"
    kr-public-key="{{$publicKey}}"
    kr-post-url-success="{{ env('FRONTEND_URL', 'http://localhost:3000') }}/payment/return"
    kr-language="es-Es">
  </script>  <!-- Estilos de la pasarela de pagos -->
  <link rel="stylesheet" href="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.css">
  <script type="text/javascript" src="https://static.micuentaweb.pe/static/js/krypton-client/V4.0/ext/classic.js">
  </script>
</head>
<body>
  <nav class="navbar bg-primary" style="background-color: #e91e63!important;">
    <div class="container-fluid">
        <a href="{{ url('/') }}" class="navbar-brand mb-1">
          <img src="{{ asset('img/logojazmin2.webp') }}" width="80" alt="Flores y Detalles Lima">
        </a>
    </div>
  </nav>

<section class="container">
  <div class="row">
    <div class="col-md-3"></div>
    <div class="center-column col-md-6">
      <section class="payment-form">
        <div class="row">
          <div class="col-12 text-center mb-3">
            <h3>Pago Seguro</h3>
            <p>Pago con tarjeta de crédito/débito</p>
            <img src="https://github.com/izipay-pe/Imagenes/blob/main/logo_tarjetas_aceptadas/logo-tarjetas-aceptadas-351x42.png?raw=true"
                 alt="Tarjetas aceptadas" style="width: 200px;">
          </div>
        </div>
        <hr>

        @if(session('error'))
          <div class="alert alert-danger">
            {{ session('error') }}
          </div>
        @endif

        <div id="micuentawebstd_rest_wrapper">
          <!-- HTML para incrustar pasarela de pagos con POP-IN -->
          <div class="kr-embedded" kr-popin kr-form-token="{{$formToken}}">
            @csrf
            <!-- El botón de pago aparecerá automáticamente aquí -->
          </div>
          <!---->
        </div>
      </section>
    </div>
    <div class="col-md-3"></div>
  </div>
</section>

<script>
// Configuración adicional del pop-in si es necesario
document.addEventListener('DOMContentLoaded', function() {
    // Personalización adicional del formulario de pago
    console.log('Izipay payment form loaded with pop-in');
});
</script>

</body>
</html>
