<?php

namespace App\Livewire\Concerns;

use App\Support\Valida;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/** Barra para elegir el día (‹ fecha › Hoy), como dayBarHTML() */
trait ConDia
{
    #[Url(as: 'dia')]
    public string $dia = '';

    public function mountConDia(): void
    {
        if (! $this->diaValido($this->dia)) {
            $this->dia = today()->toDateString();
        }
    }

    public function updatedDia(): void
    {
        if (! $this->diaValido($this->dia)) {
            $this->dia = today()->toDateString();
        }
    }

    private function diaValido(string $d): bool
    {
        return Valida::fecha($d) && $d <= today()->toDateString();
    }

    public function irDia(int $delta): void
    {
        $d = Carbon::parse($this->dia)->addDays($delta)->toDateString();
        $this->dia = $d > today()->toDateString() ? today()->toDateString() : $d;
    }

    public function hoy(): void
    {
        $this->dia = today()->toDateString();
    }

    public function esHoy(): bool
    {
        return $this->dia === today()->toDateString();
    }
}
