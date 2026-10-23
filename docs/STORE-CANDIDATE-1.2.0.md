# Gestión de Reseñas — Store Candidate 1.2.0

## Identidad

- Nombre comercial: **Gestión de Reseñas**.
- Autor / fabricante: **Kairoseth**.
- Slug y text-domain de WordPress.org: `gestion-resenas`.
- Shortcode principal conservado: `[resenas_wordpress]`.
- Shortcode legacy conservado: `[lad_review_funnel]`.

## Objetivo

Convertir el core validado v1.1.3 en una versión pública para WordPress.org sin perder datos ni compatibilidad, elevando UX, accesibilidad, administración y privacidad.

## Store Candidate RC3

El candidato `1.2.0-rc3` incluye:

- frontend premium responsive;
- rating 1–5 accesible por teclado;
- estados de foco y ARIA;
- flujo de recuperación del servicio para valoraciones bajas;
- acceso secundario a Google después del feedback interno;
- dashboard y administración WordPress-native;
- ajustes separados en General, Apariencia, Textos y Notificaciones;
- source strings en inglés + catálogo español;
- minimización de datos técnicos en registros nuevos;
- migración automática desde el paquete legacy sin borrar configuración, sedes ni feedback;
- paquete de distribución limpio en carpeta `gestion-resenas/`.

## Aceptación automática

Sobre el mismo commit de aceptación del RC3 se han validado:

- build del paquete: **success**;
- `php -l`: **success**;
- validación JavaScript: **success**;
- integridad ZIP: **success**;
- WordPress Plugin Check 2.1.0: **`Success: Checks complete. No errors found.`**;
- upgrade real v1.1.3 → Store Candidate: **success**;
- conservación de ajustes legacy: **success**;
- conservación de sedes: **success**;
- conservación de feedback: **success**;
- shortcode `[resenas_wordpress]`: **success**;
- shortcode `[lad_review_funnel]`: **success**;
- traducción española empaquetada: **success**.

## Pendiente antes de publicar 1.2.0

- auditoría visual manual en WordPress real;
- prueba explícita de una sede, multisede y `?sede=` inválida;
- auditoría final de contraste con colores configurables;
- screenshots reales del plugin;
- iconos 128/256 y banners 772/1544 para WordPress.org;
- cambiar versión `1.2.0-rc3` a `1.2.0`;
- generar ZIP final de publicación y checksum;
- mantener el PR #2 en draft hasta completar estas comprobaciones.
