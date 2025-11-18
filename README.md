# 🌸 Flores y Detalles Lima - Backend API

Backend API desarrollado en Laravel 11 para el sistema de comercio electrónico de Flores y Detalles Lima.

## 📋 Descripción

Este es el backend que proporciona la API RESTful para el sistema de florería, incluyendo:

- 🗃️ Gestión de productos, categorías y ocasiones
- 👥 Sistema de usuarios y autenticación
- 🛒 Procesamiento de órdenes de compra
- 💳 Integración con pasarela de pagos Izipay
- 📧 Sistema de notificaciones por email
- 🚚 Gestión de envíos y distritos

## 🔧 Requisitos del Sistema

### Software Necesario
- **PHP**: >= 8.2 (Descargar desde [php.net](https://www.php.net/downloads))
- **Composer**: Gestor de dependencias PHP ([getcomposer.org](https://getcomposer.org/download/))
- **MySQL**: >= 8.0 ([mysql.com](https://dev.mysql.com/downloads/mysql/))
- **Git**: Para clonar repositorios ([git-scm.com](https://git-scm.com/download))

### Extensiones PHP Requeridas
Las siguientes extensiones deben estar habilitadas en tu `php.ini`:
- PDO PHP Extension
- Mbstring PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- Ctype PHP Extension
- JSON PHP Extension
- BCMath PHP Extension
- Fileinfo PHP Extension
- OpenSSL PHP Extension
- Zip PHP Extension
- GD PHP Extension

## �️ Instalación Paso a Paso

### 1. Instalar PHP 8.2+

**Windows:**
```bash
# Descargar PHP desde https://windows.php.net/download/
# Extraer en C:\php
# Añadir C:\php a las variables de entorno PATH
# Copiar php.ini-development a php.ini
# Habilitar extensiones necesarias en php.ini
```

**Verificar instalación:**
```bash
php --version
# Debería mostrar: PHP 8.2.x o superior
```

### 2. Instalar Composer

**Windows:**
```bash
# Descargar desde https://getcomposer.org/Composer-Setup.exe
# Ejecutar el instalador
# Reiniciar terminal
```

**Verificar instalación:**
```bash
composer --version
# Debería mostrar: Composer version x.x.x
```

### 3. Instalar MySQL

**Windows:**
```bash
# Descargar MySQL Installer desde https://dev.mysql.com/downloads/installer/
# Instalar MySQL Server 8.0+
# Configurar usuario root y contraseña
# Anotar puerto (por defecto 3306)
```

**Crear base de datos:**
```sql
# Abrir MySQL Workbench o línea de comandos
mysql -u root -p
CREATE DATABASE flores_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'flores_user'@'localhost' IDENTIFIED BY 'tu_password_seguro';
GRANT ALL PRIVILEGES ON flores_db.* TO 'flores_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Clonar y Configurar el Proyecto

```bash
# 1. Clonar repositorio
git clone https://github.com/tuusuario/backend-floresdjazmin.git
cd backend-floresdjazmin

# 2. Instalar dependencias PHP
composer install
# Si aparece error de memoria: composer install --no-scripts

# 3. Crear archivo de configuración
copy .env.example .env
# En Linux/Mac: cp .env.example .env

# 4. Generar clave de aplicación
php artisan key:generate
```

### 5. Configurar Variables de Entorno

Editar el archivo `.env` con tus datos específicos:

```env
# =======================================
# CONFIGURACIÓN BÁSICA DE APLICACIÓN
# =======================================
APP_NAME="Flores y Detalles Lima"
APP_ENV=local
APP_KEY=base64:tu_clave_generada_automaticamente
APP_DEBUG=true
APP_URL=http://localhost:8000

# =======================================
# CONFIGURACIÓN DE BASE DE DATOS
# =======================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flores_db
DB_USERNAME=flores_user
DB_PASSWORD=tu_password_seguro

# =======================================
# CONFIGURACIÓN DE CORREO ELECTRÓNICO
# =======================================
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_app_password_gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_email@gmail.com
MAIL_FROM_NAME="Flores y Detalles Lima"

# =======================================
# CONFIGURACIÓN DE IZIPAY (PAGOS)
# =======================================
IZIPAY_PUBLIC_KEY=tu_clave_publica_izipay
IZIPAY_PRIVATE_KEY=tu_clave_privada_izipay
IZIPAY_API_URL=https://api.izipay.pe
IZIPAY_MODE=TEST
# Cambiar a PROD cuando vayas a producción

# =======================================
# CONFIGURACIÓN FRONTEND Y CORS
# =======================================
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS="http://localhost:3000,http://127.0.0.1:3000"
SANCTUM_STATEFUL_DOMAINS="localhost:3000,127.0.0.1:3000"

# =======================================
# CONFIGURACIÓN DE SESIONES Y CACHE
# =======================================
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# =======================================
# CONFIGURACIÓN DE ALMACENAMIENTO
# =======================================
FILESYSTEM_DISK=local
```

### 6. Configurar Base de Datos y Ejecutar Migraciones

```bash
# 1. Verificar conexión a la base de datos
php artisan tinker
# Dentro de tinker:
DB::connection()->getPdo();
# Si no hay errores, la conexión es exitosa
exit

# 2. Ejecutar migraciones (crear tablas)
php artisan migrate
# Si pregunta si quieres crear la BD, responde 'yes'

# 3. Ejecutar seeders (datos de ejemplo)
php artisan db:seed
# Esto creará usuarios, productos, categorías de ejemplo

# 4. Opcional: Ejecutar todo junto
php artisan migrate:fresh --seed
# CUIDADO: Esto borra toda la data existente
```

### 7. Configurar Almacenamiento de Archivos

```bash
# Crear enlace simbólico para archivos públicos
php artisan storage:link

# Crear directorios necesarios para imágenes
# Windows:
mkdir storage\app\public\flowers
mkdir storage\app\public\categories
mkdir storage\app\public\occasions

# Linux/Mac:
mkdir -p storage/app/public/flowers
mkdir -p storage/app/public/categories
mkdir -p storage/app/public/occasions

# Verificar permisos (Linux/Mac)
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

### 8. Iniciar el Servidor de Desarrollo

```bash
# Iniciar servidor Laravel en puerto 8000
php artisan serve

# O especificar host y puerto
php artisan serve --host=127.0.0.1 --port=8000

# El backend estará disponible en: http://localhost:8000
```

## 🧪 Verificar la Instalación

### 1. Verificar API
Abre tu navegador y visita:
- `http://localhost:8000/api/flowers` - Debería mostrar lista de flores
- `http://localhost:8000/api/categories` - Debería mostrar categorías
- `http://localhost:8000/api/districts` - Debería mostrar distritos

### 2. Verificar Base de Datos
```bash
# Conectar a MySQL y verificar tablas
mysql -u flores_user -p flores_db

# Mostrar tablas creadas
SHOW TABLES;

# Debería mostrar: flowers, categories, districts, orders, etc.

# Verificar datos de ejemplo
SELECT * FROM categories LIMIT 5;
SELECT * FROM flowers LIMIT 5;
```

### 3. Verificar Logs
```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# En Windows usar:
type storage\logs\laravel.log
```

## 📡 Endpoints Principales

### Productos
- `GET /api/flowers` - Listar flores
- `GET /api/flowers/{id}` - Detalle de flor
- `GET /api/categories` - Listar categorías
- `GET /api/occasions` - Listar ocasiones

### Órdenes
- `POST /api/orders` - Crear orden
- `GET /api/orders/{id}` - Detalle de orden
- `PUT /api/orders/{id}` - Actualizar orden

### Envíos
- `GET /api/districts` - Listar distritos de entrega
- `GET /api/shipping-cost/{district}` - Costo de envío

### Pagos
- `POST /api/payments/izipay` - Procesar pago con Izipay
- `POST /api/payments/callback` - Webhook de Izipay

## 🗄️ Estructura de Base de Datos

### Tablas Principales

- **flowers**: Productos/flores
- **categories**: Categorías de productos
- **occasions**: Ocasiones especiales
- **districts**: Distritos de entrega
- **orders**: Órdenes de compra
- **order_items**: Items de cada orden
- **users**: Usuarios del sistema

## ⚙️ Configuración de Variables de Entorno

```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flores_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

# Izipay
IZIPAY_PUBLIC_KEY=tu_clave_publica
IZIPAY_PRIVATE_KEY=tu_clave_privada
IZIPAY_API_URL=https://api.izipay.pe

# URLs Frontend
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_password
```

## 🛡️ Seguridad

- **CORS**: Configurado para frontend específico
- **Sanitización**: Todos los inputs son validados
- **Rate Limiting**: Límites de peticiones por IP
- **HTTPS**: Requerido en producción

## 📚 Documentación API

La documentación completa de la API está disponible en:
- **Desarrollo**: `http://localhost:8000/api/documentation`
- **Producción**: `https://tu-dominio.com/api/documentation`

## 🚀 Deploy en Producción

```bash
# 1. Optimizar para producción
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan view:cache
php artisan route:cache

# 2. Configurar permisos
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 3. Configurar .htaccess para hosting compartido
```

## 🔧 Comandos Artisan Útiles

```bash
# Limpiar cachés
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Regenerar archivos optimizados
php artisan config:cache
php artisan view:cache
php artisan route:cache

# Base de datos
php artisan migrate:refresh --seed
php artisan db:seed

# Storage
php artisan storage:link
```

## 📊 Monitoreo y Logs

- **Logs**: `storage/logs/laravel.log`
- **Queries**: Habilitado en desarrollo
- **Errores**: Reportados via email en producción

## 🤝 Contribución

1. Fork el proyecto
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -m 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## 📄 Licencia

Proyecto privado. Todos los derechos reservados.

---

**Versión**: 1.0.0  
**Laravel**: 11.x  
**PHP**: 8.2+
