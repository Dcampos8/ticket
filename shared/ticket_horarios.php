<?php
declare(strict_types=1);

const TICKET_HORARIO_BASE = '__base__';

function horarioTicketPredeterminado(): array
{
    $horario = [];
    for ($dia = 1; $dia <= 7; $dia++) {
        $horario[$dia] = ['activo' => $dia <= 5, 'hora_inicio' => '09:00:00', 'hora_fin' => '19:00:00'];
    }
    return $horario;
}

function cargarHorariosTicket(mysqli $conexion): array
{
    $horarios = [];
    try {
        $resultado = $conexion->query('SELECT usuario, dia_semana, activo, hora_inicio, hora_fin FROM ticket_horarios_atencion');
        if ($resultado) {
            while ($fila = $resultado->fetch_assoc()) {
                $horarios[(string) $fila['usuario']][(int) $fila['dia_semana']] = [
                    'activo' => (bool) $fila['activo'],
                    'hora_inicio' => (string) $fila['hora_inicio'],
                    'hora_fin' => (string) $fila['hora_fin'],
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('No se pudieron cargar los horarios SLA; se usa el horario base: ' . $e->getMessage());
    }
    $predeterminado = horarioTicketPredeterminado();
    $horarios[TICKET_HORARIO_BASE] = array_replace($predeterminado, $horarios[TICKET_HORARIO_BASE] ?? []);
    return $horarios;
}

function horarioParaTicket(array $horarios, ?string $usuario): array
{
    $base = $horarios[TICKET_HORARIO_BASE] ?? horarioTicketPredeterminado();
    if ($usuario === null || trim($usuario) === '') return $base;
    return array_replace($base, $horarios[$usuario] ?? []);
}

function calcularVencimientoTicketLaborable(DateTimeImmutable $inicio, int $minutosObjetivo, array $horario): DateTimeImmutable
{
    if ($minutosObjetivo <= 0) return $inicio;
    $restante = $minutosObjetivo * 60;
    $cursor = $inicio;
    $guardia = 0;
    while ($restante > 0 && $guardia++ < 3660) {
        $dia = (int) $cursor->format('N');
        $turno = $horario[$dia] ?? null;
        if (empty($turno['activo'])) {
            $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
            continue;
        }
        [$horaInicio, $minutoInicio] = array_map('intval', explode(':', substr((string) $turno['hora_inicio'], 0, 5)));
        [$horaFin, $minutoFin] = array_map('intval', explode(':', substr((string) $turno['hora_fin'], 0, 5)));
        $apertura = $cursor->setTime($horaInicio, $minutoInicio, 0);
        $cierre = $cursor->setTime($horaFin, $minutoFin, 0);
        if ($cursor < $apertura) $cursor = $apertura;
        if ($cursor >= $cierre) {
            $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
            continue;
        }
        $disponibles = $cierre->getTimestamp() - $cursor->getTimestamp();
        if ($restante <= $disponibles) return $cursor->modify('+' . $restante . ' seconds');
        $restante -= $disponibles;
        $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
    }
    return $cursor;
}

function minutosLaborablesEntre(DateTimeImmutable $inicio, DateTimeImmutable $fin, array $horario): int
{
    if ($fin <= $inicio) return 0;
    $segundos = 0;
    $diaActual = $inicio->setTime(0, 0);
    $ultimoDia = $fin->setTime(0, 0);
    $guardia = 0;
    while ($diaActual <= $ultimoDia && $guardia++ < 3660) {
        $turno = $horario[(int) $diaActual->format('N')] ?? null;
        if (!empty($turno['activo'])) {
            [$horaInicio, $minutoInicio] = array_map('intval', explode(':', substr((string) $turno['hora_inicio'], 0, 5)));
            [$horaFin, $minutoFin] = array_map('intval', explode(':', substr((string) $turno['hora_fin'], 0, 5)));
            $apertura = $diaActual->setTime($horaInicio, $minutoInicio, 0);
            $cierre = $diaActual->setTime($horaFin, $minutoFin, 0);
            $desde = $inicio > $apertura ? $inicio : $apertura;
            $hasta = $fin < $cierre ? $fin : $cierre;
            if ($hasta > $desde) $segundos += $hasta->getTimestamp() - $desde->getTimestamp();
        }
        $diaActual = $diaActual->modify('tomorrow');
    }
    return (int) floor($segundos / 60);
}
