# Reseñas WordPress

Plugin gratuito para WordPress desarrollado por Kairoseth para solicitar valoraciones, recoger feedback de clientes y gestionar reseñas para una o múltiples sedes.

## Estado

- Versión estable: `1.1.3`
- Plataforma: WordPress
- Licencia comercial: gratuito
- Marca: Kairoseth
- Caso de uso recomendado: envío de enlaces de valoración por WhatsApp después de una compra, servicio, reserva, cita o intervención.

## Descarga

La distribución instalable validada está versionada en el propio repositorio:

`dist/resenas-wordpress-1.1.3.zip`

## Funcionalidad principal

- Valoración de 1 a 5 estrellas.
- Feedback interno para valoraciones bajas.
- Acceso a Google Reviews para valoraciones altas.
- Una sola sede o múltiples sedes.
- URL de Google independiente por sede.
- Referencia externa mediante `?ref=`.
- Selección de sede mediante `?sede=`.
- Registro del origen de la valoración.
- Personalización de logo, colores y textos.
- Notificaciones por email para feedback bajo.
- Datos almacenados localmente en WordPress.

## Shortcode

```text
[resenas_wordpress]
```

Se mantiene compatibilidad con:

```text
[lad_review_funnel]
```

## Ejemplos de uso

Una sola sede:

```text
https://empresa.com/resenas/?ref=PEDIDO-123
```

Multisede:

```text
https://empresa.com/resenas/?sede=centro&ref=PEDIDO-123
```

El origen puede enviarse mediante el parámetro `source`; el uso recomendado del producto es WhatsApp, pero también puede utilizarse desde web, email, SMS, QR u otros canales.

## Personalizaciones e integraciones

El plugin funciona de forma independiente dentro de WordPress. Para automatizaciones, WhatsApp, WooCommerce, CRM, ERP, API, dashboards u otras integraciones específicas, Kairoseth ofrece desarrollos personalizados.

## Desarrollo

La versión `1.1.3` es el core funcional validado. Las siguientes versiones comerciales deben preservar este flujo estable y evitar cualquier personalización hardcodeada para clientes concretos.
