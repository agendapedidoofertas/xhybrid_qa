# Alterações / pendências de infra (varredura)

Atualizado com o layout real informado:

| Item | Hoje | Alvo futuro |
|------|------|-------------|
| App Xhybrid | `https://8xd.com.br/xhybrid_site/` | `https://8xd.com.br/` (DocumentRoot) |
| Admin site | `https://8xd.com.br/xhybrid_site/admin/` | `https://8xd.com.br/admin/` |
| Admin CRM | `https://8xd.com.br/crm_software/admin/` | `https://crm.8xd.com.br/admin/` |
| Apex `8xd.com.br` | Ainda **não** é o app | Apontar DocumentRoot / mover conteúdo |
| `crm.8xd.com.br` | Ainda **não** ativo | Subdomínio → pasta CRM |

## Como ler os logs

1. Rodar `php php/run_all.php`
2. Abrir o `.md` mais recente em `logs/`
3. **FAIL** = consertar
4. **PENDING** = esperado por enquanto (apex/subdomínio) ou precisa checagem manual
5. **OK** = passou

## Próximos testes (Playwright — pasta vazia)

Quando quiser UI logada (CRUD aparência campo a campo na tela):

- `playwright/` — login admin site + CRM, salvar, reload, assert

Por enquanto a varredura geral é HTTP + roundtrip SQLite.
