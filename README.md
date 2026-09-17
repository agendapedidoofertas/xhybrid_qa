# xhybrid_qa — testes separados (CRM + Xhybrid)

Pasta **à parte** dos repos `xhybrid_site` e `crm_software`.  
Não altera o código de produção; só lê URLs / SQLite locais e gera logs.

## URLs alvo (Hostgator)

| Papel | URL |
|-------|-----|
| Site (apex) | https://8xd.com.br/ |
| Admin site | https://8xd.com.br/adminn/ |
| Lead | https://8xd.com.br/{slug}/{letra}{id} |
| Admin CRM | https://crm.8xd.com.br/adminn/ |

Deploy: pasta irmã `deploy_hostgator/` + `xhybrid_site/docs/deploy-hostgator.md`.

## Config

```bash
copy config.example.php config.php
# ajuste paths locais se necessário
```

`config.php` é gitignored (default: `require config.example.php`).

## Rodar

```powershell
cd C:\Users\Bosco\Documents\GitHub\xhybrid_qa
php php/smoke_http.php      # varredura HTTP (produção)
php php/qa_roundtrip.php    # sync CRM↔site no SQLite local
php php/run_all.php         # os dois + resumo

cd playwright
npm.cmd run test:headed     # smoke + e2e (credenciais em .env)
```

Logs em `logs/`.

## Nota

Até o cPanel apontar DocumentRoot + subdomínio + configs `data/`, o smoke HTTP pode falhar — isso é esperado. Siga o checklist em `deploy_hostgator/CHECKLIST-CPANEL.md`.
