# Backend — Hortifruti App
> Guia de execução para agente. Laravel 13 · PostgreSQL · Multiempresa · REST API

---

## Contexto do projeto

App white-label para hortifrutis. Um único backend serve múltiplos clientes (companies). Cada company tem sua store, catálogo, banners e pedidos isolados por `company_id`. A v1 finaliza pedidos via WhatsApp — sem gateway de pagamento, sem rastreio, sem estoque em tempo real.

**Stack:**
- PHP 8.3 + Laravel 13
- PostgreSQL 16
- Laravel Sanctum (autenticação API)
- Intervention Image v3 (processamento de imagens)
- Cloudflare R2 (storage — API compatível com S3)
- Laravel Horizon (filas — opcional na v1, estrutura já preparada)

---

## Índice

1. [Setup inicial do projeto](#1-setup-inicial-do-projeto)
2. [Configuração do banco e variáveis de ambiente](#2-configuração-do-banco-e-variáveis-de-ambiente)
3. [Migrations](#3-migrations)
4. [Models e relacionamentos](#4-models-e-relacionamentos)
5. [Autenticação — Laravel Sanctum](#5-autenticação--laravel-sanctum)
6. [Estrutura de pastas e organização](#6-estrutura-de-pastas-e-organização)
7. [Rotas da API](#7-rotas-da-api)
8. [Feature: Catálogo (categories + products)](#8-feature-catálogo-categories--products)
9. [Feature: Banners](#9-feature-banners)
10. [Feature: Pedidos (orders)](#10-feature-pedidos-orders)
11. [Feature: Store (configurações da loja)](#11-feature-store-configurações-da-loja)
12. [Pipeline de imagens](#12-pipeline-de-imagens)
13. [Multiempresa — middleware e resolução de company](#13-multiempresa--middleware-e-resolução-de-company)
14. [Seeders e dados iniciais](#14-seeders-e-dados-iniciais)
15. [Testes](#15-testes)
16. [Checklist de entrega](#16-checklist-de-entrega)

---

## 1. Setup inicial do projeto

### 1.1 Criar projeto Laravel

```bash
composer create-project laravel/laravel hortifruti-api
cd hortifruti-api
```

### 1.2 Instalar dependências

```bash
# Autenticação API
composer require laravel/sanctum

# Processamento de imagens
composer require intervention/image

# AWS S3 / Cloudflare R2
composer require league/flysystem-aws-s3-v3

# Helpers de desenvolvimento
composer require --dev barryvdh/laravel-ide-helper
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
```

### 1.3 Publicar configs

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Intervention\Image\Providers\LaravelServiceProvider"
```

### 1.4 Configurar Pest

```bash
./vendor/bin/pest --init
```

### Critério de conclusão
- `php artisan serve` sobe sem erros
- `php artisan about` mostra versão 13.x
- `./vendor/bin/pest` executa sem falhar

---

## 2. Configuração do banco e variáveis de ambiente

### 2.1 `.env` base

```env
APP_NAME="Hortifruti API"
APP_ENV=local
APP_KEY=          # gerado com: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hortifruti
DB_USERNAME=postgres
DB_PASSWORD=

FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=           # chave R2
AWS_SECRET_ACCESS_KEY=       # secret R2
AWS_DEFAULT_REGION=auto
AWS_BUCKET=freshbox
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_URL=https://<custom_domain_cdn>   # domínio público CDN

QUEUE_CONNECTION=sync        # trocar para redis quando adicionar Horizon

SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

### 2.2 Configurar driver S3 para R2

Em `config/filesystems.php`, no array `disks.s3`, adicionar:

```php
'use_path_style_endpoint' => true,
```

> **Motivo:** o R2 exige path-style endpoint. Sem isso, o upload falha silenciosamente.

### 2.3 Criar banco

```bash
createdb hortifruti   # ou via pgAdmin
php artisan migrate:status   # deve retornar sem erros de conexão
```

### Critério de conclusão
- `php artisan migrate:status` conecta ao banco sem erro
- Upload de teste para R2 funciona via `Storage::disk('s3')->put('test.txt', 'ok')`

---

## 3. Migrations

> As migrations já estão definidas no arquivo `migrations.php` gerado anteriormente.
> Copiar cada classe para `database/migrations/` com o prefixo de timestamp correto.

### Ordem obrigatória (dependências)

```
2024_01_01_000001_create_companies_table.php
2024_01_01_000002_create_users_table.php
2024_01_01_000003_create_stores_table.php
2024_01_01_000004_create_categories_table.php
2024_01_01_000005_create_products_table.php
2024_01_01_000006_create_product_images_table.php
2024_01_01_000007_create_banners_table.php
2024_01_01_000008_create_orders_table.php
2024_01_01_000009_create_order_items_table.php
```

### Executar

```bash
php artisan migrate
```

### Verificar índices criados

```sql
-- Executar no psql para confirmar índices
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename IN ('products', 'orders', 'banners')
ORDER BY tablename, indexname;
```

### Critério de conclusão
- `php artisan migrate` roda sem erros
- Todas as 9 tabelas existem no banco
- Índices compostos confirmados via query acima

---

## 4. Models e relacionamentos

### 4.1 Company

**Arquivo:** `app/Models/Company.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'plan', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

### 4.2 User

**Arquivo:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['company_id', 'name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

### 4.3 Store

**Arquivo:** `app/Models/Store.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'company_id', 'name', 'phone', 'whatsapp', 'email', 'instagram',
        'description', 'logo_url', 'address_street', 'address_number',
        'address_complement', 'address_neighborhood', 'address_city',
        'address_state', 'address_zip', 'address_lat', 'address_lng',
        'delivery_fee', 'min_order_value', 'delivery_radius_km',
        'business_hours', 'is_active', 'accepts_delivery', 'accepts_pickup',
    ];

    protected $casts = [
        'business_hours'   => 'array',
        'is_active'        => 'boolean',
        'accepts_delivery' => 'boolean',
        'accepts_pickup'   => 'boolean',
        'delivery_fee'     => 'decimal:2',
        'min_order_value'  => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Verifica se a loja está aberta agora.
     * business_hours: {"mon":{"open":"08:00","close":"18:00"},"sun":null}
     */
    public function isOpenNow(): bool
    {
        if (! $this->is_active || ! $this->business_hours) {
            return false;
        }

        $day = strtolower(now()->format('D')); // mon, tue, wed...
        $hours = $this->business_hours[$day] ?? null;

        if (! $hours) return false;

        $now   = now()->format('H:i');
        return $now >= $hours['open'] && $now <= $hours['close'];
    }
}
```

### 4.4 Category

**Arquivo:** `app/Models/Category.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'parent_id', 'name', 'slug',
        'icon_url', 'image_url', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Scope para query do app cliente
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
```

### 4.5 Product

**Arquivo:** `app/Models/Product.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'category_id', 'name', 'slug', 'description',
        'unit', 'price', 'promo_price', 'promo_ends_at',
        'image_thumb_url', 'image_card_url', 'image_full_url',
        'is_active', 'is_featured', 'is_available', 'sort_order',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'promo_price'  => 'decimal:2',
        'promo_ends_at'=> 'datetime',
        'is_active'    => 'boolean',
        'is_featured'  => 'boolean',
        'is_available' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    // Preço efetivo (respeita promoção com validade)
    public function getEffectivePriceAttribute(): string
    {
        if ($this->promo_price && (! $this->promo_ends_at || $this->promo_ends_at->isFuture())) {
            return $this->promo_price;
        }
        return $this->price;
    }

    // Promoção está ativa agora?
    public function getIsOnPromoAttribute(): bool
    {
        return $this->promo_price !== null
            && (! $this->promo_ends_at || $this->promo_ends_at->isFuture());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnPromo($query)
    {
        return $query->whereNotNull('promo_price')
            ->where(fn($q) => $q->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>', now()));
    }
}
```

### 4.6 Banner

**Arquivo:** `app/Models/Banner.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'title', 'subtitle', 'image_url', 'image_mobile_url',
        'link_type', 'link_value', 'priority', 'period_start', 'period_end', 'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'priority'     => 'integer',
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('period_start')->orWhere('period_start', '<=', now()))
            ->where(fn($q) => $q->whereNull('period_end')->orWhere('period_end', '>=', now()))
            ->orderByDesc('priority');
    }
}
```

### 4.7 Order + OrderItem

**Arquivo:** `app/Models/Order.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id', 'store_id', 'customer_name', 'customer_phone',
        'delivery_type', 'delivery_street', 'delivery_number',
        'delivery_complement', 'delivery_neighborhood', 'delivery_city',
        'delivery_zip', 'subtotal', 'delivery_fee', 'total',
        'payment_method', 'observations', 'status',
        'confirmed_at', 'completed_at',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'delivery_fee'   => 'decimal:2',
        'total'          => 'decimal:2',
        'confirmed_at'   => 'datetime',
        'completed_at'   => 'datetime',
    ];

    // Status válidos
    const STATUSES = ['new', 'preparing', 'ready', 'dispatched', 'completed', 'cancelled'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

**Arquivo:** `app/Models/OrderItem.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'product_unit',
        'unit_price', 'quantity', 'subtotal', 'observation',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

### Critério de conclusão
- Todos os models criados sem erros de sintaxe
- `php artisan tinker` → `Company::first()` retorna sem erro (após seeder)
- Relacionamentos testados via tinker: `$company->products`, `$order->items`

---

## 5. Autenticação — Laravel Sanctum

### 5.1 AuthController

**Arquivo:** `app/Http/Controllers/Api/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Revogar tokens antigos (single session por usuário)
        $user->tokens()->delete();

        $token = $user->createToken(
            'admin-panel',
            ['admin'],           // abilities — expandir quando tiver roles granulares
            now()->addDays(30),  // expiração
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'company_id' => $user->company_id,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('company'));
    }
}
```

### 5.2 Proteger rotas do admin

Em `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    // todas as rotas admin aqui
});
```

### Critério de conclusão
- `POST /api/auth/login` retorna token válido
- `GET /api/auth/me` com Bearer token retorna usuário
- `POST /api/auth/logout` invalida o token
- Token expirado retorna 401

---

## 6. Estrutura de pastas e organização

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── Admin/
│   │       │   ├── CategoryController.php
│   │       │   ├── ProductController.php
│   │       │   ├── BannerController.php
│   │       │   ├── OrderController.php
│   │       │   └── StoreController.php
│   │       └── Client/
│   │           ├── CatalogController.php
│   │           ├── BannerController.php
│   │           ├── OrderController.php
│   │           └── StoreController.php
│   ├── Middleware/
│   │   └── ResolveCompany.php
│   ├── Requests/
│   │   ├── Admin/
│   │   │   ├── StoreCategoryRequest.php
│   │   │   ├── StoreProductRequest.php
│   │   │   ├── StoreBannerRequest.php
│   │   │   └── StoreOrderStatusRequest.php
│   │   └── Client/
│   │       └── PlaceOrderRequest.php
│   └── Resources/
│       ├── CategoryResource.php
│       ├── ProductResource.php
│       ├── BannerResource.php
│       ├── OrderResource.php
│       └── StoreResource.php
├── Models/                         # (conforme seção 4)
├── Services/
│   ├── ImageService.php            # pipeline de imagens
│   └── WhatsAppService.php         # monta URL do pedido
└── Actions/
    └── PlaceOrderAction.php        # orquestra criação do pedido
```

> **Regra:** controllers não têm lógica de negócio. Validação fica em `FormRequest`, lógica fica em `Action` ou `Service`, resposta fica em `Resource`.

---

## 7. Rotas da API

**Arquivo:** `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Client;

// ── Auth ──────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',     [AuthController::class, 'me']);
    });
});

// ── App cliente (público) ─────────────────────────────────
// Header obrigatório: X-Company-ID: <uuid>
Route::middleware('resolve.company')->prefix('client')->group(function () {
    Route::get('store',                     [Client\StoreController::class, 'show']);
    Route::get('banners',                   [Client\BannerController::class, 'index']);
    Route::get('categories',                [Client\CatalogController::class, 'categories']);
    Route::get('categories/{slug}/products',[Client\CatalogController::class, 'byCategory']);
    Route::get('products/{slug}',           [Client\CatalogController::class, 'show']);
    Route::get('products/featured',         [Client\CatalogController::class, 'featured']);
    Route::get('products/on-promo',         [Client\CatalogController::class, 'onPromo']);
    Route::get('products/search',           [Client\CatalogController::class, 'search']);
    Route::post('orders',                   [Client\OrderController::class, 'store']);
});

// ── Painel admin (autenticado) ────────────────────────────
Route::middleware(['auth:sanctum', 'resolve.company'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('dashboard', [Admin\DashboardController::class, 'index']);

    // Categorias
    Route::apiResource('categories', Admin\CategoryController::class);
    Route::patch('categories/{category}/toggle', [Admin\CategoryController::class, 'toggle']);
    Route::patch('categories/reorder',           [Admin\CategoryController::class, 'reorder']);

    // Produtos
    Route::apiResource('products', Admin\ProductController::class);
    Route::patch('products/{product}/toggle',    [Admin\ProductController::class, 'toggle']);
    Route::post('products/{product}/image',      [Admin\ProductController::class, 'uploadImage']);

    // Banners
    Route::apiResource('banners', Admin\BannerController::class);
    Route::patch('banners/{banner}/toggle',      [Admin\BannerController::class, 'toggle']);

    // Pedidos
    Route::get('orders',                         [Admin\OrderController::class, 'index']);
    Route::get('orders/{order}',                 [Admin\OrderController::class, 'show']);
    Route::patch('orders/{order}/status',        [Admin\OrderController::class, 'updateStatus']);

    // Loja
    Route::get('store',                          [Admin\StoreController::class, 'show']);
    Route::put('store',                          [Admin\StoreController::class, 'update']);
    Route::post('store/logo',                    [Admin\StoreController::class, 'uploadLogo']);
});
```

### Critério de conclusão
- `php artisan route:list` lista todas as rotas sem conflito
- Rotas `client/*` retornam 200 com header correto
- Rotas `admin/*` retornam 401 sem token

---

## 8. Feature: Catálogo (categories + products)

### 8.1 Middleware ResolveCompany

**Arquivo:** `app/Http/Middleware/ResolveCompany.php`

```php
<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

class ResolveCompany
{
    public function handle(Request $request, Closure $next)
    {
        // Cliente envia header X-Company-ID
        // Admin já tem company_id no user autenticado
        $companyId = $request->header('X-Company-ID')
            ?? $request->user()?->company_id;

        $company = Company::where('id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $company) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Disponível em toda a request
        $request->merge(['_company' => $company]);
        app()->instance('current_company', $company);

        return $next($request);
    }
}
```

Registrar em `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'resolve.company' => \App\Http\Middleware\ResolveCompany::class,
    ]);
})
```

### 8.2 CatalogController (cliente)

**Arquivo:** `app/Http/Controllers/Api/Client/CatalogController.php`

```php
<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    private function company()
    {
        return app('current_company');
    }

    public function categories()
    {
        $categories = Category::where('company_id', $this->company()->id)
            ->active()
            ->withCount(['products' => fn($q) => $q->active()])
            ->get();

        return CategoryResource::collection($categories);
    }

    public function byCategory(string $slug)
    {
        $category = Category::where('company_id', $this->company()->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $products = Product::where('company_id', $this->company()->id)
            ->where('category_id', $category->id)
            ->active()
            ->orderBy('sort_order')
            ->paginate(24);

        return ProductResource::collection($products);
    }

    public function show(string $slug)
    {
        $product = Product::where('company_id', $this->company()->id)
            ->where('slug', $slug)
            ->active()
            ->with('images')
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function featured()
    {
        $products = Product::where('company_id', $this->company()->id)
            ->active()
            ->featured()
            ->limit(10)
            ->get();

        return ProductResource::collection($products);
    }

    public function onPromo()
    {
        $products = Product::where('company_id', $this->company()->id)
            ->active()
            ->onPromo()
            ->orderBy('sort_order')
            ->paginate(24);

        return ProductResource::collection($products);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $products = Product::where('company_id', $this->company()->id)
            ->active()
            ->where('name', 'ilike', "%{$request->q}%")  // ilike = case-insensitive no PostgreSQL
            ->limit(30)
            ->get();

        return ProductResource::collection($products);
    }
}
```

### 8.3 ProductResource

**Arquivo:** `app/Http/Resources/ProductResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'unit'        => $this->unit,
            'price'       => (float) $this->price,
            'promo_price' => $this->promo_price ? (float) $this->promo_price : null,
            'promo_ends_at' => $this->promo_ends_at?->toIso8601String(),
            'is_on_promo' => $this->is_on_promo,       // accessor
            'effective_price' => (float) $this->effective_price, // accessor
            'images' => [
                'thumb' => $this->image_thumb_url,
                'card'  => $this->image_card_url,
                'full'  => $this->image_full_url,
            ],
            'is_available' => $this->is_available,
            'is_featured'  => $this->is_featured,
            'category_id'  => $this->category_id,
        ];
    }
}
```

### 8.4 Admin\ProductController

**Arquivo:** `app/Http/Controllers/Api/Admin/ProductController.php`

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private ImageService $imageService) {}

    private function company()
    {
        return app('current_company');
    }

    public function index(Request $request)
    {
        $products = Product::where('company_id', $this->company()->id)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search,      fn($q) => $q->where('name', 'ilike', "%{$request->search}%"))
            ->when($request->active !== null, fn($q) => $q->where('is_active', $request->boolean('active')))
            ->with('category')
            ->orderBy('sort_order')
            ->paginate(30);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $this->company()->id;
        $data['slug'] = Str::slug($data['name']);

        // Garantir slug único na company
        $baseSlug = $data['slug'];
        $count = 1;
        while (Product::where('company_id', $data['company_id'])->where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$baseSlug}-{$count}";
            $count++;
        }

        $product = Product::create($data);

        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        $this->authorizeCompany($product);
        return new ProductResource($product->load('images'));
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $this->authorizeCompany($product);
        $product->update($request->validated());
        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $this->authorizeCompany($product);
        $product->delete(); // soft delete
        return response()->json(null, 204);
    }

    public function toggle(Product $product)
    {
        $this->authorizeCompany($product);
        $product->update(['is_active' => ! $product->is_active]);
        return new ProductResource($product);
    }

    public function uploadImage(Request $request, Product $product)
    {
        $this->authorizeCompany($product);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:5120', // 5MB
        ]);

        $urls = $this->imageService->processProductImage(
            $request->file('image'),
            $this->company()->id,
            $product->id,
        );

        $product->update($urls);

        return new ProductResource($product);
    }

    private function authorizeCompany(Product $product): void
    {
        abort_if($product->company_id !== $this->company()->id, 403);
    }
}
```

### 8.5 StoreProductRequest

**Arquivo:** `app/Http/Requests/Admin/StoreProductRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:120',
            'description'  => 'nullable|string|max:500',
            'category_id'  => 'required|exists:categories,id',
            'unit'         => 'required|in:kg,un,bandeja,maço,cx,lt,pct',
            'price'        => 'required|numeric|min:0.01',
            'promo_price'  => 'nullable|numeric|min:0.01|lt:price',
            'promo_ends_at'=> 'nullable|date|after:now',
            'is_active'    => 'boolean',
            'is_featured'  => 'boolean',
            'is_available' => 'boolean',
            'sort_order'   => 'integer|min:0',
        ];
    }
}
```

### Critério de conclusão
- `GET /api/client/categories` retorna lista paginada com `X-Company-ID` válido
- `GET /api/client/products/search?q=banana` retorna resultados corretos
- `POST /api/admin/products` cria produto com slug gerado automaticamente
- `POST /api/admin/products/{id}/image` processa e salva as 3 variantes

---

## 9. Feature: Banners

### 9.1 Admin\BannerController

**Arquivo:** `app/Http/Controllers/Api/Admin/BannerController.php`

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\ImageService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(private ImageService $imageService) {}

    private function company() { return app('current_company'); }

    public function index()
    {
        $banners = Banner::where('company_id', $this->company()->id)
            ->orderByDesc('priority')
            ->get();

        return BannerResource::collection($banners);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'nullable|string|max:80',
            'subtitle'     => 'nullable|string|max:120',
            'image'        => 'required|image|mimes:jpeg,png,webp|max:5120',
            'image_mobile' => 'nullable|image|mimes:jpeg,png,webp|max:5120',
            'link_type'    => 'nullable|in:product,category,url,none',
            'link_value'   => 'nullable|string|max:255',
            'priority'     => 'integer|min:0',
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date|after:period_start',
            'is_active'    => 'boolean',
        ]);

        $data['company_id'] = $this->company()->id;

        // Upload da imagem principal
        $data['image_url'] = $this->imageService->uploadBannerImage(
            $request->file('image'),
            $this->company()->id,
        );

        // Upload da imagem mobile (se enviada)
        if ($request->hasFile('image_mobile')) {
            $data['image_mobile_url'] = $this->imageService->uploadBannerImage(
                $request->file('image_mobile'),
                $this->company()->id,
                'mobile',
            );
        }

        unset($data['image'], $data['image_mobile']);

        $banner = Banner::create($data);
        return new BannerResource($banner);
    }

    public function toggle(Banner $banner)
    {
        abort_if($banner->company_id !== $this->company()->id, 403);
        $banner->update(['is_active' => ! $banner->is_active]);
        return new BannerResource($banner);
    }

    public function destroy(Banner $banner)
    {
        abort_if($banner->company_id !== $this->company()->id, 403);
        $banner->delete();
        return response()->json(null, 204);
    }
}
```

