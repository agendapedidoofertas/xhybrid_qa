# Playwright — smoke UI (só Chromium)

## Rodar

```powershell
cd C:\Users\Bosco\Documents\GitHub\xhybrid_qa\playwright

# Sem janela
npm test

# Com janela visível (mais lento)
npm run test:headed

# Com janela + pausa entre ações (~350 ms)
$env:PW_SLOWMO=350; npm run test:headed
```

## Credenciais (opção arquivo local)

1. Abra `playwright/.env` (já criado, **não vai pro Git**).
2. Preencha:

```
QA_SITE_USER=seu_user
QA_SITE_PASS=sua_senha
QA_CRM_USER=seu_user_crm
QA_CRM_PASS=sua_senha_crm
```

3. Rode:

```powershell
cd C:\Users\Bosco\Documents\GitHub\xhybrid_qa\playwright
npm run test:headed
```

Modelo sem senha: `.env.example` (pode ir pro Git).

## Cobertura atual

1. Site público (home, páginas, assets)
2. Lead `solusempreiteira/v21`
3. Admin site / CRM sem sessão
4. Bloqueio `data/*.sqlite`
5. Apex / subdomínio (infra)
6. Login (grupo 7 smoke)
7. **e2e-logged:** menus logados, contato QA_TEST revertido, 3 leads QA, user QA, Places PENDING, planos

```powershell
npm.cmd run test:headed
```
