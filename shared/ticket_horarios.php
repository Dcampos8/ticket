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
        $resultado = $conexion->query('SELECT usuario, dia_semana, activo, hora_inicio, hora_fin, comida_inicio, comida_fin FROM ticket_horarios_atencion');
        if ($resultado) {
            while ($fila = $resultado->fetch_assoc()) {
                $horarios[(string) $fila['usuario']][(int) $fila['dia_semana']] = [
                    'activo' => (bool) $fila['activo'],
                    'hora_inicio' => (string) $fila['hora_inicio'],
                    'hora_fin' => (string) $fila['hora_fin'],
                    'comida_inicio' => (string) ($fila['comida_inicio'] ?? ''),
                    'comida_fin' => (string) ($fila['comida_fin'] ?? ''),
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

function segmentosHorarioTicket(DateTimeImmutable $dia, array $turno): array
{
    if (empty($turno['activo'])) return [];
    [$horaInicio, $minutoInicio] = array_map('intval', explode(':', substr((string) $turno['hora_inicio'], 0, 5)));
    [$horaFin, $minutoFin] = array_map('intval', explode(':', substr((string) $turno['hora_fin'], 0, 5)));
    $apertura = $dia->setTime($horaInicio, $minutoInicio, 0);
    $cierre = $dia->setTime($horaFin, $minutoFin, 0);
    $comidaInicioRaw = trim((string) ($turno['comida_inicio'] ?? ''));
    $comidaFinRaw = trim((string) ($turno['comida_fin'] ?? ''));
    if ($comidaInicioRaw === '' || $comidaFinRaw === '') return [[$apertura, $cierre]];
    [$horaComidaInicio, $minutoComidaInicio] = array_map('intval', explode(':', substr($comidaInicioRaw, 0, 5)));
    [$horaComidaFin, $minutoComidaFin] = array_map('intval', explode(':', substr($comidaFinRaw, 0, 5)));
    $comidaInicio = $dia->setTime($horaComidaInicio, $minutoComidaInicio, 0);
    $comidaFin = $dia->setTime($horaComidaFin, $minutoComidaFin, 0);
    if ($comidaInicio <= $apertura || $comidaFin >= $cierre || $comidaInicio >= $comidaFin) return [[$apertura, $cierre]];
    return [[$apertura, $comidaInicio], [$comidaFin, $cierre]];
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
        foreach (segmentosHorarioTicket($cursor->setTime(0, 0), (array) $turno) as [$apertura, $cierre]) {
            if ($cursor < $apertura) $cursor = $apertura;
            if ($cursor >= $cierre) continue;
            $disponibles = $cierre->getTimestamp() - $cursor->getTimestamp();
            if ($restante <= $disponibles) return $cursor->modify('+' . $restante . ' seconds');
            $restante -= $disponibles;
            $cursor = $cierre;
        }
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
        foreach (segmentosHorarioTicket($diaActual, (array) $turno) as [$apertura, $cierre]) {
            $desde = $inicio > $apertura ? $inicio : $apertura;
            $hasta = $fin < $cierre ? $fin : $cierre;
            if ($hasta > $desde) $segundos += $hasta->getTimestamp() - $desde->getTimestamp();
        }
        $diaActual = $diaActual->modify('tomorrow');
    }
    return (int) floor($segundos / 60);
}
