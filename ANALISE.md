# Análise de Segurança e Qualidade do Sistema de Controle de Extintores

## Sumário Executivo
A revisão do código PHP identificou problemas críticos de segurança e manutenção que podem expor dados sensíveis e comprometer a disponibilidade do sistema. Os pontos a seguir detalham vulnerabilidades observadas e recomendações práticas para mitigação.

## Achados Principais

### 1) Gerenciamento seguro de credenciais
O arquivo `config/db_conexao.php` agora lê as variáveis de ambiente `DB_HOST`, `DB_USER`, `DB_PASS` e `DB_NAME`, mantendo apenas valores de fallback para desenvolvimento. É essencial publicar instruções claras para que produção não dependa desses valores padrão e para que os segredos sejam rotacionados periodicamente.

### 2) Inclusão robusta de dependências
Todas as páginas que usam a conexão passaram a carregar `config/db_conexao.php` via `require_once __DIR__ . '/config/db_conexao.php';`, eliminando erros de caminho e reduzindo a exposição acidental de estrutura de diretórios em ambientes com `display_errors` ativado.

### 3) Falta de isolamento de configuração por ambiente
A configuração do banco é única para todos os ambientes, sem suporte a variáveis específicas para desenvolvimento, homologação ou produção. Isso dificulta rotacionar segredos e aplicar princípios de menor privilégio. Implementar carregamento condicionado por variáveis de ambiente reduz o risco de usar credenciais de produção em ambientes de teste e viabiliza pipelines de CI/CD mais seguros.

## Recomendações Imediatas
- Remover credenciais reais do repositório e rotacioná-las no banco de dados.
- Refatorar os includes de configuração para usar caminhos absolutos baseados em `__DIR__`.
- Adotar carregamento de configurações via variáveis de ambiente com valores padrão seguros e documentação de fallback.

## Próximos Passos Sugeridos
1. Implementar um bootstrap de configuração (`config/bootstrap.php`) que centralize a leitura de variáveis e a criação da conexão PDO com tratamento robusto de erros.
2. Acrescentar verificações automatizadas (por exemplo, `phpcs` ou `psalm`) e um checklist de segurança na pipeline de CI.
3. Revisar demais endpoints que manipulam entrada do usuário para garantir validação e sanitização consistentes (inclusive CSRF onde aplicável).
