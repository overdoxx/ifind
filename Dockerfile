FROM php:7.4-apache

# Atualizar lista de pacotes e instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Copiar arquivos do projeto para o diretório raiz do servidor web
COPY . /var/www/html/

# Configurar o ServerName para evitar avisos
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Expor porta 80 para o tráfego externo
EXPOSE 80

# Comando para iniciar o Apache em primeiro plano ao iniciar o contêiner
CMD ["apache2-foreground"]