### 9.2 Client\BannerController

```php
public function index()
{
    $banners = Banner::where('company_id', app('current_company')->id)
        ->active()   // scope: ativo + dentro do período
        ->get();

    return BannerResource::collection($banners);
}
```

### Critério de conclusão
- `GET /api/client/banners` retorna somente banners dentro do período e ativos
- Banner fora do `period_end` não aparece na listagem do cliente
- Upload de banner salva no R2 e retorna URL pública do CDN

---

## 10. Feature: Pedidos (orders)

### 10.1 PlaceOrderAction

**Arquivo:** `app/Actions/PlaceOrderAction.php`

```php
<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function execute(Company $company, array $data): Order
    {
        return DB::transaction(function () use ($company, $data) {
            $store = $company->stores()->where('is_active', true)->firstOrFail();

            // Buscar produtos e validar disponibilidade
            $productIds = collect($data['items'])->pluck('product_id');
            $products   = Product::where('company_id', $company->id)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // Validar que todos os produtos existem e estão disponíveis
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                throw_unless($product && $product->is_active && $product->is_available,
                    ValidationException::withMessages([
                        'items' => ["Produto {$item['product_id']} não está disponível."],
                    ])
                );
            }

            // Calcular valores
            $items    = [];
            $subtotal = 0;

            foreach ($data['items'] as $item) {
                $product    = $products->get($item['product_id']);
                $unitPrice  = $product->effective_price;
                $itemTotal  = $unitPrice * $item['quantity'];
                $subtotal  += $itemTotal;

                $items[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'product_unit' => $product->unit,
                    'unit_price'   => $unitPrice,
                    'quantity'     => $item['quantity'],
                    'subtotal'     => $itemTotal,
                    'observation'  => $item['observation'] ?? null,
                ];
            }

            $deliveryFee = $data['delivery_type'] === 'delivery' ? $store->delivery_fee : 0;

            // Validar pedido mínimo
            if ($store->min_order_value > 0 && $subtotal < $store->min_order_value) {
                throw ValidationException::withMessages([
                    'subtotal' => ["Valor mínimo do pedido é R$ {$store->min_order_value}."],
                ]);
            }

            // Criar pedido
            $order = Order::create([
                'company_id'             => $company->id,
                'store_id'               => $store->id,
                'customer_name'          => $data['customer_name'],
                'customer_phone'         => $data['customer_phone'],
                'delivery_type'          => $data['delivery_type'],
                'delivery_street'        => $data['address']['street']        ?? null,
                'delivery_number'        => $data['address']['number']        ?? null,
                'delivery_complement'    => $data['address']['complement']    ?? null,
                'delivery_neighborhood'  => $data['address']['neighborhood']  ?? null,
                'delivery_city'          => $data['address']['city']          ?? null,
                'delivery_zip'           => $data['address']['zip']           ?? null,
                'subtotal'               => $subtotal,
                'delivery_fee'           => $deliveryFee,
                'total'                  => $subtotal + $deliveryFee,
                'payment_method'         => $data['payment_method'] ?? null,
                'observations'           => $data['observations']   ?? null,
                'status'                 => 'new',
            ]);

            $order->items()->createMany($items);

            return $order->load('items');
        });
    }
}
```

