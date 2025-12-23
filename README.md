# Sistema de Controle e Manutenção de Extintores

Este repositório contém uma aplicação web em PHP destinada ao controle completo do ciclo de vida de extintores de incêndio. O sistema registra inspeções de campo, manutenções periódicas e histórico de cada extintor. Além disso, disponibiliza funcionalidades de auditoria, exportação de dados e relatórios em gráficos.

## Recursos principais

- **Cadastro de extintores** com geração automática de código por prédio.
- **Inspeção de Nível 1 (bombeiro)**: checklist de itens e upload de foto.
- **Manutenção de Nível 2 (fornecedor)** com registro de próxima manutenção.
- **Liberação de manutenções/inspeções** por prédio ou extintor.
- **Aprovação de novos extintores** pelo administrador.
- **Controle de pesagem** de extintores de CO₂.
- **Filtros de vencimento** e exportação de históricos em diversos formatos.
- **Dashboard** com gráficos de manutenções e distribuição de tipos.
- **Auditoria** de ações do usuário e logs acessíveis ao administrador.
- **Suporte a PWA** (service worker e `manifest.json`) para acesso offline básico.

## Perfis de usuário

- **Administrador**: aprova extintores, libera inspeções/manutenções, gerencia usuários, consulta logs e dashboard.
- **Bombeiro**: realiza inspeções de Nível 1 e pode cadastrar novos extintores.
- **Fornecedor**: executa manutenções de Nível 2 quando liberado pelo administrador.

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Opcionalmente um servidor web (Apache ou Nginx) para uso em produção
- [Composer](https://getcomposer.org/) para instalação das dependências (PHPMailer)

## Instalação

1. Clone este repositório e acesse o diretório do projeto.
2. Crie um banco de dados vazio e importe o `dump` inicial localizado em `config/eniltonbd.sql`:
   ```bash
   mysql -u <usuario> -p <nome_do_banco> < config/eniltonbd.sql
   ```
3. Configure a conexão do banco definindo as variáveis de ambiente `DB_HOST`, `DB_USER`, `DB_PASS` e `DB_NAME`. Para desenvolvimento local você pode ajustar os valores padrão em `config/db_conexao.php`, mas **não** versione credenciais reais.
4. Instale as dependências (diretório `vendor`) caso utilize o envio de e-mails:
   ```bash
   composer install
   ```

## Execução local

Para testes rápidos você pode utilizar o servidor embutido do PHP:

```bash
php -S localhost:8000
```

Depois acesse `http://localhost:8000` no navegador. Certifique‑se de que o banco está configurado corretamente.

Em produção recomenda‑se configurar Apache ou Nginx apontando para o diretório do projeto.

## Fluxo de utilização

1. **Login** em `login.php` com usuário e senha cadastrados. O menu superior é ajustado conforme o perfil do usuário.
2. **Administração**:
   - Libera extintores ou prédios em `liberar_manutencao.php` para que bombeiros ou fornecedores registrem intervenções.
   - Aprova novos extintores em `aprovar_extintores.php`.
   - Gerencia contas em `registrar_usuario.php` e consulta o log de ações em `auditoria_logs.php`.
   - Pode exportar dados, limpar históricos e visualizar o `dashboard.php`.
3. **Bombeiro**:
   - Utiliza `formulario_inspecao.php` para realizar inspeções de Nível 1 dos extintores liberados.
   - Cadastra novos extintores em `novo_extintor.php`; o código é gerado automaticamente via `obter_proximo_codigo.php`.
4. **Fornecedor**:
   - Acessa `formulario_manutencao.php` para registrar manutenções de Nível 2, definindo datas de próxima manutenção e coberturas.
5. **Pesagem CO₂** pode ser registrada em `controle_pesagem.php` (opcional).
6. **Histórico** de inspeções e manutenções está disponível em `historico_inspecao.php` e `historico_manutencao.php` com filtros e exportação.

Scripts auxiliares, como `atualizar_dias_expiracao.php`, podem ser agendados em um cron para atualizar os prazos de manutenção.

## Envio de e‑mails

O arquivo `enviar_alertas.php` usa PHPMailer para disparar notificações de extintores próximos ao vencimento. Edite as credenciais SMTP antes de utilizar.

## PWA e acesso offline

Os arquivos em `js/service-worker.js` e `js/manifest.json` permitem que a aplicação funcione como um aplicativo progressivo simples. O cache é configurado apenas para recursos básicos e pode ser personalizado conforme a necessidade.

## Licença

Distribuído sob a licença MIT. Consulte o arquivo `LICENSE` para mais detalhes.
