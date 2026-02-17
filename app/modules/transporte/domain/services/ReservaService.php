<?php

final class ReservaService
{
    public function calcularUrgencia(?string $dataPartida): array
    {
        // Coloque aqui a regra de urgencia da reserva.
        return ['urgencia' => 'Media', 'horas_antecedencia' => null];
    }
}