### 10.2 Client\OrderController

**Arquivo:** `app/Http/Controllers/Api/Client/OrderController.php`

```php
<?php

namespace App\Http\Controllers\Api\Client;

use App\Actions\PlaceOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\WhatsAppService;

class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, PlaceOrderAction $action, WhatsAppService $whatsApp)
    {
        $company = app('current_company');
        $order   = $action->execute($company, $request->validated());

        return response()->json([
            'order'        => new OrderResource($order),
            'whatsapp_url' => $whatsApp->buildOrderUrl($order),
        ], 201);
    }
}
```

### 10.3 WhatsAppService

**Arquivo:** `app/Services/WhatsAppService.php`

```php
<?php

namespace App\Services;

use App\Models\Order;

class WhatsAppService
{
    public function buildOrderUrl(Order $order): string
    {
        $store = $order->store;
        $phone = preg_replace('/\D/', '', $store->whatsapp);

        $message = $this->buildMessage($order);

        // Limitar mensagem a 4096 caracteres (limite do WhatsApp Web)
        if (strlen($message) > 4000) {
            $message = substr($message, 0, 3990) . "\n\n[mensagem truncada]";
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    private function buildMessage(Order $order): string
    {
        $type = $order->delivery_type === 'delivery' ? 'Entrega' : 'Retirada';

        $lines = [
            "🛒 *Novo Pedido* — #{$order->id}",
            "",
            "👤 *Cliente:* {$order->customer_name}",
            "📱 *Telefone:* {$order->customer_phone}",
            "📦 *Tipo:* {$type}",
            "",
            "*Itens:*",
        ];

        foreach ($order->items as $item) {
            $lines[] = "• {$item->quantity}x {$item->product_name} ({$item->product_unit}) — R$ " . number_format($item->subtotal, 2, ',', '.');
        }

        $lines[] = "";
        $lines[] = "Subtotal: R$ " . number_format($order->subtotal, 2, ',', '.');

        if ($order->delivery_fee > 0) {
            $lines[] = "Entrega: R$ " . number_format($order->delivery_fee, 2, ',', '.');
        }

        $lines[] = "*Total: R$ " . number_format($order->total, 2, ',', '.') . "*";

        if ($order->delivery_type === 'delivery') {
            $addr    = "{$order->delivery_street}, {$order->delivery_number}";
            $addr   .= $order->delivery_complement ? ", {$order->delivery_complement}" : '';
            $addr   .= " — {$order->delivery_neighborhood}, {$order->delivery_city}";
            $lines[] = "";
            $lines[] = "📍 *Endereço:* {$addr}";
        }

        if ($order->payment_method) {
            $lines[] = "💳 *Pagamento:* {$order->payment_method}";
        }

        if ($order->observations) {
            $lines[] = "";
            $lines[] = "📝 *Observações:* {$order->observations}";
        }

        return implode("\n", $lines);
    }
}
```

