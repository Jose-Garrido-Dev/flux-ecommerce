# Guía de instalación y configuración — Flux E-commerce

Esta guía explica qué hay que instalar, qué hay que configurar y cómo dejar funcionando una tienda Flux, primero en tu computador (desarrollo) y luego en un servidor (producción). Cada instalación es **una tienda independiente**: su propia base de datos, sus propias credenciales y su propio dominio.

> Estado actual del proyecto: **Fase 1 (fundaciones)**. Las pasarelas de pago y los couriers aún no están implementados; la sección 8 explica qué credenciales habrá que conseguir para cuando lleguen. El avance real de cada módulo está en [module-status.md](module-status.md).

## Índice

1. [Qué piezas tiene el sistema](#1-qué-piezas-tiene-el-sistema)
2. [Requisitos](#2-requisitos)
3. [Instalación de desarrollo con Docker (recomendada)](#3-instalación-de-desarrollo-con-docker-recomendada)
4. [Instalación de desarrollo sin Docker](#4-instalación-de-desarrollo-sin-docker)
5. [Variables de entorno explicadas](#5-variables-de-entorno-explicadas)
6. [Instalación en producción](#6-instalación-en-producción)
7. [Operación diaria: colas, tareas programadas, respaldos y actualizaciones](#7-operación-diaria)
8. [Servicios externos: qué contratar y dónde configurarlo](#8-servicios-externos)
9. [Primer ingreso al panel y configuración inicial de la tienda](#9-primer-ingreso-al-panel)
10. [Pruebas y calidad](#10-pruebas-y-calidad)
11. [Problemas frecuentes](#11-problemas-frecuentes)

---

## 1. Qué piezas tiene el sistema

| Pieza | Tecnología | Para qué sirve | Carpeta |
| --- | --- | --- | --- |
| Backend | Laravel 13 (PHP 8.3+) | Reglas de negocio, API, pagos, stock, pedidos, permisos | `backend/` |
| Panel administrativo | Filament 5 (dentro de Laravel) | Administración de la tienda en `/admin` | `backend/app/Filament/` |
| Storefront | Next.js 15 (React 19, Tailwind 4) | La tienda pública que ven los compradores | `storefront/` |
| Base de datos | PostgreSQL 16 | Toda la información de la tienda | servicio `postgres` |
| Colas, caché y sesiones | La misma base PostgreSQL (tablas `jobs`, `cache`, `sessions`) | Correos, webhooks, tareas en segundo plano | — |
| Búsqueda | La misma base PostgreSQL | Buscador del catálogo | — |
| Archivos | Disco del servidor (`backend/storage`) | Imágenes, etiquetas, comprobantes | volumen `storage` |
| Correo de desarrollo | Mailpit | Ver los correos que envía el sistema sin enviarlos de verdad | servicio `mailpit` |
| Worker | `php artisan queue:work` | Procesa las colas | servicio `worker` |
| Scheduler | `php artisan schedule:work` | Tareas programadas (reservas vencidas, informes, respaldos) | servicio `scheduler` |

**Todo corre en tu propio servidor.** La configuración por defecto no necesita contratar ningún servicio externo: ni Redis, ni S3, ni buscadores pagados. Redis, Meilisearch y S3 existen solo como opciones para tiendas muy grandes (sección 8.2). Los únicos servicios externos son los que el negocio necesita por naturaleza: pasarela de pago, courier y correo (sección 8).

La arquitectura completa, el esquema de base de datos y los flujos están en el documento de diseño técnico (ver [docs/arquitectura.md](arquitectura.md)).

## 2. Requisitos

### Para desarrollo con Docker (opción recomendada)

| Programa | Versión mínima | Cómo instalarlo |
| --- | --- | --- |
| Git | 2.40 | <https://git-scm.com/downloads> |
| Docker Desktop (Windows/macOS) o Docker Engine (Linux) | Docker 24 con Compose v2 | <https://docs.docker.com/get-docker/> |
| Editor | — | VS Code recomendado, con extensiones PHP Intelephense, ESLint y Tailwind CSS |

En **Windows** usa Docker Desktop con WSL 2 y clona el repositorio **dentro de WSL** (por ejemplo en `~/proyectos`), no en `C:\`; es mucho más rápido.

Recursos sugeridos para Docker: 4 CPU, 6 GB de RAM, 20 GB de disco.

### Para desarrollo sin Docker

| Programa | Versión | Notas |
| --- | --- | --- |
| PHP | 8.3 o superior | Extensiones: `pdo_pgsql`, `pgsql`, `intl`, `zip`, `gd`, `bcmath`, `mbstring`, `xml`, `curl`, `pcntl` (Linux/macOS); `redis` solo si activas Redis |
| Composer | 2.7 o superior | <https://getcomposer.org/download/> |
| Node.js | 22 LTS | <https://nodejs.org/> (incluye npm) |
| PostgreSQL | 16 | <https://www.postgresql.org/download/> |

### Para producción

| Recurso | Mínimo | Recomendado |
| --- | --- | --- |
| Servidor (VPS) | 2 vCPU, 4 GB RAM, 40 GB SSD | 4 vCPU, 8 GB RAM, 80 GB SSD |
| Sistema operativo | Ubuntu Server 24.04 LTS | igual |
| Dominio | uno, con acceso a su DNS | `mitienda.cl` para la tienda y `api.mitienda.cl` para backend y panel |
| Certificado HTTPS | Let's Encrypt, gratuito (lo emite Caddy, o Webuzo si el servidor lo usa) | igual |
| Correo transaccional | Proveedor SMTP o API (ver sección 8) | igual |

## 3. Instalación de desarrollo con Docker (recomendada)

### 3.1 Clonar el repositorio

```bash
git clone https://github.com/Jose-Garrido-Dev/flux-ecommerce.git
cd flux-ecommerce
```

### 3.2 Instalación automática

```bash
./infra/scripts/install-dev.sh
```

El script hace, en orden:

1. Verifica que Docker y Compose estén instalados.
2. Crea `backend/.env` a partir de `backend/.env.docker.example`.
3. Construye la imagen PHP y levanta PostgreSQL y Mailpit.
4. Instala las dependencias PHP con Composer.
5. Genera la clave de la aplicación (`APP_KEY`).
6. Ejecuta las migraciones y carga los datos base: las 16 regiones de Chile, los roles iniciales y el tablero de módulos.
7. Levanta todo el resto: backend, worker, scheduler y storefront.

### 3.3 Crear tu usuario administrador

```bash
docker compose exec app php artisan flux:admin
```

Te pedirá nombre, correo y contraseña (mínimo 12 caracteres) y le asignará el rol **Superadministrador técnico**.

### 3.4 Direcciones útiles en desarrollo

| Qué | Dirección |
| --- | --- |
| Tienda (storefront) | <http://localhost:3000> |
| Panel administrativo | <http://localhost:8000/admin> |
| Estado del backend | <http://localhost:8000/api/health> y <http://localhost:8000/api/health/ready> |
| Correos enviados (Mailpit) | <http://localhost:8025> |
| PostgreSQL desde tu equipo | `localhost:5432`, base `flux`, usuario `flux`, clave `flux` |

### 3.5 Comandos del día a día

```bash
docker compose up -d                 # levantar todo
docker compose down                  # detener todo (los datos se conservan)
docker compose logs -f app worker    # ver registros
docker compose exec app php artisan migrate      # aplicar migraciones nuevas
docker compose exec app php artisan test         # pruebas del backend
docker compose exec app composer require paquete # agregar dependencia PHP
docker compose exec storefront npm run lint      # revisar el storefront
docker compose down -v               # BORRA la base de datos local y empieza de cero
```

## 4. Instalación de desarrollo sin Docker

Úsala solo si no puedes usar Docker.

### 4.1 Base de datos

```sql
-- en psql como superusuario
CREATE USER flux WITH PASSWORD 'flux';
CREATE DATABASE flux OWNER flux;
```

### 4.2 Backend

```bash
cd backend
cp .env.example .env
# edita .env: DB_HOST=127.0.0.1, DB_DATABASE=flux, DB_USERNAME=flux, DB_PASSWORD=flux
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan flux:admin
```

Luego, en tres terminales distintas:

```bash
php artisan serve --port=8000      # API y panel
php artisan queue:work              # colas
php artisan schedule:work           # tareas programadas
```

### 4.3 Storefront

```bash
cd storefront
cp .env.example .env.local
npm install
npm run dev
```

## 5. Variables de entorno explicadas

Nunca subas archivos `.env` al repositorio: contienen claves. El `.gitignore` ya los excluye.

### 5.1 `backend/.env`

| Variable | Ejemplo | Qué hace |
| --- | --- | --- |
| `APP_NAME` | `"Mi Tienda"` | Nombre que aparece en el panel y en los correos |
| `APP_ENV` | `local` / `production` | Entorno. En producción siempre `production` |
| `APP_KEY` | generado | Clave de cifrado. Se genera con `php artisan key:generate`. **Si se pierde, no se pueden leer las credenciales cifradas guardadas desde el panel.** Respáldala fuera del servidor |
| `APP_DEBUG` | `true` / `false` | Muestra errores detallados. En producción **siempre `false`** |
| `APP_URL` | `https://api.mitienda.cl` | URL pública del backend y del panel |
| `APP_TIMEZONE` | `America/Santiago` | Zona horaria de la tienda |
| `APP_LOCALE` | `es` | Idioma |
| `FLUX_STOREFRONT_URL` | `https://www.mitienda.cl` | URL de la tienda; se usa para enlaces en correos y CORS |
| `FLUX_REVALIDATE_SECRET` | texto largo aleatorio | Secreto compartido con Next.js para refrescar páginas cuando cambian productos |
| `DB_CONNECTION` | `pgsql` | Motor de base de datos |
| `DB_HOST` / `DB_PORT` | `postgres` / `5432` | En Docker el host es el nombre del servicio |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | | Credenciales de la base |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` | `database` | Caché, colas y sesiones en la base de datos (sin servicios extra). `redis` solo si activas Redis (sección 8.2) |
| `SESSION_DOMAIN` | `.mitienda.cl` | Permite compartir la sesión entre `www` y `api` |
| `SANCTUM_STATEFUL_DOMAINS` | `www.mitienda.cl,mitienda.cl` | Dominios del storefront que pueden iniciar sesión de clientes |
| `MAIL_MAILER` | `smtp` | Forma de envío de correo |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` | | Datos del proveedor SMTP (en desarrollo: `mailpit`, `1025`) |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | `ventas@mitienda.cl` | Remitente de los correos |
| `SCOUT_DRIVER` | `database` | Motor de búsqueda. `meilisearch` solo si lo activas (sección 8.2) |
| `FILESYSTEM_DISK` | `local` | Archivos en el disco del servidor. `s3` es opcional |
| `REDIS_*`, `MEILISEARCH_*`, `AWS_*` | vacías | Solo si activas esos servicios opcionales |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `warning` | Registros. En producción `warning` o `error` |

Las credenciales de pasarelas de pago, couriers e IA **no van en el `.env`**: se ingresan desde el panel (Configuración → Pagos / Envíos / Inteligencia artificial) y se guardan cifradas con `APP_KEY`.

### 5.2 `storefront/.env.local` (desarrollo) o `.env` raíz (producción)

| Variable | Ejemplo | Qué hace |
| --- | --- | --- |
| `FLUX_API_INTERNAL_URL` | `http://localhost:8000` | URL del backend que usa el servidor de Next.js. En Docker: `http://nginx` (dev) o `http://web` (prod) |
| `NEXT_PUBLIC_SITE_URL` | `https://www.mitienda.cl` | URL pública de la tienda (SEO, enlaces) |
| `REVALIDATE_SECRET` | igual a `FLUX_REVALIDATE_SECRET` | Valida que las peticiones de revalidación vengan del backend |

Solo las variables que empiezan con `NEXT_PUBLIC_` llegan al navegador. **Nunca** pongas claves con ese prefijo.

### 5.3 `.env` de la raíz (solo producción)

Lo usa `compose.prod.yml`. Ver comentarios en [`.env.example`](../.env.example).

## 6. Instalación en producción

Hay dos caminos según tu servidor:

- **Servidor limpio** (sin panel de hosting): secciones 6.1 a 6.6, con Caddy como proxy y HTTPS automático.
- **Servidor con Webuzo** (u otro panel que ya usa los puertos 80 y 443): secciones 6.1 a 6.4 **sin instalar Caddy**, y luego la sección [6.7](#67-servidor-con-webuzo) para publicar el dominio desde Webuzo.

### 6.1 Preparar el DNS

En el proveedor de tu dominio crea registros **A** apuntando a la IP del servidor:

| Nombre | Tipo | Valor |
| --- | --- | --- |
| `mitienda.cl` | A | IP del servidor |
| `www.mitienda.cl` | A | IP del servidor |
| `api.mitienda.cl` | A | IP del servidor |

### 6.2 Preparar el servidor

```bash
# como root, la primera vez
adduser flux && usermod -aG sudo flux
apt update && apt upgrade -y
apt install -y git ufw fail2ban
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw enable

# Docker (método oficial)
curl -fsSL https://get.docker.com | sh
usermod -aG docker flux

# Caddy: SOLO en un servidor sin Webuzo ni otro panel (proxy con HTTPS automático)
apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
apt update && apt install -y caddy
```

Desactiva el ingreso por contraseña en SSH y usa llaves (`PasswordAuthentication no` en `/etc/ssh/sshd_config`).

### 6.3 Descargar el código y configurar

```bash
su - flux
git clone https://github.com/Jose-Garrido-Dev/flux-ecommerce.git tienda
cd tienda
git checkout v0.1.0                         # usar siempre una versión etiquetada
cp .env.example .env                        # variables de compose (claves largas)
cp backend/.env.production.example backend/.env
nano .env backend/.env                      # completar todo lo marcado CAMBIAR
```

Para generar claves largas: `openssl rand -base64 48`.

`APP_KEY` se genera después del primer build:

```bash
docker compose -f compose.prod.yml --env-file .env build
docker compose -f compose.prod.yml --env-file .env run --rm app php artisan key:generate --show
# copia el valor mostrado en APP_KEY de backend/.env
```

### 6.4 Primer despliegue

```bash
FLUX_VERSION=0.1.0 ./infra/scripts/deploy.sh
docker compose -f compose.prod.yml --env-file .env exec app php artisan db:seed --force
docker compose -f compose.prod.yml --env-file .env exec app php artisan flux:admin
```

`db:seed` en producción carga **solo datos reales** (regiones, roles, módulos). Nunca carga productos ni pedidos de ejemplo.

### 6.5 Configurar Caddy (HTTPS) — solo servidor sin panel

`/etc/caddy/Caddyfile`:

```caddy
mitienda.cl {
    redir https://www.mitienda.cl{uri} permanent
}

www.mitienda.cl {
    encode zstd gzip
    reverse_proxy 127.0.0.1:3000
}

api.mitienda.cl {
    encode zstd gzip
    reverse_proxy 127.0.0.1:8000
}
```

```bash
sudo systemctl reload caddy
```

Caddy obtiene y renueva los certificados automáticamente.

### 6.6 Verificar

- <https://api.mitienda.cl/api/health/ready> debe responder `"status":"ok"`.
- <https://api.mitienda.cl/admin> muestra el ingreso al panel.
- <https://www.mitienda.cl> muestra la tienda.

### 6.7 Servidor con Webuzo

Si la VPS ya tiene Webuzo con otros sitios y MySQL, Flux convive sin problemas:

- **PostgreSQL** corre dentro de su contenedor Docker, no se instala en el sistema y **no abre ningún puerto** hacia afuera. No toca el MySQL de Webuzo ni tus otros proyectos.
- **La tienda y el backend** escuchan solo en `127.0.0.1:3000` y `127.0.0.1:8000` (no accesibles desde internet).
- **Webuzo** sigue siendo dueño de los puertos 80 y 443 y del certificado HTTPS; se configura para reenviar (proxy) el dominio a esos puertos internos.

> Esta sección se escribió a partir de la documentación oficial de Webuzo y **todavía no se ha probado en un servidor real**. Los nombres de menú pueden variar según la versión de Webuzo. Si algo no coincide, avísalo para corregir la guía.

**Paso 1: recursos.** Revisa la memoria libre con `free -h`. Flux usa alrededor de 1 a 1,5 GB (PostgreSQL, PHP, worker, scheduler y Next.js). Con menos de 2 GB libres conviene ampliar la VPS.

**Paso 2: puertos libres.** Comprueba que nada use los puertos 3000 y 8000:

```bash
sudo ss -ltnp | grep -E ':(3000|8000)\b' || echo "libres"
```

Si están ocupados, cambia `STOREFRONT_PORT` y `BACKEND_PORT` en el `.env` de la raíz.

**Paso 3: instalar Docker y Flux.** Sigue las secciones 6.2 (sin la parte de Caddy ni `ufw`, porque Webuzo ya administra el firewall), 6.3 y 6.4.

**Paso 4: agregar los dominios en Webuzo.** En el panel de Webuzo agrega el dominio de la tienda (por ejemplo `mitienda.cl` con `www`) y el subdominio del backend (`api.mitienda.cl`). Los registros DNS de la sección 6.1 deben apuntar a la IP de la VPS.

**Paso 5: certificado HTTPS.** En el panel de usuario de Webuzo: **SSL → Automatic SSL**, y pulsa **install** en la fila de cada dominio. Webuzo emite y renueva el certificado.

**Paso 6: reenviar el dominio a Flux (proxy).** Webuzo permite agregar configuración propia por dominio. Primero averigua qué servidor web usa tu Webuzo (Apache, Nginx u OpenLiteSpeed); se ve en el panel de administración, en la configuración del servidor web. Luego crea un archivo por dominio (las carpetas se crean a mano si no existen):

*Si usa Apache* (`/var/webuzo-data/apache2/custom/domains/www.mitienda.cl.conf`):

```apache
ProxyPreserveHost On
ProxyPass        / http://127.0.0.1:3000/
ProxyPassReverse / http://127.0.0.1:3000/
RequestHeader set X-Forwarded-Proto "https"
```

y `/var/webuzo-data/apache2/custom/domains/api.mitienda.cl.conf` igual, pero con el puerto `8000`.

*Si usa Nginx* (`/var/webuzo-data/nginx/custom/domains/www.mitienda.cl.conf`):

```nginx
location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto https;
}
```

y el de `api.mitienda.cl` con el puerto `8000` y además `client_max_body_size 25M;` para permitir subir imágenes.

Después reinicia el servidor web desde el panel de Webuzo. En Apache, el módulo `mod_proxy` debe estar activo (si no lo está, el soporte de tu VPS o Webuzo lo puede habilitar).

**Paso 7: verificar.** Las mismas comprobaciones de la sección 6.6.

**Paso 8: respaldos.** Los respaldos de Webuzo cubren tus otros sitios, pero **no** la base PostgreSQL de Flux, que vive en Docker. Programa `infra/scripts/backup.sh` con cron (sección 7.2).

## 7. Operación diaria

### 7.1 Colas y tareas programadas

En producción ya corren como servicios (`worker` y `scheduler`) y se reinician solos. Para revisarlos:

```bash
docker compose -f compose.prod.yml --env-file .env ps
docker compose -f compose.prod.yml --env-file .env logs -f worker scheduler
```

El panel mostrará una alerta si el scheduler deja de reportar actividad.

### 7.2 Respaldos

Programa un respaldo diario a las 03:00 con `crontab -e` (usuario `flux`):

```cron
0 3 * * * cd /home/flux/tienda && ./infra/scripts/backup.sh >> backups/backup.log 2>&1
```

El script guarda base de datos y archivos en `backups/AAAAMMDD-HHMMSS/`, verifica que no estén vacíos y conserva los últimos 14. **Copia los respaldos fuera del servidor** (por ejemplo con `rclone` a un bucket) y prueba una restauración al menos una vez al mes:

```bash
./infra/scripts/restore.sh backups/20261119-030000
```

### 7.3 Actualizar a una nueva versión

```bash
git fetch --tags
git checkout v0.2.0
FLUX_VERSION=0.2.0 ./infra/scripts/deploy.sh
```

`deploy.sh` respalda primero, construye, migra, limpia cachés, reinicia y verifica salud. Si algo falla, vuelve a la versión anterior con `git checkout` y restaura el respaldo.

## 8. Servicios externos

Ninguna integración se considera operativa hasta que se prueba con credenciales reales de prueba (sandbox). El panel mostrará cada una como **No verificada** hasta entonces.

| Servicio | Para qué | Qué conseguir | Dónde se configurará | Estado |
| --- | --- | --- | --- | --- |
| Transbank Webpay Plus | Pago con tarjetas | Código de comercio y API Key secret (Transbank entrega credenciales de integración públicas para pruebas y las de producción tras el proceso de afiliación) | Panel → Configuración → Pagos | Pendiente (Fase 6) |
| Mercado Pago | Pagos | Access token y public key de una cuenta de vendedor; usuarios de prueba | Panel → Configuración → Pagos | Pendiente (Fase 6) |
| Flow | Pagos | API Key y Secret Key (sandbox en sandbox.flow.cl) | Panel → Configuración → Pagos | Pendiente (Fase 6) |
| Khipu | Transferencia simplificada | ID de cobrador y llave secreta | Panel → Configuración → Pagos | Pendiente (Fase 6) |
| Transferencia bancaria | Pago manual | Datos de la cuenta bancaria de la empresa | Panel → Configuración → Pagos | Pendiente (Fase 5) |
| Chilexpress / Starken / Blue Express | Cotizar, generar etiquetas y tracking | Contrato comercial y credenciales de API con cada courier | Panel → Configuración → Envíos | Pendiente (Fase 7) |
| Correo transaccional | Confirmaciones, recuperación de contraseña | Cuenta SMTP o API (Resend, Postmark, Amazon SES, Brevo) y registros SPF, DKIM y DMARC en el DNS | `backend/.env` (Fase 1) y panel (Fase 8) | Configurable vía `.env` |
| Proveedor de IA | Asistente y generación de contenido | API Key (por defecto Anthropic Claude) y presupuesto mensual | Panel → Configuración → IA | Pendiente (Fase 12) |
| Facturación electrónica SII | Boletas y facturas | Proveedor certificado (Bsale, OpenFactura, Nubox, etc.) | Interfaz preparada | Fuera de alcance inicial |

Los pasos exactos de cada integración se documentarán en `docs/integraciones/` cuando se implementen y verifiquen.

### 8.1 Correo: registros DNS obligatorios

Para que los correos no lleguen a spam, el proveedor de correo te dará valores para:

- **SPF** (registro TXT en `mitienda.cl`)
- **DKIM** (uno o más registros TXT o CNAME)
- **DMARC** (TXT en `_dmarc.mitienda.cl`, por ejemplo `v=DMARC1; p=quarantine; rua=mailto:dmarc@mitienda.cl`)

### 8.2 Servicios opcionales (no necesarios para empezar)

Por defecto Flux no usa ninguno de estos servicios. Solo valen la pena en tiendas con mucho tráfico o catálogos muy grandes, y los tres **pueden correr en tu misma VPS** sin contratar nada:

| Servicio | Cuándo considerarlo | Cómo activarlo |
| --- | --- | --- |
| Redis | Muchos pedidos por minuto o colas lentas | `docker compose ... --profile redis up -d` y en `backend/.env`: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, `REDIS_HOST=redis` |
| Meilisearch | Catálogos de miles de productos con búsqueda tolerante a errores | `--profile search` y `SCOUT_DRIVER=meilisearch` con su clave (disponible desde la fase de catálogo) |
| S3, Cloudflare R2 o MinIO | Muchas imágenes o varios servidores | `FILESYSTEM_DISK=s3` y variables `AWS_*`. MinIO puede correr en la misma VPS |

## 9. Primer ingreso al panel

1. Entra a `/admin` con el usuario creado por `php artisan flux:admin`.
2. Revisa el **Tablero de avance** (Sistema → Módulos) para ver qué está disponible.
3. Configuración disponible en Fase 1: datos del negocio, usuarios y roles.
4. Activa la verificación en dos pasos para los usuarios con permisos financieros cuando esté disponible (Fase 13).

## 10. Pruebas y calidad

```bash
# Backend
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint            # formato de código

# Storefront
docker compose exec storefront npm run lint
docker compose exec storefront npx tsc --noEmit
```

Cada push a GitHub ejecuta automáticamente estas verificaciones (`.github/workflows/ci.yml`).

## 11. Problemas frecuentes

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| `SQLSTATE[08006] could not connect` | PostgreSQL aún no arranca o datos de conexión erróneos | `docker compose ps`; revisar `DB_*` en `backend/.env` |
| Error 500 sin detalle en producción | `APP_DEBUG=false` (correcto) | Ver `docker compose -f compose.prod.yml logs app` |
| `No application encryption key has been specified` | Falta `APP_KEY` | `php artisan key:generate` |
| Los correos no llegan en desarrollo | Es lo esperado | Revisarlos en <http://localhost:8025> |
| Los correos no llegan en producción | SMTP mal configurado o DNS sin SPF/DKIM | Revisar `MAIL_*` y el registro de errores del worker |
| El panel se ve sin estilos | Assets de Filament no publicados | `php artisan filament:assets` |
| Cambios en productos no se ven en la tienda | Revalidación de Next.js | Verificar `FLUX_REVALIDATE_SECRET` = `REVALIDATE_SECRET` |
| Permisos de archivos en `storage/` (Linux) | UID distinto entre tu usuario y el contenedor | `UID=$(id -u) GID=$(id -g) docker compose build app` |
| Puerto 5432, 3000 u 8000 ocupado | Otro servicio local | Cambiar `DB_FORWARD_PORT`, `STOREFRONT_PORT` o `BACKEND_PORT` al ejecutar compose |
| Con Webuzo, el dominio muestra la página por defecto de Webuzo | Falta el archivo de proxy del dominio o no se reinició el servidor web | Revisar el paso 6 de la sección 6.7 |
| Con Webuzo, error 502/503 en el dominio | Los contenedores de Flux no están corriendo | `docker compose -f compose.prod.yml --env-file .env ps` |
| Con Webuzo, error al subir imágenes grandes | Límite de tamaño del proxy | Aumentar `client_max_body_size` (Nginx) o `LimitRequestBody` (Apache) |
