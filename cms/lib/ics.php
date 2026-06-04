<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 *  lib/ics.php — Ingesta de calendarios ICS (lado servidor)
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Porta a PHP la lógica ya probada del kiosko (antes en html/index.html):
 *  parser ICS, resolución de TZIDs propietarios de Microsoft, expansión de
 *  reglas RRULE dentro de la semana, y clasificación por categoría.
 *
 *  Al ejecutarse en el servidor se ELIMINAN los proxies CORS del navegador:
 *  el ICS se descarga con cURL directo y se cachea ~2 min.
 * ════════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config.php';

const DIA_ES = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
const MES_ES_SHORT = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

// ── Zona horaria fija de Galápagos (UTC-6, sin DST) ─────────────────────────
function gtz(): DateTimeZone { return new DateTimeZone(GALAPAGOS_TZ); }

// ════════════════════════════════════════════════════════════════════════════
//  Parseo de fechas ICS + TZIDs Microsoft
// ════════════════════════════════════════════════════════════════════════════

function resolveMicrosoftTZID(string $keyPart): string {
    if (preg_match('/TZID="([^"]+)"/', $keyPart, $m) || preg_match('/TZID=([^;:]+)/', $keyPart, $m)) {
        $tz = strtolower($m[1]);
    } else {
        return '-06:00';
    }
    if (str_contains($tz,'utc') || str_contains($tz,'greenwich') || str_contains($tz,'gmt standard')) return '+00:00';
    if (str_contains($tz,'central america') || str_contains($tz,'canada central'))                    return '-06:00';
    if (str_contains($tz,'central standard') || str_contains($tz,'central time'))                      return '-06:00';
    if (str_contains($tz,'sa pacific') || str_contains($tz,'colombia') || str_contains($tz,'lima')
        || str_contains($tz,'bogota') || str_contains($tz,'guayaquil'))                                return '-05:00';
    if (str_contains($tz,'eastern'))                                                                   return '-05:00';
    return '-06:00';
}

function parseICSDate(string $value, string $keyPart): DateTimeImmutable {
    $isDate = str_contains($keyPart, 'VALUE=DATE') || (strlen($value) === 8 && !str_contains($value, 'T'));
    $clean  = str_replace('Z', '', $value);
    if ($isDate) {
        return new DateTimeImmutable(substr($clean,0,4).'-'.substr($clean,4,2).'-'.substr($clean,6,2).'T00:00:00-06:00');
    }
    $y = substr($clean,0,4); $mo = substr($clean,4,2); $d = substr($clean,6,2);
    $h = substr($clean,9,2); $mi = substr($clean,11,2); $s = substr($clean,13,2) ?: '00';
    if (str_ends_with($value, 'Z')) {
        return new DateTimeImmutable("$y-$mo-{$d}T$h:$mi:{$s}Z");
    }
    $offset = resolveMicrosoftTZID($keyPart);
    return new DateTimeImmutable("$y-$mo-{$d}T$h:$mi:$s$offset");
}

// ════════════════════════════════════════════════════════════════════════════
//  Parser ICS → VEVENTs
// ════════════════════════════════════════════════════════════════════════════

/** Parte una línea ICS en [keyPart, value] por el primer ':' FUERA de comillas
 *  (un TZID como "tzone://Microsoft/Utc" contiene ':' que no es separador). */
function splitProp(string $line): ?array {
    $inQuotes = false;
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($ch === '"') $inQuotes = !$inQuotes;
        elseif ($ch === ':' && !$inQuotes) return [substr($line, 0, $i), substr($line, $i + 1)];
    }
    return null;
}

function parseICS(string $text): array {
    $vevents = [];
    $blocks  = explode('BEGIN:VEVENT', $text);
    array_shift($blocks);
    foreach ($blocks as $block) {
        $raw      = explode('END:VEVENT', $block)[0];
        $unfolded = preg_replace("/\r?\n[ \t]/", '', $raw);   // desdoblar líneas continuadas
        $lines    = preg_split("/\r?\n/", $unfolded);
        $ev = [];
        try {
        foreach ($lines as $line) {
            $parts = splitProp($line);
            if ($parts === null) continue;
            [$keyPart, $value] = $parts;
            $key = trim(explode(';', $keyPart)[0]);
            switch ($key) {
                case 'SUMMARY':     $ev['summary']  = trim(str_replace(['\\n','\\,'], [' ', ','], $value)); break;
                case 'DTSTART':     $ev['dtstart']  = parseICSDate($value, $keyPart); break;
                case 'DTEND':       $ev['dtend']    = parseICSDate($value, $keyPart); break;
                case 'LOCATION':    $ev['location'] = trim(str_replace(['\\n','\\,'], [' ', ','], $value)); break;
                case 'DESCRIPTION': $ev['description'] = trim(str_replace(['\\n','\\,'], ["\n", ','], $value)); break;
                case 'RRULE':       $ev['rrule']    = $value; break;
            }
        }
        } catch (Throwable $e) {
            continue;   // saltar VEVENT malformado en lugar de abortar todo
        }
        if (!empty($ev['summary']) && !empty($ev['dtstart'])) $vevents[] = $ev;
    }
    return $vevents;
}

