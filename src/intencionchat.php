<?php
declare(strict_types=1);

/**
 * Clasifica la pregunta en una lista CERRADA de intenciones (sin modelo, solo reglas de texto).
 * Intenciones: saludo, gracias, regla, vencimientos, stock, apto, porque, plato, ayuda,
 *              manipulacion, raro, fuera_de_tema.
 */
final class IntencionChat
{
    public static function normalizar(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/[^a-z0-9\s]/', ' ', $t) ?? '';
        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }

    /**
     * @param array $insumos [['insumo_id','nombre'], ...]
     * @param array $platos  [['plato_id','nombre'], ...]
     * @return array{intencion:string, insumo:?array, plato:?array, regla:?string}
     */
    public static function clasificar(string $mensaje, array $insumos, array $platos): array
    {
        $n = self::normalizar($mensaje);
        $r = ['intencion' => 'fuera_de_tema', 'insumo' => null, 'plato' => null, 'regla' => null];

        if (self::esRaro($n)) {
            $r['intencion'] = 'raro';
            return $r;
        }
        if (self::esManipulacion($n)) {
            $r['intencion'] = 'manipulacion';
            return $r;
        }
        if (preg_match('/\b(?:regla|r)\s?(\d{1,2})\b/', $n, $m)) {
            $r['intencion'] = 'regla';
            $r['regla'] = 'R' . (int)$m[1];
            return $r;
        }
        if (preg_match('/\b(gracias|te agradezco|muy amable|buen trabajo|genial|excelente|te quiero|eres el mejor)\b/', $n)) {
            $r['intencion'] = 'gracias';
            return $r;
        }
        if (count(explode(' ', $n)) <= 4 && preg_match('/^(hola|holi|hey|buenas|buenos dias|buen dia|que tal)\b/', $n)) {
            $r['intencion'] = 'saludo';
            return $r;
        }

        $r['insumo'] = self::buscarInsumo($n, $insumos);
        $r['plato']  = self::buscarPlato($n, $platos);

        if (preg_match('/\b(como (uso|se usa|funciona|registro|cargo|ingreso|genero)|que puedes|que haces|que sabes|ayuda)\b/', $n)) {
            $r['intencion'] = 'ayuda';
        } elseif (preg_match('/\b(por ?que|explica|explicame|justifica|motivo|razon|por cual)\b/', $n)) {
            $r['intencion'] = 'porque';
        } elseif (preg_match('/\b(apto|aptos|puedo (usar|servir|cocinar|utilizar)|se puede (usar|servir|cocinar)|esta bien|buen estado|sirve|descart\w*|botar|tirar|desechar|inocuo|seguro)\b/', $n)) {
            $r['intencion'] = 'apto';
        } elseif (preg_match('/\b(vence|vencen|vencer|vencimiento|vencido|vencidos|caduca|caducan|caducidad|vida util|dias de vida)\b/', $n)) {
            $r['intencion'] = 'vencimientos';
        } elseif ($r['plato'] !== null && preg_match('/\b(porcion|porciones|receta|lleva|ingrediente|ingredientes|preparar|plato)\b/', $n)) {
            $r['intencion'] = 'plato';
        } elseif (preg_match('/\b(cuanto|cuanta|cuantos|cuantas|queda|quedan|stock|inventario|tengo|tenemos|hay|disponible|cobertura|alcanza)\b/', $n)) {
            $r['intencion'] = 'stock';
        } elseif (preg_match('/\b(reglas|formulario|menu de emergencia|plan de 3 dias|instrucciones)\b/', $n)) {
            $r['intencion'] = 'ayuda';
        } elseif ($r['insumo'] !== null) {
            $r['intencion'] = 'stock';
        } elseif ($r['plato'] !== null) {
            $r['intencion'] = 'plato';
        }
        return $r;
    }

    private static function esRaro(string $n): bool
    {
        if ($n === '' || strlen($n) < 2) {
            return true;
        }
        if (preg_match('/(.)\1{5,}/', $n)) {
            return true;
        }
        foreach (explode(' ', $n) as $p) {
            if (strlen($p) >= 22 || (strlen($p) >= 5 && !preg_match('/[aeiou]/', $p) && !preg_match('/\d/', $p))) {
                return true;
            }
        }
        return false;
    }

    private static function esManipulacion(string $n): bool
    {
        return (bool)(
            preg_match('/\b(ignora|ignorar|olvida|olvidate|omite|salta\w*|desactiva)\b.*\b(regla|reglas|instrucciones|sistema|restricciones|anterior)\b/', $n)
            || preg_match('/\b(sirvelo|usalo|cocinalo|ofrecelo)\b.*\b(igual|de todas formas)\b/', $n)
            || preg_match('/\b(decide tu|tu decides|segun tu opinion|bajo tu criterio|dime tu que hago)\b/', $n)
            || preg_match('/\b(actua como|finge|eres ahora|system prompt|prompt del sistema)\b/', $n)
        );
    }

    private static function buscarInsumo(string $n, array $insumos): ?array
    {
        $primeras = [];
        foreach ($insumos as $i) {
            $p = explode(' ', self::normalizar($i['nombre']))[0];
            $primeras[$p] = ($primeras[$p] ?? 0) + 1;
        }
        $mejor = null;
        $mejorLen = 0;
        foreach ($insumos as $i) {
            $nom = self::normalizar($i['nombre']);
            $cands = [$nom];
            $pal = explode(' ', $nom);
            if (count($pal) > 1 && strlen($pal[0]) >= 4 && $primeras[$pal[0]] === 1) {
                $cands[] = $pal[0];
            }
            foreach ($cands as $c) {
                if (preg_match('/\b' . preg_quote($c, '/') . '(s|es)?\b/', $n) && strlen($c) > $mejorLen) {
                    $mejor = $i;
                    $mejorLen = strlen($c);
                }
            }
        }
        return $mejor;
    }

    private static function buscarPlato(string $n, array $platos): ?array
    {
        $vacias = ['con', 'de', 'del', 'la', 'el', 'al', 'en', 'promocion'];
        $tokens = [];
        $frecuencia = [];
        foreach ($platos as $p) {
            $t = array_values(array_filter(explode(' ', self::normalizar($p['nombre'])),
                static fn(string $x): bool => strlen($x) >= 4 && !in_array($x, $vacias, true)));
            $tokens[$p['plato_id']] = $t;
            foreach (array_unique($t) as $x) {
                $frecuencia[$x] = ($frecuencia[$x] ?? 0) + 1;
            }
        }
        $mejor = null;
        $mejorN = 0;
        foreach ($platos as $p) {
            $coinc = array_filter($tokens[$p['plato_id']], static fn(string $x): bool => (bool)preg_match('/\b' . preg_quote($x, '/') . '(s|es)?\b/', $n));
            $unico = array_filter($coinc, static fn(string $x): bool => $frecuencia[$x] === 1);
            if ((count($coinc) >= 2 || $unico !== []) && count($coinc) > $mejorN) {
                $mejor = $p;
                $mejorN = count($coinc);
            }
        }
        return $mejor;
    }
}
