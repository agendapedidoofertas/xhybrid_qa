# Pendências de infra

Layout **alvo** (já refletido em `config.example.php` e Playwright):

| Item | URL |
|------|-----|
| App Xhybrid | `https://8xd.com.br/` |
| Admin site | `https://8xd.com.br/adminn/` |
| Admin CRM | `https://crm.8xd.com.br/adminn/` |

## O que ainda é trabalho no cPanel (não no código)

1. Document Root de `8xd.com.br` = pasta do Xhybrid  
2. Subdomínio `crm` → pasta do CRM + SSL  
3. Copiar configs de `deploy_hostgator/site-data` e `crm-data`  
4. Remover `app_base_path.php` com `'/xhybrid_site'` se existir  
5. Reativar um lead e conferir URL na raiz  

Guia: `../deploy_hostgator/CHECKLIST-CPANEL.md` e `../xhybrid_site/docs/deploy-hostgator.md`.

## Como ler os logs

1. `php php/run_all.php` ou `php php/smoke_http.php`
2. Abrir o `.md` mais recente em `logs/`
3. **FAIL** = consertar no servidor/config  
4. **PENDING** = legado `/xhybrid_site` ainda vivo, ou lead inativo  
5. **OK** = passou  

## Playwright

Ver `playwright/README.md`. Credenciais em `playwright/.env` (gitignored).
