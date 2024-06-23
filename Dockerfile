FROM php:7.4-apache
# Start with the official PHP image
FROM php:fpm

# Install the necessary PHP extensions for MongoDB and Redis
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

# Atualizar lista de pacotes e instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copiar arquivos do projeto para o diretório raiz do servidor web
COPY . /var/www/html/


# Definir permissões de arquivos
RUN chown -R www-data:www-data /var/www/html

# Expor porta 80 para o tráfego externo
EXPOSE 8000

# Comando para iniciar o Apache em primeiro plano ao iniciar o contêiner
CMD ["apache2-foreground"]
