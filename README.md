# FreshBox Backend

API REST multiempresa para operação de hortifrutis no modelo white-label. Cada `company` possui catálogo, banners, configurações de loja e pedidos isolados por `company_id`. A autenticação administrativa usa Laravel Sanctum e a finalização do pedido na v1 ocorre via WhatsApp.

## Visão Geral

- Framework: Laravel 13
- PHP: 8.3+
- Banco: PostgreSQL 16
- Storage de imagens: Cloudflare R2 via driver S3
- Testes: Pest
- Autenticação: Laravel Sanctum

## Funcionalidades

- Autenticação admin com token expirável
- Resolução de tenant por `company_id`
- Catálogo público com categorias, busca, destaque e promoções
- CRUD administrativo de categorias, produtos e banners
- Configuração de loja, inclusive upload de logo
- Criação de pedidos com resumo para WhatsApp
- Upload e transformação de imagens com variantes para produto

## Arquitetura da API

### Rotas de autenticação

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

### Rotas públicas do cliente

Essas rotas usam o middleware de resolução de company. Para chamadas públicas, envie `X-Company-ID` no header.

- `GET /api/client/store`
- `GET /api/client/banners`
- `GET /api/client/categories`
- `GET /api/client/categories/{slug}/products`
- `GET /api/client/products/featured`
- `GET /api/client/products/on-promo`
- `GET /api/client/products/search?q=banana`
- `GET /api/client/products/{slug}`
- `POST /api/client/orders`

### Rotas administrativas

Essas rotas exigem `Authorization: Bearer <token>` e a company é resolvida automaticamente a partir do usuário autenticado.

- `GET /api/admin/dashboard`
- `apiResource /api/admin/categories`
- `PATCH /api/admin/categories/reorder`
- `PATCH /api/admin/categories/{id}/toggle`
- `apiResource /api/admin/products`
- `PATCH /api/admin/products/{id}/toggle`
- `POST /api/admin/products/{id}/image`
- `apiResource /api/admin/banners`
- `PATCH /api/admin/banners/{id}/toggle`
- `GET /api/admin/orders`
- `GET /api/admin/orders/{id}`
- `PATCH /api/admin/orders/{id}/status`
- `GET /api/admin/store`
- `PUT /api/admin/store`
- `POST /api/admin/store/logo`

## Requisitos

- PHP 8.3 ou superior
- Composer
- PostgreSQL 16
- Extensão `pdo_pgsql`
- Extensão `gd` para processamento de imagem
- Xdebug opcional para cobertura

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure o `.env` com PostgreSQL e Cloudflare R2. Os campos mínimos são:

```env
APP_NAME="FreshBox Backend"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hortifruti
DB_USERNAME=postgres
DB_PASSWORD=

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=freshbox
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_URL=https://<public_r2_domain>

QUEUE_CONNECTION=sync
```

Depois rode:

```bash
php artisan migrate --seed
```

## Execução local

```bash
php artisan serve
```

Por padrão, a aplicação ficará disponível em `http://127.0.0.1:8000`.

## Dados iniciais

O seeder principal cria uma empresa de demonstração com usuário administrativo.

- E-mail: `admin@hortifruti.test`
- Senha: `password`

Exemplo de login:

```bash
curl --request POST \
  --url http://127.0.0.1:8000/api/auth/login \
  --header 'Content-Type: application/json' \
  --data '{
    "email": "admin@hortifruti.test",
    "password": "password"
  }'
```

## Multiempresa

- Requisições públicas dependem do header `X-Company-ID`
- Requisições autenticadas usam o `company_id` do usuário logado
- Companies inativas retornam `404`

## Uploads e imagens

O backend envia arquivos para o bucket R2 configurado em `AWS_BUCKET` e gera URLs públicas com `AWS_URL`.

Fluxos suportados:

- imagem de produto com variantes `thumb`, `card` e `full`
- imagem principal e mobile para banners
- logo da loja

## Testes

Rodar a suíte:

```bash
./vendor/bin/pest
```

Cobertura:

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage
```

Teste de integração real com R2:

```bash
RUN_R2_TESTS=1 ./vendor/bin/pest tests/Feature/Integration/R2UploadValidationTest.php
```

## Qualidade atual

- suíte automatizada cobrindo fluxos de auth, catálogo, pedidos, admin, isolamento multiempresa e imagens
- cobertura atual acima de 90%
- integração real com Cloudflare R2 validada

## Estrutura principal

```text
app/
  Actions/
  Http/Controllers/Api/
  Http/Middleware/
  Http/Requests/
  Http/Resources/
  Models/
  Services/
database/
  factories/
  migrations/
  seeders/
routes/
  api.php
tests/
  Feature/
  Unit/
```

## Comandos úteis

```bash
php artisan route:list
php artisan migrate:fresh --seed
./vendor/bin/pest
php artisan about
```

## Observações

- O projeto segue Laravel 13 por decisão do produto
- O storage padrão é `s3`, apontando para Cloudflare R2
- Para ambiente produtivo, revise `APP_DEBUG`, fila, domínio público e política de CORS antes do deploy