### 10.4 PlaceOrderRequest

**Arquivo:** `app/Http/Requests/Client/PlaceOrderRequest.php`

```php
<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_name'     => 'required|string|max:80',
            'customer_phone'    => 'required|string|max:20',
            'delivery_type'     => 'required|in:delivery,pickup',
            'payment_method'    => 'nullable|string|max:40',
            'observations'      => 'nullable|string|max:300',

            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|integer',
            'items.*.quantity'  => 'required|integer|min:1|max:999',
            'items.*.observation' => 'nullable|string|max:100',

            // endereço obrigatório apenas se delivery
            'address'               => 'required_if:delivery_type,delivery|array',
            'address.street'        => 'required_if:delivery_type,delivery|string',
            'address.number'        => 'required_if:delivery_type,delivery|string',
            'address.complement'    => 'nullable|string',
            'address.neighborhood'  => 'required_if:delivery_type,delivery|string',
            'address.city'          => 'required_if:delivery_type,delivery|string',
            'address.zip'           => 'nullable|string|max:9',
        ];
    }
}
```

### Critério de conclusão
- `POST /api/client/orders` cria pedido e retorna `whatsapp_url` válida
- URL do WhatsApp abre com mensagem formatada corretamente
- Pedido com produto inativo retorna erro 422
- Pedido abaixo do mínimo retorna erro 422 com mensagem clara
- Admin vê pedido no painel com status `new`

