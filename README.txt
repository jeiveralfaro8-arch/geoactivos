# GeoActivos (Demo) - XAMPP

## 1) Copiar carpeta
Copia la carpeta `geoactivos/` dentro de:
`C:\xampp\htdocs\`

Quedará así:
`C:\xampp\htdocs\geoactivos\`

## 2) Crear BD
1. Abrir: http://localhost/phpmyadmin
2. Crear base de datos: `geoactivos`
3. Importar el SQL:
   `geoactivos/database/geoactivos_schema.sql`

## 3) Configurar DB (si aplica)
Archivo: `geoactivos/app/config/config.php`
Por defecto está para XAMPP:
- user: root
- pass: (vacío)
- db: geoactivos

## 4) Semilla DEMO (rápida)
En phpMyAdmin ejecuta:

### Tenant
INSERT INTO tenants (nombre, nit, email, estado)
VALUES ('Cliente Demo', '900000000', 'demo@cliente.com', 'ACTIVO');

### Rol
INSERT INTO roles (tenant_id, nombre) VALUES (1, 'ADMIN');

### Categoría base (para el MVP de Activos)
INSERT INTO categorias_activo (tenant_id, nombre) VALUES (1, 'GENERAL');

### Usuario admin
1) Abre para generar hash:
http://localhost/geoactivos/public/hash.php

2) Copia el hash y ejecútalo:
INSERT INTO usuarios (tenant_id, rol_id, nombre, email, pass_hash, estado)
VALUES (1, 1, 'Administrador', 'admin@demo.com', 'PEGA_AQUI_EL_HASH', 'ACTIVO');

**Credenciales**
- Email: admin@demo.com
- Pass: Admin123*

## 5) Entrar
http://localhost/geoactivos/public/index.php?route=login

## Nota
Cuando ya funcione, borra:
`geoactivos/public/hash.php`
