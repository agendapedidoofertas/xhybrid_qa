# xhybrid_qa — testes separados (CRM + Xhybrid)

Pasta **à parte** dos repos `xhybrid_site` e `crm_software`.  
Não altera o código de produção; só lê URLs / SQLite locais e gera logs.

## URLs atuais (Hostgator — layout em subpasta)

| Papel | URL | Status esperado |
|-------|-----|-----------------|
| Site (app) | https://8xd.com.br/xhybrid_site/ | OK |
| Admin site | https://8xd.com.br/xhybrid_site/admin/ | OK (login/setup) |
| Admin CRM | https://8xd.com.br/crm_software/admin/ | OK (login/setup) |
| Apex | https://8xd.com.br | **ainda NÃO** é o Xhybrid |
| CRM subdomínio | https://crm.8xd.com.br | **ainda NÃO** ativo |

## Config

```bash
copy config.example.php config.php
# ajuste paths locais se necessário
```

`config.php` é gitignored.

## Rodar

```bash
cd xhybrid_qa
php php/smoke_http.php      # varredura HTTP (produção)
php php/qa_roundtrip.php    # sync CRM↔site no SQLite local
php php/run_all.php         # os dois + resumo
```

Logs em `logs/qa-YYYYMMDD-HHMMSS.md`.

## Workspace

Abra o multi-root `8xd.code-workspace` (três pastas: site, CRM, QA).
