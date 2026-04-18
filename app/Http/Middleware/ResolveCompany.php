<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->user()?->company_id ?: $request->header('X-Company-ID');

        $company = Company::query()
            ->whereKey($companyId)
            ->where('is_active', true)
            ->first();

        if (! $company) {
            return new JsonResponse(['message' => 'Loja não encontrada.'], 404);
        }

        $request->attributes->set('current_company', $company);
        app()->instance('current_company', $company);

        return $next($request);
    }
}
