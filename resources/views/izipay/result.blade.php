<!DOCTYPE html>
<html>

<head>
  <title>Flores y Detalles Lima - Resultado de Pago</title>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@4.5.2/dist/journal/bootstrap.min.css"
  integrity="sha384-QDSPDoVOoSWz2ypaRUidLmLYl4RyoBWI44iA5agn6jHegBxZkNqgm2eHb6yZ5bYs" crossorigin="anonymous" />
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <link rel='stylesheet' href='{{ asset("css/style.css") }}' />
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
      <section class="result-form">
        <div class="text-center mb-4">
          @if($orderStatus === 'PAID')
            <div class="alert alert-success">
              <h2><i class="fas fa-check-circle"></i> ¡Pago Exitoso!</h2>
              <p>Tu pago se ha procesado correctamente.</p>
            </div>
          @elseif($orderStatus === 'CANCELLED' || $orderStatus === 'ABANDONED')
            <div class="alert alert-warning">
              <h2><i class="fas fa-times-circle"></i> Pago Cancelado</h2>
              <p>El pago ha sido cancelado.</p>
            </div>
          @elseif($orderStatus === 'REFUSED')
            <div class="alert alert-danger">
              <h2><i class="fas fa-exclamation-circle"></i> Pago Rechazado</h2>
              <p>Tu pago ha sido rechazado. Por favor intenta nuevamente.</p>
            </div>
          @else
            <div class="alert alert-info">
              <h2><i class="fas fa-info-circle"></i> Estado: {{ $orderStatus }}</h2>
            </div>
          @endif
        </div>

        <h2>Resultado de pago:</h2>
        <hr>

        @if(isset($answer) && !isset($answer['error']))
          <div class="payment-details">
            <p><strong>Estado:</strong>
              <span class="badge badge-{{ $orderStatus === 'PAID' ? 'success' : ($orderStatus === 'REFUSED' ? 'danger' : 'warning') }}">
                {{ $answer['orderStatus'] }}
              </span>
            </p>

            @if(isset($answer['transactions']) && count($answer['transactions']) > 0)
              <p><strong>Monto:</strong>
                {{ $answer["transactions"][0]["currency"] }}.
                {{ number_format($answer['orderDetails']["orderTotalAmount"] / 100, 2) }}
              </p>
            @endif

            @if(isset($answer['orderDetails']['orderId']))
              <p><strong>Order ID:</strong> {{ $answer['orderDetails']["orderId"] }}</p>
            @endif

            @if(isset($answer['transactions'][0]['transactionDetails']['liabilityShift']))
              <p><strong>3D Secure:</strong>
                {{ $answer['transactions'][0]['transactionDetails']['liabilityShift'] ? 'Sí' : 'No' }}
              </p>
            @endif
          </div>
        @else
          <div class="alert alert-danger">
            <p><strong>Error:</strong> {{ $answer['error'] ?? 'Error desconocido' }}</p>
          </div>
        @endif

        <hr>

        <div class="text-center">
          @if($orderStatus === 'PAID')
            <a href="{{ url('/mi-cuenta/pedidos') }}" class="btn btn-primary">
              Ver mis pedidos
            </a>
          @else
            <a href="{{ url('/carrito') }}" class="btn btn-primary">
              Intentar nuevamente
            </a>
          @endif
          <a href="{{ url('/') }}" class="btn btn-secondary ml-2">
            Ir al inicio
          </a>
        </div>

        <hr>

        <!-- Detalles técnicos (solo para desarrollo/debug) -->
        @if(config('app.debug'))
          <details>
            <summary>
              <h4>Respuesta completa del servidor:</h4>
            </summary>
            <pre>{{ json_encode($_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
          </details>
          <hr>
          <details>
            <summary>
              <h4>kr-answer:</h4>
            </summary>
            <pre>{{ json_encode($answer ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
          </details>
        @endif
      </section>
    </div>
    <div class="col-md-3"></div>
  </div>
</section>

<!-- Font Awesome para iconos -->
<script src="https://kit.fontawesome.com/a076d05399.js"></script>

</body>
</html>
