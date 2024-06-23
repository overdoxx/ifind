FROM php:7.4-apache

# Adicionar arquivos do projeto ao diretório raiz do servidor web
COPY . /var/www/html/

# Configurar o ServerName para suprimir o aviso
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
