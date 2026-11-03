FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev ca-certificates \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
COPY render/apache.conf /etc/apache2/sites-available/000-default.conf
COPY render/php-production.ini /usr/local/etc/php/conf.d/api-production.ini

# Reutiliza los endpoints del proyecto, sin copiar credenciales locales.
COPY index.php login.php registro.php usuarios.php obtener_riesgo.php ruta_protegida.php /var/www/html/
# Solo la imagen de alojamiento usa esta conexion de produccion.
COPY render/conexion.php render/health.php /var/www/html/

EXPOSE 8080
CMD ["apache2-foreground"]
