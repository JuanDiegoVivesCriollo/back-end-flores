<!DOCTYPE html>
<html>
<head>
    <title>Flores y Detalles Lima - Pago</title>
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
                <section class="payment-form">
                    <h2>Prueba de Pago - Flores y Detalles Lima</h2>
                    <hr>

                    <form action="{{ route('izipay.checkout') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label>Monto (PEN):</label>
                            <input type="number" name="amount" step="0.01" min="1" value="100.00" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Moneda:</label>
                            <select name="currency" class="form-control">
                                <option value="PEN">PEN - Sol Peruano</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Order ID:</label>
                            <input type="text" name="orderId" value="ORDER_{{ time() }}" class="form-control" required>
                        </div>

                        <h4>Datos del Cliente</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nombres:</label>
                                    <input type="text" name="firstName" value="Juan" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Apellidos:</label>
                                    <input type="text" name="lastName" value="Pérez" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" name="email" value="juan.perez@ejemplo.com" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Teléfono:</label>
                                    <input type="text" name="phoneNumber" value="999999999" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Tipo de Documento:</label>
                                    <select name="identityType" class="form-control">
                                        <option value="DNI">DNI</option>
                                        <option value="CE">Carnet de Extranjería</option>
                                        <option value="PASSPORT">Pasaporte</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Número de Documento:</label>
                            <input type="text" name="identityCode" value="12345678" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Dirección:</label>
                            <input type="text" name="address" value="Av. Ejemplo 123" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Ciudad:</label>
                                    <input type="text" name="city" value="Lima" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Código Postal:</label>
                                    <input type="text" name="zipCode" value="15000" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Estado:</label>
                                    <input type="text" name="state" value="Lima" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>País:</label>
                                    <select name="country" class="form-control">
                                        <option value="PE">Perú</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary btn-block">Proceder al Pago</button>
                    </form>
                </section>
            </div>
            <div class="col-md-3"></div>
        </div>
    </section>
</body>
</html>