---

## 11. Feature: Store (configurações da loja)

### 11.1 Admin\StoreController

**Arquivo:** `app/Http/Controllers/Api/Admin/StoreController.php`

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Services\ImageService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private ImageService $imageService) {}

    private function store()
    {
        return app('current_company')->stores()->firstOrFail();
    }

    public function show()
    {
        return new StoreResource($this->store());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name'                => 'string|max:80',
            'phone'               => 'nullable|string|max:20',
            'whatsapp'            => 'nullable|string|max:20',
            'email'               => 'nullable|email',
            'instagram'           => 'nullable|string|max:60',
            'description'         => 'nullable|string|max:500',
            'delivery_fee'        => 'nullable|numeric|min:0',
            'min_order_value'     => 'nullable|numeric|min:0',
            'delivery_radius_km'  => 'nullable|integer|min:1',
            'accepts_delivery'    => 'boolean',
            'accepts_pickup'      => 'boolean',
            'business_hours'      => 'nullable|array',
            // endereço
            'address_street'      => 'nullable|string',
            'address_number'      => 'nullable|string',
            'address_complement'  => 'nullable|string',
            'address_neighborhood'=> 'nullable|string',
            'address_city'        => 'nullable|string',
            'address_state'       => 'nullable|string|max:2',
            'address_zip'         => 'nullable|string|max:9',
        ]);

        $this->store()->update($data);
        return new StoreResource($this->store()->fresh());
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|mimes:jpeg,png,webp|max:2048']);

        $url = $this->imageService->uploadLogo(
            $request->file('logo'),
            app('current_company')->id,
        );

        $this->store()->update(['logo_url' => $url]);
        return response()->json(['logo_url' => $url]);
    }
}
```

---

## 12. Pipeline de imagens

### 12.1 ImageService

**Arquivo:** `app/Services/ImageService.php`

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Processa imagem do produto em 3 variantes e salva no R2.
     * Retorna array com as 3 URLs prontas para salvar no model.
     */
    public function processProductImage(UploadedFile $file, string $companyId, int $productId): array
    {
        $image = $this->manager->read($file->getPathname());

        $variants = [
            'thumb' => ['size' => 300,  'quality' => 80, 'key' => 'image_thumb_url'],
            'card'  => ['size' => 600,  'quality' => 82, 'key' => 'image_card_url'],
            'full'  => ['size' => 1200, 'quality' => 85, 'key' => 'image_full_url'],
        ];

        $urls = [];

        foreach ($variants as $name => $config) {
            $processed = $this->manager->read($file->getPathname())
                ->scaleDown(width: $config['size'], height: $config['size'])
                ->toJpeg(quality: $config['quality']);

            $path = "products/{$companyId}/{$productId}/{$name}.jpg";

            Storage::disk('s3')->put($path, $processed, 'public');

            $urls[$config['key']] = Storage::disk('s3')->url($path);
        }

        return $urls;
    }

    /**
     * Upload de banner — sem resize (layout do banner depende das dimensões originais).
     * Apenas compressão leve e conversão para JPEG.
     */
    public function uploadBannerImage(UploadedFile $file, string $companyId, string $suffix = 'main'): string
    {
        $processed = $this->manager->read($file->getPathname())
            ->toJpeg(quality: 82);

        $path = "banners/{$companyId}/{$suffix}_" . time() . ".jpg";

        Storage::disk('s3')->put($path, $processed, 'public');

        return Storage::disk('s3')->url($path);
    }

    /**
     * Upload de logo da loja.
     */
    public function uploadLogo(UploadedFile $file, string $companyId): string
    {
        $processed = $this->manager->read($file->getPathname())
            ->scaleDown(width: 400, height: 400)
            ->toJpeg(quality: 85);

        $path = "logos/{$companyId}/logo.jpg";

        Storage::disk('s3')->put($path, $processed, 'public');

        return Storage::disk('s3')->url($path);
    }
}
```