// ════════════════════════════════════════════════════════════════════════════
//  Límites de la semana + expansión RRULE
// ════════════════════════════════════════════════════════════════════════════

function getWeekBounds(): array {
    $now = new DateTimeImmutable('now', gtz());
    $dow = (int)$now->format('w');                 // 0=Dom .. 6=Sáb
    $diffToMon = $dow === 0 ? -6 : 1 - $dow;
    $monday = $now->modify("$diffToMon days")->setTime(0, 0, 0);
    $sunday = $monday->modify('+6 days')->setTime(23, 59, 59);
    return [$monday, $sunday];
}

function expandRRule(array $ev, DateTimeImmutable $from, DateTimeImmutable $to): array {
    $rule = [];
    foreach (explode(';', $ev['rrule']) as $p) {
        [$k, $v] = array_pad(explode('=', $p, 2), 2, null);
        $rule[$k] = $v;
    }
    $instances = [];
    $freq     = $rule['FREQ'] ?? '';
    $until    = !empty($rule['UNTIL']) ? parseICSDate($rule['UNTIL'], '') : $to;
    $count    = !empty($rule['COUNT']) ? (int)$rule['COUNT'] : 200;
    $interval = !empty($rule['INTERVAL']) ? (int)$rule['INTERVAL'] : 1;
    $byDay    = !empty($rule['BYDAY']) ? explode(',', $rule['BYDAY']) : null;
    $dayMap   = ['SU'=>0,'MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6];

    $cursor = $ev['dtstart'];
    $gen    = 0;
    $dur    = !empty($ev['dtend']) ? ($ev['dtend']->getTimestamp() - $ev['dtstart']->getTimestamp()) : 3600;

    while ($cursor <= $until && $cursor <= $to && $gen < $count) {
        if ($cursor >= $from) {
            $inc = true;
            if ($byDay) {
                $cd  = (int)$cursor->format('w');
                $inc = false;
                foreach ($byDay as $d) {
                    $dd = preg_replace('/[^A-Z]/', '', $d);   // quita prefijos tipo "1MO"
                    if (isset($dayMap[$dd]) && $dayMap[$dd] === $cd) { $inc = true; break; }
                }
            }
            if ($inc) {
                $inst = $ev;
                $inst['dtstart']    = $cursor;
                $inst['dtend']      = $cursor->modify("+$dur seconds");
                $inst['_recurring'] = true;
                unset($inst['rrule']);
                $instances[] = $inst;
                $gen++;
            }
        }
        if ($freq === 'DAILY')        $cursor = $cursor->modify('+1 day');
        elseif ($freq === 'WEEKLY')   $cursor = $cursor->modify('+' . (($byDay && count($byDay) > 1) ? 1 : 7 * $interval) . ' days');
        elseif ($freq === 'MONTHLY')  $cursor = $cursor->modify("+$interval month");
        else                          $cursor = $cursor->modify('+1 day');
    }
    return $instances;
}

/** Ventana de "próximos eventos": desde hoy hasta hoy + N días. */
function getUpcomingBounds(int $days = 60): array {
    $now = new DateTimeImmutable('now', gtz());
    return [$now->setTime(0, 0, 0), $now->modify("+$days days")->setTime(23, 59, 59)];
}

/**
 * Expande los VEVENTs dentro de [$from, $to] y deduplica.
 * Los recurrentes se muestran SOLO en su próxima ocurrencia (no se repiten).
 */
function expandEvents(array $vevents, DateTimeImmutable $from, DateTimeImmutable $to): array {
    $expanded = [];
    foreach ($vevents as $ev) {
        if (!empty($ev['rrule'])) {
            $insts = expandRRule($ev, $from, $to);
            if ($insts) $expanded[] = $insts[0];   // próxima ocurrencia únicamente
        } elseif ($ev['dtstart'] >= $from && $ev['dtstart'] <= $to) {
            $expanded[] = $ev;
        }
    }
    $seen = [];
    $out  = [];
    foreach ($expanded as $ev) {
        $key = $ev['summary'] . '|' . $ev['dtstart']->format('Y-m-d\TH:i');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $ev;
    }
    usort($out, fn($a, $b) => $a['dtstart'] <=> $b['dtstart']);
    return $out;
}

// ════════════════════════════════════════════════════════════════════════════
//  Clasificación + limpieza de texto
// ════════════════════════════════════════════════════════════════════════════

/** Mapea el título a una categoría de la paleta del diseño Mosaico. */
function detectCat(string $summary): string {
    $s = mb_strtolower($summary);
    $map = [
        'mar'       => ['buceo','marin','océano','oceano','conservaci','biodiversidad','ciencia','tortuga','lobos','arrecife','snorkel'],
        'deportes'  => ['training','alfa','basket','peloteo','jiu','halterofilia','fútbol','futbol','deporte','running','crossfit','atletismo','natación','natacion','vóley','voley'],
        'bienestar' => ['yoga','salud','mental','bienestar','mindfulness','medita','nutrici'],
        'arte'      => ['coro','creative','cine','arte','cultura','música','musica','teatro','danza','pelis','foto','pintura'],
        'letras'    => ['debate','writes','biblioteca','académic','academic','coloquio','seminario','charla','taller','conferencia','lectura','ensayo','libro'],
    ];
    foreach ($map as $cat => $kws) {
        foreach ($kws as $kw) if (str_contains($s, $kw)) return $cat;
    }
    return 'letras';
}

/** Distingue un club (recurrente) de un evento puntual. */
function detectEventType(array $ev): string {
    $s = mb_strtolower($ev['summary']);
    if (!empty($ev['_recurring']) || str_contains($s, 'club') || str_contains($s, 'training')) return 'club';
    return 'evento';
}

/** Normaliza un nombre para comparar club ↔ evento (sin acentos, sin "club/de", etc.). */
function normalizeClubName(string $s): string {
    $s = mb_strtolower(stripEmojis($s));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $stop = ['club'=>1,'de'=>1,'del'=>1,'la'=>1,'el'=>1,'los'=>1,'las'=>1];
    $words = array_filter(explode(' ', $s), fn($w) => $w !== '' && !isset($stop[$w]));
    return trim(implode(' ', $words));
}

/** ¿El título del evento corresponde a alguno de los clubes (ya listados en el CMS)? */
function matchesClubName(string $title, array $clubNorms): bool {
    $n = normalizeClubName($title);
    if ($n === '') return false;
    foreach ($clubNorms as $cn) {
        if ($cn !== '' && ($n === $cn || str_contains($n, $cn) || str_contains($cn, $n))) return true;
    }
    return false;
}

function stripEmojis(?string $s): string {
    if (!$s) return '';
    $s = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{200D}\x{20E3}]/u', '', $s);
    return trim(preg_replace('/\s{2,}/', ' ', $s));
}

