<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formate les indicateurs du tableau de bord du livreur (AR-39).
 */
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