> **Nota:** `scaleDown` respeita proporção e nunca faz upscale — imagem menor que 300px permanece no tamanho original. Comportamento correto para fotos de celular que já vieram comprimidas pelo Flutter.

### Critério de conclusão
- Upload de foto 4MB gera 3 arquivos no R2 (thumb ~15kb, card ~50kb, full ~200kb)
- URLs retornadas são públicas e acessíveis via CDN
- Formato sempre JPEG independente do formato enviado (PNG, WEBP aceitos)

---

## 13. Multiempresa — middleware e resolução de company

### Regras de isolamento

Toda query de dados de negócio **obrigatoriamente** filtra por `company_id`. Nunca buscar por ID sem esse filtro — exemplo correto:

```php
// CORRETO
Product::where('company_id', $company->id)->where('id', $id)->firstOrFail();

// ERRADO — expõe dados de outras companies
Product::find($id);
```

### Teste de isolamento

Criar teste específico que verifica que um request com `X-Company-ID` da company A nunca retorna dados da company B:

```php
it('não vaza dados entre companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $product = Product::factory()->for($companyA)->create();

    $response = $this->withHeader('X-Company-ID', $companyB->id)
        ->getJson("/api/client/products/{$product->slug}");

    $response->assertNotFound();
});
```

---

