# extintores

Sistema de controle e manutenção de extintores. A aplicação é escrita em PHP e utiliza
MySQL como banco de dados.

## Requisitos de instalação

- **PHP 7.4 ou superior**
- Servidor web (opcional para uso em produção)
- MySQL 5.7 ou superior
- [Composer](https://getcomposer.org/) para instalação de dependências opcionais

## Inicialização do banco de dados

Um *dump* do banco está disponível em `config/eniltonbd.sql`. Crie um banco de dados
novo e importe esse arquivo:

```bash
mysql -u <usuario> -p <nome_do_banco> < config/eniltonbd.sql
```

Atualize os dados de conexão em `config/db_conexao.php` ou defina as
seguintes variáveis de ambiente conforme sua configuração:

```bash
export DB_HOST=<host>
export DB_USER=<usuario>
export DB_PASS=<senha>
export DB_NAME=<nome_do_banco>
```

## Configuração de variáveis de ambiente

As credenciais do banco de dados podem ser definidas diretamente em
`config/db_conexao.php` ou via variáveis de ambiente conforme mostrado
acima. Se optar por usar variáveis de ambiente, ajuste o arquivo para
utilizá-las:

```php
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));
```

## Dependências opcionais

Para envio de e-mails a aplicação utiliza o pacote
[PHPMailer](https://github.com/PHPMailer/PHPMailer). Caso ainda não
tenha o diretório `vendor` configurado, siga os passos abaixo a partir
do diretório raiz do projeto:

1. Instale as dependências com o Composer:

   ```bash
   composer require phpmailer/phpmailer
   ```

2. Após a execução do comando acima o diretório `vendor` será criado com
   o `autoload.php` utilizado em `enviar_alertas.php`.

## Executar localmente

Para fins de testes você pode utilizar o servidor embutido do PHP a
partir do diretório do projeto:

```bash
php -S localhost:8000
```

Depois acesse `http://localhost:8000` em seu navegador. Certifique-se de
que o banco de dados esteja acessível com as credenciais configuradas.