/** Limpia un título para mostrar: sin emojis ni asteriscos/markdown de WhatsApp. */
function cleanTitle(?string $s): string {
    $s = stripEmojis($s);
    $s = trim($s, " *_\"“”\t\n");
    $s = preg_replace('/\*+/', '', $s);
    return $s !== '' ? trim($s) : 'Evento';
}

// ── Formato (es-EC, hora Galápagos) ─────────────────────────────────────────
function fmtHM(DateTimeImmutable $d): string  { return $d->setTimezone(gtz())->format('H:i'); }
function dowEs(DateTimeImmutable $d): string  { return DIA_ES[(int)$d->setTimezone(gtz())->format('w')]; }
function dayDateEs(DateTimeImmutable $d): string {
    $d = $d->setTimezone(gtz());
    return (int)$d->format('j') . ' ' . MES_ES_SHORT[(int)$d->format('n') - 1];
}

// ════════════════════════════════════════════════════════════════════════════
//  Descarga (cURL directo, sin proxies) + caché del ICS crudo
// ════════════════════════════════════════════════════════════════════════════

function fetchCalendarRaw(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'KioskoUSFQ/2.0 (+https://api.julianmaya.com)',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    return (is_string($body) && str_contains($body, 'BEGIN:VCALENDAR')) ? $body : null;
}

/**
 * Obtiene los eventos PRÓXIMOS de TODOS los calendarios habilitados (hoy → +N días),
 * ya expandidos. Cachea el ICS crudo combinado ~2 min y, si la descarga falla,
 * reusa la caché aunque esté vencida (resiliencia).
 */
function fetchUpcomingEvents(int $days = 60): array {
    $cache = ICS_CACHE_FILE;
    $combined = '';

    if (file_exists($cache) && (time() - filemtime($cache) < 120)) {
        $combined = (string)file_get_contents($cache);
    } else {
        foreach (loadCalendars() as $cal) {
            if (!($cal['enabled'] ?? true)) continue;
            $raw = fetchCalendarRaw($cal['url'] ?? '');
            if ($raw) $combined .= "\n" . $raw;
        }
        if ($combined !== '') {
            file_put_contents($cache, $combined, LOCK_EX);
        } elseif (file_exists($cache)) {
            $combined = (string)file_get_contents($cache);   // fallback a caché vencida
        }
    }

    if ($combined === '') return [];

    $vevents = parseICS($combined);
    // Excluir eventos marcados como club en Outlook (#CLUB en la descripción):
    // los clubes se gestionan en el CMS y se muestran aparte en el kiosko.
    $vevents = array_values(array_filter($vevents, fn($ev) => !isClubTagged($ev)));
    [$from, $to] = getUpcomingBounds($days);
    return expandEvents($vevents, $from, $to);
}

/** ¿El evento trae el hashtag #CLUB (en descripción o título)? → se ignora en la agenda. */
function isClubTagged(array $ev): bool {
    $haystack = ($ev['description'] ?? '') . ' ' . ($ev['summary'] ?? '');
    return stripos($haystack, '#club') !== false;
}