## 14. Seeders e dados iniciais

### 14.1 DatabaseSeeder

**Arquivo:** `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Company de exemplo
        $company = Company::create([
            'name'      => 'Hortifruti do João',
            'slug'      => 'hortifruti-joao',
            'plan'      => 'pedidos',
            'is_active' => true,
        ]);

        // Admin da company
        User::create([
            'company_id' => $company->id,
            'name'       => 'João Silva',
            'email'      => 'admin@hortifruti.test',
            'password'   => Hash::make('password'),
            'role'       => 'admin',
            'is_active'  => true,
        ]);

        // Store
        Store::create([
            'company_id'     => $company->id,
            'name'           => 'Hortifruti do João — Centro',
            'whatsapp'       => '5541999999999',
            'delivery_fee'   => 5.00,
            'min_order_value'=> 30.00,
            'is_active'      => true,
            'accepts_delivery' => true,
            'accepts_pickup'   => true,
            'business_hours' => [
                'mon' => ['open' => '08:00', 'close' => '18:00'],
                'tue' => ['open' => '08:00', 'close' => '18:00'],
                'wed' => ['open' => '08:00', 'close' => '18:00'],
                'thu' => ['open' => '08:00', 'close' => '18:00'],
                'fri' => ['open' => '08:00', 'close' => '18:00'],
                'sat' => ['open' => '08:00', 'close' => '14:00'],
                'sun' => null,
            ],
        ]);

        // Categorias base
        $categorias = ['Frutas', 'Verduras', 'Legumes', 'Orgânicos', 'Promoções'];
        $cats = [];
        foreach ($categorias as $i => $nome) {
            $cats[$nome] = Category::create([
                'company_id' => $company->id,
                'name'       => $nome,
                'slug'       => \Str::slug($nome),
                'sort_order' => $i,
                'is_active'  => true,
            ]);
        }

        // Produtos de exemplo
        $produtos = [
            ['Banana Nanica',    'frutas',   'kg',  4.99, null,  true],
            ['Maçã Fuji',        'frutas',   'kg',  8.99, 6.99,  true],
            ['Alface Americana', 'verduras', 'un',  3.49, null,  false],
            ['Tomate Italiano',  'legumes',  'kg',  7.99, null,  false],
            ['Morango Bandeja',  'frutas',   'un',  9.99, 7.99,  true],
        ];

        foreach ($produtos as [$nome, $cat, $unit, $price, $promo, $featured]) {
            Product::create([
                'company_id'  => $company->id,
                'category_id' => $cats[ucfirst($cat)]->id,
                'name'        => $nome,
                'slug'        => \Str::slug($nome),
                'unit'        => $unit,
                'price'       => $price,
                'promo_price' => $promo,
                'is_active'   => true,
                'is_featured' => $featured,
                'is_available'=> true,
                'sort_order'  => 0,
            ]);
        }
    }
}
```

