# Gestión de Reseñas — WordPress.org 1.2.0

## Identidad

- Nombre comercial: **Gestión de Reseñas**.
- Autor / fabricante: **Kairoseth**.
- Slug y text-domain de WordPress.org: `gestion-resenas`.
- Versión pública: **1.2.0**.
- Shortcode principal conservado: `[resenas_wordpress]`.
- Shortcode legacy conservado: `[lad_review_funnel]`.

## Release final

La versión 1.2.0 promueve el RC4 validado a release pública sin cambiar el comportamiento funcional aceptado en producción.

Incluye:

- frontend premium responsive;
- rating 1–5 accesible por teclado;
- estados de foco y ARIA;
- flujo de recuperación del servicio para valoraciones bajas;
- acceso secundario a Google después del feedback interno;
- Google como acción principal para valoraciones altas cuando está configurado;
- dashboard y administración WordPress-native;
- ajustes separados en General, Apariencia, Textos y Notificaciones;
- source strings en inglés + catálogo español;
- minimización de datos técnicos en registros nuevos;
- migración automática desde el paquete legacy sin borrar configuración, sedes ni feedback;
- paquete de distribución limpio en carpeta `gestion-resenas/`.

## Compatibilidad WordPress.org

- `Version: 1.2.0` en el plugin principal.
- `Stable tag: 1.2.0` en `readme.txt`.
- `Requires at least: 6.4`.
- `Tested up to: 7.1`.
- `Requires PHP: 7.4`.
- GPL v2 o posterior.
- Slug y text-domain alineados en `gestion-resenas`.

## Gates de publicación

La release debe considerarse publicable únicamente cuando los tres gates se ejecuten sobre el mismo head final:

- **Build WordPress.org Release**: PHP, JavaScript, versión, stable tag, ZIP e integridad.
- **WordPress Plugin Check**: Plugin Check oficial sin errores.
- **Legacy Upgrade Compatibility**: migración real desde v1.1.3 preservando ajustes, sedes, feedback, shortcodes y traducción española.

Además, la aceptación manual en WordPress real ya cubre el frontend y los flujos funcionales que se utilizarán en producción.

## Artefacto

El artefacto instalable final es:

`gestion-resenas-1.2.0.zip`

El paquete contiene únicamente el plugin. Los banners, iconos y screenshots de la ficha de WordPress.org se publican aparte en el directorio `assets/` del SVN de WordPress.org y no deben incluirse dentro del ZIP instalable.
