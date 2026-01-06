# Relatório de Auditoria de Segurança - Plataforma de Apostas

## Resumo Executivo
A auditoria identificou vulnerabilidades críticas que permitem a manipulação de saques e a confirmação fraudulenta de pagamentos. A exposição de rotas sensíveis sem autenticação e a falta de validação em webhooks de pagamento representam um risco imediato de perda financeira e comprometimento de dados.

---

## Lista Priorizada de Vulnerabilidades

### 1. [ALTA] Rotas Críticas de Saque e PIN sem Autenticação
- **Módulo:** API / Saques / PIN
- **Arquivo:** [api.php](file:///c:/Users/LG/Downloads/netto1/Empresta+/lendwell-connect/emp_games/routes/api.php)
- **Descrição:** Rotas como `/account_withdraw`, `/sen-saque` e `/verify-pin` estão fora dos grupos de middleware de autenticação (`auth:api` ou `auth:jwt`).
- **Impacto:** Um atacante pode criar contas de saque para qualquer usuário, alterar ou verificar PINs de segurança apenas conhecendo o `user_id`.
- **Recomendação:** Mover essas rotas para dentro do grupo de autenticação JWT/Sanctum.

### 2. [ALTA] Webhooks de Pagamento sem Validação (SuitPay / BsPay)
- **Módulo:** Gateways de Pagamento
- **Arquivos:** [SuitPayController.php](file:///c:/Users/LG/Downloads/netto1/Empresta+/lendwell-connect/emp_games/app/Http/Controllers/Gateway/SuitPayController.php), [BsPayController.php](file:///c:/Users/LG/Downloads/netto1/Empresta+/lendwell-connect/emp_games/app/Http/Controllers/Gateway/BsPayController.php)
- **Descrição:** Os callbacks de pagamento processam notificações sem verificar assinaturas digitais (hash/hmac) ou restringir IPs de origem.
- **Impacto:** Confirmação de depósitos falsos ou aprovação de saques indevidos através de requisições POST simuladas.
- **Recomendação:** Implementar verificação de `signature` ou `token` enviado pelo gateway no Header e validar o IP de origem conforme documentação oficial.

### 3. [ALTA] Exposição de Logs Públicos
- **Módulo:** Infraestrutura / Configuração
- **Arquivo:** [logs.txt](file:///c:/Users/LG/Downloads/netto1/Empresta+/lendwell-connect/emp_games/public/logs.txt)
- **Descrição:** Um arquivo de log contendo tokens e detalhes de transações está acessível publicamente na pasta `public`.
- **Impacto:** Vazamento de tokens de vitória e saldos de usuários, permitindo ataques de replay ou hijacking de sessões de jogo.
- **Recomendação:** Deletar o arquivo do diretório `public` e configurar os logs para o diretório `storage/logs` (protegido).

### 4. [MÉDIA] Credenciais e Informações Sensíveis Hardcoded
- **Módulo:** Gateways
- **Arquivo:** [SuitPayController.php](file:///c:/Users/LG/Downloads/netto1/Empresta+/lendwell-connect/emp_games/app/Http/Controllers/Gateway/SuitPayController.php) (Linhas 61-62)
- **Descrição:** CPF e NOME da conta remetente estão fixos no código.
- **Impacto:** Dificulta a rotação de contas e expõe dados sensíveis no código fonte.
- **Recomendação:** Mover esses valores para o arquivo `.env` e acessá-los via `config()`.

### 5. [MÉDIA] Ausência de Rate Limit na API
- **Módulo:** Global API
- **Arquivo:** [Kernel.php](file:///c:/Users/LG/Downloads/netto1/Empresta+/lendwell-connect/emp_games/app/Http/Kernel.php)
- **Descrição:** O grupo de middleware `api` não implementa `throttle`.
- **Impacto:** Vulnerabilidade a ataques de força bruta (PIN/Login) e negação de serviço (DoS).
- **Recomendação:** Aplicar o middleware `throttle:60,1` ao grupo `api`.

---

## Observações Adicionais
- **Node.js (Api-PGSOFT):** O componente mencionado no contexto não foi localizado na estrutura de diretórios atual. Recomenda-se auditar separadamente assim que disponível.
- **SQL Injection:** O uso de Eloquent ORM na maioria dos casos protege contra SQLi, porém a lógica de `finalizePayment` deve ser mantida estritamente com `bound parameters`.