```bash
php artisan db:seed
```

---

## 15. Testes

### Estrutura de testes

```
tests/
├── Feature/
│   ├── Auth/
│   │   └── LoginTest.php
│   ├── Client/
│   │   ├── CatalogTest.php
│   │   ├── BannerTest.php
│   │   └── OrderTest.php
│   ├── Admin/
│   │   ├── ProductTest.php
│   │   ├── CategoryTest.php
│   │   └── OrderStatusTest.php
│   └── Isolation/
│       └── CompanyIsolationTest.php
└── Unit/
    ├── WhatsAppServiceTest.php
    └── ImageServiceTest.php
```

### Testes prioritários (implementar antes de avançar)

```php
// tests/Feature/Client/CatalogTest.php

it('retorna categorias ativas da company', function () {
    $company  = Company::factory()->create();
    $active   = Category::factory()->for($company)->create(['is_active' => true]);
    $inactive = Category::factory()->for($company)->create(['is_active' => false]);

    $this->withHeader('X-Company-ID', $company->id)
         ->getJson('/api/client/categories')
         ->assertOk()
         ->assertJsonCount(1, 'data')
         ->assertJsonPath('data.0.id', $active->id);
});

it('busca produtos por nome', function () {
    $company = Company::factory()->has(Store::factory())->create();
    Product::factory()->for($company)->create(['name' => 'Banana Nanica', 'is_active' => true]);
    Product::factory()->for($company)->create(['name' => 'Tomate', 'is_active' => true]);

    $this->withHeader('X-Company-ID', $company->id)
         ->getJson('/api/client/products/search?q=banana')
         ->assertOk()
         ->assertJsonCount(1, 'data');
});

it('cria pedido e retorna url do whatsapp', function () {
    $company = Company::factory()->has(Store::factory(['whatsapp' => '5541999999999']))->create();
    $product = Product::factory()->for($company)->create(['price' => 10.00, 'is_active' => true, 'is_available' => true]);

    $this->withHeader('X-Company-ID', $company->id)
         ->postJson('/api/client/orders', [
             'customer_name'  => 'Maria',
             'customer_phone' => '41988887777',
             'delivery_type'  => 'pickup',
             'items' => [
                 ['product_id' => $product->id, 'quantity' => 2],
             ],
         ])
         ->assertCreated()
         ->assertJsonStructure(['order', 'whatsapp_url']);
});
```

### Rodar testes

```bash
./vendor/bin/pest
./vendor/bin/pest --coverage   # cobertura
./vendor/bin/pest tests/Feature/Isolation  # isolamento entre companies
```

---

## 16. Checklist de entrega

### Infraestrutura
- [ ] Laravel 13 instalado e configurado
- [ ] PostgreSQL conectado
- [ ] R2 conectado e upload funcionando
- [ ] `.env` completo e `.env.example` atualizado

### Banco
- [ ] 9 migrations rodaram sem erro
- [ ] Índices compostos criados e verificados
- [ ] Seeder popula dados de exemplo

### Autenticação
- [ ] Login retorna token Sanctum
- [ ] Token expira em 30 dias
- [ ] Logout invalida token
- [ ] Rota sem token retorna 401

### Catálogo
- [ ] Listar categorias ativas
- [ ] Listar produtos por categoria (paginado)
- [ ] Buscar produto por nome (ilike)
- [ ] Detalhe do produto
- [ ] Produtos em destaque
- [ ] Produtos em promoção (respeita validade)

### Admin
- [ ] CRUD completo de categorias
- [ ] CRUD completo de produtos
- [ ] Upload de imagem gera 3 variantes no R2
- [ ] CRUD de banners com período de exibição
- [ ] Listar pedidos com filtro de status
- [ ] Atualizar status do pedido
- [ ] Atualizar configurações da loja

### Pedidos
- [ ] Criar pedido valida produtos disponíveis
- [ ] Cria snapshot dos itens no `order_items`
- [ ] Respeita pedido mínimo
- [ ] Retorna URL do WhatsApp formatada
- [ ] Mensagem do WhatsApp tem formatação correta

### Isolamento
- [ ] Nenhuma query de negócio sem `company_id`
- [ ] Teste de isolamento entre companies passa
- [ ] Admin não acessa dados de outra company

### Testes
- [ ] Suite Pest configurada
- [ ] Testes de catálogo passando
- [ ] Testes de pedido passando
- [ ] Teste de isolamento passando
- [ ] `--coverage` acima de 70%
